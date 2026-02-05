# AI Content Rewriter for WordPress - 개발 문서

## 프로젝트 개요

URL 또는 텍스트를 AI(ChatGPT-5, Gemini-3)로 재작성하여 WordPress 블로그 게시글을 자동 생성하는 플러그인

---

## 문서 구조

```
docs/
├── README.md                    # 이 파일
├── 01-PROJECT-OVERVIEW.md       # 프로젝트 개요
├── 02-TECHNICAL-ARCHITECTURE.md # 시스템 아키텍처
├── 03-ENVIRONMENT-SETUP.md      # 환경 구축 가이드
├── 04-PLUGIN-SPECIFICATIONS.md  # 플러그인 명세서
├── 05-AI-INTEGRATION.md         # AI 통합 가이드
├── 06-DEVELOPMENT-ROADMAP.md    # 개발 로드맵
├── 07-AI-IMAGE-GENERATION.md    # AI 이미지 생성 기능 계획
├── tasks/                       # 작업 계획 문서
│   ├── TASKS-MAIN.md            # 마스터 작업 현황
│   └── TASKS-RSS-FEATURE.md     # RSS 기능 Task
├── features/                    # 기능 설계 문서
│   └── RSS-SUBSCRIPTION-DESIGN.md # RSS 구독 기능 설계
└── archive/                     # 아카이브 문서
    └── TASKS-v1-initial.md      # 초기 작업 계획표
```

---

## 핵심 문서

| 문서 | 설명 |
|------|------|
| [CLAUDE.md](../CLAUDE.md) | **Claude 작업 가이드** - 작업 규칙 및 문서 업데이트 지침 |
| [tasks/TASKS-MAIN.md](./tasks/TASKS-MAIN.md) | **마스터 작업 현황** - 전체 프로젝트 진행 상태 |

---

## 기술 문서

| 문서 | 설명 |
|------|------|
| [01-PROJECT-OVERVIEW.md](./01-PROJECT-OVERVIEW.md) | 프로젝트 개요 및 요구사항 정의 |
| [02-TECHNICAL-ARCHITECTURE.md](./02-TECHNICAL-ARCHITECTURE.md) | 시스템 아키텍처 및 기술 설계 |
| [03-ENVIRONMENT-SETUP.md](./03-ENVIRONMENT-SETUP.md) | 로컬 WordPress 개발 환경 구축 가이드 |
| [04-PLUGIN-SPECIFICATIONS.md](./04-PLUGIN-SPECIFICATIONS.md) | 플러그인 상세 개발 명세서 |
| [05-AI-INTEGRATION.md](./05-AI-INTEGRATION.md) | AI 모델 통합 및 프롬프트 시스템 |
| [06-DEVELOPMENT-ROADMAP.md](./06-DEVELOPMENT-ROADMAP.md) | 개발 로드맵 및 마일스톤 |
| [07-AI-IMAGE-GENERATION.md](./07-AI-IMAGE-GENERATION.md) | AI 이미지 생성 기능 계획 (Gemini Imagen) |

---

## 작업 문서

| 문서 | 설명 | 상태 |
|------|------|------|
| [tasks/TASKS-MAIN.md](./tasks/TASKS-MAIN.md) | 마스터 작업 현황 | 활성 |
| [tasks/TASKS-RSS-FEATURE.md](./tasks/TASKS-RSS-FEATURE.md) | RSS 구독 기능 Task (25개) | 대기 |

---

## 설계 문서

| 문서 | 설명 |
|------|------|
| [features/RSS-SUBSCRIPTION-DESIGN.md](./features/RSS-SUBSCRIPTION-DESIGN.md) | RSS 구독 기능 설계서 |

---

## 핵심 기능

### 구현 완료 ✅
- **콘텐츠 입력**: URL 추출 / 텍스트 직접 입력
- **AI 재작성**: ChatGPT-5, Gemini-3 모델 지원
- **언어 변환**: 영어 → 한국어 번역 및 재구성
- **자동화**: 스케줄 기반 자동 게시글 생성
- **메타데이터**: 제목, 태그, SEO 설명 자동 생성
- **커스텀 프롬프트**: 글로벌 설정에서 프롬프트 템플릿 관리

### 개발 예정 ⬜
- **RSS 구독**: 외부 블로그 RSS 피드 구독 및 자동 재작성
- **AI 이미지 생성**: Gemini Imagen으로 게시글에 맞는 이미지 자동 생성 및 삽입

---

## 개발 환경

```
프로젝트 경로: /Users/hansync/Dropbox/Project2025-dev/wordpress/
플러그인 경로: wp-content/plugins/ai-content-rewriter/
WordPress URL: http://localhost:8080
관리자 계정: admin / admin123
```

---

## 기술 스택

| 구성요소 | 버전 |
|----------|------|
| WordPress | 6.9 |
| PHP | 8.2.30 |
| MySQL | 9.5.0 |
| Nginx | 1.29.4 |
| AI API | OpenAI GPT-5, Google Gemini 3 |

---

## 시작하기

### 새 기능 개발 시
1. [tasks/TASKS-MAIN.md](./tasks/TASKS-MAIN.md)에서 현재 상황 확인
2. `features/` 폴더에 설계 문서 작성
3. `tasks/` 폴더에 Task 문서 작성
4. 순차적으로 개발 진행

### 작업 시 필수 사항
- 작업 시작 시: Task 문서에서 해당 태스크를 🔄 진행중으로 변경
- 작업 완료 시: Task 문서에서 해당 태스크를 ✅ 완료로 변경
- 코드 구현 시: 관련 기술 문서에 실제 구현 내용 반영

---

## 변경 로그

| 날짜 | 변경 내용 |
|------|----------|
| 2026-01-05 | AI 이미지 생성 기능 계획 문서 추가 (07-AI-IMAGE-GENERATION.md) |
| 2025-12-29 | 문서 구조 재편성, RSS 기능 계획 추가 |
| 2025-12-28 | 초기 문서 생성, Phase 1-3 완료 |

---
*프로젝트 시작일: 2025-12-28*
