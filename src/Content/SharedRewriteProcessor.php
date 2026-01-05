<?php
/**
 * Shared Rewrite Processor
 *
 * 공통 재작성 처리 모듈
 * RSS 피드 아이템 및 URL/텍스트 직접 입력 모두 지원
 *
 * @package AIContentRewriter\Content
 */

namespace AIContentRewriter\Content;

use AIContentRewriter\AI\AIFactory;
use AIContentRewriter\AI\AIException;
use AIContentRewriter\RSS\FeedItemRepository;

/**
 * 공통 재작성 프로세서
 */
class SharedRewriteProcessor {
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
     * 소스 타입 상수
     */
    public const SOURCE_RSS_ITEM = 'rss_item';
    public const SOURCE_URL = 'url';
    public const SOURCE_TEXT = 'text';

    /**
     * 트랜시언트 접두사
     */
    private const TRANSIENT_PREFIX = 'aicr_rewrite_';

    /**
     * 작업 만료 시간 (초)
     */
    private const TASK_EXPIRATION = 3600; // 1시간

    /**
     * 재작성 작업 시작
     *
     * @param array $params 작업 파라미터
     *   - source_type: 'rss_item', 'url', 'text'
     *   - item_id: RSS 아이템 ID (source_type이 'rss_item'인 경우)
     *   - source_url: URL (source_type이 'url'인 경우)
     *   - source_text: 텍스트 (source_type이 'text'인 경우)
     *   - options: 추가 옵션 (template_id, language, category, post_status 등)
     * @return string 작업 ID
     */
    public function start_task(array $params): string {
        $task_id = $this->generate_task_id();

        $task_data = [
            'task_id' => $task_id,
            'source_type' => $params['source_type'] ?? self::SOURCE_URL,
            'item_id' => $params['item_id'] ?? null,
            'source_url' => $params['source_url'] ?? '',
            'source_text' => $params['source_text'] ?? '',
            'options' => $params['options'] ?? [],
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
        $url = admin_url('admin-ajax.php');

        $args = [
            'timeout' => 0.01,
            'blocking' => false,
            'sslverify' => apply_filters('https_local_ssl_verify', false),
            'body' => [
                'action' => 'aicr_process_shared_rewrite',
                'task_id' => $task_id,
                'nonce' => wp_create_nonce('aicr_shared_rewrite_' . $task_id),
            ],
            'cookies' => $_COOKIE,
        ];

        wp_remote_post($url, $args);
    }

    /**
     * 백그라운드에서 재작성 처리
     *
     * @param string $task_id 작업 ID
     */
    public function process_task(string $task_id): void {
        // 클라이언트 연결 끊어져도 계속 실행
        ignore_user_abort(true);

        // 타임아웃 설정 (무제한)
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        // 버퍼링 비활성화 및 클라이언트 연결 종료
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
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
            $options = $task['options'];
            $source_type = $task['source_type'];

            // Step 1: 콘텐츠 추출
            $this->update_task_progress($task_id, self::STATUS_EXTRACTING, 'extracting', 10, '원본 콘텐츠 추출 중...');

            $content_data = $this->extract_content($task);

            $this->update_task_progress($task_id, self::STATUS_EXTRACTING, 'extracting', 30, '콘텐츠 추출 완료, AI 재작성 준비 중...');

            // Step 2: AI 재작성
            $this->update_task_progress($task_id, self::STATUS_REWRITING, 'rewriting', 40, 'AI가 콘텐츠를 재작성하고 있습니다...');

            $ai_provider = $options['ai_provider'] ?? get_option('aicr_default_ai_provider', 'chatgpt');
            $ai = AIFactory::create($ai_provider);

            // 프롬프트 생성
            $prompt_manager = new PromptManager();
            $template_id = $options['template_id'] ?? 0;
            $language = $options['language'] ?? 'ko';

            $prompt = $prompt_manager->build_prompt(
                'blog_post',
                [
                    'content' => $content_data['content'],
                    'title' => $content_data['title'],
                    'target_language' => $language,
                    'source_url' => $content_data['source_url'],
                ],
                $template_id ?: null
            );

            // AI 응답 생성 - 충분한 토큰으로 긴 콘텐츠도 잘림 없이 생성
            $ai_response = $ai->generate($prompt, [
                'max_completion_tokens' => 32768,
            ]);

            if (!$ai_response->is_success()) {
                throw new AIException('AI 생성 실패: ' . $ai_response->get_error());
            }

            $ai_raw_content = $ai_response->get_content();

            // AI 응답에서 JSON 파싱
            $parsed_content = $this->parse_ai_response($ai_raw_content);

            $this->update_task_progress($task_id, self::STATUS_REWRITING, 'rewriting', 70, 'AI 재작성 완료!');

            // 본문 콘텐츠 구성
            $post_content = $parsed_content['post_content'] ?? $ai_raw_content;

            // Step 3: 게시글 생성
            $this->update_task_progress($task_id, self::STATUS_PUBLISHING, 'publishing', 80, '워드프레스 게시글 생성 중...');

            $post_data = [
                'post_title' => $parsed_content['post_title'] ?? $content_data['title'],
                'post_content' => $post_content,
                'post_excerpt' => $parsed_content['excerpt'] ?? '',
                'post_status' => $options['post_status'] ?? 'draft',
                'post_author' => get_current_user_id(),
                'post_type' => 'post',
            ];

            // 카테고리 설정
            $category_id = $this->resolve_category($options['category'] ?? null, $parsed_content['category_suggestion'] ?? null);
            if ($category_id) {
                $post_data['post_category'] = [$category_id];
            }

            $post_id = wp_insert_post($post_data, true);

            if (is_wp_error($post_id)) {
                throw new \Exception('게시글 생성 실패: ' . $post_id->get_error_message());
            }

            // 메타 데이터 저장
            $this->save_post_meta($post_id, $content_data, $parsed_content, $ai_provider, $source_type);

            // RSS 아이템인 경우 상태 업데이트
            if ($source_type === self::SOURCE_RSS_ITEM && !empty($task['item_id'])) {
                $item_repository = new FeedItemRepository();
                $item_repository->set_rewritten_post($task['item_id'], $post_id);
            }

            // 태그 설정
            if (!empty($parsed_content['tags']) && is_array($parsed_content['tags'])) {
                wp_set_post_tags($post_id, $parsed_content['tags'], false);
            }

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
                'post_title' => $post_data['post_title'],
                'edit_url' => get_edit_post_link($post_id, 'raw'),
                'view_url' => get_permalink($post_id),
                'category_id' => $category_id,
                'category_name' => $category_name,
            ]);

        } catch (\Exception $e) {
            $this->update_task_error($task_id, $e->getMessage());
            error_log('[AICR Shared Rewrite] Task ' . $task_id . ' failed: ' . $e->getMessage());
        }
    }

    /**
     * 소스 타입에 따른 콘텐츠 추출
     *
     * @param array $task 작업 데이터
     * @return array ['content' => ..., 'title' => ..., 'source_url' => ...]
     */
    private function extract_content(array $task): array {
        $source_type = $task['source_type'];

        switch ($source_type) {
            case self::SOURCE_RSS_ITEM:
                return $this->extract_from_rss_item($task['item_id']);

            case self::SOURCE_URL:
                return $this->extract_from_url($task['source_url']);

            case self::SOURCE_TEXT:
                return $this->extract_from_text($task['source_text']);

            default:
                throw new \Exception('알 수 없는 소스 타입: ' . $source_type);
        }
    }

    /**
     * RSS 아이템에서 콘텐츠 추출
     */
    private function extract_from_rss_item(int $item_id): array {
        $item_repository = new FeedItemRepository();
        $item = $item_repository->find($item_id);

        if (!$item) {
            throw new \Exception('피드 아이템을 찾을 수 없습니다.');
        }

        $extractor = new ContentExtractor();
        $content_result = $extractor->extract_from_url($item->get_link());

        if (!$content_result->is_success()) {
            throw new \Exception('콘텐츠 추출 실패: ' . $content_result->get_error());
        }

        return [
            'content' => $content_result->get_content(),
            'title' => $content_result->get_title(),
            'source_url' => $item->get_link(),
        ];
    }

    /**
     * URL에서 콘텐츠 추출
     */
    private function extract_from_url(string $url): array {
        if (empty($url)) {
            throw new \Exception('URL이 비어있습니다.');
        }

        $extractor = new ContentExtractor();
        $content_result = $extractor->extract_from_url($url);

        if (!$content_result->is_success()) {
            throw new \Exception('콘텐츠 추출 실패: ' . $content_result->get_error());
        }

        return [
            'content' => $content_result->get_content(),
            'title' => $content_result->get_title(),
            'source_url' => $url,
        ];
    }

    /**
     * 텍스트에서 콘텐츠 추출
     */
    private function extract_from_text(string $text): array {
        if (empty($text)) {
            throw new \Exception('텍스트가 비어있습니다.');
        }

        // 첫 번째 줄을 제목으로 사용
        $lines = preg_split('/\r?\n/', trim($text));
        $first_line = trim($lines[0] ?? '');

        if (mb_strlen($first_line) > 100) {
            $first_line = mb_substr($first_line, 0, 100) . '...';
        }

        return [
            'content' => $text,
            'title' => $first_line ?: '새 글',
            'source_url' => '',
        ];
    }

    /**
     * 게시글 메타 데이터 저장
     */
    private function save_post_meta(int $post_id, array $content_data, array $parsed_content, string $ai_provider, string $source_type): void {
        if (!empty($content_data['source_url'])) {
            update_post_meta($post_id, '_aicr_source_url', $content_data['source_url']);
        }
        update_post_meta($post_id, '_aicr_source_title', $content_data['title']);
        update_post_meta($post_id, '_aicr_source_type', $source_type);
        update_post_meta($post_id, '_aicr_ai_provider', $ai_provider);
        update_post_meta($post_id, '_aicr_rewritten_at', current_time('mysql'));

        // SEO 메타
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
    }

    /**
     * 작업 ID 생성
     */
    private function generate_task_id(): string {
        return 'rewrite_' . wp_generate_uuid4();
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
     * 카테고리 해결
     */
    private function resolve_category(?int $manual_category, ?string $ai_suggestion): ?int {
        if ($manual_category && $manual_category > 0) {
            return $manual_category;
        }

        if (!empty($ai_suggestion)) {
            $category_name = sanitize_text_field(trim($ai_suggestion));

            if (empty($category_name)) {
                return null;
            }

            $existing_category = get_term_by('name', $category_name, 'category');
            if ($existing_category) {
                return (int) $existing_category->term_id;
            }

            $slug = sanitize_title($category_name);
            $existing_by_slug = get_term_by('slug', $slug, 'category');
            if ($existing_by_slug) {
                return (int) $existing_by_slug->term_id;
            }

            $new_category = wp_insert_term($category_name, 'category', ['slug' => $slug]);
            if (!is_wp_error($new_category)) {
                return (int) $new_category['term_id'];
            }
        }

        return null;
    }

    /**
     * AI 응답에서 JSON 파싱
     */
    private function parse_ai_response(string $content): array {
        if (preg_match('/```json\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $json_str = $matches[1];
        } elseif (preg_match('/```\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $json_str = $matches[1];
        } else {
            $json_str = $content;
        }

        $json_str = trim($json_str);
        $parsed = json_decode($json_str, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['post_content' => $content];
        }

        return $parsed;
    }
}
