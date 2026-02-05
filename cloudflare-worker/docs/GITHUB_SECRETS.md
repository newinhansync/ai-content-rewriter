# GitHub Secrets Configuration

CI/CD 파이프라인이 정상 작동하려면 다음 GitHub Secrets를 설정해야 합니다.

## Required Secrets

### Cloudflare 인증

| Secret Name | Description | 획득 방법 |
|-------------|-------------|-----------|
| `CF_API_TOKEN` | Cloudflare API Token | Cloudflare Dashboard > My Profile > API Tokens > Create Token |
| `CF_ACCOUNT_ID` | Cloudflare Account ID | Dashboard 우상단 Account ID 또는 Workers 페이지 URL에서 확인 |
| `CF_ACCOUNT_SUBDOMAIN` | Workers 서브도메인 | `{account}.workers.dev` 형식의 account 부분 |

### API Token 권한 설정

Cloudflare API Token 생성 시 다음 권한이 필요합니다:

```
Account:
  - Workers KV Storage: Edit
  - Workers R2 Storage: Edit
  - D1: Edit

Zone:
  - Workers Routes: Edit
```

또는 "Edit Cloudflare Workers" 템플릿을 사용하세요.

## Setting Secrets in GitHub

### 방법 1: GitHub UI

1. Repository > Settings > Secrets and variables > Actions
2. "New repository secret" 클릭
3. Name과 Value 입력 후 저장

### 방법 2: GitHub CLI

```bash
# Cloudflare secrets
gh secret set CF_API_TOKEN --body "your-api-token"
gh secret set CF_ACCOUNT_ID --body "your-account-id"
gh secret set CF_ACCOUNT_SUBDOMAIN --body "your-subdomain"
```

## Environment-specific Secrets

GitHub Environments를 사용하여 staging/production 별도 설정 가능:

1. Repository > Settings > Environments
2. "staging" 환경 생성
3. "production" 환경 생성 (Protection rules 추가 권장)

### Production Protection Rules (권장)

- Required reviewers: 1명 이상
- Wait timer: 5분 (선택사항)
- Deployment branches: main 브랜치만

## Verification

Secrets 설정 후 다음 명령으로 확인:

```bash
# GitHub CLI로 secrets 목록 확인
gh secret list

# 결과 예시:
# CF_API_TOKEN      Updated 2025-02-05
# CF_ACCOUNT_ID     Updated 2025-02-05
# CF_ACCOUNT_SUBDOMAIN  Updated 2025-02-05
```

## Worker Secrets (Wrangler)

GitHub Secrets와 별도로, Worker 런타임에서 사용할 secrets는 Wrangler로 설정:

```bash
cd cloudflare-worker

# WordPress → Worker 인증
wrangler secret put WORKER_SECRET

# Worker → WordPress webhook 서명
wrangler secret put HMAC_SECRET

# WordPress REST API 인증
wrangler secret put WP_API_KEY

# AI API keys
wrangler secret put OPENAI_API_KEY
wrangler secret put GEMINI_API_KEY

# WordPress URL
wrangler secret put WORDPRESS_URL
```

이 secrets는 Cloudflare에 저장되며, GitHub에서 직접 접근하지 않습니다.

## Security Best Practices

1. **API Token 최소 권한**: 필요한 권한만 부여
2. **Token 만료 설정**: 가능하면 만료 기한 설정
3. **Environment Protection**: Production에 approval 필수화
4. **Secret Rotation**: 정기적으로 secrets 교체
5. **Audit Log**: GitHub Audit log로 secret 접근 모니터링

## Troubleshooting

### "Authentication failed" 오류

```
Error: Authentication error - check your API token
```

해결: CF_API_TOKEN이 올바른지 확인, 토큰 권한 재검토

### "Account not found" 오류

```
Error: Could not find your account
```

해결: CF_ACCOUNT_ID가 정확한지 확인

### "Worker not found" 오류

```
Error: workers.api.error.script_not_found
```

해결:
1. Worker 이름이 wrangler.toml의 name과 일치하는지 확인
2. 처음 배포 시 staging 환경에서 먼저 테스트
