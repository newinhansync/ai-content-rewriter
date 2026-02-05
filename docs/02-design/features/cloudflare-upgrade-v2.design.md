# AI Content Rewriter v2.0 - Cloudflare Upgrade 설계 문서

> **Summary**: Cloudflare Worker 기반 아키텍처의 상세 기술 설계 - WordPress REST API, Webhook, Workflow, Multi-Step Prompting
>
> **Project**: AI Content Rewriter WordPress Plugin
> **Version**: 2.0.0
> **Author**: Claude
> **Date**: 2026-02-03
> **Status**: Draft
> **Planning Doc**: [cloudflare-upgrade-v2.plan.md](../01-plan/features/cloudflare-upgrade-v2.plan.md)

### Pipeline References

| Phase | Document | Status |
|-------|----------|:------:|
| Phase 1 | Schema Definition | N/A |
| Phase 2 | Coding Conventions | ✅ (CLAUDE.md) |
| Phase 3 | Mockup | ❌ (UI 설계 포함) |
| Phase 4 | API Spec | ✅ (본 문서 포함) |

---

## 1. Overview

### 1.1 Design Goals

1. **타임아웃 완전 해결**: WordPress → Worker 요청 2초 이내, 실제 처리는 Worker에서 비동기 수행
2. **완전 자동화**: Cron → Workflow → AI → Webhook 파이프라인 무인 운영
3. **품질 10배 향상**: Multi-Step Prompting + Self-Critique 품질 보증
4. **확장 가능 구조**: Workflow Step 추가만으로 기능 확장 (SNS, 뉴스레터 등)
5. **폴백 지원**: Cloudflare 장애 시 Local 모드로 자동 전환

### 1.2 Design Principles

- **Separation of Concerns**: WordPress(데이터/UI) ↔ Worker(처리/AI) 역할 분리
- **Single Source of Truth**: WordPress DB가 모든 상태의 SoT
- **Idempotency**: 동일 요청 재실행 시 동일 결과 보장
- **Graceful Degradation**: 외부 서비스 장애 시 서비스 유지
- **Observable**: 모든 처리 단계 로깅 및 모니터링 가능

---

## 2. Architecture

### 2.1 Component Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                        WordPress Plugin v2.0                         │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌─────────────┐  ┌─────────────────┐  ┌────────────────────────┐  │
│  │  Admin UI   │  │  REST API       │  │  Webhook Receiver      │  │
│  │             │  │  Controller     │  │                        │  │
│  │ ┌─────────┐ │  │ ┌─────────────┐ │  │ ┌────────────────────┐ │  │
│  │ │Settings │ │  │ │/feeds       │ │  │ │HMAC Validator      │ │  │
│  │ │Dashboard│ │  │ │/feed-items  │ │  │ │                    │ │  │
│  │ │Style    │ │  │ │/webhook     │ │  │ │Timestamp Checker   │ │  │
│  │ │Editor   │ │  │ │/media       │ │  │ │                    │ │  │
│  │ └─────────┘ │  │ │/config      │ │  │ │Post Creator        │ │  │
│  │             │  │ │/health      │ │  │ │                    │ │  │
│  │ ┌─────────┐ │  │ │/notifications│ │  │ │Image Uploader     │ │  │
│  │ │Task     │ │  │ └─────────────┘ │  │ └────────────────────┘ │  │
│  │ │Dispatch │ │  │                 │  │                        │  │
│  │ └─────────┘ │  │                 │  │                        │  │
│  └─────────────┘  └─────────────────┘  └────────────────────────┘  │
│         │                 ▲                      ▲                  │
│         │                 │                      │                  │
└─────────│─────────────────│──────────────────────│──────────────────┘
          │                 │                      │
          ▼                 │                      │
┌─────────────────────────────────────────────────────────────────────┐
│                      Cloudflare Platform                             │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌──────────────────┐    ┌────────────────────────────────────────┐ │
│  │   Cron Trigger   │───▶│           Master Workflow               │ │
│  │   (0 * * * *)    │    │                                        │ │
│  └──────────────────┘    │ ┌──────────────────────────────────┐   │ │
│                          │ │ Step 1: Lock Acquisition         │   │ │
│  ┌──────────────────┐    │ │ Step 2: RSS Collection           │   │ │
│  │   HTTP Worker    │    │ │ Step 3: AI Curation              │   │ │
│  │   (fetch)        │    │ │ Step 4: Workflow Dispatch        │   │ │
│  │ ┌──────────────┐ │    │ └──────────────────────────────────┘   │ │
│  │ │/api/rewrite  │ │    └────────────────────────────────────────┘ │
│  │ │/api/health   │ │                     │                         │
│  │ │/api/sync     │ │                     ▼ (아이템별)               │
│  │ └──────────────┘ │    ┌────────────────────────────────────────┐ │
│  └──────────────────┘    │           Item Workflow                 │ │
│                          │                                        │ │
│                          │ ┌──────────────────────────────────┐   │ │
│                          │ │ Step 1: Content Extraction       │   │ │
│                          │ │ Step 2: Outline Generation       │   │ │
│                          │ │ Step 3: Content Writing          │   │ │
│                          │ │ Step 4: SEO Optimization         │   │ │
│                          │ │ Step 5: Self-Critique            │   │ │
│                          │ │ Step 6: Image Generation         │   │ │
│                          │ │ Step 7: WordPress Publish        │   │ │
│                          │ └──────────────────────────────────┘   │ │
│                          └────────────────────────────────────────┘ │
│                                          │                          │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │                     Storage Layer                            │   │
│  │ ┌───────────┐  ┌───────────┐  ┌───────────┐                 │   │
│  │ │    KV     │  │    D1     │  │    R2     │                 │   │
│  │ │ (Config)  │  │  (Logs)   │  │ (Images)  │                 │   │
│  │ └───────────┘  └───────────┘  └───────────┘                 │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                          │                          │
│                                          ▼                          │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │                    External APIs                             │   │
│  │ ┌───────────────┐  ┌───────────────┐                        │   │
│  │ │   OpenAI      │  │   Gemini      │                        │   │
│  │ │   (GPT-4o)    │  │   (Pro)       │                        │   │
│  │ └───────────────┘  └───────────────┘                        │   │
│  └─────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
```

### 2.2 Data Flow

#### 2.2.1 수동 재작성 플로우

```
┌─────────┐   POST /api/rewrite   ┌─────────┐   AI API Call   ┌─────────┐
│WordPress│ ───────────────────▶  │ Worker  │ ──────────────▶ │ OpenAI  │
│  Admin  │   (1~2초, 즉시 반환)   │ (fetch) │                 │ Gemini  │
└─────────┘                       └─────────┘                 └─────────┘
     ▲                                 │                           │
     │                                 │  ◀───────────────────────┘
     │    POST /webhook                │  (AI 응답 수신)
     │    (HMAC 서명)                   │
     │                                 ▼
     │                           ┌─────────┐   POST /webhook   ┌─────────┐
     └───────────────────────────│ Worker  │ ─────────────────▶│WordPress│
                                 │(process)│   (결과 전달)      │ Webhook │
                                 └─────────┘                   └─────────┘
                                                                    │
                                                                    ▼
                                                              wp_insert_post()
