<?php
/**
 * Image Response Class
 *
 * @package AIContentRewriter\AI
 */

namespace AIContentRewriter\AI;

/**
 * AI 이미지 생성 응답 클래스
 */
class ImageResponse {
    /**
     * 성공 여부
     */
    private bool $success;

    /**
     * Base64 인코딩된 이미지 데이터
     */
    private string $base64;

    /**
     * MIME 타입
     */
    private string $mime_type;

    /**
     * 에러 메시지
     */
    private string $error_message;

    /**
     * 응답 시간 (초)
     */
    private float $response_time;

    /**
     * 모델명
     */
    private string $model;

    /**
     * 원본 응답 데이터
     */
    private array $raw_response;

    /**
     * 생성자
     */
    private function __construct(
        bool $success,
        string $base64 = '',
        string $mime_type = 'image/png',
        string $error_message = '',
        float $response_time = 0.0,
        string $model = '',
        array $raw_response = []
    ) {
        $this->success = $success;
        $this->base64 = $base64;
        $this->mime_type = $mime_type;
        $this->error_message = $error_message;
        $this->response_time = $response_time;
        $this->model = $model;
        $this->raw_response = $raw_response;
    }

    /**
     * 성공 응답 생성
     */
    public static function success(
        string $base64,
        string $mime_type = 'image/png',
        string $model = '',
        float $response_time = 0.0,
        array $raw_response = []
    ): self {
        return new self(true, $base64, $mime_type, '', $response_time, $model, $raw_response);
    }

    /**
     * 에러 응답 생성
     */
    public static function error(string $message, float $response_time = 0.0): self {
        return new self(false, '', '', $message, $response_time);
    }

    /**
     * 성공 여부
     */
    public function isSuccess(): bool {
        return $this->success;
    }

    /**
     * Base64 이미지 데이터 반환
     */
    public function getBase64(): string {
        return $this->base64;
    }

    /**
     * MIME 타입 반환
     */
    public function getMimeType(): string {
        return $this->mime_type;
    }

    /**
     * 에러 메시지 반환
     */
    public function getErrorMessage(): string {
        return $this->error_message;
    }

    /**
     * 응답 시간 반환
     */
    public function getResponseTime(): float {
        return $this->response_time;
    }

    /**
     * 모델명 반환
     */
    public function getModel(): string {
        return $this->model;
    }

    /**
     * 원본 응답 데이터 반환
     */
    public function getRawResponse(): array {
        return $this->raw_response;
    }

    /**
     * 이미지 바이트 데이터 반환 (Base64 디코딩)
     */
    public function getImageData(): string {
        if (empty($this->base64)) {
            return '';
        }
        return base64_decode($this->base64);
    }

    /**
     * 이미지 유효성 검증
     */
    public function validateImage(): bool {
        if (!$this->success || empty($this->base64)) {
            return false;
        }

        $decoded = base64_decode($this->base64, true);
        if ($decoded === false) {
            return false;
        }

        // PNG 시그니처 확인
        if ($this->mime_type === 'image/png') {
            return substr($decoded, 0, 8) === "\x89PNG\r\n\x1a\n";
        }

        // JPEG 시그니처 확인
        if ($this->mime_type === 'image/jpeg') {
            return substr($decoded, 0, 2) === "\xff\xd8";
        }

        return true;
    }
}
