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
use AIContentRewriter\AI\AIFactory;
use AIContentRewriter\Security\Encryption;
use AIContentRewriter\Security\RateLimiter;

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

        // 템플릿 관리
        add_action('wp_ajax_aicr_get_templates', [$this, 'get_templates']);
        add_action('wp_ajax_aicr_save_template', [$this, 'save_template']);
        add_action('wp_ajax_aicr_delete_template', [$this, 'delete_template']);

        // 비동기 콘텐츠 재작성 (공통 모듈 사용)
        add_action('wp_ajax_aicr_start_content_task', [$this, 'start_content_task']);
        add_action('wp_ajax_aicr_check_content_status', [$this, 'check_content_status']);
        add_action('wp_ajax_aicr_process_shared_rewrite', [$this, 'process_shared_rewrite']);
        add_action('wp_ajax_nopriv_aicr_process_shared_rewrite', [$this, 'process_shared_rewrite']);
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
     * 템플릿 목록 조회
     */
    public function get_templates(): void {
        $this->verify_request();

        global $wpdb;

        $table_name = $wpdb->prefix . 'aicr_templates';
        $user_id = get_current_user_id();

        $templates = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE user_id = %d AND is_active = 1 ORDER BY name ASC",
                $user_id
            ),
            ARRAY_A
        );

        $this->send_json_response(true, ['templates' => $templates]);
    }

    /**
     * 허용된 템플릿 타입 목록
     */
    private function get_allowed_template_types(): array {
        return ['rewrite', 'translate', 'seo', 'summary', 'metadata', 'custom'];
    }

    /**
     * 템플릿 저장
     */
    public function save_template(): void {
        $this->verify_request();

        global $wpdb;

        $id = absint($_POST['template_id'] ?? 0);
        $name = sanitize_text_field($_POST['template_name'] ?? '');
        $type = sanitize_text_field($_POST['template_type'] ?? 'rewrite');
        $content = wp_kses_post($_POST['template_content'] ?? '');

        // 화이트리스트 검증
        if (!in_array($type, $this->get_allowed_template_types(), true)) {
            $type = 'rewrite';
        }

        if (empty($name) || empty($content)) {
            $this->send_json_response(false, ['message' => __('필수 항목을 입력해주세요.', 'ai-content-rewriter')]);
        }

        $table_name = $wpdb->prefix . 'aicr_templates';
        $user_id = get_current_user_id();

        // 기존 템플릿 수정 시 소유권 검증
        if ($id) {
            $owner_id = $wpdb->get_var(
                $wpdb->prepare("SELECT user_id FROM {$table_name} WHERE id = %d", $id)
            );

            if ($owner_id === null) {
                $this->send_json_response(false, ['message' => __('템플릿을 찾을 수 없습니다.', 'ai-content-rewriter')]);
            }

            if (!current_user_can('manage_options') && (int) $owner_id !== $user_id) {
                $this->send_json_response(false, ['message' => __('해당 템플릿을 수정할 권한이 없습니다.', 'ai-content-rewriter')]);
            }
        }

        $data = [
            'user_id' => $user_id,
            'name' => $name,
            'type' => $type,
            'content' => $content,
            'is_active' => 1,
        ];

        if ($id) {
            $wpdb->update($table_name, $data, ['id' => $id]);
            $template_id = $id;
        } else {
            $wpdb->insert($table_name, $data);
            $template_id = $wpdb->insert_id;
        }

        $this->send_json_response(true, [
            'template_id' => $template_id,
            'message' => __('템플릿이 저장되었습니다.', 'ai-content-rewriter'),
        ]);
    }

    /**
     * 템플릿 삭제
     */
    public function delete_template(): void {
        $this->verify_request();

        global $wpdb;

        $id = absint($_POST['id'] ?? 0);

        if (!$id) {
            $this->send_json_response(false, ['message' => __('잘못된 요청입니다.', 'ai-content-rewriter')]);
        }

        $table_name = $wpdb->prefix . 'aicr_templates';

        // 소유권 검증
        $owner_id = $wpdb->get_var(
            $wpdb->prepare("SELECT user_id FROM {$table_name} WHERE id = %d", $id)
        );

        if ($owner_id === null) {
            $this->send_json_response(false, ['message' => __('템플릿을 찾을 수 없습니다.', 'ai-content-rewriter')]);
        }

        if (!current_user_can('manage_options') && (int) $owner_id !== get_current_user_id()) {
            $this->send_json_response(false, ['message' => __('해당 템플릿을 삭제할 권한이 없습니다.', 'ai-content-rewriter')]);
        }

        $wpdb->update($table_name, ['is_active' => 0], ['id' => $id]);

        $this->send_json_response(true, ['message' => __('템플릿이 삭제되었습니다.', 'ai-content-rewriter')]);
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
}