```

#### 2.2.2 자동화 파이프라인 플로우

```
┌──────────────────────────────────────────────────────────────────────────┐
│                         Cron Trigger (매 1시간)                           │
└──────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌──────────────────────────────────────────────────────────────────────────┐
│                           Master Workflow                                 │
│                                                                          │
│  ┌─────────────────┐   ┌─────────────────┐   ┌─────────────────┐        │
│  │ Step 1: Lock    │──▶│ Step 2: RSS     │──▶│ Step 3: AI      │        │
│  │ KV.get(lock)    │   │ WP→GET /feeds   │   │ Curation        │        │
│  │ 이전 실행 중?     │   │ Fetch RSS URLs  │   │ confidence≥0.8  │        │
│  │ → 종료           │   │ Parse Items     │   │ → approve       │        │
│  └─────────────────┘   │ WP→POST /items  │   └─────────────────┘        │
│                        └─────────────────┘            │                  │
│                                                       ▼                  │
│                        ┌─────────────────────────────────────────────┐  │
│                        │ Step 4: Dispatch Item Workflows             │  │
│                        │ for each approved item:                     │  │
│                        │   spawn ItemWorkflow(item_id, delay)        │  │
│                        └─────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────────┘
                                    │
                    ┌───────────────┼───────────────┐
                    ▼               ▼               ▼
            ┌─────────────┐ ┌─────────────┐ ┌─────────────┐
            │Item Workflow│ │Item Workflow│ │Item Workflow│
            │   #1        │ │   #2        │ │   #3        │
            │ (병렬 실행)  │ │             │ │             │
            └─────────────┘ └─────────────┘ └─────────────┘
                    │
                    ▼
┌──────────────────────────────────────────────────────────────────────────┐
│                           Item Workflow                                   │
│                                                                          │
│  ┌──────────┐   ┌──────────┐   ┌──────────┐   ┌──────────┐             │
│  │ Step 1   │──▶│ Step 2   │──▶│ Step 3   │──▶│ Step 4   │             │
│  │ Extract  │   │ Outline  │   │ Content  │   │ SEO      │             │
│  │ Content  │   │ Generate │   │ Write    │   │ Optimize │             │
│  └──────────┘   └──────────┘   └──────────┘   └──────────┘             │
│                                                     │                    │
│                                                     ▼                    │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │ Step 5: Self-Critique                                             │   │
│  │ score >= 8 → continue                                             │   │
│  │ score < 8  → retry Step 3 with feedback (max 1 retry)            │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                    │                                     │
│                                    ▼                                     │
│  ┌──────────┐                 ┌──────────┐                              │
│  │ Step 6   │────────────────▶│ Step 7   │                              │
│  │ Image    │                 │ Publish  │                              │
│  │ Generate │                 │ Webhook  │                              │
│  └──────────┘                 └──────────┘                              │
│                                    │                                     │
│                                    ▼                                     │
│                            score ≥ 8 → publish                          │
│                            score < 8 → draft                            │
└──────────────────────────────────────────────────────────────────────────┘
```

### 2.3 Dependencies

| Component | Depends On | Purpose |
|-----------|-----------|---------|
| WordPress REST API | WordPress Core | 데이터 조회/저장 |
| Webhook Receiver | REST API Controller | 결과 수신 |
| Task Dispatcher | Worker HTTP API | 작업 전송 |
| Master Workflow | Cron Trigger, KV, WP REST API | 자동화 오케스트레이션 |
| Item Workflow | Master Workflow, AI APIs, R2 | 개별 아이템 처리 |
| AI Services | OpenAI/Gemini APIs | 콘텐츠 생성 |

---

## 3. Data Model

### 3.1 Entity Definition

#### WordPress Plugin Entities (PHP)

```php
<?php
namespace AIContentRewriter\Types;

/**
 * Worker 설정
 */
interface WorkerConfig {
    public string $worker_url;          // Worker 엔드포인트 URL
    public string $worker_secret;       // Bearer Token (WP→Worker)
    public string $hmac_secret;         // HMAC 서명 키 (Worker→WP)
    public string $wp_api_key;          // WP REST API 인증 키
    public string $processing_mode;     // 'local' | 'cloudflare'
    public bool $auto_publish;          // 자동 발행 여부
    public int $publish_threshold;      // 자동 발행 품질 임계값 (8)
    public int $daily_limit;            // 일일 게시 한도
}

/**
 * 피드 아이템 상태
 */
enum FeedItemStatus: string {
    case NEW = 'new';              // 새로 수집됨
    case QUEUED = 'queued';        // 큐레이션 통과, 처리 대기
    case PROCESSING = 'processing'; // 처리 중
    case COMPLETED = 'completed';   // 처리 완료
    case PUBLISHED = 'published';   // 게시 완료
    case DRAFT_SAVED = 'draft_saved'; // 임시저장
    case SKIPPED = 'skipped';       // 큐레이션에서 거부
    case FAILED = 'failed';         // 처리 실패
}

/**
 * Webhook 페이로드
 */
interface WebhookPayload {
    public string $task_id;         // 작업 고유 ID
    public string $status;          // 'completed' | 'failed'
    public float $quality_score;    // Self-Critique 점수 (1-10)
    public ?WebhookResult $result;  // 성공 시 결과
    public ?WebhookError $error;    // 실패 시 에러 정보
}

interface WebhookResult {
    public string $title;           // 게시글 제목
    public string $content;         // HTML 본문
    public string $excerpt;         // 요약
    public string $category_suggestion; // 추천 카테고리
    public array $tags;             // 태그 목록
    public string $meta_title;      // SEO 제목
    public string $meta_description; // SEO 설명
    public ?string $featured_image_url; // 대표 이미지 R2 URL
}
```

#### Cloudflare Worker Entities (TypeScript)

```typescript
// src/types/index.ts

/**
 * 수동 재작성 요청
 */
interface RewriteRequest {
  task_id: string;           // UUID
  task_type: 'rewrite' | 'batch_rewrite';
  callback_url: string;      // WordPress Webhook URL
  callback_secret: string;   // HMAC Secret
  payload: RewritePayload;
}

interface RewritePayload {
  source_url?: string;       // 원본 URL
  source_content?: string;   // 또는 직접 입력 콘텐츠
  language: string;          // 'ko' | 'en' | 'ja'
  ai_provider: 'chatgpt' | 'gemini';
  ai_model: string;          // 'gpt-4o' | 'gemini-pro'
  prompt_template?: string;  // 커스텀 프롬프트 (없으면 KV에서 로드)
}

/**
 * 스타일 가이드
 */
interface WritingStyle {
  blog_name: string;
  voice: {
    tone: string;            // "전문적이면서 친근한"
    perspective: string;     // "독자와 같은 눈높이"
    personality: string;     // "호기심 많고 실용적인"
  };
  writing_rules: {
    sentence_style: string[];
    paragraph_style: string[];
    structure_variety: string[];
    unique_elements: string[];
  };
  forbidden: string[];
}

interface ImageStyle {
  style_name: string;
  base_style: string;        // "미니멀 일러스트레이션"
  visual_identity: {
    color_palette: {
      primary: string;       // "#3B82F6"
      secondary: string;
      accent: string;
      background: string;
    };
    illustration_style: string;
    mood: string;
    composition: string;
  };
  prompt_template: {
    prefix: string;
    color_instruction: string;
    style_instruction: string;
    quality: string;
  };
  forbidden: string[];
}

/**
 * Multi-Step 프롬프트 출력
 */
interface OutlineOutput {
  angle: string;             // 독창적 관점
  hook: 'question' | 'anecdote' | 'statistic' | 'contrast';
  target_word_count: number;
  sections: Section[];
  conclusion_strategy: string;
  internal_link_opportunities: string[];
}

interface Section {
  heading: string;
  purpose: string;
  key_points: string[];
  content_type: 'explanation' | 'case_study' | 'comparison' | 'tutorial' | 'insight';
  estimated_words: number;
}

