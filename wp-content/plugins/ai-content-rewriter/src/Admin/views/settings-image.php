<?php
/**
 * Image Settings Tab Template
 *
 * @package AIContentRewriter\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

// ImagePromptManager에서 스타일 목록 가져오기
$prompt_manager = \AIContentRewriter\Image\ImagePromptManager::get_instance();
$styles = $prompt_manager->get_all_styles();
$current_image_prompt = $prompt_manager->get_prompt();
$default_image_prompt = $prompt_manager->get_default_prompt();
$is_default_prompt = ($current_image_prompt === $default_image_prompt);

// 현재 설정값
$default_count = get_option('aicr_image_default_count', 2);
$default_style = get_option('aicr_image_default_style', '일러스트레이션');
$default_ratio = get_option('aicr_image_default_ratio', '16:9');
$auto_featured = get_option('aicr_image_auto_featured', true);
$skip_with_thumbnail = get_option('aicr_image_skip_with_thumbnail', true);
$skip_with_images = get_option('aicr_image_skip_with_images', true);

// 스케줄 설정
$schedule_enabled = get_option('aicr_image_schedule_enabled', false);
$schedule_interval = get_option('aicr_image_schedule_interval', 'hourly');
$batch_size = get_option('aicr_image_batch_size', 5);
?>

<h2><?php esc_html_e('AI 이미지 생성 설정', 'ai-content-rewriter'); ?></h2>
<p class="description">
    <?php esc_html_e('Gemini Imagen 3.0을 사용하여 블로그 게시글에 AI 이미지를 자동으로 생성합니다.', 'ai-content-rewriter'); ?>
</p>

<!-- API 연결 테스트 -->
<div class="aicr-api-test-box" style="background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin: 15px 0; max-width: 600px;">
    <h4 style="margin-top: 0; margin-bottom: 10px;">
        <span class="dashicons dashicons-admin-plugins" style="vertical-align: middle;"></span>
        <?php esc_html_e('Gemini Imagen API 연결 상태', 'ai-content-rewriter'); ?>
    </h4>
    <p class="description" style="margin-bottom: 10px;">
        <?php esc_html_e('이미지 생성을 위해 Gemini API 키가 설정되어 있어야 합니다.', 'ai-content-rewriter'); ?>
    </p>
    <button type="button" id="aicr-test-imagen-api" class="button">
        <span class="dashicons dashicons-cloud" style="vertical-align: middle;"></span>
        <?php esc_html_e('API 연결 테스트', 'ai-content-rewriter'); ?>
    </button>
    <span id="aicr-imagen-api-status" style="margin-left: 10px;"></span>
</div>

<!-- 기본 설정 -->
<h3><?php esc_html_e('기본 설정', 'ai-content-rewriter'); ?></h3>
<table class="form-table">
    <tr>
        <th scope="row">
            <label for="aicr-image-default-count"><?php esc_html_e('기본 이미지 수', 'ai-content-rewriter'); ?></label>
        </th>
        <td>
            <select name="image_default_count" id="aicr-image-default-count">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php selected($default_count, $i); ?>>
                        <?php echo $i; ?>개
                    </option>
                <?php endfor; ?>
            </select>
            <p class="description">
                <?php esc_html_e('게시글당 생성할 기본 이미지 수입니다.', 'ai-content-rewriter'); ?>
            </p>
        </td>
    </tr>
    <tr>
        <th scope="row">
            <label for="aicr-image-default-style"><?php esc_html_e('기본 스타일', 'ai-content-rewriter'); ?></label>
        </th>
        <td>
            <select name="image_default_style" id="aicr-image-default-style">
                <?php foreach ($styles as $style): ?>
                    <option value="<?php echo esc_attr($style->name); ?>" <?php selected($default_style, $style->name); ?>>
                        <?php echo esc_html($style->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description">
                <?php esc_html_e('이미지 생성 시 적용할 기본 스타일입니다.', 'ai-content-rewriter'); ?>
            </p>
        </td>
    </tr>
    <tr>
        <th scope="row">
            <label for="aicr-image-default-ratio"><?php esc_html_e('기본 비율', 'ai-content-rewriter'); ?></label>
        </th>
        <td>
            <select name="image_default_ratio" id="aicr-image-default-ratio">
                <option value="1:1" <?php selected($default_ratio, '1:1'); ?>>1:1 (정사각형)</option>
                <option value="3:4" <?php selected($default_ratio, '3:4'); ?>>3:4 (세로)</option>
                <option value="4:3" <?php selected($default_ratio, '4:3'); ?>>4:3 (가로)</option>
                <option value="9:16" <?php selected($default_ratio, '9:16'); ?>>9:16 (스토리)</option>
                <option value="16:9" <?php selected($default_ratio, '16:9'); ?>>16:9 (와이드)</option>
            </select>
            <p class="description">
                <?php esc_html_e('이미지의 기본 종횡비입니다.', 'ai-content-rewriter'); ?>
            </p>
        </td>
    </tr>
    <tr>
        <th scope="row"><?php esc_html_e('Featured Image 설정', 'ai-content-rewriter'); ?></th>
        <td>
            <label>
                <input type="checkbox" name="image_auto_featured" value="1" <?php checked($auto_featured); ?>>
                <?php esc_html_e('첫 번째 생성 이미지를 Featured Image로 자동 설정', 'ai-content-rewriter'); ?>
            </label>
        </td>
    </tr>
</table>

<hr>

<!-- 스킵 조건 -->
<h3><?php esc_html_e('이미지 생성 건너뛰기 조건', 'ai-content-rewriter'); ?></h3>
<table class="form-table">
    <tr>
        <th scope="row"><?php esc_html_e('Featured Image 있을 때', 'ai-content-rewriter'); ?></th>
        <td>
            <label>
                <input type="checkbox" name="image_skip_with_thumbnail" value="1" <?php checked($skip_with_thumbnail); ?>>
                <?php esc_html_e('이미 Featured Image가 있는 게시글은 건너뛰기', 'ai-content-rewriter'); ?>
            </label>
        </td>
    </tr>
    <tr>
        <th scope="row"><?php esc_html_e('콘텐츠에 이미지 있을 때', 'ai-content-rewriter'); ?></th>
        <td>
            <label>
                <input type="checkbox" name="image_skip_with_images" value="1" <?php checked($skip_with_images); ?>>
                <?php esc_html_e('콘텐츠에 이미지가 포함된 게시글은 건너뛰기', 'ai-content-rewriter'); ?>
            </label>
        </td>
    </tr>
</table>

<hr>

<!-- 자동 생성 스케줄 -->
<h3><?php esc_html_e('자동 이미지 생성 스케줄', 'ai-content-rewriter'); ?></h3>
<table class="form-table">
    <tr>
        <th scope="row"><?php esc_html_e('스케줄 활성화', 'ai-content-rewriter'); ?></th>
        <td>
            <label>
                <input type="checkbox" name="image_schedule_enabled" id="aicr-image-schedule-enabled" value="1" <?php checked($schedule_enabled); ?>>
                <?php esc_html_e('자동 이미지 생성 스케줄 활성화', 'ai-content-rewriter'); ?>
            </label>
            <p class="description">
                <?php esc_html_e('AICR로 재작성된 게시글 중 이미지가 없는 게시글에 자동으로 이미지를 생성합니다.', 'ai-content-rewriter'); ?>
            </p>
        </td>
    </tr>
    <tr>
        <th scope="row">
            <label for="aicr-image-schedule-interval"><?php esc_html_e('실행 간격', 'ai-content-rewriter'); ?></label>
        </th>
        <td>
            <select name="image_schedule_interval" id="aicr-image-schedule-interval">
                <option value="hourly" <?php selected($schedule_interval, 'hourly'); ?>><?php esc_html_e('매시간', 'ai-content-rewriter'); ?></option>
                <option value="twicedaily" <?php selected($schedule_interval, 'twicedaily'); ?>><?php esc_html_e('하루 2회', 'ai-content-rewriter'); ?></option>
                <option value="daily" <?php selected($schedule_interval, 'daily'); ?>><?php esc_html_e('매일', 'ai-content-rewriter'); ?></option>
            </select>
        </td>
    </tr>
    <tr>
        <th scope="row">
            <label for="aicr-image-batch-size"><?php esc_html_e('배치 크기', 'ai-content-rewriter'); ?></label>
        </th>
        <td>
            <input type="number" name="image_batch_size" id="aicr-image-batch-size"
                   value="<?php echo esc_attr($batch_size); ?>" min="1" max="20">
            <p class="description">
                <?php esc_html_e('한 번의 스케줄 실행에서 처리할 최대 게시글 수입니다.', 'ai-content-rewriter'); ?>
            </p>
        </td>
    </tr>
</table>

<p style="margin: 20px 0;">
    <button type="button" id="aicr-save-image-settings" class="button button-primary">
        <?php esc_html_e('이미지 설정 저장', 'ai-content-rewriter'); ?>
    </button>
    <button type="button" id="aicr-run-image-generation-now" class="button">
        <?php esc_html_e('지금 이미지 생성 실행', 'ai-content-rewriter'); ?>
    </button>
    <span id="aicr-image-settings-status" style="margin-left: 10px;"></span>
</p>

<hr>

<!-- 이미지 스타일 관리 -->
<h3><?php esc_html_e('이미지 스타일 관리', 'ai-content-rewriter'); ?></h3>
<p class="description">
    <?php esc_html_e('사전 정의된 스타일을 관리합니다. 각 스타일은 이미지 생성 프롬프트에 추가되는 지시사항입니다.', 'ai-content-rewriter'); ?>
</p>

<table class="wp-list-table widefat fixed striped" id="aicr-image-styles-table" style="max-width: 900px;">
    <thead>
        <tr>
            <th style="width: 150px;"><?php esc_html_e('스타일명', 'ai-content-rewriter'); ?></th>
            <th><?php esc_html_e('설명', 'ai-content-rewriter'); ?></th>
            <th style="width: 100px;"><?php esc_html_e('작업', 'ai-content-rewriter'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($styles as $style): ?>
            <tr data-id="<?php echo esc_attr($style->id); ?>">
                <td><strong><?php echo esc_html($style->name); ?></strong></td>
                <td><?php echo esc_html(mb_substr($style->description, 0, 100)) . (mb_strlen($style->description) > 100 ? '...' : ''); ?></td>
                <td>
                    <button type="button" class="button button-small aicr-edit-style" data-id="<?php echo esc_attr($style->id); ?>">
                        <?php esc_html_e('편집', 'ai-content-rewriter'); ?>
                    </button>
                    <?php if (!$style->is_default): ?>
                        <button type="button" class="button button-small button-link-delete aicr-delete-style" data-id="<?php echo esc_attr($style->id); ?>">
                            <?php esc_html_e('삭제', 'ai-content-rewriter'); ?>
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<p style="margin-top: 15px;">
    <button type="button" id="aicr-add-style" class="button">
        <span class="dashicons dashicons-plus-alt" style="vertical-align: middle;"></span>
        <?php esc_html_e('새 스타일 추가', 'ai-content-rewriter'); ?>
    </button>
</p>

<hr>

<!-- 이미지 프롬프트 템플릿 -->
<h3><?php esc_html_e('이미지 생성 프롬프트 템플릿', 'ai-content-rewriter'); ?></h3>
<p class="description">
    <?php esc_html_e('이미지 생성 시 AI에 전달되는 기본 프롬프트입니다. 변수는 실제 값으로 자동 치환됩니다.', 'ai-content-rewriter'); ?>
</p>

<table class="form-table">
    <tr>
        <th scope="row">
            <label for="aicr-image-prompt-content"><?php esc_html_e('프롬프트 내용', 'ai-content-rewriter'); ?></label>
        </th>
        <td>
            <textarea id="aicr-image-prompt-content" name="image_prompt_content" rows="10" class="large-text code" style="font-family: monospace; font-size: 13px;"><?php echo esc_textarea($current_image_prompt); ?></textarea>
            <?php if (!$is_default_prompt): ?>
                <p class="description" style="color: #2271b1;">
                    <span class="dashicons dashicons-edit"></span>
                    <?php esc_html_e('사용자 정의 프롬프트가 사용 중입니다.', 'ai-content-rewriter'); ?>
                </p>
            <?php else: ?>
                <p class="description">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e('기본 프롬프트가 사용 중입니다.', 'ai-content-rewriter'); ?>
                </p>
            <?php endif; ?>
        </td>
    </tr>
</table>

<h4><?php esc_html_e('사용 가능한 변수', 'ai-content-rewriter'); ?></h4>
<table class="widefat striped" style="max-width: 600px;">
    <thead>
        <tr>
            <th><?php esc_html_e('변수', 'ai-content-rewriter'); ?></th>
            <th><?php esc_html_e('설명', 'ai-content-rewriter'); ?></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><code>{{topic}}</code></td>
            <td><?php esc_html_e('섹션의 핵심 주제', 'ai-content-rewriter'); ?></td>
        </tr>
        <tr>
            <td><code>{{style}}</code></td>
            <td><?php esc_html_e('선택된 이미지 스타일 지시사항', 'ai-content-rewriter'); ?></td>
        </tr>
        <tr>
            <td><code>{{instructions}}</code></td>
            <td><?php esc_html_e('추가 지시사항 (선택 입력)', 'ai-content-rewriter'); ?></td>
        </tr>
    </tbody>
</table>

<p style="margin-top: 20px;">
    <button type="button" id="aicr-save-image-prompt" class="button button-primary">
        <?php esc_html_e('프롬프트 저장', 'ai-content-rewriter'); ?>
    </button>
    <button type="button" id="aicr-reset-image-prompt" class="button" <?php echo $is_default_prompt ? 'disabled' : ''; ?>>
        <?php esc_html_e('기본값으로 복원', 'ai-content-rewriter'); ?>
    </button>
    <span id="aicr-image-prompt-status" style="margin-left: 10px;"></span>
</p>

<!-- 스타일 편집 모달 -->
<div id="aicr-style-modal" class="aicr-modal" style="display: none;">
    <div class="aicr-modal-content">
        <div class="aicr-modal-header">
            <h3 id="aicr-style-modal-title"><?php esc_html_e('스타일 편집', 'ai-content-rewriter'); ?></h3>
            <button type="button" class="aicr-modal-close">&times;</button>
        </div>
        <div class="aicr-modal-body">
            <input type="hidden" id="aicr-style-id" value="">
            <p>
                <label for="aicr-style-name"><?php esc_html_e('스타일명', 'ai-content-rewriter'); ?></label>
                <input type="text" id="aicr-style-name" class="regular-text" required>
            </p>
            <p>
                <label for="aicr-style-description"><?php esc_html_e('스타일 지시사항', 'ai-content-rewriter'); ?></label>
                <textarea id="aicr-style-description" rows="5" class="large-text" placeholder="<?php esc_attr_e('이 스타일에 대한 AI 지시사항을 입력하세요.', 'ai-content-rewriter'); ?>"></textarea>
                <span class="description"><?php esc_html_e('이 내용이 이미지 생성 프롬프트에 추가됩니다.', 'ai-content-rewriter'); ?></span>
            </p>
        </div>
        <div class="aicr-modal-footer">
            <button type="button" id="aicr-save-style" class="button button-primary"><?php esc_html_e('저장', 'ai-content-rewriter'); ?></button>
            <button type="button" class="button aicr-modal-close"><?php esc_html_e('취소', 'ai-content-rewriter'); ?></button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Gemini Imagen API 연결 테스트
    $('#aicr-test-imagen-api').on('click', function() {
        var $btn = $(this);
        var $status = $('#aicr-imagen-api-status');

        $btn.prop('disabled', true);
        $status.html('<span style="color: #666;"><span class="dashicons dashicons-update spinning"></span> 테스트 중...</span>');

        $.ajax({
            url: aicr_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'aicr_test_imagen_api',
                nonce: aicr_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var details = response.data.details || {};
                    var endpointInfo = details.endpoint_used ? ' (' + details.endpoint_used + ')' : '';
                    $status.html('<span style="color: green;"><span class="dashicons dashicons-yes-alt"></span> ' + response.data.message + endpointInfo + '</span>');
                } else {
                    var errorMsg = response.data.message || 'API 연결 실패';
                    $status.html('<span style="color: red;"><span class="dashicons dashicons-warning"></span> ' + errorMsg + '</span>');
                }
            },
            error: function(xhr, status, error) {
                $status.html('<span style="color: red;"><span class="dashicons dashicons-warning"></span> 요청 실패: ' + error + '</span>');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    // 이미지 설정 저장
    $('#aicr-save-image-settings').on('click', function() {
        var $btn = $(this);
        var $status = $('#aicr-image-settings-status');

        $btn.prop('disabled', true).text('저장 중...');

        $.ajax({
            url: aicr_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'aicr_save_image_settings',
                nonce: aicr_ajax.nonce,
                default_count: $('#aicr-image-default-count').val(),
                default_style: $('#aicr-image-default-style').val(),
                default_ratio: $('#aicr-image-default-ratio').val(),
                auto_featured: $('input[name="image_auto_featured"]').is(':checked') ? '1' : '0',
                skip_with_thumbnail: $('input[name="image_skip_with_thumbnail"]').is(':checked') ? '1' : '0',
                skip_with_images: $('input[name="image_skip_with_images"]').is(':checked') ? '1' : '0',
                schedule_enabled: $('#aicr-image-schedule-enabled').is(':checked') ? '1' : '0',
                schedule_interval: $('#aicr-image-schedule-interval').val(),
                batch_size: $('#aicr-image-batch-size').val()
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                } else {
                    $status.html('<span style="color: red;">✗ ' + (response.data || '오류가 발생했습니다.') + '</span>');
                }
            },
            error: function() {
                $status.html('<span style="color: red;">✗ 저장에 실패했습니다.</span>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('이미지 설정 저장');
                setTimeout(function() { $status.html(''); }, 3000);
            }
        });
    });

    // 지금 이미지 생성 실행
    $('#aicr-run-image-generation-now').on('click', function() {
        var $btn = $(this);
        var $status = $('#aicr-image-settings-status');

        if (!confirm('이미지 생성을 지금 실행하시겠습니까?')) {
            return;
        }

        $btn.prop('disabled', true).text('실행 중...');

        $.ajax({
            url: aicr_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'aicr_run_image_generation',
                nonce: aicr_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                } else {
                    $status.html('<span style="color: orange;">⚠ ' + (response.data.message || '실행 중 문제가 발생했습니다.') + '</span>');
                }
            },
            error: function() {
                $status.html('<span style="color: red;">✗ 실행에 실패했습니다.</span>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('지금 이미지 생성 실행');
                setTimeout(function() { $status.html(''); }, 5000);
            }
        });
    });

    // 스타일 추가 버튼
    $('#aicr-add-style').on('click', function() {
        $('#aicr-style-id').val('');
        $('#aicr-style-name').val('');
        $('#aicr-style-description').val('');
        $('#aicr-style-modal-title').text('새 스타일 추가');
        $('#aicr-style-modal').show();
    });

    // 스타일 편집 버튼
    $(document).on('click', '.aicr-edit-style', function() {
        var $row = $(this).closest('tr');
        var id = $(this).data('id');

        // AJAX로 스타일 정보 가져오기
        $.ajax({
            url: aicr_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'aicr_get_image_styles',
                nonce: aicr_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var style = response.data.find(function(s) { return s.id == id; });
                    if (style) {
                        $('#aicr-style-id').val(style.id);
                        $('#aicr-style-name').val(style.name);
                        $('#aicr-style-description').val(style.description);
                        $('#aicr-style-modal-title').text('스타일 편집');
                        $('#aicr-style-modal').show();
                    }
                }
            }
        });
    });

    // 스타일 저장
    $('#aicr-save-style').on('click', function() {
        var $btn = $(this);
        var name = $('#aicr-style-name').val().trim();
        var description = $('#aicr-style-description').val().trim();

        if (!name) {
            alert('스타일명을 입력하세요.');
            return;
        }

        $btn.prop('disabled', true).text('저장 중...');

        $.ajax({
            url: aicr_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'aicr_save_image_style',
                nonce: aicr_ajax.nonce,
                id: $('#aicr-style-id').val(),
                name: name,
                description: description
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data || '저장에 실패했습니다.');
                }
            },
            error: function() {
                alert('저장에 실패했습니다.');
            },
            complete: function() {
                $btn.prop('disabled', false).text('저장');
            }
        });
    });

    // 스타일 삭제
    $(document).on('click', '.aicr-delete-style', function() {
        if (!confirm('이 스타일을 삭제하시겠습니까?')) {
            return;
        }

        var $btn = $(this);
        var id = $btn.data('id');

        $.ajax({
            url: aicr_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'aicr_delete_image_style',
                nonce: aicr_ajax.nonce,
                id: id
            },
            success: function(response) {
                if (response.success) {
                    $btn.closest('tr').fadeOut(function() { $(this).remove(); });
                } else {
                    alert(response.data || '삭제에 실패했습니다.');
                }
            },
            error: function() {
                alert('삭제에 실패했습니다.');
            }
        });
    });

    // 모달 닫기
    $('.aicr-modal-close').on('click', function() {
        $(this).closest('.aicr-modal').hide();
    });

    // 모달 외부 클릭 시 닫기
    $('.aicr-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });

    // 이미지 프롬프트 저장
    $('#aicr-save-image-prompt').on('click', function() {
        var $btn = $(this);
        var $status = $('#aicr-image-prompt-status');
        var prompt = $('#aicr-image-prompt-content').val();

        $btn.prop('disabled', true).text('저장 중...');

        $.ajax({
            url: aicr_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'aicr_save_image_prompt',
                nonce: aicr_ajax.nonce,
                prompt: prompt
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                    $('#aicr-reset-image-prompt').prop('disabled', false);
                } else {
                    $status.html('<span style="color: red;">✗ ' + (response.data || '오류가 발생했습니다.') + '</span>');
                }
            },
            error: function() {
                $status.html('<span style="color: red;">✗ 저장에 실패했습니다.</span>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('프롬프트 저장');
                setTimeout(function() { $status.html(''); }, 3000);
            }
        });
    });

    // 이미지 프롬프트 초기화
    $('#aicr-reset-image-prompt').on('click', function() {
        if (!confirm('프롬프트를 기본값으로 복원하시겠습니까?')) {
            return;
        }

        var $btn = $(this);
        var $status = $('#aicr-image-prompt-status');

        $btn.prop('disabled', true).text('복원 중...');

        $.ajax({
            url: aicr_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'aicr_reset_image_prompt',
                nonce: aicr_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#aicr-image-prompt-content').val(response.data.prompt);
                    $status.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                    $btn.prop('disabled', true);
                } else {
                    $status.html('<span style="color: red;">✗ ' + (response.data || '오류가 발생했습니다.') + '</span>');
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                $status.html('<span style="color: red;">✗ 복원에 실패했습니다.</span>');
                $btn.prop('disabled', false);
            },
            complete: function() {
                $btn.text('기본값으로 복원');
                setTimeout(function() { $status.html(''); }, 3000);
            }
        });
    });
});
</script>

<style>
/* 모달 스타일 */
.aicr-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.aicr-modal-content {
    background: #fff;
    border-radius: 4px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow: auto;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
}

.aicr-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #ddd;
}

.aicr-modal-header h3 {
    margin: 0;
}

.aicr-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
    padding: 0;
    line-height: 1;
}

.aicr-modal-close:hover {
    color: #d00;
}

.aicr-modal-body {
    padding: 20px;
}

.aicr-modal-body label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
}

.aicr-modal-body input[type="text"],
.aicr-modal-body textarea {
    width: 100%;
    box-sizing: border-box;
}

.aicr-modal-body p {
    margin-bottom: 15px;
}

.aicr-modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #ddd;
    text-align: right;
}

.aicr-modal-footer .button {
    margin-left: 10px;
}

/* Spinning animation */
.spinning {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>
