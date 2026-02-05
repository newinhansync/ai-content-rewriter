# AI Content Rewriter - Cloudflare Worker

Cloudflare Worker for the AI Content Rewriter WordPress Plugin v2.0.

## Features

- **Async Processing**: No WordPress timeout issues - all AI processing happens in Cloudflare
- **Multi-Step Prompting**: Outline → Content → SEO → Self-Critique pipeline
- **Durable Workflows**: Long-running tasks with checkpoint/resume capability
- **Auto Curation**: AI-powered filtering of feed items worth processing
- **Quality Control**: Self-critique with retry mechanism for low-quality content
- **Image Generation**: Automatic featured image generation via Gemini Imagen

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│                   HTTP Worker                            │
│  POST /api/rewrite    - Manual rewrite request          │
│  GET  /api/status/:id - Check task status               │
│  POST /api/sync-config - Sync WordPress config          │
│  GET  /api/health     - Health check                    │
└─────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────┐
│                   Master Workflow                        │
│  1. Acquire lock                                         │
│  2. Fetch feeds from WordPress                          │
│  3. Get pending items                                    │
│  4. AI Curation (filter items)                          │
│  5. Dispatch Item Workflows                             │
└─────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────┐
│                   Item Workflow                          │
│  1. Extract content from URL                             │
│  2. Generate outline                                     │
│  3. Write content (1.5x expansion)                      │
│  4. SEO optimization                                     │
│  5. Self-critique (retry if score < 7)                  │
│  6. Generate featured image                              │
│  7. Send to WordPress via webhook                        │
└─────────────────────────────────────────────────────────┘
```

## Prerequisites

- Node.js 18+
- Cloudflare account with Workers, KV, D1, R2, and Workflows access
- Wrangler CLI (`npm install -g wrangler`)

## Setup

### 1. Clone and Install

```bash
cd cloudflare-worker
npm install
```

### 2. Authenticate with Cloudflare

```bash
wrangler login
```

### 3. Create Required Resources

```bash
# Create KV namespaces
wrangler kv namespace create CONFIG_KV
wrangler kv namespace create LOCK_KV

# Create D1 database
wrangler d1 create aicr-worker-db

# Create R2 bucket
wrangler r2 bucket create aicr-images
```

### 4. Update wrangler.toml

Replace the placeholder IDs in `wrangler.toml` with the IDs from step 3.

### 5. Initialize Database

```bash
wrangler d1 execute aicr-worker-db --file=schema.sql
```

### 6. Set Secrets

```bash
# WordPress → Worker authentication
wrangler secret put WORKER_SECRET

# Worker → WordPress webhook signature
wrangler secret put HMAC_SECRET

# WordPress REST API authentication
wrangler secret put WP_API_KEY

# AI API keys
wrangler secret put OPENAI_API_KEY
wrangler secret put GEMINI_API_KEY

# WordPress URL
wrangler secret put WORDPRESS_URL
```

## Development

```bash
# Start local development server
npm run dev

# Type checking
npm run typecheck

# Linting
npm run lint
npm run lint:fix

# Format code
npm run format

# Run all checks
npm run check
```

## Testing

이 프로젝트는 Vitest와 `@cloudflare/vitest-pool-workers`를 사용하여 실제 Workers 런타임 환경을 시뮬레이션합니다.

```bash
# Run tests
npm test

# Run tests in watch mode
npm run test:watch

# Run tests with coverage
npm run test:coverage

# Run tests with UI
npm run test:ui
```

### Test Structure

```
tests/
├── setup.ts              # Global test setup and utilities
├── handlers/             # API endpoint tests
│   ├── rewrite.test.ts
│   └── status.test.ts
├── services/             # Service layer tests
│   ├── ai.test.ts
│   └── wordpress.test.ts
├── utils/                # Utility function tests
│   └── auth.test.ts
└── workflows/            # Workflow integration tests
    ├── MasterWorkflow.test.ts
    └── ItemWorkflow.test.ts
