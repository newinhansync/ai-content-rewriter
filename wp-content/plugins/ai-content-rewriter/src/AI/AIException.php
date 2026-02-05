<?php
/**
 * AI Exception
 *
 * @package AIContentRewriter\AI
 */

namespace AIContentRewriter\AI;

use Exception;

/**
 * AI 관련 예외 클래스
 */
class AIException extends Exception {
    /**
     * 에러 코드
     */
    private string $error_code;

    /**
     * 추가 컨텍스트 데이터
     */
    private array $context;

    /**
     * 생성자
     */
    public function __construct(
        string $message,
        string $error_code = 'UNKNOWN_ERROR',
        array $context = [],
        ?Exception $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->error_code = $error_code;
        $this->context = $context;
    }

    /**
     * 에러 코드 반환
     */
    public function get_error_code(): string {
        return $this->error_code;
    }

    /**
     * 컨텍스트 데이터 반환
     */
    public function get_context(): array {
        return $this->context;
    }

    /**
     * API 키 오류
     */
    public static function invalid_api_key(string $provider): self {
        return new self(
            sprintf(__('%s API 키가 유효하지 않습니다.', 'ai-content-rewriter'), $provider),
            'INVALID_API_KEY',
            ['provider' => $provider]
        );
    }

    /**
     * 할당량 초과 오류
     */
    public static function quota_exceeded(string $provider): self {
        return new self(
            sprintf(__('%s API 할당량이 초과되었습니다.', 'ai-content-rewriter'), $provider),
            'QUOTA_EXCEEDED',
            ['provider' => $provider]
        );
    }

    /**
     * 속도 제한 오류
     */
    public static function rate_limited(string $provider, int $retry_after = 0): self {
        return new self(
            sprintf(__('%s API 요청 속도 제한에 도달했습니다. %d초 후 다시 시도하세요.', 'ai-content-rewriter'), $provider, $retry_after),
            'RATE_LIMITED',
            ['provider' => $provider, 'retry_after' => $retry_after]
        );
    }

    /**
     * 네트워크 오류
     */
    public static function network_error(string $message): self {
        return new self(
            sprintf(__('네트워크 오류: %s', 'ai-content-rewriter'), $message),
            'NETWORK_ERROR',
            ['original_message' => $message]
        );
    }

    /**
     * 콘텐츠 필터링 오류
     */
    public static function content_filtered(string $provider): self {
        return new self(
            sprintf(__('%s 콘텐츠 정책에 의해 요청이 거부되었습니다.', 'ai-content-rewriter'), $provider),
            'CONTENT_FILTERED',
            ['provider' => $provider]
        );
    }

    /**
     * 컨텍스트 길이 초과 오류
     */
    public static function context_length_exceeded(string $provider, int $max_tokens): self {
        return new self(
            sprintf(__('%s 최대 컨텍스트 길이(%d 토큰)를 초과했습니다.', 'ai-content-rewriter'), $provider, $max_tokens),
            'CONTEXT_LENGTH_EXCEEDED',
            ['provider' => $provider, 'max_tokens' => $max_tokens]
        );
    }
}
