/**
 * WordPress API Service
 *
 * Handles communication with WordPress REST API
 * @since 2.0.0
 */

import type { Env, Feed, FeedItem, WebhookPayload, WebhookResult, ProcessingMetrics } from '../types';
import { generateHmacSignature } from '../utils/auth';
import { getConfig } from '../handlers/config';

/**
 * WordPress API Client
 */
export class WordPressService {
  private env: Env;

  constructor(env: Env) {
    this.env = env;
  }

  /**
   * Get WordPress URL from environment or config
   */
  private async getWordPressUrl(): Promise<string> {
    // First try environment variable
    if (this.env.WORDPRESS_URL) {
      return this.env.WORDPRESS_URL.replace(/\/$/, '');
    }

    // Fall back to KV config
    const config = await getConfig(this.env);
    if (config?.wordpress_url) {
      return config.wordpress_url.replace(/\/$/, '');
    }

    throw new Error('WordPress URL not configured');
  }

  /**
   * Get API key from environment or config
   */
  private async getApiKey(): Promise<string> {
    if (this.env.WP_API_KEY) {
      return this.env.WP_API_KEY;
    }

    const config = await getConfig(this.env);
    if (config?.api_key) {
      return config.api_key;
    }

    throw new Error('WordPress API key not configured');
  }

  /**
   * Make authenticated request to WordPress REST API
   */
  private async request<T>(
    endpoint: string,
    options: RequestInit = {}
  ): Promise<{ success: boolean; data?: T; error?: string }> {
    try {
      const baseUrl = await this.getWordPressUrl();
      const apiKey = await this.getApiKey();

      const url = `${baseUrl}/wp-json/aicr/v1${endpoint}`;

      const response = await fetch(url, {
        ...options,
        headers: {
          'Content-Type': 'application/json',
          'X-AICR-API-Key': apiKey,
          ...options.headers,
        },
      });

      const data = (await response.json()) as { success: boolean; data?: T; error?: unknown };

      if (!response.ok) {
        return {
          success: false,
          error: `WordPress API error: ${response.status} - ${JSON.stringify(data)}`,
        };
      }

      return { success: true, data: data.data };
    } catch (error) {
      return {
        success: false,
        error: error instanceof Error ? error.message : 'WordPress API request failed',
      };
    }
  }

  /**
   * Get active feeds
   */
  async getFeeds(): Promise<{ success: boolean; feeds?: Feed[]; error?: string }> {
    const result = await this.request<Feed[]>('/feeds');
    return {
      success: result.success,
      feeds: result.data,
      error: result.error,
    };
  }

  /**
   * Get pending feed items for processing
   */
  async getPendingItems(limit = 10): Promise<{
    success: boolean;
    items?: FeedItem[];
    error?: string;
  }> {
    const result = await this.request<FeedItem[]>(`/feed-items/pending?limit=${limit}`);
    return {
      success: result.success,
      items: result.data,
      error: result.error,
    };
  }

  /**
   * Update feed item status
   */
  async updateItemStatus(
    itemId: number,
    status: FeedItem['status'],
    additionalData?: { post_id?: number; quality_score?: number }
  ): Promise<{ success: boolean; error?: string }> {
    const result = await this.request(`/feed-items/${itemId}/status`, {
      method: 'PATCH',
      body: JSON.stringify({ status, ...additionalData }),
    });
    return { success: result.success, error: result.error };
  }

  /**
   * Send webhook notification to WordPress
   */
  async sendWebhook(
    callbackUrl: string,
    callbackSecret: string,
    payload: WebhookPayload
  ): Promise<{ success: boolean; error?: string }> {
    try {
      const timestamp = Math.floor(Date.now() / 1000);
      const payloadStr = JSON.stringify(payload);
      const signature = await generateHmacSignature(payloadStr, callbackSecret, timestamp);

      const response = await fetch(callbackUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-AICR-Signature': signature,
          'X-AICR-Timestamp': String(timestamp),
        },
        body: payloadStr,
      });

      if (!response.ok) {
        const errorText = await response.text();
        return {
          success: false,
          error: `Webhook failed: ${response.status} - ${errorText}`,
        };
      }

      return { success: true };
    } catch (error) {
      return {
        success: false,
        error: error instanceof Error ? error.message : 'Webhook request failed',
      };
    }
  }

  /**
   * Send success webhook
   */
  async sendSuccessWebhook(
    callbackUrl: string,
    callbackSecret: string,
    taskId: string,
    itemId: number | undefined,
    qualityScore: number,
    result: WebhookResult,
    metrics: ProcessingMetrics
  ): Promise<{ success: boolean; error?: string }> {
    const payload: WebhookPayload = {
      task_id: taskId,
      item_id: itemId,
      status: 'completed',
      quality_score: qualityScore,
      result,
      metrics,
    };

    return this.sendWebhook(callbackUrl, callbackSecret, payload);
  }

  /**
   * Send failure webhook
   */
  async sendFailureWebhook(
    callbackUrl: string,
    callbackSecret: string,
    taskId: string,
    itemId: number | undefined,
    errorCode: string,
    errorMessage: string,
    metrics?: ProcessingMetrics
  ): Promise<{ success: boolean; error?: string }> {
    const payload: WebhookPayload = {
      task_id: taskId,
      item_id: itemId,
      status: 'failed',
      error: {
        code: errorCode,
        message: errorMessage,
      },
      metrics,
    };

    return this.sendWebhook(callbackUrl, callbackSecret, payload);
  }

  /**
   * Upload image to WordPress media library
   */
  async uploadImage(
    imageData: string, // Base64 encoded
    filename: string,
    postId?: number
  ): Promise<{ success: boolean; url?: string; attachmentId?: number; error?: string }> {
    try {
      const baseUrl = await this.getWordPressUrl();
      const apiKey = await this.getApiKey();

      const response = await fetch(`${baseUrl}/wp-json/aicr/v1/media`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-AICR-API-Key': apiKey,
        },
        body: JSON.stringify({
          image_data: imageData,
          filename,
          post_id: postId,
        }),
      });

      const data = (await response.json()) as {
        success: boolean;
        data?: { url: string; attachment_id: number };
        error?: { message: string };
      };

      if (!response.ok || !data.success) {
        return {
          success: false,
          error: data.error?.message || `Upload failed: ${response.status}`,
        };
      }

      return {
        success: true,
        url: data.data?.url,
        attachmentId: data.data?.attachment_id,
      };
    } catch (error) {
      return {
        success: false,
        error: error instanceof Error ? error.message : 'Image upload failed',
      };
    }
  }
}
