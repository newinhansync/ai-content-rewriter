# Infographic Prompt (인포그래픽 프롬프트)

AI Content Rewriter 플러그인에서 사용하는 콘텐츠 이미지 (인포그래픽) 생성 프롬프트입니다.

## 소스 위치
`wp-content/plugins/ai-content-rewriter/src/Image/ImagePromptManager.php`
- 메서드: `build_content_prompt()`
- 라인: 343-436

## 사용 모델
**Nano Banana Pro (Gemini 3 Pro Image)** - `gemini-3-pro-image-preview`
- 2025년 11월 출시
- 한글 텍스트 렌더링 향상
- 최대 4K 해상도 지원

## 프롬프트 스타일
**Flat 2D Vector Infographic** - Canva/Venngage 스타일의 플랫 벡터 인포그래픽

## 프롬프트 템플릿

```
Digital vector infographic illustration, flat 2D design, NOT a photograph.

SUBJECT: "{$topic}"

CONTENT CONTEXT (for accurate Korean text):
"{$sectionContent}"  // 실제 섹션 콘텐츠 (500자 제한)

KEY TERMS TO INCLUDE IN INFOGRAPHIC (use these exact Korean words):
{$keywords}  // 핵심 키워드 목록

CRITICAL STYLE REQUIREMENTS:
- Style: FLAT 2D VECTOR ILLUSTRATION (like Canva infographic templates)
- Background: Pure white (#FFFFFF) or very light gray (#F5F5F5)
- NO photorealistic elements
- NO 3D rendering
- NO camera/photography effects

KOREAN TEXT REQUIREMENTS (중요):
- Use ONLY the Korean terms from the content context above
- Do NOT invent or generate random Korean text
- If you need labels, use the exact keywords provided
- Prefer icons and visual elements over text when possible
- Any Korean text must be semantically correct and readable

VISUAL ELEMENTS (MANDATORY):
- Simple geometric shapes (circles, rectangles, hexagons)
- Flat icons representing concepts
- Arrows and connecting lines for flow
- Data visualization elements (charts, graphs, diagrams)
- Minimal text labels (prefer icons)
- Numbered steps or bullet points

COLOR PALETTE:
- Primary: Blue (#3B82F6) or Teal (#14B8A6)
- Secondary: Orange (#F97316) or Purple (#8B5CF6)
- Accent: Green (#22C55E) for highlights
- Neutral: Gray (#6B7280) for text
- Background: White (#FFFFFF)

COMPOSITION:
- Clean grid-based layout
- Clear visual hierarchy
- Ample white space
- Professional business presentation style
- Information flows top-to-bottom or left-to-right

THIS IS A 2D INFOGRAPHIC:
- Like Canva, Venngage, or Piktochart templates
- Vector illustration style
- Flat design aesthetic
- Educational diagram format
- NOT a photo, NOT 3D, NOT realistic
```

## 동적 추가 요소

### Additional Instructions (선택)
```
Extra requirements: {$additionalInstructions}
```

### 최종 생성 지시어
```
Generate: A clean, professional 2D vector infographic illustrating "{$topic}" with flat icons, simple shapes, and clear data visualization. Pure digital illustration, NOT a photograph.
```

## PHP 코드

