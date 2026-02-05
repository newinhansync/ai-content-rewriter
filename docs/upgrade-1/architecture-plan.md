# AI Content Rewriter v2.0 - Cloudflare Worker 아키텍처 계획

> **버전**: v4 (Cloudflare 전용, 검증 완료)
> **작성일**: 2026-01-30
> **목적**: 공유 호스팅 환경의 타임아웃/자동화 한계를 Cloudflare 인프라로 해결

---

## 1. 문제 정의

### 문제 1: 호스팅 환경 타임아웃
- 현재 AI API 호출에 **300초(5분)** 타임아웃 설정
- 공유 호스팅은 보통 **30~60초** PHP 실행 제한
- `ignore_user_abort(true)` + `fastcgi_finish_request()`는 호스팅에 따라 미동작
- **결론**: WordPress 서버에서 직접 AI API를 호출하는 구조 자체가 문제

### 문제 2: 자동화 한계
- WP-Cron은 방문자 기반 → 트래픽 없으면 실행 안 됨
- External Cron은 호스팅에서 설정 불가능한 경우 많음
- 장시간 처리(AI 재작성 + 이미지 생성)를 Cron 한 사이클에 완료해야 함
- **결론**: WordPress 내부에서 자동화 파이프라인 전체를 운영하기 어려움

---

## 2. 해결 전략

### 핵심 아이디어

```
[기존] WordPress ──직접호출──→ AI API (타임아웃 위험)

[변경] WordPress ──작업요청──→ Cloudflare Worker ──→ AI API
       WordPress ←──결과전달──┘                      (시간 제한 없음)
```

WordPress는 **"작업 요청"과 "결과 수신"만** 담당.
실제 AI 처리는 **Cloudflare Worker**가 수행.

---

## 3. Cloudflare 플랫폼 제약 사항 (검증 완료)

> 아키텍처 설계 전 반드시 이해해야 할 Cloudflare 제한 사항.

### 3.1 실행 시간 제한

| 실행 유형 | Wall-time 제한 | CPU Time | AI 처리 적합? |
|-----------|---------------|----------|--------------|
| HTTP Request (Workers) | 무제한 (I/O 대기 포함) | 기본 30초, 최대 5분 | **적합** (수동 트리거) |
| Cron Trigger (< 1시간) | **30초** | 기본 30초 | **부적합** |
| Cron Trigger (≥ 1시간) | **15분** | 기본 30초 | 가능하나 간격 김 |
| Queue Consumer | **15분** | 기본 30초, 최대 5분 | **적합** |
| Workflows (Step 내부) | 기본 5분 (설정 가능) | - | **최적** |
| Workflows (전체) | 30분 | - | **최적** |

**핵심**: CPU Time ≠ Wall-time. AI API 호출은 대부분 **I/O 대기**이므로 CPU time은 적게 소모.

### 3.2 비용 (Workers Paid Plan: $5/월)

| 항목 | 포함량 | 초과 비용 |
|------|--------|----------|
| Requests | 1,000만/월 | $0.30/백만 |
| CPU Time | 3,000만 ms/월 | $0.02/백만 ms |
| Queues | 100만 메시지/월 | $0.40/백만 |
| KV Reads | 1,000만/월 | $0.50/백만 |
| D1 (SQLite) | 5GB 저장, 500만 읽기/월 | 종량 |
| Workflows | Paid 플랜에 포함 | 동시 4,000 인스턴스 |

### 3.3 Cloudflare 제품 맵

```
Cloudflare Developer Platform
├── Workers        → HTTP 요청 처리 (API 서버)
├── Cron Triggers  → 주기적 트리거 (30초 이내 작업만)
├── Queues         → 메시지 큐 (Consumer 15분 실행)
├── Workflows      → 장시간 다단계 처리 (자동 재시도, 상태 보존)
├── KV             → Key-Value 저장소 (설정, 토큰)
├── D1             → SQLite DB (작업 로그, 상태)
└── R2             → Object Storage (이미지 임시 저장)
```

---

## 4. 아키텍처 설계

### 4.1 전체 구조도

