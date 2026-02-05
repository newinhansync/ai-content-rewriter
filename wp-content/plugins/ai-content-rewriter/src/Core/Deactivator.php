<?php
/**
 * Plugin Deactivator
 *
 * @package AIContentRewriter\Core
 */

namespace AIContentRewriter\Core;

use AIContentRewriter\RSS\FeedScheduler;

/**
 * 플러그인 비활성화 시 실행되는 클래스
 */
class Deactivator {
    /**
     * 플러그인 비활성화 처리
     */
    public static function deactivate(): void {
        // 예약된 크론 작업 제거
        self::clear_scheduled_events();

        // 리라이트 규칙 갱신
        flush_rewrite_rules();

        // 비활성화 시간 기록
        update_option('aicr_deactivated_at', current_time('mysql'));
    }

    /**
     * 예약된 크론 이벤트 제거
     */
    private static function clear_scheduled_events(): void {
        // 기본 크론 훅
        $cron_hooks = [
            'aicr_scheduled_rewrite',
            'aicr_batch_process',
            'aicr_cleanup_logs',
        ];

        foreach ($cron_hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
        }

        // 모든 스케줄된 작업 제거
        wp_clear_scheduled_hook('aicr_scheduled_rewrite');
        wp_clear_scheduled_hook('aicr_batch_process');
        wp_clear_scheduled_hook('aicr_cleanup_logs');

        // RSS 스케줄러 이벤트 제거
        self::clear_rss_scheduled_events();
    }

    /**
     * RSS 스케줄러 이벤트 제거
     */
    private static function clear_rss_scheduled_events(): void {
        $scheduler = new FeedScheduler();
        $scheduler->unschedule_events();
    }
}
