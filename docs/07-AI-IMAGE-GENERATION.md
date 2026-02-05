# Part 7: AI 이미지 생성 기능 계획

## 7.1 개요

### 7.1.1 기능 목적

게시글 작성/재작성 후 **버튼 1개**로 AI 이미지를 자동 생성하여 콘텐츠에 삽입하는 기능.
Gemini의 Imagen 모델을 활용하여 게시글 내용에 맞는 이미지를 생성한다.

### 7.1.2 통합 vs 분리 결정

**결정: ai-content-rewriter 플러그인에 통합**

| 기준 | 통합 | 분리 |
|------|------|------|
| 설정 관리 | ✅ 단일 설정 페이지 | ❌ 중복 설정 필요 |
| API 키 관리 | ✅ Gemini 키 공유 | ❌ 별도 키 관리 |
| 사용자 경험 | ✅ 일관된 워크플로우 | ❌ 플러그인 간 이동 |
| 코드 재사용 | ✅ GeminiAdapter 확장 | ❌ 중복 코드 |
| 유지보수 | ✅ 단일 코드베이스 | ❌ 두 플러그인 관리 |

**결론**: 이미지 생성은 콘텐츠 재작성의 자연스러운 확장이므로 통합이 합리적.

### 7.1.3 핵심 기능

1. **원클릭 이미지 생성**: 게시글 편집 화면에서 버튼 1개로 이미지 생성
2. **다중 이미지 생성**: 1~5개 이미지 옵션 선택 가능
3. **단락 기반 분할**: 이미지 개수에 따라 게시글을 주제별로 분할하여 각 섹션에 맞는 이미지 생성
4. **자동 삽입**: 생성된 이미지를 해당 단락 사이에 자동 배치
5. **스타일 커스터마이징**: 설정에서 이미지 스타일과 프롬프트 템플릿 관리
6. **프롬프트 관리**: 설정 페이지에서 이미지 생성 프롬프트 직접 편집 가능
7. **스케줄 자동 실행**: Cron으로 이미지 없는 게시글에 자동으로 이미지 생성
8. **대표 이미지 자동 설정**: 첫 번째 생성 이미지를 WordPress Featured Image로 자동 설정
9. **스킵 로직**: 이미 이미지가 있는 게시글은 자동으로 건너뜀

### 7.1.4 UI 사용 위치

#### 주요 사용 지점: 게시글 편집 화면

**경로**: WordPress 관리자 → 글 → 새 글 추가 / 편집

```
┌─────────────────────────────────────────────────────────────────────┐
│  글 편집                                                            │
├─────────────────────────────────────────────────────────────────────┤
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │  제목: 블로그 포스트 제목                                      │ │
│  └───────────────────────────────────────────────────────────────┘ │
│                                                                     │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │                                                               │ │
│  │  본문 콘텐츠 에디터 (Gutenberg/Classic)                       │ │
│  │                                                               │ │
│  │  [단락 1: 도입부 텍스트...]                                   │ │
│  │                                                               │ │
│  │  ┌─────────────────────────────────────────────────────────┐ │ │
│  │  │  🖼️ AI 생성 이미지 1                                    │ │ │
│  │  └─────────────────────────────────────────────────────────┘ │ │
│  │                                                               │ │
│  │  [단락 2: 본문 텍스트...]                                     │ │
│  │                                                               │ │
│  │  ┌─────────────────────────────────────────────────────────┐ │ │
│  │  │  🖼️ AI 생성 이미지 2                                    │ │ │
│  │  └─────────────────────────────────────────────────────────┘ │ │
│  │                                                               │ │
│  │  [단락 3: 결론 텍스트...]                                     │ │
│  │                                                               │ │
│  └───────────────────────────────────────────────────────────────┘ │
│                                                                     │
│  ┌───────────────────────────┐  ┌─────────────────────────────────┐│
│  │  📝 AI Content Rewriter   │  │  🎨 AI 이미지 생성              ││
│  │  ───────────────────────  │  │  ───────────────────────────── ││
│  │  [재작성] [번역]          │  │                                 ││
│  └───────────────────────────┘  │  이미지 개수:                   ││
│                                 │  ○ 1개  ● 2개  ○ 3개  ○ 4개   ││
│                                 │                                 ││
│                                 │  스타일: [일러스트레이션    ▼]  ││
│                                 │                                 ││
│                                 │  비율: ○ 16:9  ● 4:3  ○ 1:1   ││
│                                 │                                 ││
│                                 │  추가 지시사항:                 ││
│                                 │  ┌─────────────────────────┐   ││
│                                 │  │ (선택) 밝은 색감으로... │   ││
│                                 │  └─────────────────────────┘   ││
│                                 │                                 ││
│                                 │  [ 🖼️ 이미지 생성하기 ]        ││
│                                 │                                 ││
│                                 └─────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────────┘
```

**사용 흐름**:
1. 게시글 작성 또는 AI 재작성 완료
2. 우측 사이드바의 **"AI 이미지 생성"** 메타박스 확인
3. 이미지 개수, 스타일, 비율 선택
4. (선택) 추가 지시사항 입력
5. **"이미지 생성하기"** 버튼 클릭
6. 로딩 후 이미지가 본문의 단락 사이에 자동 삽입

---

#### 설정 페이지: 이미지 옵션 관리

**경로**: WordPress 관리자 → AI Content Rewriter → 설정 → 이미지 설정 탭

```
┌─────────────────────────────────────────────────────────────────────┐
│  AI Content Rewriter                                                │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  [일반설정] [API 설정] [피드관리] [템플릿] [📷 이미지 설정]        │
│                                          ▲                          │
│                                          └── 이 탭에서 관리         │
│  ─────────────────────────────────────────────────────────────────  │
│                                                                     │
│  ## 기본 설정                                                       │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │ 기본 이미지 개수:        [2개 ▼]                            │   │
│  │ 기본 가로세로 비율:      [16:9 ▼]                           │   │
│  │ 자동 대체 텍스트 생성:   [✓] AI로 alt 텍스트 자동 생성      │   │
│  │ 자동 캡션 생성:          [✓] AI로 캡션 자동 생성            │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                     │
│  ## 스타일 프리셋                                                   │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │  ★ 일러스트레이션 (기본)    ☆ 사실적 사진                  │   │
│  │  ☆ 수채화                   ☆ 미니멀 아이콘                │   │
│  │  ☆ 3D 렌더링                ☆ 플랫 디자인                  │   │
│  │                                                             │   │
│  │  [+ 새 스타일 추가]  [편집]  [삭제]                        │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                     │
│  ## 프롬프트 템플릿                                                 │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │ {{topic}}을 나타내는 {{style}} 스타일의 이미지.             │   │
│  │ 블로그 게시글에 적합한, 전문적이고 깔끔한 디자인.           │   │
│  │ {{additional_instructions}}                                 │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                     │
│                                         [변경사항 저장]             │
└─────────────────────────────────────────────────────────────────────┘
```

**관리 항목**:
- 기본값 설정 (이미지 개수, 비율)
- 스타일 프리셋 CRUD (생성/조회/수정/삭제)
- 프롬프트 템플릿 편집
- 자동 alt/캡션 생성 옵션

---

#### UI 위치 요약

| UI 위치 | WordPress 경로 | 용도 | 사용 빈도 |
|---------|---------------|------|----------|
| **게시글 메타박스** | 글 → 편집 → 우측 사이드바 | 이미지 생성 실행 | ⭐⭐⭐ 매번 |
| **설정 이미지 탭** | AI Content Rewriter → 설정 | 스타일/옵션 관리 | ⭐ 초기 설정 |

**핵심 원칙**: 사용자는 게시글 편집 화면에서 **버튼 1개**로 이미지를 생성한다.

---

## 7.2 기술 아키텍처

### 7.2.1 시스템 흐름

