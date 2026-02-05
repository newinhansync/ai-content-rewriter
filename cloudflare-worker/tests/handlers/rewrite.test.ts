/**
 * Rewrite Handler Tests
 *
 * Tests for the /api/rewrite endpoint
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { Hono } from 'hono';
import { handleRewrite } from '../../src/handlers/rewrite';
import type { Env } from '../../src/types';

// Mock environment
const createMockEnv = (): Env =>
  ({
    ENVIRONMENT: 'test',
    LOG_LEVEL: 'debug',
    WORKER_SECRET: 'test-secret',
    HMAC_SECRET: 'test-hmac',
    WP_API_KEY: 'test-api-key',
    OPENAI_API_KEY: 'test-openai',
    GEMINI_API_KEY: 'test-gemini',
    WORDPRESS_URL: 'http://localhost:8080',
    CONFIG_KV: {
      get: vi.fn(),
      put: vi.fn(),
      delete: vi.fn(),
    },
    LOCK_KV: {
      get: vi.fn(),
      put: vi.fn(),
      delete: vi.fn(),
    },
    DB: {
      prepare: vi.fn().mockReturnValue({
        bind: vi.fn().mockReturnValue({
          run: vi.fn().mockResolvedValue({ success: true }),
          first: vi.fn().mockResolvedValue(null),
          all: vi.fn().mockResolvedValue({ results: [] }),
        }),
      }),
    },
    IMAGES: {
      put: vi.fn(),
      get: vi.fn(),
    },
    ITEM_WORKFLOW: {
      create: vi.fn().mockResolvedValue({ id: 'test-workflow-id' }),
    },
    MASTER_WORKFLOW: {
      create: vi.fn().mockResolvedValue({ id: 'test-master-id' }),
    },
  }) as unknown as Env;

describe('Rewrite Handler', () => {
  let app: Hono<{ Bindings: Env }>;
  let mockEnv: Env;

  beforeEach(() => {
    app = new Hono<{ Bindings: Env }>();
    mockEnv = createMockEnv();

    // Add auth middleware mock
    app.use('*', async (c, next) => {
      c.env = mockEnv;
      await next();
    });

    app.post('/api/rewrite', handleRewrite);
  });

  it('should reject requests without authorization', async () => {
    const req = new Request('http://localhost/api/rewrite', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        task_id: 'test-task',
        callback_url: 'http://localhost/callback',
        payload: { source_url: 'http://example.com' },
      }),
    });

    const res = await app.fetch(req, mockEnv);
    expect(res.status).toBe(401);
  });

  it('should accept valid rewrite request', async () => {
    const req = new Request('http://localhost/api/rewrite', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: 'Bearer test-secret',
      },
      body: JSON.stringify({
        task_id: 'test-task-123',
        callback_url: 'http://localhost:8080/wp-json/aicr/v1/webhook',
        callback_secret: 'test-callback-secret',
        payload: {
          source_url: 'http://example.com/article',
          language: 'ko',
          ai_provider: 'chatgpt',
        },
      }),
    });

    const res = await app.fetch(req, mockEnv);
    const data = await res.json();

    expect(res.status).toBe(202);
    expect(data.success).toBe(true);
    expect(data.data.task_id).toBe('test-task-123');
  });

  it('should reject request with missing required fields', async () => {
    const req = new Request('http://localhost/api/rewrite', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: 'Bearer test-secret',
      },
      body: JSON.stringify({
        task_id: 'test-task',
        // missing callback_url and payload
      }),
    });

    const res = await app.fetch(req, mockEnv);
    expect(res.status).toBe(400);
  });

  it('should reject request with invalid URL', async () => {
    const req = new Request('http://localhost/api/rewrite', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: 'Bearer test-secret',
      },
      body: JSON.stringify({
        task_id: 'test-task',
        callback_url: 'http://localhost/callback',
        payload: {
          source_url: 'not-a-valid-url',
        },
      }),
    });

    const res = await app.fetch(req, mockEnv);
    expect(res.status).toBe(400);
  });
});
