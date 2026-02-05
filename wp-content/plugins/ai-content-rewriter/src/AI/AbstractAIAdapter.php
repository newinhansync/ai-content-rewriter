<?php
/**
 * Abstract AI Adapter
 *
 * @package AIContentRewriter\AI
 */

namespace AIContentRewriter\AI;

/**
 * AI 어댑터 추상 클래스
 */
abstract class AbstractAIAdapter implements AIAdapterInterface {
    /**
     * API 키
     */
    protected string $api_key = '';

    /**
     * 현재 모델
     */
    protected string $model = '';

    /**
     * 기본 옵션
     */
    protected array $default_options = [
        'temperature' => 0.7,
        'max_tokens' => 4096,
        'top_p' => 1.0,
    ];

    /**
     * HTTP 타임아웃 (초) - 긴 콘텐츠 생성을 위해 5분으로 설정
     */
    protected int $timeout = 300;

    /**
     * @inheritDoc
     */
    public function set_api_key(string $api_key): self {
        $this->api_key = $api_key;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function set_model(string $model): self {
        $this->model = $model;
        return $this;
    }

    /**
     * 옵션 병합
     */
    protected function merge_options(array $options): array {
        return array_merge($this->default_options, $options);
    }

    /**
     * HTTP POST 요청
     */
    protected function http_post(string $url, array $data, array $headers = []): array {
        $default_headers = [
            'Content-Type' => 'application/json',
        ];

        $response = wp_remote_post($url, [
            'timeout' => $this->timeout,
            'headers' => array_merge($default_headers, $headers),
            'body' => wp_json_encode($data),
        ]);

        if (is_wp_error($response)) {
            throw AIException::network_error($response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        return [
            'status_code' => $status_code,
            'body' => $decoded ?? [],
            'raw_body' => $body,
        ];
    }

    /**
     * 토큰 수 추정 (기본 구현)
     * 대략 4자당 1토큰으로 계산
     */
    public function estimate_tokens(string $text): int {
        // 한글은 대략 2자당 1토큰, 영어는 4자당 1토큰
        $korean_chars = preg_match_all('/[\x{AC00}-\x{D7AF}]/u', $text);
        $total_chars = mb_strlen($text);
        $non_korean_chars = $total_chars - $korean_chars;

        return (int) ceil($korean_chars / 2 + $non_korean_chars / 4);
    }

    /**
     * API 사용량 기록
     */
    protected function log_usage(AIResponse $response): void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aicr_api_usage';

        $wpdb->insert($table_name, [
            'user_id' => get_current_user_id(),
            'ai_provider' => $this->get_provider_name(),
            'ai_model' => $response->get_model(),
            'request_type' => 'generate',
            'tokens_input' => $response->get_input_tokens(),
            'tokens_output' => $response->get_output_tokens(),
            'response_time' => $response->get_response_time(),
            'status' => $response->is_success() ? 'success' : 'failed',
            'error_code' => $response->get_error_code(),
        ]);
    }
}
