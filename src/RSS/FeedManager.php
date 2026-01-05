<?php
/**
 * Feed Manager
 *
 * RSS 피드 관리 비즈니스 로직 클래스
 *
 * @package AIContentRewriter\RSS
 */

namespace AIContentRewriter\RSS;

use AIContentRewriter\Content\ContentRewriter;
use WP_Error;

/**
 * RSS 피드 관리자 클래스
 */
class FeedManager {
    /**
     * 피드 저장소
     */
    private FeedRepository $feed_repository;

    /**
     * 아이템 저장소
     */
    private FeedItemRepository $item_repository;

    /**
     * 피드 가져오기
     */
    private FeedFetcher $fetcher;

    /**
     * 콘텐츠 재작성기
     */
    private ?ContentRewriter $rewriter = null;

    /**
     * 생성자
     */
    public function __construct() {
        $this->feed_repository = new FeedRepository();
        $this->item_repository = new FeedItemRepository();
        $this->fetcher = new FeedFetcher();
    }

    /**
     * 새 피드 추가
     *
     * @param array $data 피드 데이터
     * @return int|WP_Error 피드 ID 또는 에러
     */
    public function add_feed(array $data): int|WP_Error {
        $user_id = get_current_user_id();

        // 필수 필드 검증
        if (empty($data['feed_url'])) {
            return new WP_Error('missing_url', __('피드 URL은 필수입니다.', 'ai-content-rewriter'));
        }

        // URL 중복 확인
        if ($this->feed_repository->exists_by_url($data['feed_url'], $user_id)) {
            return new WP_Error('duplicate_url', __('이미 등록된 피드 URL입니다.', 'ai-content-rewriter'));
        }

        // 피드 유효성 검증
        $validation = $this->fetcher->validate_feed($data['feed_url']);
        if (!$validation['valid']) {
            return new WP_Error('invalid_feed', $validation['error']);
        }

        // 피드 생성
        $feed = new Feed([
            'user_id' => $user_id,
            'name' => $data['name'] ?? $validation['title'] ?? '',
            'feed_url' => $data['feed_url'],
            'site_url' => $validation['site_url'] ?? '',
            'site_name' => $validation['title'] ?? '',
            'feed_type' => $validation['type'] ?? Feed::TYPE_RSS2,
            'status' => Feed::STATUS_ACTIVE,
            'fetch_interval' => $data['fetch_interval'] ?? (int) get_option('aicr_rss_default_interval', 86400),
            'auto_rewrite' => !empty($data['auto_rewrite']),
            'auto_publish' => !empty($data['auto_publish']),
            'default_category' => $data['default_category'] ?? null,
            'default_template_id' => $data['default_template_id'] ?? null,
            'default_language' => $data['default_language'] ?? 'ko',
        ]);

        $feed_id = $this->feed_repository->save($feed);

        // 초기 피드 가져오기
        $this->fetcher->fetch($feed);

        return $feed_id;
    }

    /**
     * 피드 업데이트
     *
     * @param int $feed_id 피드 ID
     * @param array $data 업데이트 데이터
     * @return bool|WP_Error 성공 여부 또는 에러
     */
    public function update_feed(int $feed_id, array $data): bool|WP_Error {
        $feed = $this->feed_repository->find($feed_id);

        if (!$feed) {
            return new WP_Error('not_found', __('피드를 찾을 수 없습니다.', 'ai-content-rewriter'));
        }

        // 권한 확인
        if ($feed->get_user_id() !== get_current_user_id()) {
            return new WP_Error('unauthorized', __('권한이 없습니다.', 'ai-content-rewriter'));
        }

        // URL 변경 시 중복 확인
        if (!empty($data['feed_url']) && $data['feed_url'] !== $feed->get_feed_url()) {
            if ($this->feed_repository->exists_by_url($data['feed_url'], $feed->get_user_id())) {
                return new WP_Error('duplicate_url', __('이미 등록된 피드 URL입니다.', 'ai-content-rewriter'));
            }

            // 새 URL 유효성 검증
            $validation = $this->fetcher->validate_feed($data['feed_url']);
            if (!$validation['valid']) {
                return new WP_Error('invalid_feed', $validation['error']);
            }

            $feed->set_feed_url($data['feed_url']);
        }

        // 필드 업데이트
        if (isset($data['name'])) {
            $feed->set_name($data['name']);
        }
        if (isset($data['fetch_interval'])) {
            $feed->set_fetch_interval((int) $data['fetch_interval']);
        }
        if (isset($data['auto_rewrite'])) {
            $feed->set_auto_rewrite((bool) $data['auto_rewrite']);
        }
        if (isset($data['auto_publish'])) {
            $feed->set_auto_publish((bool) $data['auto_publish']);
        }
        if (isset($data['default_category'])) {
            $feed->set_default_category((int) $data['default_category']);
        }
        if (isset($data['default_template_id'])) {
            $feed->set_default_template_id((int) $data['default_template_id']);
        }
        if (isset($data['default_language'])) {
            $feed->set_default_language($data['default_language']);
        }

        $this->feed_repository->save($feed);

        return true;
    }

