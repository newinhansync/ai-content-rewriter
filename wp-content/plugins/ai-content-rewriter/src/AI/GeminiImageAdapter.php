<?php
/**
 * Gemini Image Adapter
 *
 * @package AIContentRewriter\AI
 */

namespace AIContentRewriter\AI;

use AIContentRewriter\Security\Encryption;

/**
 * Google Gemini Image API 어댑터
 * 이미지 생성 전용
 *
 * Nano Banana Pro (Gemini 3 Pro Image) 모델 사용 - 2025년 11월 출시
 * 한글 텍스트 렌더링 향상, 최대 4K 해상도 지원
 */
class GeminiImageAdapter {
    /**
     * Nano Banana Pro (Gemini 3 Pro Image) 엔드포인트 - 최신 권장
     * 텍스트 렌더링 개선, 4K 지원
     */
    private const GEMINI_IMAGE_API = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3-pro-image-preview:generateContent';

    /**
     * Nano Banana (Gemini 2.5 Flash Image) 엔드포인트 - 빠른 생성용
     */
    private const GEMINI_FLASH_IMAGE_API = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-image-preview:generateContent';

    /**
     * Legacy: OpenAI 호환 이미지 생성 엔드포인트 (폴백용)
     */
    private const OPENAI_COMPAT_API = 'https://generativelanguage.googleapis.com/v1beta/openai/images/generations';

    /**
     * Legacy: Imagen 직접 API 엔드포인트 (폴백용)
     */
    private const IMAGEN_PREDICT_API = 'https://generativelanguage.googleapis.com/v1beta/models/imagen-4.0-generate-001:predict';

    /**
     * 현재 사용 모델명
     */
    private const GEMINI_IMAGE_MODEL = 'gemini-3-pro-image-preview';

    /**
     * Legacy 모델명 (폴백용)
     */
    private const IMAGEN_MODEL = 'imagen-4.0-generate-001';

    /**
     * API 키
     */
    private string $apiKey;

    /**
     * 요청 타임아웃 (초)
     */
    private int $timeout = 90;

    /**
     * 최대 재시도 횟수
     */
    private int $maxRetries = 3;

    /**
     * 재시도 딜레이 (초)
     */
    private array $retryDelays = [2, 5, 10];

    /**
     * OpenAI 호환 엔드포인트 사용 여부
     */
    private bool $useOpenAICompat = true;

    /**
     * 지원하는 가로세로 비율
     */
    public const ASPECT_RATIOS = [
        '1:1' => '1024x1024',
        '3:4' => '768x1024',
        '4:3' => '1024x768',
        '9:16' => '576x1024',
        '16:9' => '1024x576',
    ];

    /**
     * 생성자
     */
    public function __construct(?string $apiKey = null) {
        $this->apiKey = $apiKey ?? Encryption::get_api_key('aicr_gemini_api_key');
    }

    /**
     * API 키 설정
     */
    public function setApiKey(string $apiKey): self {
        $this->apiKey = $apiKey;
        return $this;
    }

