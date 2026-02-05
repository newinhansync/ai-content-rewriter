/**
 * AI Service Tests
 *
 * Tests for AI API integrations (OpenAI, Gemini)
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { AIService } from '../../src/services/ai';
import type { Env } from '../../src/types';

// Mock fetch globally
const mockFetch = vi.fn();
global.fetch = mockFetch;

const createMockEnv = (): Env =>
  ({
    ENVIRONMENT: 'test',
    LOG_LEVEL: 'debug',
    OPENAI_API_KEY: 'test-openai-key',
    GEMINI_API_KEY: 'test-gemini-key',
    CONFIG_KV: {
      get: vi.fn().mockResolvedValue(null),
    },
  }) as unknown as Env;

describe('AIService', () => {
  let aiService: AIService;
  let mockEnv: Env;

  beforeEach(() => {
    mockEnv = createMockEnv();
    aiService = new AIService(mockEnv);
    mockFetch.mockReset();
  });

  afterEach(() => {
    vi.clearAllMocks();
  });

  describe('OpenAI Provider', () => {
    it('should call OpenAI API with correct parameters', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          choices: [
            {
              message: { content: 'Test response from OpenAI' },
            },
          ],
          usage: {
            prompt_tokens: 100,
            completion_tokens: 50,
            total_tokens: 150,
          },
        }),
      });

      const result = await aiService.complete({
        provider: 'chatgpt',
        model: 'gpt-4o-mini',
        messages: [
          { role: 'system', content: 'You are a helpful assistant.' },
          { role: 'user', content: 'Hello!' },
        ],
        temperature: 0.7,
        max_tokens: 1000,
      });

      expect(result.success).toBe(true);
      expect(result.content).toBe('Test response from OpenAI');
      expect(result.usage?.total).toBe(150);

      expect(mockFetch).toHaveBeenCalledWith(
        'https://api.openai.com/v1/chat/completions',
        expect.objectContaining({
          method: 'POST',
          headers: expect.objectContaining({
            Authorization: 'Bearer test-openai-key',
          }),
        })
      );
    });

    it('should handle OpenAI API errors', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: false,
        status: 429,
        json: async () => ({
          error: { message: 'Rate limit exceeded' },
        }),
      });

      const result = await aiService.complete({
        provider: 'chatgpt',
        model: 'gpt-4o-mini',
        messages: [{ role: 'user', content: 'Hello!' }],
      });

      expect(result.success).toBe(false);
      expect(result.error).toContain('Rate limit');
    });
  });

  describe('Gemini Provider', () => {
    it('should call Gemini API with correct parameters', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          candidates: [
            {
              content: {
                parts: [{ text: 'Test response from Gemini' }],
              },
            },
          ],
          usageMetadata: {
            promptTokenCount: 80,
            candidatesTokenCount: 40,
            totalTokenCount: 120,
          },
        }),
      });

      const result = await aiService.complete({
        provider: 'gemini',
        model: 'gemini-1.5-pro',
        messages: [{ role: 'user', content: 'Hello!' }],
        temperature: 0.7,
        max_tokens: 1000,
      });

      expect(result.success).toBe(true);
      expect(result.content).toBe('Test response from Gemini');
      expect(result.usage?.total).toBe(120);
    });

    it('should handle Gemini API errors', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: false,
        status: 400,
        json: async () => ({
          error: { message: 'Invalid request' },
        }),
      });

      const result = await aiService.complete({
        provider: 'gemini',
        model: 'gemini-1.5-pro',
        messages: [{ role: 'user', content: 'Hello!' }],
      });

      expect(result.success).toBe(false);
      expect(result.error).toBeDefined();
    });
  });

  describe('Image Generation', () => {
    it('should generate image with Gemini Imagen', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          predictions: [
            {
              bytesBase64Encoded: 'base64-encoded-image-data',
            },
          ],
        }),
      });

      const result = await aiService.generateImage({
        prompt: 'A beautiful sunset over mountains',
        width: 1024,
        height: 1024,
      });

      expect(result.success).toBe(true);
      expect(result.imageBase64).toBe('base64-encoded-image-data');
    });

    it('should handle image generation errors', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: false,
        status: 500,
        json: async () => ({
          error: { message: 'Image generation failed' },
        }),
      });

      const result = await aiService.generateImage({
        prompt: 'Test prompt',
      });

      expect(result.success).toBe(false);
      expect(result.error).toBeDefined();
    });
  });

  describe('Provider Selection', () => {
    it('should default to OpenAI when provider not specified', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          choices: [{ message: { content: 'Response' } }],
          usage: { prompt_tokens: 10, completion_tokens: 5, total_tokens: 15 },
        }),
      });

      await aiService.complete({
        provider: 'chatgpt',
        messages: [{ role: 'user', content: 'Hello!' }],
      });

      expect(mockFetch).toHaveBeenCalledWith(
        'https://api.openai.com/v1/chat/completions',
        expect.any(Object)
      );
    });
  });
});