    /**
     * 피드 삭제
     *
     * @param int $feed_id 피드 ID
     * @return bool|WP_Error 성공 여부 또는 에러
     */
    public function delete_feed(int $feed_id): bool|WP_Error {
        $feed = $this->feed_repository->find($feed_id);

        if (!$feed) {
            return new WP_Error('not_found', __('피드를 찾을 수 없습니다.', 'ai-content-rewriter'));
        }

        // 권한 확인
        if ($feed->get_user_id() !== get_current_user_id()) {
            return new WP_Error('unauthorized', __('권한이 없습니다.', 'ai-content-rewriter'));
        }

        return $this->feed_repository->delete($feed_id);
    }

    /**
     * 피드 상태 토글 (활성/일시정지)
     *
     * @param int $feed_id 피드 ID
     * @return bool|WP_Error 성공 여부 또는 에러
     */
    public function toggle_feed(int $feed_id): bool|WP_Error {
        $feed = $this->feed_repository->find($feed_id);

        if (!$feed) {
            return new WP_Error('not_found', __('피드를 찾을 수 없습니다.', 'ai-content-rewriter'));
        }

        // 권한 확인
        if ($feed->get_user_id() !== get_current_user_id()) {
            return new WP_Error('unauthorized', __('권한이 없습니다.', 'ai-content-rewriter'));
        }

        $new_status = $feed->is_active() ? Feed::STATUS_PAUSED : Feed::STATUS_ACTIVE;
        return $this->feed_repository->update_status($feed_id, $new_status);
    }

    /**
     * 피드 새로고침
     *
     * @param int $feed_id 피드 ID
     * @return array|WP_Error 결과 배열 또는 에러
     */
    public function refresh_feed(int $feed_id): array|WP_Error {
        $feed = $this->feed_repository->find($feed_id);

        if (!$feed) {
            return new WP_Error('not_found', __('피드를 찾을 수 없습니다.', 'ai-content-rewriter'));
        }

        // 권한 확인
        if ($feed->get_user_id() !== get_current_user_id()) {
            return new WP_Error('unauthorized', __('권한이 없습니다.', 'ai-content-rewriter'));
        }

        return $this->fetcher->fetch($feed);
    }

    /**
     * 아이템 상태 업데이트
     *
     * @param int $item_id 아이템 ID
     * @param string $status 새 상태
     * @return bool|WP_Error 성공 여부 또는 에러
     */
    public function update_item_status(int $item_id, string $status): bool|WP_Error {
        $item = $this->item_repository->find($item_id);

        if (!$item) {
            return new WP_Error('not_found', __('아이템을 찾을 수 없습니다.', 'ai-content-rewriter'));
        }

        // 유효한 상태인지 확인
        $valid_statuses = [
            FeedItem::STATUS_UNREAD,
            FeedItem::STATUS_READ,
            FeedItem::STATUS_QUEUED,
            FeedItem::STATUS_SKIPPED,
        ];

        if (!in_array($status, $valid_statuses, true)) {
            return new WP_Error('invalid_status', __('유효하지 않은 상태입니다.', 'ai-content-rewriter'));
        }

        $result = $this->item_repository->update_status($item_id, $status);

        // 피드의 미읽음 카운트 업데이트
        $this->feed_repository->update_unread_count($item->get_feed_id());

        return $result;
    }

