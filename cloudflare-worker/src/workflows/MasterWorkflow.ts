/**
 * Master Workflow
 *
 * Orchestrates the automated content processing pipeline:
 * 1. Acquire lock (prevent concurrent execution)
 * 2. Fetch RSS feeds from WordPress
 * 3. Collect and parse feed items
 * 4. AI Curation (filter relevant items)
 * 5. Dispatch Item Workflows for approved items
 *
 * @since 2.0.0
 */

import {
  WorkflowEntrypoint,
  WorkflowEvent,
  WorkflowStep,
} from 'cloudflare:workers';

import type { Env, MasterWorkflowParams, Feed, FeedItem, WorkflowResult } from '../types';
import { WordPressService } from '../services/wordpress';
import { AIService } from '../services/ai';
import { getConfigValue } from '../handlers/config';

const LOCK_KEY = 'master_workflow_lock';
const LOCK_TTL = 3600; // 1 hour

export class MasterWorkflow extends WorkflowEntrypoint<Env, MasterWorkflowParams> {
  /**
   * Main workflow execution
   */
  async run(event: WorkflowEvent<MasterWorkflowParams>, step: WorkflowStep): Promise<WorkflowResult> {
    const { triggered_by, timestamp } = event.payload;
    console.log(`[MasterWorkflow] Started - triggered_by: ${triggered_by}, timestamp: ${timestamp}`);

    const startTime = Date.now();
    let itemsProcessed = 0;
    let itemsFailed = 0;

    try {
      // Step 1: Acquire lock
      const lockAcquired = await step.do('acquire-lock', async () => {
        return this.acquireLock();
      });

      if (!lockAcquired) {
        console.log('[MasterWorkflow] Lock not acquired, another instance is running');
        return {
          success: false,
          error: 'Another instance is already running',
        };
      }

      // Step 2: Get feeds from WordPress
      const feeds = await step.do('fetch-feeds', async () => {
        const wpService = new WordPressService(this.env);
        const result = await wpService.getFeeds();
        if (!result.success || !result.feeds) {
          throw new Error(result.error || 'Failed to fetch feeds');
        }
        return result.feeds.filter((f) => f.is_active && f.auto_rewrite);
      });

      console.log(`[MasterWorkflow] Found ${feeds.length} active feeds with auto_rewrite`);

      if (feeds.length === 0) {
        await this.releaseLock();
        return {
          success: true,
          items_processed: 0,
          items_failed: 0,
        };
      }

      // Step 3: Get pending items
      const pendingItems = await step.do('fetch-pending-items', async () => {
        const wpService = new WordPressService(this.env);
        const dailyLimit = parseInt((await getConfigValue(this.env, 'daily_limit')) || '10');
        const result = await wpService.getPendingItems(dailyLimit);

        if (!result.success || !result.items) {
          throw new Error(result.error || 'Failed to fetch pending items');
        }
        return result.items;
      });

      console.log(`[MasterWorkflow] Found ${pendingItems.length} pending items`);

      if (pendingItems.length === 0) {
        await this.releaseLock();
        return {
          success: true,
          items_processed: 0,
          items_failed: 0,
        };
      }

      // Step 4: AI Curation (filter items worth processing)
      const approvedItems = await step.do('ai-curation', async () => {
        return this.curateItems(pendingItems, feeds);
      });

      console.log(`[MasterWorkflow] ${approvedItems.length} items approved for processing`);

      // Step 5: Dispatch Item Workflows
      const dispatchResults = await step.do('dispatch-workflows', async () => {
        return this.dispatchItemWorkflows(approvedItems, feeds);
      });

      itemsProcessed = dispatchResults.successful;
      itemsFailed = dispatchResults.failed;

      // Release lock
      await this.releaseLock();

      const duration = Date.now() - startTime;
      console.log(
        `[MasterWorkflow] Completed in ${duration}ms - processed: ${itemsProcessed}, failed: ${itemsFailed}`
      );

      return {
        success: true,
        items_processed: itemsProcessed,
        items_failed: itemsFailed,
      };
    } catch (error) {
      console.error('[MasterWorkflow] Error:', error);

      // Always release lock on error
      await this.releaseLock();

      return {
        success: false,
        items_processed: itemsProcessed,
        items_failed: itemsFailed,
        error: error instanceof Error ? error.message : 'Unknown error',
      };
    }
  }

  /**
   * Acquire distributed lock
   */
  private async acquireLock(): Promise<boolean> {
    const existingLock = await this.env.LOCK_KV.get(LOCK_KEY);
    if (existingLock) {
      const lockData = JSON.parse(existingLock);
      const lockAge = Date.now() - lockData.acquired_at;

      // If lock is old (stale), we can acquire it
      if (lockAge < LOCK_TTL * 1000) {
        return false;
      }
    }

    // Acquire lock
    await this.env.LOCK_KV.put(
      LOCK_KEY,
      JSON.stringify({
        workflow_id: crypto.randomUUID(),
        acquired_at: Date.now(),
      }),
      { expirationTtl: LOCK_TTL }
    );

    return true;
  }

