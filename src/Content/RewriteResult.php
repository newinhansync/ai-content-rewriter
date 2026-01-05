<?php
/**
 * Rewrite Result DTO
 *
 * @package AIContentRewriter\Content
 */

namespace AIContentRewriter\Content;

/**
 * 콘텐츠 변환 결과 데이터 클래스
 */
class RewriteResult {
    /**
     * 성공 여부
     */
    private bool $success;

    /**
     * 변환된 콘텐츠
     */
    private string $content;

    /**
     * 메타데이터
     */
    private array $metadata;

    /**
     * 에러 메시지
     */
    private ?string $error_message;

    /**
     * 에러 코드
     */
    private ?string $error_code;

    /**
     * 생성자
     */
    private function __construct(
        bool $success,
        string $content = '',
        array $metadata = [],
        ?string $error_message = null,
        ?string $error_code = null
    ) {
        $this->success = $success;
        $this->content = $content;
        $this->metadata = $metadata;
        $this->error_message = $error_message;
        $this->error_code = $error_code;
    }

    /**
     * 성공 결과 생성
     */
    public static function success(string $content, array $metadata = []): self {
        return new self(true, $content, $metadata);
    }

    /**
     * 실패 결과 생성
     */
    public static function error(string $message, ?string $code = null): self {
        return new self(false, '', [], $message, $code);
    }

    // Getters
    public function is_success(): bool {
        return $this->success;
    }

    public function get_content(): string {
        return $this->content;
    }

    public function get_metadata(): array {
        return $this->metadata;
    }

    public function get_error_message(): ?string {
        return $this->error_message;
    }

    public function get_error_code(): ?string {
        return $this->error_code;
    }

    /**
     * 특정 메타데이터 값 반환
     */
    public function get_meta(string $key, mixed $default = null): mixed {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * 토큰 사용량
     */
    public function get_tokens_used(): int {
        return $this->metadata['tokens_used'] ?? 0;
    }

    /**
     * 처리 시간
     */
    public function get_processing_time(): float {
        return $this->metadata['processing_time'] ?? 0.0;
    }

    /**
     * JSON 응답 파싱 (blog_post 템플릿용)
     *
     * @return array|null 파싱된 JSON 데이터 또는 null
     */
    public function parse_json_response(): ?array {
        // JSON 블록 추출 시도
        if (preg_match('/```json\s*(.*?)\s*```/s', $this->content, $matches)) {
            $json_str = $matches[1];
        } elseif (preg_match('/\{[\s\S]*\}/s', $this->content, $matches)) {
            // 중괄호로 시작하는 JSON 객체 추출
            $json_str = $matches[0];
        } else {
            return null;
        }

        $data = json_decode($json_str, true);
        return is_array($data) ? $data : null;
    }

    /**
     * JSON 응답인지 확인
     */
    public function is_json_response(): bool {
        return $this->parse_json_response() !== null;
    }

    /**
     * 제목 추출 (첫 번째 H1 또는 첫 줄)
     */
    public function extract_title(): string {
        // H1 태그에서 추출
        if (preg_match('/^#\s+(.+)$/m', $this->content, $matches)) {
            return trim($matches[1]);
        }

        // 첫 줄에서 추출
        $lines = explode("\n", trim($this->content));
        $first_line = trim($lines[0] ?? '');

        // 마크다운 헤더 제거
        $first_line = preg_replace('/^#+\s*/', '', $first_line);

        return mb_substr($first_line, 0, 200);
    }

    /**
     * WordPress 포스트로 변환
     */
    public function to_post_data(array $extra = []): array {
        // JSON 응답 확인 (blog_post 템플릿)
        $json_data = $this->parse_json_response();

        if ($json_data) {
            return $this->build_post_data_from_json($json_data, $extra);
        }

        // 기존 마크다운 처리
        return $this->build_post_data_from_markdown($extra);
    }

    /**
     * JSON 데이터에서 포스트 데이터 생성
     */
    private function build_post_data_from_json(array $json_data, array $extra): array {
        $post_data = [
            'post_title' => $json_data['post_title'] ?? '',
            'post_content' => $json_data['post_content'] ?? '',
            'post_excerpt' => $json_data['excerpt'] ?? '',
            'post_status' => 'draft',
            'post_type' => 'post',
            'meta_input' => [
                '_aicr_source_url' => $this->metadata['source_url'] ?? '',
                '_aicr_tokens_used' => $this->get_tokens_used(),
                '_aicr_ai_provider' => $this->metadata['ai_provider'] ?? '',
                '_aicr_generated_at' => current_time('mysql'),
                // SEO 메타데이터
                '_aicr_meta_title' => $json_data['meta_title'] ?? '',
                '_aicr_meta_description' => $json_data['meta_description'] ?? '',
                '_aicr_focus_keyword' => $json_data['focus_keyword'] ?? '',
                '_aicr_keywords' => implode(', ', $json_data['keywords'] ?? []),
                '_aicr_category_suggestion' => $json_data['category_suggestion'] ?? '',
            ],
        ];

        // 태그 설정
        if (!empty($json_data['tags']) && is_array($json_data['tags'])) {
            $post_data['tags_input'] = $json_data['tags'];
        }

        return array_merge($post_data, $extra);
    }

    /**
     * 마크다운에서 포스트 데이터 생성
     */
    private function build_post_data_from_markdown(array $extra): array {
        $title = $this->extract_title();
        $content = $this->content;

        // 제목이 콘텐츠에 포함되어 있으면 제거
        $content = preg_replace('/^#\s+.+\n+/', '', $content, 1);

        return array_merge([
            'post_title' => $title,
            'post_content' => $this->convert_markdown_to_html($content),
            'post_status' => 'draft',
            'post_type' => 'post',
            'meta_input' => [
                '_aicr_source_url' => $this->metadata['source_url'] ?? '',
                '_aicr_tokens_used' => $this->get_tokens_used(),
                '_aicr_ai_provider' => $this->metadata['ai_provider'] ?? '',
                '_aicr_generated_at' => current_time('mysql'),
            ],
        ], $extra);
    }

    /**
     * 간단한 마크다운 -> HTML 변환
     */
    private function convert_markdown_to_html(string $markdown): string {
        // 헤더 변환
        $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $markdown);
        $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);

        // 볼드/이탤릭
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);

        // 링크
        $html = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $html);

        // 리스트
        $html = preg_replace('/^- (.+)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/(<li>.+<\/li>\n?)+/', "<ul>\n$0</ul>\n", $html);

        // 문단
        $paragraphs = preg_split('/\n{2,}/', $html);
        $paragraphs = array_map(function ($p) {
            $p = trim($p);
            if (empty($p)) {
                return '';
            }
            // 이미 태그로 감싸진 경우 제외
            if (preg_match('/^<(h[1-6]|ul|ol|li|blockquote)/', $p)) {
                return $p;
            }
            return "<p>{$p}</p>";
        }, $paragraphs);

        return implode("\n\n", array_filter($paragraphs));
    }

    /**
     * 배열로 변환
     */
    public function to_array(): array {
        return [
            'success' => $this->success,
            'content' => $this->content,
            'metadata' => $this->metadata,
            'error_message' => $this->error_message,
            'error_code' => $this->error_code,
            'title' => $this->extract_title(),
        ];
    }
}
