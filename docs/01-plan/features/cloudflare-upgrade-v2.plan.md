# AI Content Rewriter v2.0 - Cloudflare Upgrade 개발 계획서

> **Summary**: 공유 호스팅 타임아웃 해결 및 완전 자동화 파이프라인 구축을 위한 Cloudflare Worker 기반 아키텍처 전환
>
> **Project**: AI Content Rewriter WordPress Plugin
> **Version**: 2.0.0
> **Author**: Claude
> **Date**: 2026-02-03
> **Status**: Draft

---

## 1. Overview

### 1.1 Purpose

공유 호스팅 환경의 **타임아웃 문제**와 **자동화 한계**를 Cloudflare 인프라로 해결하여:
- 사람 개입 없는 완전 자동화 콘텐츠 파이프라인 구축
- 현재 대비 10배 품질의 AI 생성 콘텐츠 달성
- 블로그 고유의 글쓰기 + 삽화 스타일 시스템 적용

### 1.2 Background

#### 현재 문제점

| 문제 | 현재 상태 | 영향 |
|------|----------|------|
| **호스팅 타임아웃** | AI API 호출 300초 설정 vs 호스팅 30~60초 제한 | 재작성 실패, 불안정한 서비스 |
| **자동화 한계** | WP-Cron 방문자 기반 실행 | 트래픽 없으면 자동화 미작동 |
| **콘텐츠 품질** | 단일 프롬프트, 고정 구조 | AI 티 나는 기계적 글 |
| **스타일 일관성** | 톤/보이스 미정의 | 매번 다른 스타일의 글 |

#### 목표 상태

```
현재: 사람이 URL 입력 → AI 재작성 → 타임아웃 위험 → 수동 게시
      품질: AI 티가 나는 기계적 글, 일관성 없음, 이미지 없음

목표: RSS 피드 자동 수집 → AI 큐레이션 → Multi-Step 고품질 재작성
      → AI 이미지 생성 → 자동 게시
      ※ 사람 개입 없이 완전 자동화
      ※ 현재 대비 10배 품질 (Self-Critique score 8+/10)
      ※ 블로그 고유의 글쓰기 + 삽화 스타일 적용
```

### 1.3 Related Documents

- 아키텍처 상세: [architecture-plan.md](../upgrade-1/architecture-plan.md)
- 마스터 플랜: [master-plan.md](../upgrade-1/master-plan.md)
- 현재 플러그인 문서: [04-PLUGIN-SPECIFICATIONS.md](../04-PLUGIN-SPECIFICATIONS.md)

---

## 2. Scope

### 2.1 In Scope

- [x] **과제 A**: Cloudflare Worker 아키텍처 구축
  - WordPress REST API 엔드포인트 7개
  - Webhook Receiver + HMAC 인증
  - Worker HTTP API + Workflows

- [x] **과제 B**: 완전 자동화 파이프라인
  - 2-Tier Workflow (Master + Item)
  - AI 큐레이션 시스템
  - 품질 기반 게시 판단 (score ≥ 8 자동 발행)

- [x] **과제 C**: 콘텐츠 품질 10배 향상
  - Multi-Step Prompting (4단계)
  - Self-Critique 품질 검증
  - Few-Shot 예시 기반 학습

- [x] **과제 D**: 블로그 스타일 시스템
  - 글쓰기 스타일 가이드 (JSON)
  - 삽화 스타일 + 컬러 팔레트
  - WordPress 관리자 스타일 설정 UI

### 2.2 Out of Scope

- Mobile 앱 연동
- 멀티 블로그 지원 (단일 WordPress 사이트 대상)
- 실시간 협업 편집 기능
- SNS 자동 배포 (향후 확장 예정)
- 뉴스레터/영상 스크립트 변환 (향후 확장 예정)

---

## 3. Requirements

### 3.1 Functional Requirements

