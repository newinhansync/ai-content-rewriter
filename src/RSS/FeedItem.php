<?php
/**
 * Feed Item Entity
 *
 * @package AIContentRewriter\RSS
 */

namespace AIContentRewriter\RSS;

/**
 * RSS 피드 아이템 엔티티 클래스
 */
class FeedItem {
    private ?int $id = null;
    private int $feed_id;
    private string $guid;
    private string $title;
    private string $link;
    private ?string $content = null;
    private ?string $summary = null;
    private ?string $author = null;
    private ?\DateTime $pub_date = null;
    private array $categories = [];
    private array $enclosures = [];
    private ?string $thumbnail_url = null;
    private string $status = 'unread';
    private ?int $rewritten_post_id = null;
    private ?string $error_message = null;
    private array $metadata = [];
    private ?\DateTime $fetched_at = null;
    private ?\DateTime $processed_at = null;

    /**
     * 상태 상수
     */
    public const STATUS_UNREAD = 'unread';
    public const STATUS_READ = 'read';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_FAILED = 'failed';

    /**
     * 생성자
     */
    public function __construct(array $data = []) {
        if (!empty($data)) {
            $this->hydrate($data);
        }
    }

    /**
     * 배열 데이터로 객체 초기화
     */
    public function hydrate(array $data): self {
        if (isset($data['id'])) {
            $this->id = (int) $data['id'];
        }
        if (isset($data['feed_id'])) {
            $this->feed_id = (int) $data['feed_id'];
        }
        if (isset($data['guid'])) {
            $this->guid = $data['guid'];
        }
        if (isset($data['title'])) {
            $this->title = $data['title'];
        }
        if (isset($data['link'])) {
            $this->link = $data['link'];
        }
        if (isset($data['content'])) {
            $this->content = $data['content'];
        }
        if (isset($data['summary'])) {
            $this->summary = $data['summary'];
        }
        if (isset($data['author'])) {
            $this->author = $data['author'];
        }
        if (isset($data['pub_date']) && $data['pub_date']) {
            $this->pub_date = new \DateTime($data['pub_date']);
        }
        if (isset($data['categories'])) {
            $this->categories = is_string($data['categories'])
                ? json_decode($data['categories'], true) ?? []
                : $data['categories'];
        }
        if (isset($data['enclosures'])) {
            $this->enclosures = is_string($data['enclosures'])
                ? json_decode($data['enclosures'], true) ?? []
                : $data['enclosures'];
        }
        if (isset($data['thumbnail_url'])) {
            $this->thumbnail_url = $data['thumbnail_url'];
        }
        if (isset($data['status'])) {
            $this->status = $data['status'];
        }
        if (isset($data['rewritten_post_id'])) {
            $this->rewritten_post_id = $data['rewritten_post_id'] ? (int) $data['rewritten_post_id'] : null;
        }
        if (isset($data['error_message'])) {
            $this->error_message = $data['error_message'];
        }
        if (isset($data['metadata'])) {
            $this->metadata = is_string($data['metadata'])
                ? json_decode($data['metadata'], true) ?? []
                : $data['metadata'];
        }
        if (isset($data['fetched_at']) && $data['fetched_at']) {
            $this->fetched_at = new \DateTime($data['fetched_at']);
        }
        if (isset($data['processed_at']) && $data['processed_at']) {
            $this->processed_at = new \DateTime($data['processed_at']);
        }

        return $this;
    }

