<?php
/**
 * Image Prompt Manager
 *
 * @package AIContentRewriter\Image
 */

namespace AIContentRewriter\Image;

/**
 * 이미지 프롬프트 관리 클래스 (싱글톤)
 *
 * 설정 페이지에서 프롬프트 템플릿을 직접 편집 가능
 */
class ImagePromptManager {
    /**
     * 싱글톤 인스턴스
     */
    private static ?ImagePromptManager $instance = null;

    /**
     * 프롬프트 옵션 키
     */
    private const OPTION_KEY = 'aicr_image_prompt';

    /**
     * 기본 이미지 생성 프롬프트
     */
    private const DEFAULT_PROMPT = <<<PROMPT
{{topic}}을 나타내는 {{style}} 스타일의 이미지.
블로그 게시글에 적합한, 전문적이고 깔끔한 디자인.
고품질, 선명함, 현대적인 느낌.
{{additional_instructions}}
PROMPT;

    /**
     * 생성자 (private for singleton)
     */
    private function __construct() {}

    /**
     * 복제 방지
     */
    private function __clone() {}

    /**
     * 싱글톤 인스턴스 반환
     */
    public static function get_instance(): ImagePromptManager {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 저장된 프롬프트 반환 (없으면 기본값)
     */
    public function get_prompt(): string {
        $saved_prompt = get_option(self::OPTION_KEY);
        return $saved_prompt ?: self::DEFAULT_PROMPT;
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
     *
     * @param array $variables 변수 배열
     * @return string 최종 프롬프트
     */
    public function build_prompt(array $variables): string {
        $template = $this->get_prompt();

        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', (string) $value, $template);
        }

        // 사용되지 않은 변수 제거
        $template = preg_replace('/\{\{[a-z_]+\}\}/i', '', $template);

        return trim($template);
    }

    /**
     * 스타일과 함께 전체 프롬프트 빌드
     *
     * @param string $topic 주제/섹션 내용
     * @param string $styleName 스타일 이름
     * @param string $additionalInstructions 추가 지시사항
     * @return string 최종 프롬프트
     */
    public function build_full_prompt(
        string $topic,
        string $styleName = '',
        string $additionalInstructions = ''
    ): string {
        $style = $this->get_style_by_name($styleName);

        $variables = [
            'topic' => $topic,
            'style' => $style['style_prompt'] ?? 'professional',
            'additional_instructions' => $additionalInstructions,
        ];

        $basePrompt = $this->build_prompt($variables);

        // 네거티브 프롬프트 추가 (있는 경우)
        if (!empty($style['negative_prompt'])) {
            $basePrompt .= "\n\nAvoid: " . $style['negative_prompt'];
        }

        return $basePrompt;
    }

    /**
     * 이름으로 스타일 조회
     */
    public function get_style_by_name(string $name): ?array {
        global $wpdb;

        if (empty($name)) {
            // 기본 스타일 반환
            return $this->get_default_style();
        }

        $table_name = $wpdb->prefix . 'aicr_image_styles';

        $style = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE name = %s LIMIT 1",
                $name
            ),
            ARRAY_A
        );

