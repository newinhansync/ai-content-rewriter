/**
 * AI Service
 *
 * Handles communication with OpenAI and Gemini APIs
 * @since 2.0.0
 */

import type {
  Env,
  AIProvider,
  AIMessage,
  AICompletionRequest,
  AICompletionResponse,
} from '../types';

/**
 * AI Service Class
 */
export class AIService {
  private env: Env;

  constructor(env: Env) {
    this.env = env;
  }

  /**
   * Generate completion from AI provider
   */
  async complete(request: AICompletionRequest): Promise<AICompletionResponse> {
    switch (request.provider) {
      case 'chatgpt':
        return this.completeWithOpenAI(request);
      case 'gemini':
        return this.completeWithGemini(request);
      default:
        return {
          success: false,
          error: `Unsupported AI provider: ${request.provider}`,
        };
    }
  }

  /**
   * Complete with OpenAI
   */
  private async completeWithOpenAI(request: AICompletionRequest): Promise<AICompletionResponse> {
    const apiKey = this.env.OPENAI_API_KEY;
    if (!apiKey) {
      return { success: false, error: 'OpenAI API key not configured' };
    }

    const model = request.model || 'gpt-4o';
    const maxTokens = request.max_tokens || 4096;
    const temperature = request.temperature ?? 0.7;

    try {
      const response = await fetch('https://api.openai.com/v1/chat/completions', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${apiKey}`,
        },
        body: JSON.stringify({
          model,
          messages: request.messages,
          temperature,
          max_tokens: maxTokens,
        }),
      });

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        return {
          success: false,
          error: `OpenAI API error: ${response.status} - ${JSON.stringify(errorData)}`,
        };
      }

      const data = (await response.json()) as {
        choices: { message: { content: string } }[];
        usage: {
          prompt_tokens: number;
          completion_tokens: number;
          total_tokens: number;
        };
      };

      return {
        success: true,
        content: data.choices[0]?.message?.content || '',
        usage: {
          prompt_tokens: data.usage.prompt_tokens,
          completion_tokens: data.usage.completion_tokens,
          total_tokens: data.usage.total_tokens,
        },
      };
    } catch (error) {
      return {
        success: false,
        error: error instanceof Error ? error.message : 'OpenAI request failed',
      };
    }
  }

  /**
   * Complete with Gemini
   */
  private async completeWithGemini(request: AICompletionRequest): Promise<AICompletionResponse> {
    const apiKey = this.env.GEMINI_API_KEY;
    if (!apiKey) {
      return { success: false, error: 'Gemini API key not configured' };
    }

    const model = request.model || 'gemini-1.5-pro';
    const maxTokens = request.max_tokens || 4096;
    const temperature = request.temperature ?? 0.7;

    // Convert messages to Gemini format
    const contents = this.convertToGeminiFormat(request.messages);

    try {
      const response = await fetch(
        `https://generativelanguage.googleapis.com/v1beta/models/${model}:generateContent?key=${apiKey}`,
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            contents,
            generationConfig: {
              temperature,
              maxOutputTokens: maxTokens,
            },
          }),
        }
      );

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        return {
          success: false,
          error: `Gemini API error: ${response.status} - ${JSON.stringify(errorData)}`,
        };
      }

      const data = (await response.json()) as {
        candidates: {
          content: {
            parts: { text: string }[];
          };
        }[];
        usageMetadata: {
          promptTokenCount: number;
          candidatesTokenCount: number;
          totalTokenCount: number;
        };
      };

      const content = data.candidates?.[0]?.content?.parts?.[0]?.text || '';

      return {
        success: true,
        content,
        usage: data.usageMetadata
          ? {
              prompt_tokens: data.usageMetadata.promptTokenCount,
              completion_tokens: data.usageMetadata.candidatesTokenCount,
              total_tokens: data.usageMetadata.totalTokenCount,
            }
          : undefined,
      };
    } catch (error) {
      return {
        success: false,
        error: error instanceof Error ? error.message : 'Gemini request failed',
      };
    }
  }

  /**
   * Convert OpenAI message format to Gemini format
   */
  private convertToGeminiFormat(
    messages: AIMessage[]
  ): { role: string; parts: { text: string }[] }[] {
    const contents: { role: string; parts: { text: string }[] }[] = [];

    // Handle system message by prepending to first user message
    let systemMessage = '';
    const nonSystemMessages = messages.filter((m) => {
      if (m.role === 'system') {
        systemMessage = m.content;
        return false;
      }
      return true;
    });

    for (let i = 0; i < nonSystemMessages.length; i++) {
      const msg = nonSystemMessages[i];
      let content = msg.content;

      // Prepend system message to first user message
      if (i === 0 && msg.role === 'user' && systemMessage) {
        content = `${systemMessage}\n\n${content}`;
      }

      contents.push({
        role: msg.role === 'assistant' ? 'model' : 'user',
        parts: [{ text: content }],
      });
    }

    return contents;
  }

  /**
   * Generate image with Gemini Imagen
   */
  async generateImage(prompt: string, options?: { aspectRatio?: string }): Promise<{
    success: boolean;
    imageData?: string;
    error?: string;
  }> {
    const apiKey = this.env.GEMINI_API_KEY;
    if (!apiKey) {
      return { success: false, error: 'Gemini API key not configured' };
    }

    try {
      const response = await fetch(
        `https://generativelanguage.googleapis.com/v1beta/models/imagen-3.0-generate-001:generateImages?key=${apiKey}`,
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            prompt,
            numberOfImages: 1,
            aspectRatio: options?.aspectRatio || '16:9',
          }),
        }
      );

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        return {
          success: false,
          error: `Imagen API error: ${response.status} - ${JSON.stringify(errorData)}`,
        };
      }

      const data = (await response.json()) as {
        generatedImages?: { image: { imageBytes: string } }[];
      };

      const imageBytes = data.generatedImages?.[0]?.image?.imageBytes;
      if (!imageBytes) {
        return { success: false, error: 'No image generated' };
      }

      return {
        success: true,
        imageData: imageBytes, // Base64 encoded
      };
    } catch (error) {
      return {
        success: false,
        error: error instanceof Error ? error.message : 'Image generation failed',
      };
    }
  }
}
