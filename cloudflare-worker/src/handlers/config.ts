/**
 * Configuration Handler
 *
 * Handles configuration sync from WordPress
 * @since 2.0.0
 */

import type { Context } from 'hono';
import type { Env, ApiResponse, WorkerConfig } from '../types';

/**
 * Handle POST /api/sync-config
 *
 * Receives configuration from WordPress and stores in KV
 */
export async function handleSyncConfig(c: Context<{ Bindings: Env }>): Promise<Response> {
  const env = c.env;

  try {
    const config = await c.req.json<WorkerConfig>();

    // Validate required fields
    if (!config.wordpress_url || !config.api_key) {
      return c.json<ApiResponse>(
        {
          success: false,
          error: {
            code: 'INVALID_REQUEST',
            message: 'Missing required configuration fields',
          },
        },
        400
      );
    }

    // Store configuration in KV
    await env.CONFIG_KV.put('wordpress_config', JSON.stringify(config), {
      metadata: {
        updated_at: new Date().toISOString(),
        version: '2.0.0',
      },
    });

    // Store individual settings for quick access
    await Promise.all([
      env.CONFIG_KV.put('wordpress_url', config.wordpress_url),
      env.CONFIG_KV.put('publish_threshold', String(config.publish_threshold || 8)),
      env.CONFIG_KV.put('daily_limit', String(config.daily_limit || 10)),
      env.CONFIG_KV.put('curation_threshold', String(config.curation_threshold || 0.8)),
    ]);

    // Store prompt templates if provided
    if (config.prompt_templates) {
      await env.CONFIG_KV.put('prompt_templates', JSON.stringify(config.prompt_templates));
    }

    // Store styles if provided
    if (config.writing_style) {
      await env.CONFIG_KV.put('writing_style', config.writing_style);
    }
    if (config.image_style) {
      await env.CONFIG_KV.put('image_style', config.image_style);
    }

    console.log('[Config] Configuration synced from WordPress');

    return c.json<ApiResponse>({
      success: true,
      data: {
        message: 'Configuration synced successfully',
        synced_at: new Date().toISOString(),
      },
    });
  } catch (error) {
    console.error('[Config] Error:', error);

    return c.json<ApiResponse>(
      {
        success: false,
        error: {
          code: 'INTERNAL_ERROR',
          message: error instanceof Error ? error.message : 'Failed to sync configuration',
        },
      },
      500
    );
  }
}

/**
 * Get configuration from KV
 */
export async function getConfig(env: Env): Promise<WorkerConfig | null> {
  try {
    const configStr = await env.CONFIG_KV.get('wordpress_config');
    if (!configStr) {
      return null;
    }
    return JSON.parse(configStr);
  } catch (error) {
    console.error('[Config] Failed to get config:', error);
    return null;
  }
}

/**
 * Get individual config value
 */
export async function getConfigValue(env: Env, key: string): Promise<string | null> {
  return env.CONFIG_KV.get(key);
}