interface SelfCritiqueOutput {
  score: number;             // 1-10
  passed: boolean;           // score >= threshold
  checklist: {
    hook_quality: number;
    angle_originality: number;
    section_value: number;
    length_adequacy: number;
    writing_naturalness: number;
    examples_included: boolean;
    seo_integration: number;
    conclusion_actionable: number;
  };
  issues: string[];
  improvement_suggestions: string[];
}

/**
 * Workflow 상태
 */
interface WorkflowState {
  item_id: number;
  status: 'pending' | 'processing' | 'completed' | 'failed';
  current_step: number;
  retry_count: number;
  started_at: string;
  completed_at?: string;
  error?: string;
  result?: WebhookResult;
}
```

### 3.2 Entity Relationships

```
┌─────────────────┐     1:N     ┌─────────────────┐
│   aicr_feeds    │ ──────────▶ │ aicr_feed_items │
│                 │             │                 │
│ - id            │             │ - id            │
│ - url           │             │ - feed_id (FK)  │
│ - title         │             │ - guid          │
│ - status        │             │ - title         │
│ - last_fetched  │             │ - content       │
└─────────────────┘             │ - status        │
                                │ - quality_score │
                                └─────────────────┘
                                        │
                                        │ 1:1
                                        ▼
                                ┌─────────────────┐
                                │   wp_posts      │
                                │                 │
                                │ - ID            │
                                │ - post_title    │
                                │ - post_content  │
                                │ - post_status   │
                                └─────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                    Cloudflare Storage                        │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  KV Namespace (AICR_CONFIG)                                 │
│  ├── workflow:lock → { timestamp, instance_id }             │
│  ├── prompt:outline → Step A 프롬프트 템플릿                  │
│  ├── prompt:content → Step B 프롬프트 템플릿                  │
│  ├── prompt:seo → Step C 프롬프트 템플릿                      │
│  ├── prompt:critique → Step D 프롬프트 템플릿                 │
│  ├── prompt:curation → 큐레이션 프롬프트                      │
│  ├── style:writing → 글쓰기 스타일 JSON                      │
│  ├── style:image → 삽화 스타일 JSON                          │
│  ├── example:{category} → Few-Shot 예시                     │
│  ├── blog:profile → 블로그 프로필                             │
│  └── config:version → 설정 버전 (동기화 확인)                  │
│                                                              │
│  D1 Database (AICR_DB)                                       │
│  └── execution_logs                                          │
│      - id (PRIMARY KEY)                                      │
│      - workflow_type ('master' | 'item')                    │
│      - item_id                                               │
│      - step_name                                             │
│      - status                                                │
│      - duration_ms                                           │
│      - error_message                                         │
│      - created_at                                            │
│                                                              │
│  R2 Bucket (AICR_IMAGES)                                     │
│  └── {year}/{month}/{task_id}.png                           │
│      - Lifecycle: 24시간 후 자동 삭제                          │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### 3.3 Database Schema

#### D1 Schema (Cloudflare)

```sql
-- execution_logs: Workflow 실행 로그
CREATE TABLE execution_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    workflow_type TEXT NOT NULL,           -- 'master' | 'item'
    workflow_instance_id TEXT NOT NULL,    -- Workflow 인스턴스 ID
    item_id INTEGER,                       -- WordPress feed_item_id (nullable for master)
    step_name TEXT NOT NULL,               -- 'lock', 'rss', 'curation', 'outline', etc.
    status TEXT NOT NULL,                  -- 'started' | 'completed' | 'failed'
    duration_ms INTEGER,                   -- 실행 시간 (ms)
    input_summary TEXT,                    -- 입력 요약 (JSON)
    output_summary TEXT,                   -- 출력 요약 (JSON)
    error_message TEXT,                    -- 에러 메시지
    created_at TEXT DEFAULT (datetime('now'))
);

CREATE INDEX idx_logs_workflow ON execution_logs(workflow_instance_id);
CREATE INDEX idx_logs_item ON execution_logs(item_id);
CREATE INDEX idx_logs_created ON execution_logs(created_at);

-- daily_stats: 일일 통계
CREATE TABLE daily_stats (
    date TEXT PRIMARY KEY,                 -- 'YYYY-MM-DD'
    items_collected INTEGER DEFAULT 0,     -- RSS 수집 건수
    items_approved INTEGER DEFAULT 0,      -- 큐레이션 승인 건수
    items_published INTEGER DEFAULT 0,     -- 게시 완료 건수
    items_drafted INTEGER DEFAULT 0,       -- 임시저장 건수
    items_failed INTEGER DEFAULT 0,        -- 실패 건수
    avg_quality_score REAL,                -- 평균 품질 점수
    total_ai_cost_usd REAL DEFAULT 0,      -- AI 비용 (추정)
    updated_at TEXT DEFAULT (datetime('now'))
);
```

---

## 4. API Specification

### 4.1 WordPress REST API Endpoints

| Method | Path | Description | Auth | Rate Limit |
|:------:|------|-------------|:----:|:----------:|
| GET | `/wp-json/aicr/v1/feeds` | 활성 피드 목록 | API Key | 60/min |
| GET | `/wp-json/aicr/v1/feed-items/pending` | 대기 중 아이템 | API Key | 60/min |
| PATCH | `/wp-json/aicr/v1/feed-items/{id}/status` | 아이템 상태 변경 | API Key | 120/min |
| POST | `/wp-json/aicr/v1/webhook` | 처리 결과 수신 | HMAC | 60/min |
| POST | `/wp-json/aicr/v1/media` | 이미지 업로드 | API Key | 30/min |
| GET | `/wp-json/aicr/v1/config` | AI 설정 조회 | API Key | 30/min |
| GET | `/wp-json/aicr/v1/health` | 연결 확인 | API Key | 120/min |
| POST | `/wp-json/aicr/v1/notifications` | 알림 전송 | HMAC | 30/min |

### 4.2 Detailed Specification

#### `GET /wp-json/aicr/v1/feeds`

활성화된 RSS 피드 목록 조회

**Request Headers:**
```http
Authorization: Bearer {AICR_WP_API_KEY}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "url": "https://example.com/feed",
      "title": "Example Blog",
      "status": "active",
      "last_fetched": "2026-02-03T06:00:00Z",
      "fetch_interval": 3600,
      "auto_rewrite": true,
      "category_id": 5
    }
  ],
  "meta": {
    "total": 15,
    "active": 12
  }
}
```

#### `GET /wp-json/aicr/v1/feed-items/pending`