```
┌─────────────────────────────────────────────────────────────────────┐
│                         이미지 생성 워크플로우                         │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐             │
│  │  게시글     │    │  단락 분석   │    │  프롬프트   │             │
│  │  콘텐츠     │───▶│  & 주제추출  │───▶│  생성      │             │
│  └─────────────┘    └─────────────┘    └──────┬──────┘             │
│                                                │                    │
│                     ┌──────────────────────────┘                    │
│                     ▼                                               │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐             │
│  │  콘텐츠에    │◀───│  미디어     │◀───│  Gemini    │             │
│  │  이미지삽입  │    │  라이브러리 │    │  Imagen    │             │
│  └─────────────┘    └─────────────┘    └─────────────┘             │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### 7.2.2 클래스 구조

```
src/
├── AI/
│   ├── GeminiAdapter.php          # 기존 (텍스트 생성)
│   └── GeminiImageAdapter.php     # 신규 (이미지 생성)
│
├── Image/
│   ├── ImageGenerator.php         # 이미지 생성 통합 매니저
│   ├── ImagePromptManager.php     # 프롬프트 관리 (싱글톤) - 설정 연동
│   ├── ContentSectionizer.php     # 단락 분할 및 주제 추출
│   ├── ImageInserter.php          # 콘텐츠에 이미지 삽입
│   ├── ImageScheduler.php         # 스케줄 자동 실행
│   └── ImageStyle.php             # 스타일 설정 클래스
│
├── Admin/
│   └── views/
│       └── settings-image.php     # 이미지 설정 탭
```

### 7.2.3 데이터베이스 스키마 확장

```sql
-- 이미지 생성 이력 테이블
CREATE TABLE {prefix}aicr_image_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id BIGINT UNSIGNED NOT NULL,
    attachment_id BIGINT UNSIGNED,           -- WP 미디어 라이브러리 ID
    prompt TEXT NOT NULL,                     -- 사용된 프롬프트
    style VARCHAR(50),                        -- 이미지 스타일
    section_index INT DEFAULT 0,             -- 삽입된 섹션 인덱스
    generation_time FLOAT,                    -- 생성 소요 시간(초)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_post_id (post_id)
);

-- 이미지 스타일 프리셋 테이블
CREATE TABLE {prefix}aicr_image_styles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    style_prompt TEXT NOT NULL,              -- 스타일 지시 프롬프트
    negative_prompt TEXT,                     -- 제외할 요소
    aspect_ratio VARCHAR(20) DEFAULT '16:9', -- 가로세로 비율
    is_default BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

---

## 7.3 핵심 컴포넌트 설계

### 7.3.1 GeminiImageAdapter

```php
<?php
namespace AIContentRewriter\AI;

class GeminiImageAdapter {
    // Imagen 3 직접 사용 (권장)
    private const IMAGEN_API = 'https://generativelanguage.googleapis.com/v1beta/models/imagen-3.0-generate-002:predict';

    // 또는 Gemini 내장 이미지 생성
    // private const GEMINI_API = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-001:generateContent';

    private string $apiKey;
    private int $timeout = 60;

    /**
     * 이미지 생성 (Imagen 3 모델)
     */
    public function generateImage(string $prompt, array $options = []): ImageResponse {
        $payload = [
            'instances' => [
                ['prompt' => $prompt]
            ],
            'parameters' => [
                'sampleCount' => $options['count'] ?? 1,
                'aspectRatio' => $options['aspect_ratio'] ?? '16:9',
                'personGeneration' => $options['person_generation'] ?? 'DONT_ALLOW',
                'safetyFilterLevel' => 'BLOCK_SOME',
            ]
        ];

        $response = wp_remote_post(self::IMAGEN_API . '?key=' . $this->apiKey, [
            'timeout' => $this->timeout,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($payload),
        ]);

        // ... 응답 처리
    }

    /**
     * 이미지 편집 (인페인팅)
     */
    public function editImage(string $baseImage, string $mask, string $prompt): ImageResponse {
        // Gemini 이미지 편집 API 호출
    }
}
```

### 7.3.2 ContentSectionizer (단락 분할기)

**청크 분할 알고리즘 상세:**

```
이미지 개수: 2개인 경우

원본 콘텐츠:
┌─────────────────────────────────────┐
│ H2: 도입부                           │
│ P: 첫 번째 단락...                   │
│ P: 두 번째 단락...                   │
├─────────────────────────────────────┤  ← 이미지 1 삽입
│ H2: 본론                             │
│ P: 세 번째 단락...                   │
│ P: 네 번째 단락...                   │
├─────────────────────────────────────┤  ← 이미지 2 삽입
│ H2: 결론                             │
│ P: 다섯 번째 단락...                 │
└─────────────────────────────────────┘

분할 기준 우선순위:
1. H2 태그 기준 (있는 경우)
2. 단락 균등 분할 (H2 없는 경우)
```

**삽입 후 결과 예시:**

```html
<h2>도입부</h2>
<p>첫 번째 단락...</p>
<p>두 번째 단락...</p>

<!-- AI 생성 이미지 1 -->
<figure class="wp-block-image aicr-generated">
    <img src="..." alt="도입부 관련 이미지" />
    <figcaption>AI가 생성한 이미지</figcaption>
</figure>

<h2>본론</h2>
<p>세 번째 단락...</p>
<p>네 번째 단락...</p>

<!-- AI 생성 이미지 2 (첫 번째가 Featured Image로 설정) -->
<figure class="wp-block-image aicr-generated">
    <img src="..." alt="본론 관련 이미지" />
</figure>

<h2>결론</h2>
<p>다섯 번째 단락...</p>
```

```php
<?php
namespace AIContentRewriter\Image;

class ContentSectionizer {
    private $textAdapter; // GeminiAdapter (텍스트 분석용)

    /**
     * 콘텐츠를 N개의 섹션으로 분할하고 각 섹션의 주제 추출
     *
     * @return array [
     *   ['content' => '...', 'topic' => '...', 'keywords' => [...], 'insert_after_element' => 3],
     *   ...
     * ]
     */
    public function sectionize(string $content, int $sectionCount): array {
        // 1. H2 태그가 있는지 확인
        $hasH2 = preg_match('/<h2[^>]*>/i', $content);

        // 2. 분할 전략 선택
        if ($hasH2) {
            $sections = $this->splitByHeadings($content, $sectionCount);
        } else {
            $sections = $this->splitByParagraphs($content, $sectionCount);
        }

        // 3. 각 섹션의 주제/키워드 추출 (AI 사용)
        foreach ($sections as &$section) {
            $section['topic'] = $this->extractTopic($section['content']);
            $section['keywords'] = $this->extractKeywords($section['content']);
        }

        return $sections;
    }

    /**
     * H2 태그 기준 분할 (우선)
     */
    private function splitByHeadings(string $content, int $count): array {
        // H2로 콘텐츠 분할
        $parts = preg_split('/(?=<h2[^>]*>)/i', $content, -1, PREG_SPLIT_NO_EMPTY);
        $totalParts = count($parts);

        if ($totalParts <= $count) {
            // H2 섹션 수가 이미지 수 이하면 그대로 사용
            return array_map(fn($p) => ['content' => $p], $parts);
        }

        // H2 섹션을 N개 그룹으로 병합
        $sections = [];
        $groupSize = ceil($totalParts / $count);

        for ($i = 0; $i < $count; $i++) {
            $start = $i * $groupSize;
            $group = array_slice($parts, $start, $groupSize);
            $sections[] = ['content' => implode('', $group)];
        }

        return $sections;
    }

    /**
     * 단락 균등 분할 (H2 없는 경우)
     */
    private function splitByParagraphs(string $content, int $count): array {
        $paragraphs = $this->extractParagraphs($content);
        $totalParagraphs = count($paragraphs);

        $sections = [];
        $groupSize = max(1, ceil($totalParagraphs / $count));

        for ($i = 0; $i < $count; $i++) {
            $start = $i * $groupSize;
            $group = array_slice($paragraphs, $start, $groupSize);
            $sections[] = ['content' => implode("\n", $group)];
        }

        return $sections;
    }

    /**
     * HTML에서 단락 요소 추출
     */
    private function extractParagraphs(string $content): array {
        preg_match_all('/<p[^>]*>.*?<\/p>/is', $content, $matches);
        return $matches[0] ?? [];
    }

    /**
     * AI를 사용하여 섹션의 핵심 주제 추출
     */
    private function extractTopic(string $content): string {
        $plainText = strip_tags($content);
        $prompt = "다음 텍스트의 핵심 주제를 이미지 생성에 적합한 한 문장으로 요약해주세요:\n\n{$plainText}";

        $response = $this->textAdapter->generate($prompt);
        return $response->getContent();
    }

    /**
     * 섹션에서 키워드 추출
     */
    private function extractKeywords(string $content): array {
        $plainText = strip_tags($content);
        $prompt = "다음 텍스트에서 이미지 생성에 사용할 핵심 키워드 3-5개를 쉼표로 구분하여 추출해주세요:\n\n{$plainText}";

        $response = $this->textAdapter->generate($prompt);
        $keywords = explode(',', $response->getContent());

        return array_map('trim', $keywords);
    }

    /**
     * 이미지 삽입 위치 계산
     *
     * DOM 요소 인덱스 기준으로 삽입 위치 반환
     */
    public function getInsertionPoints(string $content, int $imageCount): array {
        // H2가 있으면 H2 앞에 삽입, 없으면 단락 균등 분할
        $hasH2 = preg_match_all('/<h2[^>]*>/i', $content, $h2Matches, PREG_OFFSET_MATCH);

        if ($hasH2 && count($h2Matches[0]) > 1) {
            return $this->getH2InsertionPoints($h2Matches[0], $imageCount);
        }

        return $this->getParagraphInsertionPoints($content, $imageCount);
    }

    /**
     * H2 기준 삽입 위치 계산
     */
    private function getH2InsertionPoints(array $h2Matches, int $imageCount): array {
        $points = [];
        $totalH2 = count($h2Matches);

        // 첫 번째 H2는 제외하고 분배
        $availableH2 = $totalH2 - 1;
        $interval = max(1, floor($availableH2 / $imageCount));

        for ($i = 0; $i < $imageCount; $i++) {
            $h2Index = min($i * $interval + 1, $availableH2);
            $points[] = [
                'before_h2_index' => $h2Index,
                'section_index' => $i,
            ];
        }

        return $points;
    }

    /**
     * 단락 기준 삽입 위치 계산
     */
    private function getParagraphInsertionPoints(string $content, int $imageCount): array {
        $paragraphs = $this->extractParagraphs($content);
        $totalParagraphs = count($paragraphs);

        $points = [];
        $interval = floor($totalParagraphs / ($imageCount + 1));

        for ($i = 1; $i <= $imageCount; $i++) {
            $position = $interval * $i;
            $points[] = [
                'after_paragraph' => min($position, $totalParagraphs - 1),
                'section_index' => $i - 1
            ];
        }

        return $points;
    }
}
```