```
┌──────────────────────────────────────────────────────────┐
│                  WordPress Plugin (v2.0)                   │
│  ┌──────────┐  ┌──────────────┐  ┌──────────────────┐    │
│  │ Admin UI │  │ REST API     │  │ Webhook Receiver │    │
│  │ (기존)    │  │ (데이터 제공) │  │ (결과 수신)       │    │
│  └──────────┘  └──────────────┘  └──────────────────┘    │
│        │              ▲                   ▲               │
└────────│──────────────│───────────────────│───────────────┘
         │              │                   │
         ▼              │                   │
┌────────────────────────────────────────────────────────────┐
│                   Cloudflare Platform                       │
│                                                            │
│  ┌────────────┐     ┌──────────────────────────────────┐  │
│  │   Cron     │────→│         Workflows                │  │
│  │  Triggers  │     │  ┌──────┐ ┌──────┐ ┌──────────┐ │  │
│  │ (매 1시간)  │     │  │Step1 │→│Step2 │→│Step3     │ │  │
│  └────────────┘     │  │RSS   │ │AI    │ │Webhook   │ │  │
│                     │  │수집   │ │재작성 │ │전송      │ │  │
│  ┌────────────┐     │  └──────┘ └──────┘ └──────────┘ │  │
│  │  Workers   │     └──────────────────────────────────┘  │
│  │ (HTTP API) │                    │                      │
│  └────────────┘                    ▼                      │
│        │              ┌─────────────────────┐             │
│        │              │   AI APIs           │             │
│        ▼              │   (OpenAI / Gemini) │             │
│  ┌──────────┐         └─────────────────────┘             │
│  │ KV / D1  │                                             │
│  │ (설정/로그)│                                             │
│  └──────────┘                                             │
└────────────────────────────────────────────────────────────┘
```

### 4.2 컴포넌트별 역할

#### A. WordPress Plugin (경량 클라이언트)

| 컴포넌트 | 역할 |
|----------|------|
| Admin UI | 기존 관리 화면 유지 (설정, 피드 관리, 히스토리) |
| REST API | Worker가 호출하는 인증된 엔드포인트 (피드/아이템 조회) |
| Webhook Receiver | Worker로부터 완료 결과 수신 → `wp_insert_post()` |
| Task Dispatcher | 수동 재작성 요청 시 Worker에 HTTP POST (1~2초) |

#### B. Cloudflare Worker (HTTP API)

| 컴포넌트 | 역할 |
|----------|------|
| `fetch` handler | WordPress로부터 수동 작업 요청 수신 |
| AI Processor | OpenAI/Gemini API 호출, 콘텐츠 재작성 |
| Webhook Client | 처리 완료 시 WordPress에 결과 POST |

#### C. Cloudflare Workflows (자동화 파이프라인)

| 컴포넌트 | 역할 |
|----------|------|
| `scheduled` handler | Cron Trigger로 Workflow 인스턴스 생성 |
| Step 1: RSS 수집 | WordPress REST API로 피드 목록 조회 → RSS 파싱 → 새 아이템 저장 |
| Step 2: AI 재작성 | 대기 아이템 조회 → AI API 호출 → 콘텐츠 생성 |
| Step 3: 이미지 생성 | AI 이미지 생성 (선택적) |
| Step 4: 게시 | Webhook으로 WordPress에 결과 전달 |

#### D. Cloudflare Storage

| 저장소 | 용도 |
|--------|------|
| KV | Worker 설정 (WordPress URL, API Key, AI Key) |
| D1 | 작업 로그, 처리 이력 |
| R2 | 생성된 이미지 임시 저장 (WordPress 업로드 전) |

---

## 5. 통신 프로토콜

### 5.1 수동 트리거: WordPress → Worker

사용자가 관리자 UI에서 "재작성" 버튼 클릭 시:

```
POST https://{worker-name}.{account}.workers.dev/api/rewrite
Authorization: Bearer {SHARED_SECRET}
Content-Type: application/json

{
  "task_id": "uuid-xxx",
  "task_type": "rewrite",
  "callback_url": "https://myblog.com/wp-json/aicr/v1/webhook",
  "callback_secret": "{HMAC_SECRET}",
  "payload": {
    "source_url": "https://...",
    "source_content": "...",
    "language": "ko",
    "ai_provider": "chatgpt",
    "ai_model": "gpt-4o",
    "prompt_template": "..."
  }
}
```