| ID | 요구사항 | 우선순위 | 상태 |
|----|----------|:--------:|:----:|
| **과제 A: Cloudflare 아키텍처** |
| FR-A01 | WordPress REST API 엔드포인트 7개 구현 (feeds, feed-items, webhook, media, config, health, notifications) | High | Pending |
| FR-A02 | Webhook Receiver + HMAC-SHA256 서명 검증 | High | Pending |
| FR-A03 | Worker ↔ WordPress 양방향 통신 (Bearer Token + API Key) | High | Pending |
| FR-A04 | 처리 모드 스위치 (Local/Cloudflare) | High | Pending |
| FR-A05 | Worker 설정 페이지 UI (URL, Secret, 연결 테스트) | Medium | Pending |
| **과제 B: 완전 자동화** |
| FR-B01 | Master Workflow (RSS 수집 + 큐레이션 + 디스패치) | High | Pending |
| FR-B02 | Item Workflow (Multi-Step + 이미지 + 게시) | High | Pending |
| FR-B03 | AI 큐레이션 (적합성 판단, confidence 기반 승인) | High | Pending |
| FR-B04 | 품질 기반 게시 판단 (score ≥ 8 자동 발행) | High | Pending |
| FR-B05 | 동시성 제어 (KV lock) | High | Pending |
| FR-B06 | 일일 게시 한도 설정 | Medium | Pending |
| FR-B07 | 자동화 대시보드 UI (상태, 통계, 이력) | Medium | Pending |
| FR-B08 | 에러 알림 체계 (Critical/Warning/Info) | Medium | Pending |
| **과제 C: 품질 향상** |
| FR-C01 | Step A: 아웃라인 생성 프롬프트 | High | Pending |
| FR-C02 | Step B: 본문 작성 프롬프트 (Role + Few-Shot + CoT) | High | Pending |
| FR-C03 | Step C: SEO 최적화 프롬프트 | High | Pending |
| FR-C04 | Step D: Self-Critique 품질 검증 프롬프트 | High | Pending |
| FR-C05 | 품질 미달 시 재시도 로직 (1회) | Medium | Pending |
| FR-C06 | AI 이미지 생성 (DALL-E 3 / Gemini) | Medium | Pending |
| **과제 D: 스타일 시스템** |
| FR-D01 | 글쓰기 스타일 가이드 JSON 스키마 | High | Pending |
| FR-D02 | 삽화 스타일 가이드 JSON 스키마 | High | Pending |
| FR-D03 | 스타일 설정 관리자 UI | Medium | Pending |
| FR-D04 | Few-Shot 예시 관리 (등록/편집/삭제) | Medium | Pending |
| FR-D05 | KV 동기화 API (WordPress → Worker) | High | Pending |

### 3.2 Non-Functional Requirements

| 카테고리 | 기준 | 측정 방법 |
|----------|------|----------|
| **성능** | 수동 재작성 요청 응답 < 2초 (비동기 처리 위임) | WordPress → Worker POST 응답 시간 |
| **성능** | Workflow 전체 처리 < 15분/아이템 | D1 로그 처리 시간 |
| **신뢰성** | Worker 가용성 99.9% (Cloudflare SLA) | Cloudflare Dashboard |
| **신뢰성** | Webhook 전달 실패 시 자동 재시도 (최대 3회) | Workflow 내장 재시도 |
| **보안** | HMAC-SHA256 서명 검증 + Timestamp 5분 이내 | Webhook Receiver 로직 |
| **보안** | API Key 기반 REST API 인증 | Application Password / Custom Key |
| **비용** | 월 $15 이내 (AI $6 + Cloudflare $5 + 이미지 $2 + 버퍼) | 실제 사용량 모니터링 |
| **품질** | Self-Critique score 평균 8/10 이상 | D1 품질 점수 로그 |

---

## 4. Success Criteria

### 4.1 Definition of Done

#### Phase 1: 인프라 기반 (과제 A)
- [ ] WordPress REST API 7개 엔드포인트 구현 및 인증 동작
- [ ] Worker 기본 구조 배포 (fetch handler)
- [ ] 수동 재작성 → Worker 처리 → Webhook → 게시글 생성 E2E 성공
- [ ] Local/Cloudflare 모드 스위치 동작

