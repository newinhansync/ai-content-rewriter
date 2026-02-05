<?php
/**
 * Content Rewriter Service
 *
 * @package AIContentRewriter\Content
 */

namespace AIContentRewriter\Content;

use AIContentRewriter\AI\AIFactory;
use AIContentRewriter\AI\AIAdapterInterface;
use AIContentRewriter\AI\AIResponse;
use AIContentRewriter\AI\AIException;

/**
 * 콘텐츠 변환 서비스 클래스
 */
class ContentRewriter {
    /**
     * AI 어댑터
     */
    private AIAdapterInterface $ai_adapter;

    /**
     * 콘텐츠 청커
     */
    private ContentChunker $chunker;

    /**
     * 프롬프트 매니저
     */
    private PromptManager $prompt_manager;

    /**
     * 콘텐츠 추출기
     */
    private ContentExtractor $extractor;

    /**
     * 대상 언어
     */
    private string $target_language = 'ko';

    /**
     * 생성자
     */
    public function __construct(?string $ai_provider = null) {
        $this->ai_adapter = $ai_provider
            ? AIFactory::create($ai_provider)
            : AIFactory::get_default();
        $this->chunker = new ContentChunker();
        $this->prompt_manager = PromptManager::get_instance();
        $this->extractor = new ContentExtractor();
    }

    /**
     * AI 제공자 설정
     */
    public function set_ai_provider(string $provider): self {
        $this->ai_adapter = AIFactory::create($provider);
        return $this;
    }

    /**
     * 대상 언어 설정
     */
    public function set_target_language(string $language): self {
        $this->target_language = $language;
        return $this;
    }

    /**
     * URL에서 콘텐츠 추출 후 변환
     */
    public function rewrite_from_url(string $url, array $options = []): RewriteResult {
        // URL에서 콘텐츠 추출
        $extract_result = $this->extractor->extract_from_url($url);

        if (!$extract_result->is_success()) {
            return RewriteResult::error($extract_result->get_error_message());
        }

        return $this->rewrite_content(
            $extract_result->get_content(),
            array_merge($options, [
                'source_url' => $url,
                'source_title' => $extract_result->get_title(),
                'source_metadata' => $extract_result->get_metadata(),
            ])
        );
    }

    /**
     * 텍스트 콘텐츠 변환
     */
    public function rewrite_content(string $content, array $options = []): RewriteResult {
        $start_time = microtime(true);

        try {
            $template_type = $options['template_type'] ?? 'content_rewrite';
            $custom_template_id = $options['template_id'] ?? null;

            // blog_post 템플릿이 없으면 content_rewrite로 폴백
            try {
                $this->prompt_manager->get_default_template($template_type);
            } catch (\InvalidArgumentException $e) {
                $template_type = 'content_rewrite';
            }

            // 청킹이 필요한지 확인
            if ($this->chunker->needs_chunking($content)) {
                return $this->rewrite_chunked_content($content, $options);
            }

            // 단일 콘텐츠 처리
            $prompt = $this->prompt_manager->build_prompt(
                $template_type,
                [
                    'content' => $content,
                    'target_language' => $this->get_language_name($this->target_language),
                    'title' => $options['source_title'] ?? '',
                    'source_url' => $options['source_url'] ?? '',
                ],
                $custom_template_id
            );

            $ai_response = $this->ai_adapter->generate($prompt, $options['ai_options'] ?? []);

            if (!$ai_response->is_success()) {
                return RewriteResult::error($ai_response->get_error_message());
            }

            $processing_time = microtime(true) - $start_time;

            // 결과 로깅
            $this->log_rewrite($content, $ai_response, $options);

            return RewriteResult::success(
                $ai_response->get_content(),
                [
                    'tokens_used' => $ai_response->get_total_tokens(),
                    'processing_time' => $processing_time,
                    'ai_provider' => $this->ai_adapter->get_provider_name(),
                    'model' => $ai_response->get_model(),
                    'source_url' => $options['source_url'] ?? null,
                ]
            );

        } catch (AIException $e) {
            return RewriteResult::error($e->getMessage(), $e->get_error_code());
        }
    }