처리 대기 중인 피드 아이템 조회

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `status` | string | `queued` | 필터링 상태 |
| `limit` | int | 10 | 최대 반환 개수 |
| `offset` | int | 0 | 페이지네이션 |

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "feed_id": 1,
      "guid": "https://example.com/post-123",
      "title": "Original Post Title",
      "content": "Original content...",
      "link": "https://example.com/post-123",
      "pub_date": "2026-02-03T05:30:00Z",
      "status": "queued",
      "curation_confidence": 0.85,
      "curation_reason": "최신 AI 트렌드로 블로그 주제에 적합"
    }
  ],
  "meta": {
    "total_pending": 5,
    "daily_published": 2,
    "daily_limit": 5
  }
}
```

#### `POST /wp-json/aicr/v1/webhook`

Worker로부터 처리 결과 수신

**Request Headers:**
```http
Content-Type: application/json
X-AICR-Signature: {HMAC-SHA256 signature}
X-AICR-Timestamp: {Unix timestamp}
```

**Request Body:**
```json
{
  "task_id": "550e8400-e29b-41d4-a716-446655440000",
  "item_id": 123,
  "status": "completed",
  "quality_score": 8.5,
  "result": {
    "title": "AI 시대의 개발자 역량: 2026년 필수 스킬 가이드",
    "content": "<h2>도입</h2><p>AI가 코드를 작성하는 시대...</p>",
    "excerpt": "2026년 AI 시대에 개발자가 갖춰야 할 핵심 역량을 분석합니다.",
    "category_suggestion": "기술",
    "tags": ["AI", "개발자", "커리어", "GPT"],
    "meta_title": "AI 시대 개발자 역량 | 2026 필수 스킬",
    "meta_description": "AI가 코드를 작성하는 시대, 개발자가 갖춰야 할 핵심 역량 5가지를 알아봅니다.",
    "featured_image_url": "https://r2.example.com/2026/02/550e8400.png"
  },
  "metrics": {
    "processing_time_ms": 45000,
    "ai_calls": 5,
    "retry_count": 0,
    "token_usage": {
      "input": 12500,
      "output": 8000
    }
  }
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "post_id": 456,
    "post_status": "publish",
    "permalink": "https://myblog.com/ai-developer-skills-2026/"
  }
}
```

**Error Responses:**
- `400 Bad Request`: 잘못된 페이로드
- `401 Unauthorized`: HMAC 검증 실패
- `403 Forbidden`: Timestamp 만료 (5분 초과)
- `422 Unprocessable Entity`: 이미지 다운로드 실패

#### `POST /wp-json/aicr/v1/notifications`

Worker에서 WordPress로 알림 전송

**Request Body:**
```json
{
  "level": "warning",
  "code": "QUALITY_BELOW_THRESHOLD",
  "message": "아이템 #234의 품질 점수가 임계값 미달입니다",
  "details": {
    "item_id": 234,
    "quality_score": 7.5,
    "threshold": 8,
    "action_taken": "draft_saved"
  }
}
```

**Notification Levels:**
| Level | WordPress Action |
|-------|------------------|
| `critical` | admin_notices + wp_mail |
| `warning` | admin_notices |
| `info` | Dashboard log only |

### 4.3 Cloudflare Worker API Endpoints

| Method | Path | Description | Auth |
|:------:|------|-------------|:----:|
| POST | `/api/rewrite` | 수동 재작성 요청 | Bearer |
| GET | `/api/health` | 헬스체크 | Bearer |
| POST | `/api/sync-config` | 설정 동기화 | Bearer |
| GET | `/api/status/{task_id}` | 작업 상태 조회 | Bearer |

#### `POST /api/rewrite`

수동 재작성 작업 요청

**Request:**
```http
POST /api/rewrite
Authorization: Bearer {AICR_WORKER_SECRET}
Content-Type: application/json

{
  "task_id": "550e8400-e29b-41d4-a716-446655440000",
  "task_type": "rewrite",
  "callback_url": "https://myblog.com/wp-json/aicr/v1/webhook",
  "callback_secret": "{HMAC_SECRET}",
  "payload": {
    "source_url": "https://example.com/original-article",
    "language": "ko",
    "ai_provider": "chatgpt",
    "ai_model": "gpt-4o"
  }
}
```

**Response (202 Accepted):**
```json
{
  "accepted": true,
  "task_id": "550e8400-e29b-41d4-a716-446655440000",
  "estimated_time_seconds": 180
}
```

### 4.4 Security - HMAC Signature

#### Signature Generation (Worker → WordPress)

```typescript
// Worker: webhook/sender.ts
import { createHmac } from 'crypto';

function generateSignature(payload: string, secret: string): string {
  return createHmac('sha256', secret)
    .update(payload)
    .digest('hex');
}

async function sendWebhook(url: string, data: object, secret: string) {
  const timestamp = Math.floor(Date.now() / 1000);
  const body = JSON.stringify(data);
  const signature = generateSignature(`${timestamp}.${body}`, secret);

  await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-AICR-Signature': signature,
      'X-AICR-Timestamp': timestamp.toString(),
    },
    body,
  });
}
```

#### Signature Verification (WordPress)

```php
<?php
// src/API/WebhookReceiver.php

class WebhookReceiver {
    private const TIMESTAMP_TOLERANCE = 300; // 5분

    public function verify_signature(WP_REST_Request $request): bool {
        $signature = $request->get_header('X-AICR-Signature');
        $timestamp = $request->get_header('X-AICR-Timestamp');
        $body = $request->get_body();

        // 1. Timestamp 검증
        if (abs(time() - (int)$timestamp) > self::TIMESTAMP_TOLERANCE) {
            return false;
        }

        // 2. HMAC 검증
        $secret = get_option('aicr_hmac_secret');
        $expected = hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

        return hash_equals($expected, $signature);
    }
}
```

---

## 5. UI/UX Design

### 5.1 Worker 설정 페이지

```
┌─────────────────────────────────────────────────────────────────────┐
│  AI Rewriter > Settings > Cloudflare Worker                         │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌─ 처리 모드 ───────────────────────────────────────────────────┐  │
│  │                                                                │  │
│  │  ○ Local (서버에서 직접 처리)                                   │  │
│  │     - PHP 환경에서 AI API 직접 호출                             │  │
│  │     - 장시간 처리 시 타임아웃 위험                               │  │
│  │                                                                │  │
│  │  ● Cloudflare Worker (외부 위임) [권장]                         │  │
│  │     - 타임아웃 없이 안정적인 처리                                │  │
│  │     - 완전 자동화 파이프라인 지원                                │  │
│  │                                                                │  │
│  └────────────────────────────────────────────────────────────────┘  │
│                                                                      │
│  ┌─ Worker 연결 설정 ─────────────────────────────────────────────┐  │
│  │                                                                │  │
│  │  Worker URL:                                                   │  │
│  │  ┌──────────────────────────────────────────────────────────┐ │  │
│  │  │ https://aicr-worker.yourname.workers.dev                 │ │  │
│  │  └──────────────────────────────────────────────────────────┘ │  │
│  │                                                                │  │
│  │  API Secret:                                                   │  │
│  │  ┌──────────────────────────────────────────────────────────┐ │  │
│  │  │ ••••••••••••••••••••••••••••••••                         │ │  │
│  │  └──────────────────────────────────────────────────────────┘ │  │
│  │  ⓘ Worker 배포 시 설정한 WORKER_SECRET 값을 입력하세요         │  │
│  │                                                                │  │
│  │  Webhook Secret (HMAC):                                        │  │
│  │  ┌──────────────────────────────────────────────────────────┐ │  │
│  │  │ ••••••••••••••••••••••••••••••••  [🔄 재생성]             │ │  │
│  │  └──────────────────────────────────────────────────────────┘ │  │
│  │  ⓘ Worker에도 동일한 값을 설정해야 합니다                       │  │
│  │                                                                │  │
│  │  [ 연결 테스트 ]                                                │  │
│  │                                                                │  │
│  │  ✅ 연결 성공: Worker v1.0.0, 응답 시간 120ms                   │  │
│  │                                                                │  │
│  └────────────────────────────────────────────────────────────────┘  │
│                                                                      │
│  [ 변경사항 저장 ]                                                    │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

### 5.2 자동화 대시보드

