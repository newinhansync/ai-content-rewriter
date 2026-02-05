<?php
/**
 * REST API Controller for Cloudflare Worker Integration
 *
 * @package AIContentRewriter\API
 * @since 2.0.0
 */

namespace AIContentRewriter\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use AIContentRewriter\RSS\FeedRepository;
use AIContentRewriter\RSS\FeedItemRepository;

/**
 * REST API Controller Class
 */
class RestController extends WP_REST_Controller {

    /**
     * API namespace
     */
    protected $namespace = 'aicr/v1';

    /**
     * API Key option name
     */
    const API_KEY_OPTION = 'aicr_worker_api_key';

    /**
     * Register REST API routes
     */
    public function register_routes(): void {
        // GET /feeds - 활성 피드 목록
        register_rest_route($this->namespace, '/feeds', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_feeds'],
            'permission_callback' => [$this, 'check_api_key_permission'],
        ]);

        // GET /feed-items/pending - 대기 중 아이템
        register_rest_route($this->namespace, '/feed-items/pending', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_pending_items'],
            'permission_callback' => [$this, 'check_api_key_permission'],
            'args'                => $this->get_pending_items_args(),
        ]);

        // PATCH /feed-items/{id}/status - 아이템 상태 변경
        register_rest_route($this->namespace, '/feed-items/(?P<id>\d+)/status', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [$this, 'update_item_status'],
            'permission_callback' => [$this, 'check_api_key_permission'],
            'args'                => [
                'id' => [
                    'required'          => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ],
                'status' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function($param) {
                        $valid_statuses = ['new', 'queued', 'processing', 'completed', 'published', 'draft_saved', 'skipped', 'failed'];
                        return in_array($param, $valid_statuses, true);
                    },
                ],
            ],
        ]);

        // POST /webhook - 처리 결과 수신 (HMAC 인증)
        register_rest_route($this->namespace, '/webhook', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_webhook'],
            'permission_callback' => [$this, 'verify_hmac_signature'],
        ]);

        // POST /media - 이미지 업로드
        register_rest_route($this->namespace, '/media', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'upload_media'],
            'permission_callback' => [$this, 'check_api_key_permission'],
        ]);

        // GET /config - AI 설정 조회
        register_rest_route($this->namespace, '/config', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_config'],
            'permission_callback' => [$this, 'check_api_key_permission'],
        ]);

        // GET /health - 연결 확인
        register_rest_route($this->namespace, '/health', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'health_check'],
            'permission_callback' => [$this, 'check_api_key_permission'],
        ]);

        // POST /notifications - 알림 전송 (HMAC 인증)
        register_rest_route($this->namespace, '/notifications', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_notification'],
            'permission_callback' => [$this, 'verify_hmac_signature'],
        ]);
    }

    /**
     * Check API Key permission
     */
    public function check_api_key_permission(WP_REST_Request $request): bool {
        $auth_header = $request->get_header('Authorization');

        if (empty($auth_header) || strpos($auth_header, 'Bearer ') !== 0) {
            return false;
        }

        $provided_key = substr($auth_header, 7);
        $stored_key = get_option(self::API_KEY_OPTION, '');

        if (empty($stored_key)) {
            return false;
        }

        return hash_equals($stored_key, $provided_key);
    }

    /**
     * Verify HMAC signature for webhook requests
     */
    public function verify_hmac_signature(WP_REST_Request $request): bool {
        $signature = $request->get_header('X-AICR-Signature');
        $timestamp = $request->get_header('X-AICR-Timestamp');

        if (empty($signature) || empty($timestamp)) {
            return false;
        }

        // Timestamp 검증 (5분 이내)
        $timestamp_tolerance = 300;
        if (abs(time() - (int)$timestamp) > $timestamp_tolerance) {
            return false;
        }

        // HMAC 검증
        $body = $request->get_body();
        $secret = get_option('aicr_hmac_secret', '');

        if (empty($secret)) {
            return false;
        }

        $expected_signature = hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

        return hash_equals($expected_signature, $signature);
    }

    /**
     * GET /feeds - 활성 피드 목록
     */
    public function get_feeds(WP_REST_Request $request): WP_REST_Response {
        $feed_repo = new FeedRepository();
        $all_feeds = $feed_repo->get_all();

        $active_feeds = array_filter($all_feeds, function($feed) {
            return $feed['status'] === 'active';
        });

        $data = array_map(function($feed) {
            return [
                'id'             => (int)$feed['id'],
                'url'            => $feed['url'],
                'title'          => $feed['title'],
                'status'         => $feed['status'],
                'last_fetched'   => $feed['last_fetched'],
                'fetch_interval' => (int)($feed['fetch_interval'] ?? 3600),
                'auto_rewrite'   => (bool)($feed['auto_rewrite'] ?? false),
                'category_id'    => (int)($feed['category_id'] ?? 0),
            ];
        }, array_values($active_feeds));

        return new WP_REST_Response([
            'success' => true,
            'data'    => $data,
            'meta'    => [
                'total'  => count($all_feeds),
                'active' => count($active_feeds),
            ],
        ], 200);
    }

    /**
     * GET /feed-items/pending - 대기 중 아이템
     */
    public function get_pending_items(WP_REST_Request $request): WP_REST_Response {
        $status = $request->get_param('status') ?: 'queued';
        $limit = (int)$request->get_param('limit') ?: 10;
        $offset = (int)$request->get_param('offset') ?: 0;

        global $wpdb;
        $table = $wpdb->prefix . 'aicr_feed_items';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $status,
                $limit,
                $offset
            ),
            ARRAY_A
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $total_pending = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status = %s", $status)
        );

        // 오늘 게시된 건수
        $today = date('Y-m-d');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $daily_published = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE status = 'published' AND DATE(updated_at) = %s",
                $today
            )
        );

        $daily_limit = (int)get_option('aicr_daily_publish_limit', 10);

        $data = array_map(function($item) {
            return [
                'id'                   => (int)$item['id'],
                'feed_id'              => (int)$item['feed_id'],
                'guid'                 => $item['guid'],
                'title'                => $item['title'],
                'content'              => $item['content'],
                'link'                 => $item['link'],
                'pub_date'             => $item['pub_date'],
                'status'               => $item['status'],
                'curation_confidence'  => isset($item['curation_confidence']) ? (float)$item['curation_confidence'] : null,
                'curation_reason'      => $item['curation_reason'] ?? null,
            ];
        }, $items);

        return new WP_REST_Response([
            'success' => true,
            'data'    => $data,
            'meta'    => [
                'total_pending'   => (int)$total_pending,
                'daily_published' => (int)$daily_published,
                'daily_limit'     => $daily_limit,
            ],
        ], 200);
    }

    /**
     * PATCH /feed-items/{id}/status - 아이템 상태 변경
     */
    public function update_item_status(WP_REST_Request $request): WP_REST_Response {
        $item_id = (int)$request->get_param('id');
        $new_status = $request->get_param('status');

        global $wpdb;
        $table = $wpdb->prefix . 'aicr_feed_items';

        // 아이템 존재 확인
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $item = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $item_id),
            ARRAY_A
        );

        if (!$item) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => [
                    'code'    => 'AICR_005',
                    'message' => 'Feed item not found',
                ],
            ], 404);
        }

        // 상태 업데이트
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $updated = $wpdb->update(
            $table,
            [
                'status'     => $new_status,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $item_id],
            ['%s', '%s'],
            ['%d']
        );

        if ($updated === false) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => [
                    'code'    => 'AICR_010',
                    'message' => 'Failed to update item status',
                ],
            ], 500);
        }

        return new WP_REST_Response([
            'success' => true,
            'data'    => [
                'id'          => $item_id,
                'status'      => $new_status,
                'previous'    => $item['status'],
                'updated_at'  => current_time('mysql'),
            ],
        ], 200);
    }

    /**
     * POST /webhook - 처리 결과 수신
     */
    public function handle_webhook(WP_REST_Request $request): WP_REST_Response {
        $webhook_receiver = new WebhookReceiver();
        return $webhook_receiver->process($request);
    }

    /**
     * POST /media - 이미지 업로드
     */
    public function upload_media(WP_REST_Request $request): WP_REST_Response {
        $image_url = $request->get_param('image_url');
        $filename = $request->get_param('filename');
        $post_id = $request->get_param('post_id');

        if (empty($image_url)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => [
                    'code'    => 'AICR_001',
                    'message' => 'Image URL is required',
                ],
            ], 400);
        }

        // 이미지 다운로드 및 업로드
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url($image_url);

        if (is_wp_error($tmp)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => [
                    'code'    => 'AICR_007',
                    'message' => 'Image download failed: ' . $tmp->get_error_message(),
                ],
            ], 422);
        }

        $file_array = [
            'name'     => $filename ?: basename($image_url),
            'tmp_name' => $tmp,
        ];

        $attachment_id = media_handle_sideload($file_array, $post_id ?: 0);

        // 임시 파일 삭제
        @unlink($tmp);

        if (is_wp_error($attachment_id)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => [
                    'code'    => 'AICR_007',
                    'message' => 'Image upload failed: ' . $attachment_id->get_error_message(),
                ],
            ], 422);
        }

        return new WP_REST_Response([
            'success' => true,
            'data'    => [
                'attachment_id' => $attachment_id,
                'url'           => wp_get_attachment_url($attachment_id),
            ],
        ], 201);
    }

    /**
     * GET /config - AI 설정 조회
     */
    public function get_config(WP_REST_Request $request): WP_REST_Response {
        $config = [
            'ai_provider'        => get_option('aicr_ai_service', 'chatgpt'),
            'ai_model'           => get_option('aicr_chatgpt_model', 'gpt-4o'),
            'language'           => get_option('aicr_output_language', 'ko'),
            'content_length'     => get_option('aicr_content_length', 'long'),
            'auto_category'      => (bool)get_option('aicr_auto_category', true),
            'auto_tags'          => (bool)get_option('aicr_auto_tags', true),
            'seo_optimization'   => (bool)get_option('aicr_seo_optimization', true),
            'publish_threshold'  => (int)get_option('aicr_publish_threshold', 8),
            'daily_limit'        => (int)get_option('aicr_daily_publish_limit', 10),
            'curation_threshold' => (float)get_option('aicr_curation_threshold', 0.8),
        ];

        // 프롬프트 템플릿 (저장되어 있는 경우)
        $templates = get_option('aicr_prompt_templates', []);
        if (!empty($templates)) {
            $config['templates'] = $templates;
        }

        // 스타일 설정 (저장되어 있는 경우)
        $writing_style = get_option('aicr_writing_style', null);
        $image_style = get_option('aicr_image_style', null);

        if ($writing_style) {
            $config['writing_style'] = $writing_style;
        }
        if ($image_style) {
            $config['image_style'] = $image_style;
        }

        return new WP_REST_Response([
            'success' => true,
            'data'    => $config,
            'meta'    => [
                'version'    => AICR_VERSION,
                'updated_at' => get_option('aicr_config_updated', null),
            ],
        ], 200);
    }

    /**
     * GET /health - 연결 확인
     */
    public function health_check(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response([
            'success' => true,
            'data'    => [
                'status'    => 'healthy',
                'version'   => AICR_VERSION,
                'timestamp' => current_time('c'),
                'php'       => PHP_VERSION,
                'wordpress' => get_bloginfo('version'),
            ],
        ], 200);
    }

    /**
     * POST /notifications - 알림 전송
     */
    public function handle_notification(WP_REST_Request $request): WP_REST_Response {
        $level = $request->get_param('level');
        $code = $request->get_param('code');
        $message = $request->get_param('message');
        $details = $request->get_param('details');

        $valid_levels = ['critical', 'warning', 'info'];
        if (!in_array($level, $valid_levels, true)) {
            $level = 'info';
        }

        // 알림 저장
        $notification = [
            'level'      => $level,
            'code'       => sanitize_text_field($code),
            'message'    => sanitize_text_field($message),
            'details'    => $details,
            'created_at' => current_time('mysql'),
            'read'       => false,
        ];

        $notifications = get_option('aicr_notifications', []);
        array_unshift($notifications, $notification);

        // 최대 100개까지만 유지
        $notifications = array_slice($notifications, 0, 100);
        update_option('aicr_notifications', $notifications);

        // Critical 레벨은 이메일 발송
        if ($level === 'critical') {
            $admin_email = get_option('admin_email');
            $subject = '[AI Content Rewriter] Critical Alert: ' . $code;
            $body = "Alert: {$message}\n\nDetails:\n" . print_r($details, true);
            wp_mail($admin_email, $subject, $body);
        }

        return new WP_REST_Response([
            'success' => true,
            'data'    => [
                'received' => true,
                'level'    => $level,
            ],
        ], 200);
    }

    /**
     * Get pending items endpoint arguments
     */
    private function get_pending_items_args(): array {
        return [
            'status' => [
                'default'           => 'queued',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'limit' => [
                'default'           => 10,
                'sanitize_callback' => 'absint',
                'validate_callback' => function($param) {
                    return $param > 0 && $param <= 100;
                },
            ],
            'offset' => [
                'default'           => 0,
                'sanitize_callback' => 'absint',
            ],
        ];
    }
}
