<?php
/**
 * Feed Scheduler
 *
 * RSS 피드 자동 갱신 스케줄러
 *
 * @package AIContentRewriter\RSS
 */

namespace AIContentRewriter\RSS;

use AIContentRewriter\Cron\CronLogger;

/**
 * RSS 피드 스케줄러 클래스
 */
class FeedScheduler {
    /**
     * Cron 훅 이름 - 피드 가져오기
     */
    public const HOOK_FETCH_FEEDS = 'aicr_fetch_feeds';

    /**
     * Cron 훅 이름 - 자동 재작성
     */
    public const HOOK_AUTO_REWRITE = 'aicr_auto_rewrite_items';

    /**
     * Cron 훅 이름 - 정리 작업
     */
    public const HOOK_CLEANUP = 'aicr_cleanup_old_items';

    /**
     * 피드 가져오기
     */
    private FeedFetcher $fetcher;

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
        $this->fetcher = new FeedFetcher();
        $this->manager = new FeedManager();
        $this->feed_repository = new FeedRepository();
        $this->item_repository = new FeedItemRepository();
    }

    /**
     * 초기화
     */
    public function init(): void {
        // 커스텀 Cron 주기 등록
        add_filter('cron_schedules', [$this, 'add_cron_schedules']);

        // Cron 훅 등록
        add_action(self::HOOK_FETCH_FEEDS, [$this, 'fetch_due_feeds']);
        add_action(self::HOOK_AUTO_REWRITE, [$this, 'process_auto_rewrite']);
        add_action(self::HOOK_CLEANUP, [$this, 'cleanup_old_items']);
    }

    /**
     * 커스텀 Cron 주기 추가
     */
    public function add_cron_schedules(array $schedules): array {
        // 15분마다
        $schedules['aicr_fifteen_minutes'] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display' => __('15분마다', 'ai-content-rewriter'),
        ];

        // 30분마다
        $schedules['aicr_thirty_minutes'] = [
            'interval' => 30 * MINUTE_IN_SECONDS,
            'display' => __('30분마다', 'ai-content-rewriter'),
        ];

        return $schedules;
    }

    /**
     * 커스텀 스케줄이 등록되어 있는지 확인하고 없으면 추가
     */
    private function ensure_custom_schedules(): void {
        // 필터가 아직 등록되지 않았으면 등록
        if (!has_filter('cron_schedules', [$this, 'add_cron_schedules'])) {
            add_filter('cron_schedules', [$this, 'add_cron_schedules']);
        }
    }

    /**
     * 스케줄 이벤트 등록
     */
    public function schedule_events(): void {
        // 커스텀 스케줄이 등록되어 있는지 확인 (활성화 시점에는 필터가 아직 없을 수 있음)
        $this->ensure_custom_schedules();

        // 피드 가져오기 스케줄 (15분마다)
        if (!wp_next_scheduled(self::HOOK_FETCH_FEEDS)) {
            wp_schedule_event(time(), 'aicr_fifteen_minutes', self::HOOK_FETCH_FEEDS);
        }

        // 자동 재작성 스케줄 (30분마다)
        if (!wp_next_scheduled(self::HOOK_AUTO_REWRITE)) {
            wp_schedule_event(time(), 'aicr_thirty_minutes', self::HOOK_AUTO_REWRITE);
        }

        // 정리 작업 스케줄 (매일)
        if (!wp_next_scheduled(self::HOOK_CLEANUP)) {
            wp_schedule_event(time(), 'daily', self::HOOK_CLEANUP);
        }
    }

    /**
     * 스케줄 이벤트 해제
     */
    public function unschedule_events(): void {
        $hooks = [
            self::HOOK_FETCH_FEEDS,
            self::HOOK_AUTO_REWRITE,
            self::HOOK_CLEANUP,
        ];

        foreach ($hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
        }
    }

    /**
     * 갱신 필요한 피드 가져오기
     */
    public function fetch_due_feeds(): void {
        $logger = new CronLogger();
        $log_id = $logger->start(self::HOOK_FETCH_FEEDS);
        $processed_count = 0;
        $error_message = null;

        try {
            $limit = (int) get_option('aicr_rss_concurrent_fetch', 5);
            $feeds = $this->feed_repository->find_due_for_fetch($limit);

            if (empty($feeds)) {
                $this->log('No feeds due for fetching');
                $logger->complete($log_id, 0);
                return;
            }

            $this->log(sprintf('Fetching %d feeds', count($feeds)));

            foreach ($feeds as $feed) {
                try {
                    $result = $this->fetcher->fetch($feed);

                    if ($result['success']) {
                        $processed_count += $result['new_items'];
                        $this->log(sprintf(
                            'Feed %d (%s): %d new items',
                            $feed->get_id(),
                            $feed->get_name(),
                            $result['new_items']
                        ));
                    } else {
                        $this->log(sprintf(
                            'Feed %d (%s) failed: %s',
                            $feed->get_id(),
                            $feed->get_name(),
                            $result['error']
                        ), 'error');
                    }
                } catch (\Exception $e) {
                    $this->log(sprintf(
                        'Feed %d exception: %s',
                        $feed->get_id(),
                        $e->getMessage()
                    ), 'error');
                }
            }

            $logger->complete($log_id, $processed_count);
        } catch (\Exception $e) {
            $error_message = $e->getMessage();
            $this->log('fetch_due_feeds exception: ' . $error_message, 'error');
            $logger->complete($log_id, $processed_count, $error_message);
        }
    }

    /**
     * 자동 재작성 처리
     */
    public function process_auto_rewrite(): void {
        $logger = new CronLogger();
        $log_id = $logger->start(self::HOOK_AUTO_REWRITE);
        $success_count = 0;
        $failed_count = 0;
        $error_message = null;

        try {
            // 큐에 있는 아이템 조회
            $limit = (int) get_option('aicr_rss_rewrite_queue_limit', 10);
            $items = $this->get_queued_items($limit);

            if (empty($items)) {
                $this->log('No items in auto-rewrite queue');
                $logger->complete($log_id, 0);
                return;
            }

            $this->log(sprintf('Processing %d items for auto-rewrite', count($items)));

            foreach ($items as $item) {
                try {
                    $result = $this->manager->rewrite_item($item->get_id());

                    if (is_wp_error($result)) {
                        $failed_count++;
                        $this->log(sprintf(
                            'Item %d rewrite failed: %s',
                            $item->get_id(),
                            $result->get_error_message()
                        ), 'error');
                    } else {
                        $success_count++;
                        $this->log(sprintf(
                            'Item %d rewritten to post %d',
                            $item->get_id(),
                            $result
                        ));
                    }

                    // API Rate Limit 고려하여 딜레이 추가
                    sleep(2);

                } catch (\Exception $e) {
                    $failed_count++;
                    $this->log(sprintf(
                        'Item %d exception: %s',
                        $item->get_id(),
                        $e->getMessage()
                    ), 'error');
                }
            }

            $this->log(sprintf(
                'Auto-rewrite completed: %d success, %d failed',
                $success_count,
                $failed_count
            ));

            $logger->complete($log_id, $success_count);
        } catch (\Exception $e) {
            $error_message = $e->getMessage();
            $this->log('process_auto_rewrite exception: ' . $error_message, 'error');
            $logger->complete($log_id, $success_count, $error_message);
        }
    }

    /**
     * 오래된 아이템 정리
     */
    public function cleanup_old_items(): void {
        $logger = new CronLogger();
        $log_id = $logger->start(self::HOOK_CLEANUP);

        try {
            if (!get_option('aicr_rss_auto_cleanup', true)) {
                $logger->complete($log_id, 0);
                return;
            }

            $deleted = $this->manager->cleanup_old_items();

            if ($deleted > 0) {
                $this->log(sprintf('Cleaned up %d old items', $deleted));
            }

            // Cron 로그 정리도 함께 수행 (7일 이전)
            $cron_logger = new CronLogger();
            $cron_logger->cleanup(7);

            $logger->complete($log_id, $deleted);
        } catch (\Exception $e) {
            $this->log('cleanup_old_items exception: ' . $e->getMessage(), 'error');
            $logger->complete($log_id, 0, $e->getMessage());
        }
    }

    /**
     * 큐에 있는 아이템 조회
     */
    private function get_queued_items(int $limit): array {
        global $wpdb;

        $table = $wpdb->prefix . 'aicr_feed_items';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT i.*, f.auto_rewrite, f.auto_publish
                 FROM {$table} i
                 JOIN {$wpdb->prefix}aicr_feeds f ON i.feed_id = f.id
                 WHERE i.status = %s
                 AND f.auto_rewrite = 1
                 AND f.status = %s
                 ORDER BY i.fetched_at ASC
                 LIMIT %d",
                FeedItem::STATUS_QUEUED,
                Feed::STATUS_ACTIVE,
                $limit
            ),
            ARRAY_A
        );

        return array_map(fn($row) => new FeedItem($row), $rows);
    }

    /**
     * 로그 기록
     */
    private function log(string $message, string $level = 'info'): void {
        if (!get_option('aicr_debug_mode', false)) {
            return;
        }

        $log_message = sprintf(
            '[%s] [AICR RSS Scheduler] [%s] %s',
            current_time('Y-m-d H:i:s'),
            strtoupper($level),
            $message
        );

        error_log($log_message);
    }

    /**
     * 다음 실행 시간 정보 가져오기
     */
    public function get_schedule_info(): array {
        $info = [];

        $hooks = [
            self::HOOK_FETCH_FEEDS => __('피드 갱신', 'ai-content-rewriter'),
            self::HOOK_AUTO_REWRITE => __('자동 재작성', 'ai-content-rewriter'),
            self::HOOK_CLEANUP => __('정리 작업', 'ai-content-rewriter'),
        ];

        foreach ($hooks as $hook => $label) {
            $next = wp_next_scheduled($hook);
            $info[$hook] = [
                'label' => $label,
                'next_run' => $next ? date_i18n('Y-m-d H:i:s', $next) : null,
                'scheduled' => $next !== false,
            ];
        }

        return $info;
    }

    /**
     * 즉시 실행 (수동)
     */
    public function run_now(string $task): bool {
        switch ($task) {
            case 'fetch':
                $this->fetch_due_feeds();
                return true;

            case 'rewrite':
                $this->process_auto_rewrite();
                return true;

            case 'cleanup':
                $this->cleanup_old_items();
                return true;

            default:
                return false;
        }
    }
}