```
┌─────────────────────────────────────────────────────────────────────┐
│  AI Rewriter > Automation Dashboard                                  │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌─ 시스템 상태 ─────────────────────────────────────────────────┐  │
│  │                                                                │  │
│  │  ✅ Worker 상태: 정상                                          │  │
│  │     마지막 확인: 2분 전 | 응답 시간: 95ms                       │  │
│  │                                                                │  │
│  │  ✅ 마지막 자동화 실행: 06:02 (38분 전)                         │  │
│  │     다음 실행 예정: 07:00                                       │  │
│  │                                                                │  │
│  └────────────────────────────────────────────────────────────────┘  │
│                                                                      │
│  ┌─ 오늘 통계 ───────────────────────────────────────────────────┐  │
│  │                                                                │  │
│  │   RSS 수집        큐레이션       재작성 완료      게시 완료      │  │
│  │  ┌────────┐    ┌────────┐    ┌────────┐    ┌────────┐        │  │
│  │  │   12   │ →  │  5/7   │ →  │   5    │ →  │  4/1   │        │  │
│  │  │  건    │    │승인/거부│    │  건    │    │발행/임시│        │  │
│  │  └────────┘    └────────┘    └────────┘    └────────┘        │  │
│  │                                                                │  │
│  │  평균 품질: 8.6/10 | AI 비용: $0.72 | 처리 시간: 평균 3분 24초  │  │
│  │                                                                │  │
│  └────────────────────────────────────────────────────────────────┘  │
│                                                                      │
│  ┌─ 알림 (1건) ──────────────────────────────────────────────────┐  │
│  │                                                                │  │
│  │  ⚠️ 아이템 #234: 품질 점수 7.5 → 임시저장됨                     │  │
│  │     "React 19 신기능" | 2026-02-03 06:15                       │  │
│  │     [ 검토하기 ] [ 무시 ]                                       │  │
│  │                                                                │  │
│  └────────────────────────────────────────────────────────────────┘  │
│                                                                      │
│  ┌─ 최근 처리 이력 ──────────────────────────────────────────────┐  │
│  │                                                                │  │
│  │  │ ID  │ 제목              │ 품질   │ 상태 │ 처리시간│ 시각   │  │
│  │  ├─────┼──────────────────┼───────┼──────┼────────┼───────┤  │
│  │  │ 238 │ AI 트렌드 2026    │ 9.0   │ 발행 │ 3:12   │ 06:18 │  │
│  │  │ 237 │ 클라우드 보안      │ 8.5   │ 발행 │ 2:45   │ 06:15 │  │
│  │  │ 236 │ TypeScript 5.4   │ 8.2   │ 발행 │ 3:33   │ 06:12 │  │
│  │  │ 235 │ DevOps 자동화     │ 8.8   │ 발행 │ 2:58   │ 06:09 │  │
│  │  │ 234 │ React 19 신기능   │ 7.5   │ 임시 │ 4:02   │ 06:06 │  │
│  │                                                                │  │
│  │  [ 전체 이력 보기 ]                                             │  │
│  │                                                                │  │
│  └────────────────────────────────────────────────────────────────┘  │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

### 5.3 스타일 설정 페이지

```
┌─────────────────────────────────────────────────────────────────────┐
│  AI Rewriter > Style Settings                                        │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌─ 글쓰기 스타일 ───────────────────────────────────────────────┐  │
│  │                                                                │  │
│  │  블로그 설명:                                                   │  │
│  │  ┌──────────────────────────────────────────────────────────┐ │  │
│  │  │ 기술/AI 전문 블로그. 개발자와 기술 관심 직장인을 위한       │ │  │
│  │  │ 실용적 인사이트를 제공합니다.                               │ │  │
│  │  └──────────────────────────────────────────────────────────┘ │  │
│  │                                                                │  │
│  │  톤/보이스:                                                     │  │
│  │  ┌──────────────────────────────────────────────────────────┐ │  │
│  │  │ 전문적이면서 친근한, 독자와 같은 눈높이에서 대화하듯       │ │  │
│  │  └──────────────────────────────────────────────────────────┘ │  │
│  │                                                                │  │
│  │  글쓰기 규칙:                         금지 사항:                │  │
│  │  ┌────────────────────────────┐     ┌────────────────────────┐│  │
│  │  │ • 짧은 문장과 긴 문장 혼합  │     │ • "오늘날 빠르게..." 도입│ │  │
│  │  │ • 동일 어미 3회 연속 금지   │     │ • "~중요합니다" 반복     │ │  │
│  │  │ • 첫 문장은 호기심 자극     │     │ • 과도한 이모지          │ │  │
│  │  │ • 수동태보다 능동태         │     │ • 원본 문장 그대로       │ │  │
│  │  └────────────────────────────┘     └────────────────────────┘│  │
│  │                                                                │  │
│  │  ┌─ Few-Shot 예시 글 ─────────────────────────────────────┐   │  │
│  │  │ #1: "AI 시대의 개발자 역량" (2026-01-15) [편집] [삭제]  │   │  │
│  │  │ #2: "클라우드 비용 최적화 가이드" (2026-01-20)          │   │  │
│  │  │ #3: "TypeScript 마스터하기" (2026-01-25)               │   │  │
│  │  │ [ + 예시 글 추가 ]                                      │   │  │
│  │  └────────────────────────────────────────────────────────┘   │  │
│  │                                                                │  │
│  └────────────────────────────────────────────────────────────────┘  │
│                                                                      │
│  ┌─ 삽화 스타일 ─────────────────────────────────────────────────┐  │
│  │                                                                │  │
│  │  기본 스타일: [ 미니멀 일러스트 ▼ ]                             │  │
│  │                                                                │  │
│  │  컬러 팔레트:                                                   │  │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐         │  │
│  │  │ Primary  │ │Secondary │ │  Accent  │ │Background│         │  │
│  │  │ #3B82F6  │ │ #10B981  │ │ #F59E0B  │ │ #F8FAFC  │         │  │
│  │  │ [🎨]     │ │ [🎨]     │ │ [🎨]     │ │ [🎨]     │         │  │
│  │  └──────────┘ └──────────┘ └──────────┘ └──────────┘         │  │
│  │                                                                │  │
│  │  [ 미리보기 생성 ] → 샘플 이미지 3장                            │  │
│  │                                                                │  │
│  │  ┌────────────────────────────────────────────────────────┐   │  │
│  │  │  [샘플1]     [샘플2]     [샘플3]                        │   │  │
│  │  │  (미리보기   (미리보기   (미리보기                       │   │  │
│  │  │   이미지)    이미지)    이미지)                         │   │  │
│  │  └────────────────────────────────────────────────────────┘   │  │
│  │                                                                │  │
│  └────────────────────────────────────────────────────────────────┘  │
│                                                                      │
│  [ 저장 + Worker 동기화 ]   [ 기본값 복원 ]                          │
│  ⓘ 설정 변경 후 Worker 동기화에 최대 1분 소요됩니다.                  │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

### 5.4 수동 재작성 진행 상태 UI

