# 프롬프트 관리 시스템 리팩토링 계획서

**작성일**: 2026-01-07
**버전**: v1.0.12 → v1.1.0
**상태**: 완료 (2026-01-07)

---

## 1. 현재 상황 분석

### 1.1 문제점

| 문제 | 설명 |
|------|------|
| **템플릿 미사용** | 템플릿 메뉴에서 생성한 프롬프트가 실제 콘텐츠 변환에 사용되지 않음 |
| **하드코딩된 프롬프트** | `PromptManager.php`의 `DEFAULT_TEMPLATES` 상수에 프롬프트가 고정됨 |
| **불필요한 복잡성** | 템플릿 DB 테이블, 클래스, UI가 있지만 실질적 기능 없음 |
| **사용자 혼란** | 템플릿 메뉴가 있지만 동작하지 않아 사용자 혼란 야기 |

### 1.2 현재 파일 구조

```
삭제 대상:
├── src/Admin/views/templates.php      # 템플릿 페이지 UI
├── src/Content/PromptTemplate.php     # 프롬프트 템플릿 클래스
└── (DB) aicr_templates 테이블          # 템플릿 저장 테이블

수정 대상:
├── src/Admin/AdminMenu.php            # 템플릿 메뉴 제거
├── src/Admin/AjaxHandler.php          # 템플릿 AJAX 핸들러 제거
├── src/Content/PromptManager.php      # wp_options 기반으로 변경
├── src/Admin/views/settings.php       # 프롬프트 관리 탭 추가
└── src/Database/Schema.php            # 템플릿 테이블 생성 코드 제거
```

### 1.3 현재 프롬프트 흐름

```
[현재 - 비정상]
PromptManager.php (DEFAULT_TEMPLATES 상수)
       ↓
SharedRewriteProcessor.php → build_prompt('blog_post', ...)
       ↓
AI API 호출

※ 템플릿 메뉴에서 만든 프롬프트는 전혀 사용되지 않음
```

---

## 2. 개선 목표

### 2.1 핵심 변경사항

1. **템플릿 기능 완전 제거** - 메뉴, 코드, DB 테이블 모두 삭제
2. **설정 > 프롬프트 관리** - 설정 페이지에 프롬프트 관리 탭 추가
3. **wp_options 기반 저장** - 프롬프트를 `aicr_prompt_blog_post` 옵션으로 저장
4. **실시간 적용** - 저장된 프롬프트가 즉시 콘텐츠 변환에 사용됨

### 2.2 새로운 프롬프트 흐름

```
[개선 후]
설정 > 프롬프트 관리 (UI)
       ↓
wp_options 테이블 (aicr_prompt_blog_post)
       ↓
PromptManager.php → get_prompt()
       ↓
SharedRewriteProcessor.php
       ↓
AI API 호출
```

---

## 3. 구현 계획

### Phase 1: 템플릿 기능 제거

#### Task 1.1: 메뉴 제거
**파일**: `src/Admin/AdminMenu.php`

```php
// 삭제할 코드
add_submenu_page(
    self::MENU_SLUG,
    __('프롬프트 템플릿', 'ai-content-rewriter'),
    __('템플릿', 'ai-content-rewriter'),
    'manage_options',
    self::MENU_SLUG . '-templates',
    [$this, 'render_templates_page']
);

// 삭제할 메서드
public function render_templates_page(): void { ... }
```

#### Task 1.2: 템플릿 뷰 파일 삭제
**파일**: `src/Admin/views/templates.php` - 전체 삭제

#### Task 1.3: PromptTemplate 클래스 삭제
**파일**: `src/Content/PromptTemplate.php` - 전체 삭제

#### Task 1.4: AJAX 핸들러에서 템플릿 관련 코드 제거
**파일**: `src/Admin/AjaxHandler.php`

삭제할 메서드:
- `handle_save_template()`
- `handle_delete_template()`
- `handle_get_templates()`

#### Task 1.5: DB 스키마에서 템플릿 테이블 제거
**파일**: `src/Database/Schema.php`

삭제할 메서드:
- `create_templates_table()`

---

### Phase 2: 프롬프트 관리 UI 구현

#### Task 2.1: 설정 페이지에 프롬프트 탭 추가
**파일**: `src/Admin/views/settings.php`

