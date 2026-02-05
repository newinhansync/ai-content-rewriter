# WordPress Plugin ↔ Cloudflare Worker 아키텍처

> 이 문서는 AI Content Rewriter 시스템의 전체 아키텍처와 WordPress 플러그인-Cloudflare Worker 간의 통신 로직을 설명합니다.

## 목차

1. [시스템 개요](#시스템-개요)
2. [아키텍처 다이어그램](#아키텍처-다이어그램)
3. [컴포넌트 상세](#컴포넌트-상세)
4. [통신 프로토콜](#통신-프로토콜)
5. [데이터 흐름](#데이터-흐름)
6. [Workflow 상세](#workflow-상세)
7. [보안 메커니즘](#보안-메커니즘)
8. [에러 처리](#에러-처리)

---

## 시스템 개요

### 왜 Cloudflare Worker인가?

WordPress 환경의 제약:
- **PHP 타임아웃**: 기본 30초, 최대 300초
- **메모리 제한**: 일반적으로 256MB~512MB
- **동기 처리**: 긴 작업이 사용자 경험 저하

AI 콘텐츠 생성의 요구사항:
- **긴 처리 시간**: 1개 글 생성에 2~5분 소요
- **다단계 처리**: Outline → Content → SEO → Critique
- **높은 안정성**: 중간 실패 시 재시도 필요

**해결책: Cloudflare Workers + Workflows**
- 30분 타임아웃 (Workflows)
- 글로벌 엣지 배포
- 자동 체크포인트/재개
- 비동기 웹훅 콜백

### 핵심 원칙

```
1. WordPress는 "요청자/수신자" 역할
2. Worker는 "처리자" 역할
3. 모든 AI 처리는 Worker에서 수행
4. 결과는 Webhook으로 WordPress에 전달
```

---

## 아키텍처 다이어그램

### 전체 시스템 구조

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              사용자 (WordPress Admin)                        │
└─────────────────────────────────────────────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                           WordPress (PHP)                                    │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐        │
│  │   Admin UI  │  │  REST API   │  │  RSS Feed   │  │  Webhook    │        │
│  │   (React)   │  │  Endpoints  │  │  Manager    │  │  Receiver   │        │
│  └─────────────┘  └─────────────┘  └─────────────┘  └─────────────┘        │
│         │                │                │                ▲                │
│         │                │                │                │                │
│  ┌──────┴────────────────┴────────────────┴────────────────┴──────────────┐│
│  │                        Plugin Database                                  ││
│  │  [aicr_feeds] [aicr_feed_items] [aicr_history] [aicr_templates]        ││
│  └────────────────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────────────────┘
         │                                                         ▲
         │ ① Rewrite 요청                                          │ ⑥ Webhook
         │ (POST /api/rewrite)                                     │ (결과 전달)
         ▼                                                         │
┌─────────────────────────────────────────────────────────────────────────────┐
│                         Cloudflare Worker                                    │
│  ┌─────────────┐  ┌─────────────────────────────────────────────────┐      │
│  │ HTTP Router │  │                   Workflows                      │      │
│  │   (Hono)    │  │  ┌─────────────┐      ┌─────────────────────┐  │      │
│  │             │  │  │   Master    │ ───▶ │   Item Workflow     │  │      │
│  │ /api/*      │  │  │  Workflow   │      │                     │  │      │
│  └─────────────┘  │  │  (자동화)   │      │ ② Extract           │  │      │
│         │         │  └─────────────┘      │ ③ Outline           │  │      │
│         │         │                       │ ④ Content           │  │      │
│         │         │                       │ ⑤ SEO + Critique    │  │      │
│         │         │                       └─────────────────────┘  │      │
│         │         └─────────────────────────────────────────────────┘      │
│         │                                                                   │
│  ┌──────┴───────────────────────────────────────────────────────────┐      │
│  │                      Cloudflare Services                          │      │
│  │  [KV: Config/Lock]  [D1: Tasks/Logs]  [R2: Images]               │      │
│  └───────────────────────────────────────────────────────────────────┘      │
└─────────────────────────────────────────────────────────────────────────────┘
         │                                                         │
         ▼                                                         ▼
┌─────────────────────┐                              ┌─────────────────────┐
│     OpenAI API      │                              │   Google Gemini     │
│   (GPT-4o, etc.)    │                              │ (Pro, Flash, Imagen)│
└─────────────────────┘                              └─────────────────────┘
```

### 데이터 저장소 역할

| 저장소 | 위치 | 용도 |
|--------|------|------|
| MySQL | WordPress | 피드, 아이템, 히스토리, 게시글 |
| KV | Cloudflare | 설정 캐시, 분산 잠금 |
| D1 | Cloudflare | 작업 상태, 워크플로우 로그 |
| R2 | Cloudflare | 생성된 이미지 임시 저장 |

---

## 컴포넌트 상세

### WordPress 플러그인 컴포넌트

```
wp-content/plugins/ai-content-rewriter/
├── src/
│   ├── Admin/              # 관리자 UI
│   │   ├── AdminMenu.php   # 메뉴 등록
│   │   ├── SettingsPage.php # 설정 페이지
│   │   └── assets/         # React 앱
│   │
│   ├── API/                # REST API
│   │   ├── RewriteController.php   # 재작성 요청
│   │   ├── FeedsController.php     # 피드 관리
│   │   ├── WebhookController.php   # 결과 수신
│   │   └── ConfigController.php    # 설정 동기화
│   │
│   ├── RSS/                # RSS 피드 처리
│   │   ├── FeedManager.php # 피드 CRUD
│   │   └── FeedParser.php  # RSS 파싱
│   │
│   ├── Content/            # 콘텐츠 처리
│   │   ├── PostCreator.php # 게시글 생성
│   │   └── MediaHandler.php # 이미지 처리
│   │
│   └── Worker/             # Worker 통신
│       ├── WorkerClient.php    # HTTP 클라이언트
│       └── WebhookHandler.php  # 웹훅 처리
```

### Cloudflare Worker 컴포넌트

```
cloudflare-worker/
├── src/
│   ├── index.ts            # 진입점, 라우터
│   │
│   ├── handlers/           # HTTP 핸들러
│   │   ├── rewrite.ts      # POST /api/rewrite
│   │   ├── status.ts       # GET /api/status/:id
│   │   └── config.ts       # POST /api/sync-config
│   │
│   ├── workflows/          # Durable Workflows
│   │   ├── MasterWorkflow.ts   # 자동화 오케스트레이터
│   │   └── ItemWorkflow.ts     # 개별 아이템 처리
│   │
│   ├── services/           # 외부 서비스 통신
│   │   ├── ai.ts           # OpenAI, Gemini API
│   │   └── wordpress.ts    # WordPress REST API
│   │
│   └── utils/              # 유틸리티
│       └── auth.ts         # 인증, HMAC
```

---

## 통신 프로토콜

### 1. WordPress → Worker 요청

#### 인증 방식: Bearer Token

```http
POST /api/rewrite HTTP/1.1
Host: aicr-worker.{subdomain}.workers.dev
Authorization: Bearer {WORKER_SECRET}
Content-Type: application/json

{
  "task_id": "uuid-v4",
  "callback_url": "https://your-site.com/wp-json/aicr/v1/webhook",
  "callback_secret": "{HMAC_SECRET}",
  "payload": {
    "source_url": "https://example.com/article",
    "source_content": "<p>fallback content</p>",
    "language": "ko",
    "ai_provider": "chatgpt",
    "options": {
      "auto_publish": true,
      "publish_threshold": 8,
      "generate_images": true
    }
  }
}
```

#### 응답 (즉시 반환)

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

### 2. Worker → WordPress 웹훅

#### 인증 방식: HMAC-SHA256

```http
POST /wp-json/aicr/v1/webhook HTTP/1.1
Host: your-site.com
X-AICR-Signature: {HMAC_SIGNATURE}
X-AICR-Timestamp: {UNIX_TIMESTAMP}
Content-Type: application/json

{
  "task_id": "uuid-v4",
  "item_id": 123,
  "status": "completed",
  "quality_score": 8.5,
  "result": {
    "title": "생성된 제목",
    "content": "<p>생성된 본문...</p>",
    "excerpt": "요약...",
    "category_suggestion": "Technology",
    "tags": ["AI", "Automation"],
    "meta_title": "SEO 제목",
    "meta_description": "SEO 설명",
    "featured_image_url": "https://r2.../image.png"
  },
  "metrics": {
    "processing_time_ms": 45000,
    "token_usage": {
      "input": 5000,
      "output": 3000,
      "total": 8000
    },
    "steps_completed": ["extraction", "outline", "content", "seo", "critique", "webhook"],
    "retry_count": 0
  }
}
```

#### HMAC 서명 생성 알고리즘

```
signature = HMAC-SHA256(
  key: HMAC_SECRET,
  message: "{timestamp}.{json_payload}"
)
```

#### WordPress에서 검증

```php
// WebhookHandler.php
$timestamp = $_SERVER['HTTP_X_AICR_TIMESTAMP'];
$signature = $_SERVER['HTTP_X_AICR_SIGNATURE'];
$payload = file_get_contents('php://input');

// 5분 이내 요청인지 확인
if (abs(time() - $timestamp) > 300) {
    return new WP_Error('expired', 'Request expired');
}

// 서명 검증
$expected = hash_hmac('sha256', "{$timestamp}.{$payload}", HMAC_SECRET);
if (!hash_equals($expected, $signature)) {
    return new WP_Error('invalid_signature', 'Invalid signature');
}
```

### 3. Worker → WordPress REST API

#### 피드 목록 조회

```http
GET /wp-json/aicr/v1/feeds HTTP/1.1
Host: your-site.com
X-AICR-API-Key: {WP_API_KEY}
```

#### 피드 아이템 조회

```http
GET /wp-json/aicr/v1/feed-items/pending?limit=10 HTTP/1.1
Host: your-site.com
X-AICR-API-Key: {WP_API_KEY}
```

#### 아이템 상태 업데이트

```http
PATCH /wp-json/aicr/v1/feed-items/123/status HTTP/1.1
Host: your-site.com
X-AICR-API-Key: {WP_API_KEY}
Content-Type: application/json

{
  "status": "processing"
}
```

---

## 데이터 흐름

### 수동 재작성 흐름

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ 1. 사용자가 URL 입력하고 "Rewrite" 클릭                                       │
└─────────────────────────────────────────────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ 2. WordPress가 Worker에 POST /api/rewrite 요청                               │
│    - task_id 생성                                                            │
│    - 로컬 DB에 pending 상태로 저장                                            │
└─────────────────────────────────────────────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ 3. Worker가 요청 수락하고 즉시 202 Accepted 반환                               │
│    - ItemWorkflow 시작                                                       │
│    - D1에 task 레코드 생성                                                    │
└─────────────────────────────────────────────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ 4. 사용자는 즉시 UI 반환받음 (비동기 처리)                                      │
│    - 프론트엔드가 폴링으로 GET /api/status/:taskId 조회                        │
│    - 또는 WebSocket으로 실시간 업데이트 (미구현)                                │
└─────────────────────────────────────────────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ 5. ItemWorkflow 실행 (2~5분 소요)                                            │
│    Step 1: URL에서 콘텐츠 추출                                                │
│    Step 2: AI로 아웃라인 생성                                                 │
│    Step 3: AI로 본문 작성 (1.5배 확장)                                        │
│    Step 4: SEO 메타데이터 생성                                                │
│    Step 5: 자체 비평 (품질 < 7이면 재시도)                                     │
│    Step 6: 이미지 생성 (선택)                                                 │
│    Step 7: 웹훅으로 결과 전송                                                 │
└─────────────────────────────────────────────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ 6. WordPress가 웹훅 수신                                                      │
│    - HMAC 서명 검증                                                          │
│    - 결과로 게시글 생성 (또는 초안)                                            │
│    - 이미지 다운로드 및 미디어 라이브러리 등록                                   │
│    - 히스토리 테이블 업데이트                                                  │
└─────────────────────────────────────────────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ 7. 사용자에게 완료 알림                                                        │
│    - 관리자 알림 또는 이메일                                                   │
│    - UI에서 결과 확인 가능                                                    │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 자동화 (Cron) 흐름

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ 매시 정각 (0 * * * *)                                                        │
│ Cloudflare Cron Trigger가 MasterWorkflow 시작                                │
└─────────────────────────────────────────────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ MasterWorkflow Step 1: 분산 잠금 획득                                         │
│ - KV에서 lock 확인                                                           │
│ - 이미 실행 중이면 종료                                                       │
│ - 새 lock 획득 (TTL: 1시간)                                                  │
└─────────────────────────────────────────────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ MasterWorkflow Step 2: WordPress에서 피드 목록 조회                           │
│ - GET /wp-json/aicr/v1/feeds                                                │
│ - is_active=true, auto_rewrite=true인 피드만 필터링                          │
└─────────────────────────────────────────────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ MasterWorkflow Step 3: 대기 중인 피드 아이템 조회                              │
│ - GET /wp-json/aicr/v1/feed-items/pending?limit={daily_limit}               │
│ - 일일 처리량 제한 적용                                                       │
└─────────────────────────────────────────────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ MasterWorkflow Step 4: AI 큐레이션                                           │
│ - 각 아이템의 재작성 가치 평가 (0.0 ~ 1.0)                                    │
│ - threshold 이상인 아이템만 승인                                              │
│ - 미승인 아이템은 "skipped" 상태로 변경                                       │
└─────────────────────────────────────────────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ MasterWorkflow Step 5: ItemWorkflow 디스패치                                  │
│ - 승인된 각 아이템에 대해 ItemWorkflow 생성                                    │
│ - 2초 간격으로 순차 디스패치 (rate limiting)                                  │
│ - 아이템 상태를 "processing"으로 변경                                         │
└─────────────────────────────────────────────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ 각 ItemWorkflow가 독립적으로 실행                                             │
│ (위의 수동 재작성 흐름과 동일)                                                 │
└─────────────────────────────────────────────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ MasterWorkflow 종료: 잠금 해제                                                │
│ - KV에서 lock 삭제                                                           │
│ - 처리 통계 D1에 저장                                                        │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Workflow 상세

### MasterWorkflow

**목적**: 자동화된 콘텐츠 처리 파이프라인 오케스트레이션

```typescript
class MasterWorkflow extends WorkflowEntrypoint<Env, MasterWorkflowParams> {
  async run(event: WorkflowEvent<MasterWorkflowParams>, step: WorkflowStep) {
    // Step 1: 분산 잠금 획득
    const locked = await step.do('acquire-lock', () => this.acquireLock());
    if (!locked) return { success: false, error: 'Already running' };

    // Step 2: 피드 목록 조회
    const feeds = await step.do('fetch-feeds', () => this.fetchFeeds());

    // Step 3: 대기 아이템 조회
    const items = await step.do('fetch-items', () => this.fetchPendingItems());

    // Step 4: AI 큐레이션
    const approved = await step.do('curate', () => this.curateItems(items));

    // Step 5: ItemWorkflow 디스패치
    await step.do('dispatch', () => this.dispatchWorkflows(approved));

    // 잠금 해제
    await this.releaseLock();

    return { success: true, items_processed: approved.length };
  }
}
```

**체크포인트**: 각 `step.do()` 호출 후 자동 저장. 실패 시 해당 단계부터 재개.

### ItemWorkflow

**목적**: 개별 콘텐츠 처리 (Multi-Step Prompting)

```typescript
class ItemWorkflow extends WorkflowEntrypoint<Env, ItemWorkflowParams> {
  async run(event: WorkflowEvent<ItemWorkflowParams>, step: WorkflowStep) {
    const { source_url, language, ai_provider } = event.payload;

    // Step 1: 콘텐츠 추출
    const content = await step.do('extract', () => this.extractContent(source_url));

    // Step 2: 아웃라인 생성
    const outline = await step.do('outline', () => this.generateOutline(content));

    // Step 3: 본문 작성 (1.5배 확장)
    let article = await step.do('content', () => this.generateContent(outline));

    // Step 4: SEO 최적화
    const seo = await step.do('seo', () => this.optimizeSEO(article));

    // Step 5: 자체 비평 (재시도 루프)
    let retries = 0;
    let critique = await step.do('critique', () => this.selfCritique(article));

    while (critique.score < 7 && retries < 2) {
      article = await step.do(`improve-${retries}`, () =>
        this.improveContent(article, critique.suggestions)
      );
      critique = await step.do(`critique-${retries}`, () =>
        this.selfCritique(article)
      );
      retries++;
    }

    // Step 6: 이미지 생성
    const image = await step.do('image', () => this.generateImage(article.title));

    // Step 7: 웹훅 전송
    await step.do('webhook', () => this.sendWebhook({
      title: article.title,
      content: article.content,
      ...seo,
      featured_image_url: image.url,
      quality_score: critique.score
    }));

    return { success: true, quality_score: critique.score };
  }
}
```

### Multi-Step Prompting 상세

```
┌──────────────────────────────────────────────────────────────────┐
│                    원본 콘텐츠 (500단어)                          │
└──────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌──────────────────────────────────────────────────────────────────┐
│ Step 1: OUTLINE (gpt-4o-mini, ~$0.001)                          │
│                                                                  │
│ System: "You are a content strategist..."                       │
│ User: "Create outline for: {extracted_content}"                 │
│                                                                  │
│ Output:                                                          │
│ {                                                                │
│   "main_topic": "AI의 미래",                                     │
│   "target_audience": "기술 관심자",                               │
│   "key_points": ["현재 상태", "발전 방향", "사회적 영향"],          │
│   "structure": ["intro", "body1", "body2", "body3", "conclusion"]│
│   "tone": "informative",                                         │
│   "word_count_target": 750                                       │
│ }                                                                │
└──────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌──────────────────────────────────────────────────────────────────┐
│ Step 2: CONTENT (gpt-4o, ~$0.03)                                │
│                                                                  │
│ System: "You are a professional Korean blog writer..."          │
│ User: "Write article based on: {outline}"                       │
│                                                                  │
│ Output: 750단어 HTML 콘텐츠 (1.5x 확장)                          │
│ <h2>AI의 미래: 우리가 알아야 할 것들</h2>                         │
│ <p>인공지능 기술은 급속도로 발전하고 있습니다...</p>               │
│ ...                                                              │
└──────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌──────────────────────────────────────────────────────────────────┐
│ Step 3: SEO (gpt-4o-mini, ~$0.001)                              │
│                                                                  │
│ System: "You are an SEO specialist..."                          │
│ User: "Optimize: {article}"                                     │
│                                                                  │
│ Output:                                                          │
│ {                                                                │
│   "meta_title": "AI의 미래: 2025년 전망 | 완벽 가이드",           │
│   "meta_description": "인공지능 기술의 현재와 미래를...",         │
│   "keywords": ["AI", "인공지능", "기술 트렌드"],                  │
│   "category_suggestion": "Technology",                           │
│   "tags": ["AI", "미래기술", "트렌드"]                            │
│ }                                                                │
└──────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌──────────────────────────────────────────────────────────────────┐
│ Step 4: CRITIQUE (gpt-4o-mini, ~$0.002)                         │
│                                                                  │
│ System: "You are a content quality evaluator..."                │
│ User: "Evaluate: {article}"                                     │
│                                                                  │
│ Output:                                                          │
│ {                                                                │
│   "overall_score": 8.5,                                          │
│   "criteria_scores": {                                           │
│     "accuracy": 9,                                               │
│     "readability": 8,                                            │
│     "seo_optimization": 8,                                       │
│     "engagement": 9                                              │
│   },                                                             │
│   "passed": true,                                                │
│   "suggestions": []                                              │
│ }                                                                │
│                                                                  │
│ ※ score < 7이면 suggestions 기반으로 Step 2 재실행              │
└──────────────────────────────────────────────────────────────────┘
```

---

## 보안 메커니즘

### 1. WordPress → Worker 인증

```
┌─────────────────┐     Bearer Token      ┌─────────────────┐
│    WordPress    │ ───────────────────▶  │     Worker      │
│                 │   Authorization:      │                 │
│                 │   Bearer {SECRET}     │                 │
└─────────────────┘                       └─────────────────┘
```

- **WORKER_SECRET**: 32자 이상 랜덤 문자열 권장
- **상수 시간 비교**: 타이밍 공격 방지

### 2. Worker → WordPress 인증

```
┌─────────────────┐     HMAC-SHA256       ┌─────────────────┐
│     Worker      │ ───────────────────▶  │    WordPress    │
│                 │   X-AICR-Signature    │                 │
│                 │   X-AICR-Timestamp    │                 │
└─────────────────┘                       └─────────────────┘
```

- **Replay Attack 방지**: 5분 타임스탬프 윈도우
- **HMAC_SECRET**: 32자 이상 랜덤 문자열 권장

### 3. Worker → WordPress REST API

```
┌─────────────────┐     API Key           ┌─────────────────┐
│     Worker      │ ───────────────────▶  │    WordPress    │
│                 │   X-AICR-API-Key      │                 │
└─────────────────┘                       └─────────────────┘
```

- **WP_API_KEY**: WordPress 플러그인에서 생성한 키
- **용도**: 피드/아이템 조회, 상태 업데이트

---

## 에러 처리

### WordPress 측 에러 처리

```php
// Worker 요청 실패 시
try {
    $response = $worker_client->rewrite($url);
} catch (WorkerConnectionException $e) {
    // Worker 연결 실패 - 로컬 처리로 폴백
    $this->process_locally($url);
} catch (WorkerTimeoutException $e) {
    // 타임아웃 - 나중에 재시도
    $this->schedule_retry($url);
}
```

### Worker 측 에러 처리

```typescript
// Workflow 단계별 에러 처리
try {
  const content = await step.do('content', async () => {
    const result = await aiService.complete({ ... });
    if (!result.success) {
      throw new AIServiceError(result.error);
    }
    return result.content;
  });
} catch (error) {
  // step.do 내에서 에러 발생 시:
  // 1. 자동으로 step 재시도 (최대 3회)
  // 2. 실패 시 workflow 일시 중지
  // 3. 나중에 수동 또는 자동 재개 가능

  // 영구 실패 시 웹훅으로 에러 전송
  await this.sendFailureWebhook(error);
}
```

### 에러 코드 정의

| 코드 | 설명 | 조치 |
|------|------|------|
| `EXTRACTION_FAILED` | URL 콘텐츠 추출 실패 | source_content 폴백 사용 |
| `AI_API_ERROR` | AI API 호출 실패 | 재시도 후 다른 모델 시도 |
| `AI_RATE_LIMIT` | API Rate Limit | 지수 백오프 후 재시도 |
| `QUALITY_LOW` | 품질 점수 미달 | 최대 2회 재생성 |
| `WEBHOOK_FAILED` | 웹훅 전송 실패 | 3회 재시도 후 D1에 보관 |
| `TIMEOUT` | 처리 시간 초과 | Workflow 체크포인트에서 재개 |

---

## 성능 최적화

### 토큰 사용량 최적화

| 단계 | 모델 | 예상 토큰 | 비용 (GPT-4o 기준) |
|------|------|----------|-------------------|
| Outline | gpt-4o-mini | ~500 | $0.001 |
| Content | gpt-4o | ~2,000 | $0.030 |
| SEO | gpt-4o-mini | ~300 | $0.001 |
| Critique | gpt-4o-mini | ~400 | $0.001 |
| **총합** | - | ~3,200 | **~$0.033** |

### 처리 시간 최적화

- **병렬 처리 불가**: AI 단계는 순차적 (이전 결과 의존)
- **이미지 생성 선택적**: 옵션으로 비활성화 가능
- **캐싱**: KV에 설정 캐싱 (TTL: 5분)

---

*최종 업데이트: 2025-02-05*
