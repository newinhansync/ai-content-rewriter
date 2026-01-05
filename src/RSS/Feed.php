<?php
/**
 * Feed Entity
 *
 * @package AIContentRewriter\RSS
 */

namespace AIContentRewriter\RSS;

/**
 * RSS 피드 엔티티 클래스
 */
class Feed {
    private ?int $id = null;
    private int $user_id;
    private string $name;
    private string $feed_url;
    private ?string $site_url = null;
    private ?string $site_name = null;
    private string $feed_type = 'rss2';
    private string $status = 'active';
    private ?\DateTime $last_fetched_at = null;
    private ?string $last_error = null;
    private int $fetch_interval = 3600;
    private bool $auto_rewrite = false;
    private bool $auto_publish = false;
    private ?int $default_category = null;
    private ?int $default_template_id = null;
    private string $default_language = 'ko';
    private int $item_count = 0;
    private int $unread_count = 0;
    private array $metadata = [];
    private ?\DateTime $created_at = null;
    private ?\DateTime $updated_at = null;

    /**
     * 상태 상수
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_ERROR = 'error';

    /**
     * 피드 타입 상수
     */
    public const TYPE_RSS2 = 'rss2';
    public const TYPE_RSS1 = 'rss1';
    public const TYPE_ATOM = 'atom';

    /**
     * 갱신 주기 옵션 (초 단위)
     */
    public const INTERVALS = [
        'hourly' => 3600,
        'twice_daily' => 43200,
        'daily' => 86400,
        'weekly' => 604800,
    ];

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
        if (isset($data['user_id'])) {
            $this->user_id = (int) $data['user_id'];
        }
        if (isset($data['name'])) {
            $this->name = $data['name'];
        }
        if (isset($data['feed_url'])) {
            $this->feed_url = $data['feed_url'];
        }
        if (isset($data['site_url'])) {
            $this->site_url = $data['site_url'];
        }
        if (isset($data['site_name'])) {
            $this->site_name = $data['site_name'];
        }
        if (isset($data['feed_type'])) {
            $this->feed_type = $data['feed_type'];
        }
        if (isset($data['status'])) {
            $this->status = $data['status'];
        }
        if (isset($data['last_fetched_at']) && $data['last_fetched_at']) {
            $this->last_fetched_at = new \DateTime($data['last_fetched_at']);
        }
        if (isset($data['last_error'])) {
            $this->last_error = $data['last_error'];
        }
        if (isset($data['fetch_interval'])) {
            $this->fetch_interval = (int) $data['fetch_interval'];
        }
        if (isset($data['auto_rewrite'])) {
            $this->auto_rewrite = (bool) $data['auto_rewrite'];
        }
        if (isset($data['auto_publish'])) {
            $this->auto_publish = (bool) $data['auto_publish'];
        }
        if (isset($data['default_category'])) {
            $this->default_category = $data['default_category'] ? (int) $data['default_category'] : null;
        }
        if (isset($data['default_template_id'])) {
            $this->default_template_id = $data['default_template_id'] ? (int) $data['default_template_id'] : null;
        }
        if (isset($data['default_language'])) {
            $this->default_language = $data['default_language'];
        }
        if (isset($data['item_count'])) {
            $this->item_count = (int) $data['item_count'];
        }
        if (isset($data['unread_count'])) {
            $this->unread_count = (int) $data['unread_count'];
        }
        if (isset($data['metadata'])) {
            $this->metadata = is_string($data['metadata'])
                ? json_decode($data['metadata'], true) ?? []
                : $data['metadata'];
        }
        if (isset($data['created_at']) && $data['created_at']) {
            $this->created_at = new \DateTime($data['created_at']);
        }
        if (isset($data['updated_at']) && $data['updated_at']) {
            $this->updated_at = new \DateTime($data['updated_at']);
        }

