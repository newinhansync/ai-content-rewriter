<?php
/**
 * AJAX Handler
 *
 * @package AIContentRewriter\Admin
 */

namespace AIContentRewriter\Admin;

use AIContentRewriter\Content\ContentRewriter;
use AIContentRewriter\Content\ContentExtractor;
use AIContentRewriter\Content\SharedRewriteProcessor;
use AIContentRewriter\Content\PromptManager;
use AIContentRewriter\AI\AIFactory;
use AIContentRewriter\Security\Encryption;
use AIContentRewriter\Security\RateLimiter;
use AIContentRewriter\Cron\CronLogger;
use AIContentRewriter\Cron\CronMonitor;
use AIContentRewriter\RSS\FeedScheduler;
use AIContentRewriter\Image\ImageGenerator;
use AIContentRewriter\Image\ImagePromptManager;
use AIContentRewriter\Image\ImageScheduler;
use AIContentRewriter\AI\GeminiImageAdapter;
use AIContentRewriter\Worker\WorkerConfig;
use AIContentRewriter\Core\ProcessingMode;

/**
 * AJAX 요청 처리 클래스
 */
class AjaxHandler {
    /**
     * 초기화
     */
    public function init(): void {
        // 설정 저장
        add_action('wp_ajax_aicr_save_settings', [$this, 'save_settings']);

        // API 키 테스트
        add_action('wp_ajax_aicr_test_api_key', [$this, 'test_api_key']);

        // 콘텐츠 변환
        add_action('wp_ajax_aicr_rewrite_content', [$this, 'rewrite_content']);

        // URL 콘텐츠 미리보기
        add_action('wp_ajax_aicr_preview_url', [$this, 'preview_url']);

        // 포스트 저장
        add_action('wp_ajax_aicr_save_post', [$this, 'save_post']);

        // 히스토리 조회
        add_action('wp_ajax_aicr_get_history', [$this, 'get_history']);

        // 히스토리 삭제
        add_action('wp_ajax_aicr_delete_history', [$this, 'delete_history']);

        // 스케줄 관리
        add_action('wp_ajax_aicr_get_schedules', [$this, 'get_schedules']);
        add_action('wp_ajax_aicr_save_schedule', [$this, 'save_schedule']);
        add_action('wp_ajax_aicr_delete_schedule', [$this, 'delete_schedule']);
        add_action('wp_ajax_aicr_toggle_schedule', [$this, 'toggle_schedule']);

        // 프롬프트 관리
        add_action('wp_ajax_aicr_save_prompt', [$this, 'save_prompt']);
        add_action('wp_ajax_aicr_reset_prompt', [$this, 'reset_prompt']);
        add_action('wp_ajax_aicr_get_prompt', [$this, 'get_prompt']);

        // 비동기 콘텐츠 재작성 (공통 모듈 사용)
        add_action('wp_ajax_aicr_start_content_task', [$this, 'start_content_task']);
        add_action('wp_ajax_aicr_check_content_status', [$this, 'check_content_status']);
        add_action('wp_ajax_aicr_process_shared_rewrite', [$this, 'process_shared_rewrite']);
        add_action('wp_ajax_nopriv_aicr_process_shared_rewrite', [$this, 'process_shared_rewrite']);

        // Cron 관리 (자동화 탭)
        add_action('wp_ajax_aicr_get_cron_status', [$this, 'get_cron_status']);
        add_action('wp_ajax_aicr_run_cron_task', [$this, 'run_cron_task']);
        add_action('wp_ajax_aicr_get_cron_logs', [$this, 'get_cron_logs']);
        add_action('wp_ajax_aicr_clear_cron_logs', [$this, 'clear_cron_logs']);
        add_action('wp_ajax_aicr_regenerate_cron_token', [$this, 'regenerate_cron_token']);

        // 이미지 생성 관리
        add_action('wp_ajax_aicr_generate_images', [$this, 'generate_images']);
        add_action('wp_ajax_aicr_remove_images', [$this, 'remove_images']);
        add_action('wp_ajax_aicr_get_image_styles', [$this, 'get_image_styles']);
        add_action('wp_ajax_aicr_save_image_style', [$this, 'save_image_style']);
        add_action('wp_ajax_aicr_delete_image_style', [$this, 'delete_image_style']);
        add_action('wp_ajax_aicr_save_image_settings', [$this, 'save_image_settings']);
        add_action('wp_ajax_aicr_get_image_settings', [$this, 'get_image_settings']);
        add_action('wp_ajax_aicr_save_image_prompt', [$this, 'save_image_prompt']);
        add_action('wp_ajax_aicr_reset_image_prompt', [$this, 'reset_image_prompt']);
        add_action('wp_ajax_aicr_run_image_generation', [$this, 'run_image_generation']);
        add_action('wp_ajax_aicr_get_image_generation_status', [$this, 'get_image_generation_status']);
        add_action('wp_ajax_aicr_test_imagen_api', [$this, 'test_imagen_api']);

        // 점진적 이미지 생성 (Progressive Generation - Bad Gateway 방지)
        add_action('wp_ajax_aicr_prepare_progressive_images', [$this, 'prepare_progressive_images']);
        add_action('wp_ajax_aicr_generate_single_image', [$this, 'generate_single_image']);
        add_action('wp_ajax_aicr_finalize_progressive_images', [$this, 'finalize_progressive_images']);
        add_action('wp_ajax_aicr_cancel_progressive_images', [$this, 'cancel_progressive_images']);

        // Cloudflare Worker 설정 (v2.0)
        add_action('wp_ajax_aicr_test_worker_connection', [$this, 'test_worker_connection']);
        add_action('wp_ajax_aicr_sync_worker_config', [$this, 'sync_worker_config']);
        add_action('wp_ajax_aicr_regenerate_hmac', [$this, 'regenerate_hmac']);
        add_action('wp_ajax_aicr_regenerate_api_key', [$this, 'regenerate_api_key']);
        add_action('wp_ajax_aicr_save_worker_settings', [$this, 'save_worker_settings']);
    }

