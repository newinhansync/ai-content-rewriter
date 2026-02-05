<?php
/**
 * Settings Admin Page Template
 *
 * @package AIContentRewriter\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap aicr-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php settings_errors('aicr_messages'); ?>

    <form method="post" action="">
        <?php wp_nonce_field('aicr_settings', 'aicr_settings_nonce'); ?>

        <div class="aicr-settings-tabs">
            <nav class="nav-tab-wrapper">
                <a href="#api-settings" class="nav-tab nav-tab-active"><?php esc_html_e('API 설정', 'ai-content-rewriter'); ?></a>
                <a href="#general-settings" class="nav-tab"><?php esc_html_e('일반 설정', 'ai-content-rewriter'); ?></a>
                <a href="#content-settings" class="nav-tab"><?php esc_html_e('콘텐츠 설정', 'ai-content-rewriter'); ?></a>
                <a href="#image-settings" class="nav-tab"><?php esc_html_e('이미지 생성', 'ai-content-rewriter'); ?></a>
                <a href="#rss-settings" class="nav-tab"><?php esc_html_e('RSS 피드', 'ai-content-rewriter'); ?></a>
                <a href="#prompt-settings" class="nav-tab"><?php esc_html_e('프롬프트 관리', 'ai-content-rewriter'); ?></a>
                <a href="#automation-settings" class="nav-tab"><?php esc_html_e('자동화', 'ai-content-rewriter'); ?></a>
                <a href="#worker-settings" class="nav-tab"><?php esc_html_e('Cloudflare Worker', 'ai-content-rewriter'); ?></a>
                <a href="#advanced-settings" class="nav-tab"><?php esc_html_e('고급 설정', 'ai-content-rewriter'); ?></a>
            </nav>

            <!-- API 설정 -->
            <div id="api-settings" class="aicr-tab-panel active">
                <h2><?php esc_html_e('API 키 설정', 'ai-content-rewriter'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="chatgpt_api_key"><?php esc_html_e('OpenAI API Key', 'ai-content-rewriter'); ?></label>
                        </th>
                        <td>
                            <?php $has_chatgpt_key = !empty(get_option('aicr_chatgpt_api_key', '')); ?>
                            <input type="password" name="chatgpt_api_key" id="chatgpt_api_key"
                                   class="regular-text" value=""
                                   placeholder="<?php echo $has_chatgpt_key ? '••••••••••••••••' : ''; ?>">
                            <button type="button" class="button aicr-toggle-password"><?php esc_html_e('표시', 'ai-content-rewriter'); ?></button>
                            <?php if ($has_chatgpt_key): ?>
                                <span class="aicr-key-saved" style="color: green; margin-left: 10px;">✓ <?php esc_html_e('저장됨', 'ai-content-rewriter'); ?></span>
                            <?php endif; ?>
                            <p class="description">
                                <?php esc_html_e('ChatGPT (GPT-5) 사용을 위한 OpenAI API 키를 입력하세요.', 'ai-content-rewriter'); ?>
                                <?php if ($has_chatgpt_key): ?>
                                    <br><em><?php esc_html_e('새 키를 입력하면 기존 키가 대체됩니다.', 'ai-content-rewriter'); ?></em>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="gemini_api_key"><?php esc_html_e('Google Gemini API Key', 'ai-content-rewriter'); ?></label>
                        </th>
                        <td>
                            <?php $has_gemini_key = !empty(get_option('aicr_gemini_api_key', '')); ?>
                            <input type="password" name="gemini_api_key" id="gemini_api_key"
                                   class="regular-text" value=""
                                   placeholder="<?php echo $has_gemini_key ? '••••••••••••••••' : ''; ?>">
                            <button type="button" class="button aicr-toggle-password"><?php esc_html_e('표시', 'ai-content-rewriter'); ?></button>
                            <?php if ($has_gemini_key): ?>
                                <span class="aicr-key-saved" style="color: green; margin-left: 10px;">✓ <?php esc_html_e('저장됨', 'ai-content-rewriter'); ?></span>
                            <?php endif; ?>
                            <p class="description">
                                <?php esc_html_e('Google Gemini 3 사용을 위한 API 키를 입력하세요.', 'ai-content-rewriter'); ?>
                                <?php if ($has_gemini_key): ?>
                                    <br><em><?php esc_html_e('새 키를 입력하면 기존 키가 대체됩니다.', 'ai-content-rewriter'); ?></em>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('기본 AI 제공자', 'ai-content-rewriter'); ?></th>
                        <td>
                            <select name="default_ai_provider" id="default_ai_provider">
                                <option value="chatgpt" <?php selected(get_option('aicr_default_ai_provider'), 'chatgpt'); ?>>
                                    ChatGPT (GPT-5)
                                </option>
                                <option value="gemini" <?php selected(get_option('aicr_default_ai_provider'), 'gemini'); ?>>
                                    Google Gemini 3
                                </option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- 일반 설정 -->
            <div id="general-settings" class="aicr-tab-panel">
                <h2><?php esc_html_e('일반 설정', 'ai-content-rewriter'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('기본 언어', 'ai-content-rewriter'); ?></th>
                        <td>
                            <select name="default_language" id="default_language">
                                <option value="ko" <?php selected(get_option('aicr_default_language'), 'ko'); ?>>
                                    <?php esc_html_e('한국어', 'ai-content-rewriter'); ?>
                                </option>
                                <option value="en" <?php selected(get_option('aicr_default_language'), 'en'); ?>>
                                    <?php esc_html_e('영어', 'ai-content-rewriter'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('기본 포스트 상태', 'ai-content-rewriter'); ?></th>
                        <td>
                            <select name="default_post_status" id="default_post_status">
                                <option value="draft" <?php selected(get_option('aicr_default_post_status'), 'draft'); ?>>
                                    <?php esc_html_e('임시글', 'ai-content-rewriter'); ?>
                                </option>
                                <option value="pending" <?php selected(get_option('aicr_default_post_status'), 'pending'); ?>>
                                    <?php esc_html_e('검토 대기', 'ai-content-rewriter'); ?>
                                </option>
                                <option value="publish" <?php selected(get_option('aicr_default_post_status'), 'publish'); ?>>
                                    <?php esc_html_e('즉시 발행', 'ai-content-rewriter'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- 콘텐츠 설정 -->
            <div id="content-settings" class="aicr-tab-panel">
                <h2><?php esc_html_e('콘텐츠 설정', 'ai-content-rewriter'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('청크 크기', 'ai-content-rewriter'); ?></th>
                        <td>
                            <input type="number" name="chunk_size" id="chunk_size"
                                   value="<?php echo esc_attr(get_option('aicr_chunk_size', 3000)); ?>"
                                   min="1000" max="10000" step="500">
                            <p class="description">
                                <?php esc_html_e('긴 콘텐츠를 처리할 때 분할하는 문자 수 (기본: 3000)', 'ai-content-rewriter'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('메타데이터 자동 생성', 'ai-content-rewriter'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_generate_metadata" value="1"
                                       <?php checked(get_option('aicr_auto_generate_metadata'), '1'); ?>>
                                <?php esc_html_e('SEO 메타데이터 자동 생성 활성화', 'ai-content-rewriter'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- 이미지 생성 설정 -->
            <div id="image-settings" class="aicr-tab-panel">
                <?php include __DIR__ . '/settings-image.php'; ?>
            </div>

            <!-- RSS 피드 설정 -->
            <div id="rss-settings" class="aicr-tab-panel">
                <h2><?php esc_html_e('RSS 피드 설정', 'ai-content-rewriter'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('기본 갱신 주기', 'ai-content-rewriter'); ?></th>
                        <td>
                            <select name="rss_default_interval" id="rss_default_interval">
                                <option value="3600" <?php selected(get_option('aicr_rss_default_interval'), '3600'); ?>>
                                    <?php esc_html_e('1시간마다', 'ai-content-rewriter'); ?>
                                </option>
                                <option value="21600" <?php selected(get_option('aicr_rss_default_interval'), '21600'); ?>>
                                    <?php esc_html_e('6시간마다', 'ai-content-rewriter'); ?>
                                </option>
                                <option value="43200" <?php selected(get_option('aicr_rss_default_interval'), '43200'); ?>>
                                    <?php esc_html_e('12시간마다', 'ai-content-rewriter'); ?>
                                </option>
                                <option value="86400" <?php selected(get_option('aicr_rss_default_interval', '86400'), '86400'); ?>>
                                    <?php esc_html_e('매일', 'ai-content-rewriter'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('새 피드 등록 시 기본으로 적용되는 갱신 주기입니다.', 'ai-content-rewriter'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('피드당 최대 아이템 수', 'ai-content-rewriter'); ?></th>
                        <td>
                            <input type="number" name="rss_max_items_per_feed" id="rss_max_items_per_feed"
                                   value="<?php echo esc_attr(get_option('aicr_rss_max_items_per_feed', 50)); ?>"
                                   min="10" max="500" step="10">
                            <p class="description">
                                <?php esc_html_e('피드당 저장할 최대 아이템 수입니다. (기본: 50)', 'ai-content-rewriter'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('오래된 아이템 자동 삭제', 'ai-content-rewriter'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="rss_auto_cleanup" value="1"
                                       <?php checked(get_option('aicr_rss_auto_cleanup', '1'), '1'); ?>>
                                <?php esc_html_e('활성화', 'ai-content-rewriter'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('처리 완료된 오래된 피드 아이템을 자동으로 삭제합니다.', 'ai-content-rewriter'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('아이템 보존 기간', 'ai-content-rewriter'); ?></th>
                        <td>
                            <input type="number" name="rss_item_retention_days" id="rss_item_retention_days"
                                   value="<?php echo esc_attr(get_option('aicr_rss_item_retention_days', 30)); ?>"
                                   min="7" max="365">
                            <span><?php esc_html_e('일', 'ai-content-rewriter'); ?></span>
                            <p class="description">
                                <?php esc_html_e('이 기간이 지난 아이템은 자동 삭제됩니다. (기본: 30일)', 'ai-content-rewriter'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('동시 갱신 피드 수', 'ai-content-rewriter'); ?></th>
                        <td>
                            <input type="number" name="rss_concurrent_fetch" id="rss_concurrent_fetch"
                                   value="<?php echo esc_attr(get_option('aicr_rss_concurrent_fetch', 5)); ?>"
                                   min="1" max="20">
                            <p class="description">
                                <?php esc_html_e('스케줄 실행 시 동시에 갱신할 최대 피드 수입니다. (기본: 5)', 'ai-content-rewriter'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <hr>

                <h3><?php esc_html_e('자동 재작성 설정', 'ai-content-rewriter'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('자동 재작성 기본값', 'ai-content-rewriter'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="rss_default_auto_rewrite" value="1"
                                       <?php checked(get_option('aicr_rss_default_auto_rewrite'), '1'); ?>>
                                <?php esc_html_e('새 피드 등록 시 자동 재작성 기본 활성화', 'ai-content-rewriter'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('새로 등록하는 피드에 자동 재작성 옵션을 기본으로 활성화합니다.', 'ai-content-rewriter'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('자동 게시 기본값', 'ai-content-rewriter'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="rss_default_auto_publish" value="1"
                                       <?php checked(get_option('aicr_rss_default_auto_publish'), '1'); ?>>
                                <?php esc_html_e('재작성 후 자동으로 게시글 발행', 'ai-content-rewriter'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('자동 재작성이 완료되면 즉시 게시글로 발행합니다.', 'ai-content-rewriter'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('재작성 큐 제한', 'ai-content-rewriter'); ?></th>
                        <td>
                            <input type="number" name="rss_rewrite_queue_limit" id="rss_rewrite_queue_limit"
                                   value="<?php echo esc_attr(get_option('aicr_rss_rewrite_queue_limit', 10)); ?>"
                                   min="1" max="50">
                            <p class="description">
                                <?php esc_html_e('한 번의 스케줄 실행에서 재작성할 최대 아이템 수입니다. (기본: 10)', 'ai-content-rewriter'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- 프롬프트 관리 -->
            <div id="prompt-settings" class="aicr-tab-panel">
                <h2><?php esc_html_e('블로그 포스트 생성 프롬프트', 'ai-content-rewriter'); ?></h2>
                <p class="description">
                    <?php esc_html_e('콘텐츠 변환 시 AI에게 전달되는 프롬프트입니다. 변수는 실제 값으로 자동 치환됩니다.', 'ai-content-rewriter'); ?>
                </p>

                <?php
                $prompt_manager = \AIContentRewriter\Content\PromptManager::get_instance();
                $current_prompt = $prompt_manager->get_prompt();
                $default_prompt = $prompt_manager->get_default_prompt();
                $is_default = ($current_prompt === $default_prompt);
                ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="aicr-prompt-content"><?php esc_html_e('프롬프트 내용', 'ai-content-rewriter'); ?></label>
                        </th>
                        <td>
                            <textarea id="aicr-prompt-content" name="prompt_content" rows="25" class="large-text code" style="font-family: monospace; font-size: 13px;"><?php echo esc_textarea($current_prompt); ?></textarea>
                            <?php if (!$is_default): ?>
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

                <h3><?php esc_html_e('사용 가능한 변수', 'ai-content-rewriter'); ?></h3>
                <table class="widefat striped" style="max-width: 600px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('변수', 'ai-content-rewriter'); ?></th>
                            <th><?php esc_html_e('설명', 'ai-content-rewriter'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>{{content}}</code></td>
                            <td><?php esc_html_e('원본 콘텐츠', 'ai-content-rewriter'); ?></td>
                        </tr>
                        <tr>
                            <td><code>{{title}}</code></td>
                            <td><?php esc_html_e('원본 제목', 'ai-content-rewriter'); ?></td>
                        </tr>
                        <tr>
                            <td><code>{{source_url}}</code></td>
                            <td><?php esc_html_e('원본 URL', 'ai-content-rewriter'); ?></td>
                        </tr>
                        <tr>
                            <td><code>{{target_language}}</code></td>
                            <td><?php esc_html_e('출력 언어 (ko, en 등)', 'ai-content-rewriter'); ?></td>
                        </tr>
                    </tbody>
                </table>

                <p style="margin-top: 20px;">
                    <button type="button" id="aicr-save-prompt" class="button button-primary">
                        <?php esc_html_e('프롬프트 저장', 'ai-content-rewriter'); ?>
                    </button>
                    <button type="button" id="aicr-reset-prompt" class="button" <?php echo $is_default ? 'disabled' : ''; ?>>
                        <?php esc_html_e('기본값으로 복원', 'ai-content-rewriter'); ?>
                    </button>
                    <span id="aicr-prompt-status" style="margin-left: 10px;"></span>
                </p>
            </div>

            <!-- 자동화 설정 -->
            <div id="automation-settings" class="aicr-tab-panel">
                <?php include __DIR__ . '/automation.php'; ?>
            </div>

            <!-- Cloudflare Worker 설정 -->
            <div id="worker-settings" class="aicr-tab-panel">
                <?php include __DIR__ . '/settings-worker.php'; ?>
            </div>

            <!-- 고급 설정 -->
            <div id="advanced-settings" class="aicr-tab-panel">
                <h2><?php esc_html_e('고급 설정', 'ai-content-rewriter'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('로그 보존 기간', 'ai-content-rewriter'); ?></th>
                        <td>
                            <input type="number" name="log_retention_days" id="log_retention_days"
                                   value="<?php echo esc_attr(get_option('aicr_log_retention_days', 90)); ?>"
                                   min="7" max="365">
                            <span><?php esc_html_e('일', 'ai-content-rewriter'); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('디버그 모드', 'ai-content-rewriter'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="debug_mode" value="1"
                                       <?php checked(get_option('aicr_debug_mode'), '1'); ?>>
                                <?php esc_html_e('디버그 로깅 활성화', 'ai-content-rewriter'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <hr>

                <h3><?php esc_html_e('데이터 관리', 'ai-content-rewriter'); ?></h3>
                <p>
                    <button type="button" id="aicr-clear-cache" class="button">
                        <?php esc_html_e('캐시 지우기', 'ai-content-rewriter'); ?>
                    </button>
                    <button type="button" id="aicr-export-data" class="button">
                        <?php esc_html_e('데이터 내보내기', 'ai-content-rewriter'); ?>
                    </button>
                </p>
            </div>
        </div>

        <?php submit_button(__('설정 저장', 'ai-content-rewriter')); ?>
    </form>
</div>