#### Phase 2: 품질 혁신 (과제 C + D)
- [ ] Multi-Step 프롬프트 4종 완성 및 KV 저장
- [ ] 글쓰기/삽화 스타일 가이드 JSON 완성
- [ ] 품질 비교 테스트: 단일 vs Multi-Step (5개 샘플, 점수 차이 확인)
- [ ] 스타일 설정 관리자 UI 동작

#### Phase 3: 완전 자동화 (과제 B)
- [ ] Master Workflow + Item Workflow 구현 및 Cron 트리거 동작
- [ ] AI 큐레이션 → 자동 재작성 → 품질 검증 → 자동 게시 파이프라인 완성
- [ ] 48시간 무인 운영 테스트 통과
- [ ] 자동화 대시보드 UI 완성

#### Phase 4: 안정화
- [ ] 수동 재작성에도 Multi-Step 적용 + 진행 상태 UI
- [ ] 에러 핸들링 및 알림 체계 완성
- [ ] 배포 가이드 문서 완성

### 4.2 Quality Criteria

| 품질 측정 항목 | 현재 (v1 추정) | 목표 (v2) | 측정 방법 |
|----------------|:--------------:|:---------:|----------|
| 구조적 완성도 | 4/10 | 8/10 | Self-Critique 구조 점수 |
| 도입부 매력도 | 3/10 | 8/10 | Self-Critique 도입부 점수 |
| 문체 자연스러움 | 4/10 | 8/10 | 어미 반복 비율 측정 |
| 독창적 관점 | 3/10 | 8/10 | Self-Critique angle 점수 |
| 실제 사례/데이터 | 2/10 | 7/10 | Self-Critique 체크 |
| SEO 최적화 | 5/10 | 8/10 | Yoast SEO 기준 |
| 스타일 일관성 | 3/10 | 8/10 | Few-Shot 기반 일관성 |
| **종합 품질 점수** | **4~5/10** | **8+/10** | Self-Critique 종합 |

---

## 5. Risks and Mitigation

| 리스크 | 영향 | 발생 확률 | 대응 방안 |
|--------|:----:|:--------:|----------|
| **Cloudflare 장애** | High | Low | Local 모드 폴백 기능 유지 |
| **AI API 키 만료/에러** | High | Medium | 알림 + 자동 재시도 + 다른 모델 폴백 |
| **Workflow 30분 초과** | High | Low | 2-Tier Workflow로 분리 (아이템별 15분 내) |
| **Webhook 전달 실패** | Medium | Medium | Workflow 자동 재시도 + D1 로그 |
| **AI 큐레이션 오판** | Low | Medium | confidence 임계값 조정 (기본 0.8) |
| **Self-Critique 점수 왜곡** | Medium | Medium | 체크리스트 기반 구체적 평가 |
| **호스팅 REST API 차단** | High | Low | AJAX 폴백 + .htaccess 가이드 |
| **비용 예상 초과** | Low | Low | 일일 한도 + 실시간 모니터링 |
| **KV eventual consistency** | Low | High | UI 안내 "동기화 최대 1분 소요" |

---

## 6. Architecture Considerations

### 6.1 Project Level Selection

| Level | 특징 | 권장 대상 | 선택 |
|-------|------|----------|:----:|
| **Starter** | 단순 구조 | 정적 사이트 | ☐ |
| **Dynamic** | Feature 기반 모듈, 서비스 레이어 | 백엔드 있는 웹앱, SaaS MVP | ☑ |
| **Enterprise** | 엄격한 레이어 분리, DI, 마이크로서비스 | 고트래픽, 복잡한 아키텍처 | ☐ |

> **선택: Dynamic** - WordPress 플러그인 + Cloudflare Worker 연동으로 백엔드 서비스 레이어 필요

### 6.2 Key Architectural Decisions

