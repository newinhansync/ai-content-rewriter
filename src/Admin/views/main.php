<?php
/**
 * Main Admin Page Template
 *
 * @package AIContentRewriter\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

// 설정에서 기본값 가져오기
$default_ai_provider = get_option('aicr_default_ai_provider', 'chatgpt');
$default_language = get_option('aicr_default_language', 'ko');
$default_post_status = get_option('aicr_default_post_status', 'draft');
$auto_generate_metadata = get_option('aicr_auto_generate_metadata', '1');
?>
<div class="wrap aicr-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="aicr-container">
        <div class="aicr-main-form">
            <form id="aicr-rewrite-form" method="post">
                <?php wp_nonce_field('aicr_rewrite', 'aicr_nonce'); ?>

                <!-- 소스 타입 선택 -->
                <div class="aicr-form-section">
                    <h2><?php esc_html_e('콘텐츠 소스', 'ai-content-rewriter'); ?></h2>
                    <div class="aicr-source-tabs">
                        <button type="button" class="aicr-tab active" data-tab="url">
                            <?php esc_html_e('URL 입력', 'ai-content-rewriter'); ?>
                        </button>
                        <button type="button" class="aicr-tab" data-tab="text">
                            <?php esc_html_e('텍스트 입력', 'ai-content-rewriter'); ?>
                        </button>
                    </div>

                    <div id="aicr-tab-url" class="aicr-tab-content active">
                        <input type="url" name="source_url" id="source_url"
                               placeholder="https://example.com/article"
                               class="regular-text aicr-full-width">
                        <p class="description">
                            <?php esc_html_e('변환할 웹 페이지의 URL을 입력하세요.', 'ai-content-rewriter'); ?>
                        </p>
                    </div>

                    <div id="aicr-tab-text" class="aicr-tab-content">
                        <textarea name="source_text" id="source_text" rows="10"
                                  placeholder="<?php esc_attr_e('변환할 텍스트를 입력하세요...', 'ai-content-rewriter'); ?>"
                                  class="aicr-full-width"></textarea>
                    </div>
                </div>

                <!-- AI 설정 -->
                <div class="aicr-form-section">
                    <h2><?php esc_html_e('AI 설정', 'ai-content-rewriter'); ?></h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="ai_provider"><?php esc_html_e('AI 제공자', 'ai-content-rewriter'); ?></label>
                            </th>
                            <td>
                                <select name="ai_provider" id="ai_provider">
                                    <option value="chatgpt" <?php selected($default_ai_provider, 'chatgpt'); ?>>ChatGPT (GPT-5)</option>
                                    <option value="gemini" <?php selected($default_ai_provider, 'gemini'); ?>>Google Gemini 3</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="target_language"><?php esc_html_e('대상 언어', 'ai-content-rewriter'); ?></label>
                            </th>
                            <td>
                                <select name="target_language" id="target_language">
                                    <option value="ko" <?php selected($default_language, 'ko'); ?>><?php esc_html_e('한국어', 'ai-content-rewriter'); ?></option>
                                    <option value="en" <?php selected($default_language, 'en'); ?>><?php esc_html_e('영어', 'ai-content-rewriter'); ?></option>
                                    <option value="ja" <?php selected($default_language, 'ja'); ?>><?php esc_html_e('일본어', 'ai-content-rewriter'); ?></option>
                                    <option value="zh" <?php selected($default_language, 'zh'); ?>><?php esc_html_e('중국어', 'ai-content-rewriter'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="prompt_template"><?php esc_html_e('프롬프트 템플릿', 'ai-content-rewriter'); ?></label>
                            </th>
                            <td>
                                <select name="prompt_template" id="prompt_template">
                                    <option value="default"><?php esc_html_e('기본 템플릿', 'ai-content-rewriter'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- 발행 옵션 -->
                <div class="aicr-form-section">
                    <h2><?php esc_html_e('발행 옵션', 'ai-content-rewriter'); ?></h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="post_status"><?php esc_html_e('포스트 상태', 'ai-content-rewriter'); ?></label>
                            </th>
                            <td>
                                <select name="post_status" id="post_status">
                                    <option value="draft" <?php selected($default_post_status, 'draft'); ?>><?php esc_html_e('임시글', 'ai-content-rewriter'); ?></option>
                                    <option value="pending" <?php selected($default_post_status, 'pending'); ?>><?php esc_html_e('검토 대기', 'ai-content-rewriter'); ?></option>
                                    <option value="publish" <?php selected($default_post_status, 'publish'); ?>><?php esc_html_e('즉시 발행', 'ai-content-rewriter'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="post_category"><?php esc_html_e('카테고리', 'ai-content-rewriter'); ?></label>
                            </th>
                            <td>
                                <?php
                                wp_dropdown_categories([
                                    'name' => 'post_category',
                                    'id' => 'post_category',
                                    'show_option_none' => __('카테고리 선택', 'ai-content-rewriter'),
                                    'option_none_value' => '0',
                                    'hierarchical' => true,
                                ]);
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('메타데이터', 'ai-content-rewriter'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="generate_metadata" value="1" <?php checked($auto_generate_metadata, '1'); ?>>
                                    <?php esc_html_e('SEO 메타데이터 자동 생성', 'ai-content-rewriter'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="aicr-form-actions">
                    <button type="submit" name="action" value="rewrite" class="button button-primary button-large" id="aicr-start-rewrite">
                        <?php esc_html_e('재작성 시작', 'ai-content-rewriter'); ?>
                    </button>
                    <button type="button" id="aicr-schedule-btn" class="button button-secondary">
                        <?php esc_html_e('스케줄 설정', 'ai-content-rewriter'); ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- 진행 상황 표시 영역 -->
        <div id="aicr-content-progress" class="aicr-content-progress" style="display: none;">
            <div class="aicr-progress-card">
                <div class="aicr-progress-header">
                    <span class="dashicons dashicons-update spin" id="aicr-progress-spinner"></span>
                    <span id="aicr-content-progress-title">재작성 진행 중...</span>
                </div>
                <div class="aicr-progress-bar-container">
                    <div class="aicr-progress-bar" id="aicr-content-progress-bar" style="width: 0%"></div>
                </div>
                <div class="aicr-progress-steps">
                    <div class="aicr-progress-step" data-step="extracting">
                        <span class="step-icon">📄</span>
                        <span class="step-label">콘텐츠 추출</span>
                    </div>
                    <div class="aicr-progress-step" data-step="rewriting">
                        <span class="step-icon">🤖</span>
                        <span class="step-label">AI 재작성</span>
                    </div>
                    <div class="aicr-progress-step" data-step="publishing">
                        <span class="step-icon">📝</span>
                        <span class="step-label">게시글 생성</span>
                    </div>
                </div>
                <div class="aicr-progress-message" id="aicr-content-progress-message">작업을 시작하는 중...</div>
            </div>
        </div>

        <!-- 완료 결과 표시 -->
        <div id="aicr-result-preview" class="aicr-result-preview" style="display: none;">
            <div class="aicr-result-card success">
                <div class="aicr-result-icon">✅</div>
                <h2 id="aicr-result-title"><?php esc_html_e('재작성 완료!', 'ai-content-rewriter'); ?></h2>
                <p id="aicr-result-post-title" class="aicr-result-post-title"></p>
                <div id="aicr-result-category" class="aicr-result-category"></div>
                <div class="aicr-result-actions">
                    <a href="#" id="aicr-edit-post" class="button button-primary" target="_blank">
                        <?php esc_html_e('게시글 편집', 'ai-content-rewriter'); ?>
                    </a>
                    <a href="#" id="aicr-view-post" class="button" target="_blank">
                        <?php esc_html_e('게시글 보기', 'ai-content-rewriter'); ?>
                    </a>
                    <button type="button" id="aicr-new-content" class="button">
                        <?php esc_html_e('새 콘텐츠 작성', 'ai-content-rewriter'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- 오류 표시 -->
        <div id="aicr-error-preview" class="aicr-error-preview" style="display: none;">
            <div class="aicr-result-card error">
                <div class="aicr-result-icon">❌</div>
                <h2><?php esc_html_e('재작성 실패', 'ai-content-rewriter'); ?></h2>
                <p id="aicr-error-message" class="aicr-error-message"></p>
                <div class="aicr-result-actions">
                    <button type="button" id="aicr-retry" class="button button-primary">
                        <?php esc_html_e('다시 시도', 'ai-content-rewriter'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