**WordPress 측 소요 시간: 1~2초** (POST 전송 후 즉시 반환)

Worker 응답:
```json
{ "accepted": true, "task_id": "uuid-xxx" }
```

### 5.2 자동화: Cron → Workflow

```
[Cron Trigger (매 1시간)]
    │
    ▼
[Workflow 인스턴스 생성]
    │
    ├── Step 1: WordPress REST API GET /feeds → 활성 피드 목록
    │           각 피드 URL fetch → RSS 파싱 → 새 아이템 필터링
    │           WordPress REST API POST /feed-items → 저장
    │
    ├── Step 2: WordPress REST API GET /feed-items/pending
    │           각 아이템에 대해 AI API 호출 → 재작성
    │           (Step 간 상태 자동 보존 - 실패 시 해당 Step부터 재시도)
    │
    ├── Step 3: (선택) AI 이미지 생성 → R2에 임시 저장
    │
    └── Step 4: WordPress REST API POST /webhook → 게시글 생성
                아이템별로 반복
```

### 5.3 결과 전달: Worker → WordPress

```
POST https://myblog.com/wp-json/aicr/v1/webhook
X-AICR-Signature: hmac_sha256(body, HMAC_SECRET)
X-AICR-Timestamp: 1706600000
Content-Type: application/json

{
  "task_id": "uuid-xxx",
  "status": "completed",
  "result": {
    "title": "재작성된 제목",
    "content": "<p>본문...</p>",
    "excerpt": "요약",
    "category_suggestion": "기술",
    "tags": ["AI", "블로그"],
    "meta_title": "SEO 제목",
    "meta_description": "SEO 설명",
    "featured_image_url": "https://r2.example.com/image.png"
  }
}
```

### 5.4 보안

| 레이어 | 방법 |
|--------|------|
| Worker 인증 | Bearer Token (WordPress → Worker) |
| Webhook 인증 | HMAC-SHA256 서명 (Worker → WordPress) |
| REST API 인증 | Application Password 또는 API Key (Worker → WP REST API) |
| 전송 암호화 | HTTPS 필수 (Workers는 기본 HTTPS) |
| Replay 방지 | X-AICR-Timestamp (5분 이내만 허용) |

---

## 6. WordPress REST API 엔드포인트

Worker가 WordPress 데이터에 접근하기 위한 엔드포인트:

| 엔드포인트 | 메서드 | 용도 | 인증 |
|-----------|--------|------|------|
| `/wp-json/aicr/v1/feeds` | GET | 활성 피드 목록 | API Key |
| `/wp-json/aicr/v1/feed-items/pending` | GET | 대기 중 아이템 | API Key |
| `/wp-json/aicr/v1/feed-items/{id}/status` | PATCH | 아이템 상태 변경 | API Key |
| `/wp-json/aicr/v1/webhook` | POST | 처리 결과 수신 | HMAC |
| `/wp-json/aicr/v1/media` | POST | 이미지 업로드 | API Key |
| `/wp-json/aicr/v1/config` | GET | AI 설정 조회 (모델, 프롬프트) | API Key |
| `/wp-json/aicr/v1/health` | GET | 연결 확인 (Worker 헬스체크) | API Key |

---

## 7. Cloudflare Worker 프로젝트 구조

```
aicr-worker/
├── wrangler.toml              # Cloudflare 설정 (Cron, Bindings)
├── package.json
├── tsconfig.json
├── src/
│   ├── index.ts               # Entry point (fetch + scheduled handlers)
│   ├── workflows/
│   │   └── auto-rewrite.ts    # Workflow 정의 (RSS → AI → Publish)
│   ├── handlers/
│   │   ├── rewrite.ts         # 수동 재작성 요청 처리
│   │   └── health.ts          # 헬스체크
│   ├── services/
│   │   ├── ai/
│   │   │   ├── openai.ts      # OpenAI API 클라이언트
│   │   │   └── gemini.ts      # Gemini API 클라이언트
│   │   ├── rss/
│   │   │   └── parser.ts      # RSS 피드 파싱
│   │   ├── wordpress/
│   │   │   └── client.ts      # WordPress REST API 클라이언트
│   │   └── webhook/
│   │       └── sender.ts      # Webhook 결과 전송 + HMAC 서명
│   ├── config/
│   │   └── settings.ts        # KV에서 설정 로드
│   └── utils/
│       ├── crypto.ts          # HMAC, 토큰 관리
│       └── logger.ts          # D1 로깅
└── test/
    └── ...
```

