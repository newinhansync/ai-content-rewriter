/**
 * Vitest Test Setup
 *
 * Global setup and utilities for all tests
 */

import { vi } from 'vitest';

// Mock crypto.randomUUID if not available
if (typeof crypto === 'undefined' || !crypto.randomUUID) {
  vi.stubGlobal('crypto', {
    ...crypto,
    randomUUID: () => 'test-uuid-' + Math.random().toString(36).substring(7),
    subtle: {
      importKey: vi.fn().mockResolvedValue({}),
      sign: vi.fn().mockResolvedValue(new ArrayBuffer(32)),
    },
  });
}

// Global test utilities
export const createMockRequest = (
  url: string,
  options: {
    method?: string;
    headers?: Record<string, string>;
    body?: unknown;
  } = {}
) => {
  return new Request(url, {
    method: options.method || 'GET',
    headers: {
      'Content-Type': 'application/json',
      ...options.headers,
    },
    body: options.body ? JSON.stringify(options.body) : undefined,
  });
};

// Test data factories
export const createMockFeed = (overrides = {}) => ({
  id: 1,
  name: 'Test Feed',
  url: 'http://example.com/rss',
  is_active: true,
  auto_rewrite: true,
  auto_publish: true,
  created_at: '2025-02-05T00:00:00Z',
  ...overrides,
});

export const createMockFeedItem = (overrides = {}) => ({
  id: 1,
  feed_id: 1,
  title: 'Test Article',
  link: 'http://example.com/article',
  content: '<p>Test content</p>',
  pub_date: '2025-02-05T00:00:00Z',
  status: 'pending' as const,
  ...overrides,
});

export const createMockTaskRecord = (overrides = {}) => ({
  task_id: 'test-task-123',
  item_id: 1,
  status: 'processing',
  progress: 50,
  current_step: 'content',
  params: JSON.stringify({}),
  result: null,
  error: null,
  created_at: '2025-02-05T00:00:00Z',
  updated_at: '2025-02-05T00:00:00Z',
  ...overrides,
});

// Mock Cloudflare bindings factory
export const createMockEnv = (overrides = {}) => ({
  ENVIRONMENT: 'test',
  LOG_LEVEL: 'debug',
  WORKER_SECRET: 'test-worker-secret',
  HMAC_SECRET: 'test-hmac-secret',
  WP_API_KEY: 'test-wp-api-key',
  OPENAI_API_KEY: 'test-openai-key',
  GEMINI_API_KEY: 'test-gemini-key',
  WORDPRESS_URL: 'http://localhost:8080',
  CONFIG_KV: {
    get: vi.fn().mockResolvedValue(null),
    put: vi.fn().mockResolvedValue(undefined),
    delete: vi.fn().mockResolvedValue(undefined),
    list: vi.fn().mockResolvedValue({ keys: [] }),
  },
  LOCK_KV: {
    get: vi.fn().mockResolvedValue(null),
    put: vi.fn().mockResolvedValue(undefined),
    delete: vi.fn().mockResolvedValue(undefined),
  },
  DB: {
    prepare: vi.fn().mockReturnValue({
      bind: vi.fn().mockReturnValue({
        run: vi.fn().mockResolvedValue({ success: true }),
        first: vi.fn().mockResolvedValue(null),
        all: vi.fn().mockResolvedValue({ results: [] }),
      }),
    }),
    batch: vi.fn().mockResolvedValue([]),
    exec: vi.fn().mockResolvedValue({ results: [] }),
  },
  IMAGES: {
    put: vi.fn().mockResolvedValue(undefined),
    get: vi.fn().mockResolvedValue(null),
    delete: vi.fn().mockResolvedValue(undefined),
    list: vi.fn().mockResolvedValue({ objects: [] }),
  },
  ITEM_WORKFLOW: {
    create: vi.fn().mockResolvedValue({ id: 'mock-item-workflow-id' }),
    get: vi.fn().mockResolvedValue({ status: 'running' }),
  },
  MASTER_WORKFLOW: {
    create: vi.fn().mockResolvedValue({ id: 'mock-master-workflow-id' }),
    get: vi.fn().mockResolvedValue({ status: 'running' }),
  },
  ...overrides,
});