### 7.3.3 ImagePromptManager (싱글톤 - 설정 페이지 연동)

PromptManager 패턴을 따라 설정 페이지에서 프롬프트를 편집할 수 있도록 구현.

```php
<?php
namespace AIContentRewriter\Image;

/**
 * 이미지 프롬프트 관리 클래스 (싱글톤)
 *
 * 설정 페이지에서 프롬프트 템플릿을 직접 편집 가능
 */
class ImagePromptManager {
    private static ?ImagePromptManager $instance = null;

    private const OPTION_KEY = 'aicr_image_prompt';

    private const DEFAULT_PROMPT = <<<PROMPT
{{topic}}을 나타내는 {{style}} 스타일의 이미지.
블로그 게시글에 적합한, 전문적이고 깔끔한 디자인.
고품질, 선명함, 현대적인 느낌.
{{additional_instructions}}
PROMPT;

    private function __construct() {}

    public static function get_instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 저장된 프롬프트 반환 (없으면 기본값)
     */
    public function get_prompt(): string {
        return get_option(self::OPTION_KEY, self::DEFAULT_PROMPT);
    }

    /**
     * 프롬프트 저장
     */
    public function save_prompt(string $prompt): bool {
        return update_option(self::OPTION_KEY, $prompt);
    }

    /**
     * 기본 프롬프트 반환 (복원용)
     */
    public function get_default_prompt(): string {
        return self::DEFAULT_PROMPT;
    }

    /**
     * 변수 치환하여 최종 프롬프트 빌드
     */
    public function build_prompt(array $variables): string {
        $template = $this->get_prompt();

        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }

        // 사용되지 않은 변수 제거
        $template = preg_replace('/\{\{[a-z_]+\}\}/i', '', $template);

        return trim($template);
    }

    /**
     * 사용 가능한 변수 목록
     */
    public function get_available_variables(): array {
        return [
            'topic' => '섹션/단락의 주제',
            'keywords' => '추출된 키워드',
            'style' => '선택된 이미지 스타일',
            'post_title' => '게시글 제목',
            'additional_instructions' => '추가 지시사항',
        ];
    }
}
```

### 7.3.4 ImageInserter

```php
<?php
namespace AIContentRewriter\Image;

class ImageInserter {
    /**
     * 생성된 이미지를 콘텐츠에 삽입
     */
    public function insert(string $content, array $images, array $insertionPoints): string {
        // DOM 파싱
        $dom = new \DOMDocument();
        $dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // 삽입 위치 역순으로 처리 (앞에서부터 하면 인덱스가 밀림)
        $points = array_reverse($insertionPoints);

        foreach ($points as $point) {
            $imageHtml = $this->createImageBlock($images[$point['section_index']]);
            $this->insertAfterParagraph($dom, $point['after_paragraph'], $imageHtml);
        }

        return $dom->saveHTML();
    }

    /**
     * 이미지 블록 HTML 생성
     */
    private function createImageBlock(array $image): string {
        $attachmentId = $image['attachment_id'];
        $imageUrl = wp_get_attachment_url($attachmentId);
        $alt = esc_attr($image['alt'] ?? '');
        $caption = esc_html($image['caption'] ?? '');

        return sprintf(
            '<figure class="wp-block-image aicr-generated-image">
                <img src="%s" alt="%s" class="aicr-ai-image" />
                %s
            </figure>',
            esc_url($imageUrl),
            $alt,
            $caption ? "<figcaption>{$caption}</figcaption>" : ''
        );
    }
}
```

### 7.3.5 ImageScheduler (자동화 시스템 통합)

**기존 FeedScheduler 및 자동화 탭과 통합**하여 이미지 생성을 스케줄로 자동 실행.

```php
<?php
namespace AIContentRewriter\Image;

use AIContentRewriter\Cron\CronLogger;

/**
 * 이미지 생성 스케줄러
 *
 * 기존 자동화 시스템(FeedScheduler, automation.php)과 통합
 */
class ImageScheduler {
    public const HOOK_GENERATE_IMAGES = 'aicr_generate_images';

    private CronLogger $logger;
    private ImageGenerator $generator;

    public function __construct() {
        $this->logger = new CronLogger();
        $this->generator = new ImageGenerator();
    }

    /**
     * 스케줄러 초기화 - Plugin.php에서 호출
     */
    public function init(): void {
        // Cron 훅 등록
        add_action(self::HOOK_GENERATE_IMAGES, [$this, 'process_pending_posts']);

        // 스케줄 등록
        if (!wp_next_scheduled(self::HOOK_GENERATE_IMAGES)) {
            $interval = get_option('aicr_image_schedule_interval', 'hourly');
            wp_schedule_event(time(), $interval, self::HOOK_GENERATE_IMAGES);
        }
    }

    /**
     * 이미지 없는 게시글에 자동으로 이미지 생성
     */
    public function process_pending_posts(): void {
        if (!get_option('aicr_image_schedule_enabled', true)) {
            return;
        }

        $log_id = $this->logger->start(self::HOOK_GENERATE_IMAGES);
        $processed = 0;

        try {
            $limit = (int) get_option('aicr_image_batch_size', 5);
            $posts = $this->get_posts_without_images($limit);

            foreach ($posts as $post) {
                if ($this->should_skip_post($post->ID)) {
                    continue;
                }

                $this->generator->generate_for_post($post->ID);
                update_post_meta($post->ID, 'aicr_images_generated', true);
                $processed++;

                // API Rate Limit 대비 딜레이
                sleep(3);
            }

            $this->logger->complete($log_id, $processed);

        } catch (\Exception $e) {
            $this->logger->complete($log_id, $processed, $e->getMessage());
        }
    }

    /**
     * 이미지 없는 게시글 조회
     */
    private function get_posts_without_images(int $limit): array {
        global $wpdb;

        // AICR로 재작성된 게시글 중 이미지 없는 것
        $sql = "
            SELECT p.ID
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_thumbnail_id'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'aicr_images_generated'
            WHERE p.post_status = 'publish'
              AND p.post_type = 'post'
              AND pm1.meta_value IS NULL
              AND pm2.meta_value IS NULL
              AND EXISTS (
                  SELECT 1 FROM {$wpdb->postmeta} pm3
                  WHERE pm3.post_id = p.ID AND pm3.meta_key = '_aicr_rewritten_at'
              )
            ORDER BY p.post_date DESC
            LIMIT %d
        ";

        return $wpdb->get_results($wpdb->prepare($sql, $limit));
    }

    /**
     * 스킵 로직 - 이미 이미지가 있는 게시글 건너뛰기
     */
    private function should_skip_post(int $post_id): bool {
        // 1. 이미 이미지 생성됨
        if (get_post_meta($post_id, 'aicr_images_generated', true)) {
            return true;
        }

        // 2. Featured Image 이미 있음
        if (has_post_thumbnail($post_id) &&
            get_option('aicr_image_skip_with_thumbnail', true)) {
            return true;
        }

        // 3. 콘텐츠에 이미지 태그 이미 있음
        $content = get_post_field('post_content', $post_id);
        if (strpos($content, '<img') !== false &&
            get_option('aicr_image_skip_with_images', true)) {
            return true;
        }

        return false;
    }

    /**
     * 수동 실행 (자동화 탭에서 호출)
     */
    public function run_now(): array {
        $this->process_pending_posts();
        return ['status' => 'completed'];
    }
}
```