    /**
     * 청크 단위로 콘텐츠 변환
     */
    private function rewrite_chunked_content(string $content, array $options): RewriteResult {
        $start_time = microtime(true);
        $chunks = $this->chunker->chunk($content);
        $total_tokens = 0;
        $processed_chunks = [];

        try {
            foreach ($chunks as $chunk) {
                $chunk->set_status('processing');

                $prompt = $this->prompt_manager->build_prompt(
                    'chunk_continuation',
                    [
                        'content' => $chunk->get_content(),
                        'target_language' => $this->get_language_name($this->target_language),
                        'chunk_index' => $chunk->get_index() + 1,
                        'chunk_total' => $chunk->get_total(),
                        'is_first' => $chunk->is_first() ? 'true' : '',
                        'is_last' => $chunk->is_last() ? 'true' : '',
                    ]
                );

                $ai_response = $this->ai_adapter->generate($prompt, $options['ai_options'] ?? []);

                if (!$ai_response->is_success()) {
                    $chunk->set_status('failed');
                    throw new AIException(
                        sprintf(
                            __('청크 %d/%d 처리 실패: %s', 'ai-content-rewriter'),
                            $chunk->get_index() + 1,
                            $chunk->get_total(),
                            $ai_response->get_error_message()
                        )
                    );
                }

                $chunk->set_processed_content($ai_response->get_content());
                $chunk->set_status('completed');
                $chunk->add_metadata('tokens', $ai_response->get_total_tokens());

                $total_tokens += $ai_response->get_total_tokens();
                $processed_chunks[] = $chunk;
            }

            // 청크 병합
            $merged_content = $this->chunker->merge($processed_chunks);
            $processing_time = microtime(true) - $start_time;

            // 결과 로깅
            $this->log_rewrite($content, null, array_merge($options, [
                'chunked' => true,
                'chunk_count' => count($chunks),
                'total_tokens' => $total_tokens,
            ]));

            return RewriteResult::success(
                $merged_content,
                [
                    'tokens_used' => $total_tokens,
                    'processing_time' => $processing_time,
                    'ai_provider' => $this->ai_adapter->get_provider_name(),
                    'chunk_count' => count($chunks),
                    'source_url' => $options['source_url'] ?? null,
                ]
            );

        } catch (AIException $e) {
            return RewriteResult::error($e->getMessage(), $e->get_error_code());
        }
    }

    /**
     * SEO 메타데이터 생성
     */
    public function generate_metadata(string $content): array {
        $prompt = $this->prompt_manager->build_prompt('metadata', [
            'content' => mb_substr($content, 0, 3000), // 메타데이터용은 앞부분만
        ]);

        try {
            $response = $this->ai_adapter->generate($prompt, [
                'temperature' => 0.3, // 더 일관된 결과를 위해 낮은 temperature
            ]);

            if (!$response->is_success()) {
                return [];
            }

            // JSON 추출
            $result = $response->get_content();
            preg_match('/```json\s*(.*?)\s*```/s', $result, $matches);

            if (!empty($matches[1])) {
                $metadata = json_decode($matches[1], true);
                if ($metadata) {
                    return $metadata;
                }
            }

            // JSON 블록이 없으면 전체를 파싱 시도
            $metadata = json_decode($result, true);
            return $metadata ?: [];

        } catch (AIException $e) {
            return [];
        }
    }

    /**
     * 콘텐츠 번역
     */
    public function translate(string $content, string $target_language): RewriteResult {
        $this->target_language = $target_language;

        return $this->rewrite_content($content, [
            'template_type' => 'translate',
        ]);
    }

    /**
     * 언어 코드를 언어 이름으로 변환
     */
    private function get_language_name(string $code): string {
        $languages = [
            'ko' => '한국어',
            'en' => 'English',
            'ja' => '日本語',
            'zh' => '中文',
            'es' => 'Español',
            'fr' => 'Français',
            'de' => 'Deutsch',
        ];

        return $languages[$code] ?? $code;
    }

    /**
     * 변환 이력 로깅
     */
    private function log_rewrite(string $source_content, ?AIResponse $response, array $options): void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aicr_history';

        $wpdb->insert($table_name, [
            'user_id' => get_current_user_id(),
            'source_type' => isset($options['source_url']) ? 'url' : 'text',
            'source_url' => $options['source_url'] ?? null,
            'source_content' => mb_substr($source_content, 0, 65535),
            'result_content' => $response ? mb_substr($response->get_content(), 0, 65535) : null,
            'ai_provider' => $this->ai_adapter->get_provider_name(),
            'ai_model' => $response?->get_model(),
            'tokens_used' => $options['total_tokens'] ?? $response?->get_total_tokens() ?? 0,
            'processing_time' => $options['processing_time'] ?? $response?->get_response_time() ?? 0,
            'status' => $response?->is_success() ? 'completed' : 'pending',
            'metadata' => wp_json_encode([
                'chunked' => $options['chunked'] ?? false,
                'chunk_count' => $options['chunk_count'] ?? 1,
                'template_type' => $options['template_type'] ?? 'content_rewrite',
            ]),
        ]);
    }
}