    /**
     * 타임아웃 설정
     */
    public function setTimeout(int $timeout): self {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Gemini Image 모델 사용 여부 (새 API)
     */
    private bool $useGeminiImage = true;

    /**
     * OpenAI 호환 모드 설정 (Legacy)
     */
    public function setUseOpenAICompat(bool $use): self {
        $this->useOpenAICompat = $use;
        return $this;
    }

    /**
     * Gemini Image 모드 설정
     */
    public function setUseGeminiImage(bool $use): self {
        $this->useGeminiImage = $use;
        return $this;
    }

    /**
     * 이미지 생성
     *
     * @param string $prompt 이미지 생성 프롬프트
     * @param array $options 옵션 (aspect_ratio, count, person_generation, safety_filter)
     * @return ImageResponse
     */
    public function generate(string $prompt, array $options = []): ImageResponse {
        $startTime = microtime(true);

        if (empty($this->apiKey)) {
            return ImageResponse::error(__('Gemini API 키가 설정되지 않았습니다.', 'ai-content-rewriter'));
        }

        // 메모리 체크
        if (!$this->checkMemoryAvailable()) {
            return ImageResponse::error(__('이미지 생성을 위한 메모리가 부족합니다.', 'ai-content-rewriter'));
        }

        try {
            // 1순위: Nano Banana Pro (Gemini 3 Pro Image) - 한글 텍스트 렌더링 최적
            if ($this->useGeminiImage) {
                error_log('[AICR Image] Using Nano Banana Pro (gemini-3-pro-image-preview) model');
                return $this->generateWithGeminiImage($prompt, $options, $startTime);
            }

            // 2순위: Legacy OpenAI 호환 엔드포인트
            if ($this->useOpenAICompat) {
                return $this->generateWithOpenAICompat($prompt, $options, $startTime);
            }

            // 3순위: Legacy Imagen predict 엔드포인트
            return $this->generateWithImagenPredict($prompt, $options, $startTime);

        } catch (\Exception $e) {
            $responseTime = microtime(true) - $startTime;
            $this->logError($e->getMessage());

            // Gemini Image 실패 시 Legacy로 폴백 시도
            if ($this->useGeminiImage) {
                try {
                    $this->logError('Gemini Image API 실패, Legacy OpenAI 호환 API로 폴백 시도...');
                    return $this->generateWithOpenAICompat($prompt, $options, $startTime);
                } catch (\Exception $fallbackException) {
                    $this->logError('OpenAI 호환 API도 실패: ' . $fallbackException->getMessage());

                    // 최종 폴백: Imagen predict
                    try {
                        $this->logError('Imagen predict로 최종 폴백 시도...');
                        return $this->generateWithImagenPredict($prompt, $options, $startTime);
                    } catch (\Exception $finalException) {
                        $this->logError('모든 API 실패: ' . $finalException->getMessage());
                    }
                }
            }

            return ImageResponse::error($e->getMessage(), $responseTime);
        }
    }

    /**
     * Nano Banana Pro (Gemini 3 Pro Image) API로 이미지 생성
     * 한글 텍스트 렌더링 향상, 최대 4K 해상도 지원
     */
    private function generateWithGeminiImage(string $prompt, array $options, float $startTime): ImageResponse {
        $aspectRatio = $options['aspect_ratio'] ?? '16:9';
        $imageSize = $options['image_size'] ?? '2K'; // 기본 2K, 4K 가능

        // Gemini Image API 페이로드 - 공식 문서 형식 준수
        $payload = [
            'contents' => [
                [
                    'role' => 'user',  // 중요: role 필드 필수
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseModalities' => ['TEXT', 'IMAGE'],  // TEXT도 포함해야 정확한 렌더링
                'imageConfig' => [
                    'aspectRatio' => $aspectRatio,
                ]
            ]
        ];

        // 2K/4K 해상도 설정 (Gemini 3 Pro만 지원)
        if (in_array($imageSize, ['2K', '4K'])) {
            $payload['generationConfig']['imageConfig']['imageSize'] = $imageSize;
        }

        $response = $this->requestWithRetry(self::GEMINI_IMAGE_API, $payload, false, true);
        return $this->parseGeminiImageResponse($response, $startTime);
    }

    /**
     * Gemini Image API 응답 파싱
     */
    private function parseGeminiImageResponse(array $response, float $startTime): ImageResponse {
        $responseTime = microtime(true) - $startTime;

        // 구조 검증
        if (!isset($response['candidates']) || !is_array($response['candidates']) || empty($response['candidates'])) {
            // 안전 필터 차단 확인
            if (isset($response['promptFeedback']['blockReason'])) {
                $reason = $response['promptFeedback']['blockReason'];
                throw new \Exception(sprintf(__('안전 필터에 의해 차단됨: %s', 'ai-content-rewriter'), $reason));
            }
            throw new \Exception(__('API 응답에 이미지 데이터가 없습니다.', 'ai-content-rewriter'));
        }

        $candidate = $response['candidates'][0];

        // finishReason 확인
        if (isset($candidate['finishReason']) && $candidate['finishReason'] !== 'STOP') {
            throw new \Exception(sprintf(__('이미지 생성 중단: %s', 'ai-content-rewriter'), $candidate['finishReason']));
        }

        // parts에서 이미지 데이터 추출
        if (!isset($candidate['content']['parts']) || !is_array($candidate['content']['parts'])) {
            throw new \Exception(__('API 응답 형식이 올바르지 않습니다.', 'ai-content-rewriter'));
        }

        foreach ($candidate['content']['parts'] as $part) {
            if (isset($part['inlineData'])) {
                $base64 = $part['inlineData']['data'];
                $mimeType = $part['inlineData']['mimeType'] ?? 'image/png';

                // Base64 유효성 검증
                $this->validateBase64Image($base64);

                return ImageResponse::success(
                    $base64,
                    $mimeType,
                    self::GEMINI_IMAGE_MODEL,
                    $responseTime,
                    $response
                );
            }
        }

        throw new \Exception(__('API 응답에서 이미지를 찾을 수 없습니다.', 'ai-content-rewriter'));
    }

    /**
     * OpenAI 호환 엔드포인트로 이미지 생성
     */
    private function generateWithOpenAICompat(string $prompt, array $options, float $startTime): ImageResponse {
        $aspectRatio = $options['aspect_ratio'] ?? '16:9';
        $size = self::ASPECT_RATIOS[$aspectRatio] ?? '1024x576';

        // 비율 정보를 프롬프트에 추가하여 더 정확한 결과 유도
        $aspectDescription = $this->getAspectRatioDescription($aspectRatio);
        $enhancedPrompt = $prompt . "\n\n[Image Format: {$aspectDescription}. Compose the image to fit this aspect ratio perfectly.]";

        // Imagen 4.0 모델 사용
        $payload = [
            'model' => self::IMAGEN_MODEL,
            'prompt' => $enhancedPrompt,
            'n' => 1,
            'size' => $size,
            'response_format' => 'b64_json',
        ];

        $response = $this->requestWithRetry(self::OPENAI_COMPAT_API, $payload, true);
        return $this->parseOpenAICompatResponse($response, $startTime);
    }

    /**
     * 비율에 대한 설명 텍스트 반환
     */
    private function getAspectRatioDescription(string $ratio): string {
        return match ($ratio) {
            '1:1' => 'Square format (1:1), 1024x1024 pixels',
            '3:4' => 'Portrait format (3:4), 768x1024 pixels, taller than wide',
            '4:3' => 'Landscape format (4:3), 1024x768 pixels, wider than tall',
            '9:16' => 'Vertical/Story format (9:16), 576x1024 pixels, very tall and narrow',
            '16:9' => 'Widescreen format (16:9), 1024x576 pixels, cinematic wide composition',
            default => 'Widescreen format (16:9), 1024x576 pixels',
        };
    }

    /**
     * Imagen predict 엔드포인트로 이미지 생성
     */
    private function generateWithImagenPredict(string $prompt, array $options, float $startTime): ImageResponse {
        $payload = [
            'instances' => [
                ['prompt' => $prompt]
            ],
            'parameters' => [
                'sampleCount' => 1,
                'aspectRatio' => $options['aspect_ratio'] ?? '16:9',
                'personGeneration' => $options['person_generation'] ?? 'DONT_ALLOW',
                'safetyFilterLevel' => $options['safety_filter'] ?? 'BLOCK_SOME',
            ]
        ];

        $response = $this->requestWithRetry(self::IMAGEN_PREDICT_API, $payload, false);
        return $this->parseImagenPredictResponse($response, $startTime);
    }

    /**
     * 재시도 로직이 포함된 요청
     *
     * @param string $url API 엔드포인트
     * @param array $payload 요청 데이터
     * @param bool $useBearer Bearer 토큰 사용 여부 (OpenAI 호환용)
     * @param bool $isGeminiImage Gemini Image API 여부 (x-goog-api-key 사용)
     * @return array 응답 데이터
     */
    private function requestWithRetry(string $url, array $payload, bool $useBearer = false, bool $isGeminiImage = false): array {
        $lastException = null;

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            try {
                // 헤더 구성
                $headers = ['Content-Type' => 'application/json'];

                if ($useBearer) {
                    // OpenAI 호환 엔드포인트: Bearer 토큰 사용
                    $headers['Authorization'] = 'Bearer ' . $this->apiKey;
                } else {
                    // Gemini Image / Imagen predict 엔드포인트: x-goog-api-key 헤더 사용
                    $headers['x-goog-api-key'] = $this->apiKey;
                }

                // 디버그 로깅 (요청 시작)
                if (get_option('aicr_debug_mode', false)) {
                    $apiType = $isGeminiImage ? 'Gemini Image (Nano Banana Pro)' : ($useBearer ? 'OpenAI Compatible' : 'Imagen Predict');
                    error_log('[AICR Image] API Type: ' . $apiType);
                    error_log('[AICR Image] Endpoint: ' . $url);
                }

                $response = wp_remote_post($url, [
                    'timeout' => $this->timeout,
                    'headers' => $headers,
                    'body' => wp_json_encode($payload),
                ]);

                if (is_wp_error($response)) {
                    throw new \Exception('API 연결 실패: ' . $response->get_error_message());
                }

                $code = wp_remote_retrieve_response_code($response);
                $bodyRaw = wp_remote_retrieve_body($response);
                $body = json_decode($bodyRaw, true);

                // 디버그 로깅
                if (get_option('aicr_debug_mode', false)) {
                    error_log('[AICR Image] Response code: ' . $code);
                    error_log('[AICR Image] Response (first 500 chars): ' . substr($bodyRaw, 0, 500));
                }

                // 성공
                if ($code === 200) {
                    return $body ?? [];
                }

                // 429 Rate Limit - 재시도
                if ($code === 429) {
                    $retryAfter = (int) wp_remote_retrieve_header($response, 'retry-after');
                    $delay = $retryAfter > 0 ? min($retryAfter, 60) : ($this->retryDelays[$attempt] ?? 10);
                    $this->logError("Rate limit hit, waiting {$delay} seconds before retry...");
                    sleep($delay);
                    continue;
                }

                // 5xx 서버 에러 - 재시도
                if ($code >= 500 && $attempt < $this->maxRetries) {
                    $this->logError("Server error {$code}, retrying...");
                    sleep($this->retryDelays[$attempt] ?? 10);
                    continue;
                }

                // 4xx 클라이언트 에러 - 상세 에러 메시지 추출
                $errorMessage = $this->extractErrorMessage($body, $code);
                throw new \Exception($errorMessage);

            } catch (\Exception $e) {
                $lastException = $e;

                if ($attempt < $this->maxRetries && $this->isRetryableError($e->getMessage())) {
                    $this->logError("Attempt {$attempt} failed: " . $e->getMessage() . ", retrying...");
                    sleep($this->retryDelays[$attempt] ?? 10);
                    continue;
                }
            }
        }

        throw $lastException ?? new \Exception(__('최대 재시도 횟수 초과', 'ai-content-rewriter'));
    }

    /**
     * OpenAI 호환 응답 파싱
     */
    private function parseOpenAICompatResponse(array $response, float $startTime): ImageResponse {
        $responseTime = microtime(true) - $startTime;

        // 구조 검증
        if (!isset($response['data']) || !is_array($response['data']) || empty($response['data'])) {
            throw new \Exception(__('API 응답에 이미지 데이터가 없습니다.', 'ai-content-rewriter'));
        }

        $imageData = $response['data'][0];

        if (!isset($imageData['b64_json'])) {
            throw new \Exception(__('API 응답에 Base64 이미지 데이터가 없습니다.', 'ai-content-rewriter'));
        }

        $base64 = $imageData['b64_json'];

        // Base64 유효성 검증
        $this->validateBase64Image($base64);

        return ImageResponse::success(
            $base64,
            'image/png',
            self::IMAGEN_MODEL,
            $responseTime,
            $response
        );
    }

    /**
     * Imagen predict 응답 파싱
     */
    private function parseImagenPredictResponse(array $response, float $startTime): ImageResponse {
        $responseTime = microtime(true) - $startTime;

        // 구조 검증
        if (!isset($response['predictions']) || !is_array($response['predictions'])) {
            throw new \Exception(__('잘못된 API 응답 형식: predictions 필드 누락', 'ai-content-rewriter'));
        }

        if (empty($response['predictions'])) {
            throw new \Exception(__('API가 이미지를 생성하지 못했습니다.', 'ai-content-rewriter'));
        }

        $prediction = $response['predictions'][0];

        if (!isset($prediction['bytesBase64Encoded'])) {
            // 안전 필터 차단 확인
            if (isset($prediction['safetyAttributes']['blocked']) && $prediction['safetyAttributes']['blocked']) {
                throw new \Exception(__('안전 필터에 의해 이미지가 차단되었습니다.', 'ai-content-rewriter'));
            }
            throw new \Exception(__('API 응답에 이미지 데이터가 없습니다.', 'ai-content-rewriter'));
        }

        $base64 = $prediction['bytesBase64Encoded'];

        // Base64 유효성 검증
        $this->validateBase64Image($base64);

        $mimeType = $prediction['mimeType'] ?? 'image/png';

        return ImageResponse::success(
            $base64,
            $mimeType,
            self::IMAGEN_MODEL,
            $responseTime,
            $response
        );
    }

    /**
     * Base64 이미지 데이터 검증
     */
    private function validateBase64Image(string $base64): void {
        // Base64 유효성 검증
        if (!preg_match('/^[A-Za-z0-9+\/=]+$/', $base64)) {
            throw new \Exception(__('잘못된 Base64 인코딩', 'ai-content-rewriter'));
        }

        // 디코딩 검증
        $decoded = base64_decode($base64, true);
        if ($decoded === false) {
            throw new \Exception(__('Base64 디코딩 실패', 'ai-content-rewriter'));
        }

        // PNG 또는 JPEG 시그니처 확인
        $isPng = substr($decoded, 0, 8) === "\x89PNG\r\n\x1a\n";
        $isJpeg = substr($decoded, 0, 2) === "\xff\xd8";

        if (!$isPng && !$isJpeg) {
            throw new \Exception(__('유효한 이미지 파일이 아닙니다.', 'ai-content-rewriter'));
        }

        // 메모리 해제
        unset($decoded);
    }

    /**
     * 에러 메시지 추출
     */
    private function extractErrorMessage(array $body, int $code): string {
        // OpenAI 호환 형식
        if (isset($body['error']['message'])) {
            return $body['error']['message'];
        }

        // Google API 형식
        if (isset($body['error']['status'])) {
            $status = $body['error']['status'];
            $message = $body['error']['message'] ?? '';
            return "{$status}: {$message}";
        }

        return sprintf(__('API 에러 (HTTP %d)', 'ai-content-rewriter'), $code);
    }

    /**
     * 재시도 가능한 에러인지 확인
     */
    private function isRetryableError(string $message): bool {
        $retryablePatterns = [
            'timeout',
            'connection',
            'temporarily unavailable',
            'server error',
            'rate limit',
            '503',
            '502',
            '504',
        ];

        $lowerMessage = strtolower($message);
        foreach ($retryablePatterns as $pattern) {
            if (strpos($lowerMessage, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 메모리 가용량 체크
     */
    private function checkMemoryAvailable(): bool {
        $memoryLimit = $this->getMemoryLimitBytes();
        $currentUsage = memory_get_usage(true);
        $required = 50 * 1024 * 1024; // 50MB 여유 필요

        return ($memoryLimit - $currentUsage) > $required;
    }

    /**
     * PHP 메모리 제한을 바이트로 변환
     */
    private function getMemoryLimitBytes(): int {
        $limit = ini_get('memory_limit');

        if ($limit === '-1') {
            return PHP_INT_MAX;
        }

        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    /**
     * 에러 로깅
     */
    private function logError(string $message): void {
        if (get_option('aicr_debug_mode', false)) {
            error_log('[AICR Image Generation] Error: ' . $message);
        }
    }

    /**
     * API 연결 테스트
     */
    public function testConnection(): bool {
        if (empty($this->apiKey)) {
            return false;
        }

        try {
            // 모델 목록 조회로 API 키 유효성 확인 (헤더 방식)
            $url = 'https://generativelanguage.googleapis.com/v1beta/models';

            $response = wp_remote_get($url, [
                'timeout' => 10,
                'headers' => [
                    'x-goog-api-key' => $this->apiKey,
                ],
            ]);

            if (is_wp_error($response)) {
                return false;
            }

            return wp_remote_retrieve_response_code($response) === 200;

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 이미지 생성 모델 사용 가능 여부 테스트
     *
     * @return array ['success' => bool, 'error' => string|null, 'endpoint_used' => string]
     */
    public function testImagenAvailability(): array {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'error' => __('API 키가 설정되지 않았습니다.', 'ai-content-rewriter'),
            ];
        }

        // 먼저 API 키 유효성 확인
        if (!$this->testConnection()) {
            return [
                'success' => false,
                'error' => __('Gemini API 키가 유효하지 않습니다. 설정 페이지에서 API 키를 확인해주세요.', 'ai-content-rewriter'),
            ];
        }

        // 이미지 모델 사용 가능 여부 확인
        $modelCheck = $this->checkImageModelAccess();
        if (!$modelCheck['available']) {
            return [
                'success' => false,
                'error' => $modelCheck['message'],
            ];
        }

        try {
            // 간단한 테스트 이미지 생성 요청
            $response = $this->generate('A simple blue square for testing', [
                'aspect_ratio' => '1:1',
            ]);

            if ($response->isSuccess()) {
                $endpointUsed = $this->useGeminiImage ? 'Nano Banana Pro (gemini-3-pro-image)' :
                    ($this->useOpenAICompat ? 'OpenAI Compatible' : 'Imagen Predict');
                return [
                    'success' => true,
                    'message' => __('이미지 생성 API가 정상적으로 작동합니다.', 'ai-content-rewriter'),
                    'response_time' => round($response->getResponseTime(), 2),
                    'endpoint_used' => $endpointUsed,
                    'model' => $response->getModel(),
                ];
            }

            // 모델 접근 불가 에러 확인
            $errorMsg = $response->getErrorMessage();
            if (strpos($errorMsg, 'not found') !== false || strpos($errorMsg, 'not supported') !== false) {
                return [
                    'success' => false,
                    'error' => __('이미지 생성 모델에 접근할 수 없습니다. Google AI Studio에서 Gemini Image API를 활성화했는지 확인해주세요.', 'ai-content-rewriter'),
                ];
            }

            return [
                'success' => false,
                'error' => $errorMsg,
            ];

        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();

            // 모델 접근 불가 에러 친화적 메시지
            if (strpos($errorMsg, 'not found') !== false || strpos($errorMsg, 'not supported') !== false) {
                return [
                    'success' => false,
                    'error' => __('이미지 생성 모델에 접근할 수 없습니다. Google AI Studio에서 Gemini Image API를 활성화했는지 확인해주세요.', 'ai-content-rewriter'),
                ];
            }

            return [
                'success' => false,
                'error' => $errorMsg,
            ];
        }
    }

    /**
     * 이미지 모델 접근 가능 여부 확인
     * Gemini 3 Pro Image (Nano Banana Pro) 또는 Imagen 모델
     *
     * @return array ['available' => bool, 'message' => string]
     */
    private function checkImageModelAccess(): array {
        try {
            // 모델 목록 조회
            $url = 'https://generativelanguage.googleapis.com/v1beta/models';

            $response = wp_remote_get($url, [
                'timeout' => 10,
                'headers' => [
                    'x-goog-api-key' => $this->apiKey,
                ],
            ]);

            if (is_wp_error($response)) {
                return ['available' => true, 'message' => '확인 불가']; // 계속 진행
            }

            $code = wp_remote_retrieve_response_code($response);
            if ($code !== 200) {
                return ['available' => true, 'message' => '확인 불가']; // 계속 진행
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            $models = $body['models'] ?? [];

            // Gemini Image 또는 Imagen 모델이 있는지 확인
            $imageModels = array_filter($models, function ($model) {
                if (!isset($model['name'])) {
                    return false;
                }
                $name = $model['name'];
                // gemini-*-image 또는 imagen-* 모델 확인
                return strpos($name, 'gemini') !== false && strpos($name, 'image') !== false
                    || strpos($name, 'imagen') !== false;
            });

            if (empty($imageModels)) {
                return [
                    'available' => false,
                    'message' => __('API 키에 이미지 생성 모델 접근 권한이 없습니다. Google AI Studio에서 Gemini Image API를 활성화해야 합니다.', 'ai-content-rewriter'),
                ];
            }

            // 사용 가능한 모델명 로깅
            $modelNames = array_column($imageModels, 'name');
            error_log('[AICR Image] Available image models: ' . implode(', ', $modelNames));

            return ['available' => true, 'message' => '이미지 모델 접근 가능'];

        } catch (\Exception $e) {
            return ['available' => true, 'message' => '확인 실패']; // 계속 진행
        }
    }
}