### wrangler.toml 예시

```toml
name = "aicr-worker"
main = "src/index.ts"
compatibility_date = "2026-01-01"

# Cron Triggers
[triggers]
crons = ["0 * * * *"]   # 매 1시간 (wall-time 15분 확보)

# KV Namespace (설정 저장)
[[kv_namespaces]]
binding = "AICR_CONFIG"
id = "xxx"

# D1 Database (로그)
[[d1_databases]]
binding = "AICR_DB"
database_name = "aicr-logs"
database_id = "xxx"

# R2 Bucket (이미지 임시)
[[r2_buckets]]
binding = "AICR_IMAGES"
bucket_name = "aicr-images"

# Workflows
[[workflows]]
name = "auto-rewrite-workflow"
binding = "AUTO_REWRITE"
class_name = "AutoRewriteWorkflow"

# 환경 변수 (Secrets는 wrangler secret으로 별도 설정)
[vars]
ENVIRONMENT = "production"
```

---

## 8. 마이그레이션 전략

### Phase 1: 하이브리드 모드

```
WordPress Plugin v2.0 설정 화면:

┌─────────────────────────────────────────┐
│  처리 모드                               │
│  ○ Local (기존 방식 - 서버에서 직접 처리)  │
│  ● Cloudflare Worker (외부 위임)          │
│                                          │
│  Worker URL: [________________________]  │
│  API Secret: [________________________]  │
│  [연결 테스트]                            │
│                                          │
│  자동화: ☑ Worker 스케줄러 사용           │
│         (WP-Cron 대신 Worker가 관리)      │
└─────────────────────────────────────────┘
```

- 기존 사용자: Local 모드로 변경 없이 사용
- 호스팅 사용자: Cloudflare Worker 모드 선택

### Phase 2: Worker 배포 자동화

```bash
# 사용자가 실행할 배포 스크립트
npx wrangler deploy
npx wrangler secret put WORDPRESS_URL
npx wrangler secret put WORDPRESS_API_KEY
npx wrangler secret put OPENAI_API_KEY
npx wrangler secret put HMAC_SECRET
```

### Phase 3: 완전 자동화

- Cron Trigger 활성화 → Workflow가 주기적으로 RSS 수집 + AI 재작성
- WordPress는 결과 수신만 수행
- WordPress 대시보드에서 Worker 상태 모니터링

---

## 9. 자동화 파이프라인 상세

### 완전 자동화 흐름 (Workflow)

```
Cron Trigger (매 1시간)
    │
    ▼
┌─ Workflow Instance ─────────────────────────────────┐
│                                                      │
│  [Step 1] RSS 수집                                   │
│  ├── WP REST API: GET /feeds (활성 피드)              │
│  ├── 각 피드 URL fetch + RSS 파싱                    │
│  ├── 기존 아이템과 비교 (중복 제거)                    │
│  ├── WP REST API: POST /feed-items (새 아이템 저장)   │
│  └── 결과: { new_items: [...] }                      │
│       ※ 실패 시 자동 재시도 (Workflows 내장)           │
│                                                      │
│  [Step 2] AI 콘텐츠 재작성 (아이템별 반복)             │
│  ├── WP REST API: GET /feed-items/pending            │
│  ├── WP REST API: GET /config (프롬프트, AI 설정)     │
│  ├── AI API 호출 (OpenAI 또는 Gemini)                │
│  │   ※ Wall-time: 수 분 소요 가능 (Step 내 충분)      │
│  └── 결과: { rewritten_content: {...} }              │
│       ※ AI API 실패 시 해당 Step만 재시도             │
│                                                      │
│  [Step 3] 이미지 생성 (선택적)                        │
│  ├── AI 이미지 생성 API 호출                          │
│  ├── R2에 이미지 저장                                 │
│  └── 결과: { image_url: "..." }                      │
│                                                      │
│  [Step 4] WordPress 게시                              │
│  ├── WP REST API: POST /webhook (콘텐츠 + 이미지)     │
│  ├── HMAC 서명 포함                                   │
│  └── 아이템 상태 → "published"                        │
│                                                      │
└──────────────────────────────────────────────────────┘
```

