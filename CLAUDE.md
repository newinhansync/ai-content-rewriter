# CLAUDE.md - AI Content Rewriter Plugin 개발 가이드

이 파일은 Claude Code가 이 프로젝트에서 작업할 때 따라야 할 지침입니다.

---

## ⛔ 최우선 규칙: WordPress Core 수정 금지

> **이 규칙은 모든 다른 규칙보다 우선합니다.**

```
❌ 절대 수정 금지 대상:
├── wp-admin/          ← WordPress 관리자 코어
├── wp-includes/       ← WordPress 핵심 라이브러리
├── wp-config.php      ← WordPress 설정 파일
├── wp-*.php           ← WordPress 루트의 모든 PHP 파일
└── wp-content/themes/ ← 테마 파일 (별도 지시 없는 한)

✅ 수정 가능 대상:
└── wp-content/plugins/ai-content-rewriter/  ← 오직 이 플러그인만!
```

**이유:**
- WordPress Core는 테스트 환경일 뿐, 개발 대상이 아닙니다
- 플러그인은 다른 WordPress 사이트에 배포되므로, Core 의존성이 있으면 안 됩니다
- Core 수정은 WordPress 업데이트 시 덮어쓰기되어 사라집니다

**위반 시:** 작업을 즉시 중단하고 사용자에게 확인을 요청하세요.

---

## 프로젝트 개요

- **프로젝트명**: AI Content Rewriter (WordPress Plugin)
- **목적**: URL/텍스트를 AI(ChatGPT, Gemini)로 재작성하여 블로그 게시글 자동 생성
- **프로젝트 경로**: `/Users/hansync/Dropbox/Project2025-dev/wordpress`

### 프로젝트 구조 이해

```
이 프로젝트의 핵심은 WordPress 플러그인 개발입니다.

┌─────────────────────────────────────────────────────────┐
│  wordpress/                                             │
│  ├── wp-admin/, wp-includes/, wp-config.php  ← 테스트 환경 │
│  │   (WordPress Core - 수정 대상 아님)                    │
│  │                                                      │
│  └── wp-content/plugins/                                │
│      └── ai-content-rewriter/  ← 개발 대상 (플러그인)      │
│          이것이 실제 개발하는 코드입니다!                    │
└─────────────────────────────────────────────────────────┘

WordPress는 플러그인을 테스트하기 위한 베이스 환경일 뿐입니다.
플러그인 개발이 완료되면 /pack 명령어로 ZIP 패키징하여 배포합니다.
```

