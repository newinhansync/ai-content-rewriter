<?php
/**
 * Admin Menu Handler
 *
 * @package AIContentRewriter\Admin
 */

namespace AIContentRewriter\Admin;

use AIContentRewriter\Admin\ImageMetabox;
use AIContentRewriter\Worker\WorkerConfig;

/**
 * 관리자 메뉴 클래스
 */
class AdminMenu {
    /**
     * 메뉴 슬러그
     */
    public const MENU_SLUG = 'ai-content-rewriter';

    /**
     * 초기화
     */
    public function init(): void {
        add_action('admin_menu', [$this, 'register_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        // 이미지 메타박스 초기화
        $imageMetabox = new ImageMetabox();
        $imageMetabox->init();
    }

    /**
     * 관리자 메뉴 등록
     */
    public function register_menus(): void {
        // 메인 메뉴
        add_menu_page(
            __('AI Content Rewriter', 'ai-content-rewriter'),
            __('AI Rewriter', 'ai-content-rewriter'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render_main_page'],
            'dashicons-edit-page',
            30
        );

        // 서브 메뉴: 콘텐츠 작성
        add_submenu_page(
            self::MENU_SLUG,
            __('새 콘텐츠 작성', 'ai-content-rewriter'),
            __('새 콘텐츠', 'ai-content-rewriter'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render_main_page']
        );

        // 서브 메뉴: RSS 피드
        add_submenu_page(
            self::MENU_SLUG,
            __('RSS 피드 관리', 'ai-content-rewriter'),
            __('RSS 피드', 'ai-content-rewriter'),
            'manage_options',
            self::MENU_SLUG . '-feeds',
            [$this, 'render_feeds_page']
        );

        // 서브 메뉴: 피드 리더
        add_submenu_page(
            self::MENU_SLUG,
            __('피드 리더', 'ai-content-rewriter'),
            __('피드 리더', 'ai-content-rewriter'),
            'manage_options',
            self::MENU_SLUG . '-feed-reader',
            [$this, 'render_feed_reader_page']
        );

        // 서브 메뉴: 히스토리
        add_submenu_page(
            self::MENU_SLUG,
            __('변환 히스토리', 'ai-content-rewriter'),
            __('히스토리', 'ai-content-rewriter'),
            'manage_options',
            self::MENU_SLUG . '-history',
            [$this, 'render_history_page']
        );

        // 서브 메뉴: 스케줄
        add_submenu_page(
            self::MENU_SLUG,
            __('스케줄 관리', 'ai-content-rewriter'),
            __('스케줄', 'ai-content-rewriter'),
            'manage_options',
            self::MENU_SLUG . '-schedule',
            [$this, 'render_schedule_page']
        );

        // 서브 메뉴: 설정
        add_submenu_page(
            self::MENU_SLUG,
            __('설정', 'ai-content-rewriter'),
            __('설정', 'ai-content-rewriter'),
            'manage_options',
            self::MENU_SLUG . '-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * 관리자 에셋 로드
     */
    public function enqueue_assets(string $hook): void {
        // 플러그인 페이지에서만 로드
        if (strpos($hook, self::MENU_SLUG) === false) {
            return;
        }

        wp_enqueue_style(
            'aicr-admin-style',
            AICR_PLUGIN_URL . 'assets/css/admin.css',
            [],
            AICR_VERSION
        );

        wp_enqueue_script(
            'aicr-admin-script',
            AICR_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            AICR_VERSION,
            true
        );

        wp_localize_script('aicr-admin-script', 'aicr_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aicr_nonce'),
            'strings' => [
                'processing' => __('처리 중...', 'ai-content-rewriter'),
                'success' => __('완료', 'ai-content-rewriter'),
                'error' => __('오류가 발생했습니다', 'ai-content-rewriter'),
            ],
        ]);
    }

    /**
     * 메인 페이지 렌더링
     */
    public function render_main_page(): void {
        include AICR_PLUGIN_DIR . 'src/Admin/views/main.php';
    }

    /**
     * 히스토리 페이지 렌더링
     */
    public function render_history_page(): void {
        include AICR_PLUGIN_DIR . 'src/Admin/views/history.php';
    }

    /**
     * 스케줄 페이지 렌더링
     */
    public function render_schedule_page(): void {
        include AICR_PLUGIN_DIR . 'src/Admin/views/schedule.php';
    }

    /**
     * 설정 페이지 렌더링
     */
    public function render_settings_page(): void {
        // 폼 제출 처리
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aicr_settings_nonce'])) {
            if (wp_verify_nonce($_POST['aicr_settings_nonce'], 'aicr_settings') && current_user_can('manage_options')) {
                $this->save_all_settings();
                add_settings_error('aicr_messages', 'aicr_message', __('설정이 저장되었습니다.', 'ai-content-rewriter'), 'updated');
            }
        }

        include AICR_PLUGIN_DIR . 'src/Admin/views/settings.php';
    }

    /**
     * 모든 설정 저장 (Worker 설정 포함)
     */
    private function save_all_settings(): void {
        // API 키 저장
        $chatgpt_key = sanitize_text_field($_POST['chatgpt_api_key'] ?? '');
        $gemini_key = sanitize_text_field($_POST['gemini_api_key'] ?? '');

        if (!empty($chatgpt_key)) {
            \AIContentRewriter\Security\Encryption::save_api_key('aicr_chatgpt_api_key', $chatgpt_key);
        }
        if (!empty($gemini_key)) {
            \AIContentRewriter\Security\Encryption::save_api_key('aicr_gemini_api_key', $gemini_key);
        }

        // 일반 설정
        $general_settings = [
            'aicr_default_ai_provider' => sanitize_text_field($_POST['default_ai_provider'] ?? 'chatgpt'),
            'aicr_default_language' => sanitize_text_field($_POST['default_language'] ?? 'ko'),
            'aicr_default_post_status' => sanitize_text_field($_POST['default_post_status'] ?? 'draft'),
            'aicr_chunk_size' => min(10000, max(1000, absint($_POST['chunk_size'] ?? 3000))),
            'aicr_auto_generate_metadata' => isset($_POST['auto_generate_metadata']) ? '1' : '0',
            'aicr_log_retention_days' => min(365, max(7, absint($_POST['log_retention_days'] ?? 90))),
            'aicr_debug_mode' => isset($_POST['debug_mode']) ? '1' : '0',
        ];

        foreach ($general_settings as $key => $value) {
            update_option($key, $value);
        }

        // RSS 설정
        $rss_settings = [
            'aicr_rss_default_interval' => sanitize_text_field($_POST['rss_default_interval'] ?? '86400'),
            'aicr_rss_max_items_per_feed' => min(500, max(10, absint($_POST['rss_max_items_per_feed'] ?? 50))),
            'aicr_rss_auto_cleanup' => isset($_POST['rss_auto_cleanup']) ? '1' : '0',
            'aicr_rss_item_retention_days' => min(365, max(7, absint($_POST['rss_item_retention_days'] ?? 30))),
            'aicr_rss_concurrent_fetch' => min(20, max(1, absint($_POST['rss_concurrent_fetch'] ?? 5))),
            'aicr_rss_default_auto_rewrite' => isset($_POST['rss_default_auto_rewrite']) ? '1' : '0',
            'aicr_rss_default_auto_publish' => isset($_POST['rss_default_auto_publish']) ? '1' : '0',
            'aicr_rss_rewrite_queue_limit' => min(50, max(1, absint($_POST['rss_rewrite_queue_limit'] ?? 10))),
        ];

        foreach ($rss_settings as $key => $value) {
            update_option($key, $value);
        }

        // Cloudflare Worker 설정
        $config = new WorkerConfig();

        $worker_url = esc_url_raw($_POST['worker_url'] ?? '');
        if (!empty($worker_url)) {
            $config->set_worker_url($worker_url);
        }

        $worker_secret = sanitize_text_field($_POST['worker_secret'] ?? '');
        if (!empty($worker_secret)) {
            $config->set_worker_secret($worker_secret);
        }

        $processing_mode = sanitize_text_field($_POST['processing_mode'] ?? 'local');
        if (in_array($processing_mode, [WorkerConfig::MODE_LOCAL, WorkerConfig::MODE_CLOUDFLARE], true)) {
            $config->set_processing_mode($processing_mode);
        }

        $config->set_auto_publish(!empty($_POST['worker_auto_publish']));
        $config->set_publish_threshold(min(10, max(1, absint($_POST['publish_threshold'] ?? 8))));
        $config->set_daily_limit(max(1, absint($_POST['daily_publish_limit'] ?? 10)));
        $config->set_curation_threshold(min(1.0, max(0.0, floatval($_POST['curation_threshold'] ?? 0.8))));
    }

    /**
     * RSS 피드 페이지 렌더링
     */
    public function render_feeds_page(): void {
        include AICR_PLUGIN_DIR . 'src/Admin/views/feeds.php';
    }

    /**
     * 피드 리더 페이지 렌더링
     */
    public function render_feed_reader_page(): void {
        include AICR_PLUGIN_DIR . 'src/Admin/views/feed-reader.php';
    }
}
