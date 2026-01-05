<?php
/**
 * History Admin Page Template
 *
 * @package AIContentRewriter\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap aicr-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="aicr-history-container">
        <!-- 필터 -->
        <div class="aicr-filters">
            <select id="filter-status">
                <option value=""><?php esc_html_e('모든 상태', 'ai-content-rewriter'); ?></option>
                <option value="completed"><?php esc_html_e('완료', 'ai-content-rewriter'); ?></option>
                <option value="pending"><?php esc_html_e('대기중', 'ai-content-rewriter'); ?></option>
                <option value="failed"><?php esc_html_e('실패', 'ai-content-rewriter'); ?></option>
            </select>
            <select id="filter-provider">
                <option value=""><?php esc_html_e('모든 AI', 'ai-content-rewriter'); ?></option>
                <option value="chatgpt">ChatGPT</option>
                <option value="gemini">Gemini</option>
            </select>
            <input type="text" id="filter-date" placeholder="<?php esc_attr_e('날짜 선택', 'ai-content-rewriter'); ?>">
        </div>

        <!-- 히스토리 테이블 -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('ID', 'ai-content-rewriter'); ?></th>
                    <th><?php esc_html_e('소스', 'ai-content-rewriter'); ?></th>
                    <th><?php esc_html_e('AI', 'ai-content-rewriter'); ?></th>
                    <th><?php esc_html_e('상태', 'ai-content-rewriter'); ?></th>
                    <th><?php esc_html_e('결과 포스트', 'ai-content-rewriter'); ?></th>
                    <th><?php esc_html_e('날짜', 'ai-content-rewriter'); ?></th>
                    <th><?php esc_html_e('작업', 'ai-content-rewriter'); ?></th>
                </tr>
            </thead>
            <tbody id="aicr-history-list">
                <tr>
                    <td colspan="7" class="aicr-loading">
                        <?php esc_html_e('히스토리를 불러오는 중...', 'ai-content-rewriter'); ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- 페이지네이션 -->
        <div class="aicr-pagination">
            <!-- 동적으로 생성됨 -->
        </div>
    </div>
</div>
