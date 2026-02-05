<?php
/**
 * Prompt Manager
 *
 * @package AIContentRewriter\Content
 */

namespace AIContentRewriter\Content;

/**
 * 프롬프트 관리 클래스
 * wp_options 기반으로 프롬프트 저장 및 관리
 */
class PromptManager {
    /**
     * 프롬프트 옵션 키
     */
    private const OPTION_KEY = 'aicr_prompt_blog_post';

    /**
     * 기본 프롬프트 (옵션에 저장된 값이 없을 때 사용)
     */
    private const DEFAULT_PROMPT = '당신은 전문 블로그 콘텐츠 작성자입니다. 다음 원본 콘텐츠를 분석하여 워드프레스 블로그 게시글에 필요한 모든 필드를 생성해주세요.

## 원본 콘텐츠
제목: {{title}}
출처: {{source_url}}

내용:
{{content}}

---

## 요구사항

다음 JSON 형식으로 워드프레스 게시글의 모든 필드를 생성해주세요:

```json
{
  "post_title": "SEO 최적화된 매력적인 제목 (60자 이내)",
  "post_content": "HTML 형식의 본문 콘텐츠 (아래 구조 참고)",
  "meta_title": "검색엔진용 메타 타이틀 (60자 이내)",
  "meta_description": "검색 결과에 표시될 메타 설명 (155자 이내, 핵심 키워드 포함)",
  "focus_keyword": "주요 타겟 키워드 1개",
  "keywords": ["관련 키워드 5-8개"],
  "tags": ["태그 3-5개"],
  "category_suggestion": "추천 카테고리명",
  "excerpt": "게시글 요약 (150자 이내)"
}
```

## 분량 요구사항 (매우 중요 - 반드시 준수)

**본문 분량은 원본 콘텐츠의 1.5배 이상으로 작성해야 합니다.**
- 절대로 요약하거나 축약하지 마세요
- 원본의 모든 정보를 포함하고, 추가 설명과 예시를 덧붙여 확장하세요
- 원본이 1000단어라면 최소 1500단어 이상으로 작성하세요
- 각 섹션의 내용을 풍부하게 설명하고, 독자가 이해하기 쉽도록 상세히 기술하세요
- 원본에서 간략히 언급된 개념은 더 자세히 풀어서 설명하세요
- 각 포인트마다 실제 사례, 통계, 전문가 의견 등을 추가하세요
- 독자에게 실질적인 도움이 되는 구체적인 팁과 조언을 포함하세요

## 본문 작성 가이드라인

1. **도입부**: 독자의 관심을 끄는 첫 문단 (문제 제기 또는 흥미로운 사실) - 3-4개 문단으로 충분히 작성
2. **본문 구조**:
   - H2 소제목으로 6-8개 섹션 구분 (원본보다 더 세분화)
   - 각 섹션에 구체적인 정보, 예시, 사례를 풍부하게 포함
   - 각 섹션당 최소 3-4개 문단 이상 작성
   - 리스트(<ul>, <ol>)를 활용하되 각 항목에 2-3문장의 상세 설명 추가
   - 중요 내용은 <strong> 태그로 강조
   - 필요시 H3 소제목으로 세부 섹션 추가
3. **결론**: 핵심 내용 요약, 실행 가능한 조언, 행동 유도(CTA) - 3-4개 문단으로 마무리

## 본문 HTML 형식 예시

```html
<p>도입부 문단 1 - 독자의 관심을 끄는 내용, 배경 설명</p>
<p>도입부 문단 2 - 이 글에서 다룰 주요 내용 소개</p>

<h2>첫 번째 섹션 제목</h2>
<p>섹션 도입 설명...</p>
<p>상세 내용 설명...</p>
<ul>
  <li><strong>포인트 1</strong>: 상세 설명과 예시</li>
  <li><strong>포인트 2</strong>: 상세 설명과 예시</li>
  <li><strong>포인트 3</strong>: 상세 설명과 예시</li>
</ul>
<p>추가 설명 및 실무 적용 방법...</p>

<h2>두 번째 섹션 제목</h2>
<p>섹션 내용을 풍부하게...</p>
<p>구체적인 예시와 함께 설명...</p>

<h2>세 번째 섹션 제목</h2>
<p>더 많은 정보와 인사이트...</p>

<h2>결론</h2>
<p>핵심 요약 및 정리...</p>
<p>독자를 위한 실행 가능한 조언...</p>
<p>마무리 및 행동 유도...</p>
```

## 작성 언어
{{target_language}}로 모든 콘텐츠를 작성해주세요.

## 중요 사항
- **분량 확장**: 원본 콘텐츠의 1.5배 이상으로 작성 (절대 요약 금지, 반드시 확장)
- 원본의 핵심 정보는 유지하되, 완전히 새로운 문장으로 재작성
- 표절 방지를 위해 원문을 그대로 복사하지 않음
- SEO 최적화를 위해 focus_keyword를 제목, 본문 첫 100단어, H2 헤딩에 자연스럽게 포함
- 가독성을 위해 문단은 3-4줄 이내로 유지하되, 문단 수를 늘려 전체 분량 확보
- 각 주제에 대해 "왜", "어떻게", "무엇을"의 관점에서 심층적으로 설명

JSON 형식으로만 응답해주세요.';

    /**
     * 싱글톤 인스턴스
     */
    private static ?PromptManager $instance = null;

    /**
     * 싱글톤 인스턴스 반환
     */
    public static function get_instance(): PromptManager {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 현재 프롬프트 가져오기
     * wp_options에 저장된 값이 있으면 반환, 없으면 기본값 반환
     *
     * @return string 프롬프트 내용
     */
    public function get_prompt(): string {
        $saved_prompt = get_option(self::OPTION_KEY);
        return $saved_prompt ?: self::DEFAULT_PROMPT;
    }

    /**
     * 기본 프롬프트 반환 (복원용)
     *
     * @return string 기본 프롬프트
     */
    public function get_default_prompt(): string {
        return self::DEFAULT_PROMPT;
    }

    /**
     * 프롬프트에 변수 적용하여 최종 프롬프트 생성
     *
     * @param string $template_type 템플릿 타입 (하위 호환성 유지, 현재는 무시됨)
     * @param array $variables 치환할 변수들
     * @param int|null $custom_template_id 사용자 템플릿 ID (하위 호환성 유지, 현재는 무시됨)
     * @return string 변수가 적용된 최종 프롬프트
     */
    public function build_prompt(
        string $template_type,
        array $variables,
        ?int $custom_template_id = null
    ): string {
        $prompt = $this->get_prompt();

        // 변수 치환
        foreach ($variables as $key => $value) {
            $prompt = str_replace('{{' . $key . '}}', (string) $value, $prompt);
        }

        return $prompt;
    }

    /**
     * 사용 가능한 변수 목록
     *
     * @return array 변수 이름 => 설명
     */
    public function get_available_variables(): array {
        return [
            'content' => __('원본 콘텐츠', 'ai-content-rewriter'),
            'title' => __('콘텐츠 제목', 'ai-content-rewriter'),
            'source_url' => __('원본 URL', 'ai-content-rewriter'),
            'target_language' => __('대상 언어', 'ai-content-rewriter'),
        ];
    }
}