| 결정 사항 | 옵션 | 선택 | 근거 |
|----------|------|:----:|------|
| **Worker 실행 방식** | Queue / Workflow / 단순 Worker | Workflow | 장시간 처리, 자동 재시도, 상태 보존 |
| **Cron 간격** | 30분 / 1시간 / 2시간 | 1시간 | wall-time 15분 확보 |
| **AI 처리 방식** | 단일 호출 / Multi-Step | Multi-Step | 품질 10배 향상 목표 |
| **품질 검증** | 없음 / 규칙 기반 / AI Self-Critique | AI Self-Critique | 자동화된 품질 보증 |
| **이미지 생성** | DALL-E 3 / Gemini / Stable Diffusion | DALL-E 3 (기본) | 품질 + 통합 용이성 |
| **데이터 SoT** | WordPress DB / Cloudflare D1 | WordPress DB | 기존 데이터 활용, 이중화 방지 |

### 6.3 System Architecture

```
┌──────────────────────────────────────────────────────────────┐
│                  WordPress Plugin (v2.0)                      │
│  ┌──────────┐  ┌──────────────┐  ┌──────────────────┐        │
│  │ Admin UI │  │ REST API     │  │ Webhook Receiver │        │
│  │ (기존)    │  │ (데이터 제공) │  │ (결과 수신)       │        │
│  └──────────┘  └──────────────┘  └──────────────────┘        │
│        │              ▲                   ▲                   │
└────────│──────────────│───────────────────│───────────────────┘
         │              │                   │
         ▼              │                   │
┌────────────────────────────────────────────────────────────────┐
│                   Cloudflare Platform                          │
│                                                                │
│  ┌────────────┐     ┌─────────────────────────────────────┐   │
│  │   Cron     │────→│         Master Workflow              │   │
│  │  (매 1시간) │     │  RSS 수집 → 큐레이션 → 디스패치      │   │
│  └────────────┘     └─────────────────────────────────────┘   │
│                              │                                 │
│                              ▼ (아이템별 생성)                  │
│                     ┌─────────────────────────────────────┐   │
│                     │         Item Workflow (병렬)         │   │
│                     │  아웃라인 → 본문 → SEO → 검증 → 게시  │   │
│                     └─────────────────────────────────────┘   │
│                              │                                 │
│  ┌────────────┐              ▼                                │
│  │  Workers   │     ┌─────────────────────┐                   │
│  │ (HTTP API) │     │   AI APIs           │                   │
│  └────────────┘     │   (OpenAI / Gemini) │                   │
│        │            └─────────────────────┘                   │
│        ▼                                                      │
│  ┌──────────────────────────────────────────┐                 │
│  │ KV (설정/스타일) │ D1 (로그) │ R2 (이미지) │                 │
│  └──────────────────────────────────────────┘                 │
└────────────────────────────────────────────────────────────────┘
```

### 6.4 Data Flow

```
[자동화 플로우]

Cron Trigger (매 1시간)
    │
    ▼
┌─ Master Workflow ──────────────────────────────┐
│  Step 1: 동시성 체크 (KV lock)                   │
│  Step 2: RSS 수집 → WP REST API                 │
│  Step 3: AI 큐레이션 (confidence ≥ 0.8 승인)     │
│  Step 4: Item Workflow 디스패치                  │
└────────────────────────────────────────────────┘
                    │
                    ▼ (아이템별 병렬)
┌─ Item Workflow ────────────────────────────────┐
│  Step 1: 원본 콘텐츠 추출                        │
│  Step 2: 아웃라인 생성 (30초)                    │
│  Step 3: 본문 작성 (90초)                        │
│  Step 4: SEO 최적화 (30초)                       │
│  Step 5: Self-Critique (30초)                   │
│          └─ score < 8 → Step 3 재시도 (1회)     │
│  Step 6: 이미지 생성 (20초)                      │
│  Step 7: WordPress Webhook → 게시               │
│          └─ score ≥ 8 → publish                │
│          └─ score < 8 → draft                  │
└────────────────────────────────────────────────┘
```

---

## 7. Convention Prerequisites

### 7.1 Existing Project Conventions

