<?php
/**
 * Database Schema
 *
 * @package AIContentRewriter\Database
 */

namespace AIContentRewriter\Database;

/**
 * 데이터베이스 스키마 관리 클래스
 */
class Schema {
    /**
     * 테이블 생성
     */
    public static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // 히스토리 테이블
        self::create_history_table($charset_collate);

        // 스케줄 테이블
        self::create_schedules_table($charset_collate);

        // 프롬프트 템플릿 테이블
        self::create_templates_table($charset_collate);

        // API 사용량 테이블
        self::create_api_usage_table($charset_collate);

        // RSS 피드 테이블
        self::create_feeds_table($charset_collate);

        // RSS 피드 아이템 테이블
        self::create_feed_items_table($charset_collate);
    }

    /**
     * 히스토리 테이블 생성
     */
    private static function create_history_table(string $charset_collate): void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aicr_history';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            source_type varchar(20) NOT NULL DEFAULT 'url',
            source_url varchar(2083) DEFAULT NULL,
            source_content longtext,
            result_content longtext,
            result_post_id bigint(20) unsigned DEFAULT NULL,
            ai_provider varchar(50) NOT NULL,
            ai_model varchar(100) DEFAULT NULL,
            prompt_template_id bigint(20) unsigned DEFAULT NULL,
            tokens_used int(11) DEFAULT 0,
            processing_time float DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'pending',
            error_message text DEFAULT NULL,
            metadata json DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at),
            KEY result_post_id (result_post_id)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * 스케줄 테이블 생성
     */
    private static function create_schedules_table(string $charset_collate): void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aicr_schedules';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            name varchar(255) NOT NULL,
            source_type varchar(20) NOT NULL DEFAULT 'url',
            source_url varchar(2083) DEFAULT NULL,
            ai_provider varchar(50) NOT NULL,
            prompt_template_id bigint(20) unsigned DEFAULT NULL,
            target_language varchar(10) DEFAULT 'ko',
            post_status varchar(20) DEFAULT 'draft',
            post_category bigint(20) unsigned DEFAULT NULL,
            schedule_type varchar(20) NOT NULL DEFAULT 'once',
            schedule_interval varchar(50) DEFAULT NULL,
            next_run datetime DEFAULT NULL,
            last_run datetime DEFAULT NULL,
            run_count int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            metadata json DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY is_active (is_active),
            KEY next_run (next_run)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * 프롬프트 템플릿 테이블 생성
     */
    private static function create_templates_table(string $charset_collate): void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aicr_templates';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            name varchar(255) NOT NULL,
            type varchar(50) NOT NULL DEFAULT 'rewrite',
            content longtext NOT NULL,
            variables json DEFAULT NULL,
            is_default tinyint(1) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            usage_count int(11) DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY is_active (is_active)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * API 사용량 테이블 생성
     */
    private static function create_api_usage_table(string $charset_collate): void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aicr_api_usage';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            ai_provider varchar(50) NOT NULL,
            ai_model varchar(100) DEFAULT NULL,
            request_type varchar(50) NOT NULL,
            tokens_input int(11) DEFAULT 0,
            tokens_output int(11) DEFAULT 0,
            cost_estimate decimal(10,6) DEFAULT 0,
            response_time float DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'success',
            error_code varchar(50) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY ai_provider (ai_provider),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * RSS 피드 테이블 생성
     */
    private static function create_feeds_table(string $charset_collate): void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aicr_feeds';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            name varchar(255) NOT NULL,
            feed_url varchar(2083) NOT NULL,
            site_url varchar(2083) DEFAULT NULL,
            site_name varchar(255) DEFAULT NULL,
            feed_type varchar(20) DEFAULT 'rss2',
            status varchar(20) DEFAULT 'active',
            last_fetched_at datetime DEFAULT NULL,
            last_error varchar(500) DEFAULT NULL,
            fetch_interval int(11) DEFAULT 3600,
            auto_rewrite tinyint(1) DEFAULT 0,
            auto_publish tinyint(1) DEFAULT 0,
            default_category bigint(20) unsigned DEFAULT NULL,
            default_template_id bigint(20) unsigned DEFAULT NULL,
            default_language varchar(10) DEFAULT 'ko',
            item_count int(11) DEFAULT 0,
            unread_count int(11) DEFAULT 0,
            metadata json DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id_status (user_id, status),
            KEY last_fetched_at (last_fetched_at)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * RSS 피드 아이템 테이블 생성
     */
    private static function create_feed_items_table(string $charset_collate): void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aicr_feed_items';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            feed_id bigint(20) unsigned NOT NULL,
            guid varchar(255) NOT NULL,
            title varchar(500) NOT NULL,
            link varchar(2083) NOT NULL,
            content longtext DEFAULT NULL,
            summary text DEFAULT NULL,
            author varchar(255) DEFAULT NULL,
            pub_date datetime DEFAULT NULL,
            categories json DEFAULT NULL,
            enclosures json DEFAULT NULL,
            thumbnail_url varchar(2083) DEFAULT NULL,
            status varchar(20) DEFAULT 'unread',
            rewritten_post_id bigint(20) unsigned DEFAULT NULL,
            error_message varchar(500) DEFAULT NULL,
            metadata json DEFAULT NULL,
            fetched_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY feed_guid (feed_id, guid(191)),
            KEY feed_id_status (feed_id, status),
            KEY pub_date (pub_date DESC),
            KEY status (status)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * 테이블 삭제 (플러그인 삭제 시)
     */
    public static function drop_tables(): void {
        global $wpdb;

        // 외래키 참조 순서 고려하여 역순 삭제
        $tables = [
            $wpdb->prefix . 'aicr_feed_items',
            $wpdb->prefix . 'aicr_feeds',
            $wpdb->prefix . 'aicr_api_usage',
            $wpdb->prefix . 'aicr_templates',
            $wpdb->prefix . 'aicr_schedules',
            $wpdb->prefix . 'aicr_history',
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
    }
}
