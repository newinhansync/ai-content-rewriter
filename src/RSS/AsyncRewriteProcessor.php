<?php
/**
 * Async Rewrite Processor
 *
 * 비동기 재작성 처리를 위한 클래스
 * 타임아웃 문제 해결을 위해 백그라운드 처리 + 폴링 방식 사용
 *
 * @package AIContentRewriter\RSS
 */

namespace AIContentRewriter\RSS;

use AIContentRewriter\Content\ContentExtractor;
use AIContentRewriter\Content\PromptManager;
use AIContentRewriter\AI\AIFactory;
use AIContentRewriter\AI\AIException;

/**
 * 비동기 재작성 프로세서
 */
class AsyncRewriteProcessor {
    /**
     * 작업 상태 상수
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_EXTRACTING = 'extracting';
    public const STATUS_REWRITING = 'rewriting';
    public const STATUS_PUBLISHING = 'publishing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    /**
     * 트랜시언트 접두사
     */
    private const TRANSIENT_PREFIX = 'aicr_task_';

    /**
     * 작업 만료 시간 (초)
     */
    private const TASK_EXPIRATION = 3600; // 1시간

    /**
     * 피드 아이템 저장소
     */
    private FeedItemRepository $item_repository;

    /**
     * 생성자
     */
    public function __construct() {
        $this->item_repository = new FeedItemRepository();
    }

    /**
     * 재작성 작업 시작
     *
     * @param int $item_id 피드 아이템 ID
     * @param array $options 옵션
     * @return string 작업 ID
     */
    public function start_task(int $item_id, array $options = []): string {
        $task_id = $this->generate_task_id();

        $task_data = [
            'task_id' => $task_id,
            'item_id' => $item_id,
            'options' => $options,
            'status' => self::STATUS_PENDING,
            'step' => 'waiting',
            'progress' => 0,
            'message' => '작업 대기 중...',
            'result' => null,
            'error' => null,
            'created_at' => time(),
            'updated_at' => time(),
        ];

        // 작업 데이터 저장
        $this->save_task($task_id, $task_data);

        // 백그라운드 처리 시작
        $this->trigger_background_process($task_id);

        return $task_id;
    }

    /**
     * 작업 상태 확인
     *
     * @param string $task_id 작업 ID
     * @return array|null 작업 데이터
     */
    public function get_task_status(string $task_id): ?array {
        return $this->get_task($task_id);
    }

    /**
     * 백그라운드 처리 트리거
     *
     * @param string $task_id 작업 ID
     */
    private function trigger_background_process(string $task_id): void {
        // 비동기 HTTP 요청으로 백그라운드 처리 시작
        $url = admin_url('admin-ajax.php');

        $args = [
            'timeout' => 0.01, // 즉시 반환 (non-blocking)
            'blocking' => false,
            'sslverify' => apply_filters('https_local_ssl_verify', false),
            'body' => [
                'action' => 'aicr_process_rewrite_background',
                'task_id' => $task_id,
                'nonce' => wp_create_nonce('aicr_background_' . $task_id),
            ],
            'cookies' => $_COOKIE,
        ];

        wp_remote_post($url, $args);
    }

