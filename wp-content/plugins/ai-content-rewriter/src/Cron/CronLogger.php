<?php
/**
 * Cron Logger
 *
 * Cron 실행 이력 로깅 클래스
 *
 * @package AIContentRewriter\Cron
 */

namespace AIContentRewriter\Cron;

/**
 * Cron 로거 클래스
 */
class CronLogger {
    /**
     * 테이블 이름
     */
    private string $table_name;

    /**
     * 생성자
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aicr_cron_logs';
    }

    /**
     * 실행 시작 기록
     *
     * @param string $hook_name 훅 이름
     * @return int 로그 ID
     */
    public function start(string $hook_name): int {
        global $wpdb;

        $wpdb->insert($this->table_name, [
            'hook_name' => $hook_name,
            'started_at' => current_time('mysql'),
            'status' => 'running',
        ], ['%s', '%s', '%s']);

        return (int) $wpdb->insert_id;
    }

    /**
     * 실행 완료 기록
     *
     * @param int $log_id 로그 ID
     * @param int $items_processed 처리된 아이템 수
     * @param string|null $error 에러 메시지
     */
    public function complete(int $log_id, int $items_processed = 0, ?string $error = null): void {
        global $wpdb;

        // 시작 시간 조회
        $started = $wpdb->get_var($wpdb->prepare(
            "SELECT started_at FROM {$this->table_name} WHERE id = %d",
            $log_id
        ));

        // 실행 시간 계산
        $execution_time = 0;
        if ($started) {
            $start_timestamp = strtotime($started);
            $end_timestamp = strtotime(current_time('mysql'));
            $execution_time = max(0, $end_timestamp - $start_timestamp);
        }

        $wpdb->update(
            $this->table_name,
            [
                'completed_at' => current_time('mysql'),
                'status' => $error ? 'failed' : 'completed',
                'items_processed' => $items_processed,
                'error_message' => $error,
                'execution_time' => $execution_time,
            ],
            ['id' => $log_id],
            ['%s', '%s', '%d', '%s', '%f'],
            ['%d']
        );
    }

    /**
     * 최근 로그 조회
     *
     * @param int $hours 조회할 시간 범위 (기본: 24시간)
     * @param int $limit 최대 조회 개수 (기본: 50)
     * @return array 로그 목록
     */
    public function get_recent(int $hours = 24, int $limit = 50): array {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE started_at > DATE_SUB(NOW(), INTERVAL %d HOUR)
             ORDER BY started_at DESC
             LIMIT %d",
            $hours,
            $limit
        ), ARRAY_A);

        return $results ?: [];
    }

    /**
     * 특정 훅의 마지막 실행 정보 조회
     *
     * @param string $hook_name 훅 이름
     * @return array|null 마지막 실행 정보
     */
    public function get_last_run(string $hook_name): ?array {
        global $wpdb;

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE hook_name = %s AND status != 'running'
             ORDER BY started_at DESC
             LIMIT 1",
            $hook_name
        ), ARRAY_A);

        return $result ?: null;
    }

    /**
     * 오래된 로그 정리
     *
     * @param int $days 보존 기간 (일), 0이면 모든 로그 삭제
     * @return int 삭제된 로그 수
     */
    public function cleanup(int $days = 7): int {
        global $wpdb;

        if ($days === 0) {
            // 모든 로그 삭제
            return (int) $wpdb->query("DELETE FROM {$this->table_name}");
        }

        return (int) $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name}
             WHERE started_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }

    /**
     * 실행 중인 작업 조회
     *
     * @return array 실행 중인 작업 목록
     */
    public function get_running(): array {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT * FROM {$this->table_name}
             WHERE status = 'running'
             ORDER BY started_at DESC",
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * 훅별 통계 조회
     *
     * @param int $days 통계 기간 (일)
     * @return array 훅별 통계
     */
    public function get_statistics(int $days = 7): array {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT
                hook_name,
                COUNT(*) as total_runs,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as success_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                AVG(execution_time) as avg_execution_time,
                SUM(items_processed) as total_items_processed
             FROM {$this->table_name}
             WHERE started_at > DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY hook_name",
            $days
        ), ARRAY_A);

        return $results ?: [];
    }
}
