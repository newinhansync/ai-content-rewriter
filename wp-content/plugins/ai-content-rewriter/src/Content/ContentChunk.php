<?php
/**
 * Content Chunk DTO
 *
 * @package AIContentRewriter\Content
 */

namespace AIContentRewriter\Content;

/**
 * 콘텐츠 청크 데이터 클래스
 */
class ContentChunk {
    /**
     * 원본 콘텐츠
     */
    private string $content;

    /**
     * 처리된 콘텐츠
     */
    private ?string $processed_content = null;

    /**
     * 청크 인덱스 (0부터 시작)
     */
    private int $index;

    /**
     * 총 청크 수
     */
    private int $total;

    /**
     * 원본에서의 시작 위치
     */
    private int $start_position;

    /**
     * 원본에서의 끝 위치
     */
    private int $end_position;

    /**
     * 처리 상태
     */
    private string $status = 'pending';

    /**
     * 메타데이터
     */
    private array $metadata = [];

    /**
     * 생성자
     */
    public function __construct(
        string $content,
        int $index,
        int $total,
        int $start_position,
        int $end_position
    ) {
        $this->content = $content;
        $this->index = $index;
        $this->total = $total;
        $this->start_position = $start_position;
        $this->end_position = $end_position;
    }

    // Getters
    public function get_content(): string {
        return $this->content;
    }

    public function get_processed_content(): ?string {
        return $this->processed_content;
    }

    public function get_index(): int {
        return $this->index;
    }

    public function get_total(): int {
        return $this->total;
    }

    public function get_start_position(): int {
        return $this->start_position;
    }

    public function get_end_position(): int {
        return $this->end_position;
    }

    public function get_status(): string {
        return $this->status;
    }

    public function get_metadata(): array {
        return $this->metadata;
    }

    // Setters
    public function set_processed_content(string $content): self {
        $this->processed_content = $content;
        return $this;
    }

    public function set_total(int $total): self {
        $this->total = $total;
        return $this;
    }

    public function set_status(string $status): self {
        $this->status = $status;
        return $this;
    }

    public function set_metadata(array $metadata): self {
        $this->metadata = $metadata;
        return $this;
    }

    public function add_metadata(string $key, mixed $value): self {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * 청크 길이 (문자 수)
     */
    public function get_length(): int {
        return mb_strlen($this->content);
    }

    /**
     * 처리 완료 여부
     */
    public function is_processed(): bool {
        return $this->processed_content !== null;
    }

    /**
     * 첫 번째 청크인지 확인
     */
    public function is_first(): bool {
        return $this->index === 0;
    }

    /**
     * 마지막 청크인지 확인
     */
    public function is_last(): bool {
        return $this->index === $this->total - 1;
    }

    /**
     * 청크 식별자 반환
     */
    public function get_identifier(): string {
        return sprintf('chunk_%d_of_%d', $this->index + 1, $this->total);
    }

    /**
     * 배열로 변환
     */
    public function to_array(): array {
        return [
            'content' => $this->content,
            'processed_content' => $this->processed_content,
            'index' => $this->index,
            'total' => $this->total,
            'start_position' => $this->start_position,
            'end_position' => $this->end_position,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'length' => $this->get_length(),
        ];
    }
}