### 7.3.6 ImageGenerator 통합 매니저 (대표 이미지 설정 포함)

```php
<?php
namespace AIContentRewriter\Image;

use AIContentRewriter\AI\GeminiImageAdapter;

/**
 * 이미지 생성 통합 매니저
 *
 * 전체 흐름 조율 및 Featured Image 자동 설정
 */
class ImageGenerator {
    private GeminiImageAdapter $imageAdapter;
    private ImagePromptManager $promptManager;
    private ContentSectionizer $sectionizer;
    private ImageInserter $inserter;

    public function __construct() {
        $this->imageAdapter = new GeminiImageAdapter();
        $this->promptManager = ImagePromptManager::get_instance();
        $this->sectionizer = new ContentSectionizer();
        $this->inserter = new ImageInserter();
    }

    /**
     * 게시글에 이미지 생성 및 삽입
     */
    public function generate_for_post(int $post_id, array $options = []): array {
        $post = get_post($post_id);
        if (!$post) {
            throw new \Exception('게시글을 찾을 수 없습니다.');
        }

        $image_count = $options['count'] ?? (int) get_option('aicr_image_default_count', 2);
        $style = $options['style'] ?? get_option('aicr_image_default_style', 'illustration');
        $ratio = $options['ratio'] ?? get_option('aicr_image_default_ratio', '16:9');

        // 1. 콘텐츠를 섹션으로 분할
        $sections = $this->sectionizer->sectionize($post->post_content, $image_count);
        $insertion_points = $this->sectionizer->getInsertionPoints($post->post_content, $image_count);

        // 2. 각 섹션에 대해 이미지 생성
        $generated_images = [];
        foreach ($sections as $index => $section) {
            // 프롬프트 빌드
            $prompt = $this->promptManager->build_prompt([
                'topic' => $section['topic'],
                'keywords' => implode(', ', $section['keywords'] ?? []),
                'style' => $style,
                'post_title' => $post->post_title,
                'additional_instructions' => $options['instructions'] ?? '',
            ]);

            // 이미지 생성
            $response = $this->imageAdapter->generate($prompt, [
                'aspect_ratio' => $ratio,
            ]);

            // 미디어 라이브러리에 저장
            $attachment_id = $this->save_to_media_library(
                $response->getBase64(),
                $post_id,
                $section['topic']
            );

            $generated_images[] = [
                'attachment_id' => $attachment_id,
                'alt' => $section['topic'],
                'caption' => '',
                'section_index' => $index,
            ];

            // 3. 첫 번째 이미지를 Featured Image로 설정
            if ($index === 0 && get_option('aicr_image_auto_featured', true)) {
                $this->set_featured_image($post_id, $attachment_id);
            }
        }

        // 4. 콘텐츠에 이미지 삽입
        $new_content = $this->inserter->insert(
            $post->post_content,
            $generated_images,
            $insertion_points
        );

        // 5. 게시글 업데이트
        wp_update_post([
            'ID' => $post_id,
            'post_content' => $new_content,
        ]);

        // 6. 메타데이터 저장
        update_post_meta($post_id, 'aicr_images_generated', true);
        update_post_meta($post_id, 'aicr_images_generated_at', current_time('mysql'));

        return $generated_images;
    }

    /**
     * Base64 이미지를 미디어 라이브러리에 저장
     */
    private function save_to_media_library(string $base64, int $post_id, string $title): int {
        $upload_dir = wp_upload_dir();
        $filename = 'aicr-image-' . $post_id . '-' . time() . '.png';
        $file_path = $upload_dir['path'] . '/' . $filename;

        // Base64 디코딩 및 파일 저장
        file_put_contents($file_path, base64_decode($base64));

        // 미디어 라이브러리에 등록
        $attachment = [
            'post_mime_type' => 'image/png',
            'post_title' => sanitize_file_name($title),
            'post_content' => '',
            'post_status' => 'inherit',
        ];

        $attachment_id = wp_insert_attachment($attachment, $file_path, $post_id);

        // 메타데이터 생성
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($attachment_id, $file_path);
        wp_update_attachment_metadata($attachment_id, $metadata);

        return $attachment_id;
    }

    /**
     * Featured Image (대표 이미지) 설정
     */
    private function set_featured_image(int $post_id, int $attachment_id): void {
        set_post_thumbnail($post_id, $attachment_id);
    }
}
```

### 7.3.7 Plugin.php 통합 (외부 Cron 엔드포인트)

기존 외부 Cron 엔드포인트에 `image` 태스크 추가:

```php
// src/Core/Plugin.php - handle_external_cron() 메서드 수정

$allowed_tasks = ['all', 'fetch', 'rewrite', 'cleanup', 'image'];

// ... 기존 코드 ...

if ($task === 'all' || $task === 'image') {
    $image_scheduler = new \AIContentRewriter\Image\ImageScheduler();
    $image_scheduler->process_pending_posts();
    $results[] = 'image';
}
```

**외부 Cron URL 예시:**
```
https://yoursite.com/?aicr_cron=1&token=YOUR_TOKEN&task=image
```

---

## 7.4 사용자 인터페이스

### 7.4.1 게시글 편집 화면 - 메타박스

```
┌─────────────────────────────────────────────────────────────────┐
│  🎨 AI 이미지 생성                                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  이미지 개수:  ○ 1개  ● 2개  ○ 3개  ○ 4개  ○ 5개            │
│                                                                 │
│  이미지 스타일: [일러스트레이션          ▼]                    │
│                                                                 │
│  가로세로 비율: ○ 16:9  ● 4:3  ○ 1:1  ○ 3:4                  │
│                                                                 │
│  ┌───────────────────────────────────────────────────────────┐ │
│  │ 추가 지시사항 (선택)                                       │ │
│  │                                                           │ │
│  │ 예: "밝고 따뜻한 색감으로", "미니멀한 디자인"            │ │
│  └───────────────────────────────────────────────────────────┘ │
│                                                                 │
│  [  🖼️ 이미지 생성하기  ]                                      │
│                                                                 │
│  ─────────────────────────────────────────────────────────────  │
│  💡 팁: 콘텐츠를 저장한 후 이미지를 생성하세요.                │
└─────────────────────────────────────────────────────────────────┘
```

### 7.4.2 설정 페이지 - 이미지 설정 탭

```
┌─────────────────────────────────────────────────────────────────┐
│  [설정] [피드관리] [템플릿] [📷 이미지 설정] [히스토리]         │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ## 기본 설정                                                   │
│  ┌─────────────────────────────────────────────────────────────┐
│  │ 이미지 생성 활성화:      [✓]                                │
│  │ 기본 이미지 개수:        [2개 ▼]                           │
│  │ 기본 가로세로 비율:      [16:9 ▼]                          │
│  │ 첫 이미지 대표이미지:    [✓] Featured Image로 자동 설정    │
│  │ 자동 대체 텍스트 생성:   [✓] AI로 alt 텍스트 자동 생성     │
│  │ 자동 캡션 생성:          [✓] AI로 캡션 자동 생성           │
│  └─────────────────────────────────────────────────────────────┘
│                                                                 │
│  ## 이미지 프롬프트 템플릿 (편집 가능)                          │
│  ┌─────────────────────────────────────────────────────────────┐
│  │ {{topic}}을 나타내는 {{style}} 스타일의 이미지.            │
│  │ 블로그 게시글에 적합한, 전문적이고 깔끔한 디자인.          │
│  │ 고품질, 선명함, 현대적인 느낌.                              │
│  │ {{additional_instructions}}                                 │
│  │                                                             │
│  │ 사용 가능한 변수:                                           │
│  │ • {{topic}} - 섹션 주제                                     │
│  │ • {{keywords}} - 추출된 키워드                              │
│  │ • {{style}} - 선택된 스타일                                 │
│  │ • {{post_title}} - 게시글 제목                              │
│  │ • {{additional_instructions}} - 추가 지시사항              │
│  └─────────────────────────────────────────────────────────────┘
│  [기본값으로 복원]                                              │
│                                                                 │
│  ## 스케줄 자동 실행 설정                                       │
│  ┌─────────────────────────────────────────────────────────────┐
│  │ 자동 이미지 생성:        [✓] 스케줄로 자동 실행             │
│  │ 실행 주기:               [매 시간 ▼]                        │
│  │ 한 번에 처리할 개수:     [5개 ▼]                            │
│  │ 스킵 조건:                                                  │
│  │   [✓] 이미 대표 이미지가 있는 게시글                       │
│  │   [✓] 본문에 이미지 태그가 있는 게시글                     │
│  │   [✓] 이미 이미지 생성 완료된 게시글                       │
│  └─────────────────────────────────────────────────────────────┘
│  💡 자동화 탭에서 실행 이력 확인 가능                          │
│                                                                 │
│  ## 이미지 스타일 프리셋                                        │
│  ┌─────────────────────────────────────────────────────────────┐
│  │  ☆ 사실적 사진           ★ 일러스트레이션    ☆ 수채화     │
│  │  ☆ 미니멀 아이콘         ☆ 3D 렌더링        ☆ 플랫 디자인 │
│  │                                                             │
│  │  [+ 새 스타일 추가]                                        │
│  └─────────────────────────────────────────────────────────────┘
│                                                                 │
│                                    [변경사항 저장]              │
└─────────────────────────────────────────────────────────────────┘
```

