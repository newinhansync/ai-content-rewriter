<?php
/**
 * Prompt Manager
 *
 * @package AIContentRewriter\Content
 */

namespace AIContentRewriter\Content;

/**
 * 프롬프트 템플릿 관리 클래스
 */
class PromptManager {
    /**
     * 기본 템플릿 정의
     */
    private const DEFAULT_TEMPLATES = [
        'blog_post' => [
            'name' => '워드프레스 블로그 포스트 생성',
            'type' => 'blog_post',
            'content' => '당신은 전문 블로그 콘텐츠 작성자입니다. 다음 원본 콘텐츠를 분석하여 워드프레스 블로그 게시글에 필요한 모든 필드를 생성해주세요.

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
  "excerpt": "게시글 요약 (150자 이내)",
  "summary_table": "본문 핵심 내용을 발췌하여 정리한 HTML 표 (아래 가이드 참고)"
}
```

## ⚠️ 분량 요구사항 (매우 중요 - 반드시 준수)

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

## 핵심 내용 발췌 표 (summary_table) 작성 가이드

글에서 독자에게 가장 중요한 정보를 **발췌**하여 HTML 표로 정리해주세요.

**중요**: 고정된 형식을 사용하지 마세요. 글의 주제와 내용에 따라 가장 적합한 컬럼과 행을 직접 설계하세요.

### 발췌 표 설계 원칙

1. **글의 성격에 맞는 컬럼 구성**
   - 제품/서비스 비교글: 항목명 | 특징 | 장점 | 단점 | 가격
   - 방법/절차 설명글: 단계 | 작업 내용 | 소요 시간 | 주의사항
   - 정보/데이터 분석글: 항목 | 수치/데이터 | 의미/해석
   - 리뷰/평가글: 평가 항목 | 점수 | 상세 내용
   - 기술/개념 설명글: 용어 | 정의 | 활용 예시
   - 뉴스/트렌드 분석글: 키워드 | 현황 | 전망

2. **발췌할 내용 선정 기준**
   - 본문에서 가장 중요한 사실, 수치, 비교 정보를 추출
   - 독자가 반드시 알아야 할 핵심 정보 위주로 선별
   - 본문 내용을 그대로 발췌하거나 핵심만 간결하게 정리

3. **표 형식 예시** (글 내용에 따라 자유롭게 변형)

```html
<table class="aicr-summary-table" style="width:100%; border-collapse:collapse; margin:20px 0;">
  <thead>
    <tr style="background-color:#f8f9fa;">
      <th style="border:1px solid #dee2e6; padding:12px; text-align:left;">컬럼1</th>
      <th style="border:1px solid #dee2e6; padding:12px; text-align:left;">컬럼2</th>
      <th style="border:1px solid #dee2e6; padding:12px; text-align:left;">컬럼3</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td style="border:1px solid #dee2e6; padding:10px;">발췌 내용 1</td>
      <td style="border:1px solid #dee2e6; padding:10px;">발췌 내용 2</td>
      <td style="border:1px solid #dee2e6; padding:10px;">발췌 내용 3</td>
    </tr>
    <!-- 필요한 만큼 행 추가 -->
  </tbody>
</table>
```

### 발췌 표 작성 시 필수 준수사항
- 컬럼 수: 2~5개 (글의 내용에 맞게 결정)
- 행 수: 3~10개 (핵심 정보량에 맞게 결정)
- 본문의 구체적인 정보(숫자, 이름, 특징 등)를 발췌하여 기재
- 추상적인 설명이 아닌 본문에서 직접 가져온 실제 내용으로 채우기
- 표 제목(caption)은 넣지 않음 (별도 제공됨)

## 작성 언어
{{target_language}}로 모든 콘텐츠를 작성해주세요.

## 중요 사항
- **분량 확장**: 원본 콘텐츠의 1.5배 이상으로 작성 (절대 요약 금지, 반드시 확장)
- 원본의 핵심 정보는 유지하되, 완전히 새로운 문장으로 재작성
- 표절 방지를 위해 원문을 그대로 복사하지 않음
- SEO 최적화를 위해 focus_keyword를 제목, 본문 첫 100단어, H2 헤딩에 자연스럽게 포함
- 가독성을 위해 문단은 3-4줄 이내로 유지하되, 문단 수를 늘려 전체 분량 확보
- 각 주제에 대해 "왜", "어떻게", "무엇을"의 관점에서 심층적으로 설명

JSON 형식으로만 응답해주세요.',
            'variables' => ['content', 'title', 'source_url', 'target_language'],
        ],
        'content_rewrite' => [
            'name' => '콘텐츠 재작성',
            'type' => 'rewrite',
            'content' => '다음 콘텐츠를 SEO 최적화된 블로그 포스트로 재작성해주세요. 원본의 핵심 정보를 유지하면서 독창적이고 매력적인 글로 변환해주세요.

원본 콘텐츠:
{{content}}

## ⚠️ 분량 요구사항 (매우 중요)
- **본문 분량은 원본 콘텐츠와 동일하거나 더 길게 작성해야 합니다**
- 절대로 요약하거나 축약하지 마세요
- 원본의 모든 정보를 포함하고, 추가 설명과 예시를 덧붙여 확장하세요
- 원본이 1000단어라면 최소 1000단어 이상으로 작성하세요
- 원본에서 간략히 언급된 개념은 더 자세히 풀어서 설명하세요

요구사항:
- 자연스러운 {{target_language}} 문장으로 작성
- SEO 친화적인 제목 포함
- 4-6개의 H2 소제목으로 세분화하여 구조화
- 각 섹션당 최소 2-3개 문단 이상 작성
- 핵심 키워드 자연스럽게 포함
- 독자의 관심을 끄는 도입부 (2-3개 문단)
- 구체적인 예시와 설명 포함
- 명확한 결론으로 마무리 (2-3개 문단)

출력 형식:
# [제목]

[본문 내용 - 원본과 동일하거나 더 긴 분량으로 작성]',
            'variables' => ['content', 'target_language'],
        ],
        'translate' => [
            'name' => '번역',
            'type' => 'translate',
            'content' => '다음 텍스트를 {{target_language}}로 번역해주세요. 자연스러운 표현을 사용하고 원문의 의미와 톤을 정확히 전달해주세요.

원문:
{{content}}

주의사항:
- 직역보다는 의역을 선호
- 문화적 맥락을 고려
- 전문 용어는 적절히 번역하거나 원어 병기
- 문단 구조 유지',
            'variables' => ['content', 'target_language'],
        ],
        'metadata' => [
            'name' => 'SEO 메타데이터 생성',
            'type' => 'metadata',
            'content' => '다음 블로그 포스트에 대해 SEO 메타데이터를 생성해주세요.

포스트 내용:
{{content}}

다음 JSON 형식으로 응답해주세요:
```json
{
  "meta_title": "SEO 최적화된 제목 (60자 이내)",
  "meta_description": "메타 설명 (160자 이내, 핵심 키워드 포함)",
  "focus_keyword": "주요 키워드",
  "keywords": ["키워드1", "키워드2", "키워드3", "키워드4", "키워드5"],
  "tags": ["태그1", "태그2", "태그3"],
  "category_suggestion": "추천 카테고리"
}
```',
            'variables' => ['content'],
        ],
        'summarize' => [
            'name' => '요약',
            'type' => 'summarize',
            'content' => '다음 콘텐츠를 간결하게 요약해주세요.

원본 콘텐츠:
{{content}}

요구사항:
- 핵심 포인트만 추출
- 3-5개의 주요 사항으로 정리
- 원문 길이의 20-30% 수준으로 요약
- {{target_language}}로 작성',
            'variables' => ['content', 'target_language'],
        ],
        'chunk_continuation' => [
            'name' => '청크 연속 처리',
            'type' => 'system',
            'content' => '이것은 긴 콘텐츠의 {{chunk_index}}/{{chunk_total}} 부분입니다.

이전 내용과 자연스럽게 이어지도록 다음 콘텐츠를 처리해주세요:
{{content}}

{{#if is_first}}
이것은 첫 번째 부분입니다. 적절한 도입부로 시작해주세요.
{{/if}}

{{#if is_last}}
이것은 마지막 부분입니다. 적절한 결론으로 마무리해주세요.
{{/if}}',
            'variables' => ['content', 'chunk_index', 'chunk_total', 'is_first', 'is_last'],
        ],
    ];

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
     * 기본 템플릿 반환
     */
    public function get_default_template(string $type): PromptTemplate {
        if (!isset(self::DEFAULT_TEMPLATES[$type])) {
            throw new \InvalidArgumentException(
                sprintf(__('알 수 없는 템플릿 유형: %s', 'ai-content-rewriter'), $type)
            );
        }

        $template_data = self::DEFAULT_TEMPLATES[$type];
        $template_data['is_default'] = true;

        return new PromptTemplate($template_data);
    }

    /**
     * 모든 기본 템플릿 반환
     */
    public function get_all_default_templates(): array {
        $templates = [];
        foreach (self::DEFAULT_TEMPLATES as $type => $data) {
            $data['is_default'] = true;
            $templates[$type] = new PromptTemplate($data);
        }
        return $templates;
    }

    /**
     * 사용자 템플릿 조회
     */
    public function get_user_template(int $template_id): ?PromptTemplate {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aicr_templates';
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $template_id),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        return new PromptTemplate([
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'type' => $row['type'],
            'content' => $row['content'],
            'variables' => json_decode($row['variables'] ?? '[]', true),
            'is_default' => (bool) $row['is_default'],
        ]);
    }

    /**
     * 사용자 템플릿 저장
     */
    public function save_user_template(PromptTemplate $template): int {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aicr_templates';

        $data = [
            'user_id' => get_current_user_id(),
            'name' => $template->get_name(),
            'type' => $template->get_type(),
            'content' => $template->get_content(),
            'variables' => wp_json_encode($template->get_variables()),
            'is_default' => $template->is_default() ? 1 : 0,
        ];

        if ($template->get_id()) {
            $wpdb->update($table_name, $data, ['id' => $template->get_id()]);
            return $template->get_id();
        }

        $wpdb->insert($table_name, $data);
        return $wpdb->insert_id;
    }

    /**
     * 사용자의 모든 템플릿 조회
     */
    public function get_user_templates(?int $user_id = null): array {
        global $wpdb;

        $user_id = $user_id ?? get_current_user_id();
        $table_name = $wpdb->prefix . 'aicr_templates';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE user_id = %d AND is_active = 1 ORDER BY name ASC",
                $user_id
            ),
            ARRAY_A
        );

        $templates = [];
        foreach ($rows as $row) {
            $templates[] = new PromptTemplate([
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'type' => $row['type'],
                'content' => $row['content'],
                'variables' => json_decode($row['variables'] ?? '[]', true),
                'is_default' => (bool) $row['is_default'],
            ]);
        }

        return $templates;
    }

    /**
     * 템플릿 삭제
     */
    public function delete_template(int $template_id): bool {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aicr_templates';

        return $wpdb->update(
            $table_name,
            ['is_active' => 0],
            ['id' => $template_id]
        ) !== false;
    }

    /**
     * 프롬프트 빌드 (템플릿 + 변수)
     */
    public function build_prompt(
        string $template_type,
        array $variables,
        ?int $custom_template_id = null
    ): string {
        // 커스텀 템플릿 우선
        if ($custom_template_id) {
            $template = $this->get_user_template($custom_template_id);
            if ($template) {
                return $template->render($variables);
            }
        }

        // 기본 템플릿 사용
        $template = $this->get_default_template($template_type);
        return $template->render($variables);
    }

    /**
     * 지원되는 변수 목록
     */
    public function get_available_variables(): array {
        return [
            'content' => __('원본 콘텐츠', 'ai-content-rewriter'),
            'title' => __('콘텐츠 제목', 'ai-content-rewriter'),
            'source_url' => __('원본 URL', 'ai-content-rewriter'),
            'target_language' => __('대상 언어', 'ai-content-rewriter'),
            'chunk_index' => __('현재 청크 번호', 'ai-content-rewriter'),
            'chunk_total' => __('전체 청크 수', 'ai-content-rewriter'),
            'is_first' => __('첫 번째 청크 여부', 'ai-content-rewriter'),
            'is_last' => __('마지막 청크 여부', 'ai-content-rewriter'),
            'keywords' => __('키워드 목록', 'ai-content-rewriter'),
            'tone' => __('글의 톤/스타일', 'ai-content-rewriter'),
            'word_count' => __('목표 단어 수', 'ai-content-rewriter'),
        ];
    }
}