    /**
     * 아이템 재작성
     *
     * @param int $item_id 아이템 ID
     * @param array $options 재작성 옵션
     * @return int|WP_Error 게시글 ID 또는 에러
     */
    public function rewrite_item(int $item_id, array $options = []): int|WP_Error {
        $item = $this->item_repository->find($item_id);

        if (!$item) {
            return new WP_Error('not_found', __('아이템을 찾을 수 없습니다.', 'ai-content-rewriter'));
        }

        // 이미 재작성되었는지 확인
        if ($item->get_rewritten_post_id()) {
            return new WP_Error('already_rewritten', __('이미 재작성된 아이템입니다.', 'ai-content-rewriter'));
        }

        // 상태 업데이트
        $this->item_repository->update_status($item_id, FeedItem::STATUS_PROCESSING);

        try {
            // 피드 정보 가져오기
            $feed = $this->feed_repository->find($item->get_feed_id());
            if (!$feed) {
                throw new \Exception(__('피드를 찾을 수 없습니다.', 'ai-content-rewriter'));
            }

            // 옵션 병합
            $rewrite_options = [
                'template_id' => $options['template_id'] ?? $feed->get_default_template_id(),
                'category' => $options['category'] ?? $feed->get_default_category(),
                'language' => $options['language'] ?? $feed->get_default_language(),
                'post_status' => $options['post_status'] ?? ($feed->is_auto_publish() ? 'publish' : 'draft'),
            ];

            // ContentRewriter 인스턴스 생성
            if (!$this->rewriter) {
                $this->rewriter = new ContentRewriter();
            }

            // 원본 URL에서 전체 콘텐츠 크롤링
            $content_to_rewrite = $item->get_content();
            $source_title = $item->get_title();

            if ($item->get_link()) {
                $extractor = new \AIContentRewriter\Content\ContentExtractor();
                $extracted = $extractor->extract_from_url($item->get_link());

                if ($extracted->is_success()) {
                    // 크롤링 성공: 전체 콘텐츠 사용
                    $content_to_rewrite = $extracted->get_content();

                    // 추출된 제목이 있으면 사용
                    if (!empty($extracted->get_title())) {
                        $source_title = $extracted->get_title();
                    }
                }
                // 크롤링 실패 시 RSS 콘텐츠를 폴백으로 사용
            }

            // 콘텐츠가 너무 짧으면 에러
            if (mb_strlen($content_to_rewrite) < 100) {
                throw new \Exception(__('원본 콘텐츠가 너무 짧습니다. 최소 100자 이상 필요합니다.', 'ai-content-rewriter'));
            }

            // 대상 언어 설정
            $this->rewriter->set_target_language($rewrite_options['language']);

            // 콘텐츠 재작성
            $rewritten = $this->rewriter->rewrite_content(
                $content_to_rewrite,
                [
                    'source_title' => $source_title,
                    'source_url' => $item->get_link(),
                    'template_id' => $rewrite_options['template_id'],
                    'template_type' => 'blog_post',  // 워드프레스 블로그 포스트용 템플릿
                ]
            );

            if (!$rewritten->is_success()) {
                throw new \Exception($rewritten->get_error_message());
            }

            // 게시글 데이터 생성 (RewriteResult의 to_post_data() 활용)
            $post_data = $rewritten->to_post_data([
                'post_status' => $rewrite_options['post_status'],
                'post_author' => get_current_user_id(),
            ]);

            // 제목이 비어있으면 원본 제목 사용
            if (empty($post_data['post_title'])) {
                $post_data['post_title'] = $item->get_title();
            }

            if (!empty($rewrite_options['category'])) {
                $post_data['post_category'] = [$rewrite_options['category']];
            }

            $post_id = wp_insert_post($post_data, true);

            if (is_wp_error($post_id)) {
                throw new \Exception($post_id->get_error_message());
            }

            // 메타데이터 저장
            update_post_meta($post_id, '_aicr_source_url', $item->get_link());
            update_post_meta($post_id, '_aicr_source_feed_id', $item->get_feed_id());
            update_post_meta($post_id, '_aicr_source_item_id', $item->get_id());
            update_post_meta($post_id, '_aicr_rewritten_at', current_time('mysql'));

            // 썸네일 설정
            if (!empty($item->get_thumbnail_url())) {
                $this->set_featured_image($post_id, $item->get_thumbnail_url());
            }

            // 아이템 상태 업데이트
            $this->item_repository->set_rewritten_post($item_id, $post_id);

            // 피드 미읽음 카운트 업데이트
            $this->feed_repository->update_unread_count($item->get_feed_id());

            return $post_id;

        } catch (\Exception $e) {
            $this->item_repository->update_status($item_id, FeedItem::STATUS_FAILED, $e->getMessage());
            return new WP_Error('rewrite_failed', $e->getMessage());
        }
    }