        return $style ?: $this->get_default_style();
    }

    /**
     * ID로 스타일 조회
     */
    public function get_style_by_id(int $id): ?array {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aicr_image_styles';

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );
    }

    /**
     * 기본 스타일 조회
     */
    public function get_default_style(): ?array {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aicr_image_styles';

        return $wpdb->get_row(
            "SELECT * FROM {$table_name} WHERE is_default = 1 LIMIT 1",
            ARRAY_A
        );
    }

    /**
     * 모든 스타일 조회
     */
    public function get_all_styles(): array {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aicr_image_styles';

        $styles = $wpdb->get_results(
            "SELECT * FROM {$table_name} ORDER BY is_default DESC, name ASC"
        );

        return $styles ?: [];
    }

    /**
     * 스타일 저장
     */
    public function save_style(array $data): int|bool {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aicr_image_styles';

        $style_data = [
            'name' => sanitize_text_field($data['name'] ?? ''),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'style_prompt' => sanitize_textarea_field($data['style_prompt'] ?? ''),
            'negative_prompt' => sanitize_textarea_field($data['negative_prompt'] ?? ''),
            'aspect_ratio' => sanitize_text_field($data['aspect_ratio'] ?? '16:9'),
            'is_default' => !empty($data['is_default']) ? 1 : 0,
        ];

        // 기본 스타일로 설정 시 기존 기본 스타일 해제
        if ($style_data['is_default']) {
            $wpdb->update($table_name, ['is_default' => 0], ['is_default' => 1]);
        }

        if (!empty($data['id'])) {
            // 업데이트
            $wpdb->update($table_name, $style_data, ['id' => (int) $data['id']]);
            return (int) $data['id'];
        }

        // 새로 삽입
        $wpdb->insert($table_name, $style_data);
        return $wpdb->insert_id;
    }

    /**
     * 스타일 삭제
     */
    public function delete_style(int $id): bool {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aicr_image_styles';

        // 기본 스타일은 삭제 불가
        $style = $this->get_style_by_id($id);
        if ($style && $style['is_default']) {
            return false;
        }

        return $wpdb->delete($table_name, ['id' => $id]) !== false;
    }

    /**
     * 사용 가능한 변수 목록
     */
    public function get_available_variables(): array {
        return [
            'topic' => __('섹션/단락의 주제', 'ai-content-rewriter'),
            'keywords' => __('추출된 키워드', 'ai-content-rewriter'),
            'style' => __('선택된 이미지 스타일', 'ai-content-rewriter'),
            'post_title' => __('게시글 제목', 'ai-content-rewriter'),
            'additional_instructions' => __('추가 지시사항', 'ai-content-rewriter'),
        ];
    }

    /**
     * 표지 이미지 프롬프트 빌드
     *
     * AI스럽지 않은 자연스러운 에디토리얼 스타일 이미지 생성
     * - 솔리드 컬러 배경
     * - 주제를 대표하는 오브제, 일러스트, 인물, 상황 이미지
     *
     * @param string $postTitle 게시글 제목
     * @param string $contentSummary 콘텐츠 요약
     * @param string $additionalInstructions 추가 지시사항
     * @return string 표지 이미지 프롬프트
     */
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

    /**
     * 콘텐츠 이미지 프롬프트 빌드
     *
     * 한글 텍스트가 포함된 인포그래픽 스타일
     * - 핵심 키워드를 한글로 표시
     * - 크고 굵은 산세리프 폰트로 가독성 확보
     * - Canva/Venngage 스타일의 깔끔한 시각화
     *
     * @param string $topic 섹션 주제/내용
     * @param string $styleName 스타일 이름 (참고용)
     * @param string $additionalInstructions 추가 지시사항
     * @param string $sectionContent 실제 섹션 콘텐츠 (컨텍스트 참고용)
     * @param array $keywords 키워드 배열 (한글 레이블로 표시)
     * @return string 콘텐츠 이미지 프롬프트
     */
    public function build_content_prompt(
        string $topic,
        string $styleName = '',
        string $additionalInstructions = '',
        string $sectionContent = '',
        array $keywords = []
    ): string {
        // 주제 (한글 유지)
        $topicTitle = mb_substr($topic, 0, 30); // 30자 제한

        $prompt = <<<PROMPT
Create a professional Korean infographic about: "{$topicTitle}"

=== CRITICAL: KOREAN TEXT REQUIREMENTS ===
This infographic MUST include Korean text (한글). The text must be:
1. LARGE and BOLD - minimum 48pt equivalent size
2. CLEAN SANS-SERIF FONT - like Noto Sans KR, Pretendard, or similar modern Korean font
3. HIGH CONTRAST - dark text (#1F2937) on light backgrounds, or white text on colored backgrounds
4. SIMPLE WORDS ONLY - use 2-4 character Korean keywords, not full sentences
5. CLEAR SPACING - generous padding around all text elements

=== STYLE: Modern Korean Infographic ===
- Design style: Clean, minimal infographic like Canva or Figma templates
- Layout: Structured grid with clear sections
- Background: White (#FFFFFF) or very light gray (#F9FAFB)
- NOT a photograph, NOT 3D rendering

=== KOREAN TEXT TO INCLUDE ===
Main Title (top): "{$topicTitle}"
PROMPT;

        // 키워드를 한글 레이블로 추가
        if (!empty($keywords)) {
            $koreanLabels = $this->formatKoreanKeywords($keywords);
            $prompt .= "\n\nKeyword Labels to display in Korean:\n{$koreanLabels}";
        }

        $prompt .= <<<PROMPT


=== VISUAL ELEMENTS ===
- Simple flat icons next to each Korean keyword
- Connecting lines or arrows between concepts
- Color-coded sections (Blue #3B82F6, Teal #14B8A6, Orange #F97316)
- Numbered circles (1, 2, 3, 4) for sequential items
- Clean geometric shapes (rounded rectangles, circles)

=== TYPOGRAPHY RULES ===
- Title: Extra bold, 48-72pt equivalent, centered at top
- Keywords: Bold, 32-48pt equivalent, inside colored boxes or next to icons
- All Korean text must be PERFECTLY RENDERED with no distortion or artifacts
- Use standard Korean characters only (가-힣)

=== LAYOUT ===
- Clean grid structure (2x2, 3x1, or flowchart style)
- Each concept in its own visual container
- Generous white space between elements
- Professional business presentation aesthetic

PROMPT;

        if (!empty($additionalInstructions)) {
            $prompt .= "\nAdditional requirements: {$additionalInstructions}\n";
        }

        $prompt .= <<<PROMPT

=== FINAL OUTPUT ===
Generate a clean, professional Korean infographic with:
1. The title "{$topicTitle}" prominently displayed at the top in Korean
2. 3-5 Korean keyword labels with icons
3. Modern flat design aesthetic
4. All Korean text must be crisp, clear, and perfectly readable

IMPORTANT: The Korean text (한글) is the MOST important element. It must be rendered clearly without any distortion, blur, or character corruption.
PROMPT;

        return trim($prompt);
    }

    /**
     * 키워드를 한글 레이블 형식으로 포맷
     */
    private function formatKoreanKeywords(array $keywords): string {
        $labels = [];
        $count = 0;
        foreach ($keywords as $keyword) {
            if ($count >= 5) break; // 최대 5개
            $keyword = trim($keyword);
            if (!empty($keyword) && mb_strlen($keyword) <= 10) {
                $labels[] = "- " . $keyword;
                $count++;
            }
        }
        return implode("\n", $labels);
    }

    /**
     * 주제에서 영어 설명 추출
     * 한글 주제를 시각적 컨셉으로 변환
     */
    private function extractTopicDescription(string $topic, string $content = ''): string {
        // 한글 주제를 영어 컨셉으로 매핑 (일반적인 블로그 주제들)
        $topicMappings = [
            '소개' => 'introduction and overview',
            '개요' => 'overview and summary',
            '방법' => 'step-by-step process',
            '단계' => 'sequential steps',
            '장점' => 'benefits and advantages',
            '특징' => 'key features',
            '비교' => 'comparison chart',
            '분석' => 'analysis diagram',
            '결론' => 'conclusion summary',
            '팁' => 'helpful tips',
            '가이드' => 'guide flowchart',
            '전략' => 'strategy framework',
            '트렌드' => 'trend visualization',
            '통계' => 'statistics and data',
            '사례' => 'case study example',
            '원리' => 'principle diagram',
            '구조' => 'structure diagram',
            '과정' => 'process flow',
            '요약' => 'summary overview',
        ];

        // 한글 키워드 매칭
        foreach ($topicMappings as $korean => $english) {
            if (mb_strpos($topic, $korean) !== false) {
                return $english;
            }
        }

        // 매칭되지 않으면 일반적인 비즈니스/기술 인포그래픽으로
        return 'business concept visualization with icons and diagrams';
    }

    /**
     * 키워드를 시각적 요소 설명으로 변환
     */
    private function convertKeywordsToVisualElements(array $keywords): string {
        $visualMappings = [
            // 일반적인 비즈니스/기술 용어 -> 아이콘 설명
            '데이터' => 'bar chart icon',
            '분석' => 'magnifying glass with graph',
            '성장' => 'upward arrow',
            '보안' => 'shield icon',
            '속도' => 'speedometer icon',
            '효율' => 'gear mechanism',
            '비용' => 'coin stack icon',
            '시간' => 'clock icon',
            '사용자' => 'person silhouette',
            '팀' => 'group of people icons',
            '목표' => 'target bullseye',
            '성공' => 'trophy icon',
            '아이디어' => 'lightbulb icon',
            '연결' => 'connected nodes',
            '클라우드' => 'cloud icon',
            '모바일' => 'smartphone icon',
            '웹' => 'globe icon',
            '이메일' => 'envelope icon',
            '검색' => 'magnifying glass',
            '설정' => 'gear icon',
        ];

        $elements = [];
        foreach ($keywords as $keyword) {
            foreach ($visualMappings as $korean => $icon) {
                if (mb_strpos($keyword, $korean) !== false) {
                    $elements[] = "- {$icon}";
                    break;
                }
            }
        }

        // 매칭된 것이 없으면 일반 아이콘 제안
        if (empty($elements)) {
            $elements = [
                '- Abstract geometric shapes',
                '- Connected flowchart nodes',
                '- Colorful icon set',
            ];
        }

        return implode("\n", array_unique(array_slice($elements, 0, 5)));
    }
}
