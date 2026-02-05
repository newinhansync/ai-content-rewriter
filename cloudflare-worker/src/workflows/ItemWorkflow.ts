/**
 * Item Workflow
 *
 * Processes individual feed items through multi-step AI prompting:
 * 1. Content Extraction (from URL or provided content)
 * 2. Outline Generation
 * 3. Content Writing (1.5x expansion)
 * 4. SEO Optimization
 * 5. Self-Critique (quality check)
 * 6. Image Generation
 * 7. WordPress Publish (via webhook)
 *
 * @since 2.0.0
 */

import {
  WorkflowEntrypoint,
  WorkflowEvent,
  WorkflowStep,
} from 'cloudflare:workers';

import type {
  Env,
  ItemWorkflowParams,
  WorkflowResult,
  OutlineStep,
  ContentStep,
  SEOStep,
  CritiqueStep,
  WebhookResult,
  ProcessingMetrics,
} from '../types';
import { AIService } from '../services/ai';
import { WordPressService } from '../services/wordpress';
import { updateTaskStatus } from '../handlers/status';

export class ItemWorkflow extends WorkflowEntrypoint<Env, ItemWorkflowParams> {
  /**
   * Main workflow execution
   */
  async run(event: WorkflowEvent<ItemWorkflowParams>, step: WorkflowStep): Promise<WorkflowResult> {
    const params = event.payload;
    const taskId = event.id || `item-${params.item_id}`;
    const startTime = Date.now();
    const stepsCompleted: string[] = [];
    let totalTokens = { input: 0, output: 0, total: 0 };
    let retryCount = 0;

    console.log(`[ItemWorkflow] Started - task_id: ${taskId}, item_id: ${params.item_id}`);

    try {
      // Update status to processing
      await updateTaskStatus(this.env, taskId, {
        status: 'processing',
        current_step: 'extraction',
        progress: 10,
      });

      // Step 1: Content Extraction
      const extractedContent = await step.do('extract-content', async () => {
        return this.extractContent(params);
      });
      stepsCompleted.push('extraction');

      await updateTaskStatus(this.env, taskId, {
        current_step: 'outline',
        progress: 20,
      });

      // Step 2: Outline Generation
      const outline = await step.do('generate-outline', async () => {
        const result = await this.generateOutline(extractedContent, params);
        totalTokens = this.addTokens(totalTokens, result.tokens);
        return result.outline;
      });
      stepsCompleted.push('outline');

      await updateTaskStatus(this.env, taskId, {
        current_step: 'content',
        progress: 40,
      });

      // Step 3: Content Writing (with potential retry)
      let content: ContentStep;
      let critiqueResult: CritiqueStep;

      const contentResult = await step.do('write-content', async () => {
        const result = await this.writeContent(extractedContent, outline, params);
        totalTokens = this.addTokens(totalTokens, result.tokens);
        return result.content;
      });
      content = contentResult;
      stepsCompleted.push('content');

      await updateTaskStatus(this.env, taskId, {
        current_step: 'critique',
        progress: 60,
      });

      // Step 5: Self-Critique
      critiqueResult = await step.do('self-critique', async () => {
        const result = await this.selfCritique(content, params);
        totalTokens = this.addTokens(totalTokens, result.tokens);
        return result.critique;
      });
      stepsCompleted.push('critique');

      // Retry content if score is too low
      if (critiqueResult.should_retry && retryCount === 0) {
        retryCount++;

        await updateTaskStatus(this.env, taskId, {
          current_step: 'content_retry',
          progress: 50,
        });

        const retryResult = await step.do('retry-content', async () => {
          const result = await this.writeContent(extractedContent, outline, params, critiqueResult.feedback);
          totalTokens = this.addTokens(totalTokens, result.tokens);
          return result.content;
        });
        content = retryResult;
        stepsCompleted.push('content_retry');

        // Re-critique
        critiqueResult = await step.do('self-critique-retry', async () => {
          const result = await this.selfCritique(content, params);
          totalTokens = this.addTokens(totalTokens, result.tokens);
          return result.critique;
        });
        stepsCompleted.push('critique_retry');
      }

      await updateTaskStatus(this.env, taskId, {
        current_step: 'seo',
        progress: 70,
      });

      // Step 4: SEO Optimization
      const seo = await step.do('optimize-seo', async () => {
        const result = await this.optimizeSEO(content, params);
        totalTokens = this.addTokens(totalTokens, result.tokens);
        return result.seo;
      });
      stepsCompleted.push('seo');

      await updateTaskStatus(this.env, taskId, {
        current_step: 'image',
        progress: 80,
      });

      // Step 6: Image Generation (optional)
      let featuredImageUrl: string | undefined;
      if (params.options.generate_images) {
        try {
          featuredImageUrl = await step.do('generate-image', async () => {
            return this.generateFeaturedImage(content.title, params);
          });
          stepsCompleted.push('image');
        } catch (error) {
          console.warn('[ItemWorkflow] Image generation failed:', error);
          // Continue without image
        }
      }

      await updateTaskStatus(this.env, taskId, {
        current_step: 'publish',
        progress: 90,
      });

      // Step 7: Send to WordPress via webhook
      const webhookResult: WebhookResult = {
        title: content.title,
        content: content.content,
        excerpt: seo.excerpt,
        category_suggestion: seo.category_suggestion,
        tags: seo.tags,
        meta_title: seo.meta_title,
        meta_description: seo.meta_description,
        featured_image_url: featuredImageUrl,
      };

      const metrics: ProcessingMetrics = {
        processing_time_ms: Date.now() - startTime,
        token_usage: totalTokens,
        steps_completed: stepsCompleted,
        retry_count: retryCount,
      };

      await step.do('send-webhook', async () => {
        const wpService = new WordPressService(this.env);
        const result = await wpService.sendSuccessWebhook(
          params.callback_url,
          params.callback_secret,
          taskId,
          params.item_id,
          critiqueResult.score,
          webhookResult,
          metrics
        );

        if (!result.success) {
          throw new Error(result.error || 'Webhook failed');
        }
      });
      stepsCompleted.push('webhook');

      // Update final status
      await updateTaskStatus(this.env, taskId, {
        status: 'completed',
        progress: 100,
        current_step: 'completed',
        result: webhookResult,
      });

      console.log(
        `[ItemWorkflow] Completed - task_id: ${taskId}, score: ${critiqueResult.score}, duration: ${Date.now() - startTime}ms`
      );

      return { success: true };
    } catch (error) {
      console.error('[ItemWorkflow] Error:', error);

      const errorMessage = error instanceof Error ? error.message : 'Unknown error';

      // Send failure webhook
      try {
        const wpService = new WordPressService(this.env);
        await wpService.sendFailureWebhook(
          params.callback_url,
          params.callback_secret,
          taskId,
          params.item_id,
          'WORKFLOW_ERROR',
          errorMessage,
          {
            processing_time_ms: Date.now() - startTime,
            token_usage: totalTokens,
            steps_completed: stepsCompleted,
            retry_count: retryCount,
          }
        );
      } catch {
        console.error('[ItemWorkflow] Failed to send failure webhook');
      }

      // Update task status
      await updateTaskStatus(this.env, taskId, {
        status: 'failed',
        error: { code: 'WORKFLOW_ERROR', message: errorMessage },
      });

      return { success: false, error: errorMessage };
    }
  }