```
┌─────────────────────────────────────────────────────────────────────┐
│  AI Rewriter > New Content                                           │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  URL: https://example.com/original-article                           │
│                                                                      │
│  ┌─ 처리 진행 상황 ──────────────────────────────────────────────┐  │
│  │                                                                │  │
│  │  Cloudflare Worker에서 처리 중...                              │  │
│  │                                                                │  │
│  │  ✅ 원본 콘텐츠 추출 완료                                       │  │
│  │  ✅ 아웃라인 생성 완료                                          │  │
│  │  🔄 본문 작성 중... (45초 경과)                                 │  │
│  │  ⏳ SEO 최적화 대기                                            │  │
│  │  ⏳ 품질 검증 대기                                              │  │
│  │  ⏳ 이미지 생성 대기                                            │  │
│  │                                                                │  │
│  │  ████████████████░░░░░░░░░░░░░░░░░░░░  45%                    │  │
│  │                                                                │  │
│  │  예상 남은 시간: 약 2분                                         │  │
│  │                                                                │  │
│  └────────────────────────────────────────────────────────────────┘  │
│                                                                      │
│  [ 취소 ]                                                            │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 6. Error Handling

### 6.1 Error Code Definition

| Code | HTTP | Message | Cause | Recovery |
|------|:----:|---------|-------|----------|
| `AICR_001` | 400 | Invalid request payload | 필수 필드 누락/형식 오류 | 요청 수정 후 재시도 |
| `AICR_002` | 401 | Authentication failed | API Key/Bearer Token 오류 | 설정 확인 |
| `AICR_003` | 403 | HMAC signature invalid | 서명 불일치 | Secret 동기화 확인 |
| `AICR_004` | 403 | Timestamp expired | 5분 초과 | 시스템 시간 동기화 |
| `AICR_005` | 404 | Feed item not found | 존재하지 않는 아이템 | ID 확인 |
| `AICR_006` | 409 | Item already processing | 중복 처리 요청 | 상태 확인 후 대기 |
| `AICR_007` | 422 | Image download failed | R2 URL 접근 불가 | R2 상태 확인 |
| `AICR_008` | 429 | Rate limit exceeded | 요청 빈도 초과 | 잠시 후 재시도 |
| `AICR_009` | 500 | AI API error | OpenAI/Gemini 오류 | 재시도 또는 폴백 |
| `AICR_010` | 500 | Workflow execution failed | Worker 내부 오류 | D1 로그 확인 |
| `AICR_011` | 503 | Worker unavailable | Worker 다운 | Local 모드 폴백 |

### 6.2 Error Response Format

```json
{
  "success": false,
  "error": {
    "code": "AICR_009",
    "message": "AI API error: Rate limit exceeded",
    "details": {
      "provider": "openai",
      "model": "gpt-4o",
      "retry_after_seconds": 60
    },
    "timestamp": "2026-02-03T06:15:00Z"
  }
}
```

### 6.3 Retry Strategy

```typescript
// Worker: utils/retry.ts

interface RetryConfig {
  maxAttempts: number;
  baseDelayMs: number;
  maxDelayMs: number;
  backoffMultiplier: number;
}

const AI_API_RETRY: RetryConfig = {
  maxAttempts: 3,
  baseDelayMs: 1000,
  maxDelayMs: 30000,
  backoffMultiplier: 2,
};

const WEBHOOK_RETRY: RetryConfig = {
  maxAttempts: 3,
  baseDelayMs: 5000,
  maxDelayMs: 60000,
  backoffMultiplier: 2,
};

async function withRetry<T>(
  fn: () => Promise<T>,
  config: RetryConfig,
  context: string
): Promise<T> {
  let lastError: Error;

  for (let attempt = 1; attempt <= config.maxAttempts; attempt++) {
    try {
      return await fn();
    } catch (error) {
      lastError = error;

      if (attempt === config.maxAttempts) {
        throw error;
      }

      const delay = Math.min(
        config.baseDelayMs * Math.pow(config.backoffMultiplier, attempt - 1),
        config.maxDelayMs
      );

      console.log(`[${context}] Attempt ${attempt} failed, retrying in ${delay}ms`);
      await sleep(delay);
    }
  }

  throw lastError;
}
```

---

## 7. Security Considerations

### 7.1 Security Checklist

- [x] **Input Validation**: 모든 API 입력 검증 (WordPress sanitize_* 함수)
- [x] **Authentication**: Bearer Token (WP→Worker), API Key (Worker→WP)
- [x] **Authorization**: WordPress capability 체크 (`manage_options`)
- [x] **Data Integrity**: HMAC-SHA256 서명 (Webhook)
- [x] **Replay Prevention**: Timestamp 검증 (5분 이내)
- [x] **Rate Limiting**: 엔드포인트별 제한
- [x] **HTTPS Enforcement**: Cloudflare Workers 기본 HTTPS
- [x] **Secret Management**: wp_options 암호화, wrangler secret

### 7.2 Secret Rotation

```php
<?php
// WordPress: Secret 자동 재생성 (90일 주기)
add_action('aicr_rotate_secrets', function() {
    $last_rotation = get_option('aicr_secret_rotation_date');

    if (strtotime($last_rotation) < strtotime('-90 days')) {
        $new_hmac_secret = wp_generate_password(32, true, true);
        update_option('aicr_hmac_secret', $new_hmac_secret);
        update_option('aicr_secret_rotation_date', date('Y-m-d'));

        // 관리자에게 Worker 업데이트 알림
        set_transient('aicr_secret_rotated_notice', true, 3600);
    }
});
```

---

## 8. Test Plan

### 8.1 Test Scope

| Type | Target | Tool | Coverage |
|------|--------|------|:--------:|
| Unit Test | PHP Classes | PHPUnit | 70%+ |
| Unit Test | TypeScript Services | Vitest | 80%+ |
| Integration Test | REST API | wp-browser | 100% |
| Integration Test | Worker API | Miniflare | 100% |
| E2E Test | Full Pipeline | Playwright | Critical paths |

### 8.2 Test Cases (Key)

#### WordPress REST API

- [x] `GET /feeds` - 활성 피드 목록 반환
- [x] `GET /feed-items/pending` - 대기 아이템 필터링
- [x] `POST /webhook` - HMAC 검증 성공/실패
- [x] `POST /webhook` - Timestamp 만료 처리
- [x] `POST /webhook` - 게시글 생성 + 이미지 업로드
- [x] `POST /notifications` - 알림 레벨별 처리

#### Cloudflare Worker

- [x] `POST /api/rewrite` - 작업 수락 및 비동기 처리
- [x] `GET /api/health` - 헬스체크 응답
- [x] Master Workflow - Lock 획득/해제
- [x] Master Workflow - RSS 수집 및 중복 제거
- [x] Master Workflow - 큐레이션 결과 저장
- [x] Item Workflow - Multi-Step 전체 흐름
- [x] Item Workflow - Self-Critique 재시도
- [x] Item Workflow - 이미지 생성 및 R2 저장
- [x] Webhook 전송 - 성공/실패 재시도

#### E2E Scenarios

- [x] 수동 재작성 → Worker 처리 → Webhook → 게시
- [x] Cron → Master → Item(×3) → 자동 게시 (품질 8+)
- [x] Cron → Master → Item → 임시저장 (품질 < 8)
- [x] Worker 장애 시 Local 모드 폴백

---

## 9. Clean Architecture

### 9.1 Layer Structure

#### WordPress Plugin

| Layer | Responsibility | Location |
|-------|---------------|----------|
| **Presentation** | Admin UI, AJAX handlers | `src/Admin/` |
| **Application** | REST API Controller, Task Dispatcher | `src/API/`, `src/Worker/` |
| **Domain** | Entities, Business Rules | `src/Core/`, `src/Content/` |
| **Infrastructure** | DB, External APIs, WordPress Core | `src/Database/`, `src/AI/` |

#### Cloudflare Worker

| Layer | Responsibility | Location |
|-------|---------------|----------|
| **Handlers** | HTTP Request handling | `src/handlers/` |
| **Workflows** | Business orchestration | `src/workflows/` |
| **Services** | External API clients | `src/services/` |
| **Config** | Settings, prompts | `src/config/`, `src/prompts/` |
| **Utils** | Crypto, logging | `src/utils/` |

### 9.2 Dependency Rules

```
WordPress Plugin:
┌───────────────────────────────────────────────────────────┐
│                                                           │
│   Admin UI ──→ API Controller ──→ Core ←── Database      │
│       │              │              ↑          │         │
│       │              ▼              │          │         │
│       └──────→ Task Dispatcher ─────┘          │         │
│                      │                         │         │
│                      ▼                         ▼         │
│              (WordPress Core, AI Adapters)               │
│                                                           │
└───────────────────────────────────────────────────────────┘