  /**
   * Release distributed lock
   */
  private async releaseLock(): Promise<void> {
    await this.env.LOCK_KV.delete(LOCK_KEY);
  }

  /**
   * AI-powered curation to filter items worth processing
   */
  private async curateItems(items: FeedItem[], feeds: Feed[]): Promise<FeedItem[]> {
    const curationThreshold = parseFloat(
      (await getConfigValue(this.env, 'curation_threshold')) || '0.8'
    );

    const aiService = new AIService(this.env);
    const approvedItems: FeedItem[] = [];

    // Process in batches to avoid rate limits
    const batchSize = 5;
    for (let i = 0; i < items.length; i += batchSize) {
      const batch = items.slice(i, i + batchSize);

      const prompt = this.buildCurationPrompt(batch, feeds);

      const result = await aiService.complete({
        provider: 'chatgpt',
        model: 'gpt-4o-mini', // Use cheaper model for curation
        messages: [
          {
            role: 'system',
            content:
              'You are a content curator. Evaluate each article and return a JSON array with scores.',
          },
          { role: 'user', content: prompt },
        ],
        temperature: 0.3,
        max_tokens: 500,
      });

      if (!result.success || !result.content) {
        // On error, approve all items in batch (fail-open)
        approvedItems.push(...batch);
        continue;
      }

      try {
        // Parse AI response
        const scores = this.parseCurationResponse(result.content);

        for (let j = 0; j < batch.length; j++) {
          const score = scores[j] ?? 0.8;
          if (score >= curationThreshold) {
            approvedItems.push(batch[j]);
          } else {
            // Mark as skipped
            const wpService = new WordPressService(this.env);
            await wpService.updateItemStatus(batch[j].id, 'skipped');
          }
        }
      } catch {
        // On parse error, approve all items in batch
        approvedItems.push(...batch);
      }

      // Rate limiting
      await new Promise((resolve) => setTimeout(resolve, 500));
    }

    return approvedItems;
  }

  /**
   * Build curation prompt
   */
  private buildCurationPrompt(items: FeedItem[], feeds: Feed[]): string {
    const itemList = items
      .map((item, index) => {
        const feed = feeds.find((f) => f.id === item.feed_id);
        return `${index + 1}. Title: "${item.title}"\n   Feed: ${feed?.name || 'Unknown'}\n   Published: ${item.pub_date}`;
      })
      .join('\n\n');

    return `Evaluate these articles for blog rewriting potential.
Score each from 0.0 to 1.0 based on:
- Relevance and interest
- Information value
- Rewrite potential

Return ONLY a JSON array of scores in order: [0.9, 0.7, ...]

Articles:
${itemList}`;
  }

  /**
   * Parse curation response
   */
  private parseCurationResponse(content: string): number[] {
    // Extract JSON array from response
    const match = content.match(/\[[\d.,\s]+\]/);
    if (!match) {
      throw new Error('No valid JSON array found');
    }
    return JSON.parse(match[0]) as number[];
  }

  /**
   * Dispatch Item Workflows for approved items
   */
  private async dispatchItemWorkflows(
    items: FeedItem[],
    feeds: Feed[]
  ): Promise<{ successful: number; failed: number }> {
    let successful = 0;
    let failed = 0;

    const wpService = new WordPressService(this.env);
    const wordpressUrl = await getConfigValue(this.env, 'wordpress_url');
    const hmacSecret = this.env.HMAC_SECRET;

    for (const item of items) {
      try {
        const feed = feeds.find((f) => f.id === item.feed_id);

        // Mark as processing
        await wpService.updateItemStatus(item.id, 'processing');

        // Dispatch workflow
        await this.env.ITEM_WORKFLOW.create({
          id: `item-${item.id}-${Date.now()}`,
          params: {
            item_id: item.id,
            feed_id: item.feed_id,
            source_url: item.link,
            source_content: item.content,
            language: 'ko',
            ai_provider: 'chatgpt',
            callback_url: `${wordpressUrl}/wp-json/aicr/v1/webhook`,
            callback_secret: hmacSecret,
            options: {
              auto_publish: feed?.auto_publish ?? true,
              publish_threshold: 8,
              generate_images: true,
            },
          },
        });

        successful++;

        // Stagger workflow starts
        await new Promise((resolve) => setTimeout(resolve, 2000));
      } catch (error) {
        console.error(`[MasterWorkflow] Failed to dispatch item ${item.id}:`, error);
        await wpService.updateItemStatus(item.id, 'failed');
        failed++;
      }
    }

    return { successful, failed };
  }
}