    /**
     * Nonce 및 권한 검증 (실패 시 즉시 종료)
     *
     * @return void 검증 실패 시 wp_send_json_error로 종료
     */
    private function verify_request(): void {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'aicr_nonce')) {
            wp_send_json_error(['message' => __('보안 검증에 실패했습니다.', 'ai-content-rewriter')]);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('권한이 없습니다.', 'ai-content-rewriter')]);
        }
    }

    /**
     * Nonce 검증 (하위 호환성 유지)
     * @deprecated Use verify_request() instead
     */
    private function verify_nonce(): bool {
        return wp_verify_nonce($_POST['nonce'] ?? '', 'aicr_nonce');
    }

    /**
     * 권한 검증 (하위 호환성 유지)
     * @deprecated Use verify_request() instead
     */
    private function verify_permission(): bool {
        return current_user_can('manage_options');
    }

    /**
     * JSON 응답 전송
     */
    private function send_json_response(bool $success, array $data = []): void {
        if ($success) {
            wp_send_json_success($data);
        } else {
            wp_send_json_error($data);
        }
    }

    /**
     * 허용된 포스트 상태 목록
     */
    private function get_allowed_post_statuses(): array {
        return ['draft', 'pending', 'publish', 'private'];
    }

    /**
     * 허용된 스케줄 간격 목록
     */
    private function get_allowed_intervals(): array {
        return ['once', 'hourly', 'twicedaily', 'daily', 'weekly'];
    }

    /**
     * 설정 저장
     */
    public function save_settings(): void {
        $this->verify_request();

        // API 키는 암호화하여 저장
        $chatgpt_key = sanitize_text_field($_POST['chatgpt_api_key'] ?? '');
        $gemini_key = sanitize_text_field($_POST['gemini_api_key'] ?? '');

        if (!empty($chatgpt_key)) {
            Encryption::save_api_key('aicr_chatgpt_api_key', $chatgpt_key);
        }
        if (!empty($gemini_key)) {
            Encryption::save_api_key('aicr_gemini_api_key', $gemini_key);
        }

        // AI 캐시 초기화 (API 키 변경 시 새 인스턴스 생성 필요)
        AIFactory::clear_cache();

        // 일반 설정 저장
        $settings = [
            'aicr_default_ai_provider' => sanitize_text_field($_POST['default_ai_provider'] ?? 'chatgpt'),
            'aicr_default_language' => sanitize_text_field($_POST['default_language'] ?? 'ko'),
            'aicr_default_post_status' => sanitize_text_field($_POST['default_post_status'] ?? 'draft'),
            'aicr_chunk_size' => min(10000, max(1000, absint($_POST['chunk_size'] ?? 3000))),
            'aicr_auto_generate_metadata' => isset($_POST['auto_generate_metadata']) ? '1' : '0',
            'aicr_log_retention_days' => min(365, max(7, absint($_POST['log_retention_days'] ?? 90))),
            'aicr_debug_mode' => isset($_POST['debug_mode']) ? '1' : '0',
        ];

        // 화이트리스트 검증
        $allowed_providers = ['chatgpt', 'gemini'];
        if (!in_array($settings['aicr_default_ai_provider'], $allowed_providers, true)) {
            $settings['aicr_default_ai_provider'] = 'chatgpt';
        }

        $allowed_languages = ['ko', 'en', 'ja', 'zh', 'es', 'fr', 'de'];
        if (!in_array($settings['aicr_default_language'], $allowed_languages, true)) {
            $settings['aicr_default_language'] = 'ko';
        }

        if (!in_array($settings['aicr_default_post_status'], $this->get_allowed_post_statuses(), true)) {
            $settings['aicr_default_post_status'] = 'draft';
        }

        foreach ($settings as $key => $value) {
            update_option($key, $value);
        }

        $this->send_json_response(true, ['message' => __('설정이 저장되었습니다.', 'ai-content-rewriter')]);
    }

    /**
     * API 키 테스트
     */
    public function test_api_key(): void {
        $this->verify_request();

        $provider = sanitize_text_field($_POST['provider'] ?? '');
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');

        // 허용된 제공자만 허용
        $allowed_providers = ['chatgpt', 'gemini'];
        if (empty($provider) || !in_array($provider, $allowed_providers, true)) {
            wp_send_json_error(['message' => __('유효하지 않은 AI 제공자입니다.', 'ai-content-rewriter')]);
        }

        if (empty($api_key)) {
            wp_send_json_error(['message' => __('API 키를 입력해주세요.', 'ai-content-rewriter')]);
        }

        try {
            $adapter = AIFactory::create($provider);
            $adapter->set_api_key($api_key);

            $is_valid = $adapter->test_connection();

            if ($is_valid) {
                $this->send_json_response(true, ['message' => __('API 연결 성공!', 'ai-content-rewriter')]);
            } else {
                $this->send_json_response(false, ['message' => __('API 연결 실패. API 키를 확인해주세요.', 'ai-content-rewriter')]);
            }
        } catch (\Exception $e) {
            $this->send_json_response(false, ['message' => $e->getMessage()]);
        }
    }

    /**
     * 콘텐츠 변환
     */
    public function rewrite_content(): void {
        $this->verify_request();

        // Rate Limiting 체크
        $rate_check = RateLimiter::check('content_rewrite');
        if (!$rate_check['allowed']) {
            wp_send_json_error([
                'message' => $rate_check['message'],
                'remaining' => $rate_check['remaining'],
                'reset_time' => $rate_check['reset_time'],
            ]);
        }

        $source_type = sanitize_text_field($_POST['source_type'] ?? 'url');

        // source_type 화이트리스트 검증
        if (!in_array($source_type, ['url', 'text'], true)) {
            wp_send_json_error(['message' => __('유효하지 않은 소스 타입입니다.', 'ai-content-rewriter')]);
        }

        $source_url = esc_url_raw($_POST['source_url'] ?? '');
        $source_text = wp_kses_post($_POST['source_text'] ?? '');
        $ai_provider = sanitize_text_field($_POST['ai_provider'] ?? '');
        $target_language = sanitize_text_field($_POST['target_language'] ?? 'ko');
        $template_type = sanitize_text_field($_POST['template_type'] ?? 'content_rewrite');

        // 언어 코드 화이트리스트
        $allowed_languages = ['ko', 'en', 'ja', 'zh', 'es', 'fr', 'de'];
        if (!in_array($target_language, $allowed_languages, true)) {
            $target_language = 'ko';
        }

        try {
            $rewriter = new ContentRewriter($ai_provider ?: null);
            $rewriter->set_target_language($target_language);

            if ($source_type === 'url') {
                if (empty($source_url)) {
                    wp_send_json_error(['message' => __('URL을 입력해주세요.', 'ai-content-rewriter')]);
                }
                $result = $rewriter->rewrite_from_url($source_url, [
                    'template_type' => $template_type,
                ]);
            } else {
                if (empty($source_text)) {
                    wp_send_json_error(['message' => __('텍스트를 입력해주세요.', 'ai-content-rewriter')]);
                }
                $result = $rewriter->rewrite_content($source_text, [
                    'template_type' => $template_type,
                ]);
            }

            if ($result->is_success()) {
                // 메타데이터 생성 옵션이 활성화된 경우
                $metadata = [];
                if (!empty($_POST['generate_metadata'])) {
                    $metadata = $rewriter->generate_metadata($result->get_content());
                }

                $this->send_json_response(true, [
                    'content' => $result->get_content(),
                    'title' => $result->extract_title(),
                    'metadata' => $metadata,
                    'tokens_used' => $result->get_tokens_used(),
                    'processing_time' => round($result->get_processing_time(), 2),
                ]);
            } else {
                $this->send_json_response(false, ['message' => $result->get_error_message()]);
            }

        } catch (\Exception $e) {
            $this->send_json_response(false, ['message' => $e->getMessage()]);
        }
    }

    /**
     * URL 콘텐츠 미리보기
     */
    public function preview_url(): void {
        $this->verify_request();

        $url = esc_url_raw($_POST['url'] ?? '');

        if (empty($url)) {
            wp_send_json_error(['message' => __('URL을 입력해주세요.', 'ai-content-rewriter')]);
        }

        $extractor = new ContentExtractor();
        $result = $extractor->extract_from_url($url);

        if ($result->is_success()) {
            $this->send_json_response(true, [
                'title' => $result->get_title(),
                'content' => mb_substr($result->get_content(), 0, 500) . '...',
                'word_count' => $result->get_word_count(),
                'metadata' => $result->get_metadata(),
            ]);
        } else {
            $this->send_json_response(false, ['message' => $result->get_error_message()]);
        }
    }

    /**
     * 포스트 저장
     */
    public function save_post(): void {
        $this->verify_request();

        $title = sanitize_text_field($_POST['title'] ?? '');
        $content = wp_kses_post($_POST['content'] ?? '');
        $status = sanitize_text_field($_POST['post_status'] ?? 'draft');
        $category = absint($_POST['post_category'] ?? 0);

        // 포스트 상태 화이트리스트 검증
        if (!in_array($status, $this->get_allowed_post_statuses(), true)) {
            $status = 'draft';
        }

        if (empty($title) || empty($content)) {
            wp_send_json_error(['message' => __('제목과 내용을 입력해주세요.', 'ai-content-rewriter')]);
        }

        $post_data = [
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => $status,
            'post_type' => 'post',
            'post_author' => get_current_user_id(),
        ];

        if ($category > 0) {
            $post_data['post_category'] = [$category];
        }

        // 메타데이터 추가
        if (!empty($_POST['meta_title'])) {
            $post_data['meta_input']['_yoast_wpseo_title'] = sanitize_text_field($_POST['meta_title']);
        }
        if (!empty($_POST['meta_description'])) {
            $post_data['meta_input']['_yoast_wpseo_metadesc'] = sanitize_text_field($_POST['meta_description']);
        }
        if (!empty($_POST['keywords'])) {
            $post_data['meta_input']['_aicr_keywords'] = sanitize_text_field($_POST['keywords']);
        }

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            $this->send_json_response(false, ['message' => $post_id->get_error_message()]);
        }

        // 태그 추가
        if (!empty($_POST['tags'])) {
            $tags = array_map('sanitize_text_field', (array) $_POST['tags']);
            wp_set_post_tags($post_id, $tags);
        }

        $this->send_json_response(true, [
            'post_id' => $post_id,
            'edit_url' => get_edit_post_link($post_id, 'raw'),
            'view_url' => get_permalink($post_id),
            'message' => __('포스트가 저장되었습니다.', 'ai-content-rewriter'),
        ]);
    }

    /**
     * 히스토리 조회
     */
    public function get_history(): void {
        $this->verify_request();

        global $wpdb;

        $page = max(1, absint($_POST['page'] ?? 1));
        $per_page = min(100, max(1, absint($_POST['per_page'] ?? 20))); // 1-100 범위 제한
        $status = sanitize_text_field($_POST['status'] ?? '');
        $provider = sanitize_text_field($_POST['provider'] ?? '');

        // 허용된 상태값 화이트리스트
        $allowed_statuses = ['pending', 'processing', 'completed', 'failed'];
        if ($status && !in_array($status, $allowed_statuses, true)) {
            $status = '';
        }

        // 허용된 제공자 화이트리스트
        $allowed_providers = ['chatgpt', 'gemini'];
        if ($provider && !in_array(strtolower($provider), $allowed_providers, true)) {
            $provider = '';
        }

        $table_name = $wpdb->prefix . 'aicr_history';
        $offset = ($page - 1) * $per_page;

        // 안전한 쿼리 구성
        $where_parts = [];
        $query_params = [];

        if ($status) {
            $where_parts[] = 'status = %s';
            $query_params[] = $status;
        }

        if ($provider) {
            $where_parts[] = 'ai_provider = %s';
            $query_params[] = $provider;
        }

        $where_clause = !empty($where_parts) ? implode(' AND ', $where_parts) : '1=1';

        // COUNT 쿼리
        if (!empty($query_params)) {
            $count_sql = $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}",
                ...$query_params
            );
        } else {
            $count_sql = "SELECT COUNT(*) FROM {$table_name}";
        }
        $total = (int) $wpdb->get_var($count_sql);

        // 결과 쿼리
        $query_params[] = $per_page;
        $query_params[] = $offset;

        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, user_id, source_type, source_url, ai_provider, ai_model, tokens_used,
                        processing_time, status, created_at, updated_at
                 FROM {$table_name}
                 WHERE {$where_clause}
                 ORDER BY created_at DESC
                 LIMIT %d OFFSET %d",
                ...$query_params
            ),
            ARRAY_A
        );

        // 민감 데이터 제거 (source_content, result_content는 목록에서 제외)
        $this->send_json_response(true, [
            'items' => $items ?: [],
            'total' => $total,
            'pages' => $total > 0 ? (int) ceil($total / $per_page) : 0,
            'current_page' => $page,
        ]);
    }

    /**
     * 히스토리 삭제
     */
    public function delete_history(): void {
        $this->verify_request();

        global $wpdb;

        $id = absint($_POST['id'] ?? 0);

        if (!$id) {
            $this->send_json_response(false, ['message' => __('잘못된 요청입니다.', 'ai-content-rewriter')]);
        }

        $table_name = $wpdb->prefix . 'aicr_history';

        // 소유권 검증: 현재 사용자가 해당 레코드의 소유자인지 확인
        $owner_id = $wpdb->get_var(
            $wpdb->prepare("SELECT user_id FROM {$table_name} WHERE id = %d", $id)
        );

        if ($owner_id === null) {
            $this->send_json_response(false, ['message' => __('레코드를 찾을 수 없습니다.', 'ai-content-rewriter')]);
        }

        // 관리자가 아니면서 소유자도 아닌 경우 거부
        if (!current_user_can('manage_options') && (int) $owner_id !== get_current_user_id()) {
            $this->send_json_response(false, ['message' => __('해당 데이터를 삭제할 권한이 없습니다.', 'ai-content-rewriter')]);
        }

        $deleted = $wpdb->delete($table_name, ['id' => $id], ['%d']);

        if ($deleted) {
            $this->send_json_response(true, ['message' => __('삭제되었습니다.', 'ai-content-rewriter')]);
        } else {
            $this->send_json_response(false, ['message' => __('삭제 실패', 'ai-content-rewriter')]);
        }
    }

    /**
     * 스케줄 목록 조회
     */
    public function get_schedules(): void {
        $this->verify_request();

        global $wpdb;

        $table_name = $wpdb->prefix . 'aicr_schedules';
        $user_id = get_current_user_id();

        $schedules = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY created_at DESC",
                $user_id
            ),
            ARRAY_A
        );

        $this->send_json_response(true, ['schedules' => $schedules]);
    }

    /**
     * 스케줄 저장
     */
    public function save_schedule(): void {
        $this->verify_request();

        global $wpdb;

        $id = absint($_POST['schedule_id'] ?? 0);
        $name = sanitize_text_field($_POST['schedule_name'] ?? '');
        $url = esc_url_raw($_POST['schedule_url'] ?? '');
        $interval = sanitize_text_field($_POST['schedule_interval'] ?? 'once');
        $start = sanitize_text_field($_POST['schedule_start'] ?? '');

        // 화이트리스트 검증
        if (!in_array($interval, $this->get_allowed_intervals(), true)) {
            $interval = 'once';
        }

        if (empty($name) || empty($url)) {
            $this->send_json_response(false, ['message' => __('필수 항목을 입력해주세요.', 'ai-content-rewriter')]);
        }

        // SSRF 방지: URL 검증
        $url_validation = \AIContentRewriter\Security\UrlValidator::validate($url);
        if (!$url_validation['valid']) {
            $this->send_json_response(false, ['message' => $url_validation['message']]);
        }

        $table_name = $wpdb->prefix . 'aicr_schedules';
        $user_id = get_current_user_id();

        // 기존 스케줄 수정 시 소유권 검증
        if ($id) {
            $owner_id = $wpdb->get_var(
                $wpdb->prepare("SELECT user_id FROM {$table_name} WHERE id = %d", $id)
            );

            if ($owner_id === null) {
                $this->send_json_response(false, ['message' => __('스케줄을 찾을 수 없습니다.', 'ai-content-rewriter')]);
            }

            if (!current_user_can('manage_options') && (int) $owner_id !== $user_id) {
                $this->send_json_response(false, ['message' => __('해당 스케줄을 수정할 권한이 없습니다.', 'ai-content-rewriter')]);
            }
        }

        $data = [
            'user_id' => $user_id,
            'name' => $name,
            'source_type' => 'url',
            'source_url' => $url,
            'ai_provider' => get_option('aicr_default_ai_provider', 'chatgpt'),
            'schedule_type' => $interval,
            'schedule_interval' => $interval,
            'next_run' => $start ? gmdate('Y-m-d H:i:s', strtotime($start)) : current_time('mysql'),
            'is_active' => 1,
        ];

        if ($id) {
            $wpdb->update($table_name, $data, ['id' => $id]);
            $schedule_id = $id;
        } else {
            $wpdb->insert($table_name, $data);
            $schedule_id = $wpdb->insert_id;
        }

        $this->send_json_response(true, [
            'schedule_id' => $schedule_id,
            'message' => __('스케줄이 저장되었습니다.', 'ai-content-rewriter'),
        ]);
    }

    /**
     * 스케줄 삭제
     */
    public function delete_schedule(): void {
        $this->verify_request();

        global $wpdb;

        $id = absint($_POST['id'] ?? 0);

        if (!$id) {
            $this->send_json_response(false, ['message' => __('잘못된 요청입니다.', 'ai-content-rewriter')]);
        }

        $table_name = $wpdb->prefix . 'aicr_schedules';

        // 소유권 검증
        $owner_id = $wpdb->get_var(
            $wpdb->prepare("SELECT user_id FROM {$table_name} WHERE id = %d", $id)
        );

        if ($owner_id === null) {
            $this->send_json_response(false, ['message' => __('스케줄을 찾을 수 없습니다.', 'ai-content-rewriter')]);
        }

        if (!current_user_can('manage_options') && (int) $owner_id !== get_current_user_id()) {
            $this->send_json_response(false, ['message' => __('해당 스케줄을 삭제할 권한이 없습니다.', 'ai-content-rewriter')]);
        }

        $wpdb->delete($table_name, ['id' => $id], ['%d']);

        $this->send_json_response(true, ['message' => __('스케줄이 삭제되었습니다.', 'ai-content-rewriter')]);
    }

    /**
     * 스케줄 활성/비활성 토글
     */
    public function toggle_schedule(): void {
        $this->verify_request();

        global $wpdb;

        $id = absint($_POST['id'] ?? 0);

        if (!$id) {
            $this->send_json_response(false, ['message' => __('잘못된 요청입니다.', 'ai-content-rewriter')]);
        }

        $table_name = $wpdb->prefix . 'aicr_schedules';

        // 소유권 검증
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT user_id, is_active FROM {$table_name} WHERE id = %d", $id)
        );

        if (!$row) {
            $this->send_json_response(false, ['message' => __('스케줄을 찾을 수 없습니다.', 'ai-content-rewriter')]);
        }

        if (!current_user_can('manage_options') && (int) $row->user_id !== get_current_user_id()) {
            $this->send_json_response(false, ['message' => __('해당 스케줄을 변경할 권한이 없습니다.', 'ai-content-rewriter')]);
        }

        $new_status = $row->is_active ? 0 : 1;
        $wpdb->update($table_name, ['is_active' => $new_status], ['id' => $id]);

        $this->send_json_response(true, [
            'is_active' => $new_status,
            'message' => $new_status ? __('스케줄이 활성화되었습니다.', 'ai-content-rewriter') : __('스케줄이 비활성화되었습니다.', 'ai-content-rewriter'),
        ]);
    }

    /**
     * 프롬프트 조회
     */
    public function get_prompt(): void {
        $this->verify_request();

        $prompt_manager = PromptManager::get_instance();
        $prompt = $prompt_manager->get_prompt();
        $default_prompt = $prompt_manager->get_default_prompt();

        $this->send_json_response(true, [
            'prompt' => $prompt,
            'is_default' => ($prompt === $default_prompt),
        ]);
    }

    /**
     * 프롬프트 저장
     */
    public function save_prompt(): void {
        $this->verify_request();

        $prompt = wp_unslash($_POST['prompt'] ?? '');

        if (empty(trim($prompt))) {
            $this->send_json_response(false, ['message' => __('프롬프트를 입력해주세요.', 'ai-content-rewriter')]);
            return;
        }

        // wp_options에 저장
        update_option('aicr_prompt_blog_post', $prompt);

        $this->send_json_response(true, ['message' => __('프롬프트가 저장되었습니다.', 'ai-content-rewriter')]);
    }

    /**
     * 프롬프트 기본값으로 복원
     */
    public function reset_prompt(): void {
        $this->verify_request();

        // 옵션 삭제 시 기본 프롬프트가 사용됨
        delete_option('aicr_prompt_blog_post');

        $prompt_manager = PromptManager::get_instance();

        $this->send_json_response(true, [
            'message' => __('기본 프롬프트로 복원되었습니다.', 'ai-content-rewriter'),
            'prompt' => $prompt_manager->get_default_prompt(),
        ]);
    }

    /**
     * 비동기 콘텐츠 재작성 작업 시작
     */
    public function start_content_task(): void {
        $this->verify_request();

        // Rate Limiting 체크
        $rate_check = RateLimiter::check('content_rewrite');
        if (!$rate_check['allowed']) {
            wp_send_json_error([
                'message' => $rate_check['message'],
                'remaining' => $rate_check['remaining'],
                'reset_time' => $rate_check['reset_time'],
            ]);
        }

        $source_type = sanitize_text_field($_POST['source_type'] ?? 'url');

        // source_type 화이트리스트 검증
        if (!in_array($source_type, ['url', 'text'], true)) {
            wp_send_json_error(['message' => __('유효하지 않은 소스 타입입니다.', 'ai-content-rewriter')]);
        }

        $source_url = esc_url_raw($_POST['source_url'] ?? '');
        $source_text = wp_kses_post($_POST['source_text'] ?? '');

        // 입력값 검증
        if ($source_type === 'url' && empty($source_url)) {
            wp_send_json_error(['message' => __('URL을 입력해주세요.', 'ai-content-rewriter')]);
        }

        if ($source_type === 'text' && empty($source_text)) {
            wp_send_json_error(['message' => __('텍스트를 입력해주세요.', 'ai-content-rewriter')]);
        }

        // 언어 코드 화이트리스트
        $language = sanitize_text_field($_POST['language'] ?? 'ko');
        $allowed_languages = ['ko', 'en', 'ja', 'zh', 'es', 'fr', 'de'];
        if (!in_array($language, $allowed_languages, true)) {
            $language = 'ko';
        }

        // 옵션 구성
        $options = [
            'ai_provider' => sanitize_text_field($_POST['ai_provider'] ?? ''),
            'template_id' => absint($_POST['template_id'] ?? 0),
            'category' => absint($_POST['category'] ?? 0),
            'language' => $language,
            'post_status' => sanitize_text_field($_POST['post_status'] ?? 'draft'),
        ];

        // 포스트 상태 화이트리스트 검증
        if (!in_array($options['post_status'], $this->get_allowed_post_statuses(), true)) {
            $options['post_status'] = 'draft';
        }

        // SharedRewriteProcessor로 작업 시작 (공통 모듈)
        $processor = new SharedRewriteProcessor();
        $task_id = $processor->start_task([
            'source_type' => $source_type,
            'source_url' => $source_url,
            'source_text' => $source_text,
            'options' => $options,
        ]);

        $this->send_json_response(true, [
            'task_id' => $task_id,
            'message' => __('재작성 작업이 시작되었습니다.', 'ai-content-rewriter'),
        ]);
    }

    /**
     * 콘텐츠 재작성 작업 상태 확인
     */
    public function check_content_status(): void {
        $this->verify_request();

        $task_id = sanitize_text_field($_POST['task_id'] ?? '');

        if (empty($task_id)) {
            wp_send_json_error(['message' => __('작업 ID가 없습니다.', 'ai-content-rewriter')]);
        }

        // SharedRewriteProcessor 사용 (공통 모듈)
        $processor = new SharedRewriteProcessor();
        $status = $processor->get_task_status($task_id);

        if (!$status) {
            wp_send_json_error(['message' => __('작업을 찾을 수 없습니다.', 'ai-content-rewriter')]);
        }

        $this->send_json_response(true, $status);
    }

    /**
     * 백그라운드에서 공통 재작성 처리
     */
    public function process_shared_rewrite(): void {
        $task_id = sanitize_text_field($_POST['task_id'] ?? '');
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');

        // Nonce 검증
        if (!wp_verify_nonce($nonce, 'aicr_shared_rewrite_' . $task_id)) {
            wp_die('Invalid nonce');
        }

        // SharedRewriteProcessor로 백그라운드 처리 실행
        $processor = new SharedRewriteProcessor();
        $processor->process_task($task_id);

        wp_die(); // 명시적 종료
    }

    /**
     * Cron 상태 조회
     */
    public function get_cron_status(): void {
        $this->verify_request();

        $monitor = new CronMonitor();
        $status = $monitor->get_health_status();

        $this->send_json_response(true, $status);
    }

    /**
     * Cron 작업 수동 실행
     */
    public function run_cron_task(): void {
        $this->verify_request();

        $task = sanitize_text_field($_POST['task'] ?? '');

        // 허용된 작업 타입 화이트리스트
        $allowed_tasks = ['fetch', 'rewrite', 'cleanup'];
        if (!in_array($task, $allowed_tasks, true)) {
            wp_send_json_error(['message' => __('유효하지 않은 작업입니다.', 'ai-content-rewriter')]);
        }

        try {
            $scheduler = new FeedScheduler();
            $result = $scheduler->run_now($task);

            if ($result) {
                $task_labels = [
                    'fetch' => __('피드 갱신', 'ai-content-rewriter'),
                    'rewrite' => __('자동 재작성', 'ai-content-rewriter'),
                    'cleanup' => __('정리 작업', 'ai-content-rewriter'),
                ];

                $this->send_json_response(true, [
                    'message' => sprintf(
                        __('%s 작업이 완료되었습니다.', 'ai-content-rewriter'),
                        $task_labels[$task] ?? $task
                    ),
                ]);
            } else {
                wp_send_json_error(['message' => __('작업 실행에 실패했습니다.', 'ai-content-rewriter')]);
            }
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Cron 로그 조회
     */
    public function get_cron_logs(): void {
        $this->verify_request();

        $hours = min(168, max(1, absint($_POST['hours'] ?? 24))); // 1-168시간 (최대 7일)
        $limit = min(100, max(10, absint($_POST['limit'] ?? 50))); // 10-100개

        $logger = new CronLogger();
        $logs = $logger->get_recent($hours, $limit);
        $statistics = $logger->get_statistics(7); // 7일 통계
        $running = $logger->get_running();

        $this->send_json_response(true, [
            'logs' => $logs,
            'statistics' => $statistics,
            'running' => $running,
        ]);
    }

    /**
     * Cron 로그 삭제
     */
    public function clear_cron_logs(): void {
        $this->verify_request();

        $days = absint($_POST['days'] ?? 0); // 0이면 모든 로그 삭제

        $logger = new CronLogger();
        $deleted = $logger->cleanup($days);

        $this->send_json_response(true, [
            'deleted' => $deleted,
            'message' => sprintf(
                __('%d개의 로그가 삭제되었습니다.', 'ai-content-rewriter'),
                $deleted
            ),
        ]);
    }

    /**
     * Cron 보안 토큰 재생성
     */
    public function regenerate_cron_token(): void {
        $this->verify_request();

        $monitor = new CronMonitor();
        $new_token = $monitor->regenerate_token();
        $cron_urls = $monitor->get_cron_urls();

        $this->send_json_response(true, [
            'token' => $new_token,
            'cron_urls' => $cron_urls,
            'message' => __('보안 토큰이 재생성되었습니다. 외부 Cron 서비스에서 URL을 업데이트하세요.', 'ai-content-rewriter'),
        ]);
    }

    /**
     * 게시글에 이미지 생성
     */
    public function generate_images(): void {
        $this->verify_request();

        // Gemini API 이미지 생성은 시간이 오래 걸릴 수 있음 (이미지당 약 30-60초)
        // PHP 실행 시간을 5분(300초)으로 연장
        set_time_limit(300);

        $post_id = absint($_POST['post_id'] ?? 0);

        if (!$post_id) {
            wp_send_json_error(['message' => __('게시글 ID가 필요합니다.', 'ai-content-rewriter')]);
        }

        // 게시글 편집 권한 확인
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => __('이 게시글을 편집할 권한이 없습니다.', 'ai-content-rewriter')]);
        }

        $options = [
            'count' => absint($_POST['count'] ?? 2),
            'style' => sanitize_text_field($_POST['style'] ?? ''),
            'ratio' => sanitize_text_field($_POST['ratio'] ?? '16:9'),
            'instructions' => sanitize_textarea_field($_POST['instructions'] ?? ''),
        ];

        // 이미지 수 제한 (1-5)
        $options['count'] = max(1, min(5, $options['count']));

        // 비율 화이트리스트
        $allowed_ratios = ['1:1', '3:4', '4:3', '9:16', '16:9'];
        if (!in_array($options['ratio'], $allowed_ratios, true)) {
            $options['ratio'] = '16:9';
        }

        try {
            $generator = new ImageGenerator();
            $images = $generator->generateForPost($post_id, $options);

            $this->send_json_response(true, [
                'message' => sprintf(
                    __('%d개의 이미지가 생성되었습니다.', 'ai-content-rewriter'),
                    count($images)
                ),
                'images' => $images,
                'post_id' => $post_id,
            ]);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * 게시글에서 AI 생성 이미지 제거
     */
    public function remove_images(): void {
        $this->verify_request();

        $post_id = absint($_POST['post_id'] ?? 0);

        if (!$post_id) {
            wp_send_json_error(['message' => __('게시글 ID가 필요합니다.', 'ai-content-rewriter')]);
        }

        // 게시글 편집 권한 확인
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => __('이 게시글을 편집할 권한이 없습니다.', 'ai-content-rewriter')]);
        }

        try {
            $generator = new ImageGenerator();
            $result = $generator->removeImagesFromPost($post_id);

            if ($result) {
                $this->send_json_response(true, [
                    'message' => __('AI 생성 이미지가 제거되었습니다.', 'ai-content-rewriter'),
                    'post_id' => $post_id,
                ]);
            } else {
                wp_send_json_error(['message' => __('이미지 제거에 실패했습니다.', 'ai-content-rewriter')]);
            }

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * 이미지 스타일 목록 조회
     */
    public function get_image_styles(): void {
        $this->verify_request();

        $manager = ImagePromptManager::get_instance();
        $styles = $manager->get_all_styles();

        $this->send_json_response(true, ['styles' => $styles]);
    }

    /**
     * 이미지 스타일 저장
     */
    public function save_image_style(): void {
        $this->verify_request();

        $data = [
            'id' => absint($_POST['id'] ?? 0),
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'style_prompt' => sanitize_textarea_field($_POST['style_prompt'] ?? ''),
            'negative_prompt' => sanitize_textarea_field($_POST['negative_prompt'] ?? ''),
            'aspect_ratio' => sanitize_text_field($_POST['aspect_ratio'] ?? '16:9'),
            'is_default' => !empty($_POST['is_default']),
        ];

        if (empty($data['name']) || empty($data['style_prompt'])) {
            wp_send_json_error(['message' => __('스타일 이름과 프롬프트는 필수입니다.', 'ai-content-rewriter')]);
        }

        $manager = ImagePromptManager::get_instance();
        $result = $manager->save_style($data);

        if ($result) {
            $this->send_json_response(true, [
                'id' => $result,
                'message' => __('스타일이 저장되었습니다.', 'ai-content-rewriter'),
            ]);
        } else {
            wp_send_json_error(['message' => __('스타일 저장에 실패했습니다.', 'ai-content-rewriter')]);
        }
    }

    /**
     * 이미지 스타일 삭제
     */
    public function delete_image_style(): void {
        $this->verify_request();

        $id = absint($_POST['id'] ?? 0);

        if (!$id) {
            wp_send_json_error(['message' => __('스타일 ID가 필요합니다.', 'ai-content-rewriter')]);
        }

        $manager = ImagePromptManager::get_instance();
        $result = $manager->delete_style($id);

        if ($result) {
            $this->send_json_response(true, ['message' => __('스타일이 삭제되었습니다.', 'ai-content-rewriter')]);
        } else {
            wp_send_json_error(['message' => __('기본 스타일은 삭제할 수 없습니다.', 'ai-content-rewriter')]);
        }
    }

    /**
     * 이미지 설정 저장
     */
    public function save_image_settings(): void {
        $this->verify_request();

        // 기본 설정
        update_option('aicr_image_enabled', !empty($_POST['enabled']));
        update_option('aicr_image_default_count', max(1, min(5, absint($_POST['default_count'] ?? 2))));
        update_option('aicr_image_default_ratio', sanitize_text_field($_POST['default_ratio'] ?? '16:9'));
        update_option('aicr_image_default_style', sanitize_text_field($_POST['default_style'] ?? '일러스트레이션'));

        // 자동 설정
        update_option('aicr_image_auto_featured', !empty($_POST['auto_featured']));
        update_option('aicr_image_auto_alt', !empty($_POST['auto_alt']));
        update_option('aicr_image_auto_caption', !empty($_POST['auto_caption']));

        // 스케줄 설정
        $schedule_enabled = !empty($_POST['schedule_enabled']);
        $current_enabled = get_option('aicr_image_schedule_enabled', false);

        update_option('aicr_image_schedule_enabled', $schedule_enabled);
        update_option('aicr_image_schedule_interval', sanitize_text_field($_POST['schedule_interval'] ?? 'hourly'));
        update_option('aicr_image_batch_size', max(1, min(20, absint($_POST['batch_size'] ?? 5))));

        // 스킵 조건
        update_option('aicr_image_skip_with_thumbnail', !empty($_POST['skip_with_thumbnail']));
        update_option('aicr_image_skip_with_images', !empty($_POST['skip_with_images']));

        // 스케줄 상태 변경 처리
        $scheduler = new ImageScheduler();
        if ($schedule_enabled && !$current_enabled) {
            $scheduler->enableSchedule();
        } elseif (!$schedule_enabled && $current_enabled) {
            $scheduler->disableSchedule();
        } elseif ($schedule_enabled) {
            $scheduler->updateInterval(sanitize_text_field($_POST['schedule_interval'] ?? 'hourly'));
        }

        $this->send_json_response(true, ['message' => __('이미지 설정이 저장되었습니다.', 'ai-content-rewriter')]);
    }

    /**
     * 이미지 설정 조회
     */
    public function get_image_settings(): void {
        $this->verify_request();

        $settings = [
            'enabled' => (bool) get_option('aicr_image_enabled', true),
            'default_count' => (int) get_option('aicr_image_default_count', 2),
            'default_ratio' => get_option('aicr_image_default_ratio', '16:9'),
            'default_style' => get_option('aicr_image_default_style', '일러스트레이션'),
            'auto_featured' => (bool) get_option('aicr_image_auto_featured', true),
            'auto_alt' => (bool) get_option('aicr_image_auto_alt', true),
            'auto_caption' => (bool) get_option('aicr_image_auto_caption', true),
            'schedule_enabled' => (bool) get_option('aicr_image_schedule_enabled', false),
            'schedule_interval' => get_option('aicr_image_schedule_interval', 'hourly'),
            'batch_size' => (int) get_option('aicr_image_batch_size', 5),
            'skip_with_thumbnail' => (bool) get_option('aicr_image_skip_with_thumbnail', true),
            'skip_with_images' => (bool) get_option('aicr_image_skip_with_images', true),
        ];

        $manager = ImagePromptManager::get_instance();

        $this->send_json_response(true, [
            'settings' => $settings,
            'prompt' => $manager->get_prompt(),
            'default_prompt' => $manager->get_default_prompt(),
            'styles' => $manager->get_all_styles(),
        ]);
    }

    /**
     * 이미지 프롬프트 저장
     */
    public function save_image_prompt(): void {
        $this->verify_request();

        $prompt = wp_unslash($_POST['prompt'] ?? '');

        if (empty(trim($prompt))) {
            wp_send_json_error(['message' => __('프롬프트를 입력해주세요.', 'ai-content-rewriter')]);
        }

        $manager = ImagePromptManager::get_instance();
        $manager->save_prompt($prompt);

        $this->send_json_response(true, ['message' => __('이미지 프롬프트가 저장되었습니다.', 'ai-content-rewriter')]);
    }

    /**
     * 이미지 프롬프트 초기화
     */
    public function reset_image_prompt(): void {
        $this->verify_request();

        delete_option('aicr_image_prompt');

        $manager = ImagePromptManager::get_instance();

        $this->send_json_response(true, [
            'message' => __('기본 이미지 프롬프트로 복원되었습니다.', 'ai-content-rewriter'),
            'prompt' => $manager->get_default_prompt(),
        ]);
    }

    /**
     * 이미지 생성 스케줄 수동 실행
     */
    public function run_image_generation(): void {
        $this->verify_request();

        try {
            $scheduler = new ImageScheduler();
            $result = $scheduler->runNow();

            $this->send_json_response(true, $result);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * 이미지 생성 스케줄 상태 조회
     */
    public function get_image_generation_status(): void {
        $this->verify_request();

        $scheduler = new ImageScheduler();
        $status = $scheduler->getStatus();

        $this->send_json_response(true, $status);
    }

    /**
     * Gemini Imagen API 연결 테스트
     */
    public function test_imagen_api(): void {
        $this->verify_request();

        try {
            $adapter = new GeminiImageAdapter();
            $result = $adapter->testImagenAvailability();

            if ($result['success']) {
                $this->send_json_response(true, [
                    'message' => __('Gemini Imagen API 연결 성공!', 'ai-content-rewriter'),
                    'details' => $result,
                ]);
            } else {
                $this->send_json_response(false, [
                    'message' => $result['error'] ?? __('API 연결 실패. API 키를 확인해주세요.', 'ai-content-rewriter'),
                    'details' => $result,
                ]);
            }
        } catch (\Exception $e) {
            $this->send_json_response(false, [
                'message' => $e->getMessage(),
            ]);
        }
    }

    // =========================================================================
    // 점진적 이미지 생성 (Progressive Image Generation)
    // 다중 이미지 생성 시 HTTP 타임아웃 방지를 위해 하나씩 생성
    // =========================================================================

    /**
     * 점진적 이미지 생성 준비
     * 콘텐츠를 분석하고 세션을 시작합니다
     */
    public function prepare_progressive_images(): void {
        $this->verify_request();

        $post_id = absint($_POST['post_id'] ?? 0);

        if (!$post_id) {
            wp_send_json_error(['message' => __('게시글 ID가 필요합니다.', 'ai-content-rewriter')]);
        }

        // 게시글 편집 권한 확인
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => __('이 게시글을 편집할 권한이 없습니다.', 'ai-content-rewriter')]);
        }

        $options = [
            'count' => absint($_POST['count'] ?? 2),
            'style' => sanitize_text_field($_POST['style'] ?? ''),
            'ratio' => sanitize_text_field($_POST['ratio'] ?? '16:9'),
            'instructions' => sanitize_textarea_field($_POST['instructions'] ?? ''),
        ];

        // 이미지 수 제한 (1-5)
        $options['count'] = max(1, min(5, $options['count']));

        // 비율 화이트리스트
        $allowed_ratios = ['1:1', '3:4', '4:3', '9:16', '16:9'];
        if (!in_array($options['ratio'], $allowed_ratios, true)) {
            $options['ratio'] = '16:9';
        }

        try {
            $generator = new ImageGenerator();
            $result = $generator->prepareProgressiveGeneration($post_id, $options);

            $this->send_json_response(true, [
                'message' => sprintf(
                    __('%d개의 이미지 생성을 준비했습니다.', 'ai-content-rewriter'),
                    $result['total_count']
                ),
                'session_key' => $result['session_key'],
                'total_count' => $result['total_count'],
                'sections' => $result['sections'],
            ]);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * 단일 이미지 생성 (점진적 생성의 각 단계)
     */
    public function generate_single_image(): void {
        $this->verify_request();

        $session_key = sanitize_text_field($_POST['session_key'] ?? '');
        $index = absint($_POST['index'] ?? 0);

        if (empty($session_key)) {
            wp_send_json_error(['message' => __('세션 키가 필요합니다.', 'ai-content-rewriter')]);
        }

        try {
            $generator = new ImageGenerator();
            $imageData = $generator->generateSingleImage($session_key, $index);

            $this->send_json_response(true, [
                'message' => sprintf(
                    __('이미지 %d 생성 완료', 'ai-content-rewriter'),
                    $index + 1
                ),
                'image' => $imageData,
                'index' => $index,
            ]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'index' => $index,
            ]);
        }
    }

    /**
     * 점진적 이미지 생성 완료 - 콘텐츠에 삽입
     */
    public function finalize_progressive_images(): void {
        $this->verify_request();

        $session_key = sanitize_text_field($_POST['session_key'] ?? '');

        if (empty($session_key)) {
            wp_send_json_error(['message' => __('세션 키가 필요합니다.', 'ai-content-rewriter')]);
        }

        try {
            $generator = new ImageGenerator();
            $result = $generator->finalizeProgressiveGeneration($session_key);

            $this->send_json_response(true, [
                'message' => sprintf(
                    __('%d개의 이미지가 콘텐츠에 삽입되었습니다.', 'ai-content-rewriter'),
                    $result['count']
                ),
                'images' => $result['images'],
                'post_id' => $result['post_id'],
                'count' => $result['count'],
            ]);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * 점진적 이미지 생성 취소
     */
    public function cancel_progressive_images(): void {
        $this->verify_request();

        $session_key = sanitize_text_field($_POST['session_key'] ?? '');

        if (empty($session_key)) {
            wp_send_json_error(['message' => __('세션 키가 필요합니다.', 'ai-content-rewriter')]);
        }

        try {
            $generator = new ImageGenerator();
            $generator->cancelProgressiveGeneration($session_key);

            $this->send_json_response(true, [
                'message' => __('이미지 생성이 취소되었습니다.', 'ai-content-rewriter'),
            ]);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // Cloudflare Worker 설정 (v2.0)
    // =========================================================================

    /**
     * Worker nonce 검증 (Worker 설정 전용)
     */
    private function verify_worker_request(): void {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'aicr_worker_nonce')) {
            wp_send_json_error(['message' => __('보안 검증에 실패했습니다.', 'ai-content-rewriter')]);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('권한이 없습니다.', 'ai-content-rewriter')]);
        }
    }

    /**
     * Worker 연결 테스트
     */
    public function test_worker_connection(): void {
        $this->verify_worker_request();

        try {
            $config = new WorkerConfig();
            $result = $config->test_connection();

            if ($result['success']) {
                // 연결 상태 캐싱 (5분)
                set_transient('aicr_worker_connection_status', [
                    'success' => true,
                    'tested_at' => current_time('mysql'),
                    'data' => $result['data'] ?? [],
                ], 300);

                $this->send_json_response(true, [
                    'message' => $result['message'],
                    'worker_version' => $result['data']['worker_version'] ?? 'unknown',
                    'worker_status' => $result['data']['worker_status'] ?? 'unknown',
                ]);
            } else {
                $this->send_json_response(false, [
                    'message' => $result['message'],
                ]);
            }

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Worker 설정 동기화
     */
    public function sync_worker_config(): void {
        $this->verify_worker_request();

        try {
            $mode = ProcessingMode::get_instance();
            $result = $mode->sync_config();

            if ($result['success']) {
                $this->send_json_response(true, [
                    'message' => $result['message'] ?? __('설정이 Worker에 동기화되었습니다.', 'ai-content-rewriter'),
                ]);
            } else {
                $this->send_json_response(false, [
                    'message' => $result['error'] ?? __('동기화에 실패했습니다.', 'ai-content-rewriter'),
                ]);
            }

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * HMAC Secret 재생성
     */
    public function regenerate_hmac(): void {
        $this->verify_worker_request();

        try {
            $config = new WorkerConfig();
            $new_secret = $config->regenerate_hmac_secret();

            $this->send_json_response(true, [
                'secret' => $new_secret,
                'message' => __('HMAC Secret이 재생성되었습니다.', 'ai-content-rewriter'),
            ]);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * WordPress API Key 재생성
     */
    public function regenerate_api_key(): void {
        $this->verify_worker_request();

        try {
            $config = new WorkerConfig();
            $new_key = $config->regenerate_wp_api_key();

            $this->send_json_response(true, [
                'key' => $new_key,
                'message' => __('API Key가 재생성되었습니다.', 'ai-content-rewriter'),
            ]);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Worker 설정 저장
     */
    public function save_worker_settings(): void {
        $this->verify_request();

        try {
            $config = new WorkerConfig();

            // Worker URL
            $worker_url = esc_url_raw($_POST['worker_url'] ?? '');
            if (!empty($worker_url)) {
                $config->set_worker_url($worker_url);
            }

            // Worker Secret
            $worker_secret = sanitize_text_field($_POST['worker_secret'] ?? '');
            if (!empty($worker_secret)) {
                $config->set_worker_secret($worker_secret);
            }

            // 처리 모드
            $processing_mode = sanitize_text_field($_POST['processing_mode'] ?? 'local');
            if (in_array($processing_mode, [WorkerConfig::MODE_LOCAL, WorkerConfig::MODE_CLOUDFLARE], true)) {
                $config->set_processing_mode($processing_mode);
            }

            // 자동 게시 설정
            $auto_publish = !empty($_POST['worker_auto_publish']);
            $config->set_auto_publish($auto_publish);

            // 게시 품질 임계값
            $publish_threshold = absint($_POST['publish_threshold'] ?? 8);
            $config->set_publish_threshold($publish_threshold);

            // 일일 게시 한도
            $daily_limit = absint($_POST['daily_publish_limit'] ?? 10);
            $config->set_daily_limit($daily_limit);

            // 큐레이션 임계값
            $curation_threshold = floatval($_POST['curation_threshold'] ?? 0.8);
            $config->set_curation_threshold($curation_threshold);

            $this->send_json_response(true, [
                'message' => __('Worker 설정이 저장되었습니다.', 'ai-content-rewriter'),
            ]);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}