```php
// 탭 구조
<nav class="nav-tab-wrapper">
    <a class="nav-tab" href="#general">일반</a>
    <a class="nav-tab" href="#api">API 설정</a>
    <a class="nav-tab nav-tab-active" href="#prompt">프롬프트 관리</a>  <!-- 새로 추가 -->
</nav>

// 프롬프트 관리 탭 내용
<div id="prompt" class="tab-content">
    <h2>블로그 포스트 생성 프롬프트</h2>
    <p class="description">콘텐츠 변환 시 AI에게 전달되는 프롬프트입니다.</p>

    <textarea name="aicr_prompt_blog_post" rows="30" class="large-text code">
        {{현재 프롬프트}}
    </textarea>

    <h3>사용 가능한 변수</h3>
    <ul>
        <li><code>{{content}}</code> - 원본 콘텐츠</li>
        <li><code>{{title}}</code> - 원본 제목</li>
        <li><code>{{source_url}}</code> - 원본 URL</li>
        <li><code>{{target_language}}</code> - 출력 언어</li>
    </ul>

    <button type="button" class="button" id="reset-prompt">기본값으로 복원</button>
</div>
```

#### Task 2.2: 프롬프트 저장/로드 AJAX 핸들러
**파일**: `src/Admin/AjaxHandler.php`

```php
// 새로 추가할 메서드
public function handle_save_prompt(): void {
    // wp_options에 프롬프트 저장
    update_option('aicr_prompt_blog_post', sanitize_textarea_field($_POST['prompt']));
}

public function handle_reset_prompt(): void {
    // 기본 프롬프트로 복원
    delete_option('aicr_prompt_blog_post');
}
```

---

### Phase 3: PromptManager 리팩토링

#### Task 3.1: wp_options 기반으로 변경
**파일**: `src/Content/PromptManager.php`

```php
class PromptManager {
    /**
     * 기본 프롬프트 (옵션에 없을 때 사용)
     */
    private const DEFAULT_PROMPT = '...현재 blog_post 프롬프트...';

    /**
     * 프롬프트 가져오기
     */
    public function get_prompt(): string {
        $saved_prompt = get_option('aicr_prompt_blog_post');
        return $saved_prompt ?: self::DEFAULT_PROMPT;
    }

    /**
     * 프롬프트에 변수 적용
     */
    public function build_prompt(array $variables): string {
        $prompt = $this->get_prompt();

        foreach ($variables as $key => $value) {
            $prompt = str_replace('{{' . $key . '}}', $value, $prompt);
        }

        return $prompt;
    }

    /**
     * 기본 프롬프트 반환 (복원용)
     */
    public function get_default_prompt(): string {
        return self::DEFAULT_PROMPT;
    }
}
```

#### Task 3.2: SharedRewriteProcessor 수정
**파일**: `src/Content/SharedRewriteProcessor.php`

```php
// 기존
$prompt = $prompt_manager->build_prompt(
    'blog_post',
    [
        'content' => $content_data['content'],
        'title' => $content_data['title'],
        'target_language' => $language,
        'source_url' => $content_data['source_url'],
    ],
    $template_id ?: null
);

// 변경 후
$prompt = $prompt_manager->build_prompt([
    'content' => $content_data['content'],
    'title' => $content_data['title'],
    'target_language' => $language,
    'source_url' => $content_data['source_url'],
]);
```

---

### Phase 4: 정리 작업

#### Task 4.1: 불필요한 코드 제거
- `PromptTemplate.php` 참조 제거
- `use` 문 정리
- 미사용 변수 제거

#### Task 4.2: JavaScript 수정
**파일**: `assets/js/admin.js`
- 템플릿 관련 JS 코드 제거
- 프롬프트 저장/복원 기능 추가

#### Task 4.3: CSS 수정
**파일**: `assets/css/admin.css`
- 템플릿 관련 스타일 제거 (선택적)

---

## 4. 데이터베이스 변경

### 4.1 삭제할 테이블
```sql
DROP TABLE IF EXISTS {prefix}aicr_templates;
```

### 4.2 새로운 wp_options 항목
| 옵션명 | 설명 | 기본값 |
|--------|------|--------|
| `aicr_prompt_blog_post` | 블로그 포스트 생성 프롬프트 | DEFAULT_PROMPT 상수값 |

