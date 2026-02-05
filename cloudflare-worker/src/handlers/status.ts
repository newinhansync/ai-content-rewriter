/**
 * Status Handler
 *
 * Handles task status queries
 * @since 2.0.0
 */

import type { Context } from 'hono';
import type { Env, ApiResponse, TaskStatus } from '../types';

/**
 * Handle GET /api/status/:taskId
 */
export async function handleStatus(c: Context<{ Bindings: Env }>): Promise<Response> {
  const env = c.env;
  const taskId = c.req.param('taskId');

  if (!taskId) {
    return c.json<ApiResponse>(
      {
        success: false,
        error: {
          code: 'INVALID_REQUEST',
          message: 'Task ID is required',
        },
      },
      400
    );
  }

  try {
    // Query task from D1
    const task = await env.DB.prepare(
      `
      SELECT
        task_id,
        status,
        progress,
        current_step,
        result,
        error,
        created_at,
        updated_at
      FROM tasks
      WHERE task_id = ?
    `
    )
      .bind(taskId)
      .first<{
        task_id: string;
        status: string;
        progress: number | null;
        current_step: string | null;
        result: string | null;
        error: string | null;
        created_at: string;
        updated_at: string;
      }>();

    if (!task) {
      return c.json<ApiResponse>(
        {
          success: false,
          error: {
            code: 'NOT_FOUND',
            message: `Task ${taskId} not found`,
          },
        },
        404
      );
    }

    // Parse JSON fields
    const taskStatus: TaskStatus = {
      task_id: task.task_id,
      status: task.status as TaskStatus['status'],
      progress: task.progress ?? undefined,
      current_step: task.current_step ?? undefined,
      result: task.result ? JSON.parse(task.result) : undefined,
      error: task.error ? JSON.parse(task.error) : undefined,
      created_at: task.created_at,
      updated_at: task.updated_at,
    };

    return c.json<ApiResponse<TaskStatus>>({
      success: true,
      data: taskStatus,
    });
  } catch (error) {
    console.error('[Status] Error:', error);

    return c.json<ApiResponse>(
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
 * Update task status in D1
 */
export async function updateTaskStatus(
  env: Env,
  taskId: string,
  updates: {
    status?: TaskStatus['status'];
    progress?: number;
    current_step?: string;
    result?: unknown;
    error?: unknown;
  }
): Promise<void> {
  const fields: string[] = ['updated_at = datetime(\'now\')'];
  const values: (string | number | null)[] = [];

  if (updates.status !== undefined) {
    fields.push('status = ?');
    values.push(updates.status);
  }

  if (updates.progress !== undefined) {
    fields.push('progress = ?');
    values.push(updates.progress);
  }

  if (updates.current_step !== undefined) {
    fields.push('current_step = ?');
    values.push(updates.current_step);
  }

  if (updates.result !== undefined) {
    fields.push('result = ?');
    values.push(JSON.stringify(updates.result));
  }

  if (updates.error !== undefined) {
    fields.push('error = ?');
    values.push(JSON.stringify(updates.error));
  }

  values.push(taskId);

  await env.DB.prepare(`UPDATE tasks SET ${fields.join(', ')} WHERE task_id = ?`)
    .bind(...values)
    .run();
}
