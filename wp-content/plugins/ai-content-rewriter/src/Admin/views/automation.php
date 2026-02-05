<?php
/**
 * Automation Settings Tab
 *
 * Cron 상태 모니터링 및 외부 Cron 설정 가이드
 *
 * @package AIContentRewriter\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

use AIContentRewriter\Cron\CronMonitor;

$monitor = new CronMonitor();
$health = $monitor->get_health_status();
$cron_urls = $monitor->get_cron_urls();

// 상태별 색상 및 아이콘
$status_classes = [
    'healthy' => ['class' => 'aicr-status-healthy', 'icon' => 'yes-alt', 'color' => '#00a32a'],
    'warning' => ['class' => 'aicr-status-warning', 'icon' => 'warning', 'color' => '#dba617'],
    'critical' => ['class' => 'aicr-status-critical', 'icon' => 'dismiss', 'color' => '#d63638'],
];

$current_status = $status_classes[$health['overall_status']] ?? $status_classes['warning'];
?>

<h2><?php esc_html_e('자동화 설정', 'ai-content-rewriter'); ?></h2>

<!-- 전체 상태 표시 -->
<div class="aicr-automation-status <?php echo esc_attr($current_status['class']); ?>">
    <span class="dashicons dashicons-<?php echo esc_attr($current_status['icon']); ?>" style="color: <?php echo esc_attr($current_status['color']); ?>"></span>
    <span class="aicr-status-text">
        <?php
        printf(
            /* translators: %s: status label */
            esc_html__('Cron 상태: %s', 'ai-content-rewriter'),
            '<strong>' . esc_html(CronMonitor::get_status_label($health['overall_status'])) . '</strong>'
        );
        ?>
    </span>
    <button type="button" id="aicr-refresh-cron-status" class="button button-small">
        <span class="dashicons dashicons-update"></span>
        <?php esc_html_e('새로고침', 'ai-content-rewriter'); ?>
    </button>
</div>

<!-- 스케줄 카드 -->
<div class="aicr-schedule-cards" id="aicr-schedule-cards">
    <?php foreach ($health['schedules'] as $hook => $schedule): ?>
        <div class="aicr-schedule-card" data-hook="<?php echo esc_attr($hook); ?>">
            <h4><?php echo esc_html($schedule['label']); ?></h4>
            <div class="aicr-schedule-info">
                <p>
                    <span class="dashicons dashicons-clock"></span>
                    <?php
                    $intervals = [
                        'aicr_fetch_feeds' => __('15분마다', 'ai-content-rewriter'),
                        'aicr_auto_rewrite_items' => __('30분마다', 'ai-content-rewriter'),
                        'aicr_cleanup_old_items' => __('매일', 'ai-content-rewriter'),
                    ];
                    echo esc_html($intervals[$hook] ?? __('알 수 없음', 'ai-content-rewriter'));
                    ?>
                </p>
                <p>
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <?php
                    if ($schedule['next_run']) {
                        printf(
                            /* translators: %s: next run time */
                            esc_html__('다음 실행: %s', 'ai-content-rewriter'),
                            esc_html($schedule['next_run'])
                        );
                    } else {
                        esc_html_e('예약되지 않음', 'ai-content-rewriter');
                    }
                    ?>
                </p>
                <p>
                    <?php if ($schedule['last_run']): ?>
                        <span class="dashicons dashicons-<?php echo $schedule['last_status'] === 'completed' ? 'yes' : 'no'; ?>"
                              style="color: <?php echo $schedule['last_status'] === 'completed' ? '#00a32a' : '#d63638'; ?>"></span>
                        <?php
                        printf(
                            /* translators: %s: last run time */
                            esc_html__('이전: %s', 'ai-content-rewriter'),
                            esc_html($schedule['last_run'])
                        );
                        if ($schedule['last_items'] !== null) {
                            echo ' (' . esc_html($schedule['last_items']) . ' ' . esc_html__('건', 'ai-content-rewriter') . ')';
                        }
                        ?>
                    <?php else: ?>
                        <span class="dashicons dashicons-minus"></span>
                        <?php esc_html_e('실행 기록 없음', 'ai-content-rewriter'); ?>
                    <?php endif; ?>
                </p>
            </div>
            <button type="button" class="button aicr-run-now"
                    data-task="<?php echo esc_attr(str_replace(['aicr_', '_feeds', '_items', '_old_items'], ['', '', '', ''], $hook)); ?>">
                <span class="dashicons dashicons-controls-play"></span>
                <?php esc_html_e('지금 실행', 'ai-content-rewriter'); ?>
            </button>
        </div>
    <?php endforeach; ?>
</div>

<!-- 권고사항 -->
<?php if (!empty($health['recommendations'])): ?>
<div class="aicr-recommendations">
    <h3><span class="dashicons dashicons-lightbulb"></span> <?php esc_html_e('권고사항', 'ai-content-rewriter'); ?></h3>
    <ul>
        <?php foreach ($health['recommendations'] as $rec): ?>
            <li class="aicr-recommendation-<?php echo esc_attr($rec['type']); ?>">
                <span class="dashicons dashicons-<?php
                    echo $rec['type'] === 'error' ? 'warning' : ($rec['type'] === 'warning' ? 'info' : 'lightbulb');
                ?>"></span>
                <?php echo esc_html($rec['message']); ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<hr>

