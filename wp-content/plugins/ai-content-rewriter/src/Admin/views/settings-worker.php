<?php
/**
 * Cloudflare Worker Settings Tab
 *
 * @package AIContentRewriter\Admin
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use AIContentRewriter\Worker\WorkerConfig;

$config = new WorkerConfig();
$processing_mode = $config->get_processing_mode();
$is_configured = $config->is_configured();
?>

<h2><?php esc_html_e('Cloudflare Worker 설정', 'ai-content-rewriter'); ?></h2>

<div class="aicr-worker-status-card" style="background: <?php echo $is_configured ? '#d4edda' : '#fff3cd'; ?>; border: 1px solid <?php echo $is_configured ? '#c3e6cb' : '#ffeeba'; ?>; border-radius: 4px; padding: 15px; margin-bottom: 20px;">
    <h3 style="margin-top: 0;">
        <?php if ($is_configured): ?>
            <span class="dashicons dashicons-yes-alt" style="color: #155724;"></span>
            <?php esc_html_e('Worker 연결됨', 'ai-content-rewriter'); ?>
        <?php else: ?>
            <span class="dashicons dashicons-warning" style="color: #856404;"></span>
            <?php esc_html_e('Worker 설정 필요', 'ai-content-rewriter'); ?>
        <?php endif; ?>
    </h3>
    <p style="margin-bottom: 0;">
        <?php if ($is_configured): ?>
            <?php esc_html_e('Cloudflare Worker가 정상적으로 설정되었습니다. 연결 테스트를 실행하여 상태를 확인할 수 있습니다.', 'ai-content-rewriter'); ?>
        <?php else: ?>
            <?php esc_html_e('Cloudflare Worker URL과 Secret을 설정하여 고급 자동화 기능을 사용할 수 있습니다.', 'ai-content-rewriter'); ?>
        <?php endif; ?>
    </p>
</div>

<table class="form-table">
    <!-- 처리 모드 -->
    <tr>
        <th scope="row"><?php esc_html_e('처리 모드', 'ai-content-rewriter'); ?></th>
        <td>
            <fieldset>
                <label>
                    <input type="radio" name="processing_mode" value="local"
                           <?php checked($processing_mode, WorkerConfig::MODE_LOCAL); ?>>
                    <strong><?php esc_html_e('Local', 'ai-content-rewriter'); ?></strong>
                    - <?php esc_html_e('WordPress 서버에서 직접 처리 (기본)', 'ai-content-rewriter'); ?>
                </label>
                <br><br>
                <label>
                    <input type="radio" name="processing_mode" value="cloudflare"
                           <?php checked($processing_mode, WorkerConfig::MODE_CLOUDFLARE); ?>
                           <?php echo !$is_configured ? 'disabled' : ''; ?>>
                    <strong><?php esc_html_e('Cloudflare Worker', 'ai-content-rewriter'); ?></strong>
                    - <?php esc_html_e('Cloudflare에서 비동기 처리 (타임아웃 없음)', 'ai-content-rewriter'); ?>
                    <?php if (!$is_configured): ?>
                        <span style="color: #856404;"><?php esc_html_e('(Worker 설정 필요)', 'ai-content-rewriter'); ?></span>
                    <?php endif; ?>
                </label>
            </fieldset>
            <p class="description">
                <?php esc_html_e('Cloudflare 모드는 장시간 걸리는 AI 처리를 타임아웃 없이 비동기로 실행합니다.', 'ai-content-rewriter'); ?>
            </p>
        </td>
    </tr>
</table>

<hr>

<h3><?php esc_html_e('Worker 연결 설정', 'ai-content-rewriter'); ?></h3>
<table class="form-table">
    <!-- Worker URL -->
    <tr>
        <th scope="row">
            <label for="worker_url"><?php esc_html_e('Worker URL', 'ai-content-rewriter'); ?></label>
        </th>
        <td>
            <input type="url" name="worker_url" id="worker_url" class="regular-text"
                   value="<?php echo esc_attr($config->get_worker_url()); ?>"
                   placeholder="https://your-worker.workers.dev">
            <p class="description">
                <?php esc_html_e('Cloudflare Worker가 배포된 URL을 입력하세요.', 'ai-content-rewriter'); ?>
            </p>
        </td>
    </tr>

    <!-- Worker Secret -->
    <tr>
        <th scope="row">
            <label for="worker_secret"><?php esc_html_e('Worker Secret', 'ai-content-rewriter'); ?></label>
        </th>
        <td>
            <?php $has_secret = !empty($config->get_worker_secret()); ?>
            <input type="password" name="worker_secret" id="worker_secret" class="regular-text"
                   placeholder="<?php echo $has_secret ? '••••••••••••••••' : ''; ?>">
            <button type="button" class="button aicr-toggle-password"><?php esc_html_e('표시', 'ai-content-rewriter'); ?></button>
            <?php if ($has_secret): ?>
                <span class="aicr-key-saved" style="color: green; margin-left: 10px;">✓ <?php esc_html_e('저장됨', 'ai-content-rewriter'); ?></span>
            <?php endif; ?>
            <p class="description">
                <?php esc_html_e('WordPress → Worker 요청 인증에 사용되는 Bearer Token입니다. Worker 배포 시 설정한 값과 동일해야 합니다.', 'ai-content-rewriter'); ?>
            </p>
        </td>
    </tr>
</table>

<hr>

<h3><?php esc_html_e('보안 키 (자동 생성)', 'ai-content-rewriter'); ?></h3>
<p class="description" style="margin-bottom: 15px;">
    <?php esc_html_e('아래 키들은 자동으로 생성됩니다. Worker에 동일한 값을 설정해야 합니다.', 'ai-content-rewriter'); ?>
</p>

<table class="form-table">
    <!-- HMAC Secret -->
    <tr>
        <th scope="row">
            <label for="hmac_secret"><?php esc_html_e('HMAC Secret', 'ai-content-rewriter'); ?></label>
        </th>
        <td>
            <input type="text" id="hmac_secret" class="regular-text code" readonly
                   value="<?php echo esc_attr($config->get_hmac_secret()); ?>">
            <button type="button" class="button" id="aicr-copy-hmac">
                <span class="dashicons dashicons-clipboard" style="vertical-align: middle;"></span>
                <?php esc_html_e('복사', 'ai-content-rewriter'); ?>
            </button>
            <button type="button" class="button" id="aicr-regenerate-hmac">
                <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
                <?php esc_html_e('재생성', 'ai-content-rewriter'); ?>
            </button>
            <p class="description">
                <?php esc_html_e('Worker → WordPress 웹훅 요청의 서명 검증에 사용됩니다.', 'ai-content-rewriter'); ?>
            </p>
        </td>
    </tr>

    <!-- WordPress API Key -->
    <tr>
        <th scope="row">
            <label for="wp_api_key"><?php esc_html_e('WordPress API Key', 'ai-content-rewriter'); ?></label>
        </th>
        <td>
            <input type="text" id="wp_api_key" class="regular-text code" readonly
                   value="<?php echo esc_attr($config->get_wp_api_key()); ?>">
            <button type="button" class="button" id="aicr-copy-api-key">
                <span class="dashicons dashicons-clipboard" style="vertical-align: middle;"></span>
                <?php esc_html_e('복사', 'ai-content-rewriter'); ?>
            </button>
            <button type="button" class="button" id="aicr-regenerate-api-key">
                <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
                <?php esc_html_e('재생성', 'ai-content-rewriter'); ?>
            </button>
            <p class="description">
                <?php esc_html_e('Worker가 WordPress REST API에 접근할 때 사용하는 인증 키입니다.', 'ai-content-rewriter'); ?>
            </p>
        </td>
    </tr>

    <!-- Webhook URL (Read-only) -->
    <tr>
        <th scope="row">
            <label for="webhook_url"><?php esc_html_e('Webhook URL', 'ai-content-rewriter'); ?></label>
        </th>
        <td>
            <input type="text" id="webhook_url" class="large-text code" readonly
                   value="<?php echo esc_url($config->get_webhook_url()); ?>">
            <button type="button" class="button" id="aicr-copy-webhook">
                <span class="dashicons dashicons-clipboard" style="vertical-align: middle;"></span>
                <?php esc_html_e('복사', 'ai-content-rewriter'); ?>
            </button>
            <p class="description">
                <?php esc_html_e('Worker가 처리 결과를 전송할 Webhook URL입니다. Worker 설정에 이 URL을 입력하세요.', 'ai-content-rewriter'); ?>
            </p>
        </td>
    </tr>
</table>

<hr>

<h3><?php esc_html_e('자동 게시 설정', 'ai-content-rewriter'); ?></h3>
<table class="form-table">
    <!-- Auto Publish -->
    <tr>
        <th scope="row"><?php esc_html_e('품질 기반 자동 게시', 'ai-content-rewriter'); ?></th>
        <td>
            <label>
                <input type="checkbox" name="worker_auto_publish" value="1"
                       <?php checked($config->is_auto_publish()); ?>>
                <?php esc_html_e('품질 점수가 임계값 이상이면 자동으로 게시', 'ai-content-rewriter'); ?>
            </label>
        </td>
    </tr>

    <!-- Publish Threshold -->
    <tr>
        <th scope="row">
            <label for="publish_threshold"><?php esc_html_e('게시 품질 임계값', 'ai-content-rewriter'); ?></label>
        </th>
        <td>
            <input type="range" name="publish_threshold" id="publish_threshold"
                   min="1" max="10" step="1"
                   value="<?php echo esc_attr($config->get_publish_threshold()); ?>"
                   style="width: 200px; vertical-align: middle;">
            <span id="threshold-value" style="font-weight: bold; margin-left: 10px;">
                <?php echo esc_html($config->get_publish_threshold()); ?>/10
            </span>
            <p class="description">
                <?php esc_html_e('이 점수 이상이면 자동 게시, 미만이면 임시글로 저장됩니다. (권장: 8)', 'ai-content-rewriter'); ?>
            </p>
        </td>
    </tr>

    <!-- Daily Limit -->
    <tr>
        <th scope="row">
            <label for="daily_publish_limit"><?php esc_html_e('일일 자동 게시 한도', 'ai-content-rewriter'); ?></label>
        </th>
        <td>
            <input type="number" name="daily_publish_limit" id="daily_publish_limit"
                   min="1" max="100"
                   value="<?php echo esc_attr($config->get_daily_limit()); ?>">
            <p class="description">
                <?php esc_html_e('하루에 자동으로 게시할 최대 글 수입니다. (기본: 10)', 'ai-content-rewriter'); ?>
            </p>
        </td>
    </tr>

    <!-- Curation Threshold -->
    <tr>
        <th scope="row">
            <label for="curation_threshold"><?php esc_html_e('큐레이션 신뢰도 임계값', 'ai-content-rewriter'); ?></label>
        </th>
        <td>
            <input type="range" name="curation_threshold" id="curation_threshold"
                   min="0" max="1" step="0.1"
                   value="<?php echo esc_attr($config->get_curation_threshold()); ?>"
                   style="width: 200px; vertical-align: middle;">
            <span id="curation-value" style="font-weight: bold; margin-left: 10px;">
                <?php echo esc_html((float)$config->get_curation_threshold() * 100); ?>%
            </span>
            <p class="description">
                <?php esc_html_e('피드 아이템 자동 큐레이션의 신뢰도 임계값입니다. (권장: 80%)', 'ai-content-rewriter'); ?>
            </p>
        </td>
    </tr>
</table>

<hr>

<h3><?php esc_html_e('연결 테스트', 'ai-content-rewriter'); ?></h3>
<p>
    <button type="button" id="aicr-test-worker" class="button button-primary" <?php echo !$is_configured ? 'disabled' : ''; ?>>
        <span class="dashicons dashicons-cloud" style="vertical-align: middle;"></span>
        <?php esc_html_e('Worker 연결 테스트', 'ai-content-rewriter'); ?>
    </button>
    <button type="button" id="aicr-sync-config" class="button" <?php echo !$is_configured ? 'disabled' : ''; ?>>
        <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
        <?php esc_html_e('설정 동기화', 'ai-content-rewriter'); ?>
    </button>
    <span id="aicr-worker-status" style="margin-left: 15px;"></span>
</p>

<div id="aicr-worker-test-result" style="display: none; margin-top: 15px; padding: 15px; border-radius: 4px;">
    <!-- 테스트 결과가 여기에 표시됩니다 -->
</div>

<script>
jQuery(document).ready(function($) {
    // 품질 임계값 슬라이더
    $('#publish_threshold').on('input', function() {
        $('#threshold-value').text($(this).val() + '/10');
    });

    // 큐레이션 임계값 슬라이더
    $('#curation_threshold').on('input', function() {
        $('#curation-value').text(Math.round($(this).val() * 100) + '%');
    });

    // 복사 버튼들
    $('#aicr-copy-hmac').on('click', function() {
        navigator.clipboard.writeText($('#hmac_secret').val()).then(function() {
            alert('<?php esc_html_e('HMAC Secret이 클립보드에 복사되었습니다.', 'ai-content-rewriter'); ?>');
        });
    });

    $('#aicr-copy-api-key').on('click', function() {
        navigator.clipboard.writeText($('#wp_api_key').val()).then(function() {
            alert('<?php esc_html_e('API Key가 클립보드에 복사되었습니다.', 'ai-content-rewriter'); ?>');
        });
    });

    $('#aicr-copy-webhook').on('click', function() {
        navigator.clipboard.writeText($('#webhook_url').val()).then(function() {
            alert('<?php esc_html_e('Webhook URL이 클립보드에 복사되었습니다.', 'ai-content-rewriter'); ?>');
        });
    });

    // Worker 연결 테스트
    $('#aicr-test-worker').on('click', function() {
        var $btn = $(this);
        var $status = $('#aicr-worker-status');
        var $result = $('#aicr-worker-test-result');

        $btn.prop('disabled', true);
        $status.html('<span class="spinner is-active" style="float: none;"></span> <?php esc_html_e('테스트 중...', 'ai-content-rewriter'); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aicr_test_worker_connection',
                nonce: '<?php echo wp_create_nonce('aicr_worker_nonce'); ?>'
            },
            success: function(response) {
                $btn.prop('disabled', false);

                if (response.success) {
                    $status.html('<span style="color: green;">✓ <?php esc_html_e('연결 성공!', 'ai-content-rewriter'); ?></span>');
                    $result.css('background', '#d4edda').css('border', '1px solid #c3e6cb').html(
                        '<strong><?php esc_html_e('연결 성공', 'ai-content-rewriter'); ?></strong><br>' +
                        '<?php esc_html_e('Worker 버전:', 'ai-content-rewriter'); ?> ' + (response.data.worker_version || 'unknown') + '<br>' +
                        '<?php esc_html_e('상태:', 'ai-content-rewriter'); ?> ' + (response.data.worker_status || 'unknown')
                    ).show();
                } else {
                    $status.html('<span style="color: red;">✗ <?php esc_html_e('연결 실패', 'ai-content-rewriter'); ?></span>');
                    $result.css('background', '#f8d7da').css('border', '1px solid #f5c6cb').html(
                        '<strong><?php esc_html_e('연결 실패', 'ai-content-rewriter'); ?></strong><br>' +
                        (response.data && response.data.message ? response.data.message : '<?php esc_html_e('알 수 없는 오류', 'ai-content-rewriter'); ?>')
                    ).show();
                }
            },
            error: function() {
                $btn.prop('disabled', false);
                $status.html('<span style="color: red;">✗ <?php esc_html_e('요청 실패', 'ai-content-rewriter'); ?></span>');
            }
        });
    });

    // 설정 동기화
    $('#aicr-sync-config').on('click', function() {
        var $btn = $(this);
        var $status = $('#aicr-worker-status');

        $btn.prop('disabled', true);
        $status.html('<span class="spinner is-active" style="float: none;"></span> <?php esc_html_e('동기화 중...', 'ai-content-rewriter'); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aicr_sync_worker_config',
                nonce: '<?php echo wp_create_nonce('aicr_worker_nonce'); ?>'
            },
            success: function(response) {
                $btn.prop('disabled', false);

                if (response.success) {
                    $status.html('<span style="color: green;">✓ <?php esc_html_e('동기화 완료!', 'ai-content-rewriter'); ?></span>');
                } else {
                    $status.html('<span style="color: red;">✗ ' + (response.data && response.data.message ? response.data.message : '<?php esc_html_e('동기화 실패', 'ai-content-rewriter'); ?>') + '</span>');
                }
            },
            error: function() {
                $btn.prop('disabled', false);
                $status.html('<span style="color: red;">✗ <?php esc_html_e('요청 실패', 'ai-content-rewriter'); ?></span>');
            }
        });
    });

    // HMAC Secret 재생성
    $('#aicr-regenerate-hmac').on('click', function() {
        if (!confirm('<?php esc_html_e('HMAC Secret을 재생성하시겠습니까? Worker에도 새 값을 설정해야 합니다.', 'ai-content-rewriter'); ?>')) {
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aicr_regenerate_hmac',
                nonce: '<?php echo wp_create_nonce('aicr_worker_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#hmac_secret').val(response.data.secret);
                    alert('<?php esc_html_e('HMAC Secret이 재생성되었습니다. Worker 설정도 업데이트하세요.', 'ai-content-rewriter'); ?>');
                }
            }
        });
    });

    // API Key 재생성
    $('#aicr-regenerate-api-key').on('click', function() {
        if (!confirm('<?php esc_html_e('API Key를 재생성하시겠습니까? Worker에도 새 값을 설정해야 합니다.', 'ai-content-rewriter'); ?>')) {
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aicr_regenerate_api_key',
                nonce: '<?php echo wp_create_nonce('aicr_worker_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#wp_api_key').val(response.data.key);
                    alert('<?php esc_html_e('API Key가 재생성되었습니다. Worker 설정도 업데이트하세요.', 'ai-content-rewriter'); ?>');
                }
            }
        });
    });
});
</script>
