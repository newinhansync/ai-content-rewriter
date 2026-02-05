/**
 * AI Content Rewriter - Cloudflare Worker Entry Point
 *
 * @description Main entry point for the Cloudflare Worker that handles
 *              HTTP requests and scheduled triggers for AI content rewriting.
 * @since 2.0.0
 */

import { Hono } from 'hono';
import { cors } from 'hono/cors';
import { logger } from 'hono/logger';

import type { Env, ApiResponse, HealthStatus } from './types';
import { handleRewrite } from './handlers/rewrite';
import { handleStatus } from './handlers/status';
import { handleSyncConfig } from './handlers/config';
import { verifyBearerToken } from './utils/auth';

// Import Workflows
export { MasterWorkflow } from './workflows/MasterWorkflow';
export { ItemWorkflow } from './workflows/ItemWorkflow';

// ============================================================================
// Hono App Setup
// ============================================================================

const app = new Hono<{ Bindings: Env }>();

// Middleware
app.use('*', logger());
app.use(
  '/api/*',
  cors({
    origin: '*',
    allowMethods: ['GET', 'POST', 'PATCH', 'OPTIONS'],
    allowHeaders: ['Content-Type', 'Authorization', 'X-Timestamp'],
  })
);

// ============================================================================
// Public Routes
// ============================================================================

/**
 * Health Check Endpoint
 * GET /api/health
 */
app.get('/api/health', async (c) => {
  const env = c.env;

  // Check bindings
  const checks = {
    kv: false,
    d1: false,
    r2: false,
    openai: false,
    gemini: false,
  };

  try {
    // KV Check
    await env.CONFIG_KV.get('__health_check__');
    checks.kv = true;
  } catch {
    checks.kv = false;
  }

  try {
    // D1 Check
    await env.DB.prepare('SELECT 1').first();
    checks.d1 = true;
  } catch {
    checks.d1 = false;
  }

  try {
    // R2 Check
    await env.IMAGES_BUCKET.head('__health_check__');
    checks.r2 = true;
  } catch {
    // R2 returns 404 for non-existent keys, which is fine
    checks.r2 = true;
  }

  // API Keys Check
  checks.openai = !!env.OPENAI_API_KEY;
  checks.gemini = !!env.GEMINI_API_KEY;

  const allHealthy = checks.kv && checks.d1 && checks.r2;
  const status: HealthStatus['status'] = allHealthy
    ? 'healthy'
    : checks.kv || checks.d1
      ? 'degraded'
      : 'unhealthy';

  const response: ApiResponse<HealthStatus> = {
    success: status !== 'unhealthy',
    data: {
      status,
      version: '2.0.0',
      timestamp: new Date().toISOString(),
      checks,
    },
  };

  return c.json(response, status === 'unhealthy' ? 503 : 200);
});

// ============================================================================
// Protected Routes (Require Bearer Token)
// ============================================================================

/**
 * Authentication Middleware for /api/* routes (except /api/health)
 */
app.use('/api/*', async (c, next) => {
  // Skip health check
  if (c.req.path === '/api/health') {
    return next();
  }

  const authHeader = c.req.header('Authorization');
  if (!authHeader || !authHeader.startsWith('Bearer ')) {
    return c.json<ApiResponse>(
      {
        success: false,
        error: {
          code: 'UNAUTHORIZED',
          message: 'Missing or invalid Authorization header',
        },
      },
      401
    );
  }

  const token = authHeader.slice(7);
  if (!verifyBearerToken(token, c.env.WORKER_SECRET)) {
    return c.json<ApiResponse>(
      {
        success: false,
        error: {
          code: 'FORBIDDEN',
          message: 'Invalid authentication token',
        },
      },
      403
    );
  }

  await next();
});

/**
 * Rewrite Content Endpoint
 * POST /api/rewrite
 */
app.post('/api/rewrite', async (c) => {
  return handleRewrite(c);
});

/**
 * Get Task Status Endpoint
 * GET /api/status/:taskId
 */
app.get('/api/status/:taskId', async (c) => {
  return handleStatus(c);
});

/**
 * Sync Configuration Endpoint
 * POST /api/sync-config
 */
app.post('/api/sync-config', async (c) => {
  return handleSyncConfig(c);
});

/**
 * Manual Trigger Master Workflow
 * POST /api/trigger-master
 */
app.post('/api/trigger-master', async (c) => {
  const env = c.env;

  try {
    const instance = await env.MASTER_WORKFLOW.create({
      params: {
        triggered_by: 'manual',
        timestamp: new Date().toISOString(),
      },
    });

    return c.json<ApiResponse>({
      success: true,
      data: {
        workflow_id: instance.id,
        message: 'Master workflow triggered manually',
      },
    });
  } catch (error) {
    return c.json<ApiResponse>(
      {
        success: false,
        error: {
          code: 'WORKFLOW_ERROR',
          message: error instanceof Error ? error.message : 'Failed to trigger workflow',
        },
      },
      500
    );
  }
});

// ============================================================================
// 404 Handler
// ============================================================================

app.notFound((c) => {
  return c.json<ApiResponse>(
    {
      success: false,
      error: {
        code: 'NOT_FOUND',
        message: `Route ${c.req.method} ${c.req.path} not found`,
      },
    },
    404
  );
});

// ============================================================================
// Error Handler
// ============================================================================

app.onError((err, c) => {
  console.error('Unhandled error:', err);

  return c.json<ApiResponse>(
    {
      success: false,
      error: {
        code: 'INTERNAL_ERROR',
        message: c.env.ENVIRONMENT === 'production' ? 'Internal server error' : err.message,
      },
    },
    500
  );
});

// ============================================================================
// Export
// ============================================================================

export default {
  /**
   * HTTP Request Handler
   */
  fetch: app.fetch,

  /**
   * Scheduled Trigger Handler (Cron)
   */
  async scheduled(event: ScheduledEvent, env: Env, ctx: ExecutionContext): Promise<void> {
    console.log(`[Cron] Triggered at ${new Date(event.scheduledTime).toISOString()}`);

    // 매시 정각: Master Workflow 실행
    const minute = new Date(event.scheduledTime).getMinutes();

    if (minute === 0) {
      console.log('[Cron] Triggering Master Workflow...');
      try {
        const instance = await env.MASTER_WORKFLOW.create({
          params: {
            triggered_by: 'cron',
            timestamp: new Date(event.scheduledTime).toISOString(),
          },
        });
        console.log(`[Cron] Master Workflow started: ${instance.id}`);
      } catch (error) {
        console.error('[Cron] Failed to start Master Workflow:', error);
      }
    } else if (minute === 30) {
      // 매시 30분: 미완료 작업 재시도 (TODO: 구현)
      console.log('[Cron] Retry incomplete tasks (not implemented yet)');
    }
  },
};
