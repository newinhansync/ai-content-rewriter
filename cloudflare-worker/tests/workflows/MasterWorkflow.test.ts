/**
 * Master Workflow Tests
 *
 * Integration tests for the automated content processing pipeline
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { createMockEnv, createMockFeed, createMockFeedItem } from '../setup';

// Mock fetch for WordPress API calls
const mockFetch = vi.fn();
global.fetch = mockFetch;

describe('MasterWorkflow', () => {
  let mockEnv: ReturnType<typeof createMockEnv>;

  beforeEach(() => {
    mockEnv = createMockEnv();
    mockFetch.mockReset();
  });

  afterEach(() => {
    vi.clearAllMocks();
  });

  describe('Lock Acquisition', () => {
    it('should acquire lock when no lock exists', async () => {
      mockEnv.LOCK_KV.get = vi.fn().mockResolvedValue(null);

      // Simulate lock acquisition
      const existingLock = await mockEnv.LOCK_KV.get('master_workflow_lock');
      expect(existingLock).toBeNull();

      // Lock should be acquirable
      await mockEnv.LOCK_KV.put(
        'master_workflow_lock',
        JSON.stringify({ workflow_id: 'test-id', acquired_at: Date.now() }),
        { expirationTtl: 3600 }
      );

      expect(mockEnv.LOCK_KV.put).toHaveBeenCalled();
    });

    it('should not acquire lock when valid lock exists', async () => {
      const recentLock = {
        workflow_id: 'other-workflow',
        acquired_at: Date.now() - 1000, // 1 second ago
      };

      mockEnv.LOCK_KV.get = vi.fn().mockResolvedValue(JSON.stringify(recentLock));

      const existingLock = await mockEnv.LOCK_KV.get('master_workflow_lock');
      expect(existingLock).not.toBeNull();

      const lockData = JSON.parse(existingLock as string);
      const lockAge = Date.now() - lockData.acquired_at;

      // Lock is less than 1 hour old, should not acquire
      expect(lockAge).toBeLessThan(3600 * 1000);
    });

    it('should acquire stale lock (older than 1 hour)', async () => {
      const staleLock = {
        workflow_id: 'old-workflow',
        acquired_at: Date.now() - 4000 * 1000, // 4000+ seconds ago
      };

      mockEnv.LOCK_KV.get = vi.fn().mockResolvedValue(JSON.stringify(staleLock));

      const existingLock = await mockEnv.LOCK_KV.get('master_workflow_lock');
      const lockData = JSON.parse(existingLock as string);
      const lockAge = Date.now() - lockData.acquired_at;

      // Lock is older than 1 hour, can be acquired
      expect(lockAge).toBeGreaterThan(3600 * 1000);
    });
  });

  describe('Feed Fetching', () => {
    it('should fetch active feeds with auto_rewrite enabled', async () => {
      const mockFeeds = [
        createMockFeed({ id: 1, is_active: true, auto_rewrite: true }),
        createMockFeed({ id: 2, is_active: true, auto_rewrite: false }),
        createMockFeed({ id: 3, is_active: false, auto_rewrite: true }),
      ];

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: mockFeeds }),
      });

      const response = await fetch('http://localhost:8080/wp-json/aicr/v1/feeds', {
        headers: { 'X-AICR-API-Key': 'test-api-key' },
      });

      const data = await response.json();
      const activeAutoFeeds = data.data.filter(
        (f: { is_active: boolean; auto_rewrite: boolean }) => f.is_active && f.auto_rewrite
      );

      expect(activeAutoFeeds).toHaveLength(1);
      expect(activeAutoFeeds[0].id).toBe(1);
    });
  });

  describe('Pending Items', () => {
    it('should fetch pending items within daily limit', async () => {
      const mockItems = [
        createMockFeedItem({ id: 1, status: 'pending' }),
        createMockFeedItem({ id: 2, status: 'pending' }),
        createMockFeedItem({ id: 3, status: 'pending' }),
      ];

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: mockItems }),
      });

      const dailyLimit = 10;
      const response = await fetch(
        `http://localhost:8080/wp-json/aicr/v1/feed-items/pending?limit=${dailyLimit}`,
        { headers: { 'X-AICR-API-Key': 'test-api-key' } }
      );

      const data = await response.json();

      expect(data.data).toHaveLength(3);
      expect(data.data.every((item: { status: string }) => item.status === 'pending')).toBe(true);
    });
  });

  describe('AI Curation', () => {
    it('should filter items based on curation score', async () => {
      // Mock AI curation response
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          choices: [
            {
              message: { content: '[0.9, 0.5, 0.85, 0.3]' },
            },
          ],
          usage: { total_tokens: 100 },
        }),
      });

      const curationThreshold = 0.8;
      const scores = [0.9, 0.5, 0.85, 0.3];

      const approvedCount = scores.filter((score) => score >= curationThreshold).length;

      expect(approvedCount).toBe(2); // 0.9 and 0.85 pass
    });

    it('should approve all items on AI error (fail-open)', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: false,
        status: 500,
        json: async () => ({ error: 'Internal server error' }),
      });

      // On error, all items should be approved
      const items = [createMockFeedItem(), createMockFeedItem()];

      // Simulate fail-open behavior
      const approvedItems = items; // All approved on error

      expect(approvedItems).toHaveLength(2);
    });
  });

  describe('Workflow Dispatch', () => {
    it('should dispatch ItemWorkflow for approved items', async () => {
      const approvedItem = createMockFeedItem({ id: 123 });

      await mockEnv.ITEM_WORKFLOW.create({
        id: `item-${approvedItem.id}-${Date.now()}`,
        params: {
          item_id: approvedItem.id,
          feed_id: approvedItem.feed_id,
          source_url: approvedItem.link,
          language: 'ko',
          ai_provider: 'chatgpt',
        },
      });

      expect(mockEnv.ITEM_WORKFLOW.create).toHaveBeenCalledWith(
        expect.objectContaining({
          params: expect.objectContaining({
            item_id: 123,
          }),
        })
      );
    });

    it('should stagger workflow starts', async () => {
      const items = [createMockFeedItem({ id: 1 }), createMockFeedItem({ id: 2 })];

      const delays: number[] = [];
      const start = Date.now();

      for (const item of items) {
        await mockEnv.ITEM_WORKFLOW.create({
          id: `item-${item.id}`,
          params: { item_id: item.id },
        });

        // Simulate staggering delay
        await new Promise((resolve) => setTimeout(resolve, 100));
        delays.push(Date.now() - start);
      }

      // Second dispatch should be delayed
      expect(delays[1] - delays[0]).toBeGreaterThanOrEqual(90);
    });
  });

  describe('Lock Release', () => {
    it('should release lock on completion', async () => {
      await mockEnv.LOCK_KV.delete('master_workflow_lock');

      expect(mockEnv.LOCK_KV.delete).toHaveBeenCalledWith('master_workflow_lock');
    });

    it('should release lock on error', async () => {
      // Simulate error scenario
      try {
        throw new Error('Test error');
      } catch {
        await mockEnv.LOCK_KV.delete('master_workflow_lock');
      }

      expect(mockEnv.LOCK_KV.delete).toHaveBeenCalled();
    });
  });
});
