<?php
/**
 * Plugin Name: AI Content Rewriter
 * Plugin URI: https://github.com/hansync/ai-content-rewriter
 * Description: URL 또는 텍스트를 AI를 활용하여 블로그 포스트로 변환하는 WordPress 플러그인
 * Version: 1.2.2
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: hansync
 * Author URI: https://github.com/hansync
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-content-rewriter
 * Domain Path: /languages
 *
 * @package AIContentRewriter
 */

namespace AIContentRewriter;

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

// 플러그인 상수 정의
define('AICR_VERSION', '1.2.2');
define('AICR_PLUGIN_FILE', __FILE__);
define('AICR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AICR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AICR_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader 로드
require_once AICR_PLUGIN_DIR . 'src/autoload.php';

/**
 * 플러그인 초기화
 */
function init(): void {
    // 언어 파일 로드
    load_plugin_textdomain(
        'ai-content-rewriter',
        false,
        dirname(AICR_PLUGIN_BASENAME) . '/languages'
    );

    // 플러그인 코어 초기화
    $plugin = Core\Plugin::get_instance();
    $plugin->init();
}
add_action('plugins_loaded', __NAMESPACE__ . '\\init');

/**
 * 플러그인 활성화 시 실행
 */
function activate(): void {
    Core\Activator::activate();
}
register_activation_hook(__FILE__, __NAMESPACE__ . '\\activate');

/**
 * 플러그인 비활성화 시 실행
 */
function deactivate(): void {
    Core\Deactivator::deactivate();
}
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\\deactivate');