### 7.4.3 스타일 편집 모달

```
┌─────────────────────────────────────────────────────────────────┐
│  스타일 편집: 일러스트레이션                              [×]  │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  스타일 이름:                                                   │
│  ┌─────────────────────────────────────────────────────────────┐
│  │ 일러스트레이션                                              │
│  └─────────────────────────────────────────────────────────────┘
│                                                                 │
│  스타일 프롬프트:                                               │
│  ┌─────────────────────────────────────────────────────────────┐
│  │ digital illustration style, clean lines, vibrant colors,   │
│  │ modern design, professional artwork                        │
│  │                                                             │
│  └─────────────────────────────────────────────────────────────┘
│                                                                 │
│  제외할 요소 (Negative Prompt):                                 │
│  ┌─────────────────────────────────────────────────────────────┐
│  │ blurry, low quality, distorted, watermark, text overlay    │
│  │                                                             │
│  └─────────────────────────────────────────────────────────────┘
│                                                                 │
│  기본 가로세로 비율: [16:9 ▼]                                  │
│                                                                 │
│  [기본값으로 설정]                    [취소]  [저장]           │
└─────────────────────────────────────────────────────────────────┘
```

---

## 7.5 API 연동 상세

### 7.5.1 Gemini Imagen API 스펙

**방법 1: Imagen 3 모델 직접 사용 (권장)**

```
POST https://generativelanguage.googleapis.com/v1beta/models/imagen-3.0-generate-002:predict?key={API_KEY}
```

**요청 본문:**
```json
{
  "instances": [
    {
      "prompt": "A colorful illustration of a coffee shop interior, modern design"
    }
  ],
  "parameters": {
    "sampleCount": 1,
    "aspectRatio": "16:9",
    "personGeneration": "DONT_ALLOW",
    "safetyFilterLevel": "BLOCK_SOME"
  }
}
```

**응답:**
```json
{
  "predictions": [
    {
      "bytesBase64Encoded": "iVBORw0KGgo...",
      "mimeType": "image/png"
    }
  ]
}
```

---

**방법 2: Gemini 내장 이미지 생성**

```
POST https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-001:generateContent?key={API_KEY}
```

**요청 본문:**
```json
{
  "contents": [{
    "parts": [{
      "text": "Generate an image of a colorful illustration of a coffee shop interior"
    }]
  }],
  "generationConfig": {
    "responseModalities": ["TEXT", "IMAGE"]
  }
}
```

**모델 옵션:**
- `gemini-2.0-flash-001` (Nano Banana) - 빠른 생성
- `gemini-3-pro-preview` (Nano Banana Pro) - 고품질

### 7.5.2 지원 옵션

| 옵션 | 값 | 설명 |
|------|-----|------|
| `sampleCount` | 1-4 | 생성할 이미지 수 |
| `aspectRatio` | 1:1, 3:4, 4:3, 9:16, 16:9 | 가로세로 비율 |
| `personGeneration` | DONT_ALLOW, ALLOW | 인물 생성 허용 여부 |
| `safetyFilterLevel` | BLOCK_NONE, BLOCK_FEW, BLOCK_SOME, BLOCK_MOST | 안전 필터 수준 |

---

## 7.6 구현 로드맵

### Phase 1: 기반 구축 (1-2일)

| 태스크 | 설명 | 상태 |
|--------|------|------|
| 7.1.1 | GeminiImageAdapter 클래스 생성 | ⬜ |
| 7.1.2 | 이미지 생성 API 연동 및 테스트 | ⬜ |
| 7.1.3 | 데이터베이스 스키마 마이그레이션 | ⬜ |
| 7.1.4 | 미디어 라이브러리 저장 로직 | ⬜ |
| 7.1.5 | ImagePromptManager 싱글톤 구현 | ⬜ |

### Phase 2: 핵심 기능 (2-3일)

| 태스크 | 설명 | 상태 |
|--------|------|------|
| 7.2.1 | ContentSectionizer 구현 (H2/단락 분할) | ⬜ |
| 7.2.2 | ImageInserter 구현 (DOM 기반 삽입) | ⬜ |
| 7.2.3 | ImageGenerator 통합 매니저 | ⬜ |
| 7.2.4 | Featured Image 자동 설정 로직 | ⬜ |

### Phase 3: 스케줄러 통합 (1일)

| 태스크 | 설명 | 상태 |
|--------|------|------|
| 7.3.1 | ImageScheduler 구현 | ⬜ |
| 7.3.2 | 스킵 로직 (이미 이미지 있는 게시글) | ⬜ |
| 7.3.3 | Plugin.php 외부 Cron 통합 | ⬜ |
| 7.3.4 | CronLogger 연동 | ⬜ |

### Phase 4: UI 구현 (1-2일)

| 태스크 | 설명 | 상태 |
|--------|------|------|
| 7.4.1 | 게시글 메타박스 UI | ⬜ |
| 7.4.2 | AJAX 핸들러 구현 | ⬜ |
| 7.4.3 | 설정 페이지 이미지 탭 (프롬프트 편집) | ⬜ |
| 7.4.4 | 스케줄 설정 UI | ⬜ |
| 7.4.5 | 스타일 프리셋 CRUD | ⬜ |

### Phase 5: 고도화 (1-2일)

| 태스크 | 설명 | 상태 |
|--------|------|------|
| 7.5.1 | 자동 alt 텍스트 생성 | ⬜ |
| 7.5.2 | 자동 캡션 생성 | ⬜ |
| 7.5.3 | 이미지 생성 히스토리 | ⬜ |
| 7.5.4 | 에러 처리 및 재시도 | ⬜ |
| 7.5.5 | 자동화 탭 UI 업데이트 | ⬜ |

### 구현 순서 요약

```
Phase 1: 기반
├── GeminiImageAdapter
├── DB 스키마 (aicr_image_history, aicr_image_styles)
└── ImagePromptManager (싱글톤)

Phase 2: 핵심 로직
├── ContentSectionizer (청크 분할)
├── ImageInserter (이미지 삽입)
├── ImageGenerator (통합 매니저)
└── Featured Image 설정

Phase 3: 스케줄러
├── ImageScheduler
├── 스킵 로직
└── Plugin.php 통합 (외부 Cron)

Phase 4: UI
├── 게시글 메타박스
├── 설정 > 이미지 설정 탭
└── 프롬프트 편집 UI

Phase 5: 고도화
├── 자동 alt/캡션
└── 히스토리/에러 처리
```

---

## 7.7 설정 옵션

### 7.7.1 wp_options 저장 구조

