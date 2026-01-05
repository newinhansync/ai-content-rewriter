<?php
/**
 * Gemini Adapter
 *
 * @package AIContentRewriter\AI
 */

namespace AIContentRewriter\AI;

/**
 * Google Gemini API 어댑터
 */
class GeminiAdapter extends AbstractAIAdapter {
    /**
     * API 기본 URL
     */
    private const API_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

    /**
     * 사용 가능한 모델 목록
     */
    private const MODELS = [
        'gemini-3-pro' => 'Gemini 3 Pro (Latest)',
        'gemini-3-ultra' => 'Gemini 3 Ultra',
        'gemini-2.0-flash' => 'Gemini 2.0 Flash',
        'gemini-1.5-pro' => 'Gemini 1.5 Pro',
        'gemini-1.5-flash' => 'Gemini 1.5 Flash',
        'gemini-pro' => 'Gemini Pro',
    ];

    /**
     * 기본 모델
     */
    protected string $model = 'gemini-3-pro';

    /**
     * 기본 옵션
     */
    protected array $default_options = [
        'temperature' => 0.7,
        'max_tokens' => 4096,
        'top_p' => 0.95,
        'top_k' => 40,
    ];

    /**
     * @inheritDoc
     */
    public function get_provider_name(): string {
        return 'Gemini';
    }

    /**
     * @inheritDoc
     */
    public function get_available_models(): array {
        return self::MODELS;
    }

    /**
     * @inheritDoc
     */
    public function generate(string $prompt, array $options = []): AIResponse {
        $start_time = microtime(true);

        try {
            $merged_options = $this->merge_options($options);

            $request_body = $this->build_request_body($prompt, $merged_options);

            $url = sprintf(
                '%s/models/%s:generateContent?key=%s',
                self::API_BASE_URL,
                $this->model,
                $this->api_key
            );

            $response = $this->http_post($url, $request_body);

            $result = $this->parse_response($response, $start_time);
            $this->log_usage($result);

            return $result;

        } catch (AIException $e) {
            $error_response = AIResponse::error($e->getMessage(), $e->get_error_code());
            $this->log_usage($error_response);
            throw $e;
        }
    }

    /**
     * @inheritDoc
     */
    public function generate_stream(string $prompt, callable $callback, array $options = []): AIResponse {
        // 스트리밍은 추후 구현
        return $this->generate($prompt, $options);
    }

    /**
     * @inheritDoc
     */
    public function test_connection(): bool {
        try {
            $url = sprintf(
                '%s/models/%s:generateContent?key=%s',
                self::API_BASE_URL,
                'gemini-pro',
                $this->api_key
            );

            $response = $this->http_post($url, [
                'contents' => [
                    ['parts' => [['text' => 'Hi']]]
                ],
                'generationConfig' => ['maxOutputTokens' => 5],
            ]);

            return $response['status_code'] === 200;
        } catch (AIException $e) {
            return false;
        }
    }

    /**
     * 요청 본문 생성
     */
    private function build_request_body(string $prompt, array $options): array {
        $contents = [];

        // 시스템 인스트럭션 추가
        $system_instruction = $options['system_message'] ??
            'You are a helpful AI assistant that specializes in content writing and rewriting. You create high-quality, SEO-optimized blog posts.';

        // 사용자 메시지 추가
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $prompt]],
        ];

        return [
            'contents' => $contents,
            'systemInstruction' => [
                'parts' => [['text' => $system_instruction]],
            ],
            'generationConfig' => [
                'temperature' => $options['temperature'],
                'maxOutputTokens' => $options['max_tokens'],
                'topP' => $options['top_p'],
                'topK' => $options['top_k'],
            ],
            'safetySettings' => [
                [
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_ONLY_HIGH',
                ],
                [
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_ONLY_HIGH',
                ],
                [
                    'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                    'threshold' => 'BLOCK_ONLY_HIGH',
                ],
                [
                    'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                    'threshold' => 'BLOCK_ONLY_HIGH',
                ],
            ],
        ];
    }

    /**
     * API 응답 파싱
     */
    private function parse_response(array $response, float $start_time): AIResponse {
        $status_code = $response['status_code'];
        $body = $response['body'];
        $response_time = microtime(true) - $start_time;

        // 에러 처리
        if ($status_code !== 200) {
            $error = $body['error'] ?? [];
            $error_message = $error['message'] ?? __('알 수 없는 오류가 발생했습니다.', 'ai-content-rewriter');
            $error_status = $error['status'] ?? 'UNKNOWN';

            switch ($status_code) {
                case 400:
                    if (strpos($error_message, 'API key') !== false) {
                        throw AIException::invalid_api_key('Google');
                    }
                    break;
                case 403:
                    throw AIException::invalid_api_key('Google');
                case 429:
                    throw AIException::rate_limited('Google');
            }

            throw new AIException($error_message, $error_status, $body);
        }

        // 콘텐츠 필터링 체크
        $candidates = $body['candidates'] ?? [];
        if (empty($candidates)) {
            $block_reason = $body['promptFeedback']['blockReason'] ?? '';
            if ($block_reason) {
                throw AIException::content_filtered('Google');
            }
            throw new AIException(__('응답을 생성할 수 없습니다.', 'ai-content-rewriter'), 'NO_CONTENT');
        }

        // 성공 응답 파싱
        $candidate = $candidates[0];
        $content = '';

        if (!empty($candidate['content']['parts'])) {
            foreach ($candidate['content']['parts'] as $part) {
                $content .= $part['text'] ?? '';
            }
        }

        // 토큰 사용량
        $usage = $body['usageMetadata'] ?? [];
        $input_tokens = $usage['promptTokenCount'] ?? 0;
        $output_tokens = $usage['candidatesTokenCount'] ?? 0;

        return AIResponse::success(
            trim($content),
            $input_tokens,
            $output_tokens,
            $this->model,
            $response_time,
            $body
        );
    }

    /**
     * 토큰 수 추정 (Gemini 모델용)
     */
    public function estimate_tokens(string $text): int {
        // Gemini: 대략 영어 4자당 1토큰, 한글 2자당 1토큰
        $korean_chars = preg_match_all('/[\x{AC00}-\x{D7AF}]/u', $text);
        $total_chars = mb_strlen($text);
        $non_korean_chars = $total_chars - $korean_chars;

        return (int) ceil($korean_chars / 2 + $non_korean_chars / 4);
    }
}
