/**
 * AI Content Rewriter Worker - Type Definitions
 * @since 2.0.0
 */

// ============================================================================
// Environment Bindings
// ============================================================================

export interface Env {
  // KV Namespaces
  CONFIG_KV: KVNamespace;
  LOCK_KV: KVNamespace;

  // D1 Database
  DB: D1Database;

  // R2 Bucket
  IMAGES_BUCKET: R2Bucket;

  // Workflows
  MASTER_WORKFLOW: Workflow;
  ITEM_WORKFLOW: Workflow;

  // Environment Variables
  ENVIRONMENT: string;
  LOG_LEVEL: string;
  MAX_RETRIES: string;
  RETRY_DELAY_MS: string;

  // Secrets (set via wrangler secret)
  WORKER_SECRET: string;
  HMAC_SECRET: string;
  WP_API_KEY: string;
  OPENAI_API_KEY: string;
  GEMINI_API_KEY: string;
  WORDPRESS_URL: string;
}

// ============================================================================
// Rewrite Request/Response
// ============================================================================

export interface RewriteRequest {
  task_id: string;
  task_type: 'rewrite' | 'batch_rewrite';
  callback_url: string;
  callback_secret: string;
  payload: RewritePayload;
}

export interface RewritePayload {
  source_url?: string;
  source_content?: string;
  item_id?: number;
  language: string;
  ai_provider: 'chatgpt' | 'gemini';
  ai_model?: string;
}

export interface RewriteResponse {
  success: boolean;
  task_id?: string;
  message?: string;
  estimated_time_seconds?: number;
  error?: ApiError;
}

// ============================================================================
// Webhook Payload (Worker → WordPress)
// ============================================================================

export interface WebhookPayload {
  task_id: string;
  item_id?: number;
  status: 'completed' | 'failed';
  quality_score?: number;
  result?: WebhookResult;
  error?: WebhookError;
  metrics?: ProcessingMetrics;
}

export interface WebhookResult {
  title: string;
  content: string;
  excerpt: string;
  category_suggestion?: string;
  tags: string[];
  meta_title: string;
  meta_description: string;
  featured_image_url?: string;
}

export interface WebhookError {
  code: string;
  message: string;
  details?: Record<string, unknown>;
}

export interface ProcessingMetrics {
  processing_time_ms: number;
  token_usage: {
    input: number;
    output: number;
    total: number;
  };
  steps_completed: string[];
  retry_count: number;
}

// ============================================================================
// Feed & Item Types
// ============================================================================

export interface Feed {
  id: number;
  name: string;
  url: string;
  category_id?: number;
  is_active: boolean;
  auto_rewrite: boolean;
  auto_publish: boolean;
  fetch_interval: number;
  last_fetched_at?: string;
}

export interface FeedItem {
  id: number;
  feed_id: number;
  guid: string;
  title: string;
  link: string;
  content?: string;
  pub_date: string;
  status: FeedItemStatus;
  post_id?: number;
  quality_score?: number;
  created_at: string;
  updated_at: string;
}

export type FeedItemStatus =
  | 'new'
  | 'queued'
  | 'processing'
  | 'completed'
  | 'published'
  | 'draft_saved'
  | 'skipped'
  | 'failed';

// ============================================================================
// AI Service Types
// ============================================================================

export type AIProvider = 'chatgpt' | 'gemini';

export interface AIMessage {
  role: 'system' | 'user' | 'assistant';
  content: string;
}

export interface AICompletionRequest {
  provider: AIProvider;
  model?: string;
  messages: AIMessage[];
  temperature?: number;
  max_tokens?: number;
}

export interface AICompletionResponse {
  success: boolean;
  content?: string;
  usage?: {
    prompt_tokens: number;
    completion_tokens: number;
    total_tokens: number;
  };
  error?: string;
}

// ============================================================================
// Multi-Step Prompting Types
// ============================================================================

export interface OutlineStep {
  title: string;
  sections: {
    heading: string;
    key_points: string[];
  }[];
  estimated_word_count: number;
}

export interface ContentStep {
  title: string;
  content: string;
  word_count: number;
}

export interface SEOStep {
  meta_title: string;
  meta_description: string;
  keywords: string[];
  tags: string[];
  category_suggestion: string;
  excerpt: string;
}

export interface CritiqueStep {
  score: number;
  feedback: {
    strengths: string[];
    weaknesses: string[];
    suggestions: string[];
  };
  should_retry: boolean;
}

// ============================================================================
// Workflow Types
// ============================================================================

export interface MasterWorkflowParams {
  triggered_by: 'cron' | 'manual';
  timestamp: string;
}

export interface ItemWorkflowParams {
  item_id: number;
  feed_id: number;
  source_url: string;
  source_content?: string;
  language: string;
  ai_provider: AIProvider;
  ai_model?: string;
  callback_url: string;
  callback_secret: string;
  options: {
    auto_publish: boolean;
    publish_threshold: number;
    generate_images: boolean;
  };
}

export interface WorkflowResult {
  success: boolean;
  items_processed?: number;
  items_failed?: number;
  error?: string;
}

// ============================================================================
// Configuration Types
// ============================================================================

export interface WorkerConfig {
  wordpress_url: string;
  api_key: string;
  hmac_secret: string;
  publish_threshold: number;
  daily_limit: number;
  curation_threshold: number;
  prompt_templates?: Record<string, string>;
  writing_style?: string;
  image_style?: string;
}

// ============================================================================
// API Error Types
// ============================================================================

export interface ApiError {
  code: string;
  message: string;
  details?: Record<string, unknown>;
}

export interface ApiResponse<T = unknown> {
  success: boolean;
  data?: T;
  error?: ApiError;
}

// ============================================================================
// Task Status Types
// ============================================================================

export interface TaskStatus {
  task_id: string;
  status: 'pending' | 'processing' | 'completed' | 'failed';
  progress?: number;
  current_step?: string;
  result?: WebhookResult;
  error?: WebhookError;
  created_at: string;
  updated_at: string;
}

// ============================================================================
// Health Check Types
// ============================================================================

export interface HealthStatus {
  status: 'healthy' | 'degraded' | 'unhealthy';
  version: string;
  timestamp: string;
  checks: {
    kv: boolean;
    d1: boolean;
    r2: boolean;
    openai?: boolean;
    gemini?: boolean;
  };
}
