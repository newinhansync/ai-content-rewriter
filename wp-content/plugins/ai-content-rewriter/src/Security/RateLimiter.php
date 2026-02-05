<?php
/**
 * Rate Limiter for API Calls
 *
 * @package AIContentRewriter\Security
 */

namespace AIContentRewriter\Security;

/**
 * API 호출 빈도 제한 클래스
 */
class RateLimiter {
    /**
     * 기본 제한 설정
     */
    private const DEFAULT_LIMITS = [
        'ai_request' => [
            'limit' => 60,      // 최대 요청 수
            'window' => 3600,   // 시간 창 (초) - 1시간
        ],
        'url_fetch' => [
            'limit' => 100,
            'window' => 3600,
        ],
        'content_rewrite' => [
            'limit' => 30,
            'window' => 3600,
        ],
    ];

    /**
     * Transient 키 접두사
     */
    private const TRANSIENT_PREFIX = 'aicr_rate_';

    /**
     * 요청 허용 여부 확인 및 카운터 증가
     *
     * @param string $action 액션 유형 (ai_request, url_fetch, content_rewrite)
     * @param int|null $user_id 사용자 ID (null이면 현재 사용자)
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_time' => int]
     */
    public static function check(string $action, ?int $user_id = null): array {
        $user_id = $user_id ?? get_current_user_id();
        $limits = self::get_limits($action);

        if (!$limits) {
            // 알 수 없는 액션은 허용
            return ['allowed' => true, 'remaining' => -1, 'reset_time' => 0];
        }

        $key = self::get_key($action, $user_id);
        $data = self::get_data($key);

        // 시간 창이 지났으면 리셋
        if ($data['window_start'] + $limits['window'] < time()) {
            $data = [
                'count' => 0,
                'window_start' => time(),
            ];
        }

        $remaining = max(0, $limits['limit'] - $data['count']);
        $reset_time = $data['window_start'] + $limits['window'];

        if ($data['count'] >= $limits['limit']) {
            return [
                'allowed' => false,
                'remaining' => 0,
                'reset_time' => $reset_time,
                'message' => sprintf(
                    __('요청 한도 초과. %d분 후 다시 시도해주세요.', 'ai-content-rewriter'),
                    ceil(($reset_time - time()) / 60)
                ),
            ];
        }

        // 카운터 증가
        $data['count']++;
        self::set_data($key, $data, $limits['window']);

        return [
            'allowed' => true,
            'remaining' => $limits['limit'] - $data['count'],
            'reset_time' => $reset_time,
        ];
    }

    /**
     * 요청 허용 여부만 확인 (카운터 증가 없음)
     *
     * @param string $action 액션 유형
     * @param int|null $user_id 사용자 ID
     * @return bool 허용 여부
     */
    public static function is_allowed(string $action, ?int $user_id = null): bool {
        $user_id = $user_id ?? get_current_user_id();
        $limits = self::get_limits($action);

        if (!$limits) {
            return true;
        }

        $key = self::get_key($action, $user_id);
        $data = self::get_data($key);

        // 시간 창이 지났으면 허용
        if ($data['window_start'] + $limits['window'] < time()) {
            return true;
        }

        return $data['count'] < $limits['limit'];
    }

    /**
     * 현재 사용량 조회
     *
     * @param string $action 액션 유형
     * @param int|null $user_id 사용자 ID
     * @return array ['count' => int, 'limit' => int, 'remaining' => int, 'reset_time' => int]
     */
    public static function get_usage(string $action, ?int $user_id = null): array {
        $user_id = $user_id ?? get_current_user_id();
        $limits = self::get_limits($action);

        if (!$limits) {
            return ['count' => 0, 'limit' => -1, 'remaining' => -1, 'reset_time' => 0];
        }

        $key = self::get_key($action, $user_id);
        $data = self::get_data($key);

        // 시간 창이 지났으면 리셋
        if ($data['window_start'] + $limits['window'] < time()) {
            $data = ['count' => 0, 'window_start' => time()];
        }

        return [
            'count' => $data['count'],
            'limit' => $limits['limit'],
            'remaining' => max(0, $limits['limit'] - $data['count']),
            'reset_time' => $data['window_start'] + $limits['window'],
        ];
    }

    /**
     * 사용자 카운터 리셋
     *
     * @param string $action 액션 유형
     * @param int|null $user_id 사용자 ID
     */
    public static function reset(string $action, ?int $user_id = null): void {
        $user_id = $user_id ?? get_current_user_id();
        $key = self::get_key($action, $user_id);
        delete_transient($key);
    }

    /**
     * 제한 설정 가져오기
     */
    private static function get_limits(string $action): ?array {
        // 사용자 정의 설정 확인
        $custom_limits = get_option('aicr_rate_limits', []);
        if (isset($custom_limits[$action])) {
            return $custom_limits[$action];
        }

        return self::DEFAULT_LIMITS[$action] ?? null;
    }

    /**
     * Transient 키 생성
     */
    private static function get_key(string $action, int $user_id): string {
        return self::TRANSIENT_PREFIX . $action . '_' . $user_id;
    }

    /**
     * 데이터 조회
     */
    private static function get_data(string $key): array {
        $data = get_transient($key);
        if (!$data) {
            return [
                'count' => 0,
                'window_start' => time(),
            ];
        }
        return $data;
    }

    /**
     * 데이터 저장
     */
    private static function set_data(string $key, array $data, int $expiration): void {
        set_transient($key, $data, $expiration);
    }

    /**
     * 관리자용 - 모든 사용자 제한 현황 조회
     *
     * @return array
     */
    public static function get_all_user_stats(): array {
        global $wpdb;

        $stats = [];
        $pattern = '_transient_' . self::TRANSIENT_PREFIX . '%';

        $transients = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options}
                 WHERE option_name LIKE %s",
                $pattern
            )
        );

        foreach ($transients as $transient) {
            $name = str_replace('_transient_', '', $transient->option_name);
            $data = maybe_unserialize($transient->option_value);
            if (is_array($data)) {
                $stats[$name] = $data;
            }
        }

        return $stats;
    }
}
