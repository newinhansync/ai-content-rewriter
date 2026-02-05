<?php
/**
 * Cron Monitor
 *
 * Cron 상태 모니터링 및 건강 체크 클래스
 *
 * @package AIContentRewriter\Cron
 */

namespace AIContentRewriter\Cron;

use AIContentRewriter\RSS\FeedScheduler;

/**
 * Cron 모니터 클래스
 */
class CronMonitor {
    /**
     * 상태 상수
     */
    public const STATUS_HEALTHY = 'healthy';
    public const STATUS_WARNING = 'warning';
    public const STATUS_CRITICAL = 'critical';

    /**
     * 전체 Cron 상태 조회
     *
     * @return array 상태 정보
     */
    public function get_health_status(): array {
        return [
            'overall_status' => $this->calculate_overall_status(),
            'wp_cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
            'alternate_cron' => defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON,
            'external_cron_confirmed' => (bool) get_option('aicr_external_cron_confirmed', false),
            'last_execution' => $this->get_last_execution_time(),
            'schedules' => $this->get_schedule_status(),
            'recommendations' => $this->get_recommendations(),
            'cron_urls' => $this->get_cron_urls(),
        ];
    }

    /**
     * 각 스케줄 상태 조회
     *
     * @return array 스케줄별 상태
     */
    public function get_schedule_status(): array {
        $scheduler = new FeedScheduler();
        $info = $scheduler->get_schedule_info();
        $logger = new CronLogger();

        foreach ($info as $hook => &$data) {
            $last_log = $logger->get_last_run($hook);

            $data['last_run'] = $last_log ? $last_log['completed_at'] : null;
            $data['last_status'] = $last_log ? $last_log['status'] : null;
            $data['last_items'] = $last_log ? (int) $last_log['items_processed'] : null;
            $data['last_execution_time'] = $last_log ? (float) $last_log['execution_time'] : null;
        }

        return $info;
    }

    /**
     * 전체 상태 계산
     *
     * @return string 상태 (healthy, warning, critical)
     */
    private function calculate_overall_status(): string {
        $wp_cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $external_confirmed = (bool) get_option('aicr_external_cron_confirmed', false);
        $last_execution = $this->get_last_execution_time();

        // Critical: DISABLE_WP_CRON인데 외부 cron 미확인
        if ($wp_cron_disabled && !$external_confirmed) {
            return self::STATUS_CRITICAL;
        }

        // Warning: 2시간 이상 실행 없음
        if ($last_execution) {
            $last_timestamp = strtotime($last_execution);
            if ($last_timestamp && (time() - $last_timestamp) > 7200) {
                return self::STATUS_WARNING;
            }
        }

        // Warning: WP-Cron 의존 (불안정)
        if (!$wp_cron_disabled && !$external_confirmed) {
            return self::STATUS_WARNING;
        }

        return self::STATUS_HEALTHY;
    }

    /**
     * 권고사항 생성
     *
     * @return array 권고사항 목록
     */
    public function get_recommendations(): array {
        $recommendations = [];

        // WP-Cron 비활성화 권고
        if (!defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON) {
            $recommendations[] = [
                'type' => 'warning',
                'code' => 'wp_cron_enabled',
                'message' => __('WP-Cron은 사이트 방문에 의존합니다. 안정적인 자동화를 위해 외부 Cron을 설정하고 DISABLE_WP_CRON을 활성화하세요.', 'ai-content-rewriter'),
            ];
        }

        // 최근 실행 없음 경고
        $last_execution = $this->get_last_execution_time();
        if (!$last_execution) {
            $recommendations[] = [
                'type' => 'info',
                'code' => 'no_execution',
                'message' => __('아직 Cron 실행 기록이 없습니다. 수동 실행 버튼으로 테스트해보세요.', 'ai-content-rewriter'),
            ];
        } elseif ((time() - strtotime($last_execution)) > 7200) {
            $recommendations[] = [
                'type' => 'error',
                'code' => 'stale_execution',
                'message' => __('최근 2시간 동안 Cron 실행 기록이 없습니다. 설정을 확인하세요.', 'ai-content-rewriter'),
            ];
        }

        // 외부 Cron 미확인
        $external_confirmed = (bool) get_option('aicr_external_cron_confirmed', false);
        if (!$external_confirmed && (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON)) {
            $recommendations[] = [
                'type' => 'error',
                'code' => 'external_cron_not_confirmed',
                'message' => __('DISABLE_WP_CRON이 활성화되어 있지만 외부 Cron 호출이 확인되지 않았습니다. 외부 Cron을 설정하세요.', 'ai-content-rewriter'),
            ];
        }

        return $recommendations;
    }

    /**
     * 보안 토큰 포함 Cron URL 생성
     *
     * @return array URL 목록
     */
    public function get_cron_urls(): array {
        $token = $this->get_or_create_token();

        return [
            'wp_cron' => site_url('/wp-cron.php'),
            'plugin_endpoint' => add_query_arg([
                'aicr_cron' => '1',
                'token' => $token,
            ], site_url('/')),
        ];
    }

    /**
     * 토큰 생성 또는 조회
     *
     * @return string 보안 토큰
     */
    public function get_or_create_token(): string {
        $token = get_option('aicr_cron_secret_token');

        if (empty($token)) {
            $token = wp_generate_password(32, false);
            update_option('aicr_cron_secret_token', $token);
        }

        return $token;
    }

    /**
     * 토큰 재생성
     *
     * @return string 새 토큰
     */
    public function regenerate_token(): string {
        $token = wp_generate_password(32, false);
        update_option('aicr_cron_secret_token', $token);
        return $token;
    }

    /**
     * 마지막 실행 시간 조회
     *
     * @return string|null 마지막 실행 시간
     */
    private function get_last_execution_time(): ?string {
        $logger = new CronLogger();
        $logs = $logger->get_recent(24, 1);

        if (!empty($logs) && isset($logs[0]['completed_at'])) {
            return $logs[0]['completed_at'];
        }

        return null;
    }

    /**
     * 상태 라벨 반환
     *
     * @param string $status 상태 코드
     * @return string 상태 라벨
     */
    public static function get_status_label(string $status): string {
        $labels = [
            self::STATUS_HEALTHY => __('정상', 'ai-content-rewriter'),
            self::STATUS_WARNING => __('경고', 'ai-content-rewriter'),
            self::STATUS_CRITICAL => __('위험', 'ai-content-rewriter'),
        ];

        return $labels[$status] ?? $status;
    }

    /**
     * 외부 Cron 호출 확인 표시
     */
    public function confirm_external_cron(): void {
        update_option('aicr_external_cron_confirmed', true);
    }

    /**
     * 외부 Cron 호출 확인 해제
     */
    public function reset_external_cron_confirmation(): void {
        update_option('aicr_external_cron_confirmed', false);
    }
}
