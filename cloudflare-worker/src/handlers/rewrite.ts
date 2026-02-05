/**
 * Rewrite Handler
 *
 * Handles incoming rewrite requests from WordPress
 * @since 2.0.0
 */

import type { Context } from 'hono';
import type {
  Env,
  ApiResponse,
  RewriteRequest,
  RewriteResponse,
  ItemWorkflowParams,
} from '../types';
import { generateUUID } from '../utils/auth';

/**
 * Handle POST /api/rewrite
 *
 * Accepts a rewrite request and dispatches it to the ItemWorkflow
 */
export async function handleRewrite(c: Context<{ Bindings: Env }>): Promise<Response> {
  const env = c.env;

  try {
    // Parse request body
    const body = await c.req.json<RewriteRequest>();

    // Validate required fields
    if (!body.task_id || !body.callback_url || !body.payload) {
      return c.json<ApiResponse<RewriteResponse>>(
        {
          success: false,
          error: {
            code: 'INVALID_REQUEST',
            message: 'Missing required fields: task_id, callback_url, payload',
          },
        },
        400
      );
    }

    // Validate payload
    const { payload } = body;
    if (!payload.source_url && !payload.source_content) {
      return c.json<ApiResponse<RewriteResponse>>(
        {
          success: false,
          error: {
            code: 'INVALID_REQUEST',
            message: 'Either source_url or source_content is required',
          },
        },
        400
      );
    }

    // Prepare workflow parameters
    const workflowParams: ItemWorkflowParams = {
      item_id: payload.item_id || 0,
      feed_id: 0, // Manual rewrite, no feed
      source_url: payload.source_url || '',
      source_content: payload.source_content,
      language: payload.language || 'ko',
      ai_provider: payload.ai_provider || 'chatgpt',
      ai_model: payload.ai_model,
      callback_url: body.callback_url,
      callback_secret: body.callback_secret,
      options: {
        auto_publish: true,
        publish_threshold: 8,
        generate_images: true,
      },
    };

    // Save task to D1 for tracking
    await saveTask(env, body.task_id, workflowParams);

    // Dispatch to ItemWorkflow
    const instance = await env.ITEM_WORKFLOW.create({
      id: body.task_id,
      params: workflowParams,
    });

    console.log(`[Rewrite] Task ${body.task_id} dispatched to ItemWorkflow: ${instance.id}`);

    // Return immediately with 202 Accepted
    return c.json<ApiResponse<RewriteResponse>>(
      {
        success: true,
        data: {
          success: true,
          task_id: body.task_id,
          message: 'Rewrite task accepted and queued',
          estimated_time_seconds: estimateProcessingTime(payload),
        },
      },
      202
    );
  } catch (error) {
    console.error('[Rewrite] Error:', error);

    return c.json<ApiResponse<RewriteResponse>>(
      {
        success: false,
        error: {
          code: 'INTERNAL_ERROR',
          message: error instanceof Error ? error.message : 'Unknown error occurred',
        },
      },
      500
    );
  }
}

/**
 * Save task to D1 for tracking
 */
async function saveTask(env: Env, taskId: string, params: ItemWorkflowParams): Promise<void> {
  try {
    await env.DB.prepare(
      `
      INSERT INTO tasks (task_id, item_id, status, params, created_at, updated_at)
      VALUES (?, ?, 'pending', ?, datetime('now'), datetime('now'))
      ON CONFLICT(task_id) DO UPDATE SET
        status = 'pending',
        params = ?,
        updated_at = datetime('now')
    `
    )
      .bind(taskId, params.item_id || null, JSON.stringify(params), JSON.stringify(params))
      .run();
  } catch (error) {
    console.error('[Rewrite] Failed to save task:', error);
    // Don't throw - task tracking is not critical
  }
}

/**
 * Estimate processing time based on content
 */
function estimateProcessingTime(payload: RewriteRequest['payload']): number {
  // Base time: 60 seconds
  let estimate = 60;

  // Add time for URL fetch
  if (payload.source_url) {
    estimate += 10;
  }

  // Add time for content length (rough estimate)
  if (payload.source_content) {
    const wordCount = payload.source_content.split(/\s+/).length;
    estimate += Math.min(wordCount / 100, 60); // Cap at 60 additional seconds
  }

  // Add time for image generation
  estimate += 30;

  return Math.round(estimate);
}
