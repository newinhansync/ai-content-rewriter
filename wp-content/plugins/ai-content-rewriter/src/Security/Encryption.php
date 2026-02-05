<?php
/**
 * Encryption Utility for API Keys
 *
 * @package AIContentRewriter\Security
 */

namespace AIContentRewriter\Security;

/**
 * API 키 암호화/복호화 유틸리티 클래스
 */
class Encryption {
    /**
     * 암호화 메서드
     */
    private const METHOD = 'aes-256-cbc';

    /**
     * 암호화 키 가져오기
     * AUTH_KEY가 있으면 사용하고, 없으면 플러그인 전용 키 생성
     */
    private static function get_key(): string {
        if (defined('AUTH_KEY') && AUTH_KEY !== '') {
            return hash('sha256', AUTH_KEY, true);
        }

        // AUTH_KEY가 없으면 플러그인 전용 키 생성 및 저장
        $plugin_key = get_option('aicr_encryption_key');
        if (!$plugin_key) {
            $plugin_key = wp_generate_password(64, true, true);
            update_option('aicr_encryption_key', $plugin_key);
        }

        return hash('sha256', $plugin_key, true);
    }

    /**
     * 문자열 암호화
     *
     * @param string $plain_text 평문
     * @return string 암호화된 문자열 (base64 인코딩)
     */
    public static function encrypt(string $plain_text): string {
        if (empty($plain_text)) {
            return '';
        }

        $key = self::get_key();
        $iv_length = openssl_cipher_iv_length(self::METHOD);
        $iv = openssl_random_pseudo_bytes($iv_length);

        $encrypted = openssl_encrypt(
            $plain_text,
            self::METHOD,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            return '';
        }

        // IV와 암호문을 결합하여 base64 인코딩
        return base64_encode($iv . $encrypted);
    }

    /**
     * 문자열 복호화
     *
     * @param string $encrypted_text 암호화된 문자열 (base64 인코딩)
     * @return string 복호화된 평문
     */
    public static function decrypt(string $encrypted_text): string {
        if (empty($encrypted_text)) {
            return '';
        }

        $key = self::get_key();
        $iv_length = openssl_cipher_iv_length(self::METHOD);

        $decoded = base64_decode($encrypted_text, true);
        if ($decoded === false) {
            // base64 디코딩 실패 시 평문으로 간주 (마이그레이션 지원)
            return $encrypted_text;
        }

        // 데이터가 IV 길이보다 짧으면 평문으로 간주
        if (strlen($decoded) < $iv_length) {
            return $encrypted_text;
        }

        $iv = substr($decoded, 0, $iv_length);
        $encrypted = substr($decoded, $iv_length);

        $decrypted = openssl_decrypt(
            $encrypted,
            self::METHOD,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        // 복호화 실패 시 평문으로 간주 (마이그레이션 지원)
        return $decrypted !== false ? $decrypted : $encrypted_text;
    }

    /**
     * API 키 저장 (암호화)
     *
     * @param string $option_name 옵션 이름
     * @param string $api_key API 키 (평문)
     * @return bool 저장 성공 여부
     */
    public static function save_api_key(string $option_name, string $api_key): bool {
        if (empty($api_key)) {
            return delete_option($option_name);
        }

        $encrypted = self::encrypt($api_key);
        return update_option($option_name, $encrypted);
    }

    /**
     * API 키 조회 (복호화)
     *
     * @param string $option_name 옵션 이름
     * @return string 복호화된 API 키
     */
    public static function get_api_key(string $option_name): string {
        $encrypted = get_option($option_name, '');
        if (empty($encrypted)) {
            return '';
        }

        return self::decrypt($encrypted);
    }

    /**
     * 마스킹된 API 키 반환 (UI 표시용)
     *
     * @param string $api_key API 키
     * @param int $visible_chars 표시할 문자 수 (앞/뒤)
     * @return string 마스킹된 API 키
     */
    public static function mask_api_key(string $api_key, int $visible_chars = 4): string {
        if (empty($api_key)) {
            return '';
        }

        $length = strlen($api_key);
        if ($length <= $visible_chars * 2) {
            return str_repeat('*', $length);
        }

        $start = substr($api_key, 0, $visible_chars);
        $end = substr($api_key, -$visible_chars);
        $masked_length = $length - ($visible_chars * 2);

        return $start . str_repeat('*', $masked_length) . $end;
    }
}
