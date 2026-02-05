<?php
/**
 * Task Dispatcher for sending tasks to Cloudflare Worker
 *
 * @package AIContentRewriter\Worker
 * @since 2.0.0
 */

namespace AIContentRewriter\Worker;

use WP_Error;

/**
 * Task Dispatcher Class
 */
class TaskDispatcher {

    /**
     * Worker configuration
     */
    private WorkerConfig $config;

    /**
     * Constructor
     */
    public function __construct() {
        $this->config = new WorkerConfig();
    }

    /**
     * Dispatch a rewrite task to Worker
     *
     * @param array $payload Task payload
     * @return array{success: bool, task_id?: string, message?: string, error?: string}
     */
    public function dispatch_rewrite(array $payload): array {
        if (!$this->config->is_cloudflare_mode()) {
            return [
                'success' => false,
                'error'   => 'Cloudflare mode is not enabled',
            ];
        }

        if (!$this->config->is_configured()) {
            return [
                'success' => false,
                'error'   => 'Worker is not configured',
            ];
        }

        // 태스크 ID 생성
        $task_id = $this->generate_task_id();

        // 요청 데이터 구성
        $request_data = [
            'task_id'         => $task_id,
            'task_type'       => 'rewrite',
            'callback_url'    => $this->config->get_webhook_url(),
            'callback_secret' => $this->config->get_hmac_secret(),
            'payload'         => $this->prepare_payload($payload),
        ];

        // Worker에 요청 전송
        $worker_url = rtrim($this->config->get_worker_url(), '/') . '/api/rewrite';

        $response = wp_remote_post($worker_url, [
            'timeout'     => 30,
            'headers'     => [
                'Authorization' => 'Bearer ' . $this->config->get_worker_secret(),
                'Content-Type'  => 'application/json',
            ],
            'body'        => wp_json_encode($request_data),
            'data_format' => 'body',
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error'   => 'Request failed: ' . $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // 202 Accepted 또는 200 OK
        if ($status_code === 202 || $status_code === 200) {
            // 태스크 추적 저장
            $this->save_task($task_id, $request_data, $payload);

            return [
                'success'        => true,
                'task_id'        => $task_id,
                'message'        => 'Task dispatched successfully',
                'estimated_time' => $data['estimated_time_seconds'] ?? 180,
            ];
        }

        return [
            'success' => false,
            'error'   => "Worker returned status {$status_code}: " . ($data['error']['message'] ?? 'Unknown error'),
            'data'    => $data,
        ];
    }

    /**
     * Dispatch a batch rewrite task
     *
     * @param array $items Array of items to rewrite
     * @return array{success: bool, tasks?: array, error?: string}
     */
    public function dispatch_batch(array $items): array {
        $results = [];
        $success_count = 0;
        $error_count = 0;

        foreach ($items as $item) {
            $result = $this->dispatch_rewrite($item);

            $results[] = [
                'item_id' => $item['item_id'] ?? null,
                'success' => $result['success'],
                'task_id' => $result['task_id'] ?? null,
                'error'   => $result['error'] ?? null,
            ];

            if ($result['success']) {
                $success_count++;
            } else {
                $error_count++;
            }

            // Rate limiting: 1초 대기
            if (count($items) > 1) {
                usleep(100000); // 0.1초
            }
        }

        return [
            'success'       => $error_count === 0,
            'tasks'         => $results,
            'success_count' => $success_count,
            'error_count'   => $error_count,
        ];
    }

    /**
     * Get task status from Worker
     *
     * @param string $task_id Task ID
     * @return array{success: bool, status?: string, data?: array, error?: string}
     */
    public function get_task_status(string $task_id): array {
        if (!$this->config->is_configured()) {
            return [
                'success' => false,
                'error'   => 'Worker is not configured',
            ];
        }

        $worker_url = rtrim($this->config->get_worker_url(), '/') . '/api/status/' . $task_id;

        $response = wp_remote_get($worker_url, [
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->config->get_worker_secret(),
            ],
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error'   => 'Request failed: ' . $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code === 200) {
            return [
                'success' => true,
                'status'  => $data['data']['status'] ?? 'unknown',
                'data'    => $data['data'] ?? [],
            ];
        }

        return [
            'success' => false,
            'error'   => "Worker returned status {$status_code}",
            'data'    => $data,
        ];
    }

    /**
     * Sync configuration to Worker
     *
     * @return array{success: bool, message?: string, error?: string}
     */
    public function sync_config(): array {
        if (!$this->config->is_configured()) {
            return [
                'success' => false,
                'error'   => 'Worker is not configured',
            ];
        }

        // 동기화할 설정 데이터
        $config_data = [
            'wordpress_url'       => home_url(),
            'api_key'             => $this->config->get_wp_api_key(),
            'hmac_secret'         => $this->config->get_hmac_secret(),
            'publish_threshold'   => $this->config->get_publish_threshold(),
            'daily_limit'         => $this->config->get_daily_limit(),
            'curation_threshold'  => $this->config->get_curation_threshold(),
        ];

        // 프롬프트 템플릿
        $templates = get_option('aicr_prompt_templates', []);
        if (!empty($templates)) {
            $config_data['prompt_templates'] = $templates;
        }

        // 스타일 설정
        $writing_style = get_option('aicr_writing_style', null);
        $image_style = get_option('aicr_image_style', null);

        if ($writing_style) {
            $config_data['writing_style'] = $writing_style;
        }
        if ($image_style) {
            $config_data['image_style'] = $image_style;
        }

        // Worker에 전송
        $worker_url = rtrim($this->config->get_worker_url(), '/') . '/api/sync-config';

        $response = wp_remote_post($worker_url, [
            'timeout'     => 30,
            'headers'     => [
                'Authorization' => 'Bearer ' . $this->config->get_worker_secret(),
                'Content-Type'  => 'application/json',
            ],
            'body'        => wp_json_encode($config_data),
            'data_format' => 'body',
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error'   => 'Sync failed: ' . $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code === 200) {
            update_option('aicr_config_synced_at', current_time('mysql'));
            return [
                'success' => true,
                'message' => 'Configuration synced successfully',
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return [
            'success' => false,
            'error'   => "Sync failed with status {$status_code}: " . ($data['error']['message'] ?? 'Unknown error'),
        ];
    }

    /**
     * Prepare payload for Worker request
     */
    private function prepare_payload(array $payload): array {
        return [
            'source_url'     => $payload['source_url'] ?? null,
            'source_content' => $payload['source_content'] ?? null,
            'item_id'        => $payload['item_id'] ?? null,
            'language'       => $payload['language'] ?? get_option('aicr_output_language', 'ko'),
            'ai_provider'    => $payload['ai_provider'] ?? get_option('aicr_ai_service', 'chatgpt'),
            'ai_model'       => $payload['ai_model'] ?? get_option('aicr_chatgpt_model', 'gpt-4o'),
        ];
    }

    /**
     * Generate unique task ID
     */
    private function generate_task_id(): string {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * Save task for tracking
     */
    private function save_task(string $task_id, array $request_data, array $original_payload): void {
        global $wpdb;
        $table = $wpdb->prefix . 'aicr_pending_tasks';

        // 테이블이 없으면 생성
        $this->ensure_task_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->insert($table, [
            'task_id'      => $task_id,
            'item_id'      => $original_payload['item_id'] ?? null,
            'task_type'    => 'rewrite',
            'status'       => 'dispatched',
            'payload'      => wp_json_encode($original_payload),
            'created_at'   => current_time('mysql'),
        ]);
    }

    /**
     * Ensure task tracking table exists
     */
    private function ensure_task_table(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'aicr_pending_tasks';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $table_exists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $table)
        );

        if (!$table_exists) {
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                task_id varchar(36) NOT NULL,
                item_id bigint(20) unsigned DEFAULT NULL,
                task_type varchar(50) NOT NULL DEFAULT 'rewrite',
                status varchar(50) NOT NULL DEFAULT 'dispatched',
                payload longtext,
                created_at datetime DEFAULT NULL,
                updated_at datetime DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY task_id (task_id),
                KEY item_id (item_id),
                KEY status (status)
            ) {$charset_collate};";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
        }
    }

    /**
     * Get pending tasks
     *
     * @param int $limit Maximum number of tasks to return
     * @return array
     */
    public function get_pending_tasks(int $limit = 10): array {
        global $wpdb;
        $table = $wpdb->prefix . 'aicr_pending_tasks';

        $this->ensure_task_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = 'dispatched' ORDER BY created_at ASC LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Update task status
     */
    public function update_task_status(string $task_id, string $status): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'aicr_pending_tasks';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $result = $wpdb->update(
            $table,
            [
                'status'     => $status,
                'updated_at' => current_time('mysql'),
            ],
            ['task_id' => $task_id]
        );

        return $result !== false;
    }

    /**
     * Clean up old completed tasks
     */
    public function cleanup_old_tasks(int $days = 7): int {
        global $wpdb;
        $table = $wpdb->prefix . 'aicr_pending_tasks';

        $this->ensure_task_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE status IN ('completed', 'failed') AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );

        return $deleted ?: 0;
    }
}
