<?php
/**
 * RSS AJAX Handler
 *
 * RSS 피드 관련 AJAX 요청 처리
 *
 * @package AIContentRewriter\RSS
 */

namespace AIContentRewriter\RSS;

use AIContentRewriter\Content\SharedRewriteProcessor;

/**
 * RSS AJAX 핸들러 클래스
 */
class AjaxHandler {
    /**
     * 피드 관리자
     */
    private FeedManager $manager;

    /**
     * 피드 저장소
     */
    private FeedRepository $feed_repository;

    /**
     * 아이템 저장소
     */
    private FeedItemRepository $item_repository;

    /**
     * 생성자
     */
    public function __construct() {
        $this->manager = new FeedManager();
        $this->feed_repository = new FeedRepository();
        $this->item_repository = new FeedItemRepository();
    }

    /**
     * 공통 재작성 프로세서
     */
    private ?SharedRewriteProcessor $shared_processor = null;

    /**
     * 초기화
     */
    public function init(): void {
        // 피드 관련 AJAX
        add_action('wp_ajax_aicr_validate_feed', [$this, 'validate_feed']);
        add_action('wp_ajax_aicr_add_feed', [$this, 'add_feed']);
        add_action('wp_ajax_aicr_update_feed', [$this, 'update_feed']);
        add_action('wp_ajax_aicr_delete_feed', [$this, 'delete_feed']);
        add_action('wp_ajax_aicr_toggle_feed', [$this, 'toggle_feed']);
        add_action('wp_ajax_aicr_refresh_feed', [$this, 'refresh_feed']);
        add_action('wp_ajax_aicr_get_feed', [$this, 'get_feed']);
        add_action('wp_ajax_aicr_discover_feeds', [$this, 'discover_feeds']);

        // 피드 아이템 관련 AJAX
        add_action('wp_ajax_aicr_get_feed_items', [$this, 'get_feed_items']);
        add_action('wp_ajax_aicr_get_feed_item', [$this, 'get_feed_item']);
        add_action('wp_ajax_aicr_update_item_status', [$this, 'update_item_status']);
        add_action('wp_ajax_aicr_rewrite_item', [$this, 'rewrite_item']);
        add_action('wp_ajax_aicr_rewrite_items', [$this, 'rewrite_items']);
        add_action('wp_ajax_aicr_mark_all_read', [$this, 'mark_all_read']);

        // 비동기 재작성 관련 AJAX (공통 모듈 사용)
        add_action('wp_ajax_aicr_start_rewrite_task', [$this, 'start_rewrite_task']);
        add_action('wp_ajax_aicr_check_rewrite_status', [$this, 'check_rewrite_status']);
        // 백그라운드 처리는 SharedRewriteProcessor의 aicr_process_shared_rewrite 사용
    }

    /**
     * 공통 재작성 프로세서 인스턴스 반환
     */
    private function get_shared_processor(): SharedRewriteProcessor {
        if ($this->shared_processor === null) {
            $this->shared_processor = new SharedRewriteProcessor();
        }
        return $this->shared_processor;
    }

    /**
     * 피드 URL 검증
     */
    public function validate_feed(): void {
        $this->verify_nonce();

        $url = sanitize_url($_POST['url'] ?? '');

        if (empty($url)) {
            wp_send_json_error(['message' => __('URL을 입력해주세요.', 'ai-content-rewriter')]);
        }

        $fetcher = new FeedFetcher();
        $result = $fetcher->validate_feed($url);

        if ($result['valid']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(['message' => $result['error']]);
        }
    }

