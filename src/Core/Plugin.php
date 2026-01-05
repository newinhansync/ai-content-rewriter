<?php
/**
 * Plugin Core Class
 *
 * @package AIContentRewriter\Core
 */

namespace AIContentRewriter\Core;

use AIContentRewriter\Admin\AdminMenu;
use AIContentRewriter\Admin\AjaxHandler;
use AIContentRewriter\RSS\AjaxHandler as RssAjaxHandler;
use AIContentRewriter\RSS\FeedScheduler;
use AIContentRewriter\Schedule\Scheduler;

/**
 * Main Plugin class - Singleton
 */
class Plugin {
    /**
     * 싱글톤 인스턴스
     */
    private static ?Plugin $instance = null;

    /**
     * 플러그인 초기화 상태
     */
    private bool $initialized = false;

    /**
     * 생성자 (private for singleton)
     */
    private function __construct() {}

    /**
     * 싱글톤 인스턴스 반환
     */
    public static function get_instance(): Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 플러그인 초기화
     */
    public function init(): void {
        if ($this->initialized) {
            return;
        }

        $this->initialized = true;

        // 버전 업그레이드 체크
        $this->check_version_upgrade();

        // 관리자 영역 훅
        if (is_admin()) {
            $this->init_admin();
        }

        // 스케줄러 초기화
        $this->init_scheduler();

        // REST API 등록
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    /**
     * 버전 업그레이드 체크 및 처리
     */
    private function check_version_upgrade(): void {
        $installed_version = get_option('aicr_version', '0');

        // 버전이 다르면 업그레이드 처리
        if (version_compare($installed_version, AICR_VERSION, '<')) {
            $this->run_upgrade($installed_version);
            update_option('aicr_version', AICR_VERSION);
        }
    }

    /**
     * 업그레이드 처리
     */
    private function run_upgrade(string $from_version): void {
        // 데이터베이스 스키마 업데이트 (새 컬럼/테이블이 추가된 경우)
        \AIContentRewriter\Database\Schema::create_tables();

        // 버전별 마이그레이션 처리
        if (version_compare($from_version, '1.0.5', '<')) {
            // 1.0.5 이전 버전에서 업그레이드 시 필요한 처리
            // RSS 기본 옵션 설정
            $this->migrate_to_1_0_5();
        }

        if (version_compare($from_version, '1.0.6', '<')) {
            // 1.0.6 업그레이드 처리
            $this->migrate_to_1_0_6();
        }
    }

    /**
     * 1.0.5 마이그레이션
     */
    private function migrate_to_1_0_5(): void {
        // RSS 기본 옵션이 없으면 추가
        $rss_defaults = [
            'aicr_rss_fetch_interval' => 60,
            'aicr_rss_max_items' => 20,
            'aicr_rss_auto_cleanup' => true,
            'aicr_rss_retention_days' => 30,
        ];

        foreach ($rss_defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }

    /**
     * 1.0.6 마이그레이션
     */
    private function migrate_to_1_0_6(): void {
        // 1.0.6에서 추가된 설정이 없으면 추가
        if (get_option('aicr_delete_data_on_uninstall') === false) {
            add_option('aicr_delete_data_on_uninstall', false);
        }
    }

    /**
     * 관리자 영역 초기화
     */
    private function init_admin(): void {
        $admin_menu = new AdminMenu();
        $admin_menu->init();

        $ajax_handler = new AjaxHandler();
        $ajax_handler->init();

        // RSS AJAX 핸들러 초기화
        $rss_ajax_handler = new RssAjaxHandler();
        $rss_ajax_handler->init();
    }

    /**
     * 스케줄러 초기화
     */
    private function init_scheduler(): void {
        $scheduler = new Scheduler();
        $scheduler->init();

        // RSS 피드 스케줄러 초기화
        $feed_scheduler = new FeedScheduler();
        $feed_scheduler->init();
    }

    /**
     * REST API 라우트 등록
     */
    public function register_rest_routes(): void {
        // TODO: REST API 엔드포인트 등록 예정
    }
}
