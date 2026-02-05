<?php
/**
 * URL Security Validator
 *
 * @package AIContentRewriter\Security
 */

namespace AIContentRewriter\Security;

/**
 * SSRF 방지를 위한 URL 검증 클래스
 */
class UrlValidator {
    /**
     * 차단할 내부 IP 범위
     */
    private const BLOCKED_IP_RANGES = [
        '10.0.0.0/8',       // Private-Use Networks
        '172.16.0.0/12',    // Private-Use Networks
        '192.168.0.0/16',   // Private-Use Networks
        '127.0.0.0/8',      // Loopback
        '169.254.0.0/16',   // Link Local
        '0.0.0.0/8',        // "This" Network
        '224.0.0.0/4',      // Multicast
        '240.0.0.0/4',      // Reserved
        '100.64.0.0/10',    // Shared Address Space (CGN)
        '192.0.0.0/24',     // IETF Protocol Assignments
        '192.0.2.0/24',     // TEST-NET-1
        '198.51.100.0/24',  // TEST-NET-2
        '203.0.113.0/24',   // TEST-NET-3
        '::1/128',          // IPv6 Loopback
        'fc00::/7',         // IPv6 Unique Local
        'fe80::/10',        // IPv6 Link Local
    ];

    /**
     * 차단할 호스트명
     */
    private const BLOCKED_HOSTS = [
        'localhost',
        'localhost.localdomain',
        '*.local',
        '*.internal',
        '*.localhost',
        'metadata.google.internal',     // GCP Metadata
        '169.254.169.254',              // AWS/GCP/Azure Metadata
        'metadata.google',
    ];

    /**
     * 차단할 프로토콜
     */
    private const BLOCKED_SCHEMES = [
        'file',
        'gopher',
        'dict',
        'ftp',
        'sftp',
        'ssh',
        'telnet',
        'ldap',
        'ldaps',
    ];

    /**
     * URL 보안 검증
     *
     * @param string $url 검증할 URL
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validate(string $url): array {
        // 1. URL 형식 검증
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return [
                'valid' => false,
                'message' => __('유효하지 않은 URL 형식입니다.', 'ai-content-rewriter'),
            ];
        }

        // 2. URL 파싱
        $parsed = wp_parse_url($url);
        if (!$parsed || !isset($parsed['host'])) {
            return [
                'valid' => false,
                'message' => __('URL을 파싱할 수 없습니다.', 'ai-content-rewriter'),
            ];
        }

        $scheme = strtolower($parsed['scheme'] ?? '');
        $host = strtolower($parsed['host']);

        // 3. 프로토콜 검증 (http, https만 허용)
        if (!in_array($scheme, ['http', 'https'], true)) {
            return [
                'valid' => false,
                'message' => sprintf(
                    __('허용되지 않은 프로토콜입니다: %s', 'ai-content-rewriter'),
                    esc_html($scheme)
                ),
            ];
        }

        // 4. 차단된 프로토콜 검증
        if (in_array($scheme, self::BLOCKED_SCHEMES, true)) {
            return [
                'valid' => false,
                'message' => __('차단된 프로토콜입니다.', 'ai-content-rewriter'),
            ];
        }

        // 5. 차단된 호스트명 검증
        if (self::is_blocked_host($host)) {
            return [
                'valid' => false,
                'message' => __('내부 네트워크 주소에 대한 접근은 허용되지 않습니다.', 'ai-content-rewriter'),
            ];
        }

        // 6. DNS 조회 및 IP 검증
        $ip_validation = self::validate_resolved_ip($host);
        if (!$ip_validation['valid']) {
            return $ip_validation;
        }

        // 7. 포트 검증 (표준 포트만 허용)
        if (isset($parsed['port'])) {
            $port = (int) $parsed['port'];
            $allowed_ports = [80, 443, 8080, 8443];
            if (!in_array($port, $allowed_ports, true)) {
                return [
                    'valid' => false,
                    'message' => sprintf(
                        __('허용되지 않은 포트입니다: %d', 'ai-content-rewriter'),
                        $port
                    ),
                ];
            }
        }

        return [
            'valid' => true,
            'message' => '',
        ];
    }

    /**
     * 호스트가 차단 목록에 있는지 확인
     */
    private static function is_blocked_host(string $host): bool {
        foreach (self::BLOCKED_HOSTS as $blocked) {
            // 와일드카드 패턴 처리
            if (strpos($blocked, '*') !== false) {
                $pattern = '/^' . str_replace('\*', '.*', preg_quote($blocked, '/')) . '$/i';
                if (preg_match($pattern, $host)) {
                    return true;
                }
            } elseif ($host === $blocked) {
                return true;
            }
        }

        return false;
    }

    /**
     * DNS 조회 후 IP 주소 검증
     */
    private static function validate_resolved_ip(string $host): array {
        // IP 주소인 경우 직접 검증
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (self::is_blocked_ip($host)) {
                return [
                    'valid' => false,
                    'message' => __('내부 네트워크 IP 주소에 대한 접근은 허용되지 않습니다.', 'ai-content-rewriter'),
                ];
            }
            return ['valid' => true, 'message' => ''];
        }

        // 호스트명인 경우 DNS 조회
        $ips = gethostbynamel($host);
        if (!$ips) {
            // DNS 조회 실패해도 일단 허용 (WordPress HTTP API가 처리)
            return ['valid' => true, 'message' => ''];
        }

        // 모든 해결된 IP 검증
        foreach ($ips as $ip) {
            if (self::is_blocked_ip($ip)) {
                return [
                    'valid' => false,
                    'message' => __('DNS 조회 결과 내부 네트워크 IP가 확인되었습니다.', 'ai-content-rewriter'),
                ];
            }
        }

        return ['valid' => true, 'message' => ''];
    }

    /**
     * IP 주소가 차단 범위에 있는지 확인
     */
    private static function is_blocked_ip(string $ip): bool {
        // IPv4 검증
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ip_long = ip2long($ip);
            if ($ip_long === false) {
                return true; // 변환 실패 시 차단
            }

            $ipv4_ranges = [
                '10.0.0.0/8',
                '172.16.0.0/12',
                '192.168.0.0/16',
                '127.0.0.0/8',
                '169.254.0.0/16',
                '0.0.0.0/8',
            ];

            foreach ($ipv4_ranges as $range) {
                if (self::ip_in_range($ip, $range)) {
                    return true;
                }
            }
        }

        // IPv6 간단 검증 (루프백, 링크 로컬)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if ($ip === '::1' || strpos($ip, 'fe80:') === 0 || strpos($ip, 'fc') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * IP가 CIDR 범위 내에 있는지 확인
     */
    private static function ip_in_range(string $ip, string $cidr): bool {
        [$subnet, $bits] = explode('/', $cidr);
        $ip_long = ip2long($ip);
        $subnet_long = ip2long($subnet);
        $mask = -1 << (32 - (int) $bits);
        $subnet_long &= $mask;

        return ($ip_long & $mask) === $subnet_long;
    }

    /**
     * URL 안전하게 가져오기 (검증 포함)
     *
     * @param string $url URL
     * @param array $args wp_remote_get 인자
     * @return array|WP_Error
     */
    public static function safe_remote_get(string $url, array $args = []): \WP_Error|array {
        $validation = self::validate($url);
        if (!$validation['valid']) {
            return new \WP_Error('ssrf_blocked', $validation['message']);
        }

        // 리다이렉트 검증을 위한 콜백 추가
        $args['reject_unsafe_urls'] = true;

        return wp_remote_get($url, $args);
    }
}