    /**
     * 배열로 변환
     */
    public function to_array(): array {
        return [
            'id' => $this->id,
            'feed_id' => $this->feed_id,
            'guid' => $this->guid,
            'title' => $this->title,
            'link' => $this->link,
            'content' => $this->content,
            'summary' => $this->summary,
            'author' => $this->author,
            'pub_date' => $this->pub_date?->format('Y-m-d H:i:s'),
            'categories' => $this->categories,
            'enclosures' => $this->enclosures,
            'thumbnail_url' => $this->thumbnail_url,
            'status' => $this->status,
            'rewritten_post_id' => $this->rewritten_post_id,
            'error_message' => $this->error_message,
            'metadata' => $this->metadata,
            'fetched_at' => $this->fetched_at?->format('Y-m-d H:i:s'),
            'processed_at' => $this->processed_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * DB 저장용 배열
     */
    public function to_db_array(): array {
        return [
            'feed_id' => $this->feed_id,
            'guid' => $this->guid,
            'title' => $this->title,
            'link' => $this->link,
            'content' => $this->content,
            'summary' => $this->summary,
            'author' => $this->author,
            'pub_date' => $this->pub_date?->format('Y-m-d H:i:s'),
            'categories' => wp_json_encode($this->categories),
            'enclosures' => wp_json_encode($this->enclosures),
            'thumbnail_url' => $this->thumbnail_url,
            'status' => $this->status,
            'rewritten_post_id' => $this->rewritten_post_id,
            'error_message' => $this->error_message,
            'metadata' => wp_json_encode($this->metadata),
            'processed_at' => $this->processed_at?->format('Y-m-d H:i:s'),
        ];
    }

    // Getters
    public function get_id(): ?int {
        return $this->id;
    }

    public function get_feed_id(): int {
        return $this->feed_id;
    }

    public function get_guid(): string {
        return $this->guid;
    }

    public function get_title(): string {
        return $this->title;
    }

    public function get_link(): string {
        return $this->link;
    }

    public function get_content(): ?string {
        return $this->content;
    }

    public function get_summary(): ?string {
        return $this->summary;
    }

    public function get_author(): ?string {
        return $this->author;
    }

    public function get_pub_date(): ?\DateTime {
        return $this->pub_date;
    }

    public function get_categories(): array {
        return $this->categories;
    }

    public function get_enclosures(): array {
        return $this->enclosures;
    }

    public function get_thumbnail_url(): ?string {
        return $this->thumbnail_url;
    }

    public function get_status(): string {
        return $this->status;
    }

    public function get_rewritten_post_id(): ?int {
        return $this->rewritten_post_id;
    }

    public function get_error_message(): ?string {
        return $this->error_message;
    }

    public function get_metadata(): array {
        return $this->metadata;
    }

    public function get_fetched_at(): ?\DateTime {
        return $this->fetched_at;
    }

    public function get_processed_at(): ?\DateTime {
        return $this->processed_at;
    }

    // Setters
    public function set_id(int $id): self {
        $this->id = $id;
        return $this;
    }

    public function set_feed_id(int $feed_id): self {
        $this->feed_id = $feed_id;
        return $this;
    }

    public function set_guid(string $guid): self {
        $this->guid = $guid;
        return $this;
    }

    public function set_title(string $title): self {
        $this->title = $title;
        return $this;
    }

    public function set_link(string $link): self {
        $this->link = $link;
        return $this;
    }

    public function set_content(?string $content): self {
        $this->content = $content;
        return $this;
    }

    public function set_summary(?string $summary): self {
        $this->summary = $summary;
        return $this;
    }

    public function set_author(?string $author): self {
        $this->author = $author;
        return $this;
    }

    public function set_pub_date(?\DateTime $datetime): self {
        $this->pub_date = $datetime;
        return $this;
    }

    public function set_categories(array $categories): self {
        $this->categories = $categories;
        return $this;
    }

    public function set_enclosures(array $enclosures): self {
        $this->enclosures = $enclosures;
        return $this;
    }

    public function set_thumbnail_url(?string $url): self {
        $this->thumbnail_url = $url;
        return $this;
    }

    public function set_status(string $status): self {
        $this->status = $status;
        return $this;
    }

    public function set_rewritten_post_id(?int $post_id): self {
        $this->rewritten_post_id = $post_id;
        return $this;
    }

    public function set_error_message(?string $message): self {
        $this->error_message = $message;
        return $this;
    }

    public function set_metadata(array $metadata): self {
        $this->metadata = $metadata;
        return $this;
    }

    public function set_processed_at(?\DateTime $datetime): self {
        $this->processed_at = $datetime;
        return $this;
    }

    // 유틸리티 메서드
    public function is_unread(): bool {
        return $this->status === self::STATUS_UNREAD;
    }

    public function is_read(): bool {
        return $this->status === self::STATUS_READ;
    }

    public function is_queued(): bool {
        return $this->status === self::STATUS_QUEUED;
    }

    public function is_processing(): bool {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function is_completed(): bool {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function is_failed(): bool {
        return $this->status === self::STATUS_FAILED;
    }

    public function has_rewritten_post(): bool {
        return $this->rewritten_post_id !== null;
    }

    public function can_rewrite(): bool {
        return in_array($this->status, [
            self::STATUS_UNREAD,
            self::STATUS_READ,
            self::STATUS_FAILED,
        ], true);
    }

    public function get_status_label(): string {
        $labels = [
            self::STATUS_UNREAD => __('미읽음', 'ai-content-rewriter'),
            self::STATUS_READ => __('읽음', 'ai-content-rewriter'),
            self::STATUS_QUEUED => __('대기', 'ai-content-rewriter'),
            self::STATUS_PROCESSING => __('처리중', 'ai-content-rewriter'),
            self::STATUS_COMPLETED => __('완료', 'ai-content-rewriter'),
            self::STATUS_SKIPPED => __('건너뜀', 'ai-content-rewriter'),
            self::STATUS_FAILED => __('실패', 'ai-content-rewriter'),
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function get_pub_date_formatted(): string {
        if ($this->pub_date === null) {
            return '';
        }

        return human_time_diff($this->pub_date->getTimestamp(), time()) . ' ' . __('전', 'ai-content-rewriter');
    }

    public function get_excerpt(int $length = 200): string {
        $text = $this->summary ?: $this->content;
        if (!$text) {
            return '';
        }

        $text = wp_strip_all_tags($text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length) . '...';
    }

    /**
     * 콘텐츠에서 첫 번째 이미지 URL 추출
     */
    public function extract_first_image(): ?string {
        if ($this->thumbnail_url) {
            return $this->thumbnail_url;
        }

        if (!$this->content) {
            return null;
        }

        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $this->content, $matches)) {
            return $matches[1];
        }

        // enclosures에서 이미지 찾기
        foreach ($this->enclosures as $enclosure) {
            if (isset($enclosure['type']) && strpos($enclosure['type'], 'image/') === 0) {
                return $enclosure['url'] ?? null;
            }
        }

        return null;
    }
}