Cloudflare Worker:
┌───────────────────────────────────────────────────────────┐
│                                                           │
│   Handlers ──→ Workflows ──→ Services ←── Config         │
│       │            │              ↑                      │
│       │            ▼              │                      │
│       └────→ Prompts ─────────────┘                      │
│                      │                                   │
│                      ▼                                   │
│              (KV, D1, R2, External APIs)                 │
│                                                           │
└───────────────────────────────────────────────────────────┘
```

### 9.3 This Feature's Layer Assignment

| Component | Layer | Location |
|-----------|-------|----------|
| `SettingsPage` | Presentation | `src/Admin/SettingsPage.php` |
| `DashboardPage` | Presentation | `src/Admin/DashboardPage.php` |
| `StyleEditorPage` | Presentation | `src/Admin/StyleEditorPage.php` |
| `RestController` | Application | `src/API/RestController.php` |
| `WebhookReceiver` | Application | `src/API/WebhookReceiver.php` |
| `TaskDispatcher` | Application | `src/Worker/TaskDispatcher.php` |
| `ProcessingMode` | Domain | `src/Core/ProcessingMode.php` |
| `WorkerConfig` | Domain | `src/Worker/WorkerConfig.php` |
| `AIAdapter` | Infrastructure | `src/AI/` (기존) |
| `MasterWorkflow` | Workflows | `worker/src/workflows/master.ts` |
| `ItemWorkflow` | Workflows | `worker/src/workflows/item.ts` |
| `OpenAIService` | Services | `worker/src/services/ai/openai.ts` |
| `WordPressClient` | Services | `worker/src/services/wordpress/client.ts` |

---

## 10. Coding Convention Reference

### 10.1 WordPress Plugin (PHP)

| Target | Rule | Example |
|--------|------|---------|
| Namespace | PascalCase | `AIContentRewriter\API` |
| Class | PascalCase | `RestController` |
| Method | snake_case | `register_routes()` |
| Property | snake_case | `private $worker_url` |
| Constant | UPPER_SNAKE | `const API_VERSION = '1'` |
| Hook | lowercase_underscore | `add_action('aicr_webhook_received')` |

### 10.2 Cloudflare Worker (TypeScript)

| Target | Rule | Example |
|--------|------|---------|
| File | camelCase.ts | `openai.ts`, `master.ts` |
| Interface | PascalCase | `RewriteRequest` |
| Function | camelCase | `generateOutline()` |
| Constant | UPPER_SNAKE | `const MAX_RETRIES = 3` |
| Type | PascalCase | `type StepResult` |

### 10.3 Import Order (TypeScript)

```typescript
// 1. Cloudflare bindings
import { Env, KVNamespace, D1Database, R2Bucket } from '@cloudflare/workers-types';

// 2. Workflow imports
import { WorkflowEntrypoint, WorkflowStep } from 'cloudflare:workers';

// 3. Internal services
import { openaiService } from '../services/ai/openai';
import { wordpressClient } from '../services/wordpress/client';

// 4. Prompts and config
import { outlinePrompt } from '../prompts/outline';
import { loadConfig } from '../config/settings';

// 5. Types
import type { RewriteRequest, WebhookResult } from '../types';

// 6. Utils
import { generateSignature } from '../utils/crypto';
```

---

## 11. Implementation Guide

### 11.1 File Structure

#### WordPress Plugin (신규/수정 파일)

```
wp-content/plugins/ai-content-rewriter/
├── src/
│   ├── API/                          # 신규
│   │   ├── RestController.php        # REST API 엔드포인트 등록
│   │   └── WebhookReceiver.php       # Webhook 수신 + HMAC 검증
│   │
│   ├── Worker/                       # 신규
│   │   ├── TaskDispatcher.php        # Worker에 작업 전송
│   │   └── WorkerConfig.php          # Worker 설정 관리
│   │
│   ├── Admin/                        # 수정
│   │   ├── SettingsPage.php          # Worker 설정 섹션 추가
│   │   ├── DashboardPage.php         # 신규: 자동화 대시보드
│   │   └── StyleEditorPage.php       # 신규: 스타일 설정 UI
│   │
│   └── Core/                         # 수정
│       ├── Plugin.php                # REST API 등록, ProcessingMode 초기화
│       └── ProcessingMode.php        # 신규: Local/Cloudflare 분기
│
├── assets/
│   ├── js/
│   │   ├── admin-settings.js         # 수정: 연결 테스트
│   │   ├── admin-dashboard.js        # 신규: 대시보드 AJAX
│   │   └── admin-style-editor.js     # 신규: 스타일 편집기
│   └── css/
│       └── admin-dashboard.css       # 신규
│
└── languages/                        # 번역 업데이트
```

#### Cloudflare Worker (신규)

```
aicr-worker/
├── wrangler.toml
├── package.json
├── tsconfig.json
├── vitest.config.ts
│
├── src/
│   ├── index.ts                      # Entry point
│   │
│   ├── workflows/
│   │   ├── master.ts                 # Master Workflow
│   │   └── item.ts                   # Item Workflow
│   │
│   ├── handlers/
│   │   ├── rewrite.ts                # POST /api/rewrite
│   │   ├── health.ts                 # GET /api/health
│   │   └── sync.ts                   # POST /api/sync-config
│   │
│   ├── services/
│   │   ├── ai/
│   │   │   ├── openai.ts
│   │   │   ├── gemini.ts
│   │   │   └── image.ts              # DALL-E 이미지 생성
│   │   ├── rss/
│   │   │   └── parser.ts
│   │   ├── wordpress/
│   │   │   └── client.ts
│   │   └── webhook/
│   │       └── sender.ts
│   │
│   ├── prompts/
│   │   ├── outline.ts
│   │   ├── content.ts
│   │   ├── seo.ts
│   │   ├── critique.ts
│   │   └── curation.ts
│   │
│   ├── config/
│   │   └── settings.ts
│   │
│   ├── types/
│   │   └── index.ts
│   │
│   └── utils/
│       ├── crypto.ts
│       ├── logger.ts
│       └── retry.ts
│
└── test/
    ├── workflows/
    ├── handlers/
    └── services/
```

### 11.2 Implementation Order

#### Sprint 1: 인프라 기반

```
1. [ ] WordPress: RestController.php 구현
   - register_rest_route() 7개 엔드포인트
   - permission_callback, sanitize_callback

2. [ ] WordPress: WebhookReceiver.php 구현
   - HMAC 검증 로직
   - wp_insert_post() + 이미지 업로드

3. [ ] WordPress: ProcessingMode.php 구현
   - is_cloudflare_mode()
   - switch_mode()

4. [ ] Worker: 프로젝트 초기화
   - wrangler init
   - KV/D1/R2 바인딩

5. [ ] Worker: handlers/rewrite.ts 구현
   - 요청 검증
   - 단일 AI 호출
   - Webhook 전송

6. [ ] Worker: services/wordpress/client.ts 구현
   - REST API 호출
   - 인증 헤더