```php
// 개별 옵션 키 (PromptManager 패턴)
$options = [
    // 기본 설정
    'aicr_image_enabled'           => true,              // 이미지 생성 기능 활성화
    'aicr_image_default_count'     => 2,                 // 기본 이미지 개수
    'aicr_image_default_ratio'     => '16:9',            // 기본 비율
    'aicr_image_default_style'     => 'illustration',    // 기본 스타일

    // 프롬프트 관리 (ImagePromptManager 연동)
    'aicr_image_prompt'            => '{{topic}}을 나타내는...', // 프롬프트 템플릿

    // 자동 생성 옵션
    'aicr_image_auto_featured'     => true,              // 첫 이미지를 Featured Image로
    'aicr_image_auto_alt'          => true,              // 자동 alt 텍스트 생성
    'aicr_image_auto_caption'      => true,              // 자동 캡션 생성

    // 스케줄 설정 (ImageScheduler 연동)
    'aicr_image_schedule_enabled'  => true,              // 스케줄 실행 활성화
    'aicr_image_schedule_interval' => 'hourly',          // 실행 주기 (hourly, twicedaily, daily)
    'aicr_image_batch_size'        => 5,                 // 배치 처리 개수

    // 스킵 조건
    'aicr_image_skip_with_thumbnail' => true,            // 대표 이미지 있으면 스킵
    'aicr_image_skip_with_images'    => true,            // 본문에 이미지 있으면 스킵

    // API 설정
    'aicr_image_person_generation' => 'DONT_ALLOW',      // 인물 생성 허용 여부
    'aicr_image_safety_filter'     => 'BLOCK_SOME',      // 안전 필터 수준
];
```

### 7.7.2 wp_options 요약 테이블

| 옵션 키 | 기본값 | 설명 |
|--------|-------|------|
| `aicr_image_enabled` | `true` | 이미지 생성 기능 활성화 |
| `aicr_image_prompt` | (기본 템플릿) | 이미지 프롬프트 (설정에서 편집 가능) |
| `aicr_image_default_count` | `2` | 기본 이미지 개수 |
| `aicr_image_default_ratio` | `'16:9'` | 기본 비율 |
| `aicr_image_default_style` | `'illustration'` | 기본 스타일 |
| `aicr_image_auto_featured` | `true` | 첫 이미지를 대표이미지로 설정 |
| `aicr_image_auto_alt` | `true` | 자동 alt 텍스트 생성 |
| `aicr_image_schedule_enabled` | `true` | 스케줄 실행 활성화 |
| `aicr_image_schedule_interval` | `'hourly'` | 실행 주기 |
| `aicr_image_batch_size` | `5` | 배치 처리 개수 |
| `aicr_image_skip_with_thumbnail` | `true` | 대표 이미지 있으면 스킵 |
| `aicr_image_skip_with_images` | `true` | 본문에 이미지 있으면 스킵 |

---

## 7.8 보안 고려사항

### 7.8.1 권한 검사

```php
// 이미지 생성 권한 확인
if (!current_user_can('edit_post', $post_id)) {
    wp_send_json_error(['message' => '권한이 없습니다.']);
}

// nonce 검증
if (!wp_verify_nonce($_POST['nonce'], 'aicr_generate_image')) {
    wp_send_json_error(['message' => '보안 검증 실패']);
}
```

### 7.8.2 API 요청 제한

- 분당 최대 10회 이미지 생성 요청
- 하루 최대 100회 제한 (설정 가능)
- Rate limiting 적용

### 7.8.3 콘텐츠 필터링

- 부적절한 프롬프트 필터링
- Gemini 안전 필터 활성화
- 생성된 이미지 검토 옵션

---

## 7.9 예상 비용

### 7.9.1 Gemini Imagen API 비용 (2024년 기준)

| 모델 | 가격 |
|------|------|
| Imagen 3 | $0.03 / 이미지 |
| Imagen 3 Fast | $0.02 / 이미지 |

### 7.9.2 월간 비용 예상

| 사용량 | 비용 (Imagen 3) |
|--------|----------------|
| 100 이미지/월 | $3 |
| 500 이미지/월 | $15 |
| 1,000 이미지/월 | $30 |

---

## 7.10 테스트 계획

### 7.10.1 단위 테스트

- [ ] GeminiImageAdapter Imagen API 호출 테스트
- [ ] ContentSectionizer H2/단락 분할 테스트
- [ ] ImagePromptManager 프롬프트 빌드 테스트
- [ ] ImageInserter DOM 조작 테스트
- [ ] ImageScheduler 스킵 로직 테스트
- [ ] Featured Image 설정 테스트

### 7.10.2 E2E 테스트 (Playwright MCP)

- [ ] 메타박스 UI 렌더링 확인
- [ ] 이미지 개수 선택 동작
- [ ] 스타일 선택 동작
- [ ] 이미지 생성 버튼 클릭 → 로딩 → 완료 흐름
- [ ] 생성된 이미지 콘텐츠 삽입 확인
- [ ] Featured Image 자동 설정 확인
- [ ] 설정 페이지 이미지 탭 동작
- [ ] 프롬프트 템플릿 편집/저장 동작
- [ ] 스케줄 설정 UI 동작
- [ ] 자동화 탭에서 이미지 생성 작업 확인

---

## 7.11 향후 확장 가능성

1. **다중 AI 프로바이더 지원**: DALL-E, Stable Diffusion 등
2. **이미지 편집**: 생성된 이미지 수정/재생성
3. **배치 처리**: 여러 게시글 일괄 이미지 생성
4. **이미지 템플릿**: 자주 사용하는 구도/스타일 저장
5. **A/B 테스트**: 여러 스타일 이미지 비교 테스트

---

## 7.12 위험 요소 및 대응 방안 (Red Team Review)

### 7.12.1 치명적 위험 (Critical)

#### 🔴 C1: 동시 실행 경쟁 조건 (Race Condition)

**문제점:**
- 스케줄러가 동시에 여러 번 실행될 경우 같은 게시글을 중복 처리
- 외부 Cron과 WordPress Cron이 동시에 실행될 수 있음

**영향:**
- 중복 이미지 생성으로 API 비용 낭비
- 게시글 콘텐츠 손상 가능성

**해결 방안:**
```php
// ImageScheduler.php에 락 메커니즘 추가
public function process_pending_posts(): void {
    // 트랜지언트 기반 락
    $lock_key = 'aicr_image_generation_lock';
    if (get_transient($lock_key)) {
        return; // 이미 실행 중
    }
    set_transient($lock_key, true, 300); // 5분 락

    try {
        // 처리 로직...
    } finally {
        delete_transient($lock_key);
    }
}

// 개별 게시글 처리 시에도 락 적용
private function acquire_post_lock(int $post_id): bool {
    global $wpdb;
    $lock_key = "aicr_image_gen_{$post_id}";

    // 원자적 락 획득 (INSERT IGNORE 사용)
    $result = $wpdb->query($wpdb->prepare(
        "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload)
         VALUES (%s, %s, 'no')",
        "_transient_{$lock_key}",
        time()
    ));

    return $result === 1;
}
```

---

#### 🔴 C2: 트랜잭션/롤백 부재

**문제점:**
- 이미지 저장 후 `wp_update_post` 실패 시 고아 첨부파일 발생
- 일부 이미지만 생성된 상태에서 실패 시 불완전한 상태

**영향:**
- 미디어 라이브러리에 불필요한 이미지 축적
- 게시글 콘텐츠 불일치

**해결 방안:**
```php
// ImageGenerator.php - 롤백 메커니즘 추가
public function generate_for_post(int $post_id, array $options = []): array {
    $generated_attachments = [];

    try {
        // 이미지 생성 및 저장
        foreach ($sections as $index => $section) {
            $attachment_id = $this->save_to_media_library(...);
            $generated_attachments[] = $attachment_id;
            // ...
        }

        // 게시글 업데이트
        $result = wp_update_post([...]);
        if (is_wp_error($result)) {
            throw new \Exception($result->get_error_message());
        }

        return $generated_images;

    } catch (\Exception $e) {
        // 롤백: 생성된 첨부파일 삭제
        foreach ($generated_attachments as $attachment_id) {
            wp_delete_attachment($attachment_id, true);
        }
        throw $e;
    }
}
```

---

#### 🔴 C3: API 실패 시 재시도 로직 부재

**문제점:**
- Gemini API 일시적 장애 시 전체 처리 실패
- 429 Rate Limit 응답 처리 없음

**영향:**
- 불안정한 배치 처리
- API 쿼터 낭비

