# Cover Image Prompt (표지 이미지 프롬프트)

AI Content Rewriter 플러그인에서 사용하는 표지 이미지 생성 프롬프트입니다.

## 소스 위치
`wp-content/plugins/ai-content-rewriter/src/Image/ImagePromptManager.php`
- 메서드: `build_cover_prompt()`
- 라인: 273-326

## 프롬프트 스타일
**Minimal Editorial Photo** - 에디토리얼 플랫레이 사진 스타일

## 프롬프트 템플릿

```
IMPORTANT: ABSOLUTELY NO TEXT IN THIS IMAGE. Zero text, zero letters, zero words, zero typography.

Create a minimal editorial photo for: "{$postTitle}"

MANDATORY RULES (MUST FOLLOW):
1. ZERO TEXT - No text, no letters, no words, no numbers, no titles, no labels anywhere
2. SOLID COLOR BACKGROUND - Single bold color (coral, teal, mustard, sage green, terracotta, navy)
3. MINIMAL OBJECTS - Just 2-3 simple objects that represent the topic

STYLE - Minimal Flat Lay Photography:
- Clean flat-lay style with solid single color background
- 2-3 real objects arranged simply on the colored surface
- Minimalist composition with lots of empty space
- Natural lighting with soft shadows
- Professional product photography aesthetic

OBJECTS TO USE (pick 2-3 only):
- Simple props: notebook, pen, glasses, plant, coffee cup, headphones
- Or: hands holding an object related to the topic
- Or: nature elements like leaves, flowers

THIS IMAGE MUST BE:
- TEXT-FREE (no titles, labels, captions, watermarks)
- Minimal and clean
- Real photography style, not digital art
- Professional and elegant

DO NOT INCLUDE:
- ANY text, letters, words, or typography
- Titles or headings
- Labels or captions
- Digital elements or screens with text
- Complex or busy compositions
```

## 동적 추가 요소

### Content Summary (선택)
```
Topic context: {$contentSummary}
```

### Additional Instructions (선택)
```
Additional requirements: {$additionalInstructions}
```

### 최종 리마인더
```
FINAL REMINDER: This image must contain ABSOLUTELY NO TEXT of any kind. Pure visual only.
```

## PHP 코드

```php
public function build_cover_prompt(
    string $postTitle,
    string $contentSummary = '',
    string $additionalInstructions = ''
): string {
    $prompt = <<<PROMPT
IMPORTANT: ABSOLUTELY NO TEXT IN THIS IMAGE. Zero text, zero letters, zero words, zero typography.

Create a minimal editorial photo for: "{$postTitle}"

MANDATORY RULES (MUST FOLLOW):
1. ZERO TEXT - No text, no letters, no words, no numbers, no titles, no labels anywhere
2. SOLID COLOR BACKGROUND - Single bold color (coral, teal, mustard, sage green, terracotta, navy)
3. MINIMAL OBJECTS - Just 2-3 simple objects that represent the topic

STYLE - Minimal Flat Lay Photography:
- Clean flat-lay style with solid single color background
- 2-3 real objects arranged simply on the colored surface
- Minimalist composition with lots of empty space
- Natural lighting with soft shadows
- Professional product photography aesthetic

OBJECTS TO USE (pick 2-3 only):
- Simple props: notebook, pen, glasses, plant, coffee cup, headphones
- Or: hands holding an object related to the topic
- Or: nature elements like leaves, flowers

THIS IMAGE MUST BE:
- TEXT-FREE (no titles, labels, captions, watermarks)
- Minimal and clean
- Real photography style, not digital art
- Professional and elegant

DO NOT INCLUDE:
- ANY text, letters, words, or typography
- Titles or headings
- Labels or captions
- Digital elements or screens with text
- Complex or busy compositions

PROMPT;

    if (!empty($contentSummary)) {
        $prompt .= "\nTopic context: {$contentSummary}\n";
    }

    if (!empty($additionalInstructions)) {
        $prompt .= "\nAdditional requirements: {$additionalInstructions}\n";
    }

    $prompt .= "\n\nFINAL REMINDER: This image must contain ABSOLUTELY NO TEXT of any kind. Pure visual only.";

    return trim($prompt);
}
```

## 사용 예시

### 입력
- postTitle: "학습 기술 트렌드 2026"
- contentSummary: "AI 분석 기반 학습"

### 생성되는 프롬프트
```
IMPORTANT: ABSOLUTELY NO TEXT IN THIS IMAGE. Zero text, zero letters, zero words, zero typography.

Create a minimal editorial photo for: "학습 기술 트렌드 2026"

MANDATORY RULES (MUST FOLLOW):
1. ZERO TEXT - No text, no letters, no words, no numbers, no titles, no labels anywhere
2. SOLID COLOR BACKGROUND - Single bold color (coral, teal, mustard, sage green, terracotta, navy)
3. MINIMAL OBJECTS - Just 2-3 simple objects that represent the topic

STYLE - Minimal Flat Lay Photography:
- Clean flat-lay style with solid single color background
- 2-3 real objects arranged simply on the colored surface
- Minimalist composition with lots of empty space
- Natural lighting with soft shadows
- Professional product photography aesthetic

OBJECTS TO USE (pick 2-3 only):
- Simple props: notebook, pen, glasses, plant, coffee cup, headphones
- Or: hands holding an object related to the topic
- Or: nature elements like leaves, flowers

THIS IMAGE MUST BE:
- TEXT-FREE (no titles, labels, captions, watermarks)
- Minimal and clean
- Real photography style, not digital art
- Professional and elegant

DO NOT INCLUDE:
- ANY text, letters, words, or typography
- Titles or headings
- Labels or captions
- Digital elements or screens with text
- Complex or busy compositions

Topic context: AI 분석 기반 학습

FINAL REMINDER: This image must contain ABSOLUTELY NO TEXT of any kind. Pure visual only.
```

---
*최종 업데이트: 2026-01-12*