### Workflows 장점 (vs 단순 Worker)

| 특성 | 단순 Worker | Workflows |
|------|------------|-----------|
| 실패 시 | 전체 재실행 | 실패한 Step부터 재시도 |
| 상태 보존 | 없음 (stateless) | Step 간 자동 보존 |
| 장시간 처리 | Cron 30초 제한 | 전체 30분, Step별 5분+ |
| 가시성 | 로그만 | 인스턴스별 상태 조회 가능 |
| 동시성 | 제한 없음 | Paid 4,000 인스턴스 |

---

## 10. 구현 범위

### A. WordPress Plugin 수정

| 항목 | 설명 | 신규/수정 |
|------|------|----------|
| REST API Controller | Worker용 엔드포인트 6개 | **신규** |
| Webhook Receiver | 결과 수신 + HMAC 검증 + 게시글 생성 | **신규** |
| Task Dispatcher | Worker에 작업 전송 (HTTP POST) | **신규** |
| Settings: Worker 섹션 | URL, Secret, 모드 선택 UI | **수정** |
| ProcessingMode | Local/Cloudflare 분기 로직 | **신규** |

### B. Cloudflare Worker 신규 개발

| 항목 | 설명 |
|------|------|
| HTTP API (fetch handler) | 수동 재작성 요청 수신 |
| Workflow (scheduled handler) | RSS 수집 → AI 재작성 → 게시 파이프라인 |
| AI Service (OpenAI) | OpenAI API 호출 (기존 PHP 로직 → TypeScript 이식) |
| AI Service (Gemini) | Gemini API 호출 (기존 PHP 로직 → TypeScript 이식) |
| RSS Parser | RSS/Atom 피드 파싱 |
| WordPress Client | WP REST API 호출 (인증, 데이터 조회/저장) |
| Webhook Sender | 결과 전달 + HMAC 서명 |
| Config Manager | KV에서 설정 로드/저장 |
| Logger | D1에 작업 로그 기록 |

---

## 11. 검증 결과

### 문제 해결 검증

| 문제 | 해결 여부 | 근거 |
|------|----------|------|
| 타임아웃 | ✅ 해결 | WordPress는 HTTP POST만 (1~2초). AI 처리는 Worker에서 수행 |
| 자동화 | ✅ 해결 | Cron Trigger(1시간) → Workflow가 독립 실행. WP-Cron 불필요 |
| 기존 호환 | ✅ 유지 | Local 모드로 기존 방식 계속 사용 가능 |

### 기술 실현성 검증

| 항목 | 검증 결과 |
|------|----------|
| Cron < 1시간 | ⚠️ 30초 wall-time → **직접 AI 처리 불가** → Workflow로 우회 |
| Cron ≥ 1시간 | ✅ 15분 wall-time → Workflow 트리거로 사용 |
| Workflow Step | ✅ 5분+ wall-time → AI API 호출에 충분 |
| Workflow 전체 | ✅ 30분 → RSS + AI + 이미지 + 게시 파이프라인에 충분 |
| Queue Consumer | ✅ 15분 wall-time → 대안으로 사용 가능 |
| CPU Time | ✅ AI API는 I/O 대기 위주 → CPU time 적게 소모 |
| WP REST API | ✅ WP 4.7+ 기본 지원, `register_rest_route()` |
| HMAC 인증 | ✅ 업계 표준, PHP `hash_hmac()` + JS `crypto.subtle` |

### 운영 검증

| 항목 | 검증 결과 |
|------|----------|
| 비용 | ✅ $5/월 (Workers Paid). 일반 블로그 사용량으로 초과 비용 거의 없음 |
| 장애 대응 | ✅ Worker 다운 시 Local 모드 폴백. Workflow 자동 재시도 내장 |
| 배포 | ✅ `npx wrangler deploy` 한 줄로 배포 |
| 모니터링 | ✅ Cloudflare Dashboard + WordPress 대시보드 연동 |
| 확장성 | ✅ 파이프라인 Step 추가로 기능 확장 (SNS 배포, 포맷 변환 등) |
| 보안 | ⚠️ 일부 호스팅에서 Authorization 헤더 차단 → .htaccess 설정 필요 |