```

### Coverage Thresholds

- Statements: 70%
- Branches: 60%
- Functions: 70%
- Lines: 70%

## CI/CD

GitHub Actions를 통해 자동화된 테스트 및 배포가 설정되어 있습니다.

### Pipeline Flow

```
Push/PR → Lint → Test → Deploy (staging/production)
```

### Branches

- `staging` → Staging 환경 자동 배포
- `main` → Production 환경 자동 배포

### Required Secrets

GitHub Secrets 설정은 `docs/GITHUB_SECRETS.md` 참조:

- `CF_API_TOKEN` - Cloudflare API Token
- `CF_ACCOUNT_ID` - Cloudflare Account ID
- `CF_ACCOUNT_SUBDOMAIN` - Workers 서브도메인

## Deployment

```bash
# Deploy to staging
npm run deploy:staging

# Deploy to production
npm run deploy:production
```

## API Endpoints

### POST /api/rewrite

Accepts a content rewrite request.

**Headers:**
- `Authorization: Bearer <WORKER_SECRET>`
- `Content-Type: application/json`

**Body:**
```json
{
  "task_id": "uuid-v4",
  "callback_url": "https://example.com/wp-json/aicr/v1/webhook",
  "callback_secret": "hmac-secret",
  "payload": {
    "source_url": "https://example.com/article",
    "language": "ko",
    "ai_provider": "chatgpt"
  }
}
```

**Response (202 Accepted):**
```json
{
  "success": true,
  "data": {
    "task_id": "uuid-v4",
    "message": "Rewrite task accepted and queued",
    "estimated_time_seconds": 120
  }
}
```

### GET /api/status/:taskId

Get task processing status.

**Response:**
```json
{
  "success": true,
  "data": {
    "task_id": "uuid-v4",
    "status": "processing",
    "progress": 60,
    "current_step": "content"
  }
}
```

### GET /api/health

Health check endpoint (no authentication required).

**Response:**
```json
{
  "success": true,
  "data": {
    "status": "healthy",
    "version": "2.0.0",
    "timestamp": "2025-02-03T12:00:00Z",
    "checks": {
      "kv": true,
      "d1": true,
      "r2": true,
      "openai": true,
      "gemini": true
    }
  }
}
```

## Webhook Payload

When processing completes, the Worker sends results to WordPress:

**Success:**
```json
{
  "task_id": "uuid-v4",
  "item_id": 123,
  "status": "completed",
  "quality_score": 8.5,
  "result": {
    "title": "Blog Post Title",
    "content": "<p>HTML content...</p>",
    "excerpt": "Short excerpt...",
    "category_suggestion": "Technology",
    "tags": ["AI", "Automation"],
    "meta_title": "SEO Title",
    "meta_description": "SEO description...",
    "featured_image_url": "https://..."
  },
  "metrics": {
    "processing_time_ms": 45000,
    "token_usage": { "input": 5000, "output": 3000, "total": 8000 },
    "steps_completed": ["extraction", "outline", "content", "seo", "critique", "image", "webhook"],
    "retry_count": 0
  }
}
```

## Cron Schedule

- `0 * * * *` - Every hour: Master Workflow (process pending items)
- `30 * * * *` - Every hour at :30: Retry incomplete tasks

## Environment Variables

| Variable | Description |
|----------|-------------|
| `ENVIRONMENT` | `development`, `staging`, `production` |
| `LOG_LEVEL` | `debug`, `info`, `warn`, `error` |
| `MAX_RETRIES` | Maximum retry attempts for failed steps |
| `RETRY_DELAY_MS` | Delay between retries |

## Secrets

| Secret | Description |
|--------|-------------|
| `WORKER_SECRET` | Bearer token for WordPress → Worker auth |
| `HMAC_SECRET` | HMAC signing key for webhooks |
| `WP_API_KEY` | WordPress REST API authentication |
| `OPENAI_API_KEY` | OpenAI API key |
| `GEMINI_API_KEY` | Google Gemini API key |
| `WORDPRESS_URL` | WordPress site URL |

## License

MIT