- [x] `CLAUDE.md` has coding conventions section
- [ ] `docs/01-plan/conventions.md` exists
- [ ] `CONVENTIONS.md` exists at project root
- [x] WordPress Coding Standards 적용
- [x] PSR-4 오토로딩 (네임스페이스: `AIContentRewriter\*`)

### 7.2 Conventions to Define/Verify

| 카테고리 | 현재 상태 | 정의 필요 | 우선순위 |
|----------|----------|----------|:--------:|
| **REST API 응답 형식** | 미정의 | JSON 스키마, 에러 코드 | High |
| **Webhook 페이로드** | 미정의 | 요청/응답 JSON 스키마 | High |
| **Worker TypeScript** | 신규 | 코딩 컨벤션, 폴더 구조 | High |
| **프롬프트 템플릿** | 미정의 | 변수 치환 형식, 버전 관리 | Medium |
| **에러 코드 체계** | 미정의 | 에러 코드 범위, 메시지 형식 | Medium |

### 7.3 Environment Variables Needed

| 변수명 | 용도 | 범위 | 생성 필요 |
|--------|------|:----:|:--------:|
| `AICR_WORKER_URL` | Worker URL | WP Plugin | ☑ |
| `AICR_WORKER_SECRET` | Worker 인증 토큰 | WP Plugin | ☑ |
| `AICR_HMAC_SECRET` | Webhook 서명용 | WP + Worker | ☑ |
| `AICR_WP_API_KEY` | WP REST API 인증 | Worker | ☑ |
| `OPENAI_API_KEY` | OpenAI API | Worker | ☑ |
| `GOOGLE_AI_API_KEY` | Gemini API | Worker | ☐ (선택) |

### 7.4 Cloudflare Worker Project Structure

```
aicr-worker/
├── wrangler.toml              # Cloudflare 설정 (Cron, Bindings)
├── package.json
├── tsconfig.json
├── src/
│   ├── index.ts               # Entry point (fetch + scheduled)
│   ├── workflows/
│   │   ├── master.ts          # Master Workflow (RSS + 큐레이션)
│   │   └── item.ts            # Item Workflow (Multi-Step + 게시)
│   ├── handlers/
│   │   ├── rewrite.ts         # 수동 재작성 요청
│   │   └── health.ts          # 헬스체크
│   ├── services/
│   │   ├── ai/
│   │   │   ├── openai.ts      # OpenAI API 클라이언트
│   │   │   └── gemini.ts      # Gemini API 클라이언트
│   │   ├── rss/
│   │   │   └── parser.ts      # RSS 피드 파싱
│   │   ├── wordpress/
│   │   │   └── client.ts      # WP REST API 클라이언트
│   │   └── webhook/
│   │       └── sender.ts      # Webhook 결과 전송
│   ├── prompts/
│   │   ├── outline.ts         # Step A 프롬프트
│   │   ├── content.ts         # Step B 프롬프트
│   │   ├── seo.ts             # Step C 프롬프트
│   │   ├── critique.ts        # Step D 프롬프트
│   │   └── curation.ts        # 큐레이션 프롬프트
│   ├── config/
│   │   └── settings.ts        # KV에서 설정 로드
│   └── utils/
│       ├── crypto.ts          # HMAC, 토큰 관리
│       └── logger.ts          # D1 로깅
└── test/
```

---

## 8. Implementation Roadmap

### Sprint 1: 인프라 기반 (1주)

| 태스크 | 설명 | 담당 | 상태 |
|--------|------|:----:|:----:|
| WP-A01 | REST API 엔드포인트 구현 (7개) | WP | ⬜ |
| WP-A02 | Webhook Receiver + HMAC 검증 | WP | ⬜ |
| WP-A03 | ProcessingMode (Local/Cloudflare) | WP | ⬜ |
| WP-A04 | Worker 설정 페이지 UI | WP | ⬜ |
| CF-A01 | Worker 프로젝트 초기화 | Worker | ⬜ |
| CF-A02 | fetch handler + WordPress Client | Worker | ⬜ |
| CF-A03 | 수동 재작성 처리 (단일 AI 호출) | Worker | ⬜ |
| CF-A04 | KV/D1/R2 바인딩 설정 | Worker | ⬜ |
| TEST-A | 연동 E2E 테스트 | QA | ⬜ |

