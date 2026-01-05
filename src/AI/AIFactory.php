<?php
/**
 * AI Factory
 *
 * @package AIContentRewriter\AI
 */

namespace AIContentRewriter\AI;

/**
 * AI 어댑터 팩토리 클래스
 */
class AIFactory {
    /**
     * 지원되는 제공자 목록
     */
    private const PROVIDERS = [
        'chatgpt' => ChatGPTAdapter::class,
        'gemini' => GeminiAdapter::class,
    ];

    /**
     * 어댑터 인스턴스 캐시
     */
    private static array $instances = [];

    /**
     * AI 어댑터 생성
     *
     * @param string $provider 제공자 ID (chatgpt, gemini)
     * @return AIAdapterInterface
     * @throws AIException 지원하지 않는 제공자
     */
    public static function create(string $provider): AIAdapterInterface {
        $provider = strtolower($provider);

        if (!isset(self::PROVIDERS[$provider])) {
            throw new AIException(
                sprintf(__('지원하지 않는 AI 제공자: %s', 'ai-content-rewriter'), $provider),
                'UNSUPPORTED_PROVIDER'
            );
        }

        // 캐시된 인스턴스 반환
        if (isset(self::$instances[$provider])) {
            return self::$instances[$provider];
        }

        // 새 인스턴스 생성
        $adapter_class = self::PROVIDERS[$provider];
        $adapter = new $adapter_class();

        // API 키 설정
        $api_key = self::get_api_key($provider);
        if ($api_key) {
            $adapter->set_api_key($api_key);
        }

        // 기본 모델 설정
        $default_model = self::get_default_model($provider);
        if ($default_model) {
            $adapter->set_model($default_model);
        }

        self::$instances[$provider] = $adapter;

        return $adapter;
    }

    /**
     * 기본 제공자 어댑터 반환
     */
    public static function get_default(): AIAdapterInterface {
        $default_provider = get_option('aicr_default_ai_provider', 'chatgpt');
        return self::create($default_provider);
    }

    /**
     * 지원되는 제공자 목록 반환
     */
    public static function get_providers(): array {
        return [
            'chatgpt' => [
                'name' => 'ChatGPT',
                'description' => 'OpenAI GPT-4o 모델',
                'models' => (new ChatGPTAdapter())->get_available_models(),
            ],
            'gemini' => [
                'name' => 'Gemini',
                'description' => 'Google Gemini 3 모델',
                'models' => (new GeminiAdapter())->get_available_models(),
            ],
        ];
    }

    /**
     * API 키 조회 (복호화)
     */
    private static function get_api_key(string $provider): string {
        $option_key = "aicr_{$provider}_api_key";

        // 암호화 클래스 사용
        if (class_exists(\AIContentRewriter\Security\Encryption::class)) {
            return \AIContentRewriter\Security\Encryption::get_api_key($option_key);
        }

        // 폴백: 평문 반환 (마이그레이션 지원)
        return get_option($option_key, '');
    }

    /**
     * 기본 모델 조회
     */
    private static function get_default_model(string $provider): string {
        $option_key = "aicr_{$provider}_default_model";
        return get_option($option_key, '');
    }

    /**
     * 캐시 초기화
     */
    public static function clear_cache(): void {
        self::$instances = [];
    }
}