---

## 5. UI/UX 설계

### 5.1 설정 페이지 탭 구조

```
설정
├── 일반 설정
│   ├── 기본 AI 제공자
│   ├── 기본 언어
│   └── 게시글 상태
├── API 설정
│   ├── OpenAI API Key
│   ├── OpenAI 모델
│   ├── Google API Key
│   └── Gemini 모델
└── 프롬프트 관리 (신규)
    ├── 프롬프트 편집기 (textarea)
    ├── 사용 가능한 변수 안내
    └── 기본값 복원 버튼
```

### 5.2 프롬프트 편집기 UI

```
┌─────────────────────────────────────────────────────────┐
│  블로그 포스트 생성 프롬프트                               │
│  ─────────────────────────────────────────────────────  │
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │ 당신은 전문 블로그 콘텐츠 작성자입니다.             │   │
│  │ 다음 원본 콘텐츠를 분석하여...                      │   │
│  │                                                   │   │
│  │ ## 원본 콘텐츠                                    │   │
│  │ 제목: {{title}}                                  │   │
│  │ ...                                              │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  📌 사용 가능한 변수                                     │
│  ┌─────────────────────────────────────────────────┐   │
│  │ {{content}} - 원본 콘텐츠                         │   │
│  │ {{title}} - 원본 제목                            │   │
│  │ {{source_url}} - 원본 URL                        │   │
│  │ {{target_language}} - 출력 언어                   │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  [기본값으로 복원]                     [변경사항 저장]    │
└─────────────────────────────────────────────────────────┘
```

---

## 6. 테스트 체크리스트

### 6.1 기능 테스트
- [ ] 템플릿 메뉴가 더 이상 표시되지 않음
- [ ] 설정 페이지에 '프롬프트 관리' 탭 표시됨
- [ ] 프롬프트 수정 후 저장 정상 동작
- [ ] 저장된 프롬프트가 콘텐츠 변환에 적용됨
- [ ] '기본값으로 복원' 버튼 정상 동작
- [ ] 새 콘텐츠 페이지에서 재작성 정상 동작
- [ ] RSS 피드 리더에서 재작성 정상 동작

### 6.2 마이그레이션 테스트
- [ ] 기존 설치 환경에서 업데이트 시 정상 동작
- [ ] 기본 프롬프트가 올바르게 로드됨
- [ ] 템플릿 테이블이 있어도 에러 없음 (DROP은 선택적)

---

## 7. 작업 순서

| 순서 | 작업 | 예상 시간 | 의존성 |
|------|------|----------|--------|
| 1 | AdminMenu.php에서 템플릿 메뉴 제거 | 5분 | - |
| 2 | templates.php 파일 삭제 | 1분 | 1 |
| 3 | PromptTemplate.php 파일 삭제 | 1분 | - |
| 4 | AjaxHandler.php에서 템플릿 관련 코드 제거 | 10분 | - |
| 5 | PromptManager.php 리팩토링 | 20분 | 3 |
| 6 | settings.php에 프롬프트 관리 탭 추가 | 30분 | 5 |
| 7 | SharedRewriteProcessor.php 수정 | 10분 | 5 |
| 8 | admin.js에 프롬프트 저장 로직 추가 | 15분 | 6 |
| 9 | 테스트 및 디버깅 | 30분 | 1-8 |
| 10 | 문서 업데이트 및 커밋 | 10분 | 9 |

**총 예상 시간**: 약 2시간

---

## 8. 버전 정보

- **현재 버전**: v1.0.12
- **목표 버전**: v1.1.0 (기능 변경이므로 마이너 버전 업)
- **하위 호환성**: 기존 사용자의 데이터 손실 없음 (기본 프롬프트 자동 적용)

---

## 9. 참고 사항

### 9.1 기본 프롬프트 보존
현재 `PromptManager.php`의 `DEFAULT_TEMPLATES['blog_post']['content']` 값을 그대로 `DEFAULT_PROMPT` 상수로 이동합니다.

### 9.2 향후 확장 가능성
- 여러 프롬프트 타입 지원 (blog_post, product_review 등)
- 프롬프트 히스토리/버전 관리
- 프롬프트 가져오기/내보내기

---

*최종 수정: 2026-01-07*
