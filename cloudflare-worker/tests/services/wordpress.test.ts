/**
 * WordPress Service Tests
 *
 * Tests for WordPress REST API integration
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { WordPressService } from '../../src/services/wordpress';
import type { Env } from '../../src/types';

const mockFetch = vi.fn();
global.fetch = mockFetch;

const createMockEnv = (): Env =>
  ({
    ENVIRONMENT: 'test',
    WORDPRESS_URL: 'http://localhost:8080',
    WP_API_KEY: 'test-api-key',
    HMAC_SECRET: 'test-hmac-secret',
    CONFIG_KV: {
      get: vi.fn().mockResolvedValue(null),
    },
  }) as unknown as Env;

describe('WordPressService', () => {
  let wpService: WordPressService;
  let mockEnv: Env;

  beforeEach(() => {
    mockEnv = createMockEnv();
    wpService = new WordPressService(mockEnv);
    mockFetch.mockReset();
  });

  afterEach(() => {
    vi.clearAllMocks();
  });

  describe('getFeeds', () => {
    it('should fetch active feeds from WordPress', async () => {
      const mockFeeds = [
        { id: 1, name: 'Test Feed', url: 'http://example.com/rss', is_active: true },
        { id: 2, name: 'Another Feed', url: 'http://example2.com/rss', is_active: true },
      ];

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: mockFeeds }),
      });

      const result = await wpService.getFeeds();

      expect(result.success).toBe(true);
      expect(result.feeds).toHaveLength(2);
      expect(mockFetch).toHaveBeenCalledWith(
        'http://localhost:8080/wp-json/aicr/v1/feeds',
        expect.objectContaining({
          headers: expect.objectContaining({
            'X-AICR-API-Key': 'test-api-key',
          }),
        })
      );
    });

    it('should handle WordPress API errors', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: false,
        status: 500,
        json: async () => ({ error: 'Internal server error' }),
      });

      const result = await wpService.getFeeds();

      expect(result.success).toBe(false);
      expect(result.error).toBeDefined();
    });
  });

  describe('getPendingItems', () => {
    it('should fetch pending feed items', async () => {
      const mockItems = [
        { id: 1, title: 'Test Item', link: 'http://example.com/article', status: 'pending' },
      ];

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: mockItems }),
      });

      const result = await wpService.getPendingItems(10);

      expect(result.success).toBe(true);
      expect(result.items).toHaveLength(1);
      expect(mockFetch).toHaveBeenCalledWith(
        'http://localhost:8080/wp-json/aicr/v1/feed-items/pending?limit=10',
        expect.any(Object)
      );
    });
  });

  describe('updateItemStatus', () => {
    it('should update feed item status', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true }),
      });

      const result = await wpService.updateItemStatus(1, 'processing');

      expect(result.success).toBe(true);
      expect(mockFetch).toHaveBeenCalledWith(
        'http://localhost:8080/wp-json/aicr/v1/feed-items/1/status',
        expect.objectContaining({
          method: 'PATCH',
          body: expect.stringContaining('processing'),
        })
      );
    });
  });

  describe('sendWebhook', () => {
    it('should send webhook with HMAC signature', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true }),
      });

      const payload = {
        task_id: 'test-task',
        status: 'completed' as const,
        quality_score: 8.5,
        result: {
          title: 'Test Title',
          content: '<p>Test content</p>',
          excerpt: 'Test excerpt',
        },
      };

      const result = await wpService.sendWebhook(
        'http://localhost:8080/wp-json/aicr/v1/webhook',
        'callback-secret',
        payload
      );

      expect(result.success).toBe(true);
      expect(mockFetch).toHaveBeenCalledWith(
        'http://localhost:8080/wp-json/aicr/v1/webhook',
        expect.objectContaining({
          method: 'POST',
          headers: expect.objectContaining({
            'X-AICR-Signature': expect.any(String),
            'X-AICR-Timestamp': expect.any(String),
          }),
        })
      );
    });

    it('should handle webhook delivery failure', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: false,
        status: 500,
        text: async () => 'Internal server error',
      });

      const result = await wpService.sendWebhook(
        'http://localhost:8080/wp-json/aicr/v1/webhook',
        'callback-secret',
        { task_id: 'test', status: 'completed' as const }
      );

      expect(result.success).toBe(false);
      expect(result.error).toContain('Webhook failed');
    });
  });

  describe('uploadImage', () => {
    it('should upload image to WordPress media library', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          success: true,
          data: {
            url: 'http://localhost:8080/wp-content/uploads/2025/02/image.png',
            attachment_id: 123,
          },
        }),
      });

      const result = await wpService.uploadImage('base64-image-data', 'featured-image.png', 456);

      expect(result.success).toBe(true);
      expect(result.url).toContain('image.png');
      expect(result.attachmentId).toBe(123);
    });
  });
});
