<?php
/**
 * Schedule Admin Page Template
 *
 * @package AIContentRewriter\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap aicr-wrap">
    <h1>
        <?php echo esc_html(get_admin_page_title()); ?>
        <a href="#" id="aicr-add-schedule" class="page-title-action">
            <?php esc_html_e('새 스케줄 추가', 'ai-content-rewriter'); ?>
        </a>
    </h1>

    <div class="aicr-schedule-container">
        <!-- 스케줄 목록 -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('이름', 'ai-content-rewriter'); ?></th>
                    <th><?php esc_html_e('소스 URL', 'ai-content-rewriter'); ?></th>
                    <th><?php esc_html_e('반복 주기', 'ai-content-rewriter'); ?></th>
                    <th><?php esc_html_e('다음 실행', 'ai-content-rewriter'); ?></th>
                    <th><?php esc_html_e('상태', 'ai-content-rewriter'); ?></th>
                    <th><?php esc_html_e('실행 횟수', 'ai-content-rewriter'); ?></th>
                    <th><?php esc_html_e('작업', 'ai-content-rewriter'); ?></th>
                </tr>
            </thead>
            <tbody id="aicr-schedule-list">
                <tr>
                    <td colspan="7" class="aicr-empty">
                        <?php esc_html_e('등록된 스케줄이 없습니다.', 'ai-content-rewriter'); ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- 스케줄 추가/수정 모달 -->
    <div id="aicr-schedule-modal" class="aicr-modal" style="display: none;">
        <div class="aicr-modal-content">
            <span class="aicr-modal-close">&times;</span>
            <h2><?php esc_html_e('스케줄 설정', 'ai-content-rewriter'); ?></h2>

            <form id="aicr-schedule-form">
                <input type="hidden" name="schedule_id" id="schedule_id">

                <table class="form-table">
                    <tr>
                        <th><label for="schedule_name"><?php esc_html_e('스케줄 이름', 'ai-content-rewriter'); ?></label></th>
                        <td><input type="text" name="schedule_name" id="schedule_name" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="schedule_url"><?php esc_html_e('소스 URL', 'ai-content-rewriter'); ?></label></th>
                        <td><input type="url" name="schedule_url" id="schedule_url" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="schedule_interval"><?php esc_html_e('반복 주기', 'ai-content-rewriter'); ?></label></th>
                        <td>
                            <select name="schedule_interval" id="schedule_interval">
                                <option value="once"><?php esc_html_e('1회만', 'ai-content-rewriter'); ?></option>
                                <option value="hourly"><?php esc_html_e('매시간', 'ai-content-rewriter'); ?></option>
                                <option value="twicedaily"><?php esc_html_e('하루 2회', 'ai-content-rewriter'); ?></option>
                                <option value="daily"><?php esc_html_e('매일', 'ai-content-rewriter'); ?></option>
                                <option value="weekly"><?php esc_html_e('매주', 'ai-content-rewriter'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="schedule_start"><?php esc_html_e('시작 시간', 'ai-content-rewriter'); ?></label></th>
                        <td><input type="datetime-local" name="schedule_start" id="schedule_start" required></td>
                    </tr>
                </table>

                <div class="aicr-modal-actions">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('저장', 'ai-content-rewriter'); ?>
                    </button>
                    <button type="button" class="button aicr-modal-cancel">
                        <?php esc_html_e('취소', 'ai-content-rewriter'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
