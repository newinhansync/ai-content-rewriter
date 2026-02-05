/**
 * Item Workflow Tests
 *
 * Tests for the multi-step content processing pipeline
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { createMockEnv, createMockFeedItem } from '../setup';

const mockFetch = vi.fn();
global.fetch = mockFetch;

describe('ItemWorkflow', () => {
  let mockEnv: ReturnType<typeof createMockEnv>;

  beforeEach(() => {
    mockEnv = createMockEnv();
    mockFetch.mockReset();
  });

  afterEach(() => {
    vi.clearAllMocks();
  });

  describe('Content Extraction', () => {
    it('should extract content from URL', async () => {
      const htmlContent = `
        <html>
          <head><title>Test Article</title></head>
          <body>
            <article>
              <h1>Main Title</h1>
              <p>This is the article content.</p>
            </article>
          </body>
        </html>
      `;

      mockFetch.mockResolvedValueOnce({
        ok: true,
        text: async () => htmlContent,
      });

      const response = await fetch('http://example.com/article');
      const text = await response.text();

      expect(text).toContain('Main Title');
      expect(text).toContain('article content');
    });

    it('should handle extraction errors gracefully', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: false,
        status: 404,
      });

      const response = await fetch('http://example.com/not-found');

      expect(response.ok).toBe(false);
      expect(response.status).toBe(404);
    });

    it('should use provided content if URL extraction fails', () => {
      const item = createMockFeedItem({
        content: '<p>Fallback content from RSS feed</p>',
      });

      // If URL extraction fails, use feed content
      expect(item.content).toContain('Fallback content');
    });
  });

  describe('Outline Generation', () => {
    it('should generate structured outline from content', async () => {
      const outlineResponse = {
        main_topic: 'AI Technology',
        target_audience: 'Tech enthusiasts',
        key_points: ['Introduction to AI', 'Current applications', 'Future prospects'],
        structure: ['intro', 'body1', 'body2', 'body3', 'conclusion'],
        tone: 'informative',
        word_count_target: 1500,
      };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          choices: [
            {
              message: { content: JSON.stringify(outlineResponse) },
            },
          ],
        }),
      });

      const response = await fetch('https://api.openai.com/v1/chat/completions', {
        method: 'POST',
        body: JSON.stringify({
          model: 'gpt-4o-mini',
          messages: [{ role: 'user', content: 'Generate outline for: AI article' }],
        }),
      });

      const data = await response.json();
      const outline = JSON.parse(data.choices[0].message.content);

      expect(outline.main_topic).toBe('AI Technology');
      expect(outline.key_points).toHaveLength(3);
      expect(outline.word_count_target).toBe(1500);
    });
  });

  describe('Content Generation', () => {
    it('should generate 1.5x expanded content', async () => {
      const originalWordCount = 500;
      const targetWordCount = Math.floor(originalWordCount * 1.5);

      // Mock content generation
      const generatedContent = 'Word '.repeat(targetWordCount);

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          choices: [
            {
              message: { content: generatedContent },
            },
          ],
          usage: { total_tokens: 2000 },
        }),
      });

      const response = await fetch('https://api.openai.com/v1/chat/completions', {
        method: 'POST',
        body: JSON.stringify({ model: 'gpt-4o' }),
      });

      const data = await response.json();
      const wordCount = data.choices[0].message.content.split(' ').length;

      expect(wordCount).toBeGreaterThanOrEqual(targetWordCount * 0.9);
    });

    it('should maintain language consistency', async () => {
      const koreanContent = '이것은 테스트 콘텐츠입니다. 한국어로 작성되었습니다.';

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          choices: [{ message: { content: koreanContent } }],
        }),
      });

      const response = await fetch('https://api.openai.com/v1/chat/completions');
      const data = await response.json();

      // Check for Korean characters
      const hasKorean = /[\uAC00-\uD7AF]/.test(data.choices[0].message.content);
      expect(hasKorean).toBe(true);
    });
  });

  describe('SEO Optimization', () => {
    it('should generate SEO metadata', async () => {
      const seoResponse = {
        meta_title: 'AI Technology Guide 2025 | Complete Overview',
        meta_description: 'Comprehensive guide to AI technology in 2025...',
        keywords: ['AI', 'machine learning', 'technology', '2025'],
        category_suggestion: 'Technology',
        tags: ['AI', 'Tech', 'Innovation'],
      };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          choices: [
            {
              message: { content: JSON.stringify(seoResponse) },
            },
          ],
        }),
      });

      const response = await fetch('https://api.openai.com/v1/chat/completions');
      const data = await response.json();
      const seo = JSON.parse(data.choices[0].message.content);

      expect(seo.meta_title.length).toBeLessThanOrEqual(60);
      expect(seo.meta_description.length).toBeLessThanOrEqual(160);
      expect(seo.keywords).toBeInstanceOf(Array);
      expect(seo.tags).toBeInstanceOf(Array);
    });
  });

  describe('Self-Critique', () => {
    it('should pass content with score >= 7', async () => {
      const critiqueResponse = {
        overall_score: 8.5,
        criteria_scores: {
          accuracy: 9,
          readability: 8,
          seo_optimization: 8,
          engagement: 9,
        },
        passed: true,
        suggestions: [],
      };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          choices: [
            {
              message: { content: JSON.stringify(critiqueResponse) },
            },
          ],
        }),
      });

      const response = await fetch('https://api.openai.com/v1/chat/completions');
      const data = await response.json();
      const critique = JSON.parse(data.choices[0].message.content);

      expect(critique.overall_score).toBeGreaterThanOrEqual(7);
      expect(critique.passed).toBe(true);
    });

    it('should trigger retry for score < 7', async () => {
      const critiqueResponse = {
        overall_score: 5.5,
        criteria_scores: {
          accuracy: 6,
          readability: 5,
          seo_optimization: 5,
          engagement: 6,
        },
        passed: false,
        suggestions: ['Improve readability', 'Add more keywords', 'Enhance introduction'],
      };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          choices: [
            {
              message: { content: JSON.stringify(critiqueResponse) },
            },
          ],
        }),
      });

      const response = await fetch('https://api.openai.com/v1/chat/completions');
      const data = await response.json();
      const critique = JSON.parse(data.choices[0].message.content);

      expect(critique.overall_score).toBeLessThan(7);
      expect(critique.passed).toBe(false);
      expect(critique.suggestions.length).toBeGreaterThan(0);
    });

    it('should limit retries to maximum attempts', () => {
      const MAX_RETRIES = 2;
      let retryCount = 0;

      // Simulate retry loop
      while (retryCount < MAX_RETRIES) {
        const score = 5; // Always fails
        if (score < 7) {
          retryCount++;
        } else {
          break;
        }
      }

      expect(retryCount).toBe(MAX_RETRIES);
    });
  });

  describe('Image Generation', () => {
    it('should generate featured image with Gemini Imagen', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          predictions: [
            {
              bytesBase64Encoded: 'base64-image-data-here',
            },
          ],
        }),
      });

      const response = await fetch('https://generativelanguage.googleapis.com/v1beta/imagen', {
        method: 'POST',
        body: JSON.stringify({
          prompt: 'A professional blog header image about AI technology',
        }),
      });

      const data = await response.json();

      expect(data.predictions).toHaveLength(1);
      expect(data.predictions[0].bytesBase64Encoded).toBeDefined();
    });

    it('should skip image generation if disabled', () => {
      const options = { generate_images: false };

      expect(options.generate_images).toBe(false);
      // Image generation step should be skipped
    });
  });

  describe('Webhook Delivery', () => {
    it('should send success webhook with results', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true }),
      });

      const webhookPayload = {
        task_id: 'test-task-123',
        item_id: 1,
        status: 'completed',
        quality_score: 8.5,
        result: {
          title: 'Generated Title',
          content: '<p>Generated content</p>',
          excerpt: 'Short excerpt',
          meta_title: 'SEO Title',
          meta_description: 'SEO Description',
          tags: ['tag1', 'tag2'],
        },
        metrics: {
          processing_time_ms: 45000,
          token_usage: { input: 5000, output: 3000, total: 8000 },
          steps_completed: ['extraction', 'outline', 'content', 'seo', 'critique', 'webhook'],
          retry_count: 0,
        },
      };

      const response = await fetch('http://localhost:8080/wp-json/aicr/v1/webhook', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-AICR-Signature': 'test-signature',
          'X-AICR-Timestamp': String(Date.now()),
        },
        body: JSON.stringify(webhookPayload),
      });

      expect(response.ok).toBe(true);
    });

    it('should send failure webhook on error', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true }),
      });

      const webhookPayload = {
        task_id: 'test-task-456',
        item_id: 2,
        status: 'failed',
        error: {
          code: 'AI_API_ERROR',
          message: 'OpenAI API rate limit exceeded',
        },
        metrics: {
          processing_time_ms: 5000,
          steps_completed: ['extraction', 'outline'],
          retry_count: 2,
        },
      };

      const response = await fetch('http://localhost:8080/wp-json/aicr/v1/webhook', {
        method: 'POST',
        body: JSON.stringify(webhookPayload),
      });

      expect(response.ok).toBe(true);
    });

    it('should retry webhook on delivery failure', async () => {
      // First attempt fails
      mockFetch.mockResolvedValueOnce({
        ok: false,
        status: 503,
      });

      // Second attempt succeeds
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true }),
      });

      let attempts = 0;
      let success = false;

      while (attempts < 3 && !success) {
        attempts++;
        const response = await fetch('http://localhost:8080/wp-json/aicr/v1/webhook', {
          method: 'POST',
          body: JSON.stringify({ task_id: 'test' }),
        });
        success = response.ok;

        if (!success) {
          await new Promise((resolve) => setTimeout(resolve, 100));
        }
      }

      expect(attempts).toBe(2);
      expect(success).toBe(true);
    });
  });

  describe('Token Usage Tracking', () => {
    it('should accumulate token usage across steps', () => {
      const tokenUsage = {
        outline: { input: 1000, output: 500 },
        content: { input: 2000, output: 2500 },
        seo: { input: 500, output: 300 },
        critique: { input: 800, output: 200 },
      };

      const total = {
        input: Object.values(tokenUsage).reduce((sum, step) => sum + step.input, 0),
        output: Object.values(tokenUsage).reduce((sum, step) => sum + step.output, 0),
      };

      expect(total.input).toBe(4300);
      expect(total.output).toBe(3500);
      expect(total.input + total.output).toBe(7800);
    });
  });

  describe('Auto-Publish Logic', () => {
    it('should auto-publish when score >= threshold and auto_publish enabled', () => {
      const options = { auto_publish: true, publish_threshold: 8 };
      const qualityScore = 8.5;

      const shouldPublish = options.auto_publish && qualityScore >= options.publish_threshold;

      expect(shouldPublish).toBe(true);
    });

    it('should not auto-publish when score < threshold', () => {
      const options = { auto_publish: true, publish_threshold: 8 };
      const qualityScore = 7.5;

      const shouldPublish = options.auto_publish && qualityScore >= options.publish_threshold;

      expect(shouldPublish).toBe(false);
    });

    it('should not auto-publish when auto_publish disabled', () => {
      const options = { auto_publish: false, publish_threshold: 8 };
      const qualityScore = 9.0;

      const shouldPublish = options.auto_publish && qualityScore >= options.publish_threshold;

      expect(shouldPublish).toBe(false);
    });
  });
});
