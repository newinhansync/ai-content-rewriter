<?php
/**
 * AI Response DTO
 *
 * @package AIContentRewriter\AI
 */

namespace AIContentRewriter\AI;

/**
 * AI 응답 데이터 클래스
 */
class AIResponse {
    /**
     * 응답 성공 여부
     */
    private bool $success;

    /**
     * 생성된 콘텐츠
     */
    private string $content;

    /**
     * 사용된 입력 토큰 수
     */
    private int $input_tokens;

    /**
     * 사용된 출력 토큰 수
     */
    private int $output_tokens;

    /**
     * 사용된 모델
     */
    private string $model;

    /**
     * 응답 시간 (초)
     */
    private float $response_time;

    /**
     * 원본 응답 데이터
     */
    private array $raw_response;

    /**
     * 에러 메시지 (실패 시)
     */
    private ?string $error_message;

    /**
     * 에러 코드 (실패 시)
     */
    private ?string $error_code;

    /**
     * 생성자
     */
    public function __construct(array $data = []) {
        $this->success = $data['success'] ?? false;
        $this->content = $data['content'] ?? '';
        $this->input_tokens = $data['input_tokens'] ?? 0;
        $this->output_tokens = $data['output_tokens'] ?? 0;
        $this->model = $data['model'] ?? '';
        $this->response_time = $data['response_time'] ?? 0.0;
        $this->raw_response = $data['raw_response'] ?? [];
        $this->error_message = $data['error_message'] ?? null;
        $this->error_code = $data['error_code'] ?? null;
    }

    /**
     * 성공 응답 생성
     */
    public static function success(
        string $content,
        int $input_tokens = 0,
        int $output_tokens = 0,
        string $model = '',
        float $response_time = 0.0,
        array $raw_response = []
    ): self {
        return new self([
            'success' => true,
            'content' => $content,
            'input_tokens' => $input_tokens,
            'output_tokens' => $output_tokens,
            'model' => $model,
            'response_time' => $response_time,
            'raw_response' => $raw_response,
        ]);
    }

    /**
     * 실패 응답 생성
     */
    public static function error(string $message, string $code = '', array $raw_response = []): self {
        return new self([
            'success' => false,
            'error_message' => $message,
            'error_code' => $code,
            'raw_response' => $raw_response,
        ]);
    }

    // Getters
    public function is_success(): bool {
        return $this->success;
    }

    public function get_content(): string {
        return $this->content;
    }

    public function get_input_tokens(): int {
        return $this->input_tokens;
    }

    public function get_output_tokens(): int {
        return $this->output_tokens;
    }

    public function get_total_tokens(): int {
        return $this->input_tokens + $this->output_tokens;
    }

    public function get_model(): string {
        return $this->model;
    }

    public function get_response_time(): float {
        return $this->response_time;
    }

    public function get_raw_response(): array {
        return $this->raw_response;
    }

    public function get_error_message(): ?string {
        return $this->error_message;
    }

    public function get_error_code(): ?string {
        return $this->error_code;
    }

    /**
     * 배열로 변환
     */
    public function to_array(): array {
        return [
            'success' => $this->success,
            'content' => $this->content,
            'input_tokens' => $this->input_tokens,
            'output_tokens' => $this->output_tokens,
            'total_tokens' => $this->get_total_tokens(),
            'model' => $this->model,
            'response_time' => $this->response_time,
            'error_message' => $this->error_message,
            'error_code' => $this->error_code,
        ];
    }
}
