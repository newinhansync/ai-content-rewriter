<?php
/**
 * Admin Menu Handler
 *
 * @package AIContentRewriter\Admin
 */

namespace AIContentRewriter\Admin;

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

        // 서브 메뉴: 프롬프트 템플릿
        add_submenu_page(
            self::MENU_SLUG,
            __('프롬프트 템플릿', 'ai-content-rewriter'),
            __('템플릿', 'ai-content-rewriter'),
            'manage_options',
            self::MENU_SLUG . '-templates',
            [$this, 'render_templates_page']
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
     * 템플릿 페이지 렌더링
     */
    public function render_templates_page(): void {
        include AICR_PLUGIN_DIR . 'src/Admin/views/templates.php';
    }

    /**
     * 설정 페이지 렌더링
     */
    public function render_settings_page(): void {
        include AICR_PLUGIN_DIR . 'src/Admin/views/settings.php';
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
