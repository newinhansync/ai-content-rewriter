<?php
/**
 * AI Adapter Interface
 *
 * @package AIContentRewriter\AI
 */

namespace AIContentRewriter\AI;

/**
 * AI 서비스 어댑터 인터페이스
 */
interface AIAdapterInterface {
    /**
     * AI 제공자 이름 반환
     *
     * @return string
     */
    public function get_provider_name(): string;

    /**
     * API 키 설정
     *
     * @param string $api_key API 키
     * @return self
     */
    public function set_api_key(string $api_key): self;

    /**
     * 모델 설정
     *
     * @param string $model 모델 ID
     * @return self
     */
    public function set_model(string $model): self;

    /**
     * 사용 가능한 모델 목록 반환
     *
     * @return array<string, string> 모델 ID => 모델 이름
     */
    public function get_available_models(): array;

    /**
     * 텍스트 생성 요청
     *
     * @param string $prompt 프롬프트
     * @param array  $options 추가 옵션
     * @return AIResponse
     * @throws AIException API 오류 시
     */
    public function generate(string $prompt, array $options = []): AIResponse;

    /**
     * 스트리밍 텍스트 생성 (콜백 방식)
     *
     * @param string   $prompt 프롬프트
     * @param callable $callback 청크별 콜백 함수
     * @param array    $options 추가 옵션
     * @return AIResponse
     * @throws AIException API 오류 시
     */
    public function generate_stream(string $prompt, callable $callback, array $options = []): AIResponse;

    /**
     * API 연결 테스트
     *
     * @return bool
     */
    public function test_connection(): bool;

    /**
     * 토큰 수 추정
     *
     * @param string $text 텍스트
     * @return int 추정 토큰 수
     */
    public function estimate_tokens(string $text): int;
}