**해결 방안:**
```php
// GeminiImageAdapter.php - 지수 백오프 재시도
private function request_with_retry(string $url, array $payload, int $max_retries = 3): array {
    $retry_delays = [1, 3, 10]; // 초 단위 지수 백오프

    for ($attempt = 0; $attempt <= $max_retries; $attempt++) {
        $response = wp_remote_post($url, [
            'timeout' => $this->timeout,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            if ($attempt < $max_retries) {
                sleep($retry_delays[$attempt] ?? 10);
                continue;
            }
            throw new \Exception('API 연결 실패: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);

        // 성공
        if ($code === 200) {
            return json_decode(wp_remote_retrieve_body($response), true);
        }

        // 429 Rate Limit - 재시도
        if ($code === 429) {
            $retry_after = (int) wp_remote_retrieve_header($response, 'retry-after') ?: $retry_delays[$attempt];
            sleep(min($retry_after, 60));
            continue;
        }

        // 5xx 서버 에러 - 재시도
        if ($code >= 500 && $attempt < $max_retries) {
            sleep($retry_delays[$attempt] ?? 10);
            continue;
        }

        // 4xx 클라이언트 에러 - 재시도 없음
        throw new \Exception("API 에러 (HTTP {$code}): " . wp_remote_retrieve_body($response));
    }

    throw new \Exception('최대 재시도 횟수 초과');
}
```

---

### 7.12.2 높은 위험 (High)

#### 🟠 H1: 메모리 부족 (Shared Hosting)

**문제점:**
- Base64 이미지 데이터 (약 2-5MB/이미지) + DOM 파싱이 메모리 소진
- 공유 호스팅의 128MB 제한에서 치명적

**영향:**
- PHP Fatal Error로 프로세스 중단
- 불완전한 게시글 상태

**해결 방안:**
```php
// ImageGenerator.php - 메모리 체크 추가
private function check_memory_available(): bool {
    $memory_limit = $this->get_memory_limit_bytes();
    $current_usage = memory_get_usage(true);
    $required = 50 * 1024 * 1024; // 50MB 여유 필요

    return ($memory_limit - $current_usage) > $required;
}

private function get_memory_limit_bytes(): int {
    $limit = ini_get('memory_limit');
    if ($limit === '-1') return PHP_INT_MAX;

    $unit = strtolower(substr($limit, -1));
    $value = (int) $limit;

    return match($unit) {
        'g' => $value * 1024 * 1024 * 1024,
        'm' => $value * 1024 * 1024,
        'k' => $value * 1024,
        default => $value,
    };
}

// 이미지 처리 후 즉시 메모리 해제
private function save_to_media_library(string $base64, ...): int {
    // 스트림 기반 저장 (메모리 효율적)
    $temp_file = wp_tempnam('aicr_');
    $stream = fopen($temp_file, 'wb');
    stream_filter_append($stream, 'convert.base64-decode');
    fwrite($stream, $base64);
    fclose($stream);

    // base64 변수 즉시 해제
    unset($base64);

    // ...파일 처리
}
```

---

#### 🟠 H2: Gutenberg 블록 호환성

**문제점:**
- 현재 `<figure>` HTML이 Gutenberg 블록 구조와 맞지 않음
- 블록 에디터에서 이미지 편집/이동 불가

**영향:**
- 편집 시 "이 블록에 예기치 않은 오류가 있습니다" 메시지
- 레이아웃 깨짐

**해결 방안:**
```php
// ImageInserter.php - Gutenberg 블록 포맷 사용
private function createImageBlock(array $image): string {
    $attachmentId = $image['attachment_id'];
    $imageUrl = wp_get_attachment_url($attachmentId);
    $alt = esc_attr($image['alt'] ?? '');

    // Gutenberg 이미지 블록 포맷
    return sprintf(
        '<!-- wp:image {"id":%d,"sizeSlug":"large","linkDestination":"none","className":"aicr-generated-image"} -->
<figure class="wp-block-image size-large aicr-generated-image"><img src="%s" alt="%s" class="wp-image-%d"/></figure>
<!-- /wp:image -->',
        $attachmentId,
        esc_url($imageUrl),
        $alt,
        $attachmentId
    );
}
```

---

#### 🟠 H3: PHP 타임아웃 초과

**문제점:**
- 5개 이미지 생성 시 약 50-100초 소요 (이미지당 10-20초)
- 대부분 호스팅의 30초 제한 초과

**영향:**
- 504 Gateway Timeout
- 부분 처리된 불완전한 상태

**해결 방안:**
```php
// 비동기 큐 기반 처리 (기존 async 처리 패턴 활용)
class ImageGenerationQueue {
    public const QUEUE_TABLE = 'aicr_image_queue';

    /**
     * 이미지 생성 작업을 큐에 추가
     */
    public function enqueue(int $post_id, array $options): string {
        global $wpdb;

        $job_id = wp_generate_uuid4();

        $wpdb->insert($wpdb->prefix . self::QUEUE_TABLE, [
            'job_id' => $job_id,
            'post_id' => $post_id,
            'options' => wp_json_encode($options),
            'status' => 'pending',
            'created_at' => current_time('mysql'),
        ]);

        // 백그라운드 처리 트리거
        wp_schedule_single_event(time(), 'aicr_process_image_job', [$job_id]);
        spawn_cron();

        return $job_id;
    }

    /**
     * AJAX 진행 상태 조회
     */
    public function get_progress(string $job_id): array {
        global $wpdb;

        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}" . self::QUEUE_TABLE . " WHERE job_id = %s",
            $job_id
        ));

        return [
            'status' => $job->status,
            'progress' => (int) $job->progress,
            'total' => (int) $job->total_images,
            'current_step' => $job->current_step,
            'error' => $job->error_message,
        ];
    }
}
```

---

#### 🟠 H4: API 응답 검증 부재

**문제점:**
- Gemini API 응답 구조가 예상과 다를 경우 PHP 오류 발생
- 빈 응답, 부분 응답 처리 없음

**영향:**
- Undefined index/key 에러
- 손상된 이미지 파일

**해결 방안:**
```php
// GeminiImageAdapter.php - 응답 검증
private function parseImageResponse(array $response): ImageResponse {
    // 구조 검증
    if (!isset($response['predictions']) || !is_array($response['predictions'])) {
        throw new \Exception('잘못된 API 응답 형식: predictions 필드 누락');
    }

    if (empty($response['predictions'])) {
        throw new \Exception('API가 이미지를 생성하지 못했습니다.');
    }

    $prediction = $response['predictions'][0];

    if (!isset($prediction['bytesBase64Encoded'])) {
        // 에러 응답 확인
        if (isset($prediction['safetyAttributes']['blocked']) && $prediction['safetyAttributes']['blocked']) {
            throw new \Exception('안전 필터에 의해 이미지가 차단되었습니다.');
        }
        throw new \Exception('API 응답에 이미지 데이터가 없습니다.');
    }

    // Base64 유효성 검증
    $base64 = $prediction['bytesBase64Encoded'];
    if (!preg_match('/^[A-Za-z0-9+\/=]+$/', $base64)) {
        throw new \Exception('잘못된 Base64 인코딩');
    }

    // 디코딩 후 이미지 헤더 확인
    $decoded = base64_decode($base64, true);
    if ($decoded === false) {
        throw new \Exception('Base64 디코딩 실패');
    }

    // PNG 시그니처 확인
    if (substr($decoded, 0, 8) !== "\x89PNG\r\n\x1a\n") {
        throw new \Exception('유효한 PNG 이미지가 아닙니다.');
    }

    return new ImageResponse($base64, $prediction['mimeType'] ?? 'image/png');
}
```

---

#### 🟠 H5: 디스크 공간 관리 부재

**문제점:**
- 게시글 삭제 시 생성된 이미지 첨부파일 미삭제
- 이미지 재생성 시 이전 이미지 미정리

**영향:**
- 시간이 지남에 따라 디스크 공간 고갈
- 미디어 라이브러리 혼잡

**해결 방안:**
```php
// ImageCleanup.php - 정리 훅 등록
class ImageCleanup {
    public function init(): void {
        // 게시글 삭제 시 관련 이미지 정리
        add_action('before_delete_post', [$this, 'cleanup_post_images']);

        // 주기적 정리 (일일)
        add_action('aicr_daily_cleanup', [$this, 'cleanup_orphaned_images']);
    }

    public function cleanup_post_images(int $post_id): void {
        global $wpdb;

        // aicr_image_history에서 해당 게시글의 이미지 ID 조회
        $attachment_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT attachment_id FROM {$wpdb->prefix}aicr_image_history
             WHERE post_id = %d AND attachment_id IS NOT NULL",
            $post_id
        ));

        foreach ($attachment_ids as $attachment_id) {
            wp_delete_attachment($attachment_id, true); // true = 파일도 삭제
        }

        // 히스토리 레코드 삭제
        $wpdb->delete($wpdb->prefix . 'aicr_image_history', ['post_id' => $post_id]);
    }

    public function cleanup_orphaned_images(): void {
        global $wpdb;

        // 게시글이 없는 이미지 히스토리 정리 (30일 이상 된 것)
        $orphaned = $wpdb->get_results(
            "SELECT h.* FROM {$wpdb->prefix}aicr_image_history h
             LEFT JOIN {$wpdb->posts} p ON h.post_id = p.ID
             WHERE p.ID IS NULL AND h.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        foreach ($orphaned as $record) {
            if ($record->attachment_id) {
                wp_delete_attachment($record->attachment_id, true);
            }
            $wpdb->delete($wpdb->prefix . 'aicr_image_history', ['id' => $record->id]);
        }
    }
}
```