```php
public function build_content_prompt(
    string $topic,
    string $styleName = '',
    string $additionalInstructions = ''
): string {
    // 플랫 2D 벡터 인포그래픽 스타일 - 사진이 아닌 일러스트
    $prompt = <<<PROMPT
Digital vector infographic illustration, flat 2D design, NOT a photograph.

SUBJECT: "{$topic}"

CRITICAL STYLE REQUIREMENTS:
- Style: FLAT 2D VECTOR ILLUSTRATION (like Canva infographic templates)
- Background: Pure white (#FFFFFF) or very light gray (#F5F5F5)
- NO photorealistic elements
- NO 3D rendering
- NO camera/photography effects

VISUAL ELEMENTS (MANDATORY):
- Simple geometric shapes (circles, rectangles, hexagons)
- Flat icons representing concepts
- Arrows and connecting lines for flow
- Data visualization elements (charts, graphs, diagrams)
- Text labels in Korean (한글)
- Numbered steps or bullet points

COLOR PALETTE:
- Primary: Blue (#3B82F6) or Teal (#14B8A6)
- Secondary: Orange (#F97316) or Purple (#8B5CF6)
- Accent: Green (#22C55E) for highlights
- Neutral: Gray (#6B7280) for text
- Background: White (#FFFFFF)

COMPOSITION:
- Clean grid-based layout
- Clear visual hierarchy
- Ample white space
- Professional business presentation style
- Information flows top-to-bottom or left-to-right

THIS IS A 2D INFOGRAPHIC:
- Like Canva, Venngage, or Piktochart templates
- Vector illustration style
- Flat design aesthetic
- Educational diagram format
- NOT a photo, NOT 3D, NOT realistic

PROMPT;

    if (!empty($additionalInstructions)) {
        $prompt .= "\nExtra requirements: {$additionalInstructions}\n";
    }

    $prompt .= "\n\nGenerate: A clean, professional 2D vector infographic illustrating \"{$topic}\" with flat icons, simple shapes, and clear data visualization. Pure digital illustration, NOT a photograph.";

    return trim($prompt);
}
```

## 사용 예시

### 입력
- topic: "예측 분석과 데이터 인프라"
- styleName: "인포그래픽"

### 생성되는 프롬프트
```
Digital vector infographic illustration, flat 2D design, NOT a photograph.

SUBJECT: "예측 분석과 데이터 인프라"

CRITICAL STYLE REQUIREMENTS:
- Style: FLAT 2D VECTOR ILLUSTRATION (like Canva infographic templates)
- Background: Pure white (#FFFFFF) or very light gray (#F5F5F5)
- NO photorealistic elements
- NO 3D rendering
- NO camera/photography effects

VISUAL ELEMENTS (MANDATORY):
- Simple geometric shapes (circles, rectangles, hexagons)
- Flat icons representing concepts
- Arrows and connecting lines for flow
- Data visualization elements (charts, graphs, diagrams)
- Text labels in Korean (한글)
- Numbered steps or bullet points

COLOR PALETTE:
- Primary: Blue (#3B82F6) or Teal (#14B8A6)
- Secondary: Orange (#F97316) or Purple (#8B5CF6)
- Accent: Green (#22C55E) for highlights
- Neutral: Gray (#6B7280) for text
- Background: White (#FFFFFF)

COMPOSITION:
- Clean grid-based layout
- Clear visual hierarchy
- Ample white space
- Professional business presentation style
- Information flows top-to-bottom or left-to-right

THIS IS A 2D INFOGRAPHIC:
- Like Canva, Venngage, or Piktochart templates
- Vector illustration style
- Flat design aesthetic
- Educational diagram format
- NOT a photo, NOT 3D, NOT realistic

Generate: A clean, professional 2D vector infographic illustrating "예측 분석과 데이터 인프라" with flat icons, simple shapes, and clear data visualization. Pure digital illustration, NOT a photograph.
```

## 특징

| 항목 | 설명 |
|------|------|
| **모델** | Nano Banana Pro (`gemini-3-pro-image-preview`) |
| 스타일 | Flat 2D Vector Illustration |
| 배경 | 흰색 (#FFFFFF) 또는 연한 회색 (#F5F5F5) |
| 금지 요소 | 사진, 3D, 사실적 렌더링 |
| 필수 요소 | 기하학 도형, 플랫 아이콘, 화살표, 차트/그래프 |
| 레이아웃 | 그리드 기반, 명확한 계층 구조 |
| 참조 플랫폼 | Canva, Venngage, Piktochart |
| **한글 텍스트** | 섹션 콘텐츠 기반 정확한 용어 사용 |

## 2026-01-12 업데이트 내용

### 모델 업그레이드
- **이전**: `imagen-4.0-generate-001`
- **현재**: `gemini-3-pro-image-preview` (Nano Banana Pro)
- 한글 텍스트 렌더링 대폭 개선

### 프롬프트 개선
- 실제 섹션 콘텐츠를 프롬프트에 포함 (500자 제한)
- 핵심 키워드를 명시적으로 전달
- "KOREAN TEXT REQUIREMENTS" 섹션 추가로 무작위 한글 생성 방지

---
*최종 업데이트: 2026-01-12*
