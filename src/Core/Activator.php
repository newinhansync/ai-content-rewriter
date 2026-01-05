<?php
/**
 * Plugin Activator
 *
 * @package AIContentRewriter\Core
 */

namespace AIContentRewriter\Core;

use AIContentRewriter\Database\Schema;
use AIContentRewriter\RSS\FeedScheduler;

/**
 * 플러그인 활성화 시 실행되는 클래스
 */
class Activator {
    /**
     * 플러그인 활성화 처리
     */
    public static function activate(): void {
        // PHP 버전 체크
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            deactivate_plugins(AICR_PLUGIN_BASENAME);
            wp_die(
                __('AI Content Rewriter requires PHP 8.0 or higher.', 'ai-content-rewriter'),
                __('Plugin Activation Error', 'ai-content-rewriter'),
                ['back_link' => true]
            );
        }

        // WordPress 버전 체크
        if (version_compare(get_bloginfo('version'), '6.0', '<')) {
            deactivate_plugins(AICR_PLUGIN_BASENAME);
            wp_die(
                __('AI Content Rewriter requires WordPress 6.0 or higher.', 'ai-content-rewriter'),
                __('Plugin Activation Error', 'ai-content-rewriter'),
                ['back_link' => true]
            );
        }

        // 데이터베이스 테이블 생성
        Schema::create_tables();

        // 기본 옵션 설정
        self::set_default_options();

        // RSS 기본 옵션 설정
        self::set_rss_default_options();

        // RSS 스케줄러 이벤트 등록
        self::schedule_rss_events();

        // 캐시 및 리라이트 규칙 갱신
        flush_rewrite_rules();

        // 버전 저장
        update_option('aicr_version', AICR_VERSION);

        // 활성화 시간 기록
        update_option('aicr_activated_at', current_time('mysql'));
    }

    /**
     * 기본 옵션 설정
     */
    private static function set_default_options(): void {
        $defaults = [
            'aicr_default_ai_provider' => 'chatgpt',
            'aicr_default_language' => 'ko',
            'aicr_auto_publish' => false,
            'aicr_default_post_status' => 'draft',
            'aicr_chunk_size' => 3000,
            'aicr_api_keys' => [],
        ];

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }

        // 기본 프롬프트 템플릿 설정
        self::set_default_prompts();
    }

    /**
     * 기본 프롬프트 템플릿 설정
     */
    private static function set_default_prompts(): void {
        $default_prompts = [
            'content_rewrite' => '다음 콘텐츠를 SEO 최적화된 블로그 포스트로 재작성해주세요. 원본의 핵심 정보를 유지하면서 독창적이고 매력적인 글로 변환해주세요.

원본 콘텐츠:
{{content}}

요구사항:
- 자연스러운 {{target_language}} 문장으로 작성
- SEO 친화적인 제목 포함
- 적절한 소제목으로 구조화
- 핵심 키워드 자연스럽게 포함',

            'translate' => '다음 텍스트를 {{target_language}}로 번역해주세요. 자연스러운 표현을 사용하고 원문의 의미를 정확히 전달해주세요.

원문:
{{content}}',

            'metadata' => '다음 블로그 포스트에 대해 SEO 메타데이터를 생성해주세요.

포스트 내용:
{{content}}

다음 형식으로 JSON을 반환해주세요:
{
  "meta_title": "SEO 최적화된 제목 (60자 이내)",
  "meta_description": "메타 설명 (160자 이내)",
  "keywords": ["키워드1", "키워드2", "키워드3"],
  "tags": ["태그1", "태그2"]
}',
        ];

        if (get_option('aicr_prompt_templates') === false) {
            add_option('aicr_prompt_templates', $default_prompts);
        }
    }

    /**
     * RSS 기본 옵션 설정
     */
    private static function set_rss_default_options(): void {
        $defaults = [
            'aicr_rss_fetch_interval' => 60,          // 분 단위 기본 갱신 주기
            'aicr_rss_max_items' => 20,               // 피드당 최대 아이템 수
            'aicr_rss_auto_cleanup' => true,          // 자동 정리 활성화
            'aicr_rss_retention_days' => 30,          // 아이템 보관 기간 (일)
            'aicr_rss_concurrent_fetch' => 5,         // 동시 가져오기 피드 수
            'aicr_rss_default_auto_rewrite' => false, // 기본 자동 재작성 설정
            'aicr_rss_default_auto_publish' => false, // 기본 자동 발행 설정
            'aicr_rss_rewrite_queue_limit' => 10,     // 재작성 큐 처리 제한
        ];

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }

    /**
     * RSS 스케줄러 이벤트 등록
     */
    private static function schedule_rss_events(): void {
        $scheduler = new FeedScheduler();
        $scheduler->schedule_events();
    }
}