7. [ ] 연동 테스트
   - WordPress → Worker → AI → Webhook → 게시
```

#### Sprint 2: 품질 혁신

```
1. [ ] 프롬프트 설계 (병렬)
   - outline.ts, content.ts, seo.ts, critique.ts

2. [ ] WordPress: StyleEditorPage.php 구현
   - 글쓰기 스타일 폼
   - 삽화 스타일 폼
   - Few-Shot 관리

3. [ ] Worker: KV 동기화
   - POST /api/sync-config
   - 프롬프트/스타일 저장

4. [ ] 품질 비교 테스트
   - 단일 vs Multi-Step 5개 샘플
```

#### Sprint 3: 완전 자동화

```
1. [ ] Worker: workflows/master.ts 구현
   - Step 1: Lock
   - Step 2: RSS 수집
   - Step 3: 큐레이션
   - Step 4: 디스패치

2. [ ] Worker: workflows/item.ts 구현
   - Step 1-7 전체 파이프라인
   - 재시도 로직

3. [ ] Cron Trigger 설정
   - wrangler.toml [triggers]

4. [ ] WordPress: DashboardPage.php 구현
   - 상태 표시
   - 통계
   - 이력

5. [ ] 48시간 운영 테스트
```

#### Sprint 4: 안정화

```
1. [ ] 수동 재작성 Multi-Step 적용
   - 진행 상태 폴링 UI
   - task_id 기반 상태 조회

2. [ ] 에러 핸들링 보강
   - exponential backoff
   - 알림 체계

3. [ ] 문서화
   - 배포 가이드
   - 사용자 매뉴얼
```

---

## Version History

| Version | Date | Changes | Author |
|---------|------|---------|--------|
| 0.1 | 2026-02-03 | 초안 작성 | Claude |

---

## Appendix A: wrangler.toml 전체 예시

```toml
name = "aicr-worker"
main = "src/index.ts"
compatibility_date = "2026-01-01"
compatibility_flags = ["nodejs_compat"]

# Cron Triggers (매 1시간)
[triggers]
crons = ["0 * * * *"]

# KV Namespace (설정/프롬프트/스타일)
[[kv_namespaces]]
binding = "AICR_CONFIG"
id = "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"

# D1 Database (실행 로그)
[[d1_databases]]
binding = "AICR_DB"
database_name = "aicr-logs"
database_id = "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"

# R2 Bucket (이미지 임시 저장)
[[r2_buckets]]
binding = "AICR_IMAGES"
bucket_name = "aicr-images"

# Workflows
[[workflows]]
name = "master-workflow"
binding = "MASTER_WORKFLOW"
class_name = "MasterWorkflow"

[[workflows]]
name = "item-workflow"
binding = "ITEM_WORKFLOW"
class_name = "ItemWorkflow"

# 환경 변수
[vars]
ENVIRONMENT = "production"
LOG_LEVEL = "info"

# Secrets (wrangler secret으로 별도 설정)
# WORDPRESS_URL
# WORDPRESS_API_KEY
# OPENAI_API_KEY
# HMAC_SECRET
```

---

## Appendix B: Prompt Templates

### Step A: Outline Prompt

```typescript
// prompts/outline.ts
export const outlinePrompt = `
당신은 전문 콘텐츠 기획자입니다. 주어진 원본 콘텐츠를 분석하여
독창적인 관점과 매력적인 구조를 갖춘 블로그 글의 아웃라인을 작성하세요.

## 블로그 프로필
{blog_profile}

## 원본 콘텐츠
{source_content}

## 요구사항
1. angle: 원본과 차별화되는 독창적 관점 (한 문장)
2. hook: 도입부 전략 (question/anecdote/statistic/contrast 중 택1)
3. target_word_count: 원본 대비 1.5배 이상 분량
4. sections: 4-8개의 섹션, 각 섹션별 purpose, key_points, content_type
5. conclusion_strategy: 결론 접근법
6. internal_link_opportunities: 연관 가능한 주제 키워드

## 출력 형식
JSON 형식으로 출력하세요.
`;
```

### Step B: Content Prompt

```typescript
// prompts/content.ts
export const contentPrompt = `
당신은 {blog_name}의 전문 블로그 작가입니다.

## 페르소나
{voice}

## 글쓰기 규칙
{writing_rules}

## 금지 사항
{forbidden}

## 예시 글 (스타일 참고)
{few_shot_examples}

## 아웃라인
{outline}

## 원본 콘텐츠 (참고용)
{source_content}

## 작성 지침
각 섹션을 작성하기 전에 다음을 먼저 생각하세요:
1. 이 섹션에서 독자가 얻을 핵심 인사이트는?
2. 원본에 없지만 추가할 가치 있는 정보는?
3. 가장 흥미로운 전달 방법은?
(내부 추론은 출력에 포함하지 마세요)

HTML 형식으로 본문만 출력하세요. (제목 제외)
`;
```

### Step D: Self-Critique Prompt

```typescript
// prompts/critique.ts
export const critiquePrompt = `
당신은 엄격한 콘텐츠 품질 평가자입니다.
아래 체크리스트에 따라 1-10점으로 평가하세요.

## 평가 대상 콘텐츠
제목: {title}
본문: {content}

## 체크리스트 (각 항목 1-10점)
1. hook_quality: 도입부가 3초 내에 독자 관심을 끄는가? (뻔한 패턴 X)
2. angle_originality: 독창적 관점이 반영되어 있는가?
3. section_value: 각 섹션이 구체적 가치를 제공하는가?
4. length_adequacy: 충분한 분량인가? (원본 대비 1.5배 이상)
5. writing_naturalness: 문체가 자연스러운가? (어미 반복 X)
6. examples_included: 실질적 사례/데이터가 포함되었는가? (true/false)
7. seo_integration: SEO 키워드가 자연스럽게 녹아있는가?
8. conclusion_actionable: 결론이 실행 가능한 인사이트를 제공하는가?

## 출력 형식
{
  "score": (1-10 종합 점수),
  "passed": (score >= 8 ? true : false),
  "checklist": { ... },
  "issues": ["발견된 문제점"],
  "improvement_suggestions": ["개선 제안"]
}
`;
```

---

## Appendix C: REST API PHP Implementation Guide

```php
<?php
// src/API/RestController.php

namespace AIContentRewriter\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class RestController extends WP_REST_Controller {

    protected $namespace = 'aicr/v1';

    public function register_routes(): void {
        // GET /feeds
        register_rest_route($this->namespace, '/feeds', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_feeds'],
            'permission_callback' => [$this, 'check_api_key'],
        ]);

        // GET /feed-items/pending
        register_rest_route($this->namespace, '/feed-items/pending', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_pending_items'],
            'permission_callback' => [$this, 'check_api_key'],
            'args' => [
                'status' => [
                    'default' => 'queued',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'limit' => [
                    'default' => 10,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // POST /webhook
        register_rest_route($this->namespace, '/webhook', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handle_webhook'],
            'permission_callback' => [$this, 'verify_hmac'],
        ]);

        // ... 나머지 엔드포인트
    }

    public function check_api_key(WP_REST_Request $request): bool {
        $auth_header = $request->get_header('Authorization');
        if (!$auth_header || strpos($auth_header, 'Bearer ') !== 0) {
            return false;
        }

        $token = substr($auth_header, 7);
        $stored_key = get_option('aicr_wp_api_key');

        return hash_equals($stored_key, $token);
    }

    public function verify_hmac(WP_REST_Request $request): bool {
        $receiver = new WebhookReceiver();
        return $receiver->verify_signature($request);
    }
}
```