### Sprint 2: 품질 혁신 (1주)

| 태스크 | 설명 | 담당 | 상태 |
|--------|------|:----:|:----:|
| PM-C01 | 글쓰기 스타일 가이드 JSON 작성 | 콘텐츠 | ⬜ |
| PM-C02 | 삽화 스타일 가이드 JSON 작성 | 콘텐츠 | ⬜ |
| PM-C03 | Step A: 아웃라인 프롬프트 설계 | 콘텐츠 | ⬜ |
| PM-C04 | Step B: 본문 작성 프롬프트 설계 | 콘텐츠 | ⬜ |
| PM-C05 | Step C: SEO 최적화 프롬프트 설계 | 콘텐츠 | ⬜ |
| PM-C06 | Step D: Self-Critique 프롬프트 설계 | 콘텐츠 | ⬜ |
| WP-D01 | 스타일 설정 관리자 UI | WP | ⬜ |
| WP-D02 | Few-Shot 예시 관리 UI | WP | ⬜ |
| CF-C01 | KV 프롬프트/스타일 동기화 API | Worker | ⬜ |
| TEST-C | 품질 비교 테스트 (5개 샘플) | QA | ⬜ |

### Sprint 3: 완전 자동화 (1주)

| 태스크 | 설명 | 담당 | 상태 |
|--------|------|:----:|:----:|
| CF-B01 | Master Workflow 구현 | Worker | ⬜ |
| CF-B02 | Item Workflow 구현 (Multi-Step) | Worker | ⬜ |
| CF-B03 | AI 큐레이션 로직 | Worker | ⬜ |
| CF-B04 | 동시성 제어 (KV lock) | Worker | ⬜ |
| CF-B05 | Cron Trigger 설정 | Worker | ⬜ |
| CF-B06 | 이미지 생성 + R2 저장 | Worker | ⬜ |
| CF-B07 | 에러 알림 → WP notifications | Worker | ⬜ |
| WP-B01 | 자동화 대시보드 UI | WP | ⬜ |
| WP-B02 | 자동화 설정 UI | WP | ⬜ |
| TEST-B | 48시간 운영 테스트 | QA | ⬜ |

### Sprint 4: 안정화 (1주)

| 태스크 | 설명 | 담당 | 상태 |
|--------|------|:----:|:----:|
| WP-S01 | 수동 재작성 Multi-Step 적용 | WP | ⬜ |
| WP-S02 | 수동 재작성 진행 상태 UI | WP | ⬜ |
| CF-S01 | R2 이미지 자동 정리 (24시간) | Worker | ⬜ |
| CF-S02 | 에러 핸들링 보강 (exponential backoff) | Worker | ⬜ |
| DOC-01 | Cloudflare Worker 배포 가이드 | 문서 | ⬜ |
| DOC-02 | 사용자 매뉴얼 업데이트 | 문서 | ⬜ |
| TEST-S | 최종 QA 및 릴리스 준비 | QA | ⬜ |

---

## 9. Cost Estimation

### 9.1 월간 비용 (30글 기준)

| 항목 | 단가 | 수량 | 월 비용 |
|------|------|:----:|-------:|
| Cloudflare Workers Paid | $5/월 | 1 | $5.00 |
| **AI API (글당 $0.18)** |
| └ 큐레이션 | 입력 800 + 출력 200 토큰 | 60건 | $0.24 |
| └ Step A (아웃라인) | 입력 2,500 + 출력 500 | 30건 | $0.33 |
| └ Step B (본문) | 입력 4,800 + 출력 6,000 | 30건 | $2.16 |
| └ Step C (SEO) | 입력 7,000 + 출력 1,000 | 30건 | $0.84 |
| └ Step D (검증) | 입력 8,000 + 출력 500 | 30건 | $0.75 |
| **AI 이미지** | DALL-E 3 HD $0.040 | 30건 | $1.20 |
| **합계** | | | **~$10.52** |

