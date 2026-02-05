<?php
/**
 * Webhook Receiver for Cloudflare Worker Results
 *
 * @package AIContentRewriter\API
 * @since 2.0.0
 */

namespace AIContentRewriter\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Webhook Receiver Class
 */
class WebhookReceiver {

    /**
     * Process webhook payload from Worker
     */
    public function process(WP_REST_Request $request): WP_REST_Response {
        $task_id = $request->get_param('task_id');
        $item_id = $request->get_param('item_id');
        $status = $request->get_param('status');
        $quality_score = $request->get_param('quality_score');
        $result = $request->get_param('result');
        $error = $request->get_param('error');
        $metrics = $request->get_param('metrics');

        // 필수 필드 검증
        if (empty($task_id) || empty($status)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => [
                    'code'    => 'AICR_001',
                    'message' => 'Missing required fields: task_id, status',
                ],
            ], 400);
        }

        // 실패 상태 처리
        if ($status === 'failed') {
            return $this->handle_failure($task_id, $item_id, $error, $metrics);
        }

        // 성공 상태 처리
        if ($status === 'completed' && $result) {
            return $this->handle_success($task_id, $item_id, $quality_score, $result, $metrics);
        }

        return new WP_REST_Response([
            'success' => false,
            'error'   => [
                'code'    => 'AICR_001',
                'message' => 'Invalid status or missing result',
            ],
        ], 400);
    }

    /**
     * Handle successful processing
     */
    private function handle_success(
        string $task_id,
        ?int $item_id,
        float $quality_score,
        array $result,
        ?array $metrics
    ): WP_REST_Response {
        // 필수 결과 필드 검증
        if (empty($result['title']) || empty($result['content'])) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => [
                    'code'    => 'AICR_001',
                    'message' => 'Missing required result fields: title, content',
                ],
            ], 400);
        }

        // 게시 상태 결정 (품질 점수 기반)
        $publish_threshold = (int)get_option('aicr_publish_threshold', 8);
        $auto_publish = (bool)get_option('aicr_auto_publish', true);

        $post_status = 'draft';
        if ($auto_publish && $quality_score >= $publish_threshold) {
            $post_status = 'publish';
        }

        // 대표 이미지 처리
        $featured_image_id = null;
        if (!empty($result['featured_image_url'])) {
            $featured_image_id = $this->download_and_attach_image(
                $result['featured_image_url'],
                $result['title']
            );
        }

        // 카테고리 처리
        $category_id = $this->resolve_category($result['category_suggestion'] ?? null);

        // 태그 처리
        $tags = $result['tags'] ?? [];

        // 게시글 생성
        $post_data = [
            'post_title'   => sanitize_text_field($result['title']),
            'post_content' => wp_kses_post($result['content']),
            'post_excerpt' => sanitize_text_field($result['excerpt'] ?? ''),
            'post_status'  => $post_status,
            'post_type'    => 'post',
            'post_author'  => $this->get_default_author(),
        ];

        if ($category_id) {
            $post_data['post_category'] = [$category_id];
        }

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => [
                    'code'    => 'AICR_010',
                    'message' => 'Failed to create post: ' . $post_id->get_error_message(),
                ],
            ], 500);
        }

        // 대표 이미지 설정
        if ($featured_image_id) {
            set_post_thumbnail($post_id, $featured_image_id);
        }

        // 태그 설정
        if (!empty($tags)) {
            wp_set_post_tags($post_id, $tags);
        }

        // SEO 메타데이터 저장
        if (!empty($result['meta_title'])) {
            update_post_meta($post_id, '_aicr_meta_title', sanitize_text_field($result['meta_title']));
        }
        if (!empty($result['meta_description'])) {
            update_post_meta($post_id, '_aicr_meta_description', sanitize_text_field($result['meta_description']));
        }

        // 품질 점수 저장
        update_post_meta($post_id, '_aicr_quality_score', $quality_score);
        update_post_meta($post_id, '_aicr_task_id', $task_id);

        // 처리 메트릭 저장
        if ($metrics) {
            update_post_meta($post_id, '_aicr_processing_metrics', $metrics);
        }

        // 피드 아이템 상태 업데이트
        if ($item_id) {
            $this->update_feed_item_status(
                $item_id,
                $post_status === 'publish' ? 'published' : 'draft_saved',
                $post_id,
                $quality_score
            );
        }

        // 히스토리 기록
        $this->log_history($task_id, $item_id, $post_id, $quality_score, $metrics);

        return new WP_REST_Response([
            'success' => true,
            'data'    => [
                'post_id'     => $post_id,
                'post_status' => $post_status,
                'permalink'   => get_permalink($post_id),
            ],
        ], 200);
    }

    /**
     * Handle processing failure
     */
    private function handle_failure(
        string $task_id,
        ?int $item_id,
        ?array $error,
        ?array $metrics
    ): WP_REST_Response {
        // 피드 아이템 상태 업데이트
        if ($item_id) {
            $this->update_feed_item_status($item_id, 'failed', null, null);
        }

        // 에러 로그
        $error_message = $error['message'] ?? 'Unknown error';
        $error_code = $error['code'] ?? 'AICR_010';

        // 알림 생성
        $notifications = get_option('aicr_notifications', []);
        array_unshift($notifications, [
            'level'      => 'warning',
            'code'       => $error_code,
            'message'    => "Task {$task_id} failed: {$error_message}",
            'details'    => [
                'task_id' => $task_id,
                'item_id' => $item_id,
                'error'   => $error,
                'metrics' => $metrics,
            ],
            'created_at' => current_time('mysql'),
            'read'       => false,
        ]);
        $notifications = array_slice($notifications, 0, 100);
        update_option('aicr_notifications', $notifications);

        // 히스토리 기록
        $this->log_history($task_id, $item_id, null, null, $metrics, $error);

        return new WP_REST_Response([
            'success' => true,
            'data'    => [
                'received'   => true,
                'status'     => 'failure_recorded',
                'error_code' => $error_code,
            ],
        ], 200);
    }

    /**
     * Download image from URL and attach to media library
     */
    private function download_and_attach_image(string $image_url, string $title): ?int {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // 다운로드
        $tmp = download_url($image_url);

        if (is_wp_error($tmp)) {
            error_log('[AICR Webhook] Image download failed: ' . $tmp->get_error_message());
            return null;
        }

        // 파일 정보
        $file_array = [
            'name'     => sanitize_file_name($title) . '.png',
            'tmp_name' => $tmp,
        ];

        // 미디어 라이브러리에 추가
        $attachment_id = media_handle_sideload($file_array, 0);

        // 임시 파일 삭제
        @unlink($tmp);

        if (is_wp_error($attachment_id)) {
            error_log('[AICR Webhook] Image upload failed: ' . $attachment_id->get_error_message());
            return null;
        }

        return $attachment_id;
    }

    /**
     * Resolve category by name or create if auto-creation is enabled
     */
    private function resolve_category(?string $category_name): ?int {
        if (empty($category_name)) {
            return (int)get_option('default_category', 1);
        }

        // 기존 카테고리 검색
        $category = get_category_by_slug(sanitize_title($category_name));

        if ($category) {
            return $category->term_id;
        }

        // 자동 생성 설정 확인
        if (get_option('aicr_auto_category', true)) {
            $result = wp_insert_term($category_name, 'category');

            if (!is_wp_error($result)) {
                return $result['term_id'];
            }
        }

        // 기본 카테고리 반환
        return (int)get_option('default_category', 1);
    }

    /**
     * Get default post author
     */
    private function get_default_author(): int {
        $author_id = (int)get_option('aicr_default_author', 0);

        if ($author_id && get_user_by('ID', $author_id)) {
            return $author_id;
        }

        // 관리자 중 첫 번째 사용자
        $admins = get_users(['role' => 'administrator', 'number' => 1]);
        if (!empty($admins)) {
            return $admins[0]->ID;
        }

        return 1;
    }

    /**
     * Update feed item status
     */
    private function update_feed_item_status(
        int $item_id,
        string $status,
        ?int $post_id,
        ?float $quality_score
    ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'aicr_feed_items';

        $data = [
            'status'     => $status,
            'updated_at' => current_time('mysql'),
        ];

        if ($post_id) {
            $data['post_id'] = $post_id;
        }

        if ($quality_score !== null) {
            $data['quality_score'] = $quality_score;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->update($table, $data, ['id' => $item_id]);
    }

    /**
     * Log processing history
     */
    private function log_history(
        string $task_id,
        ?int $item_id,
        ?int $post_id,
        ?float $quality_score,
        ?array $metrics,
        ?array $error = null
    ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'aicr_history';

        $data = [
            'task_id'       => $task_id,
            'feed_item_id'  => $item_id,
            'post_id'       => $post_id,
            'quality_score' => $quality_score,
            'status'        => $error ? 'failed' : 'success',
            'processing_time' => $metrics['processing_time_ms'] ?? null,
            'token_input'   => $metrics['token_usage']['input'] ?? null,
            'token_output'  => $metrics['token_usage']['output'] ?? null,
            'error_message' => $error ? json_encode($error) : null,
            'created_at'    => current_time('mysql'),
        ];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->insert($table, $data);
    }
}