    /**
     * 피드 추가
     */
    public function add_feed(): void {
        $this->verify_nonce();

        $data = [
            'feed_url' => sanitize_url($_POST['feed_url'] ?? ''),
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'fetch_interval' => absint($_POST['fetch_interval'] ?? 86400),
            'auto_rewrite' => !empty($_POST['auto_rewrite']),
            'auto_publish' => !empty($_POST['auto_publish']),
            'default_category' => absint($_POST['default_category'] ?? 0),
            'default_template_id' => absint($_POST['default_template_id'] ?? 0),
            'default_language' => sanitize_text_field($_POST['default_language'] ?? 'ko'),
        ];

        $result = $this->manager->add_feed($data);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('피드가 추가되었습니다.', 'ai-content-rewriter'),
            'feed_id' => $result,
        ]);
    }

    /**
     * 피드 업데이트
     */
    public function update_feed(): void {
        $this->verify_nonce();

        $feed_id = absint($_POST['feed_id'] ?? 0);

        if (!$feed_id) {
            wp_send_json_error(['message' => __('유효하지 않은 피드 ID입니다.', 'ai-content-rewriter')]);
        }

        $data = [];

        if (isset($_POST['feed_url'])) {
            $data['feed_url'] = sanitize_url($_POST['feed_url']);
        }
        if (isset($_POST['name'])) {
            $data['name'] = sanitize_text_field($_POST['name']);
        }
        if (isset($_POST['fetch_interval'])) {
            $data['fetch_interval'] = absint($_POST['fetch_interval']);
        }
        if (isset($_POST['auto_rewrite'])) {
            $data['auto_rewrite'] = !empty($_POST['auto_rewrite']);
        }
        if (isset($_POST['auto_publish'])) {
            $data['auto_publish'] = !empty($_POST['auto_publish']);
        }
        if (isset($_POST['default_category'])) {
            $data['default_category'] = absint($_POST['default_category']);
        }
        if (isset($_POST['default_template_id'])) {
            $data['default_template_id'] = absint($_POST['default_template_id']);
        }
        if (isset($_POST['default_language'])) {
            $data['default_language'] = sanitize_text_field($_POST['default_language']);
        }

        $result = $this->manager->update_feed($feed_id, $data);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('피드가 업데이트되었습니다.', 'ai-content-rewriter')]);
    }

    /**
     * 피드 삭제
     */
    public function delete_feed(): void {
        $this->verify_nonce();

        $feed_id = absint($_POST['feed_id'] ?? 0);

        if (!$feed_id) {
            wp_send_json_error(['message' => __('유효하지 않은 피드 ID입니다.', 'ai-content-rewriter')]);
        }

        $result = $this->manager->delete_feed($feed_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('피드가 삭제되었습니다.', 'ai-content-rewriter')]);
    }

    /**
     * 피드 상태 토글
     */
    public function toggle_feed(): void {
        $this->verify_nonce();

        $feed_id = absint($_POST['feed_id'] ?? 0);

        if (!$feed_id) {
            wp_send_json_error(['message' => __('유효하지 않은 피드 ID입니다.', 'ai-content-rewriter')]);
        }

        $result = $this->manager->toggle_feed($feed_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('피드 상태가 변경되었습니다.', 'ai-content-rewriter')]);
    }

    /**
     * 피드 새로고침
     */
    public function refresh_feed(): void {
        $this->verify_nonce();

        $feed_id = absint($_POST['feed_id'] ?? 0);

        if (!$feed_id) {
            wp_send_json_error(['message' => __('유효하지 않은 피드 ID입니다.', 'ai-content-rewriter')]);
        }

        $result = $this->manager->refresh_feed($feed_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => sprintf(
                __('피드가 새로고침되었습니다. (새 아이템: %d개)', 'ai-content-rewriter'),
                $result['new_items']
            ),
            'new_items' => $result['new_items'],
            'updated_items' => $result['updated_items'],
        ]);
    }

    /**
     * 피드 정보 조회
     */
    public function get_feed(): void {
        $this->verify_nonce();

        $feed_id = absint($_POST['feed_id'] ?? 0);

        if (!$feed_id) {
            wp_send_json_error(['message' => __('유효하지 않은 피드 ID입니다.', 'ai-content-rewriter')]);
        }

        $feed = $this->feed_repository->find($feed_id);

        if (!$feed) {
            wp_send_json_error(['message' => __('피드를 찾을 수 없습니다.', 'ai-content-rewriter')]);
        }

        // 권한 확인
        if ($feed->get_user_id() !== get_current_user_id()) {
            wp_send_json_error(['message' => __('권한이 없습니다.', 'ai-content-rewriter')]);
        }

        wp_send_json_success(['feed' => $feed->to_array()]);
    }

    /**
     * 피드 URL 자동 탐색
     */
    public function discover_feeds(): void {
        $this->verify_nonce();

        $url = sanitize_url($_POST['url'] ?? '');

        if (empty($url)) {
            wp_send_json_error(['message' => __('URL을 입력해주세요.', 'ai-content-rewriter')]);
        }

        $feeds = $this->manager->discover_feeds($url);

        wp_send_json_success(['feeds' => $feeds]);
    }

    /**
     * 피드 아이템 목록 조회
     */
    public function get_feed_items(): void {
        $this->verify_nonce();

        $user_id = get_current_user_id();

        $filters = [
            'feed_id' => absint($_POST['feed_id'] ?? 0) ?: null,
            'status' => isset($_POST['status']) ? $this->sanitize_status_filter($_POST['status']) : null,
            'search' => sanitize_text_field($_POST['search'] ?? ''),
            'limit' => min(absint($_POST['limit'] ?? 20), 100),
            'offset' => absint($_POST['offset'] ?? 0),
        ];

        // null 값 제거
        $filters = array_filter($filters, fn($v) => $v !== null && $v !== '');

        $items = $this->manager->get_items($user_id, $filters);
        $total = $this->manager->count_items($user_id, $filters);

        // 아이템을 배열로 변환
        $items_data = array_map(fn($item) => $item->to_array(), $items);

        wp_send_json_success([
            'items' => $items_data,
            'total' => $total,
            'has_more' => ($filters['offset'] ?? 0) + count($items) < $total,
        ]);
    }

    /**
     * 단일 아이템 조회
     */
    public function get_feed_item(): void {
        $this->verify_nonce();

        $item_id = absint($_POST['item_id'] ?? 0);

        if (!$item_id) {
            wp_send_json_error(['message' => __('유효하지 않은 아이템 ID입니다.', 'ai-content-rewriter')]);
        }

        $item = $this->item_repository->find($item_id);

        if (!$item) {
            wp_send_json_error(['message' => __('아이템을 찾을 수 없습니다.', 'ai-content-rewriter')]);
        }

        // 읽음 상태로 변경
        if ($item->get_status() === FeedItem::STATUS_UNREAD) {
            $this->manager->update_item_status($item_id, FeedItem::STATUS_READ);
            $item->set_status(FeedItem::STATUS_READ);
        }

        wp_send_json_success(['item' => $item->to_array()]);
    }

    /**
     * 아이템 상태 업데이트
     */
    public function update_item_status(): void {
        $this->verify_nonce();

        $item_id = absint($_POST['item_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');

        if (!$item_id) {
            wp_send_json_error(['message' => __('유효하지 않은 아이템 ID입니다.', 'ai-content-rewriter')]);
        }

        $result = $this->manager->update_item_status($item_id, $status);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('상태가 업데이트되었습니다.', 'ai-content-rewriter')]);
    }

    /**
     * 아이템 재작성 (레거시 - 동기 방식)
     * @deprecated 비동기 방식 사용 권장 (start_rewrite_task)
     */
    public function rewrite_item(): void {
        // URL 크롤링 + AI API 호출에 충분한 시간 확보 (3분 - 긴 콘텐츠 생성용)
        set_time_limit(180);

        $this->verify_nonce();

        $item_id = absint($_POST['item_id'] ?? 0);

        if (!$item_id) {
            wp_send_json_error(['message' => __('유효하지 않은 아이템 ID입니다.', 'ai-content-rewriter')]);
        }

        $options = [
            'template_id' => absint($_POST['template_id'] ?? 0) ?: null,
            'category' => absint($_POST['category'] ?? 0) ?: null,
            'language' => sanitize_text_field($_POST['language'] ?? '') ?: null,
            'post_status' => sanitize_text_field($_POST['post_status'] ?? 'draft'),
        ];

        $result = $this->manager->rewrite_item($item_id, $options);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('재작성이 완료되었습니다.', 'ai-content-rewriter'),
            'post_id' => $result,
            'edit_url' => get_edit_post_link($result, 'raw'),
            'view_url' => get_permalink($result),
        ]);
    }

    /**
     * 비동기 재작성 작업 시작 (공통 모듈 사용)
     */
    public function start_rewrite_task(): void {
        $this->verify_nonce();

        $item_id = absint($_POST['item_id'] ?? 0);

        if (!$item_id) {
            wp_send_json_error(['message' => __('유효하지 않은 아이템 ID입니다.', 'ai-content-rewriter')]);
        }

        $options = [
            'template_id' => absint($_POST['template_id'] ?? 0) ?: null,
            'category' => absint($_POST['category'] ?? 0) ?: null,
            'language' => sanitize_text_field($_POST['language'] ?? 'ko'),
            'post_status' => sanitize_text_field($_POST['post_status'] ?? 'draft'),
            'user_id' => get_current_user_id(),
        ];

        // SharedRewriteProcessor 사용 (공통 모듈)
        $processor = $this->get_shared_processor();
        $task_id = $processor->start_task([
            'source_type' => SharedRewriteProcessor::SOURCE_RSS_ITEM,
            'item_id' => $item_id,
            'options' => $options,
        ]);

        wp_send_json_success([
            'message' => __('재작성 작업이 시작되었습니다.', 'ai-content-rewriter'),
            'task_id' => $task_id,
        ]);
    }

    /**
     * 재작성 작업 상태 확인 (공통 모듈 사용)
     */
    public function check_rewrite_status(): void {
        $this->verify_nonce();

        $task_id = sanitize_text_field($_POST['task_id'] ?? '');

        if (empty($task_id)) {
            wp_send_json_error(['message' => __('유효하지 않은 작업 ID입니다.', 'ai-content-rewriter')]);
        }

        // SharedRewriteProcessor 사용 (공통 모듈)
        $processor = $this->get_shared_processor();
        $task = $processor->get_task_status($task_id);

        if (!$task) {
            wp_send_json_error(['message' => __('작업을 찾을 수 없습니다.', 'ai-content-rewriter')]);
        }

        wp_send_json_success([
            'task_id' => $task['task_id'],
            'status' => $task['status'],
            'step' => $task['step'],
            'progress' => $task['progress'],
            'message' => $task['message'],
            'result' => $task['result'],
            'error' => $task['error'],
        ]);
    }

    /**
     * 여러 아이템 일괄 재작성
     */
    public function rewrite_items(): void {
        // URL 크롤링 + AI API 호출에 충분한 시간 확보 (5분)
        set_time_limit(300);

        $this->verify_nonce();

        $item_ids = isset($_POST['item_ids']) ? array_map('absint', (array) $_POST['item_ids']) : [];

        if (empty($item_ids)) {
            wp_send_json_error(['message' => __('선택된 아이템이 없습니다.', 'ai-content-rewriter')]);
        }

        $options = [
            'template_id' => absint($_POST['template_id'] ?? 0) ?: null,
            'category' => absint($_POST['category'] ?? 0) ?: null,
            'language' => sanitize_text_field($_POST['language'] ?? '') ?: null,
            'post_status' => sanitize_text_field($_POST['post_status'] ?? 'draft'),
        ];

        $results = $this->manager->rewrite_items($item_ids, $options);

        $success_count = count($results['success']);
        $failed_count = count($results['failed']);

        wp_send_json_success([
            'message' => sprintf(
                __('재작성 완료: 성공 %d개, 실패 %d개', 'ai-content-rewriter'),
                $success_count,
                $failed_count
            ),
            'results' => $results,
        ]);
    }

    /**
     * 모든 아이템 읽음 처리
     */
    public function mark_all_read(): void {
        $this->verify_nonce();

        $feed_id = absint($_POST['feed_id'] ?? 0);

        if (!$feed_id) {
            wp_send_json_error(['message' => __('유효하지 않은 피드 ID입니다.', 'ai-content-rewriter')]);
        }

        $feed = $this->feed_repository->find($feed_id);

        if (!$feed || $feed->get_user_id() !== get_current_user_id()) {
            wp_send_json_error(['message' => __('권한이 없습니다.', 'ai-content-rewriter')]);
        }

        $count = $this->item_repository->mark_all_as_read($feed_id);
        $this->feed_repository->update_unread_count($feed_id);

        wp_send_json_success([
            'message' => sprintf(
                __('%d개 아이템을 읽음으로 표시했습니다.', 'ai-content-rewriter'),
                $count
            ),
        ]);
    }

    /**
     * Nonce 검증
     */
    private function verify_nonce(): void {
        if (!check_ajax_referer('aicr_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('보안 검증에 실패했습니다.', 'ai-content-rewriter')]);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('권한이 없습니다.', 'ai-content-rewriter')]);
        }
    }

    /**
     * 상태 필터 정제
     */
    private function sanitize_status_filter($status): ?array {
        if (empty($status)) {
            return null;
        }

        if (is_array($status)) {
            return array_map('sanitize_text_field', $status);
        }

        if ($status === 'all') {
            return null;
        }

        return [sanitize_text_field($status)];
    }
}