> 참고: GPT-4o 가격 기준 (입력 $2.50/1M, 출력 $10.00/1M)

### 9.2 기존 대비 비용 변화

| 항목 | v1 (현재) | v2 (목표) | 변화 |
|------|----------:|----------:|-----:|
| AI API (글당) | $0.03 | $0.18 | +600% |
| 인프라 | $0 | $5.00 | 신규 |
| 이미지 | $0 | $1.20 | 신규 |
| **월 총합 (30글)** | **$0.90** | **$10.52** | +1,069% |

> 비용 대비 효과: 품질 10배 향상 + 완전 자동화 → ROI 충분

---

## 10. Next Steps

1. [ ] 팀 리뷰 및 계획 승인
2. [ ] 설계 문서 작성 (`cloudflare-upgrade-v2.design.md`)
3. [ ] Sprint 1 착수 (인프라 기반)
4. [ ] 프롬프트 엔지니어링 병렬 진행

---

## Version History

| Version | Date | Changes | Author |
|---------|------|---------|--------|
| 0.1 | 2026-02-03 | 초안 작성 (architecture-plan + master-plan 통합) | Claude |

---

## Appendix A: API Specifications Summary

### WordPress REST API Endpoints

| 엔드포인트 | 메서드 | 용도 | 인증 |
|-----------|:------:|------|:----:|
| `/wp-json/aicr/v1/feeds` | GET | 활성 피드 목록 | API Key |
| `/wp-json/aicr/v1/feed-items/pending` | GET | 대기 중 아이템 | API Key |
| `/wp-json/aicr/v1/feed-items/{id}/status` | PATCH | 아이템 상태 변경 | API Key |
| `/wp-json/aicr/v1/webhook` | POST | 처리 결과 수신 | HMAC |
| `/wp-json/aicr/v1/media` | POST | 이미지 업로드 | API Key |
| `/wp-json/aicr/v1/config` | GET | AI 설정 조회 | API Key |
| `/wp-json/aicr/v1/health` | GET | 연결 확인 | API Key |
| `/wp-json/aicr/v1/notifications` | POST | 알림 전송 | HMAC |

### Webhook Payload (Worker → WordPress)

```json
{
  "task_id": "uuid-xxx",
  "status": "completed",
  "quality_score": 8.5,
  "result": {
    "title": "재작성된 제목",
    "content": "<p>본문...</p>",
    "excerpt": "요약",
    "category_suggestion": "기술",
    "tags": ["AI", "블로그"],
    "meta_title": "SEO 제목",
    "meta_description": "SEO 설명",
    "featured_image_url": "https://r2.../image.png"
  }
}
```

---

## Appendix B: Multi-Step Prompt Overview

| Step | 목적 | 입력 | 출력 | 예상 시간 |
|------|------|------|------|:---------:|
| A | 아웃라인 생성 | 원본 + 블로그 프로필 | angle, hook, sections | 30초 |
| B | 본문 작성 | 아웃라인 + 원본 + 스타일 | HTML 본문 | 60~90초 |
| C | SEO 최적화 | 본문 | 제목, 메타, 키워드, 태그 | 30초 |
| D | 품질 검증 | 최종 결과 | score, issues, suggestions | 30초 |

---

## Appendix C: Quality Metrics

### Self-Critique 체크리스트 (Step D)

| 항목 | 평가 기준 | 가중치 |
|------|----------|:------:|
| hook_quality | 도입부가 3초 내 독자 관심 유발 | 15% |
| angle_originality | 독창적 관점이 반영됨 | 15% |
| section_value | 각 섹션이 구체적 가치 제공 | 15% |
| length_adequacy | 원본 대비 1.5배 이상 분량 | 10% |
| writing_naturalness | 문체 자연스러움 (어미 다양성) | 15% |
| examples_included | 실질적 사례/데이터 포함 | 10% |
| seo_integration | SEO 키워드 자연스러운 배치 | 10% |
| conclusion_actionable | 결론이 실행 가능한 인사이트 | 10% |