    /**
     * 여러 아이템 일괄 재작성
     *
     * @param array $item_ids 아이템 ID 배열
     * @param array $options 재작성 옵션
     * @return array 결과 배열
     */
    public function rewrite_items(array $item_ids, array $options = []): array {
        $results = [
            'success' => [],
            'failed' => [],
        ];

        foreach ($item_ids as $item_id) {
            $result = $this->rewrite_item((int) $item_id, $options);

            if (is_wp_error($result)) {
                $results['failed'][$item_id] = $result->get_error_message();
            } else {
                $results['success'][$item_id] = $result;
            }
        }

        return $results;
    }

    /**
     * 피드 아이템 목록 조회
     *
     * @param int $user_id 사용자 ID
     * @param array $filters 필터
     * @return array 아이템 배열
     */
    public function get_items(int $user_id, array $filters = []): array {
        // 사용자의 피드 ID 목록 가져오기
        $feeds = $this->feed_repository->find_by_user($user_id);

        if (empty($feeds)) {
            return [];
        }

        $feed_ids = array_map(fn($feed) => $feed->get_id(), $feeds);

        // 특정 피드 필터링
        if (!empty($filters['feed_id'])) {
            $feed_ids = array_filter($feed_ids, fn($id) => $id === (int) $filters['feed_id']);
        }

        return $this->item_repository->find_by_feeds($feed_ids, $filters);
    }

    /**
     * 아이템 수 조회
     *
     * @param int $user_id 사용자 ID
     * @param array $filters 필터
     * @return int
     */
    public function count_items(int $user_id, array $filters = []): int {
        $feeds = $this->feed_repository->find_by_user($user_id);

        if (empty($feeds)) {
            return 0;
        }

        $feed_ids = array_map(fn($feed) => $feed->get_id(), $feeds);

        if (!empty($filters['feed_id'])) {
            $feed_ids = array_filter($feed_ids, fn($id) => $id === (int) $filters['feed_id']);
        }

        return $this->item_repository->count_by_feeds($feed_ids, $filters['status'] ?? null);
    }

    /**
     * 특성 이미지 설정
     */
    private function set_featured_image(int $post_id, string $image_url): void {
        if (!function_exists('media_sideload_image')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $attachment_id = media_sideload_image($image_url, $post_id, '', 'id');

        if (!is_wp_error($attachment_id)) {
            set_post_thumbnail($post_id, $attachment_id);
        }
    }

    /**
     * 오래된 아이템 정리
     *
     * @return int 삭제된 아이템 수
     */
    public function cleanup_old_items(): int {
        $retention_days = (int) get_option('aicr_rss_item_retention_days', 30);
        return $this->item_repository->delete_old_items($retention_days);
    }

    /**
     * 피드 URL 자동 탐색
     *
     * @param string $site_url 사이트 URL
     * @return array 발견된 피드 URL 배열
     */
    public function discover_feeds(string $site_url): array {
        return $this->fetcher->discover_feeds($site_url);
    }
}
