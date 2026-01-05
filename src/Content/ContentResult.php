<?php
/**
 * Content Result DTO
 *
 * @package AIContentRewriter\Content
 */

namespace AIContentRewriter\Content;

/**
 * 콘텐츠 추출 결과 데이터 클래스
 */
class ContentResult {
    /**
     * 성공 여부
     */
    private bool $success;

    /**
     * 추출된 콘텐츠
     */
    private string $content;

    /**
     * 제목
     */
    private string $title;

    /**
     * 메타데이터
     */
    private array $metadata;

    /**
     * 에러 메시지
     */
    private ?string $error_message;

    /**
     * 생성자
     */
    private function __construct(
        bool $success,
        string $content = '',
        string $title = '',
        array $metadata = [],
        ?string $error_message = null
    ) {
        $this->success = $success;
        $this->content = $content;
        $this->title = $title;
        $this->metadata = $metadata;
        $this->error_message = $error_message;
    }

    /**
     * 성공 결과 생성
     */
    public static function success(string $content, string $title = '', array $metadata = []): self {
        return new self(true, $content, $title, $metadata);
    }

    /**
     * 실패 결과 생성
     */
    public static function error(string $message): self {
        return new self(false, '', '', [], $message);
    }

    // Getters
    public function is_success(): bool {
        return $this->success;
    }

    public function get_content(): string {
        return $this->content;
    }

    public function get_title(): string {
        return $this->title;
    }

    public function get_metadata(): array {
        return $this->metadata;
    }

    public function get_error_message(): ?string {
        return $this->error_message;
    }

    /**
     * 콘텐츠 길이
     */
    public function get_content_length(): int {
        return mb_strlen($this->content);
    }

    /**
     * 단어 수 (대략적)
     */
    public function get_word_count(): int {
        return str_word_count($this->content) +
               preg_match_all('/[\x{AC00}-\x{D7AF}]+/u', $this->content);
    }

    /**
     * 배열로 변환
     */
    public function to_array(): array {
        return [
            'success' => $this->success,
            'content' => $this->content,
            'title' => $this->title,
            'metadata' => $this->metadata,
            'error_message' => $this->error_message,
            'content_length' => $this->get_content_length(),
            'word_count' => $this->get_word_count(),
        ];
    }
}
