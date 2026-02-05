# AI Content Rewriter Worker - 설정 가이드

> 이 문서는 Cloudflare Worker의 설치, 설정, 배포에 대한 완전한 가이드입니다.

## 목차

1. [사전 요구사항](#사전-요구사항)
2. [Cloudflare 계정 설정](#cloudflare-계정-설정)
3. [로컬 개발 환경 설정](#로컬-개발-환경-설정)
4. [Cloudflare 리소스 생성](#cloudflare-리소스-생성)
5. [Secrets 설정](#secrets-설정)
6. [데이터베이스 초기화](#데이터베이스-초기화)
7. [로컬 개발 서버 실행](#로컬-개발-서버-실행)
8. [배포](#배포)
9. [WordPress 플러그인 연동](#wordpress-플러그인-연동)
10. [문제 해결](#문제-해결)

---

## 사전 요구사항

### 필수 소프트웨어

| 소프트웨어 | 최소 버전 | 확인 명령어 |
|-----------|----------|------------|
| Node.js | 18.0.0+ | `node --version` |
| npm | 9.0.0+ | `npm --version` |
| Git | 2.0.0+ | `git --version` |

### 필수 계정

| 서비스 | 용도 | 가입 URL |
|--------|------|----------|
| Cloudflare | Worker 호스팅 | https://dash.cloudflare.com/sign-up |
| OpenAI | GPT API | https://platform.openai.com/signup |
| Google AI | Gemini API | https://aistudio.google.com/ |

### Cloudflare 유료 플랜 요구사항

> ⚠️ **중요**: 일부 기능은 Workers Paid 플랜이 필요합니다.

| 기능 | Free | Paid ($5/월) |
|------|------|--------------|
| Workers 기본 | ✅ | ✅ |
| KV Storage | ✅ (제한적) | ✅ |
| D1 Database | ✅ | ✅ |
| R2 Storage | ✅ | ✅ |
| **Workflows** | ❌ | ✅ |
| Cron Triggers | ✅ | ✅ |

**Workflows 기능은 유료 플랜에서만 사용 가능합니다.**

---

## Cloudflare 계정 설정

### 1. Cloudflare Dashboard 접속

1. https://dash.cloudflare.com 로그인
2. 좌측 메뉴에서 **Workers & Pages** 선택

### 2. Account ID 확인

Account ID는 다음 위치에서 확인할 수 있습니다:

```
Workers & Pages → Overview → 우측 사이드바 "Account ID"
```

또는 브라우저 URL에서:
```
https://dash.cloudflare.com/{ACCOUNT_ID}/workers
                           ↑ 이 부분
```

### 3. API Token 생성

1. **My Profile** (우상단 아이콘) → **API Tokens** 클릭
2. **Create Token** 클릭
3. **Edit Cloudflare Workers** 템플릿 선택
4. 또는 Custom Token 생성:

```
권한 설정:
├── Account
│   ├── Workers KV Storage: Edit
│   ├── Workers R2 Storage: Edit
│   ├── D1: Edit
│   └── Workers Scripts: Edit
└── Zone (선택사항)
    └── Workers Routes: Edit
```

5. **Continue to summary** → **Create Token**
6. 토큰을 안전한 곳에 저장 (다시 볼 수 없음)

---

## 로컬 개발 환경 설정

### 1. 프로젝트 클론 및 의존성 설치

```bash
# 프로젝트 루트로 이동
cd /path/to/wordpress

# Worker 디렉토리로 이동
cd cloudflare-worker

# 의존성 설치
npm install
```

### 2. Wrangler CLI 인증

```bash
# Cloudflare 로그인 (브라우저 열림)
npx wrangler login

# 로그인 확인
npx wrangler whoami
```

출력 예시:
```
👋 You are logged in with an OAuth Token, associated with the email user@example.com!
┌─────────────────────────────────────────────────────────────────────────────┐
│ Account Name     │ Account ID                           │
├─────────────────────────────────────────────────────────────────────────────┤
│ My Account       │ abcd1234efgh5678ijkl9012mnop3456     │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 3. 환경별 설정 파일 생성

개발 환경용 변수 파일을 생성합니다:

```bash
# .dev.vars 파일 생성 (gitignore에 포함됨)
cat > .dev.vars << 'EOF'
WORKER_SECRET=dev-worker-secret-change-in-production
HMAC_SECRET=dev-hmac-secret-change-in-production
WP_API_KEY=your-wordpress-api-key
OPENAI_API_KEY=sk-your-openai-api-key
GEMINI_API_KEY=your-gemini-api-key
WORDPRESS_URL=http://localhost:8080
EOF
```

> ⚠️ `.dev.vars` 파일은 절대 Git에 커밋하지 마세요!

---

## Cloudflare 리소스 생성

### 1. KV Namespaces 생성

```bash
# CONFIG_KV: 설정 저장용
npx wrangler kv namespace create CONFIG_KV
# 출력: { binding = "CONFIG_KV", id = "xxxxx" }

# LOCK_KV: 분산 잠금용
npx wrangler kv namespace create LOCK_KV
# 출력: { binding = "LOCK_KV", id = "yyyyy" }
```

### 2. D1 Database 생성

```bash
npx wrangler d1 create aicr-worker-db
# 출력: database_id = "zzzzz"
```

### 3. R2 Bucket 생성

```bash
npx wrangler r2 bucket create aicr-images
# 출력: Created bucket 'aicr-images'
```

### 4. wrangler.toml 업데이트

생성된 리소스 ID를 `wrangler.toml`에 반영합니다:

```toml
# wrangler.toml

name = "ai-content-rewriter-worker"
main = "src/index.ts"
compatibility_date = "2025-01-01"

# ============================================
# KV Namespaces - 위에서 생성한 ID로 교체
# ============================================
[[kv_namespaces]]
binding = "CONFIG_KV"
id = "xxxxx"  # ← 실제 ID로 교체

[[kv_namespaces]]
binding = "LOCK_KV"
id = "yyyyy"  # ← 실제 ID로 교체

# ============================================
# D1 Database
# ============================================
[[d1_databases]]
binding = "DB"
database_name = "aicr-worker-db"
database_id = "zzzzz"  # ← 실제 ID로 교체

# ============================================
# R2 Bucket
# ============================================
[[r2_buckets]]
binding = "IMAGES"
bucket_name = "aicr-images"

# ============================================
# Workflows
# ============================================
[[workflows]]
name = "master-workflow"
binding = "MASTER_WORKFLOW"
class_name = "MasterWorkflow"

[[workflows]]
name = "item-workflow"
binding = "ITEM_WORKFLOW"
class_name = "ItemWorkflow"

# ============================================
# Cron Triggers
# ============================================
[triggers]
crons = [
  "0 * * * *",   # 매시 정각: Master Workflow
  "30 * * * *"   # 매시 30분: Retry 처리
]

# ============================================
# Environment Variables
# ============================================
[vars]
ENVIRONMENT = "development"
LOG_LEVEL = "debug"
MAX_RETRIES = "3"
RETRY_DELAY_MS = "5000"
```

### 5. 환경별 설정 (선택사항)

```toml
# Staging 환경
[env.staging]
name = "aicr-worker-staging"
[env.staging.vars]
ENVIRONMENT = "staging"
LOG_LEVEL = "info"

# Production 환경
[env.production]
name = "aicr-worker"
[env.production.vars]
ENVIRONMENT = "production"
LOG_LEVEL = "warn"
```

---

## Secrets 설정

Secrets는 Cloudflare에 암호화되어 저장됩니다.

### 1. 개발 환경 (로컬)

`.dev.vars` 파일 사용 (위에서 생성함)

### 2. Staging/Production 환경

```bash
# WordPress → Worker 인증 토큰
npx wrangler secret put WORKER_SECRET
# 프롬프트에서 값 입력 (예: a7b3c9d2e8f4g1h5)

# Worker → WordPress 웹훅 서명 키
npx wrangler secret put HMAC_SECRET
# 프롬프트에서 값 입력

# WordPress REST API 인증 키
npx wrangler secret put WP_API_KEY
# 프롬프트에서 WordPress에서 생성한 API 키 입력

# OpenAI API 키
npx wrangler secret put OPENAI_API_KEY
# 프롬프트에서 sk-로 시작하는 키 입력

# Gemini API 키
npx wrangler secret put GEMINI_API_KEY
# 프롬프트에서 Gemini API 키 입력

# WordPress URL
npx wrangler secret put WORDPRESS_URL
# 프롬프트에서 https://your-site.com 입력
```

### 3. 환경별 Secrets 설정

```bash
# Staging 환경
npx wrangler secret put WORKER_SECRET --env staging

# Production 환경
npx wrangler secret put WORKER_SECRET --env production
```

### 4. Secrets 목록 확인

```bash
npx wrangler secret list
```

---

## 데이터베이스 초기화

### 1. 스키마 적용

```bash
# 로컬 D1에 스키마 적용 (개발용)
npx wrangler d1 execute aicr-worker-db --local --file=schema.sql

# 원격 D1에 스키마 적용 (staging/production)
npx wrangler d1 execute aicr-worker-db --file=schema.sql
```

### 2. 스키마 확인

```bash
# 테이블 목록 확인
npx wrangler d1 execute aicr-worker-db --command="SELECT name FROM sqlite_master WHERE type='table'"
```

예상 출력:
```
┌──────────────────────┐
│ name                 │
├──────────────────────┤
│ tasks                │
│ workflow_logs        │
│ processing_stats     │
└──────────────────────┘
```

---

## 로컬 개발 서버 실행

### 1. 개발 서버 시작

```bash
npm run dev
```

출력 예시:
```
⎔ Starting local server...
[wrangler:inf] Ready on http://localhost:8787
╭──────────────────────────────────────────────────────────────────────────────╮
│  [b] open a browser, [d] open devtools, [l] turn on local mode, [x] to exit  │
╰──────────────────────────────────────────────────────────────────────────────╯
```

### 2. Health Check 확인

```bash
curl http://localhost:8787/api/health
```

응답:
```json
{
  "success": true,
  "data": {
    "status": "healthy",
    "version": "2.0.0",
    "timestamp": "2025-02-05T12:00:00Z"
  }
}
```

### 3. API 테스트

```bash
# Rewrite 요청 테스트
curl -X POST http://localhost:8787/api/rewrite \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer dev-worker-secret-change-in-production" \
  -d '{
    "task_id": "test-123",
    "callback_url": "http://localhost:8080/wp-json/aicr/v1/webhook",
    "callback_secret": "test-secret",
    "payload": {
      "source_url": "https://example.com/article",
      "language": "ko",
      "ai_provider": "chatgpt"
    }
  }'
```

---

## 배포

### 1. 수동 배포

```bash
# Staging 배포
npm run deploy:staging

# Production 배포
npm run deploy:production
```

### 2. 배포 확인

```bash
# 배포된 Worker URL 확인
npx wrangler deployments list
```

### 3. CI/CD 자동 배포

GitHub Actions를 통한 자동 배포는 `.github/workflows/deploy.yml` 참조.

GitHub Secrets 설정:
- `CF_API_TOKEN`: Cloudflare API Token
- `CF_ACCOUNT_ID`: Cloudflare Account ID
- `CF_ACCOUNT_SUBDOMAIN`: Workers 서브도메인

자세한 내용은 `docs/GITHUB_SECRETS.md` 참조.

---

## WordPress 플러그인 연동

### 1. 플러그인 설정

WordPress 관리자 → **AI Rewriter** → **Settings**

| 설정 | 값 | 설명 |
|------|------|------|
| Worker URL | `https://aicr-worker.{subdomain}.workers.dev` | 배포된 Worker URL |
| Worker Secret | `your-worker-secret` | WORKER_SECRET과 동일한 값 |
| HMAC Secret | `your-hmac-secret` | HMAC_SECRET과 동일한 값 |

### 2. 연동 테스트

1. WordPress 관리자 → **AI Rewriter** → **New Content**
2. URL 입력 후 **Rewrite** 클릭
3. Worker에서 처리 후 결과 반환 확인

### 3. 자동화 설정

1. **AI Rewriter** → **Feeds**에서 RSS 피드 추가
2. **Auto Rewrite** 옵션 활성화
3. Cron이 매시 정각에 자동 실행

---

## 문제 해결

### 일반적인 오류

#### "Worker not found" 오류

```bash
# Worker 이름 확인
npx wrangler deployments list

# wrangler.toml의 name과 일치하는지 확인
```

#### "KV namespace not found" 오류

```bash
# KV namespace ID 확인
npx wrangler kv namespace list

# wrangler.toml의 id와 일치하는지 확인
```

#### "D1 database not found" 오류

```bash
# D1 database 목록 확인
npx wrangler d1 list

# wrangler.toml의 database_id 확인
```

#### 인증 오류

```bash
# 로그인 상태 확인
npx wrangler whoami

# 재로그인
npx wrangler logout
npx wrangler login
```

### 로그 확인

```bash
# 실시간 로그 스트리밍
npx wrangler tail

# 필터링된 로그
npx wrangler tail --format=pretty --search="error"
```

### 디버깅 팁

1. **LOG_LEVEL을 debug로 설정**
   ```toml
   [vars]
   LOG_LEVEL = "debug"
   ```

2. **로컬에서 먼저 테스트**
   ```bash
   npm run dev
   ```

3. **D1 쿼리 직접 실행**
   ```bash
   npx wrangler d1 execute aicr-worker-db --command="SELECT * FROM tasks LIMIT 10"
   ```

---

## 다음 단계

1. [WordPress 플러그인과 Worker 아키텍처](./ARCHITECTURE.md) 이해하기
2. [GitHub Secrets 설정](./GITHUB_SECRETS.md)으로 CI/CD 구성
3. 테스트 실행: `npm test`

---

*최종 업데이트: 2025-02-05*