---

## 12. 리스크 및 대응

| 리스크 | 확률 | 대응 |
|--------|------|------|
| Cloudflare 장애 | 낮 | Local 모드 폴백, Workflow 자동 재시도 |
| Webhook 전달 실패 | 중 | Workflow Step 재시도 (자동), D1에 실패 로그 |
| 호스팅 REST API 차단 | 낮 | 대부분 허용. 차단 시 AJAX 엔드포인트 폴백 |
| AI API 응답 지연 (5분+) | 낮 | Workflow Step timeout 설정 증가 |
| Cloudflare 요금 변경 | 낮 | 현재 $5/월 고정. 사용량 모니터링 |

---

## 13. 구현 순서

```
Sprint 1: 기반 연결
├── WordPress: REST API 엔드포인트 구현 (feeds, feed-items, webhook, health)
├── WordPress: Webhook Receiver + HMAC 검증
├── Worker: 기본 구조 (fetch handler + WordPress Client)
├── Worker: 수동 재작성 요청 처리 (AI 호출 → Webhook 전달)
└── 연동 E2E 테스트

Sprint 2: 자동화 파이프라인
├── Worker: Workflow 정의 (auto-rewrite-workflow)
├── Worker: RSS Parser 구현
├── Worker: Cron Trigger → Workflow 트리거
├── WordPress: 자동화 상태 대시보드
└── 파이프라인 E2E 테스트

Sprint 3: 이미지 + 안정화
├── Worker: AI 이미지 생성 Step
├── Worker: R2 이미지 저장 + WordPress 업로드
├── WordPress: 처리 모드 스위치 UI
├── 에러 핸들링 + 재시도 로직 보강
└── 배포 가이드 문서화
```

---

## 부록 A: 기존 코드 영향 분석

### 변경 없음 (유지)
- `Admin/` - 관리자 UI 전체 (설정 페이지에 Worker 섹션만 추가)
- `Database/` - DB 스키마
- `AI/` - AI 어댑터 (Local 모드에서 계속 사용)
- `RSS/` - RSS 피드 관리 (Local 모드에서 계속 사용)
- `Content/` - 콘텐츠 추출 (Local 모드에서 계속 사용)

### 신규 추가
- `src/API/RestController.php` - REST API 엔드포인트 등록
- `src/API/WebhookReceiver.php` - Webhook 수신 + HMAC 검증
- `src/Worker/TaskDispatcher.php` - Worker에 작업 전송
- `src/Worker/WorkerConfig.php` - Worker URL, Secret 관리
- `src/Core/ProcessingMode.php` - Local/Cloudflare 모드 분기

### 수정
- `src/Core/Plugin.php` - REST API 라우트 등록, ProcessingMode 초기화
- `src/Admin/SettingsPage.php` - Worker 설정 섹션 추가
- `src/Schedule/FeedScheduler.php` - Cloudflare 모드일 때 Worker에 위임

## 부록 B: AI 로직 이식 범위

Worker에 이식해야 할 기존 PHP 로직:

| PHP 원본 | TypeScript 대응 | 이식 내용 |
|----------|----------------|----------|
| `AbstractAIAdapter::http_post()` | `openai.ts` / `gemini.ts` | HTTP 호출 + 응답 파싱 |
| `PromptManager::build_prompt()` | Workflow Step 내 | 프롬프트 조립 (템플릿 + 변수) |
| `ContentExtractor::extract_from_url()` | `parser.ts` | URL → 본문 텍스트 추출 |
| `FeedParser` 관련 | `parser.ts` | RSS/Atom XML 파싱 |
| JSON 응답 파싱 | Workflow Step 내 | AI 응답 → 구조화 데이터 |

**이식하지 않는 것**: WordPress 전용 로직 (`wp_insert_post`, nonce, 권한 체크 등)은 Plugin에 유지.