---

### 7.12.3 중간 위험 (Medium)

#### 🟡 M1: API 키 URL 노출

**문제점:**
- API 키가 URL 쿼리 파라미터로 전송됨
- 서버 로그, 브라우저 히스토리에 노출

**해결 방안:**
```php
// GeminiImageAdapter.php - Authorization 헤더 사용
$response = wp_remote_post(self::IMAGEN_API, [
    'timeout' => $this->timeout,
    'headers' => [
        'Content-Type' => 'application/json',
        'x-goog-api-key' => $this->apiKey, // URL 대신 헤더 사용
    ],
    'body' => wp_json_encode($payload),
]);
```

---

#### 🟡 M2: ContentSectionizer 엣지 케이스

**문제점:**
- 빈 콘텐츠, 단일 단락, 특수 HTML 처리 없음

**해결 방안:**
```php
// ContentSectionizer.php - 가드 조건 추가
public function sectionize(string $content, int $sectionCount): array {
    // 빈 콘텐츠 체크
    $content = trim($content);
    if (empty($content)) {
        throw new \InvalidArgumentException('콘텐츠가 비어있습니다.');
    }

    // 최소 콘텐츠 길이 체크 (100자 이상)
    $plainText = strip_tags($content);
    if (mb_strlen($plainText) < 100) {
        throw new \InvalidArgumentException('콘텐츠가 너무 짧습니다. (최소 100자)');
    }

    // 단락 수 체크
    $paragraphs = $this->extractParagraphs($content);
    if (count($paragraphs) < $sectionCount) {
        // 요청된 이미지 수보다 단락이 적으면 조정
        $sectionCount = max(1, count($paragraphs) - 1);
    }

    // ...
}
```

---

#### 🟡 M3: 동시 편집 충돌

**문제점:**
- 다른 사용자가 게시글 편집 중일 때 이미지 생성하면 변경사항 덮어씀

**해결 방안:**
```php
// ImageGenerator.php - 편집 락 확인
public function generate_for_post(int $post_id, array $options = []): array {
    // WordPress 포스트 락 확인
    $lock = wp_check_post_lock($post_id);
    if ($lock) {
        $user = get_userdata($lock);
        throw new \Exception(
            sprintf('%s님이 이 게시글을 편집 중입니다. 나중에 다시 시도해주세요.', $user->display_name)
        );
    }

    // 처리 중 락 설정
    wp_set_post_lock($post_id);

    try {
        // ... 이미지 생성 로직
    } finally {
        delete_post_meta($post_id, '_edit_lock');
    }
}
```

---

#### 🟡 M4: 실패 이력 미추적

**문제점:**
- 이미지 생성 실패 시 원인 추적 불가
- 동일한 실패가 반복됨

**해결 방안:**
```sql
-- aicr_image_history 테이블 확장
ALTER TABLE {prefix}aicr_image_history
ADD COLUMN status ENUM('pending', 'success', 'failed') DEFAULT 'pending',
ADD COLUMN error_message TEXT,
ADD COLUMN retry_count INT DEFAULT 0;
```

```php
// 실패 기록
private function log_failure(int $post_id, string $error, int $section_index): void {
    global $wpdb;

    $wpdb->insert($wpdb->prefix . 'aicr_image_history', [
        'post_id' => $post_id,
        'section_index' => $section_index,
        'status' => 'failed',
        'error_message' => $error,
        'created_at' => current_time('mysql'),
    ]);
}

// 스킵 로직에 실패 횟수 체크 추가
private function should_skip_post(int $post_id): bool {
    // ... 기존 로직 ...

    // 연속 3회 이상 실패한 게시글 스킵
    global $wpdb;
    $recent_failures = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}aicr_image_history
         WHERE post_id = %d AND status = 'failed'
         AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
        $post_id
    ));

    if ($recent_failures >= 3) {
        return true;
    }

    return false;
}
```

---

### 7.12.4 낮은 위험 (Low)

#### 🟢 L1: 이미지 최적화 미적용

**문제점:**
- PNG 원본 그대로 저장 (파일 크기 큼)

**해결 방안:**
```php
// WebP 변환 및 압축 (WordPress 5.8+ 지원 시)
private function optimize_image(string $file_path): void {
    $editor = wp_get_image_editor($file_path);
    if (!is_wp_error($editor)) {
        $editor->set_quality(85);

        // WebP 지원 시 변환
        if (wp_image_editor_supports(['mime_type' => 'image/webp'])) {
            $webp_path = preg_replace('/\\.png$/', '.webp', $file_path);
            $editor->save($webp_path, 'image/webp');
        }
    }
}
```

---

#### 🟢 L2: 사용자 진행 상태 피드백 부족

**문제점:**
- 다중 이미지 생성 시 "로딩 중"만 표시

**해결 방안:**
```javascript
// admin.js - 진행 상태 폴링
async function pollProgress(jobId) {
    const response = await fetch(ajaxurl + '?action=aicr_image_progress&job_id=' + jobId);
    const data = await response.json();

    updateProgressUI(data.progress, data.total, data.current_step);

    if (data.status === 'processing') {
        setTimeout(() => pollProgress(jobId), 2000);
    } else if (data.status === 'completed') {
        showSuccess();
        reloadContent();
    } else if (data.status === 'failed') {
        showError(data.error);
    }
}
```

---

### 7.12.5 구현 필수 사항 요약

| 우선순위 | 이슈 | 구현 필수 여부 |
|---------|------|--------------|
| 🔴 Critical | C1: 동시 실행 락 | **필수** |
| 🔴 Critical | C2: 트랜잭션/롤백 | **필수** |
| 🔴 Critical | C3: API 재시도 로직 | **필수** |
| 🟠 High | H1: 메모리 체크 | **필수** |
| 🟠 High | H2: Gutenberg 호환 | **필수** |
| 🟠 High | H3: 비동기 큐 처리 | **권장** |
| 🟠 High | H4: 응답 검증 | **필수** |
| 🟠 High | H5: 이미지 정리 | **권장** |
| 🟡 Medium | M1: API 키 헤더 | **권장** |
| 🟡 Medium | M2: 엣지 케이스 | **필수** |
| 🟡 Medium | M3: 동시 편집 체크 | **권장** |
| 🟡 Medium | M4: 실패 이력 | **권장** |
| 🟢 Low | L1: 이미지 최적화 | 선택 |
| 🟢 Low | L2: 진행 상태 UI | 선택 |

---

### 7.12.6 보완된 데이터베이스 스키마

```sql
-- 이미지 생성 이력 테이블 (보완)
CREATE TABLE {prefix}aicr_image_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id BIGINT UNSIGNED NOT NULL,
    attachment_id BIGINT UNSIGNED,
    prompt TEXT NOT NULL,
    style VARCHAR(50),
    section_index INT DEFAULT 0,
    is_featured BOOLEAN DEFAULT FALSE,
    status ENUM('pending', 'processing', 'success', 'failed') DEFAULT 'pending',
    error_message TEXT,
    retry_count INT DEFAULT 0,
    generation_time FLOAT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_post_id (post_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- 이미지 생성 큐 테이블 (신규)
CREATE TABLE {prefix}aicr_image_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id VARCHAR(36) NOT NULL UNIQUE,
    post_id BIGINT UNSIGNED NOT NULL,
    options JSON,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    progress INT DEFAULT 0,
    total_images INT DEFAULT 0,
    current_step VARCHAR(100),
    error_message TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME,
    completed_at DATETIME,
    INDEX idx_status (status),
    INDEX idx_post_id (post_id)
);
```

---

*문서 버전: 2.1*
*작성일: 2026-01-07*
*갱신 내용:*
- *v2.0: 프롬프트 관리, 스케줄 자동화, 대표 이미지, 청크 분할 기능 추가*
- *v2.1: Red Team Review - 14개 위험 요소 식별 및 해결 방안 추가*
*이전 문서: [06-DEVELOPMENT-ROADMAP.md](./06-DEVELOPMENT-ROADMAP.md)*
