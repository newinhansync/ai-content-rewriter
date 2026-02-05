# Cloudflare Worker 문서

AI Content Rewriter Cloudflare Worker의 문서 인덱스입니다.

## 📚 문서 목록

| 문서 | 설명 | 대상 |
|------|------|------|
| [SETUP_GUIDE.md](./SETUP_GUIDE.md) | 설치 및 설정 완전 가이드 | 개발자, DevOps |
| [ARCHITECTURE.md](./ARCHITECTURE.md) | WordPress-Worker 아키텍처 상세 설명 | 개발자 |
| [GITHUB_SECRETS.md](./GITHUB_SECRETS.md) | CI/CD용 GitHub Secrets 설정 | DevOps |

## 🚀 빠른 시작

### 1. 필수 요구사항 확인

- Node.js 18+
- Cloudflare 계정 (Workers Paid 플랜)
- OpenAI API 키
- Google Gemini API 키

### 2. 설치

```bash
cd cloudflare-worker
npm install
npx wrangler login
```

### 3. 리소스 생성

```bash
# KV Namespaces
npx wrangler kv namespace create CONFIG_KV
npx wrangler kv namespace create LOCK_KV

# D1 Database
npx wrangler d1 create aicr-worker-db
npx wrangler d1 execute aicr-worker-db --file=schema.sql

# R2 Bucket
npx wrangler r2 bucket create aicr-images
```

### 4. Secrets 설정

```bash
npx wrangler secret put WORKER_SECRET
npx wrangler secret put HMAC_SECRET
npx wrangler secret put WP_API_KEY
npx wrangler secret put OPENAI_API_KEY
npx wrangler secret put GEMINI_API_KEY
npx wrangler secret put WORDPRESS_URL
```

### 5. 개발 서버 실행

```bash
npm run dev
# http://localhost:8787 에서 접속
```

### 6. 배포

```bash
npm run deploy:staging   # Staging 배포
npm run deploy:production # Production 배포
```

## 📖 상세 가이드

자세한 내용은 각 문서를 참조하세요:

- **처음 설정하는 경우**: [SETUP_GUIDE.md](./SETUP_GUIDE.md) 처음부터 따라하기
- **아키텍처 이해하기**: [ARCHITECTURE.md](./ARCHITECTURE.md)
- **CI/CD 구성하기**: [GITHUB_SECRETS.md](./GITHUB_SECRETS.md)

## 🔗 관련 링크

- [Cloudflare Workers 공식 문서](https://developers.cloudflare.com/workers/)
- [Cloudflare Workflows](https://developers.cloudflare.com/workflows/)
- [Wrangler CLI 문서](https://developers.cloudflare.com/workers/wrangler/)

---

*최종 업데이트: 2025-02-05*