    /**
     * 백그라운드에서 재작성 처리 (AJAX 핸들러에서 호출)
     *
     * @param string $task_id 작업 ID
     */
    public function process_task(string $task_id): void {
        // 클라이언트 연결 끊어져도 계속 실행
        ignore_user_abort(true);

        // 타임아웃 설정 (무제한 - 긴 콘텐츠 생성 지원)
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        // 버퍼링 비활성화 및 클라이언트 연결 종료
        if (function_exists('fastcgi_finish_request')) {
            // Nginx + PHP-FPM 환경
            fastcgi_finish_request();
        } else {
            // Apache 환경
            if (!headers_sent()) {
                header('Connection: close');
                header('Content-Length: 0');
            }
            if (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
        }

        $task = $this->get_task($task_id);

        if (!$task) {
            return;
        }

        // 이미 처리 중이거나 완료된 경우
        if (!in_array($task['status'], [self::STATUS_PENDING], true)) {
            return;
        }

        try {
            // Step 1: 콘텐츠 추출
            $this->update_task_progress($task_id, self::STATUS_EXTRACTING, 'extracting', 10, '원본 콘텐츠 추출 중...');

            $item = $this->item_repository->find($task['item_id']);
            if (!$item) {
                throw new \Exception('피드 아이템을 찾을 수 없습니다.');
            }

            $extractor = new ContentExtractor();
            $content_result = $extractor->extract_from_url($item->get_link());

            if (!$content_result->is_success()) {
                throw new \Exception('콘텐츠 추출 실패: ' . $content_result->get_error());
            }

            $this->update_task_progress($task_id, self::STATUS_EXTRACTING, 'extracting', 30, '콘텐츠 추출 완료, AI 재작성 준비 중...');

            // Step 2: AI 재작성
            $this->update_task_progress($task_id, self::STATUS_REWRITING, 'rewriting', 40, 'AI가 콘텐츠를 재작성하고 있습니다...');

            $options = $task['options'];
            $ai_provider = get_option('aicr_default_ai_provider', 'chatgpt');
            $ai = AIFactory::create($ai_provider);

            // 프롬프트 생성
            $prompt_manager = new PromptManager();
            $template_id = $options['template_id'] ?? 0;
            $language = $options['language'] ?? 'ko';

            $prompt = $prompt_manager->build_prompt(
                'blog_post',
                [
                    'content' => $content_result->get_content(),
                    'title' => $content_result->get_title(),
                    'target_language' => $language,
                    'source_url' => $item->get_link(),
                ],
                $template_id ?: null
            );

            // AI 응답 생성 (긴 콘텐츠를 위해 높은 토큰 제한)
            $ai_response = $ai->generate($prompt, [
                'max_completion_tokens' => 8192,
            ]);

            if (!$ai_response->is_success()) {
                throw new AIException('AI 생성 실패: ' . $ai_response->get_error());
            }

            $ai_raw_content = $ai_response->get_content();

            // AI 응답에서 JSON 파싱
            $parsed_content = $this->parse_ai_response($ai_raw_content);

            $this->update_task_progress($task_id, self::STATUS_REWRITING, 'rewriting', 70, 'AI 재작성 완료, 게시글 생성 중...');

            // Step 3: 게시글 생성
            $this->update_task_progress($task_id, self::STATUS_PUBLISHING, 'publishing', 80, '워드프레스 게시글 생성 중...');

            $post_data = [
                'post_title' => $parsed_content['post_title'] ?? $content_result->get_title(),
                'post_content' => $parsed_content['post_content'] ?? $ai_raw_content,
                'post_excerpt' => $parsed_content['excerpt'] ?? '',
                'post_status' => $options['post_status'] ?? 'draft',
                'post_author' => get_current_user_id(),
                'post_type' => 'post',
            ];

            // 카테고리 설정 (수동 선택 > AI 추천 > 기본값)
            $category_id = $this->resolve_category($options['category'] ?? null, $parsed_content['category_suggestion'] ?? null);
            if ($category_id) {
                $post_data['post_category'] = [$category_id];
            }

            $post_id = wp_insert_post($post_data, true);

            if (is_wp_error($post_id)) {
                throw new \Exception('게시글 생성 실패: ' . $post_id->get_error_message());
            }

            // 원본 정보 메타 저장
            $metadata = $content_result->get_metadata();
            update_post_meta($post_id, '_aicr_source_url', $metadata['source_url'] ?? $item->get_link());
            update_post_meta($post_id, '_aicr_source_title', $content_result->get_title());
            update_post_meta($post_id, '_aicr_feed_item_id', $item->get_id());
            update_post_meta($post_id, '_aicr_ai_provider', $ai_provider);
            update_post_meta($post_id, '_aicr_rewritten_at', current_time('mysql'));

            // SEO 메타 저장
            if (!empty($parsed_content['meta_title'])) {
                update_post_meta($post_id, '_aicr_meta_title', $parsed_content['meta_title']);
            }
            if (!empty($parsed_content['meta_description'])) {
                update_post_meta($post_id, '_aicr_meta_description', $parsed_content['meta_description']);
            }
            if (!empty($parsed_content['focus_keyword'])) {
                update_post_meta($post_id, '_aicr_focus_keyword', $parsed_content['focus_keyword']);
            }
            if (!empty($parsed_content['keywords'])) {
                update_post_meta($post_id, '_aicr_keywords', $parsed_content['keywords']);
            }

            // 태그 설정
            if (!empty($parsed_content['tags']) && is_array($parsed_content['tags'])) {
                wp_set_post_tags($post_id, $parsed_content['tags'], false);
            }

            // 피드 아이템 상태 업데이트 (rewritten_post_id 저장 및 상태 변경)
            $this->item_repository->set_rewritten_post($item->get_id(), $post_id);

            // 카테고리 이름 가져오기
            $category_name = null;
            if ($category_id) {
                $category_term = get_term($category_id, 'category');
                if ($category_term && !is_wp_error($category_term)) {
                    $category_name = $category_term->name;
                }
            }

            // 완료 상태로 업데이트
            $this->update_task_progress($task_id, self::STATUS_COMPLETED, 'completed', 100, '재작성이 완료되었습니다!', [
                'post_id' => $post_id,
                'edit_url' => get_edit_post_link($post_id, 'raw'),
                'view_url' => get_permalink($post_id),
                'category_id' => $category_id,
                'category_name' => $category_name,
            ]);

        } catch (\Exception $e) {
            // 실패 상태로 업데이트
            $this->update_task_error($task_id, $e->getMessage());

            // 로그 기록
            error_log('[AICR Async Rewrite] Task ' . $task_id . ' failed: ' . $e->getMessage());
        }
    }

    /**
     * 작업 ID 생성
     */
    private function generate_task_id(): string {
        return 'task_' . wp_generate_uuid4();
    }

    /**
     * 작업 데이터 저장
     */
    private function save_task(string $task_id, array $data): void {
        set_transient(self::TRANSIENT_PREFIX . $task_id, $data, self::TASK_EXPIRATION);
    }

    /**
     * 작업 데이터 조회
     */
    private function get_task(string $task_id): ?array {
        $data = get_transient(self::TRANSIENT_PREFIX . $task_id);
        return $data ?: null;
    }

    /**
     * 작업 진행 상황 업데이트
     */
    private function update_task_progress(
        string $task_id,
        string $status,
        string $step,
        int $progress,
        string $message,
        ?array $result = null
    ): void {
        $task = $this->get_task($task_id);

        if (!$task) {
            return;
        }

        $task['status'] = $status;
        $task['step'] = $step;
        $task['progress'] = $progress;
        $task['message'] = $message;
        $task['updated_at'] = time();

        if ($result !== null) {
            $task['result'] = $result;
        }

        $this->save_task($task_id, $task);
    }

    /**
     * 작업 오류 업데이트
     */
    private function update_task_error(string $task_id, string $error): void {
        $task = $this->get_task($task_id);

        if (!$task) {
            return;
        }

        $task['status'] = self::STATUS_FAILED;
        $task['step'] = 'failed';
        $task['error'] = $error;
        $task['message'] = '오류: ' . $error;
        $task['updated_at'] = time();

        $this->save_task($task_id, $task);
    }

    /**
     * 만료된 작업 정리 (선택적)
     */
    public function cleanup_expired_tasks(): void {
        // WordPress 트랜시언트는 자동으로 만료됨
        // 추가 정리 로직이 필요한 경우 여기에 구현
    }

    /**
     * 카테고리 해결 (수동 선택 > AI 추천 > 기본값)
     *
     * @param int|null $manual_category 수동 선택된 카테고리 ID
     * @param string|null $ai_suggestion AI가 추천한 카테고리명
     * @return int|null 카테고리 ID
     */
    private function resolve_category(?int $manual_category, ?string $ai_suggestion): ?int {
        // 1. 수동 선택된 카테고리가 있으면 사용
        if ($manual_category && $manual_category > 0) {
            return $manual_category;
        }

        // 2. AI 추천 카테고리가 있으면 처리
        if (!empty($ai_suggestion)) {
            $category_name = sanitize_text_field(trim($ai_suggestion));

            if (empty($category_name)) {
                return null;
            }

            // 기존 카테고리 검색 (이름으로)
            $existing_category = get_term_by('name', $category_name, 'category');

            if ($existing_category) {
                // 기존 카테고리 사용
                return (int) $existing_category->term_id;
            }

            // 슬러그로도 검색 (한글 이름이 슬러그로 변환된 경우)
            $slug = sanitize_title($category_name);
            $existing_by_slug = get_term_by('slug', $slug, 'category');

            if ($existing_by_slug) {
                return (int) $existing_by_slug->term_id;
            }

            // 새 카테고리 생성
            $new_category = wp_insert_term($category_name, 'category', [
                'slug' => $slug,
            ]);

            if (!is_wp_error($new_category)) {
                error_log('[AICR] Created new category: ' . $category_name . ' (ID: ' . $new_category['term_id'] . ')');
                return (int) $new_category['term_id'];
            }

            // 생성 실패 시 로그
            error_log('[AICR] Failed to create category: ' . $category_name . ' - ' . $new_category->get_error_message());
        }

        return null;
    }

    /**
     * AI 응답에서 JSON 파싱
     *
     * @param string $content AI 응답 내용
     * @return array 파싱된 데이터
     */
    private function parse_ai_response(string $content): array {
        // JSON 코드 블록에서 JSON 추출 (```json ... ```)
        if (preg_match('/```json\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $json_str = $matches[1];
        } elseif (preg_match('/```\s*([\s\S]*?)\s*```/', $content, $matches)) {
            // 일반 코드 블록에서 추출
            $json_str = $matches[1];
        } else {
            // 코드 블록 없이 JSON만 있는 경우
            $json_str = $content;
        }

        // JSON 정리 (줄바꿈, 특수문자 처리)
        $json_str = trim($json_str);

        // JSON 파싱 시도
        $parsed = json_decode($json_str, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // JSON 파싱 실패 시 원본 콘텐츠 반환
            error_log('[AICR] JSON parse error: ' . json_last_error_msg());
            error_log('[AICR] Raw content: ' . substr($content, 0, 500));

            return [
                'post_content' => $content,
            ];
        }

        return $parsed;
    }
}
