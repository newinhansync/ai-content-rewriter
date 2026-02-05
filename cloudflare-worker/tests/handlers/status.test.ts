/**
 * Status Handler Tests
 *
 * Tests for the /api/status/:taskId endpoint
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { Hono } from 'hono';
import { handleStatus } from '../../src/handlers/status';
import type { Env } from '../../src/types';

const createMockEnv = (taskData: Record<string, unknown> | null = null): Env =>
  ({
    ENVIRONMENT: 'test',
    LOG_LEVEL: 'debug',
    WORKER_SECRET: 'test-secret',
    DB: {
      prepare: vi.fn().mockReturnValue({
        bind: vi.fn().mockReturnValue({
          first: vi.fn().mockResolvedValue(taskData),
        }),
      }),
    },
  }) as unknown as Env;

describe('Status Handler', () => {
  let app: Hono<{ Bindings: Env }>;

  beforeEach(() => {
    app = new Hono<{ Bindings: Env }>();
  });

  it('should return task status for existing task', async () => {
    const mockTask = {
      task_id: 'task-123',
      status: 'processing',
      progress: 60,
      current_step: 'content',
      created_at: '2025-02-05T10:00:00Z',
      updated_at: '2025-02-05T10:05:00Z',
    };

    const mockEnv = createMockEnv(mockTask);

    app.use('*', async (c, next) => {
      c.env = mockEnv;
      await next();
    });

    app.get('/api/status/:taskId', handleStatus);

    const req = new Request('http://localhost/api/status/task-123', {
      method: 'GET',
      headers: { Authorization: 'Bearer test-secret' },
    });

    const res = await app.fetch(req, mockEnv);
    const data = await res.json();

    expect(res.status).toBe(200);
    expect(data.success).toBe(true);
    expect(data.data.task_id).toBe('task-123');
    expect(data.data.status).toBe('processing');
    expect(data.data.progress).toBe(60);
  });

  it('should return 404 for non-existent task', async () => {
    const mockEnv = createMockEnv(null);

    app.use('*', async (c, next) => {
      c.env = mockEnv;
      await next();
    });

    app.get('/api/status/:taskId', handleStatus);

    const req = new Request('http://localhost/api/status/non-existent', {
      method: 'GET',
      headers: { Authorization: 'Bearer test-secret' },
    });

    const res = await app.fetch(req, mockEnv);
    expect(res.status).toBe(404);
  });

  it('should return completed task with result', async () => {
    const mockTask = {
      task_id: 'task-456',
      status: 'completed',
      progress: 100,
      current_step: 'webhook',
      result: JSON.stringify({
        title: 'Test Article',
        content: '<p>Test content</p>',
        quality_score: 8.5,
      }),
      created_at: '2025-02-05T10:00:00Z',
      updated_at: '2025-02-05T10:10:00Z',
    };

    const mockEnv = createMockEnv(mockTask);

    app.use('*', async (c, next) => {
      c.env = mockEnv;
      await next();
    });

    app.get('/api/status/:taskId', handleStatus);

    const req = new Request('http://localhost/api/status/task-456', {
      method: 'GET',
      headers: { Authorization: 'Bearer test-secret' },
    });

    const res = await app.fetch(req, mockEnv);
    const data = await res.json();

    expect(res.status).toBe(200);
    expect(data.data.status).toBe('completed');
    expect(data.data.progress).toBe(100);
  });

  it('should return failed task with error', async () => {
    const mockTask = {
      task_id: 'task-789',
      status: 'failed',
      progress: 40,
      current_step: 'content',
      error: 'AI API rate limit exceeded',
      created_at: '2025-02-05T10:00:00Z',
      updated_at: '2025-02-05T10:03:00Z',
    };

    const mockEnv = createMockEnv(mockTask);

    app.use('*', async (c, next) => {
      c.env = mockEnv;
      await next();
    });

    app.get('/api/status/:taskId', handleStatus);

    const req = new Request('http://localhost/api/status/task-789', {
      method: 'GET',
      headers: { Authorization: 'Bearer test-secret' },
    });

    const res = await app.fetch(req, mockEnv);
    const data = await res.json();

    expect(res.status).toBe(200);
    expect(data.data.status).toBe('failed');
    expect(data.data.error).toBe('AI API rate limit exceeded');
  });
});