<!-- 외부 Cron 설정 가이드 -->
<h3><span class="dashicons dashicons-book"></span> <?php esc_html_e('외부 Cron 설정 가이드', 'ai-content-rewriter'); ?></h3>

<p class="description">
    <?php esc_html_e('WP-Cron은 사이트 방문에 의존합니다. 안정적인 자동화를 위해 외부 Cron 서비스 설정을 권장합니다.', 'ai-content-rewriter'); ?>
</p>

<div class="aicr-guide-tabs">
    <nav class="aicr-guide-tab-nav">
        <button type="button" class="aicr-guide-tab active" data-tab="cpanel"><?php esc_html_e('cPanel', 'ai-content-rewriter'); ?></button>
        <button type="button" class="aicr-guide-tab" data-tab="easycron"><?php esc_html_e('EasyCron', 'ai-content-rewriter'); ?></button>
        <button type="button" class="aicr-guide-tab" data-tab="cronjob"><?php esc_html_e('Cron-Job.org', 'ai-content-rewriter'); ?></button>
        <button type="button" class="aicr-guide-tab" data-tab="wpconfig"><?php esc_html_e('wp-config.php', 'ai-content-rewriter'); ?></button>
    </nav>

    <div class="aicr-guide-content">
        <!-- cPanel -->
        <div class="aicr-guide-panel active" id="guide-cpanel">
            <h4><?php esc_html_e('cPanel Cron Jobs 설정', 'ai-content-rewriter'); ?></h4>
            <ol>
                <li><?php esc_html_e('cPanel에 로그인합니다.', 'ai-content-rewriter'); ?></li>
                <li><?php esc_html_e('"Cron Jobs" 메뉴를 찾아 클릭합니다.', 'ai-content-rewriter'); ?></li>
                <li><?php esc_html_e('주기를 선택합니다: "*/5 * * * *" (5분마다) 권장', 'ai-content-rewriter'); ?></li>
                <li><?php esc_html_e('명령어 입력란에 아래 내용을 붙여넣습니다:', 'ai-content-rewriter'); ?></li>
            </ol>
            <div class="aicr-code-block">
                <code>wget -q -O /dev/null "<?php echo esc_url($cron_urls['plugin_endpoint']); ?>" &gt;/dev/null 2&gt;&amp;1</code>
                <button type="button" class="button aicr-copy-code">
                    <span class="dashicons dashicons-clipboard"></span>
                </button>
            </div>
            <p class="description"><?php esc_html_e('또는 curl 사용:', 'ai-content-rewriter'); ?></p>
            <div class="aicr-code-block">
                <code>curl -s "<?php echo esc_url($cron_urls['plugin_endpoint']); ?>" &gt;/dev/null 2&gt;&amp;1</code>
                <button type="button" class="button aicr-copy-code">
                    <span class="dashicons dashicons-clipboard"></span>
                </button>
            </div>
        </div>

        <!-- EasyCron -->
        <div class="aicr-guide-panel" id="guide-easycron">
            <h4><?php esc_html_e('EasyCron 설정', 'ai-content-rewriter'); ?></h4>
            <ol>
                <li><a href="https://www.easycron.com" target="_blank" rel="noopener">EasyCron.com</a><?php esc_html_e('에 가입합니다. (무료 플랜 이용 가능)', 'ai-content-rewriter'); ?></li>
                <li><?php esc_html_e('"+ Cron Job" 버튼을 클릭합니다.', 'ai-content-rewriter'); ?></li>
                <li><?php esc_html_e('URL 입력란에 아래 주소를 붙여넣습니다:', 'ai-content-rewriter'); ?></li>
            </ol>
            <div class="aicr-code-block">
                <code><?php echo esc_url($cron_urls['plugin_endpoint']); ?></code>
                <button type="button" class="button aicr-copy-code">
                    <span class="dashicons dashicons-clipboard"></span>
                </button>
            </div>
            <ol start="4">
                <li><?php esc_html_e('주기를 "Every 5 minutes"로 설정합니다.', 'ai-content-rewriter'); ?></li>
                <li><?php esc_html_e('"Create Cron Job" 버튼을 클릭합니다.', 'ai-content-rewriter'); ?></li>
            </ol>
        </div>

        <!-- Cron-Job.org -->
        <div class="aicr-guide-panel" id="guide-cronjob">
            <h4><?php esc_html_e('Cron-Job.org 설정', 'ai-content-rewriter'); ?></h4>
            <ol>
                <li><a href="https://cron-job.org" target="_blank" rel="noopener">Cron-Job.org</a><?php esc_html_e('에 가입합니다. (무료)', 'ai-content-rewriter'); ?></li>
                <li><?php esc_html_e('"Create cronjob" 버튼을 클릭합니다.', 'ai-content-rewriter'); ?></li>
                <li><?php esc_html_e('Title에 "AI Content Rewriter"를 입력합니다.', 'ai-content-rewriter'); ?></li>
                <li><?php esc_html_e('URL 입력란에 아래 주소를 붙여넣습니다:', 'ai-content-rewriter'); ?></li>
            </ol>
            <div class="aicr-code-block">
                <code><?php echo esc_url($cron_urls['plugin_endpoint']); ?></code>
                <button type="button" class="button aicr-copy-code">
                    <span class="dashicons dashicons-clipboard"></span>
                </button>
            </div>
            <ol start="5">
                <li><?php esc_html_e('Schedule에서 "Every 5 minutes"를 선택합니다.', 'ai-content-rewriter'); ?></li>
                <li><?php esc_html_e('"Create" 버튼을 클릭합니다.', 'ai-content-rewriter'); ?></li>
            </ol>
        </div>

        <!-- wp-config.php -->
        <div class="aicr-guide-panel" id="guide-wpconfig">
            <h4><?php esc_html_e('wp-config.php 설정 (권장)', 'ai-content-rewriter'); ?></h4>
            <p><?php esc_html_e('외부 Cron을 설정한 후, WP-Cron을 비활성화하면 더 안정적으로 동작합니다.', 'ai-content-rewriter'); ?></p>
            <p><?php esc_html_e('wp-config.php 파일에 아래 코드를 추가하세요:', 'ai-content-rewriter'); ?></p>
            <div class="aicr-code-block">
                <code>define('DISABLE_WP_CRON', true);</code>
                <button type="button" class="button aicr-copy-code">
                    <span class="dashicons dashicons-clipboard"></span>
                </button>
            </div>
            <p class="description">
                <span class="dashicons dashicons-warning" style="color: #dba617;"></span>
                <?php esc_html_e('주의: 외부 Cron을 먼저 설정한 후에 이 옵션을 활성화하세요.', 'ai-content-rewriter'); ?>
            </p>
            <?php if ($health['wp_cron_disabled']): ?>
                <p style="color: #00a32a;">
                    <span class="dashicons dashicons-yes"></span>
                    <?php esc_html_e('DISABLE_WP_CRON이 이미 활성화되어 있습니다.', 'ai-content-rewriter'); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Cron URL 표시 -->
