<?php
/**
 * Processing Mode Handler - Local vs Cloudflare Mode
 *
 * @package AIContentRewriter\Core
 * @since 2.0.0
 */

namespace AIContentRewriter\Core;

use AIContentRewriter\Worker\WorkerConfig;
use AIContentRewriter\Worker\TaskDispatcher;
use AIContentRewriter\Content\ContentRewriter;

/**
 * Processing Mode Class
 *
 * Determines whether to process content locally or via Cloudflare Worker
 */
class ProcessingMode {

    /**
     * Worker configuration
     */
    private WorkerConfig $config;

    /**
     * Task dispatcher
     */
    private TaskDispatcher $dispatcher;

    /**
     * Singleton instance
     */
    private static ?ProcessingMode $instance = null;

    /**
     * Private constructor
     */
    private function __construct() {
        $this->config = new WorkerConfig();
        $this->dispatcher = new TaskDispatcher();
    }

    /**
     * Get singleton instance
     */
    public static function get_instance(): ProcessingMode {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Check if Cloudflare mode is active and configured
     */
    public function is_cloudflare_available(): bool {
        return $this->config->is_cloudflare_mode() && $this->config->is_configured();
    }

    /**
     * Get current processing mode
     */
    public function get_mode(): string {
        return $this->config->get_processing_mode();
    }

    /**
     * Switch to a specific mode
     */
    public function switch_mode(string $mode): bool {
        return $this->config->set_processing_mode($mode);
    }

    /**
     * Process content rewrite request
     *
     * Routes to appropriate handler based on current mode
     *
     * @param array $params Rewrite parameters
     * @return array{success: bool, data?: array, error?: string}
     */
    public function process_rewrite(array $params): array {
        // Cloudflare 모드이고 설정이 완료된 경우
        if ($this->is_cloudflare_available()) {
            return $this->process_via_cloudflare($params);
        }

        // Local 모드 또는 Cloudflare 설정 미완료
        return $this->process_locally($params);
    }

    /**
     * Process content via Cloudflare Worker
     */
    private function process_via_cloudflare(array $params): array {
        $result = $this->dispatcher->dispatch_rewrite($params);

        if ($result['success']) {
            return [
                'success'   => true,
                'mode'      => 'cloudflare',
                'async'     => true,
                'task_id'   => $result['task_id'],
                'message'   => 'Task dispatched to Cloudflare Worker',
                'estimated' => $result['estimated_time'] ?? 180,
            ];
        }

        // Cloudflare 실패 시 폴백 여부 확인
        $fallback_enabled = (bool)get_option('aicr_cloudflare_fallback', true);

        if ($fallback_enabled) {
            // Local 모드로 폴백
            $local_result = $this->process_locally($params);
            $local_result['fallback'] = true;
            $local_result['fallback_reason'] = $result['error'] ?? 'Worker dispatch failed';
            return $local_result;
        }

        return [
            'success' => false,
            'mode'    => 'cloudflare',
            'error'   => $result['error'] ?? 'Worker dispatch failed',
        ];
    }

    /**
     * Process content locally (existing method)
     */
    private function process_locally(array $params): array {
        try {
            // 기존 ContentRewriter 사용
            $rewriter = new ContentRewriter();

            $source_content = $params['source_content'] ?? null;
            $source_url = $params['source_url'] ?? null;

            // URL에서 콘텐츠 추출
            if (empty($source_content) && !empty($source_url)) {
                $extractor = new \AIContentRewriter\Content\ContentExtractor();
                $extracted = $extractor->extract($source_url);

                if (!$extracted['success']) {
                    return [
                        'success' => false,
                        'mode'    => 'local',
                        'error'   => 'Failed to extract content from URL',
                    ];
                }

                $source_content = $extracted['content'];
            }

            if (empty($source_content)) {
                return [
                    'success' => false,
                    'mode'    => 'local',
                    'error'   => 'No source content provided',
                ];
            }

            // 재작성 실행
            $result = $rewriter->rewrite($source_content, [
                'language' => $params['language'] ?? get_option('aicr_output_language', 'ko'),
            ]);

            if (!$result['success']) {
                return [
                    'success' => false,
                    'mode'    => 'local',
                    'error'   => $result['error'] ?? 'Rewrite failed',
                ];
            }

            return [
                'success' => true,
                'mode'    => 'local',
                'async'   => false,
                'data'    => $result['data'],
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'mode'    => 'local',
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Check task status (for async Cloudflare tasks)
     */
    public function check_task_status(string $task_id): array {
        return $this->dispatcher->get_task_status($task_id);
    }

    /**
     * Sync configuration to Cloudflare Worker
     */
    public function sync_config(): array {
        if (!$this->is_cloudflare_available()) {
            return [
                'success' => false,
                'error'   => 'Cloudflare mode is not available',
            ];
        }

        return $this->dispatcher->sync_config();
    }

    /**
     * Test Cloudflare Worker connection
     */
    public function test_connection(): array {
        return $this->config->test_connection();
    }

    /**
     * Get status summary for dashboard
     */
    public function get_status_summary(): array {
        $mode = $this->get_mode();
        $summary = [
            'mode'         => $mode,
            'mode_label'   => $mode === WorkerConfig::MODE_CLOUDFLARE ? 'Cloudflare Worker' : 'Local',
            'is_available' => true,
        ];

        if ($mode === WorkerConfig::MODE_CLOUDFLARE) {
            $summary['is_configured'] = $this->config->is_configured();
            $summary['worker_url'] = $this->config->get_worker_url();

            if ($this->config->is_configured()) {
                // 마지막 연결 테스트 결과 (캐시된 경우)
                $last_test = get_transient('aicr_worker_connection_status');
                if ($last_test) {
                    $summary['last_connection'] = $last_test;
                }
            }
        }

        // 오늘 처리 통계
        global $wpdb;
        $table = $wpdb->prefix . 'aicr_history';
        $today = date('Y-m-d');

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $today_stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    AVG(quality_score) as avg_quality
                FROM {$table}
                WHERE DATE(created_at) = %s",
                $today
            ),
            ARRAY_A
        );

        if ($today_stats) {
            $summary['today_stats'] = [
                'total'       => (int)($today_stats['total'] ?? 0),
                'success'     => (int)($today_stats['success'] ?? 0),
                'failed'      => (int)($today_stats['failed'] ?? 0),
                'avg_quality' => $today_stats['avg_quality'] ? round((float)$today_stats['avg_quality'], 1) : null,
            ];
        }

        return $summary;
    }

    /**
     * Get Worker configuration instance
     */
    public function get_config(): WorkerConfig {
        return $this->config;
    }

    /**
     * Get Task Dispatcher instance
     */
    public function get_dispatcher(): TaskDispatcher {
        return $this->dispatcher;
    }
}