  /**
   * Step 1: Extract content from URL or use provided content
   */
  private async extractContent(params: ItemWorkflowParams): Promise<string> {
    if (params.source_content) {
      return params.source_content;
    }

    if (!params.source_url) {
      throw new Error('No content source provided');
    }

    // Fetch and extract content from URL
    const response = await fetch(params.source_url);
    if (!response.ok) {
      throw new Error(`Failed to fetch URL: ${response.status}`);
    }

    const html = await response.text();

    // Basic content extraction (remove scripts, styles, and extract body text)
    const cleanedHtml = html
      .replace(/<script[^>]*>[\s\S]*?<\/script>/gi, '')
      .replace(/<style[^>]*>[\s\S]*?<\/style>/gi, '')
      .replace(/<nav[^>]*>[\s\S]*?<\/nav>/gi, '')
      .replace(/<header[^>]*>[\s\S]*?<\/header>/gi, '')
      .replace(/<footer[^>]*>[\s\S]*?<\/footer>/gi, '')
      .replace(/<aside[^>]*>[\s\S]*?<\/aside>/gi, '');

    // Extract text content
    const textContent = cleanedHtml
      .replace(/<[^>]+>/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();

    // Limit to reasonable length
    return textContent.slice(0, 15000);
  }

  /**
   * Step 2: Generate outline
   */
  private async generateOutline(
    content: string,
    params: ItemWorkflowParams
  ): Promise<{ outline: OutlineStep; tokens: { input: number; output: number; total: number } }> {
    const aiService = new AIService(this.env);

    const result = await aiService.complete({
      provider: params.ai_provider,
      model: params.ai_model,
      messages: [
        {
          role: 'system',
          content: `You are a professional content strategist. Create detailed blog post outlines in ${params.language === 'ko' ? 'Korean' : 'English'}.`,
        },
        {
          role: 'user',
          content: `Based on this source content, create a detailed outline for a blog post.
The outline should:
- Have an engaging, SEO-friendly title
- Include 4-6 main sections with clear headings
- List 3-5 key points for each section
- Target 1.5x the original content length

Source content:
${content.slice(0, 5000)}

Return JSON format:
{
  "title": "...",
  "sections": [
    {"heading": "...", "key_points": ["...", "..."]}
  ],
  "estimated_word_count": 1500
}`,
        },
      ],
      temperature: 0.7,
      max_tokens: 1500,
    });

    if (!result.success || !result.content) {
      throw new Error(result.error || 'Outline generation failed');
    }

    // Parse JSON from response
    const jsonMatch = result.content.match(/\{[\s\S]*\}/);
    if (!jsonMatch) {
      throw new Error('Invalid outline format');
    }

    const outline = JSON.parse(jsonMatch[0]) as OutlineStep;

    return {
      outline,
      tokens: result.usage || { input: 0, output: 0, total: 0 },
    };
  }

  /**
   * Step 3: Write content based on outline
   */
  private async writeContent(
    sourceContent: string,
    outline: OutlineStep,
    params: ItemWorkflowParams,
    feedback?: CritiqueStep['feedback']
  ): Promise<{ content: ContentStep; tokens: { input: number; output: number; total: number } }> {
    const aiService = new AIService(this.env);

    let feedbackInstruction = '';
    if (feedback) {
      feedbackInstruction = `
IMPORTANT: Previous version had these issues:
Weaknesses: ${feedback.weaknesses.join(', ')}
Suggestions: ${feedback.suggestions.join(', ')}
Please address these in your rewrite.`;
    }

    const result = await aiService.complete({
      provider: params.ai_provider,
      model: params.ai_model,
      messages: [
        {
          role: 'system',
          content: `You are a professional blog writer. Write engaging, informative content in ${params.language === 'ko' ? 'Korean' : 'English'}.
Use HTML formatting: <h2>, <h3>, <p>, <ul>, <li>, <strong>, <em>.
Write naturally and avoid AI-like patterns.`,
        },
        {
          role: 'user',
          content: `Write a complete blog post based on this outline.

Title: ${outline.title}

Outline:
${outline.sections.map((s) => `## ${s.heading}\n${s.key_points.map((p) => `- ${p}`).join('\n')}`).join('\n\n')}

Target word count: ${outline.estimated_word_count}

Source material for reference:
${sourceContent.slice(0, 8000)}

${feedbackInstruction}

Write the full article in HTML format.`,
        },
      ],
      temperature: 0.8,
      max_tokens: 4096,
    });

    if (!result.success || !result.content) {
      throw new Error(result.error || 'Content writing failed');
    }

    const wordCount = result.content.split(/\s+/).length;

    return {
      content: {
        title: outline.title,
        content: result.content,
        word_count: wordCount,
      },
      tokens: result.usage || { input: 0, output: 0, total: 0 },
    };
  }

  /**
   * Step 4: SEO Optimization
   */
  private async optimizeSEO(
    content: ContentStep,
    params: ItemWorkflowParams
  ): Promise<{ seo: SEOStep; tokens: { input: number; output: number; total: number } }> {
    const aiService = new AIService(this.env);

    const result = await aiService.complete({
      provider: params.ai_provider,
      model: params.ai_model,
      messages: [
        {
          role: 'system',
          content: `You are an SEO specialist. Generate optimized metadata in ${params.language === 'ko' ? 'Korean' : 'English'}.`,
        },
        {
          role: 'user',
          content: `Generate SEO metadata for this blog post.

Title: ${content.title}

Content preview:
${content.content.slice(0, 2000)}

Return JSON format:
{
  "meta_title": "... (60 chars max)",
  "meta_description": "... (160 chars max)",
  "keywords": ["keyword1", "keyword2", ...],
  "tags": ["tag1", "tag2", ...],
  "category_suggestion": "...",
  "excerpt": "... (2-3 sentences)"
}`,
        },
      ],
      temperature: 0.5,
      max_tokens: 800,
    });

    if (!result.success || !result.content) {
      throw new Error(result.error || 'SEO optimization failed');
    }

    const jsonMatch = result.content.match(/\{[\s\S]*\}/);
    if (!jsonMatch) {
      throw new Error('Invalid SEO format');
    }

    const seo = JSON.parse(jsonMatch[0]) as SEOStep;

    return {
      seo,
      tokens: result.usage || { input: 0, output: 0, total: 0 },
    };
  }

  /**
   * Step 5: Self-Critique
   */
  private async selfCritique(
    content: ContentStep,
    params: ItemWorkflowParams
  ): Promise<{ critique: CritiqueStep; tokens: { input: number; output: number; total: number } }> {
    const aiService = new AIService(this.env);

    const result = await aiService.complete({
      provider: params.ai_provider,
      model: params.ai_model,
      messages: [
        {
          role: 'system',
          content: `You are a content quality reviewer. Evaluate blog posts objectively.`,
        },
        {
          role: 'user',
          content: `Evaluate this blog post quality on a scale of 1-10.

Title: ${content.title}

Content:
${content.content.slice(0, 6000)}

Evaluate:
- Clarity and readability
- Information depth
- Engagement and flow
- SEO friendliness
- Grammar and style

Return JSON format:
{
  "score": 8.5,
  "feedback": {
    "strengths": ["...", "..."],
    "weaknesses": ["...", "..."],
    "suggestions": ["...", "..."]
  },
  "should_retry": false
}

Set should_retry to true only if score < 7.`,
        },
      ],
      temperature: 0.3,
      max_tokens: 800,
    });

    if (!result.success || !result.content) {
      // Default to passing score if critique fails
      return {
        critique: {
          score: 8,
          feedback: { strengths: [], weaknesses: [], suggestions: [] },
          should_retry: false,
        },
        tokens: result.usage || { input: 0, output: 0, total: 0 },
      };
    }

    const jsonMatch = result.content.match(/\{[\s\S]*\}/);
    if (!jsonMatch) {
      return {
        critique: {
          score: 8,
          feedback: { strengths: [], weaknesses: [], suggestions: [] },
          should_retry: false,
        },
        tokens: result.usage || { input: 0, output: 0, total: 0 },
      };
    }

    const critique = JSON.parse(jsonMatch[0]) as CritiqueStep;

    return {
      critique,
      tokens: result.usage || { input: 0, output: 0, total: 0 },
    };
  }

  /**
   * Step 6: Generate featured image
   */
  private async generateFeaturedImage(title: string, params: ItemWorkflowParams): Promise<string | undefined> {
    const aiService = new AIService(this.env);

    // Generate image prompt
    const promptResult = await aiService.complete({
      provider: 'chatgpt',
      model: 'gpt-4o-mini',
      messages: [
        {
          role: 'system',
          content: 'Generate a concise image prompt for an AI image generator.',
        },
        {
          role: 'user',
          content: `Create a featured image prompt for a blog post titled: "${title}"
The image should be professional, modern, and relevant.
Return only the prompt, no explanation. Max 100 words.`,
        },
      ],
      temperature: 0.7,
      max_tokens: 150,
    });

    if (!promptResult.success || !promptResult.content) {
      return undefined;
    }

    // Generate image
    const imageResult = await aiService.generateImage(promptResult.content, {
      aspectRatio: '16:9',
    });

    if (!imageResult.success || !imageResult.imageData) {
      return undefined;
    }

    // Upload to WordPress
    const wpService = new WordPressService(this.env);
    const uploadResult = await wpService.uploadImage(
      imageResult.imageData,
      `${title.slice(0, 50).replace(/[^a-zA-Z0-9]/g, '-')}.png`
    );

    return uploadResult.success ? uploadResult.url : undefined;
  }

  /**
   * Helper: Add token counts
   */
  private addTokens(
    total: { input: number; output: number; total: number },
    add: { input: number; output: number; total: number } | { prompt_tokens: number; completion_tokens: number; total_tokens: number } | undefined
  ): { input: number; output: number; total: number } {
    if (!add) return total;

    if ('prompt_tokens' in add) {
      return {
        input: total.input + add.prompt_tokens,
        output: total.output + add.completion_tokens,
        total: total.total + add.total_tokens,
      };
    }

    return {
      input: total.input + add.input,
      output: total.output + add.output,
      total: total.total + add.total,
    };
  }
}
