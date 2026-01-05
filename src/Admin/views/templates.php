<?php
/**
 * Templates Admin Page Template
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
        <a href="#" id="aicr-add-template" class="page-title-action">
            <?php esc_html_e('새 템플릿 추가', 'ai-content-rewriter'); ?>
        </a>
    </h1>

    <div class="aicr-templates-container">
        <div class="aicr-templates-grid" id="aicr-templates-list">
            <!-- 기본 템플릿: 콘텐츠 재작성 -->
            <div class="aicr-template-card" data-template-id="default_rewrite">
                <div class="aicr-template-header">
                    <h3><?php esc_html_e('콘텐츠 재작성', 'ai-content-rewriter'); ?></h3>
                    <span class="aicr-template-badge default"><?php esc_html_e('기본', 'ai-content-rewriter'); ?></span>
                </div>
                <div class="aicr-template-body">
                    <p><?php esc_html_e('URL 또는 텍스트를 SEO 최적화된 블로그 포스트로 변환합니다.', 'ai-content-rewriter'); ?></p>
                    <div class="aicr-template-vars">
                        <span class="aicr-var">{{content}}</span>
                        <span class="aicr-var">{{target_language}}</span>
                    </div>
                </div>
                <div class="aicr-template-footer">
                    <button type="button" class="button aicr-edit-template">
                        <?php esc_html_e('편집', 'ai-content-rewriter'); ?>
                    </button>
                    <button type="button" class="button aicr-preview-template">
                        <?php esc_html_e('미리보기', 'ai-content-rewriter'); ?>
                    </button>
                </div>
            </div>

            <!-- 기본 템플릿: 번역 -->
            <div class="aicr-template-card" data-template-id="default_translate">
                <div class="aicr-template-header">
                    <h3><?php esc_html_e('번역', 'ai-content-rewriter'); ?></h3>
                    <span class="aicr-template-badge default"><?php esc_html_e('기본', 'ai-content-rewriter'); ?></span>
                </div>
                <div class="aicr-template-body">
                    <p><?php esc_html_e('콘텐츠를 다른 언어로 번역합니다.', 'ai-content-rewriter'); ?></p>
                    <div class="aicr-template-vars">
                        <span class="aicr-var">{{content}}</span>
                        <span class="aicr-var">{{target_language}}</span>
                    </div>
                </div>
                <div class="aicr-template-footer">
                    <button type="button" class="button aicr-edit-template">
                        <?php esc_html_e('편집', 'ai-content-rewriter'); ?>
                    </button>
                    <button type="button" class="button aicr-preview-template">
                        <?php esc_html_e('미리보기', 'ai-content-rewriter'); ?>
                    </button>
                </div>
            </div>

            <!-- 기본 템플릿: 메타데이터 생성 -->
            <div class="aicr-template-card" data-template-id="default_metadata">
                <div class="aicr-template-header">
                    <h3><?php esc_html_e('메타데이터 생성', 'ai-content-rewriter'); ?></h3>
                    <span class="aicr-template-badge default"><?php esc_html_e('기본', 'ai-content-rewriter'); ?></span>
                </div>
                <div class="aicr-template-body">
                    <p><?php esc_html_e('SEO 메타 제목, 설명, 키워드를 자동 생성합니다.', 'ai-content-rewriter'); ?></p>
                    <div class="aicr-template-vars">
                        <span class="aicr-var">{{content}}</span>
                    </div>
                </div>
                <div class="aicr-template-footer">
                    <button type="button" class="button aicr-edit-template">
                        <?php esc_html_e('편집', 'ai-content-rewriter'); ?>
                    </button>
                    <button type="button" class="button aicr-preview-template">
                        <?php esc_html_e('미리보기', 'ai-content-rewriter'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 템플릿 편집 모달 -->
    <div id="aicr-template-modal" class="aicr-modal" style="display: none;">
        <div class="aicr-modal-content aicr-modal-large">
            <span class="aicr-modal-close">&times;</span>
            <h2 id="aicr-template-modal-title"><?php esc_html_e('템플릿 편집', 'ai-content-rewriter'); ?></h2>

            <form id="aicr-template-form">
                <input type="hidden" name="template_id" id="template_id">

                <div class="aicr-form-row">
                    <label for="template_name"><?php esc_html_e('템플릿 이름', 'ai-content-rewriter'); ?></label>
                    <input type="text" name="template_name" id="template_name" class="regular-text" required>
                </div>

                <div class="aicr-form-row">
                    <label for="template_type"><?php esc_html_e('템플릿 유형', 'ai-content-rewriter'); ?></label>
                    <select name="template_type" id="template_type">
                        <option value="rewrite"><?php esc_html_e('콘텐츠 재작성', 'ai-content-rewriter'); ?></option>
                        <option value="translate"><?php esc_html_e('번역', 'ai-content-rewriter'); ?></option>
                        <option value="metadata"><?php esc_html_e('메타데이터', 'ai-content-rewriter'); ?></option>
                        <option value="custom"><?php esc_html_e('커스텀', 'ai-content-rewriter'); ?></option>
                    </select>
                </div>

                <div class="aicr-form-row">
                    <label for="template_content"><?php esc_html_e('프롬프트 내용', 'ai-content-rewriter'); ?></label>
                    <textarea name="template_content" id="template_content" rows="15" required></textarea>
                    <p class="description">
                        <?php esc_html_e('사용 가능한 변수: {{content}}, {{target_language}}, {{source_url}}, {{title}}', 'ai-content-rewriter'); ?>
                    </p>
                </div>

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
