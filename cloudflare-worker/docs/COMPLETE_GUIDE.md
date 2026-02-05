# AI Content Rewriter Worker - 통합 가이드

> Cloudflare Worker의 설치, 설정, 아키텍처, 테스트, CI/CD를 모두 포함한 완전한 가이드입니다.

## 목차

### Part 1: 개요
- [1.1 시스템 개요](#11-시스템-개요)
- [1.2 아키텍처 개요](#12-아키텍처-개요)
- [1.3 기술 스택](#13-기술-스택)

### Part 2: 설치 및 설정
- [2.1 사전 요구사항](#21-사전-요구사항)
- [2.2 Cloudflare 계정 설정](#22-cloudflare-계정-설정)
- [2.3 로컬 개발 환경](#23-로컬-개발-환경)
- [2.4 Cloudflare 리소스 생성](#24-cloudflare-리소스-생성)
- [2.5 Secrets 설정](#25-secrets-설정)
- [2.6 데이터베이스 초기화](#26-데이터베이스-초기화)

### Part 3: 아키텍처 상세
- [3.1 WordPress-Worker 통신](#31-wordpress-worker-통신)
- [3.2 Workflow 시스템](#32-workflow-시스템)
- [3.3 Multi-Step Prompting](#33-multi-step-prompting)
- [3.4 보안 메커니즘](#34-보안-메커니즘)

### Part 4: 테스트 자동화
- [4.1 테스트 환경 구성](#41-테스트-환경-구성)
- [4.2 테스트 구조](#42-테스트-구조)
- [4.3 테스트 실행](#43-테스트-실행)
- [4.4 테스트 작성 가이드](#44-테스트-작성-가이드)

### Part 5: CI/CD 파이프라인
- [5.1 GitHub Actions 개요](#51-github-actions-개요)
- [5.2 파이프라인 구성](#52-파이프라인-구성)
- [5.3 GitHub Secrets 설정](#53-github-secrets-설정)
- [5.4 배포 전략](#54-배포-전략)

### Part 6: 운영 및 모니터링
- [6.1 로그 확인](#61-로그-확인)
- [6.2 문제 해결](#62-문제-해결)
- [6.3 성능 최적화](#63-성능-최적화)

---

# Part 1: 개요

## 1.1 시스템 개요

### 문제 정의

WordPress 환경에서 AI 콘텐츠 생성 시 발생하는 문제:

| 문제 | 원인 | 영향 |
|------|------|------|
| 타임아웃 | PHP 기본 30초 제한 | AI 처리 중 연결 끊김 |
| 메모리 부족 | WordPress 메모리 제한 | 대용량 콘텐츠 처리 불가 |
| 사용자 경험 저하 | 동기 처리 방식 | 긴 대기 시간 |
| 확장성 부족 | 단일 서버 의존 | 동시 처리 한계 |

### 해결책: Cloudflare Workers + Workflows

```
┌─────────────────────────────────────────────────────────────────┐
│                     기존 방식 (WordPress)                        │
│  사용자 → PHP → AI API 호출 (2-5분) → 응답 → 타임아웃! ❌        │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                     새로운 방식 (Worker)                         │
│  사용자 → PHP → Worker 요청 → 즉시 응답 ✅                       │
│                     ↓                                            │
│            Worker Workflow (백그라운드, 최대 30분)                │
│                     ↓                                            │
│            완료 시 Webhook → WordPress → 게시글 생성             │
└─────────────────────────────────────────────────────────────────┘
```

### 주요 기능

| 기능 | 설명 |
|------|------|
| **비동기 처리** | WordPress 타임아웃 없이 백그라운드 처리 |
| **Multi-Step Prompting** | Outline → Content → SEO → Critique 파이프라인 |
| **Durable Workflows** | 체크포인트/재개 기능으로 안정성 보장 |
| **자동 큐레이션** | AI가 피드 아이템 가치 평가 |
| **품질 관리** | 자체 비평으로 품질 미달 시 재생성 |
| **이미지 생성** | Gemini Imagen으로 대표 이미지 자동 생성 |

## 1.2 아키텍처 개요

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
│                                                                              │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │                        Plugin Database (MySQL)                        │  │
│  │  [aicr_feeds] [aicr_feed_items] [aicr_history] [aicr_templates]      │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────────┘
         │                                                         ▲
         │ ① Rewrite 요청                                          │ ⑥ Webhook
         ▼                                                         │
┌─────────────────────────────────────────────────────────────────────────────┐
│                         Cloudflare Worker                                    │
│  ┌─────────────┐  ┌─────────────────────────────────────────────────┐      │
│  │ HTTP Router │  │                   Workflows                      │      │
│  │   (Hono)    │  │  ┌─────────────┐      ┌─────────────────────┐  │      │
│  │             │──▶│  │   Master    │ ───▶ │   Item Workflow     │  │      │
│  │ /api/*      │  │  │  Workflow   │      │                     │  │      │
│  └─────────────┘  │  │  (Cron)     │      │ Extract → Outline   │  │      │
│                   │  └─────────────┘      │ → Content → SEO     │  │      │
│                   │                       │ → Critique → Image  │  │      │
│                   │                       │ → Webhook           │  │      │
│                   │                       └─────────────────────┘  │      │
│                   └─────────────────────────────────────────────────┘      │
│                                                                              │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │                      Cloudflare Services                              │  │
│  │  [KV: Config/Lock]  [D1: Tasks/Logs]  [R2: Images]                   │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────────┘
         │                                                         │
         ▼                                                         ▼
┌─────────────────────┐                              ┌─────────────────────┐
│     OpenAI API      │                              │   Google Gemini     │
│  (GPT-4o, GPT-4o-   │                              │ (Pro, Flash, Imagen)│
│   mini, o1)         │                              │                     │
└─────────────────────┘                              └─────────────────────┘
```

## 1.3 기술 스택

### Worker 기술 스택

| 카테고리 | 기술 | 버전 | 용도 |
|----------|------|------|------|
| **런타임** | Cloudflare Workers | - | 서버리스 실행 환경 |
| **언어** | TypeScript | 5.3+ | 타입 안전성 |
| **프레임워크** | Hono | 4.6+ | HTTP 라우팅 |
| **워크플로우** | Cloudflare Workflows | - | 장기 실행 작업 |
| **테스트** | Vitest | 2.0+ | 단위/통합 테스트 |
| **린트** | ESLint | 8.57+ | 코드 품질 |
| **포맷** | Prettier | 3.2+ | 코드 스타일 |
| **CI/CD** | GitHub Actions | - | 자동화 파이프라인 |

### Cloudflare 서비스

| 서비스 | 용도 | 특징 |
|--------|------|------|
| **Workers** | 코드 실행 | 글로벌 엣지, 빠른 콜드 스타트 |
| **Workflows** | 장기 작업 | 30분 타임아웃, 체크포인트 |
| **KV** | 키-값 저장 | 설정 캐시, 분산 잠금 |
| **D1** | SQL 데이터베이스 | 작업 상태, 로그 |
| **R2** | 객체 저장 | 생성된 이미지 |

---

# Part 2: 설치 및 설정

## 2.1 사전 요구사항

### 필수 소프트웨어

```bash
# Node.js 버전 확인 (18.0.0 이상 필요)
node --version

# npm 버전 확인
npm --version

# Git 확인
git --version
```

### 필수 계정

| 서비스 | 필수 여부 | 플랜 | 가입 URL |
|--------|----------|------|----------|
| Cloudflare | ✅ | Workers Paid ($5/월) | https://dash.cloudflare.com/sign-up |
| OpenAI | ✅ | Pay-as-you-go | https://platform.openai.com/signup |
| Google AI | 선택 | Free tier 가능 | https://aistudio.google.com/ |
| GitHub | ✅ (CI/CD용) | Free | https://github.com/join |

> ⚠️ **Cloudflare Workers Paid 플랜 필수**: Workflows 기능은 유료 플랜에서만 사용 가능합니다.

## 2.2 Cloudflare 계정 설정

### Account ID 확인

1. https://dash.cloudflare.com 로그인
2. **Workers & Pages** → **Overview**
3. 우측 사이드바에서 **Account ID** 복사

### API Token 생성

1. 우상단 프로필 아이콘 → **My Profile**
2. **API Tokens** 탭 → **Create Token**
3. **Edit Cloudflare Workers** 템플릿 사용 또는 커스텀:

```
권한 구성:
├── Account
│   ├── Workers KV Storage: Edit
│   ├── Workers R2 Storage: Edit
│   ├── D1: Edit
│   └── Workers Scripts: Edit
└── Zone (선택)
    └── Workers Routes: Edit
```

4. 토큰 생성 후 안전한 곳에 저장

## 2.3 로컬 개발 환경

### 프로젝트 설정

```bash
# Worker 디렉토리로 이동
cd /path/to/wordpress/cloudflare-worker

# 의존성 설치
npm install

# Wrangler CLI 로그인
npx wrangler login

# 로그인 확인
npx wrangler whoami
```

### 로컬 환경 변수 설정

```bash
# .dev.vars 파일 생성 (개발용)
cat > .dev.vars << 'EOF'
WORKER_SECRET=dev-worker-secret-32chars-minimum
HMAC_SECRET=dev-hmac-secret-32chars-minimum
WP_API_KEY=your-wordpress-api-key
OPENAI_API_KEY=sk-your-openai-api-key
GEMINI_API_KEY=your-gemini-api-key
WORDPRESS_URL=http://localhost:8080
EOF
```

> ⚠️ `.dev.vars`는 `.gitignore`에 포함되어 있습니다. 절대 커밋하지 마세요!

## 2.4 Cloudflare 리소스 생성

### KV Namespaces

```bash
# 설정 저장용 KV
npx wrangler kv namespace create CONFIG_KV
# 출력 예: { binding = "CONFIG_KV", id = "abc123..." }

# 분산 잠금용 KV
npx wrangler kv namespace create LOCK_KV
# 출력 예: { binding = "LOCK_KV", id = "def456..." }
```

### D1 Database

```bash
npx wrangler d1 create aicr-worker-db
# 출력 예: database_id = "ghi789..."
```

### R2 Bucket

```bash
npx wrangler r2 bucket create aicr-images
```

### wrangler.toml 업데이트

생성된 ID를 `wrangler.toml`에 반영:

```toml
name = "ai-content-rewriter-worker"
main = "src/index.ts"
compatibility_date = "2025-01-01"

# KV Namespaces
[[kv_namespaces]]
binding = "CONFIG_KV"
id = "abc123..."  # ← 실제 ID로 교체

[[kv_namespaces]]
binding = "LOCK_KV"
id = "def456..."  # ← 실제 ID로 교체

# D1 Database
[[d1_databases]]
binding = "DB"
database_name = "aicr-worker-db"
database_id = "ghi789..."  # ← 실제 ID로 교체

# R2 Bucket
[[r2_buckets]]
binding = "IMAGES"
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

# Cron Triggers
[triggers]
crons = ["0 * * * *", "30 * * * *"]

# Environment Variables
[vars]
ENVIRONMENT = "development"
LOG_LEVEL = "debug"
```

## 2.5 Secrets 설정

### 로컬 개발

`.dev.vars` 파일 사용 (2.3에서 생성)

### Staging/Production

```bash
# 순차적으로 각 secret 설정
npx wrangler secret put WORKER_SECRET
npx wrangler secret put HMAC_SECRET
npx wrangler secret put WP_API_KEY
npx wrangler secret put OPENAI_API_KEY
npx wrangler secret put GEMINI_API_KEY
npx wrangler secret put WORDPRESS_URL

# 환경별 설정
npx wrangler secret put WORKER_SECRET --env staging
npx wrangler secret put WORKER_SECRET --env production
```

### Secrets 확인

```bash
npx wrangler secret list
```

## 2.6 데이터베이스 초기화

### 스키마 적용

```bash
# 로컬 D1 (개발용)
npx wrangler d1 execute aicr-worker-db --local --file=schema.sql

# 원격 D1 (staging/production)
npx wrangler d1 execute aicr-worker-db --file=schema.sql
```

### 스키마 구조

```sql
-- tasks: 작업 상태 추적
CREATE TABLE tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id TEXT UNIQUE NOT NULL,
    item_id INTEGER,
    status TEXT NOT NULL DEFAULT 'pending',
    progress INTEGER DEFAULT 0,
    current_step TEXT,
    params TEXT,
    result TEXT,
    error TEXT,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
);

-- workflow_logs: 디버깅용 로그
CREATE TABLE workflow_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    workflow_id TEXT NOT NULL,
    workflow_type TEXT NOT NULL,
    step_name TEXT,
    level TEXT DEFAULT 'info',
    message TEXT,
    details TEXT,
    created_at TEXT DEFAULT (datetime('now'))
);

-- processing_stats: 처리 통계
CREATE TABLE processing_stats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    date TEXT NOT NULL,
    items_processed INTEGER DEFAULT 0,
    items_failed INTEGER DEFAULT 0,
    total_tokens INTEGER DEFAULT 0,
    avg_quality_score REAL,
    avg_processing_time_ms INTEGER,
    created_at TEXT DEFAULT (datetime('now'))
);
```

---

# Part 3: 아키텍처 상세

## 3.1 WordPress-Worker 통신

### 통신 흐름

```
┌────────────┐                              ┌────────────┐
│ WordPress  │                              │   Worker   │
└────────────┘                              └────────────┘
      │                                           │
      │  ① POST /api/rewrite                     │
      │  Authorization: Bearer {WORKER_SECRET}    │
      │ ─────────────────────────────────────────▶│
      │                                           │
      │  ② 202 Accepted (즉시 반환)               │
      │ ◀─────────────────────────────────────────│
      │                                           │
      │         [백그라운드 처리 2-5분]            │
      │                                           │
      │  ③ POST /wp-json/aicr/v1/webhook         │
      │  X-AICR-Signature: {HMAC}                 │
      │ ◀─────────────────────────────────────────│
      │                                           │
      │  ④ 200 OK                                 │
      │ ─────────────────────────────────────────▶│
      │                                           │
```

### API 엔드포인트

| 메서드 | 경로 | 설명 | 인증 |
|--------|------|------|------|
| POST | /api/rewrite | 재작성 요청 | Bearer Token |
| GET | /api/status/:id | 작업 상태 조회 | Bearer Token |
| POST | /api/sync-config | 설정 동기화 | Bearer Token |
| GET | /api/health | 헬스 체크 | 없음 |
| POST | /api/trigger-master | 마스터 워크플로우 수동 실행 | Bearer Token |

### 요청/응답 예시

**재작성 요청:**
```json
// POST /api/rewrite
{
  "task_id": "550e8400-e29b-41d4-a716-446655440000",
  "callback_url": "https://your-site.com/wp-json/aicr/v1/webhook",
  "callback_secret": "your-hmac-secret",
  "payload": {
    "source_url": "https://example.com/article",
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

**웹훅 결과:**
```json
// POST /wp-json/aicr/v1/webhook
{
  "task_id": "550e8400-e29b-41d4-a716-446655440000",
  "item_id": 123,
  "status": "completed",
  "quality_score": 8.5,
  "result": {
    "title": "AI가 생성한 제목",
    "content": "<h2>소제목</h2><p>본문...</p>",
    "excerpt": "요약문...",
    "category_suggestion": "Technology",
    "tags": ["AI", "기술"],
    "meta_title": "SEO 최적화된 제목",
    "meta_description": "SEO 설명문...",
    "featured_image_url": "https://r2.../image.png"
  },
  "metrics": {
    "processing_time_ms": 45000,
    "token_usage": { "input": 5000, "output": 3000, "total": 8000 },
    "steps_completed": ["extraction", "outline", "content", "seo", "critique", "image", "webhook"],
    "retry_count": 0
  }
}
```

## 3.2 Workflow 시스템

### MasterWorkflow (자동화 오케스트레이터)

```
┌─────────────────────────────────────────────────────────────────┐
│                    MasterWorkflow                                │
│  Trigger: Cron (매시 정각) 또는 수동                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Step 1: 분산 잠금 획득 ─────────────────────────────────────┐  │
│          └─ KV에서 lock 확인/획득                             │  │
│          └─ 이미 실행 중이면 종료                             │  │
│                                                                  │
│  Step 2: 피드 목록 조회 ─────────────────────────────────────┐  │
│          └─ WordPress REST API 호출                           │  │
│          └─ is_active=true, auto_rewrite=true 필터            │  │
│                                                                  │
│  Step 3: 대기 아이템 조회 ───────────────────────────────────┐  │
│          └─ 일일 처리량 제한 (daily_limit) 적용               │  │
│          └─ status='pending' 아이템만                         │  │
│                                                                  │
│  Step 4: AI 큐레이션 ────────────────────────────────────────┐  │
│          └─ 각 아이템 가치 평가 (0.0 ~ 1.0)                   │  │
│          └─ threshold 미달 아이템은 'skipped' 처리            │  │
│                                                                  │
│  Step 5: ItemWorkflow 디스패치 ──────────────────────────────┐  │
│          └─ 승인된 각 아이템에 대해 Workflow 생성             │  │
│          └─ 2초 간격 순차 디스패치 (rate limiting)            │  │
│                                                                  │
│  마무리: 잠금 해제 + 통계 저장                                   │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### ItemWorkflow (콘텐츠 처리)

```
┌─────────────────────────────────────────────────────────────────┐
│                      ItemWorkflow                                │
│  입력: source_url, language, ai_provider, options               │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Step 1: 콘텐츠 추출 ────────────────────────────────────────┐  │
│          └─ URL에서 HTML 가져오기                             │  │
│          └─ 본문 추출 (article, main 태그 우선)               │  │
│          └─ 실패 시 source_content 폴백                       │  │
│                                                                  │
│  Step 2: 아웃라인 생성 ──────────────────────────────────────┐  │
│          └─ AI로 구조화된 아웃라인 생성                       │  │
│          └─ 주제, 청중, 핵심 포인트, 구조, 톤                 │  │
│                                                                  │
│  Step 3: 콘텐츠 작성 ────────────────────────────────────────┐  │
│          └─ 아웃라인 기반 본문 작성                           │  │
│          └─ 원본 대비 1.5배 확장                              │  │
│          └─ HTML 형식 출력                                    │  │
│                                                                  │
│  Step 4: SEO 최적화 ─────────────────────────────────────────┐  │
│          └─ meta_title, meta_description 생성                 │  │
│          └─ 키워드, 카테고리, 태그 추천                       │  │
│                                                                  │
│  Step 5: 자체 비평 ──────────────────────────────────────────┐  │
│          └─ 품질 점수 평가 (1-10)                             │  │
│          └─ 점수 < 7이면 개선 후 재평가 (최대 2회)            │  │
│                                                                  │
│  Step 6: 이미지 생성 ────────────────────────────────────────┐  │
│          └─ Gemini Imagen으로 대표 이미지 생성                │  │
│          └─ R2에 임시 저장                                    │  │
│          └─ options.generate_images=false면 스킵              │  │
│                                                                  │
│  Step 7: 웹훅 전송 ──────────────────────────────────────────┐  │
│          └─ HMAC 서명 생성                                    │  │
│          └─ WordPress callback_url로 결과 전송                │  │
│          └─ 실패 시 3회 재시도                                │  │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

## 3.3 Multi-Step Prompting

### 단계별 처리

```
원본 콘텐츠 (500단어)
        │
        ▼
┌───────────────────────────────────────────────────────────────┐
│ Step 1: OUTLINE (gpt-4o-mini, ~$0.001)                       │
│                                                               │
│ 입력: 추출된 원본 콘텐츠                                       │
│ 출력: {                                                       │
│   "main_topic": "AI의 미래",                                  │
│   "target_audience": "기술 관심자",                            │
│   "key_points": ["현재 상태", "발전 방향", "사회적 영향"],       │
│   "structure": ["intro", "body1", "body2", "body3", "conclusion"],
│   "tone": "informative",                                      │
│   "word_count_target": 750                                    │
│ }                                                             │
└───────────────────────────────────────────────────────────────┘
        │
        ▼
┌───────────────────────────────────────────────────────────────┐
│ Step 2: CONTENT (gpt-4o, ~$0.03)                             │
│                                                               │
│ 입력: 아웃라인 + 원본 참조                                     │
│ 출력: 750단어 HTML 콘텐츠                                      │
│                                                               │
│ <h2>AI의 미래: 우리가 알아야 할 것들</h2>                      │
│ <p>인공지능 기술은 급속도로 발전하고 있습니다...</p>            │
│ <h3>현재의 AI 기술</h3>                                       │
│ <p>...</p>                                                    │
└───────────────────────────────────────────────────────────────┘
        │
        ▼
┌───────────────────────────────────────────────────────────────┐
│ Step 3: SEO (gpt-4o-mini, ~$0.001)                           │
│                                                               │
│ 입력: 생성된 콘텐츠                                            │
│ 출력: {                                                       │
│   "meta_title": "AI의 미래: 2025년 전망 | 완벽 가이드",        │
│   "meta_description": "인공지능 기술의 현재와 미래를...",       │
│   "keywords": ["AI", "인공지능", "기술 트렌드"],               │
│   "category_suggestion": "Technology",                        │
│   "tags": ["AI", "미래기술", "트렌드"]                         │
│ }                                                             │
└───────────────────────────────────────────────────────────────┘
        │
        ▼
┌───────────────────────────────────────────────────────────────┐
│ Step 4: CRITIQUE (gpt-4o-mini, ~$0.002)                      │
│                                                               │
│ 입력: 생성된 콘텐츠 + SEO 메타데이터                           │
│ 출력: {                                                       │
│   "overall_score": 8.5,                                       │
│   "criteria_scores": {                                        │
│     "accuracy": 9, "readability": 8,                          │
│     "seo_optimization": 8, "engagement": 9                    │
│   },                                                          │
│   "passed": true,                                             │
│   "suggestions": []                                           │
│ }                                                             │
│                                                               │
│ ※ score < 7이면 suggestions 반영하여 Step 2부터 재실행        │
└───────────────────────────────────────────────────────────────┘
```

### 비용 분석

| 단계 | 모델 | 예상 토큰 | 비용 |
|------|------|----------|------|
| Outline | gpt-4o-mini | ~500 | $0.001 |
| Content | gpt-4o | ~2,000 | $0.030 |
| SEO | gpt-4o-mini | ~300 | $0.001 |
| Critique | gpt-4o-mini | ~400 | $0.001 |
| **총합** | - | ~3,200 | **~$0.033/글** |

## 3.4 보안 메커니즘

### 인증 방식

| 방향 | 방식 | 헤더 |
|------|------|------|
| WordPress → Worker | Bearer Token | `Authorization: Bearer {WORKER_SECRET}` |
| Worker → WordPress (Webhook) | HMAC-SHA256 | `X-AICR-Signature`, `X-AICR-Timestamp` |
| Worker → WordPress (REST) | API Key | `X-AICR-API-Key` |

### HMAC 서명 알고리즘

```typescript
// 서명 생성
const timestamp = Math.floor(Date.now() / 1000);
const message = `${timestamp}.${JSON.stringify(payload)}`;
const signature = HMAC_SHA256(message, HMAC_SECRET);

// 검증 (WordPress)
// 1. 타임스탬프 검증 (5분 이내)
// 2. 서명 비교 (상수 시간)
```

### 보안 권장사항

- WORKER_SECRET, HMAC_SECRET: 최소 32자 랜덤 문자열
- API Token 최소 권한 원칙
- Production 환경에서 LOG_LEVEL=warn
- 정기적인 Secret 로테이션

---

# Part 4: 테스트 자동화

## 4.1 테스트 환경 구성

### 테스트 스택

| 도구 | 용도 |
|------|------|
| **Vitest** | 테스트 러너 |
| **@cloudflare/vitest-pool-workers** | Workers 런타임 시뮬레이션 |
| **@vitest/coverage-v8** | 코드 커버리지 |

### 설정 파일: vitest.config.ts

```typescript
import { defineWorkersConfig } from '@cloudflare/vitest-pool-workers/config';

export default defineWorkersConfig({
  test: {
    poolOptions: {
      workers: {
        wrangler: { configPath: './wrangler.toml' },
        miniflare: {
          kvNamespaces: ['CONFIG_KV', 'LOCK_KV'],
          d1Databases: ['DB'],
          r2Buckets: ['IMAGES'],
          bindings: {
            ENVIRONMENT: 'test',
            WORKER_SECRET: 'test-worker-secret',
            // ... 기타 바인딩
          },
        },
      },
    },
    globals: true,
    setupFiles: ['./tests/setup.ts'],
    include: ['tests/**/*.test.ts'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'lcov', 'html'],
      include: ['src/**/*.ts'],
      thresholds: {
        statements: 70,
        branches: 60,
        functions: 70,
        lines: 70,
      },
    },
    testTimeout: 30000,
  },
});
```

## 4.2 테스트 구조

```
tests/
├── setup.ts                      # 전역 설정, mock 팩토리
├── handlers/                     # API 핸들러 테스트
│   ├── rewrite.test.ts          # /api/rewrite 테스트
│   └── status.test.ts           # /api/status 테스트
├── services/                     # 서비스 레이어 테스트
│   ├── ai.test.ts               # AI API 통합 테스트
│   └── wordpress.test.ts        # WordPress API 테스트
├── utils/                        # 유틸리티 테스트
│   └── auth.test.ts             # HMAC, Bearer 인증 테스트
└── workflows/                    # Workflow 통합 테스트
    ├── MasterWorkflow.test.ts   # 마스터 워크플로우 테스트
    └── ItemWorkflow.test.ts     # 아이템 워크플로우 테스트
```

### 테스트 유틸리티 (tests/setup.ts)

```typescript
// Mock 환경 팩토리
export const createMockEnv = (overrides = {}) => ({
  ENVIRONMENT: 'test',
  WORKER_SECRET: 'test-worker-secret',
  CONFIG_KV: {
    get: vi.fn().mockResolvedValue(null),
    put: vi.fn().mockResolvedValue(undefined),
  },
  DB: {
    prepare: vi.fn().mockReturnValue({
      bind: vi.fn().mockReturnValue({
        run: vi.fn().mockResolvedValue({ success: true }),
        first: vi.fn().mockResolvedValue(null),
      }),
    }),
  },
  ITEM_WORKFLOW: {
    create: vi.fn().mockResolvedValue({ id: 'mock-workflow-id' }),
  },
  ...overrides,
});

// 테스트 데이터 팩토리
export const createMockFeed = (overrides = {}) => ({
  id: 1,
  name: 'Test Feed',
  url: 'http://example.com/rss',
  is_active: true,
  auto_rewrite: true,
  ...overrides,
});
```

## 4.3 테스트 실행

### 기본 명령어

```bash
# 모든 테스트 실행
npm test

# 감시 모드
npm run test:watch

# 커버리지 포함
npm run test:coverage

# UI 모드 (브라우저)
npm run test:ui
```

### 특정 테스트 실행

```bash
# 특정 파일
npx vitest run tests/handlers/rewrite.test.ts

# 특정 패턴
npx vitest run --grep "should accept valid"

# 특정 디렉토리
npx vitest run tests/services/
```

### 커버리지 리포트

```bash
npm run test:coverage

# 결과 확인
# - 터미널: 텍스트 요약
# - coverage/lcov-report/index.html: HTML 리포트
# - coverage/lcov.info: CI 통합용
```

## 4.4 테스트 작성 가이드

### 핸들러 테스트 예시

```typescript
describe('Rewrite Handler', () => {
  let mockEnv: Env;

  beforeEach(() => {
    mockEnv = createMockEnv();
  });

  it('should accept valid rewrite request', async () => {
    const req = new Request('http://localhost/api/rewrite', {
      method: 'POST',
      headers: {
        'Authorization': 'Bearer test-worker-secret',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        task_id: 'test-123',
        callback_url: 'http://localhost/webhook',
        payload: { source_url: 'http://example.com' },
      }),
    });

    const res = await handleRewrite(req, mockEnv);

    expect(res.status).toBe(202);
    expect(await res.json()).toMatchObject({
      success: true,
      data: { task_id: 'test-123' },
    });
  });

  it('should reject unauthorized request', async () => {
    const req = new Request('http://localhost/api/rewrite', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({}),
    });

    const res = await handleRewrite(req, mockEnv);

    expect(res.status).toBe(401);
  });
});
```

### 서비스 테스트 예시

```typescript
describe('AIService', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('should call OpenAI API correctly', async () => {
    global.fetch = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        choices: [{ message: { content: 'Response' } }],
        usage: { total_tokens: 100 },
      }),
    });

    const aiService = new AIService(createMockEnv());
    const result = await aiService.complete({
      provider: 'chatgpt',
      messages: [{ role: 'user', content: 'Hello' }],
    });

    expect(result.success).toBe(true);
    expect(result.content).toBe('Response');
  });
});
```

---

# Part 5: CI/CD 파이프라인

## 5.1 GitHub Actions 개요

### 파이프라인 흐름

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│    Push     │ ──▶ │    Lint     │ ──▶ │    Test     │ ──▶ │   Deploy    │
│   (PR/Main) │     │  TypeCheck  │     │  Coverage   │     │ (Staging/   │
│             │     │             │     │             │     │  Production)│
└─────────────┘     └─────────────┘     └─────────────┘     └─────────────┘
```

### 트리거 조건

| 이벤트 | 조건 | 동작 |
|--------|------|------|
| Push to `staging` | cloudflare-worker/** 변경 | Lint → Test → Deploy Staging |
| Push to `main` | cloudflare-worker/** 변경 | Lint → Test → Deploy Production |
| Pull Request to `main` | cloudflare-worker/** 변경 | Lint → Test (배포 없음) |
| Manual dispatch | 환경 선택 | 선택한 환경에 배포 |

## 5.2 파이프라인 구성

### 파일: .github/workflows/deploy.yml

```yaml
name: Deploy Worker

on:
  push:
    branches: [main, staging]
    paths: ['cloudflare-worker/**']
  pull_request:
    branches: [main]
    paths: ['cloudflare-worker/**']
  workflow_dispatch:
    inputs:
      environment:
        description: 'Deployment environment'
        required: true
        default: 'staging'
        type: choice
        options: [staging, production]

defaults:
  run:
    working-directory: cloudflare-worker

env:
  NODE_VERSION: '20'

jobs:
  # ─────────────────────────────────────────────
  # Job 1: Lint & Type Check
  # ─────────────────────────────────────────────
  lint:
    name: Lint & Type Check
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: ${{ env.NODE_VERSION }}
          cache: 'npm'
          cache-dependency-path: cloudflare-worker/package-lock.json
      - run: npm ci
      - run: npm run lint
      - run: npm run typecheck

  # ─────────────────────────────────────────────
  # Job 2: Unit Tests
  # ─────────────────────────────────────────────
  test:
    name: Unit Tests
    runs-on: ubuntu-latest
    needs: lint
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: ${{ env.NODE_VERSION }}
          cache: 'npm'
          cache-dependency-path: cloudflare-worker/package-lock.json
      - run: npm ci
      - run: npm run test:coverage
      - uses: codecov/codecov-action@v4
        with:
          file: ./cloudflare-worker/coverage/lcov.info
        continue-on-error: true

  # ─────────────────────────────────────────────
  # Job 3: Deploy to Staging
  # ─────────────────────────────────────────────
  deploy-staging:
    name: Deploy to Staging
    runs-on: ubuntu-latest
    needs: test
    if: github.ref == 'refs/heads/staging' || (github.event_name == 'workflow_dispatch' && github.event.inputs.environment == 'staging')
    environment:
      name: staging
      url: https://aicr-worker-staging.${{ secrets.CF_ACCOUNT_SUBDOMAIN }}.workers.dev
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: ${{ env.NODE_VERSION }}
          cache: 'npm'
          cache-dependency-path: cloudflare-worker/package-lock.json
      - run: npm ci
      - run: npm run deploy:staging
        env:
          CLOUDFLARE_API_TOKEN: ${{ secrets.CF_API_TOKEN }}
          CLOUDFLARE_ACCOUNT_ID: ${{ secrets.CF_ACCOUNT_ID }}
      - name: Health Check
        run: |
          sleep 10
          curl -sf https://aicr-worker-staging.${{ secrets.CF_ACCOUNT_SUBDOMAIN }}.workers.dev/api/health

  # ─────────────────────────────────────────────
  # Job 4: Deploy to Production
  # ─────────────────────────────────────────────
  deploy-production:
    name: Deploy to Production
    runs-on: ubuntu-latest
    needs: test
    if: github.ref == 'refs/heads/main' || (github.event_name == 'workflow_dispatch' && github.event.inputs.environment == 'production')
    environment:
      name: production
      url: https://aicr-worker.${{ secrets.CF_ACCOUNT_SUBDOMAIN }}.workers.dev
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: ${{ env.NODE_VERSION }}
          cache: 'npm'
          cache-dependency-path: cloudflare-worker/package-lock.json
      - run: npm ci
      - run: npm run deploy:production
        env:
          CLOUDFLARE_API_TOKEN: ${{ secrets.CF_API_TOKEN }}
          CLOUDFLARE_ACCOUNT_ID: ${{ secrets.CF_ACCOUNT_ID }}
      - name: Health Check
        run: |
          sleep 10
          curl -sf https://aicr-worker.${{ secrets.CF_ACCOUNT_SUBDOMAIN }}.workers.dev/api/health
```

## 5.3 GitHub Secrets 설정

### 필수 Secrets

| Secret Name | 설명 | 획득 방법 |
|-------------|------|-----------|
| `CF_API_TOKEN` | Cloudflare API Token | Cloudflare Dashboard → API Tokens |
| `CF_ACCOUNT_ID` | Cloudflare Account ID | Workers Overview 페이지 |
| `CF_ACCOUNT_SUBDOMAIN` | Workers 서브도메인 | `{subdomain}.workers.dev` |

### 설정 방법

**GitHub UI:**
1. Repository → Settings → Secrets and variables → Actions
2. "New repository secret" 클릭
3. Name, Value 입력 후 저장

**GitHub CLI:**
```bash
gh secret set CF_API_TOKEN --body "your-api-token"
gh secret set CF_ACCOUNT_ID --body "your-account-id"
gh secret set CF_ACCOUNT_SUBDOMAIN --body "your-subdomain"
```

### Environment 설정 (권장)

1. Repository → Settings → Environments
2. `staging`, `production` 환경 생성
3. Production에 Protection rules 추가:
   - Required reviewers: 1명 이상
   - Deployment branches: `main`만 허용

## 5.4 배포 전략

### 브랜치 전략

```
feature/* ──┬──▶ staging ──▶ main
fix/*     ──┤
docs/*    ──┘

feature/new-feature
    │
    ├── PR to staging
    │   └── Lint → Test → Deploy Staging
    │
    └── Merge to staging
        └── Auto deploy to staging

staging
    │
    └── PR to main
        └── Lint → Test → (Approval) → Deploy Production
```

### 롤백 절차

```bash
# 이전 배포 목록 확인
npx wrangler deployments list

# 특정 버전으로 롤백
npx wrangler rollback --version {deployment-id}
```

---

# Part 6: 운영 및 모니터링

## 6.1 로그 확인

### 실시간 로그

```bash
# 모든 로그
npx wrangler tail

# 필터링
npx wrangler tail --format=pretty --search="error"

# 환경별
npx wrangler tail --env production
```

### D1 로그 조회

```bash
# 최근 워크플로우 로그
npx wrangler d1 execute aicr-worker-db --command="
  SELECT * FROM workflow_logs
  WHERE created_at > datetime('now', '-1 hour')
  ORDER BY created_at DESC
  LIMIT 20
"

# 실패한 작업
npx wrangler d1 execute aicr-worker-db --command="
  SELECT * FROM tasks WHERE status = 'failed'
  ORDER BY updated_at DESC LIMIT 10
"
```

## 6.2 문제 해결

### 일반적인 오류

| 오류 | 원인 | 해결책 |
|------|------|--------|
| "Worker not found" | 배포 안 됨 또는 이름 불일치 | `wrangler deployments list` 확인 |
| "KV namespace not found" | KV ID 불일치 | `wrangler kv namespace list` 확인 |
| "Authentication failed" | 잘못된 토큰 | Secret 재설정 |
| "Rate limit exceeded" | AI API 제한 | 재시도 로직 또는 쿼터 증가 |
| "Webhook failed" | WordPress 접근 불가 | WORDPRESS_URL 확인 |

### 디버깅 체크리스트

```bash
# 1. Worker 상태 확인
npx wrangler deployments list

# 2. 바인딩 확인
npx wrangler kv namespace list
npx wrangler d1 list
npx wrangler r2 bucket list

# 3. Secret 확인
npx wrangler secret list

# 4. Health check
curl https://your-worker.workers.dev/api/health

# 5. 로그 확인
npx wrangler tail --format=pretty
```

## 6.3 성능 최적화

### 권장 설정

| 설정 | 개발 | Production | 설명 |
|------|------|------------|------|
| LOG_LEVEL | debug | warn | 로그 양 조절 |
| MAX_RETRIES | 3 | 3 | 실패 시 재시도 |
| RETRY_DELAY_MS | 5000 | 5000 | 재시도 간격 |
| daily_limit | 5 | 50 | 일일 처리량 |
| curation_threshold | 0.5 | 0.8 | 큐레이션 기준 |
| publish_threshold | 6 | 8 | 자동 발행 기준 |

### 비용 최적화

1. **큐레이션 활용**: 저품질 아이템 필터링으로 AI 비용 절감
2. **모델 선택**: Outline/SEO/Critique에 gpt-4o-mini 사용
3. **이미지 생성 선택적**: 필요 시에만 활성화
4. **일일 제한 설정**: 예산에 맞게 daily_limit 조정

---

# 부록

## A. 파일 구조 전체

```
cloudflare-worker/
├── .github/
│   └── workflows/
│       └── deploy.yml           # CI/CD 파이프라인
├── docs/
│   ├── README.md                # 문서 인덱스
│   ├── COMPLETE_GUIDE.md        # 통합 가이드 (이 문서)
│   ├── SETUP_GUIDE.md           # 설치 가이드
│   ├── ARCHITECTURE.md          # 아키텍처 상세
│   └── GITHUB_SECRETS.md        # GitHub Secrets 설정
├── src/
│   ├── index.ts                 # 진입점
│   ├── types/
│   │   └── index.ts             # TypeScript 타입 정의
│   ├── handlers/
│   │   ├── rewrite.ts           # /api/rewrite
│   │   ├── status.ts            # /api/status
│   │   └── config.ts            # /api/sync-config
│   ├── workflows/
│   │   ├── MasterWorkflow.ts    # 자동화 오케스트레이터
│   │   └── ItemWorkflow.ts      # 콘텐츠 처리
│   ├── services/
│   │   ├── ai.ts                # AI API 클라이언트
│   │   └── wordpress.ts         # WordPress API 클라이언트
│   └── utils/
│       └── auth.ts              # 인증 유틸리티
├── tests/
│   ├── setup.ts                 # 테스트 설정
│   ├── handlers/
│   │   ├── rewrite.test.ts
│   │   └── status.test.ts
│   ├── services/
│   │   ├── ai.test.ts
│   │   └── wordpress.test.ts
│   ├── utils/
│   │   └── auth.test.ts
│   └── workflows/
│       ├── MasterWorkflow.test.ts
│       └── ItemWorkflow.test.ts
├── .dev.vars                    # 로컬 환경 변수 (gitignore)
├── .eslintrc.cjs                # ESLint 설정
├── .gitignore
├── .prettierrc                  # Prettier 설정
├── package.json
├── schema.sql                   # D1 스키마
├── tsconfig.json
├── vitest.config.ts             # Vitest 설정
└── wrangler.toml                # Cloudflare 설정
```

## B. 명령어 요약

```bash
# 개발
npm run dev              # 로컬 서버 시작
npm run typecheck        # 타입 체크
npm run lint             # 린트
npm run lint:fix         # 린트 자동 수정
npm run format           # 코드 포맷
npm run check            # 전체 검사

# 테스트
npm test                 # 테스트 실행
npm run test:watch       # 감시 모드
npm run test:coverage    # 커버리지 포함
npm run test:ui          # UI 모드

# 배포
npm run deploy:staging   # Staging 배포
npm run deploy:production # Production 배포

# Wrangler
npx wrangler login       # 로그인
npx wrangler whoami      # 인증 확인
npx wrangler tail        # 실시간 로그
npx wrangler secret list # Secret 목록
```

## C. 체크리스트

### 초기 설정 체크리스트

- [ ] Node.js 18+ 설치
- [ ] Cloudflare 계정 생성 (Workers Paid)
- [ ] OpenAI API 키 발급
- [ ] Gemini API 키 발급 (선택)
- [ ] `npm install` 실행
- [ ] `npx wrangler login` 완료
- [ ] KV Namespace 생성 (CONFIG_KV, LOCK_KV)
- [ ] D1 Database 생성 및 스키마 적용
- [ ] R2 Bucket 생성
- [ ] wrangler.toml 리소스 ID 업데이트
- [ ] .dev.vars 파일 생성
- [ ] Secrets 설정 (staging/production)
- [ ] `npm run dev`로 로컬 테스트
- [ ] `npm test`로 테스트 통과 확인

### CI/CD 설정 체크리스트

- [ ] GitHub 저장소에 코드 푸시
- [ ] GitHub Secrets 설정 (CF_API_TOKEN, CF_ACCOUNT_ID, CF_ACCOUNT_SUBDOMAIN)
- [ ] staging 브랜치 생성
- [ ] staging 환경 생성 (GitHub Environments)
- [ ] production 환경 생성 (Protection rules 추가)
- [ ] staging 브랜치로 푸시하여 파이프라인 테스트
- [ ] main 브랜치로 머지하여 production 배포

### WordPress 연동 체크리스트

- [ ] WordPress 플러그인 설치 및 활성화
- [ ] 플러그인 설정에서 Worker URL 입력
- [ ] WORKER_SECRET 설정 (양쪽 일치)
- [ ] HMAC_SECRET 설정 (양쪽 일치)
- [ ] 수동 재작성 테스트
- [ ] 자동화 (Cron) 설정 확인

---

*최종 업데이트: 2025-02-05*
*버전: 2.0.0*
