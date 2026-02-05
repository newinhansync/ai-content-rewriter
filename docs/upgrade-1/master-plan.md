# AI Content Rewriter v2.0 - 마스터 플랜

> **버전**: v2.0 (전문가 리뷰 완료 - Architect / Backend / Frontend)
> **작성일**: 2026-01-30
> **범위**: Cloudflare 아키텍처 + 완전 자동화 + 콘텐츠 품질 혁신 + 스타일 시스템

---

## 목차

1. [전체 비전 + 최종 결과물 예측](#1-전체-비전)
2. [과제 A: Cloudflare Worker 아키텍처](#2-과제-a-cloudflare-worker-아키텍처)
3. [과제 B: 완전 자동화 파이프라인](#3-과제-b-완전-자동화-파이프라인)
4. [과제 C: 콘텐츠 품질 10배 향상](#4-과제-c-콘텐츠-품질-10배-향상)
5. [과제 D: 블로그 스타일 시스템](#5-과제-d-블로그-스타일-시스템)
6. [통합 구현 로드맵](#6-통합-구현-로드맵)
7. [검증 결과 + 리스크](#7-검증-결과)

---

## 1. 전체 비전

### 1.1 현재 → 목표

```
현재 상태:
  사람이 URL 입력 → AI 재작성 요청 → 타임아웃 위험 → 수동 게시
  품질: AI 티가 나는 기계적 글, 일관성 없음, 이미지 없음

목표 상태:
  RSS 피드 자동 수집 → AI 큐레이션 → Multi-Step 고품질 재작성
  → AI 이미지 생성 → 자동 게시 → (향후) 포맷 변환 확장
  ※ 사람 개입 없이 완전 자동화
  ※ 현재 대비 10배 품질 (측정 가능한 기준 포함)
  ※ 블로그 고유의 글쓰기 + 삽화 스타일 적용
  ※ 품질 안전장치: 자기 검증 + confidence 기반 게시 판단
```

### 1.2 최종 결과물 예측

#### 사용자 경험 시나리오

**시나리오 1: 초기 설정 (1회)**
```
1. WordPress 관리자 > AI Rewriter > Settings
2. "처리 모드" → "Cloudflare Worker" 선택
3. Worker URL, API Secret 입력 → [연결 테스트] 클릭 → ✅ 연결 성공
4. AI Rewriter > 스타일 설정
   - 블로그 설명, 톤/보이스, 글쓰기 규칙 입력
   - 삽화 스타일 선택 + 컬러 팔레트 설정
   - 예시 글 3개 등록
5. AI Rewriter > 자동화 설정
   - 자동 큐레이션: ON (confidence ≥ 0.8 자동 승인)
   - 일일 최대 게시: 3개
   - 게시 모드: score ≥ 8 → 즉시 게시 / score < 8 → 임시 저장
6. 설정 완료. 이후 사람 개입 불필요.
```

**시나리오 2: 일상 운영 (매일 자동)**
```
[06:00] Cron Trigger 실행 → RSS 15개 피드에서 12개 신규 아이템 수집
[06:01] AI 큐레이션 → 5개 승인, 7개 거부 (부적합 주제)
[06:02] 승인된 5개 아이템에 대해 각각 Workflow 인스턴스 생성

[06:03~06:15] 아이템 #1 처리 (병렬):
  Step A: 아웃라인 생성 (30초)
  Step B: 스타일 적용 본문 작성 (90초)
  Step C: SEO 최적화 (30초)
  Step D: Self-Critique → score 9/10 ✅
  Step E: 이미지 생성 (20초)
  Step F: WordPress 게시 → 즉시 발행

[06:03~06:18] 아이템 #2~#5도 병렬 처리

[06:20] 완료. WordPress에 5개 새 게시글 + 대표 이미지 게시됨.
```

**시나리오 3: WordPress 대시보드에서 확인**
```
┌─────────────────────────────────────────────────────────────┐
│  AI Rewriter > 자동화 대시보드                                │
│                                                              │
│  ✅ Worker 상태: 정상 (마지막 확인: 2분 전)                    │
│                                                              │
│  📊 오늘 통계                                                │
│  ├── RSS 수집: 12건                                          │
│  ├── 큐레이션: 5건 승인 / 7건 거부                            │
│  ├── 재작성: 5건 완료 (평균 품질 8.6/10)                      │
│  ├── 이미지 생성: 5건                                        │
│  └── 게시: 4건 발행 / 1건 임시저장 (품질 7.5 - 검토 필요)      │
│                                                              │
│  📈 이번 주                                                  │
│  ├── 총 게시: 28건                                           │
│  ├── 평균 품질: 8.4/10                                       │
│  └── AI 비용: $1.85                                          │
│                                                              │
│  ⚠️ 알림 (1건)                                               │
│  └── 아이템 #234: 품질 점수 7.5 → 임시저장됨. 검토 필요        │
│                                                              │
│  📋 최근 처리 이력                                            │
│  ┌────┬──────────────┬────────┬──────┬──────────┐           │
│  │ ID │ 제목          │ 품질   │ 상태 │ 처리시간   │           │
│  ├────┼──────────────┼────────┼──────┼──────────┤           │
│  │ 5  │ AI 트렌드 2026│ 9.0/10│ 발행 │ 3분 12초  │           │
│  │ 4  │ 클라우드 보안  │ 8.5/10│ 발행 │ 2분 45초  │           │
│  │ 3  │ React 19 신기능│ 7.5/10│ 임시 │ 4분 02초  │           │
│  └────┴──────────────┴────────┴──────┴──────────┘           │
└─────────────────────────────────────────────────────────────┘
```

**시나리오 4: 수동 재작성 (기존 기능 유지)**
```
1. AI Rewriter > New Content → URL 입력 → [재작성] 클릭
2. "Cloudflare Worker에서 처리 중..." (프로그레스 바)
   ├── 아웃라인 생성 중... ✅
   ├── 본문 작성 중... ✅
   ├── SEO 최적화 중... ✅
   ├── 품질 검증 중... ✅ (9/10)
   └── 이미지 생성 중... ✅
3. 결과 미리보기 → [게시] 또는 [수정]
```

#### 최종 산출물 목록

| 산출물 | 설명 |
|--------|------|
| WordPress Plugin v2.0 | REST API + Webhook + 스타일 설정 + 자동화 대시보드 |
| Cloudflare Worker (aicr-worker) | TypeScript, Workflows, Cron, AI 처리 |
| 글쓰기 스타일 가이드 | JSON 형식, KV 저장, 관리자에서 편집 |
| 삽화 스타일 가이드 | 컬러팔레트 + 프롬프트 템플릿, KV 저장 |
| Multi-Step 프롬프트 4종 | 아웃라인/본문/SEO/검증 |
| 배포 가이드 | Cloudflare Worker 배포 + WordPress 연동 |

### 1.3 과제 의존 관계

```
┌──────────────────────────────────────────────────────────┐
│                                                          │
│  [과제 A] Cloudflare 아키텍처  ← 인프라 기반             │
│      │                                                   │
│      ├──→ [과제 C] 프롬프트 엔지니어링  (A와 병렬 가능)   │
│      │        │                                          │
│      ├──→ [과제 D] 스타일 시스템  (C와 병렬 가능)         │
│      │        │                                          │
│      ▼        ▼                                          │
│  [과제 B] 완전 자동화 파이프라인  ← A+C+D 통합            │
│                                                          │
└──────────────────────────────────────────────────────────┘

실제 의존:
  A (인프라)는 필수 선행
  C (프롬프트) + D (스타일)은 A와 병렬 작업 가능 (로컬 테스트)
  B (자동화)는 A+C+D 모두 완료 후 통합
```

---

## 2. 과제 A: Cloudflare Worker 아키텍처

> 상세: [architecture-plan.md](./architecture-plan.md)

### 요약

| 항목 | 내용 |
|------|------|
| 목적 | 호스팅 타임아웃 해결 + 자동화 인프라 확보 |
| 방법 | WordPress ↔ Cloudflare Worker (Workflows) 분리 |
| 비용 | $5/월 (Cloudflare Workers Paid) |
| 핵심 | WordPress는 요청/수신만, Worker가 AI 처리 전담 |

### 아키텍처 요약도

```
WordPress Plugin ←── Webhook ──── Cloudflare Worker
(경량 클라이언트)                    (AI 처리 엔진)
  │                                  │
  ├── Admin UI                       ├── Workflows (자동화)
  ├── REST API (데이터 제공)          ├── AI Processor (Multi-Step)
  ├── Webhook Receiver               ├── RSS Parser
  └── Task Dispatcher                ├── KV / D1 / R2
                                     └── Cron Triggers
```

### ⚠️ 아키텍처 핵심 제약 (리뷰에서 발견)

**Workflow 30분 전체 제한 → 다건 처리 시 초과 위험**

```
❌ 잘못된 설계 (기존):
  하나의 Workflow에서 5개 아이템을 순차 처리
  → 5 × (4 AI호출 × 45초 + 이미지 20초) = 약 17분 (AI만)
  → + RSS + 큐레이션 = 25분+ → 30분 초과 가능

✅ 올바른 설계 (수정):
  [Master Workflow] RSS 수집 + AI 큐레이션 (5~10분)
      │
      ├──→ [Item Workflow #1] 재작성 + 이미지 + 게시 (10~15분)
      ├──→ [Item Workflow #2] (병렬 실행)
      ├──→ [Item Workflow #3] (병렬 실행)
      └──→ ...

  ※ 아이템별 독립 Workflow → 30분 제한 안전
  ※ 병렬 실행 → 처리 속도 향상
  ※ Paid 4,000 동시 인스턴스 → 충분
```

**동시성 제어 필요**

```
문제: 1시간 Cron → 이전 Master Workflow가 아직 실행 중이면?
해결: KV에 실행 중 플래그 저장

  Cron 시작 시:
    lock = await KV.get("workflow:lock")
    if (lock && Date.now() - lock.timestamp < 50분):
      return  // 이전 실행 중 → 스킵
    await KV.put("workflow:lock", { timestamp: Date.now() })

  완료 시:
    await KV.delete("workflow:lock")
```

---

## 3. 과제 B: 완전 자동화 파이프라인

### 3.1 목표

**사람이 관여하지 않고** RSS 피드에서 수집한 콘텐츠를 AI가:
1. 블로그에 적합한지 판단 (큐레이션)
2. 고품질 콘텐츠로 재작성 (Multi-Step)
3. 대표 이미지 생성
4. **품질 기반 게시 판단** (score ≥ 8 → 발행, < 8 → 임시저장)

### 3.2 2-Tier Workflow 설계 (수정됨)

```
┌─ Master Workflow: collect-and-dispatch ──────────────────────┐
│  ※ Cron Trigger (매 1시간) → 이 Workflow 실행                 │
│  ※ 예상 소요: 5~10분                                         │
│                                                              │
│  [Step 1] 동시성 체크                                         │
│  └── KV lock 확인 → 이전 실행 중이면 즉시 종료                │
│                                                              │
│  [Step 2] RSS 수집                                            │
│  ├── WP REST API → 활성 피드 목록 조회                        │
│  ├── 각 피드 URL fetch → RSS 파싱                             │
│  ├── 중복 제거 (guid/link 기준)                               │
│  └── 새 아이템 → WP REST API로 저장 (status: 'new')           │
│                                                              │
│  [Step 3] AI 큐레이션                                         │
│  ├── 새 아이템 목록 + 블로그 프로필 + 최근 게시글 제목          │
│  ├── AI 판단: 적합/부적합, confidence, priority                │
│  ├── 일일 게시 한도 체크 (오늘 이미 N건 게시했으면 스킵)        │
│  └── 승인된 아이템 status → 'queued'                          │
│                                                              │
│  [Step 4] Item Workflow 디스패치                               │
│  ├── 승인된 각 아이템에 대해 Item Workflow 인스턴스 생성        │
│  ├── 게시 간격 제어: 아이템 간 10분 delay 파라미터 전달         │
│  └── KV lock 해제                                             │
│                                                              │
└──────────────────────────────────────────────────────────────┘

┌─ Item Workflow: rewrite-and-publish ─────────────────────────┐
│  ※ Master Workflow가 아이템별로 생성 (병렬 실행)               │
│  ※ 예상 소요: 10~15분 / 아이템                                │
│                                                              │
│  [Step 1] 원본 콘텐츠 추출                                    │
│  ├── WP REST API → 아이템 상세 조회                           │
│  ├── 원본 URL fetch → 본문 텍스트 추출                        │
│  └── status → 'processing'                                   │
│                                                              │
│  [Step 2] 아웃라인 생성 (프롬프트 Step A)                      │
│  ├── KV에서 프롬프트 + 스타일 로드                             │
│  ├── AI Call: 원본 → 구조화된 아웃라인                         │
│  └── 결과: { angle, hook, sections, conclusion }              │
│                                                              │
│  [Step 3] 본문 작성 (프롬프트 Step B)                          │
│  ├── AI Call: 아웃라인 + 원본 + 스타일 → HTML 본문             │
│  └── Role Prompting + Few-Shot + CoT 적용                     │
│                                                              │
│  [Step 4] SEO 최적화 (프롬프트 Step C)                         │
│  ├── AI Call: 본문 → SEO 보강 + 메타데이터 생성                │
│  └── 결과: { title, content, meta, keywords, tags }           │
│                                                              │
│  [Step 5] 품질 검증 (프롬프트 Step D)                          │
│  ├── AI Call: 최종 결과 → Self-Critique                       │
│  ├── score ≥ 8 → 통과                                        │
│  ├── score < 8 → Step 3 재실행 (issues 피드백 포함, 1회 한정)  │
│  └── 재시도 후에도 < 8 → publish_as: 'draft'로 표시            │
│                                                              │
│  [Step 6] 이미지 생성                                         │
│  ├── 콘텐츠 키워드 + 삽화 스타일 템플릿 조합                   │
│  ├── AI 이미지 API 호출 (DALL-E 3 / Gemini)                   │
│  └── R2에 이미지 저장 → public URL 생성                       │
│                                                              │
│  [Step 7] WordPress 게시                                      │
│  ├── Webhook POST: 콘텐츠 + 메타 + 이미지 URL + 품질 점수      │
│  ├── WordPress 측 처리:                                       │
│  │   ├── 이미지 URL에서 다운로드 → 미디어 라이브러리 저장       │
│  │   ├── wp_insert_post() 실행                                │
│  │   ├── score ≥ 8 + auto_publish=true → 'publish'            │
│  │   └── score < 8 또는 auto_publish=false → 'draft'          │
│  ├── 아이템 status → 'published' / 'draft_saved'              │
│  └── 처리 결과 D1에 로그 기록                                  │
│                                                              │
│  [Step 8] (향후 확장) 포맷 변환                                │
│  ├── 뉴스레터 포맷 / 소셜 카드 / 영상 스크립트                 │
│  └── Workflow Step 추가만으로 확장                              │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

### 3.3 AI 큐레이션 설계

```
입력:
  - 피드 아이템 (제목 + 요약 + 원본 URL)
  - 블로그 프로필 (주제 영역, 카테고리, 타겟 독자)  ← KV 저장
  - 최근 7일 게시글 제목 목록 (중복 주제 방지)       ← WP REST API
  - 오늘 게시 건수 (일일 한도 체크)                  ← WP REST API

AI 판단 기준:
  1. 주제 적합성: 블로그의 관심 영역에 해당하는가?
  2. 신선도: 최근 게시한 주제와 중복되지 않는가?
  3. 품질 가능성: 재작성하면 가치 있는 콘텐츠가 될 수 있는가?
  4. 독자 관심도: 타겟 독자가 관심 가질 주제인가?

출력:
  {
    "decision": "approve" | "skip",
    "confidence": 0.85,
    "reason": "최신 AI 트렌드로 블로그 주제에 적합",
    "priority": "high" | "medium" | "low"
  }

안전장치:
  - confidence < 임계값(기본 0.8) → skip
  - 오늘 이미 일일 한도 도달 → 모두 skip (내일 처리)
  - 동일 피드에서 연속 3건 이상 → 다양성 위해 1건만 승인
```

### 3.4 품질 기반 게시 판단 (안전장치)

```
Self-Critique 점수에 따른 처리:

score 9-10: 즉시 발행 (publish)
score 8:    즉시 발행 (publish) + "검토 권장" 플래그
score 6-7:  임시 저장 (draft) + 관리자 알림
score 1-5:  폐기 (trash) + 에러 로그

관리자 설정:
  - 자동 발행 임계값: [8] (1-10)
  - 임시 저장 시 알림: ☑ 이메일 / ☐ WordPress 알림
```

### 3.5 에러 알림 체계

```
[경고 수준별 알림]

🔴 Critical: Worker 연결 실패, AI API 키 만료
   → WordPress 관리자 알림 + 이메일

🟡 Warning: 품질 점수 미달 (draft 저장), AI API 에러 (재시도 성공)
   → WordPress 대시보드 알림

🟢 Info: 정상 처리 완료
   → 대시보드 이력에 기록

구현:
  Worker → WP REST API POST /aicr/v1/notifications
  WordPress → wp_mail() + admin_notices
```

### 3.6 데이터 주권 (Source of Truth)

```
⚠️ 중요: WordPress DB가 유일한 Source of Truth

WordPress DB (SoT):
  ├── aicr_feeds: 피드 설정
  ├── aicr_feed_items: 아이템 상태, 콘텐츠
  ├── aicr_history: 처리 이력
  ├── wp_posts: 게시글
  └── wp_options: 플러그인 설정

Cloudflare (보조):
  ├── KV: 프롬프트/스타일 캐시 (WP에서 동기화)
  ├── D1: 실행 로그 전용 (진단/디버깅)
  └── R2: 이미지 임시 저장 (WP 업로드 후 삭제)

규칙:
  - Worker는 항상 WP REST API로 데이터 조회/저장
  - D1은 로그만 (상태 관리 X)
  - R2 이미지는 WP 업로드 완료 후 24시간 뒤 자동 삭제
```

---

## 4. 과제 C: 콘텐츠 품질 10배 향상

### 4.1 "10배 품질"의 측정 기준

| 측정 항목 | 현재 (v1) | 목표 (v2) | 측정 방법 |
|-----------|----------|----------|----------|
| 구조적 완성도 | H2 6-8개 고정 틀 | 내용에 맞는 유동적 구조 | Self-Critique 구조 점수 |
| 도입부 매력도 | "오늘날 빠르게..." 패턴 | 질문/일화/통계/반전 등 다양 | Self-Critique 도입부 점수 |
| 문체 자연스러움 | '~입니다/합니다' 반복 | 다양한 어미 + 리듬감 | 어미 반복 비율 측정 |
| 독창적 관점 | 원본 요약 수준 | 독자적 각도/분석 추가 | Self-Critique angle 점수 |
| 실제 사례/데이터 | 거의 없음 | 매 글 1개 이상 | Self-Critique 체크 |
| SEO 최적화 | 기본 키워드 삽입 | 전략적 키워드 배치 + 메타 | SEO 점수 (Yoast 기준) |
| 스타일 일관성 | 매번 다른 톤 | 블로그 고유 스타일 유지 | Few-Shot 기반 일관성 |
| 이미지 매칭 | 이미지 없음 | 주제 맞춤 일러스트 | 자동 생성 여부 |

**종합 품질 점수**: Self-Critique score (1-10)
- v1 추정: 4~5점 수준
- v2 목표: 8점 이상

### 4.2 현재 프롬프트의 한계

현재 `PromptManager.php` 분석:

| 문제 | 현재 상태 | 영향 |
|------|----------|------|
| 단일 프롬프트 | 하나의 프롬프트로 구조+본문+SEO 전부 | 품질 저하, AI가 우선순위 혼동 |
| 역할 약함 | "전문 블로그 콘텐츠 작성자" 한 줄 | 전문성 미발휘 |
| 예시 없음 | Few-shot 없음 | 출력 형식/품질 불안정 |
| 자기 검증 없음 | 한 번에 최종 결과 | 저품질 감지 불가 |
| 스타일 없음 | 톤/보이스 미정의 | 일관성 없음 |
| 기계적 구조 | "H2 소제목으로 6-8개 섹션" 고정 | 모든 글이 같은 틀 |

### 4.3 Multi-Step Prompting 전략

```
현재: [단일 프롬프트] ─────────────────→ 최종 결과 (품질 불안정)

개선: [Step A]  → [Step B]  → [Step C]  → [Step D]  → 고품질 결과
      아웃라인    본문작성    SEO최적화    자기검증
       (30초)    (60~90초)   (30초)      (30초)
```

#### Step A: 아웃라인 생성

```
목적: 글의 뼈대 설계 → 논리적 흐름 + 독창적 관점 확보

입력: 원본 콘텐츠 + 블로그 프로필
출력:
{
  "angle": "이 글의 독창적 관점/각도",
  "hook": "도입부 전략 (질문/일화/통계/반전 중 택1)",
  "target_word_count": 2000,
  "sections": [
    {
      "heading": "섹션 제목",
      "purpose": "이 섹션이 독자에게 제공하는 가치",
      "key_points": ["핵심 포인트 1", "핵심 포인트 2"],
      "content_type": "설명 | 사례분석 | 비교 | 실습가이드 | 인사이트",
      "estimated_words": 300
    }
  ],
  "conclusion_strategy": "결론 접근법",
  "internal_link_opportunities": ["연관 가능한 주제 키워드"]
}
```

#### Step B: 본문 작성

```
목적: 아웃라인 기반 + 스타일 적용 고품질 본문

입력:
  - Step A 아웃라인
  - 원본 콘텐츠 (참고용)
  - 스타일 가이드 (과제 D) ← KV에서 로드
  - Few-Shot 예시 1-2개 ← KV에서 카테고리별 로드

프롬프트 구조:
  [시스템 메시지] 전문가 페르소나 + 스타일 가이드 전체
  [Few-Shot] 이상적인 글 예시 (300-500자 발췌)
  [사용자 메시지]
    아웃라인: {Step A 결과}
    원본 참고: {원본 콘텐츠 요약}

    각 섹션을 작성하기 전에 다음을 먼저 생각하세요:
    1. 이 섹션에서 독자가 얻을 핵심 인사이트는?
    2. 원본에 없지만 추가할 가치 있는 정보는?
    3. 가장 흥미로운 전달 방법은?
    (내부 추론은 출력에 포함하지 마세요)

출력: HTML 형식의 완성된 본문

⚠️ 토큰 예산 (GPT-4o 128K 컨텍스트):
  - 시스템 메시지 + 스타일: ~1,500 토큰
  - Few-Shot 예시: ~800 토큰
  - 아웃라인: ~500 토큰
  - 원본 콘텐츠 (요약): ~2,000 토큰 (원본이 길면 3,000자로 요약)
  - 입력 합계: ~4,800 토큰
  - 출력 (본문): ~4,000~8,000 토큰
  - 총합: ~12,800 토큰 → 128K 한도의 10% → 안전
```

#### Step C: SEO 최적화 + 메타데이터

```
목적: 본문 SEO 보강 + 전체 메타데이터 생성

입력: Step B의 본문
출력:
{
  "post_title": "SEO + 클릭 유도형 제목 (60자 이내)",
  "post_content": "(SEO 보강된 HTML 본문)",
  "meta_title": "검색엔진용 (60자)",
  "meta_description": "검색 결과 노출용 (155자, 키워드 포함)",
  "focus_keyword": "주요 타겟 키워드",
  "keywords": ["관련 키워드 5-8개"],
  "tags": ["태그 3-5개"],
  "category_suggestion": "카테고리명",
  "excerpt": "요약 (150자)"
}

SEO 보강:
  - focus_keyword를 제목, 첫 100단어, H2 1-2개에 자연 배치
  - Entity 기반 관련어 삽입 (키워드 밀도가 아닌 의미 연결)
```

#### Step D: 자기 검증 (Self-Critique)

```
목적: 품질 보증 + 게시 여부 판단

입력: Step C의 최종 결과
체크리스트:
  □ 도입부가 3초 내에 독자 관심을 끌 수 있는가? (뻔한 패턴 X)
  □ 독창적 관점(angle)이 반영되어 있는가?
  □ 각 섹션이 구체적 가치를 제공하는가? (추상적 설명 X)
  □ 원본 대비 1.5배 이상 분량인가?
  □ 문체가 자연스러운가? ('~입니다/합니다' 단조 반복 X)
  □ 실질적 사례/데이터가 1개 이상 포함되었는가?
  □ SEO 키워드가 자연스럽게 녹아있는가?
  □ 결론이 실행 가능한 인사이트를 제공하는가?

출력:
{
  "score": 8,           // 1-10
  "passed": true,       // score >= 자동발행 임계값
  "checklist": {
    "hook_quality": 9,
    "angle_originality": 8,
    "section_value": 8,
    "length_adequacy": 9,
    "writing_naturalness": 7,
    "examples_included": true,
    "seo_integration": 8,
    "conclusion_actionable": 8
  },
  "issues": ["문체 일부 단조로운 구간 있음"],
  "improvement_suggestions": ["3번째 섹션의 어미를 다양화할 것"]
}

재시도 로직:
  score < 8 → Step B 재실행 (improvement_suggestions 피드백 포함)
  재시도 후에도 < 8 → draft로 저장 + 관리자 알림
  최대 재시도: 1회
```

### 4.4 비용 추정 (수정됨)

```
GPT-4o 가격: 입력 $2.50/1M 토큰, 출력 $10.00/1M 토큰

글 1개당:
  큐레이션:  입력 800 + 출력 200  = $0.004
  Step A:    입력 2,500 + 출력 500  = $0.011
  Step B:    입력 4,800 + 출력 6,000 = $0.072
  Step C:    입력 7,000 + 출력 1,000 = $0.028
  Step D:    입력 8,000 + 출력 500  = $0.025
  이미지:    DALL-E 3 HD = $0.040
  ─────────────────────────────────────
  합계:      약 $0.18/글

월간 (30글):
  AI API: ~$5.40
  Cloudflare: $5.00
  ─────────────────
  총합: ~$10.40/월
```

> 기존 추정($9.20)에서 $1.20 상향. Step B의 출력 토큰이 가장 큰 비중.

### 4.5 프롬프트 저장 및 동기화

```
[WordPress 관리자]
  스타일/프롬프트 편집 → [저장]
      │
      ├──→ wp_options에 저장 (SoT)
      │
      └──→ Worker REST API POST /api/sync-config
           → Cloudflare KV에 동기화

KV 구조:
├── prompt:outline        → Step A 템플릿
├── prompt:content        → Step B 템플릿
├── prompt:seo            → Step C 템플릿
├── prompt:critique       → Step D 템플릿
├── prompt:curation       → 큐레이션 템플릿
├── style:writing         → 글쓰기 스타일 JSON
├── style:image           → 삽화 스타일 JSON
├── example:{category}    → 카테고리별 Few-Shot 예시
├── blog:profile          → 블로그 프로필
└── config:version        → 설정 버전 (동기화 확인용)

⚠️ KV는 eventually consistent (최대 60초 지연)
→ 프롬프트 수정 후 즉시 테스트 시 이전 버전이 적용될 수 있음
→ UI에 "설정 동기화 중... (최대 1분 소요)" 안내 표시
```

---

## 5. 과제 D: 블로그 스타일 시스템

### 5.1 글 작성 스타일

```json
{
  "blog_name": "블로그명",
  "voice": {
    "tone": "전문적이면서 친근한",
    "perspective": "독자와 같은 눈높이에서 대화하듯",
    "personality": "호기심 많고 실용적인 동료 전문가"
  },
  "writing_rules": {
    "sentence_style": [
      "짧은 문장과 긴 문장을 섞어 리듬감 부여",
      "'~입니다/합니다' 동일 어미 3회 연속 반복 금지",
      "첫 문장은 반드시 독자의 호기심을 자극",
      "수동태보다 능동태 선호"
    ],
    "paragraph_style": [
      "한 문단 = 하나의 아이디어",
      "3-4줄 이내",
      "문단 간 전환어로 자연스러운 연결"
    ],
    "structure_variety": [
      "모든 글이 같은 틀을 따르지 않도록 다양한 구조",
      "도입부: 질문/일화/통계/반전 중 랜덤 선택",
      "H2 개수는 내용에 따라 유동적 (4~10개)",
      "리스트/표/인용구/코드블록 등 형식 혼합"
    ],
    "unique_elements": [
      "핵심 인사이트는 별도 블록으로 강조 (blockquote 또는 callout)",
      "실제 사례/경험담 최소 1개 포함",
      "독자에게 던지는 질문 1-2개 포함",
      "구체적인 숫자/데이터 인용"
    ]
  },
  "forbidden": [
    "AI 티가 나는 뻔한 도입부 ('오늘날 빠르게 변화하는...')",
    "'~할 수 있습니다', '~중요합니다' 등 약한 마무리 반복",
    "과도한 감탄사/이모지",
    "근거 없는 단정적 표현",
    "원본 문장 그대로 사용"
  ]
}
```

### 5.2 삽화 스타일

```json
{
  "style_name": "블로그 삽화 스타일",
  "base_style": "미니멀 일러스트레이션",
  "visual_identity": {
    "color_palette": {
      "primary": "#3B82F6",
      "secondary": "#10B981",
      "accent": "#F59E0B",
      "background": "light neutral (#F8FAFC)"
    },
    "illustration_style": "깔끔한 라인 아트 + 제한된 컬러 팔레트",
    "mood": "밝고 전문적인, 과도하지 않은",
    "composition": "중앙 집중형, 충분한 여백"
  },
  "prompt_template": {
    "prefix": "Minimalist editorial illustration, clean line art,",
    "color_instruction": "using {primary} and {secondary} colors on {background},",
    "style_instruction": "professional blog header style, no text, no watermark, simple shapes,",
    "quality": "high quality, consistent style, 16:9 aspect ratio"
  },
  "forbidden": [
    "사실적 인물 사진/포토리얼",
    "과도한 디테일/복잡한 배경",
    "어두운/우울한 톤",
    "텍스트/글자 포함",
    "저작권 있는 캐릭터"
  ]
}
```

### 5.3 WordPress 관리자 UI (PHP 기반)

```
┌─────────────────────────────────────────────────────────────┐
│  AI Rewriter > 스타일 설정                                    │
│                                                              │
│  ┌─ 글쓰기 스타일 ─────────────────────────────────────────┐ │
│  │ 블로그 설명: [기술/AI 전문 블로그. 실용적 인사이트 제공_] │ │
│  │ 톤/보이스:   [전문적이면서 친근한__________________]      │ │
│  │ 타겟 독자:   [개발자, 기술 관심 직장인________________]   │ │
│  │                                                         │ │
│  │ 글쓰기 규칙: ┌────────────────────────────────────┐     │ │
│  │             │ (텍스트 에디터 - 규칙 수정 가능)     │     │ │
│  │             └────────────────────────────────────┘     │ │
│  │                                                         │ │
│  │ 금지 사항:   ┌────────────────────────────────────┐     │ │
│  │             │ (텍스트 에디터)                      │     │ │
│  │             └────────────────────────────────────┘     │ │
│  │                                                         │ │
│  │ 예시 글 등록 (Few-Shot):                                 │ │
│  │ ┌──────────────────────────────────────────────────┐    │ │
│  │ │ #1: "AI 시대의 개발자 역량" (2026-01-15) [삭제]   │    │ │
│  │ │ #2: "클라우드 비용 최적화 가이드" (2026-01-20)    │    │ │
│  │ └──────────────────────────────────────────────────┘    │ │
│  │ [+ 예시 글 추가] (URL 또는 텍스트 붙여넣기)              │ │
│  └─────────────────────────────────────────────────────────┘ │
│                                                              │
│  ┌─ 삽화 스타일 ───────────────────────────────────────────┐ │
│  │ 기본 스타일:  [미니멀 일러스트 ▼]                        │ │
│  │ 프라이머리:   [🎨 #3B82F6]  세컨더리: [🎨 #10B981]      │ │
│  │ 액센트:       [🎨 #F59E0B]  배경:     [🎨 #F8FAFC]      │ │
│  │                                                         │ │
│  │ 프롬프트 접두사: [Minimalist editorial illustration...]  │ │
│  │ 금지 사항: [사실적 인물, 어두운 톤, 텍스트 포함_____]    │ │
│  │                                                         │ │
│  │ [미리보기 생성] → 샘플 이미지 3장 미리보기               │ │
│  └─────────────────────────────────────────────────────────┘ │
│                                                              │
│  [저장 + Worker 동기화]  [기본값 복원]                        │
│  ⓘ 설정 변경 후 Worker 동기화에 최대 1분 소요됩니다.          │
└─────────────────────────────────────────────────────────────┘
```

### 5.4 스타일 발전 전략

```
Phase 1: 기본 정의 (Sprint 2)
  └── 톤, 금지사항, 기본 규칙, 컬러 팔레트 설정
  └── 기존 잘 쓴 게시글 3개를 예시로 등록

Phase 2: 패턴 학습 (Sprint 3~4)
  └── 자동 생성된 글 중 고품질 글을 예시에 추가 (최대 5개)
  └── 카테고리별 예시 분리 (기술/리뷰/튜토리얼 등)

Phase 3: 데이터 기반 개선 (향후)
  └── 게시 후 성과 데이터 수집 (조회수, 체류시간)
  └── 고성과 글의 패턴을 스타일에 반영
  └── (고급) AI가 자동으로 스타일 최적화 제안
```

---

## 6. 통합 구현 로드맵

### Phase 1: 인프라 기반 (과제 A)

```
Sprint 1: Cloudflare ↔ WordPress 연결
├── WordPress: REST API 엔드포인트 7개 구현
│   (feeds, feed-items, webhook, media, config, health, notifications)
├── WordPress: Webhook Receiver + HMAC 검증 + 이미지 다운로드
├── WordPress: ProcessingMode (Local/Cloudflare 분기)
├── Worker: 기본 구조 (fetch handler + WordPress Client)
├── Worker: 수동 재작성 요청 처리 (단일 AI 호출)
├── Worker: KV/D1/R2 바인딩 설정
└── 연동 E2E 테스트 (수동 재작성 → Webhook → 게시)
```

### Phase 2: 품질 혁신 (과제 C + D, A와 병렬 가능)

```
Sprint 2: 프롬프트 엔지니어링 + 스타일 시스템
├── 글쓰기 스타일 가이드 초안 작성 (JSON)
├── 삽화 스타일 가이드 초안 작성 (JSON + 컬러팔레트)
├── Multi-Step 프롬프트 4종 설계
│   ├── Step A: 아웃라인 프롬프트
│   ├── Step B: 본문 작성 프롬프트 (Role + Few-Shot + CoT)
│   ├── Step C: SEO 최적화 프롬프트
│   └── Step D: Self-Critique 프롬프트
├── 품질 비교 테스트 (현재 단일 vs Multi-Step, 5개 글)
├── WordPress: 스타일 설정 UI (PHP admin page)
├── Worker: KV 프롬프트/스타일 동기화 API
├── Few-Shot 예시 3개 선정 및 KV 등록
└── 비용 실측 검증 (예상 $0.18/글 vs 실제)
```

### Phase 3: 완전 자동화 (과제 B)

```
Sprint 3: 자동화 파이프라인
├── Worker: Master Workflow 구현 (RSS + 큐레이션 + 디스패치)
├── Worker: Item Workflow 구현 (Multi-Step + 이미지 + 게시)
├── Worker: 동시성 제어 (KV lock)
├── Worker: Cron Trigger 설정 (매 1시간)
├── Worker: 에러 알림 → WP notifications API
├── WordPress: 자동화 대시보드 UI
│   ├── Worker 상태 표시
│   ├── 오늘/이번 주 통계
│   ├── 최근 처리 이력 테이블
│   └── 알림 영역 (품질 미달, 에러)
├── WordPress: 자동화 설정 UI
│   ├── 큐레이션 설정 (임계값, 일일 한도)
│   ├── 자동 발행 임계값
│   └── 알림 설정
└── 파이프라인 E2E 테스트 (48시간 운영 테스트)
```

### Phase 4: 안정화 + 확장

```
Sprint 4: 안정화
├── 수동 재작성에도 Multi-Step 적용 (진행 상태 UI)
│   ├── 단계별 프로그레스 바
│   ├── task_id 기반 상태 폴링 (5초 간격)
│   └── 완료 시 미리보기 표시
├── R2 이미지 자동 정리 (24시간 후 삭제 lifecycle rule)
├── 스타일 미세 조정 (실제 출력 5~10개 기반)
├── 에러 핸들링 보강 (exponential backoff)
├── 처리 모드 스위치 UI (Local/Cloudflare)
├── 배포 가이드 문서화 (Cloudflare Worker 설치법)
└── (향후) 포맷 변환 파이프라인 Step 추가
```

---

## 7. 검증 결과

### 7.1 과제별 실현 가능성

| 과제 | 실현성 | 근거 | 리스크 |
|------|--------|------|--------|
| A. Cloudflare 아키텍처 | ✅ 확실 | Workflows + Cron, 검증 완료 | CF 요금/정책 변경 |
| B. 완전 자동화 | ✅ 가능 | 2-Tier Workflow로 30분 제한 회피 | AI 큐레이션 정확도 초기 불안정 |
| C. 10배 품질 | ✅ 가능 | Multi-Step + Few-Shot 검증된 기법 | 비용 6배($0.03→$0.18/글) |
| D. 스타일 시스템 | ✅ 가능 | KV + 프롬프트 주입 | 수렴에 2-4주 소요 |

### 7.2 비용 추정 (수정됨)

| 항목 | 월 비용 |
|------|--------|
| Cloudflare Workers Paid | $5.00 |
| AI API (30글/월, Multi-Step + 큐레이션) | $5.40 |
| AI 이미지 (30장, DALL-E 3 HD) | $1.20 |
| **합계** | **~$11.60/월** |

### 7.3 전문가 리뷰 발견 사항 반영 현황

| # | 발견 사항 | 심각도 | 반영 상태 |
|---|----------|--------|----------|
| 1 | Workflow 30분 제한 vs 다건 처리 | 🔴 치명 | ✅ 2-Tier Workflow로 해결 (§3.2) |
| 2 | architecture-plan.md와 불일치 | 🟡 중요 | ✅ master-plan을 SoT로 지정 |
| 3 | 동시성 제어 부재 | 🔴 치명 | ✅ KV lock 메커니즘 추가 (§2) |
| 4 | 품질 안전망 부재 | 🔴 치명 | ✅ score 기반 publish/draft 분기 (§3.4) |
| 5 | 토큰/비용 과소 추정 | 🟡 중요 | ✅ 비용 재계산 $0.18/글 (§4.4) |
| 6 | R2→WP 이미지 업로드 흐름 누락 | 🟡 중요 | ✅ Step 7에 다운로드→미디어 명시 (§3.2) |
| 7 | D1 vs WP DB 이중 상태 | 🟡 중요 | ✅ SoT 규칙 명시 (§3.6) |
| 8 | KV eventual consistency | 🟢 낮음 | ✅ UI 안내 + version 키 추가 (§4.5) |
| 9 | 에러 알림 부재 | 🔴 치명 | ✅ 알림 체계 추가 (§3.5) |
| 10 | 대시보드 설계 부재 | 🟡 중요 | ✅ 대시보드 와이어프레임 추가 (§1.2) |
| 11 | 수동 재작성 진행 UX 미정의 | 🟡 중요 | ✅ 프로그레스 바 + 폴링 명시 (§6 Sprint 4) |
| 12 | UI 기술 스택 미명시 | 🟢 낮음 | ✅ PHP 기반 명시 (§5.3) |
| 13 | 최종 결과물 예측 누락 | 🟡 중요 | ✅ 4개 시나리오 + 산출물 목록 (§1.2) |
| 14 | "10배 품질" 측정 기준 부재 | 🟡 중요 | ✅ 8개 항목 측정 체계 (§4.1) |

### 7.4 리스크 매트릭스

| 리스크 | 확률 | 영향 | 대응 |
|--------|------|------|------|
| Cloudflare 장애 | 낮 | 높 | Local 모드 폴백 |
| AI API 키 만료/에러 | 중 | 높 | 알림 + 자동 재시도 + 다른 모델 폴백 |
| Webhook 전달 실패 | 중 | 중 | Workflow 자동 재시도 + D1 로그 |
| AI 큐레이션 오판 | 중 | 낮 | confidence 임계값 상향 조정 |
| Self-Critique가 항상 높은 점수 | 중 | 중 | 체크리스트 기반 구체적 평가로 완화 |
| 호스팅 REST API 차단 | 낮 | 높 | AJAX 폴백, 관리자 가이드 |
| 비용 예상 초과 | 낮 | 낮 | 일일 한도 + 모니터링 |

---

## 부록 A: architecture-plan.md와의 관계

```
master-plan.md (이 문서) = 전체 프로젝트의 Source of Truth
architecture-plan.md = 과제 A (Cloudflare 아키텍처)의 상세 기술 문서

불일치 발생 시: master-plan.md가 우선
architecture-plan.md 업데이트 필요 사항:
  - 2-Tier Workflow 구조 반영
  - Multi-Step 프롬프팅 반영
  - 큐레이션 Step 추가
  - 에러 알림 엔드포인트 추가
```

## 부록 B: 참고 자료

### 프롬프트 엔지니어링
- [K2View - Top 6 Prompt Engineering Techniques 2026](https://www.k2view.com/blog/prompt-engineering-techniques/)
- [AiPromptsX - Advanced Techniques](https://aipromptsx.com/blog/advanced-prompt-engineering-techniques)
- [IBM - 2026 Guide to Prompt Engineering](https://www.ibm.com/think/prompt-engineering)
- [Clearscope - Prompt Engineering for SEO Content](https://www.clearscope.io/blog/prompt-engineering-for-seo-content)

### Cloudflare
- [Cloudflare Workers Limits](https://developers.cloudflare.com/workers/platform/limits/)
- [Cloudflare Workflows](https://developers.cloudflare.com/workflows/)
- [Cloudflare Workflows Limits](https://developers.cloudflare.com/workflows/reference/limits/)
- [Cloudflare Cron Triggers](https://developers.cloudflare.com/workers/configuration/cron-triggers/)
- [Cloudflare Queues](https://developers.cloudflare.com/queues/platform/limits/)