### 개발 대상
- **개발 폴더**: `wp-content/plugins/ai-content-rewriter/`
- **테스트 환경**: WordPress (http://localhost:8080)
- **배포 결과물**: `dist/ai-content-rewriter-{버전}.zip`

## 필수 규칙

### 0. 문서 저장 경로 (중요!)

**모든 문서는 반드시 프로젝트 루트의 `docs/` 폴더에 저장합니다:**

```
✅ 올바른 경로: /Users/hansync/Dropbox/Project2025-dev/wordpress/docs/
❌ 잘못된 경로: /Users/hansync/Dropbox/Project2025-dev/wordpress/wp-content/plugins/ai-content-rewriter/docs/
```

플러그인 폴더 내에 docs 폴더를 만들지 마세요. 플러그인 폴더는 배포용 코드만 포함해야 합니다.

### 1. 문서 업데이트 의무

**모든 작업 수행 시 관련 문서를 반드시 업데이트해야 합니다:**

```
작업 완료 후 필수 업데이트:
1. TASKS.md - 해당 태스크 상태를 ✅ 완료로 변경
2. 관련 기술 문서 - 구현 내용 반영
3. 06-DEVELOPMENT-ROADMAP.md - 완료 기준 체크박스 업데이트
```

### 2. 작업 흐름

```
1. TASKS.md에서 현재 Phase의 다음 대기 태스크 확인
2. 태스크 상태를 🔄 진행중으로 변경
3. 작업 수행
4. 코드 작성 시 해당 기술 문서(02~05) 참조
5. 완료 후 TASKS.md 상태를 ✅ 완료로 변경
6. 관련 문서에 실제 구현 내용 반영
```

### 3. 태스크 상태 표기

| 상태 | 표기 | 설명 |
|------|------|------|
| 대기 | ⬜ | 아직 시작하지 않음 |
| 진행중 | 🔄 | 현재 작업 중 |
| 완료 | ✅ | 작업 완료 및 검증됨 |
| 차단됨 | 🚫 | 선행 작업 필요 또는 이슈 발생 |
| 보류 | ⏸️ | 일시 중단 |

## 디렉토리 구조

```
wordpress/
├── CLAUDE.md                    # 이 파일 (작업 가이드)
├── TASKS.md                     # 작업 계획표 (진행 상황 추적)
├── .claude/
│   └── commands/
│       └── pack.md             # /pack 커맨드 (플러그인 패키징)
├── dist/                        # 배포용 ZIP 파일 저장
│   ├── ai-content-rewriter-{버전}.zip
│   └── ai-content-rewriter-latest.zip
├── docs/                        # 모든 문서는 여기에 저장!
│   ├── README.md               # 문서 인덱스
│   ├── 01-PROJECT-OVERVIEW.md  # 프로젝트 개요
│   ├── 02-TECHNICAL-ARCHITECTURE.md  # 기술 아키텍처
│   ├── 03-ENVIRONMENT-SETUP.md # 환경 구축 가이드
│   ├── 04-PLUGIN-SPECIFICATIONS.md   # 플러그인 명세
│   ├── 05-AI-INTEGRATION.md    # AI 통합 문서
│   ├── 06-DEVELOPMENT-ROADMAP.md     # 개발 로드맵
│   ├── 07-AI-IMAGE-GENERATION.md     # AI 이미지 생성
│   ├── 08-PROMPT-MANAGEMENT-REFACTOR.md  # 프롬프트 관리 리팩토링
│   └── 09-AUTOMATION-IMPLEMENTATION.md  # 자동화 기능 구현
│
├── wp-admin/                    # WordPress Core
├── wp-content/
│   └── plugins/
│       └── ai-content-rewriter/ # 플러그인 코드 (v1.2.0)
│           ├── ai-content-rewriter.php  # 메인 플러그인 파일
│           ├── build.sh         # 빌드 스크립트
│           ├── readme.txt       # WP 플러그인 문서
│           ├── uninstall.php    # 삭제 시 정리
│           ├── assets/          # CSS, JS
│           ├── languages/       # 번역 파일
│           └── src/             # PHP 소스코드
│               ├── Admin/       # 관리자 UI
│               ├── AI/          # ChatGPT, Gemini 어댑터
│               ├── Content/     # 콘텐츠 처리
│               ├── Core/        # Plugin, Activator
│               ├── Database/    # Schema
│               ├── RSS/         # 피드 관리
│               ├── Schedule/    # 스케줄러
│               └── Security/    # 보안
├── wp-includes/
└── wp-config.php
```

## 코딩 표준

### PHP

- **버전**: PHP 8.0+
- **표준**: WordPress Coding Standards
- **네임스페이스**: `AIContentRewriter\*`
- **오토로딩**: PSR-4

```php
<?php
// 파일 시작 예시
namespace AIContentRewriter\Content;

class ContentRewriter {
    // ...
}
```

### 파일 명명 규칙

- 클래스 파일: `{ClassName}.php` (예: `ContentRewriter.php`)
- 인터페이스: `{InterfaceName}.php` (예: `AIAdapterInterface.php`)
- PSR-4 오토로딩 사용

## 작업별 참조 문서

| 작업 유형 | 참조 문서 |
|----------|----------|
| 환경 구축 | `03-ENVIRONMENT-SETUP.md` |
| 플러그인 구조 | `02-TECHNICAL-ARCHITECTURE.md` |
| UI 개발 | `04-PLUGIN-SPECIFICATIONS.md` |
| AI 연동 | `05-AI-INTEGRATION.md` |
| 전체 진행 | `TASKS.md`, `06-DEVELOPMENT-ROADMAP.md` |

## 문서 업데이트 규칙

### 코드 구현 시

구현한 코드가 문서의 예시 코드와 다를 경우, **문서를 실제 코드에 맞게 업데이트**합니다:

```markdown
<!-- 문서 업데이트 예시 -->

## 기존 문서
```php
// 예시 코드
class Example { }
```

## 업데이트 후 (실제 구현 반영)
```php
// 실제 구현된 코드
class Example {
    private string $property;
    // ...
}
```
```

### 완료 기준 체크 시

`06-DEVELOPMENT-ROADMAP.md`의 완료 기준 체크박스를 업데이트합니다:

```markdown
<!-- Before -->
- [ ] WordPress가 http://localhost:8080 에서 정상 동작

<!-- After -->
- [x] WordPress가 http://localhost:8080 에서 정상 동작
```

## 의존성 관리

### 선행 작업 확인

작업 시작 전 `TASKS.md`에서 의존성을 확인합니다:

```
Task 2.1 (URL 콘텐츠 추출기)
├── 의존: Task 1.3 (플러그인 기본 구조) ✅
├── 의존: Task 1.4 (오토로더) ✅
└── 상태: 시작 가능
```

### 차단된 작업 처리

선행 작업이 완료되지 않은 경우:
1. 해당 태스크를 🚫 차단됨으로 표시
2. 차단 사유 기록
3. 선행 작업 먼저 완료

## 테스트 요구사항

### 🎭 필수: Playwright MCP를 통한 E2E 테스트

**모든 UI 기능 개발 완료 후 반드시 Playwright MCP를 사용하여 E2E 테스트를 진행해야 합니다.**

```
E2E 테스트 진행 절차:
1. mcp__playwright__browser_navigate로 테스트 페이지 접근
2. mcp__playwright__browser_snapshot으로 페이지 구조 확인
3. mcp__playwright__browser_click으로 버튼/링크 클릭 테스트
4. mcp__playwright__browser_type으로 폼 입력 테스트
5. 각 단계별 결과 확인 및 스크린샷 캡처
```

**테스트 대상:**
- [ ] 설정 페이지: API 키 저장, 옵션 변경
- [ ] 새 콘텐츠: URL/텍스트 입력 → AI 재작성 → 게시글 생성
- [ ] 피드 관리: 피드 추가/편집/삭제/새로고침
- [ ] 피드 리더: 미리보기/재작성 버튼 동작
- [ ] 프롬프트 템플릿: 템플릿 추가/편집/삭제

### ⚠️ 필수: UI 버튼 및 기능 테스트 (중요)

**작업 완료 전 반드시 모든 UI 요소의 동작을 검증해야 합니다.**

#### 1. HTML-JavaScript ID 일치 확인

버튼, 폼 필드, 모달 등의 ID가 HTML과 JavaScript에서 **완전히 일치**하는지 확인:

```
검증 체크리스트:
- [ ] 버튼 ID: HTML의 id="xxx"와 JS의 $('#xxx') 또는 document.on('click', '#xxx') 일치
- [ ] 폼 필드 ID: HTML의 id/name과 JS에서 값 읽는 셀렉터 일치
- [ ] 모달 ID: 열기/닫기 버튼의 타겟 모달 ID 일치
```

**예시 - 흔한 실수:**
```javascript
// HTML: id="aicr-rewrite-start"
// JS (잘못됨): $('#aicr-rewrite-submit').click()  ← ID 불일치!
// JS (올바름): $('#aicr-rewrite-start').click()
```

#### 2. AJAX 핸들러 검증

```
검증 체크리스트:
- [ ] PHP AJAX action이 등록되어 있는지 (add_action('wp_ajax_xxx'))
- [ ] JS에서 호출하는 action 이름과 PHP의 action 이름 일치
- [ ] 전송하는 데이터 키와 PHP에서 받는 키 일치
- [ ] nonce 검증 정상 동작
```

#### 3. 버튼별 기능 테스트

각 버튼에 대해 다음을 확인:

```
- [ ] 버튼 클릭 시 이벤트 핸들러 호출됨
- [ ] 로딩 상태 표시 (버튼 비활성화, 텍스트 변경)
- [ ] AJAX 요청 전송됨
- [ ] 성공/실패 응답 처리
- [ ] 로딩 상태 해제
- [ ] UI 업데이트 (상태 변경, 모달 닫기 등)
```

#### 4. 폼 필드 검증

```
- [ ] 필수 필드 validation
- [ ] 폼 데이터가 AJAX로 올바르게 전송됨
- [ ] hidden 필드 값 설정됨
- [ ] select/radio/checkbox 값 정확히 읽어옴
```

#### 5. 모달 동작 검증

```
- [ ] 모달 열기 버튼 동작
- [ ] 모달 닫기 버튼 동작 (X 버튼, 취소 버튼)
- [ ] 모달 외부 클릭 시 닫힘 (필요한 경우)
- [ ] 모달 내 폼 필드 초기화/설정
```

### 단위 테스트

새 클래스 작성 시 해당 테스트 파일도 함께 생성:

```
src/Content/class-url-fetcher.php
→ tests/unit/UrlFetcherTest.php
```

### 검증 체크리스트

코드 작성 후 확인:
- [ ] WordPress Coding Standards 준수
- [ ] 보안 함수 사용 (esc_*, sanitize_*, wp_nonce_*)
- [ ] 적절한 권한 체크
- [ ] 에러 핸들링
- [ ] **모든 UI 버튼/폼 기능 테스트 완료** ← 필수!

## 커밋 메시지 형식

```
<type>(<scope>): <subject>

[optional body]

[optional footer]
```

**타입:**
- `feat`: 새 기능
- `fix`: 버그 수정
- `docs`: 문서 업데이트
- `refactor`: 리팩토링
- `test`: 테스트 추가
- `chore`: 빌드, 설정 변경

**예시:**
```
feat(content): URL 콘텐츠 추출기 구현

- UrlFetcher 클래스 추가
- ContentParser 구현
- DOM 기반 본문 추출 로직

Closes #1
```

## 중요 주의사항

### 절대 하지 말 것

1. ❌ TASKS.md 업데이트 없이 작업 완료 처리
2. ❌ 선행 작업 미완료 상태에서 다음 작업 진행
3. ❌ 테스트 없이 핵심 로직 구현
4. ❌ 문서와 코드 불일치 방치

### 항상 할 것

1. ✅ 작업 전 TASKS.md에서 현재 상태 확인
2. ✅ 작업 시작 시 상태를 🔄로 변경
3. ✅ 완료 시 문서 업데이트
4. ✅ 의존성 있는 작업 확인

## 문의 및 결정 사항

### 불확실한 사항 발생 시

1. 관련 기술 문서 참조
2. `docs/` 폴더의 명세 확인
3. 결정이 필요한 경우 사용자에게 질문

### 기술적 결정 기록

중요한 기술적 결정 시 해당 문서에 기록:

```markdown
## 기술적 결정 로그

### 2025-12-28: AI 어댑터 패턴 선택
- **결정**: Strategy 패턴 대신 Adapter 패턴 사용
- **이유**: WordPress 플러그인 구조와 더 자연스러운 통합
- **영향**: `05-AI-INTEGRATION.md` 구조 반영
```

---

## 빠른 참조

### 환경 시작
```bash
brew services start mysql nginx php@8.2
open http://localhost:8080/wp-admin
```

### 플러그인 테스트
```bash
cd /Users/hansync/Dropbox/Project2025-dev/wordpress
wp plugin activate ai-content-rewriter
wp plugin deactivate ai-content-rewriter
```

### 플러그인 패키징 (배포용 ZIP 생성)
```bash
# 방법 1: Claude Code 커맨드 사용
/pack

# 방법 2: 직접 스크립트 실행
./wp-content/plugins/ai-content-rewriter/build.sh

# 생성 위치: /dist/ai-content-rewriter-{버전}.zip
```

### 디버그 로그
```bash
tail -f wp-content/debug.log
```

---

## 플러그인 현재 상태 (v1.2.0)

### 주요 기능
- **AI 콘텐츠 재작성**: URL/텍스트를 AI로 SEO 최적화된 블로그 글로 변환
- **RSS 피드 리더**: RSS 피드에서 자동으로 콘텐츠 수집 및 재작성
- **비동기 처리**: 백그라운드에서 AI 처리 (타임아웃 문제 해결)
- **자동 카테고리**: AI가 글 내용에 맞는 카테고리 자동 생성/선택
- **1.5배 확장 콘텐츠**: 원본보다 1.5배 긴 상세한 콘텐츠 생성
- **SEO 메타 자동 생성**: 제목, 설명, 키워드, 태그 자동 생성
- **자동화 대시보드**: Cron 상태 모니터링, 외부 Cron 설정 가이드, 실행 이력 로깅

### 지원 AI 모델
- **OpenAI**: GPT-4o, GPT-4o-mini, GPT-4-turbo, GPT-4, GPT-3.5-turbo, o1, o1-mini
- **Google**: Gemini Pro, Gemini 1.5 Pro, Gemini 1.5 Flash

### 배포 패키지
```
dist/
├── ai-content-rewriter-1.2.0.zip  # 버전별 패키지
└── ai-content-rewriter-latest.zip # 최신 버전 링크
```

### 설치 방법
1. WordPress 관리자 > 플러그인 > 새로 추가 > 플러그인 업로드
2. ZIP 파일 선택 후 "지금 설치"
3. 플러그인 활성화
4. AI Rewriter > Settings에서 API 키 설정

### DB 테이블 (자동 생성)
- `aicr_feeds` - RSS 피드 목록
- `aicr_feed_items` - 피드 아이템
- `aicr_history` - 재작성 기록
- `aicr_templates` - 프롬프트 템플릿
- `aicr_schedules` - 예약 작업
- `aicr_api_usage` - API 사용량
- `aicr_cron_logs` - Cron 실행 이력

---

## Claude Code 커맨드

이 프로젝트에서 사용 가능한 커맨드:

| 커맨드 | 설명 |
|--------|------|
| `/pack` | AI Content Rewriter 플러그인을 배포용 ZIP으로 패키징 |

---
*최종 업데이트: 2026-01-07*
