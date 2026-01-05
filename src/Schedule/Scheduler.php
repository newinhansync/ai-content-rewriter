<?php
/**
 * Scheduler Handler
 *
 * @package AIContentRewriter\Schedule
 */

namespace AIContentRewriter\Schedule;

/**
 * 스케줄러 클래스 - WordPress Cron 관리
 */
class Scheduler {
    /**
     * 초기화
     */
    public function init(): void {
        // 커스텀 크론 스케줄 등록
        add_filter('cron_schedules', [$this, 'add_cron_schedules']);

        // 크론 훅 등록
        add_action('aicr_scheduled_rewrite', [$this, 'process_scheduled_rewrites']);
        add_action('aicr_batch_process', [$this, 'process_batch']);
        add_action('aicr_cleanup_logs', [$this, 'cleanup_old_logs']);
    }

    /**
     * 커스텀 크론 스케줄 추가
     */
    public function add_cron_schedules(array $schedules): array {
        $schedules['every_5_minutes'] = [
            'interval' => 300,
            'display' => __('Every 5 Minutes', 'ai-content-rewriter'),
        ];

        $schedules['every_15_minutes'] = [
            'interval' => 900,
            'display' => __('Every 15 Minutes', 'ai-content-rewriter'),
        ];

        $schedules['every_30_minutes'] = [
            'interval' => 1800,
            'display' => __('Every 30 Minutes', 'ai-content-rewriter'),
        ];

        return $schedules;
    }

    /**
     * 예약된 콘텐츠 변환 처리
     */
    public function process_scheduled_rewrites(): void {
        // TODO: 스케줄된 작업 처리 구현
    }

    /**
     * 배치 처리
     */
    public function process_batch(): void {
        // TODO: 배치 처리 구현
    }

    /**
     * 오래된 로그 정리
     */
    public function cleanup_old_logs(): void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aicr_history';
        $days_to_keep = apply_filters('aicr_log_retention_days', 90);

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days_to_keep
            )
        );
    }

    /**
     * 새 스케줄 등록
     */
    public function schedule_rewrite(array $args, string $schedule = 'once', ?int $timestamp = null): bool {
        $timestamp = $timestamp ?? time();

        if ($schedule === 'once') {
            return wp_schedule_single_event($timestamp, 'aicr_scheduled_rewrite', [$args]) !== false;
        }

        return wp_schedule_event($timestamp, $schedule, 'aicr_scheduled_rewrite', [$args]) !== false;
    }

    /**
     * 스케줄 취소
     */
    public function cancel_schedule(int $schedule_id): bool {
        // TODO: 스케줄 취소 구현
        return true;
    }
}
