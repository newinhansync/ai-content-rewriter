<?php
/**
 * ChatGPT Adapter
 *
 * @package AIContentRewriter\AI
 */

namespace AIContentRewriter\AI;

/**
 * OpenAI ChatGPT API 어댑터
 */
class ChatGPTAdapter extends AbstractAIAdapter {
    /**
     * API 기본 URL
     */
    private const API_BASE_URL = 'https://api.openai.com/v1';

    /**
     * 사용 가능한 모델 목록
     */
    private const MODELS = [
        'gpt-5' => 'GPT-5 (Latest)',
        'gpt-5-mini' => 'GPT-5 Mini',
        'gpt-4.1' => 'GPT-4.1',
        'gpt-4o' => 'GPT-4o',
        'gpt-4o-mini' => 'GPT-4o Mini',
        'o3' => 'o3 (Reasoning)',
        'o3-mini' => 'o3-mini (Reasoning)',
        'o1' => 'o1 (Reasoning)',
    ];

    /**
     * 기본 모델
     */
    protected string $model = 'gpt-5';

    /**
     * 기본 옵션
     */
    protected array $default_options = [
        'temperature' => 0.7,
        'max_completion_tokens' => 32768,
        'top_p' => 1.0,
        'frequency_penalty' => 0,
        'presence_penalty' => 0,
    ];

    /**
     * max_completion_tokens를 사용해야 하는 모델 목록
     */
    private const MODELS_USING_MAX_COMPLETION_TOKENS = [
        'gpt-5',
        'gpt-5-mini',
        'gpt-4.1',
        'gpt-4o',
        'gpt-4o-mini',
        'gpt-4-turbo',
        'gpt-4-turbo-preview',
        'o1',
        'o1-mini',
        'o1-preview',
        'o3',
        'o3-mini',
    ];

    /**
     * 제한된 파라미터만 지원하는 모델 (gpt-5, o1, o3 시리즈)
     * temperature, top_p, frequency_penalty, presence_penalty 지원 안함
     */
    private const MODELS_WITH_LIMITED_PARAMS = [
        'gpt-5',
        'gpt-5-mini',
        'o1',
        'o1-mini',
        'o1-preview',
        'o3',
        'o3-mini',
    ];

    /**
     * @inheritDoc
     */
    public function get_provider_name(): string {
        return 'ChatGPT';
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

            $messages = $this->build_messages($prompt, $options);

            $request_body = [
                'model' => $this->model,
                'messages' => $messages,
            ];

            // o1/o3 시리즈는 temperature, top_p 등을 지원하지 않음
            if (!$this->has_limited_params()) {
                $request_body['temperature'] = $merged_options['temperature'];
                $request_body['top_p'] = $merged_options['top_p'];
                $request_body['frequency_penalty'] = $merged_options['frequency_penalty'];
                $request_body['presence_penalty'] = $merged_options['presence_penalty'];
            }

            // 모델에 따라 적절한 토큰 제한 파라미터 사용
            $max_tokens = $merged_options['max_completion_tokens'] ?? $merged_options['max_tokens'] ?? 4096;
            if ($this->uses_max_completion_tokens()) {
                $request_body['max_completion_tokens'] = $max_tokens;
            } else {
                $request_body['max_tokens'] = $max_tokens;
            }

            $response = $this->http_post(
                self::API_BASE_URL . '/chat/completions',
                $request_body,
                ['Authorization' => 'Bearer ' . $this->api_key]
            );

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
        // 현재는 일반 generate 호출
        return $this->generate($prompt, $options);
    }

    /**
     * @inheritDoc
     */
    public function test_connection(): bool {
        try {
            $request_body = [
                'model' => $this->model,
                'messages' => [['role' => 'user', 'content' => 'Hi']],
            ];

            // 모델에 따라 적절한 토큰 제한 파라미터 사용
            if ($this->uses_max_completion_tokens()) {
                $request_body['max_completion_tokens'] = 5;
            } else {
                $request_body['max_tokens'] = 5;
            }

            $response = $this->http_post(
                self::API_BASE_URL . '/chat/completions',
                $request_body,
                ['Authorization' => 'Bearer ' . $this->api_key]
            );

            return $response['status_code'] === 200;
        } catch (AIException $e) {
            return false;
        }
    }

    /**
     * 현재 모델이 max_completion_tokens를 사용하는지 확인
     */
    private function uses_max_completion_tokens(): bool {
        foreach (self::MODELS_USING_MAX_COMPLETION_TOKENS as $model_prefix) {
            if (str_starts_with($this->model, $model_prefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 현재 모델이 제한된 파라미터만 지원하는지 확인 (o1, o3 시리즈)
     */
    private function has_limited_params(): bool {
        foreach (self::MODELS_WITH_LIMITED_PARAMS as $model_prefix) {
            if (str_starts_with($this->model, $model_prefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 메시지 배열 생성
     */
    private function build_messages(string $prompt, array $options): array {
        $messages = [];

        // o1/o3 시리즈는 system 메시지를 지원하지 않음
        if (!$this->has_limited_params()) {
            // 시스템 메시지 추가
            if (!empty($options['system_message'])) {
                $messages[] = [
                    'role' => 'system',
                    'content' => $options['system_message'],
                ];
            } else {
                $messages[] = [
                    'role' => 'system',
                    'content' => 'You are a helpful AI assistant that specializes in content writing and rewriting. You create high-quality, SEO-optimized blog posts.',
                ];
            }
        }

        // 사용자 메시지 추가 (o1/o3의 경우 시스템 지시사항 포함)
        $user_content = $prompt;
        if ($this->has_limited_params()) {
            $system_instruction = $options['system_message']
                ?? 'You are a helpful AI assistant that specializes in content writing and rewriting. You create high-quality, SEO-optimized blog posts.';
            $user_content = "[Instructions: {$system_instruction}]\n\n{$prompt}";
        }

        $messages[] = [
            'role' => 'user',
            'content' => $user_content,
        ];

        return $messages;
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
            $error_type = $error['type'] ?? 'unknown_error';

            switch ($status_code) {
                case 401:
                    throw AIException::invalid_api_key('OpenAI');
                case 429:
                    if (strpos($error_message, 'quota') !== false) {
                        throw AIException::quota_exceeded('OpenAI');
                    }
                    throw AIException::rate_limited('OpenAI');
                case 400:
                    if (strpos($error_type, 'context_length') !== false) {
                        throw AIException::context_length_exceeded('OpenAI', 128000);
                    }
                    break;
            }

            throw new AIException($error_message, strtoupper($error_type), $body);
        }

        // 성공 응답 파싱
        $choice = $body['choices'][0] ?? [];
        $content = $choice['message']['content'] ?? '';
        $usage = $body['usage'] ?? [];

        return AIResponse::success(
            trim($content),
            $usage['prompt_tokens'] ?? 0,
            $usage['completion_tokens'] ?? 0,
            $body['model'] ?? $this->model,
            $response_time,
            $body
        );
    }

    /**
     * 토큰 수 추정 (GPT 모델용)
     * tiktoken 라이브러리 없이 대략적으로 계산
     */
    public function estimate_tokens(string $text): int {
        // GPT 모델: 대략 영어 4자당 1토큰, 한글 2자당 1토큰
        $korean_chars = preg_match_all('/[\x{AC00}-\x{D7AF}]/u', $text);
        $total_chars = mb_strlen($text);
        $non_korean_chars = $total_chars - $korean_chars;

        // GPT는 좀 더 효율적인 토크나이저 사용
        return (int) ceil($korean_chars / 1.5 + $non_korean_chars / 4);
    }
}