<div class="aicr-cron-url-section">
    <h4><span class="dashicons dashicons-admin-links"></span> <?php esc_html_e('Cron URL (외부 서비스에서 사용)', 'ai-content-rewriter'); ?></h4>
    <div class="aicr-cron-url-box">
        <input type="text" id="aicr-cron-url" class="large-text" readonly
               value="<?php echo esc_url($cron_urls['plugin_endpoint']); ?>">
        <button type="button" id="aicr-copy-cron-url" class="button">
            <span class="dashicons dashicons-clipboard"></span>
            <?php esc_html_e('복사', 'ai-content-rewriter'); ?>
        </button>
        <button type="button" id="aicr-regenerate-token" class="button">
            <span class="dashicons dashicons-update"></span>
            <?php esc_html_e('토큰 재생성', 'ai-content-rewriter'); ?>
        </button>
    </div>
    <p class="description">
        <?php esc_html_e('이 URL에는 보안 토큰이 포함되어 있습니다. URL이 노출된 경우 "토큰 재생성" 버튼을 클릭하세요.', 'ai-content-rewriter'); ?>
    </p>
</div>

<hr>

<!-- 실행 이력 -->
<h3><span class="dashicons dashicons-list-view"></span> <?php esc_html_e('최근 실행 이력 (24시간)', 'ai-content-rewriter'); ?></h3>

<div class="aicr-cron-logs-section">
    <table class="wp-list-table widefat fixed striped" id="aicr-cron-logs-table">
        <thead>
            <tr>
                <th style="width: 150px;"><?php esc_html_e('시간', 'ai-content-rewriter'); ?></th>
                <th style="width: 150px;"><?php esc_html_e('작업', 'ai-content-rewriter'); ?></th>
                <th style="width: 80px;"><?php esc_html_e('상태', 'ai-content-rewriter'); ?></th>
                <th style="width: 80px;"><?php esc_html_e('처리 건수', 'ai-content-rewriter'); ?></th>
                <th style="width: 100px;"><?php esc_html_e('소요 시간', 'ai-content-rewriter'); ?></th>
                <th><?php esc_html_e('메시지', 'ai-content-rewriter'); ?></th>
            </tr>
        </thead>
        <tbody id="aicr-cron-logs-body">
            <tr>
                <td colspan="6" style="text-align: center;">
                    <span class="spinner is-active" style="float: none;"></span>
                    <?php esc_html_e('로그를 불러오는 중...', 'ai-content-rewriter'); ?>
                </td>
            </tr>
        </tbody>
    </table>

    <p class="aicr-log-actions">
        <button type="button" id="aicr-refresh-logs" class="button">
            <span class="dashicons dashicons-update"></span>
            <?php esc_html_e('새로고침', 'ai-content-rewriter'); ?>
        </button>
        <button type="button" id="aicr-clear-logs" class="button">
            <span class="dashicons dashicons-trash"></span>
            <?php esc_html_e('로그 삭제', 'ai-content-rewriter'); ?>
        </button>
    </p>
</div>