        return $this;
    }

    /**
     * 배열로 변환
     */
    public function to_array(): array {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'feed_url' => $this->feed_url,
            'site_url' => $this->site_url,
            'site_name' => $this->site_name,
            'feed_type' => $this->feed_type,
            'status' => $this->status,
            'last_fetched_at' => $this->last_fetched_at?->format('Y-m-d H:i:s'),
            'last_error' => $this->last_error,
            'fetch_interval' => $this->fetch_interval,
            'auto_rewrite' => $this->auto_rewrite,
            'auto_publish' => $this->auto_publish,
            'default_category' => $this->default_category,
            'default_template_id' => $this->default_template_id,
            'default_language' => $this->default_language,
            'item_count' => $this->item_count,
            'unread_count' => $this->unread_count,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * DB 저장용 배열
     */
    public function to_db_array(): array {
        $data = [
            'user_id' => $this->user_id,
            'name' => $this->name,
            'feed_url' => $this->feed_url,
            'site_url' => $this->site_url,
            'site_name' => $this->site_name,
            'feed_type' => $this->feed_type,
            'status' => $this->status,
            'last_fetched_at' => $this->last_fetched_at?->format('Y-m-d H:i:s'),
            'last_error' => $this->last_error,
            'fetch_interval' => $this->fetch_interval,
            'auto_rewrite' => $this->auto_rewrite ? 1 : 0,
            'auto_publish' => $this->auto_publish ? 1 : 0,
            'default_category' => $this->default_category,
            'default_template_id' => $this->default_template_id,
            'default_language' => $this->default_language,
            'item_count' => $this->item_count,
            'unread_count' => $this->unread_count,
            'metadata' => wp_json_encode($this->metadata),
        ];

        return $data;
    }

    // Getters
    public function get_id(): ?int {
        return $this->id;
    }

    public function get_user_id(): int {
        return $this->user_id;
    }

    public function get_name(): string {
        return $this->name;
    }

    public function get_feed_url(): string {
        return $this->feed_url;
    }

    public function get_site_url(): ?string {
        return $this->site_url;
    }

    public function get_site_name(): ?string {
        return $this->site_name;
    }

    public function get_feed_type(): string {
        return $this->feed_type;
    }

    public function get_status(): string {
        return $this->status;
    }

    public function get_last_fetched_at(): ?\DateTime {
        return $this->last_fetched_at;
    }

    public function get_last_error(): ?string {
        return $this->last_error;
    }

    public function get_fetch_interval(): int {
        return $this->fetch_interval;
    }

    public function is_auto_rewrite(): bool {
        return $this->auto_rewrite;
    }

    public function is_auto_publish(): bool {
        return $this->auto_publish;
    }

    public function get_default_category(): ?int {
        return $this->default_category;
    }

    public function get_default_template_id(): ?int {
        return $this->default_template_id;
    }

    public function get_default_language(): string {
        return $this->default_language;
    }

    public function get_item_count(): int {
        return $this->item_count;
    }

    public function get_unread_count(): int {
        return $this->unread_count;
    }

    public function get_metadata(): array {
        return $this->metadata;
    }

    public function get_created_at(): ?\DateTime {
        return $this->created_at;
    }

    public function get_updated_at(): ?\DateTime {
        return $this->updated_at;
    }

    // Setters
    public function set_id(int $id): self {
        $this->id = $id;
        return $this;
    }

    public function set_user_id(int $user_id): self {
        $this->user_id = $user_id;
        return $this;
    }

    public function set_name(string $name): self {
        $this->name = $name;
        return $this;
    }

    public function set_feed_url(string $feed_url): self {
        $this->feed_url = $feed_url;
        return $this;
    }

    public function set_site_url(?string $site_url): self {
        $this->site_url = $site_url;
        return $this;
    }

    public function set_site_name(?string $site_name): self {
        $this->site_name = $site_name;
        return $this;
    }

    public function set_feed_type(string $feed_type): self {
        $this->feed_type = $feed_type;
        return $this;
    }

    public function set_status(string $status): self {
        $this->status = $status;
        return $this;
    }

    public function set_last_fetched_at(?\DateTime $datetime): self {
        $this->last_fetched_at = $datetime;
        return $this;
    }

    public function set_last_error(?string $error): self {
        $this->last_error = $error;
        return $this;
    }

    public function set_fetch_interval(int $interval): self {
        $this->fetch_interval = $interval;
        return $this;
    }

    public function set_auto_rewrite(bool $auto): self {
        $this->auto_rewrite = $auto;
        return $this;
    }

    public function set_auto_publish(bool $auto): self {
        $this->auto_publish = $auto;
        return $this;
    }

    public function set_default_category(?int $category): self {
        $this->default_category = $category;
        return $this;
    }

    public function set_default_template_id(?int $template_id): self {
        $this->default_template_id = $template_id;
        return $this;
    }

    public function set_default_language(string $language): self {
        $this->default_language = $language;
        return $this;
    }

    public function set_item_count(int $count): self {
        $this->item_count = $count;
        return $this;
    }

    public function set_unread_count(int $count): self {
        $this->unread_count = $count;
        return $this;
    }

    public function set_metadata(array $metadata): self {
        $this->metadata = $metadata;
        return $this;
    }

    // 유틸리티 메서드
    public function is_active(): bool {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function is_paused(): bool {
        return $this->status === self::STATUS_PAUSED;
    }

    public function has_error(): bool {
        return $this->status === self::STATUS_ERROR || !empty($this->last_error);
    }

    public function needs_fetch(): bool {
        if (!$this->is_active()) {
            return false;
        }

        if ($this->last_fetched_at === null) {
            return true;
        }

        $next_fetch = clone $this->last_fetched_at;
        $next_fetch->modify("+{$this->fetch_interval} seconds");

        return new \DateTime() >= $next_fetch;
    }

    public function get_time_since_last_fetch(): string {
        if ($this->last_fetched_at === null) {
            return __('갱신 없음', 'ai-content-rewriter');
        }

        return human_time_diff($this->last_fetched_at->getTimestamp(), time()) . ' ' . __('전', 'ai-content-rewriter');
    }

    public function get_interval_label(): string {
        $labels = [
            3600 => __('1시간마다', 'ai-content-rewriter'),
            43200 => __('12시간마다', 'ai-content-rewriter'),
            86400 => __('매일', 'ai-content-rewriter'),
            604800 => __('매주', 'ai-content-rewriter'),
        ];

        return $labels[$this->fetch_interval] ?? sprintf(__('%d초마다', 'ai-content-rewriter'), $this->fetch_interval);
    }

    public function get_status_label(): string {
        $labels = [
            self::STATUS_ACTIVE => __('활성', 'ai-content-rewriter'),
            self::STATUS_PAUSED => __('일시정지', 'ai-content-rewriter'),
            self::STATUS_ERROR => __('오류', 'ai-content-rewriter'),
        ];

        return $labels[$this->status] ?? $this->status;
    }
}
