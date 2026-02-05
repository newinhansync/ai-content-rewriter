<?php
/**
 * Image Metabox
 *
 * @package AIContentRewriter\Admin
 */

namespace AIContentRewriter\Admin;

use AIContentRewriter\Image\ImageGenerator;
use AIContentRewriter\Image\ImagePromptManager;

/**
 * 게시글 편집 화면의 이미지 생성 메타박스
 */
class ImageMetabox {
    /**
     * 메타박스 ID
     */
    public const METABOX_ID = 'aicr_image_generation';

    /**
     * 초기화
     */
    public function init(): void {
        add_action('add_meta_boxes', [$this, 'register_metabox']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * 메타박스 등록
     */
    public function register_metabox(): void {
        add_meta_box(
            self::METABOX_ID,
            __('AI 이미지 생성', 'ai-content-rewriter'),
            [$this, 'render_metabox'],
            'post',
            'side',
            'default'
        );
    }

    /**
     * 에셋 로드 (게시글 편집 화면에서만)
     */
    public function enqueue_assets(string $hook): void {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'post') {
            return;
        }

        wp_enqueue_style(
            'aicr-image-metabox',
            AICR_PLUGIN_URL . 'assets/css/image-metabox.css',
            [],
            AICR_VERSION
        );

        wp_enqueue_script(
            'aicr-image-metabox',
            AICR_PLUGIN_URL . 'assets/js/image-metabox.js',
            ['jquery'],
            AICR_VERSION,
            true
        );

        wp_localize_script('aicr-image-metabox', 'aicr_image', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aicr_nonce'),
            'strings' => [
                'generating' => __('이미지 생성 중...', 'ai-content-rewriter'),
                'success' => __('이미지가 생성되었습니다!', 'ai-content-rewriter'),
                'error' => __('오류가 발생했습니다', 'ai-content-rewriter'),
                'confirm_regenerate' => __('기존 AI 생성 이미지를 삭제하고 새로 생성합니다. 계속하시겠습니까?', 'ai-content-rewriter'),
                'no_content' => __('게시글 내용이 없습니다. 먼저 내용을 작성해주세요.', 'ai-content-rewriter'),
            ],
        ]);
    }

    /**
     * 메타박스 렌더링
     */
    public function render_metabox(\WP_Post $post): void {
        // 이미지 생성기 초기화
        $generator = new ImageGenerator();
        $promptManager = ImagePromptManager::get_instance();

        // 현재 상태 조회
        $status = $generator->getGenerationStatus($post->ID);
        $styles = $promptManager->get_all_styles();

        // 기본 설정값
        $defaultCount = (int) get_option('aicr_image_default_count', 2);
        $defaultStyle = get_option('aicr_image_default_style', '일러스트레이션');
        $defaultRatio = get_option('aicr_image_default_ratio', '16:9');

        // Nonce 필드
        wp_nonce_field('aicr_image_metabox', 'aicr_image_nonce');
        ?>
        <div class="aicr-image-metabox">
            <?php if ($status['generated']): ?>
                <div class="aicr-status aicr-status-success">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php
                    printf(
                        /* translators: 1: image count, 2: generation date */
                        esc_html__('%1$d개 이미지 생성됨 (%2$s)', 'ai-content-rewriter'),
                        $status['count'],
                        date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($status['generated_at']))
                    );
                    ?>
                </div>
            <?php else: ?>
                <div class="aicr-status aicr-status-pending">
                    <span class="dashicons dashicons-info-outline"></span>
                    <?php esc_html_e('AI 이미지가 생성되지 않았습니다.', 'ai-content-rewriter'); ?>
                </div>
            <?php endif; ?>

            <div class="aicr-image-options">
                <p>
                    <label for="aicr-image-count"><?php esc_html_e('이미지 수', 'ai-content-rewriter'); ?></label>
                    <select id="aicr-image-count" name="aicr_image_count">
                        <?php for ($i = 2; $i <= 5; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php selected(max(2, $defaultCount), $i); ?>>
                                <?php echo $i; ?> (표지 1 + 콘텐츠 <?php echo $i - 1; ?>)
                            </option>
                        <?php endfor; ?>
                    </select>
                    <small style="display: block; color: #666; margin-top: 4px;">
                        <?php esc_html_e('첫 번째는 표지 이미지, 나머지는 콘텐츠 이미지', 'ai-content-rewriter'); ?>
                    </small>
                </p>

                <p>
                    <label for="aicr-image-style"><?php esc_html_e('스타일', 'ai-content-rewriter'); ?></label>
                    <select id="aicr-image-style" name="aicr_image_style">
                        <?php foreach ($styles as $style): ?>
                            <option value="<?php echo esc_attr($style->name); ?>" <?php selected($defaultStyle, $style->name); ?>>
                                <?php echo esc_html($style->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <p>
                    <label for="aicr-image-ratio"><?php esc_html_e('비율', 'ai-content-rewriter'); ?></label>
                    <select id="aicr-image-ratio" name="aicr_image_ratio">
                        <option value="1:1" <?php selected($defaultRatio, '1:1'); ?>>1:1 (정사각형)</option>
                        <option value="3:4" <?php selected($defaultRatio, '3:4'); ?>>3:4 (세로)</option>
                        <option value="4:3" <?php selected($defaultRatio, '4:3'); ?>>4:3 (가로)</option>
                        <option value="9:16" <?php selected($defaultRatio, '9:16'); ?>>9:16 (스토리)</option>
                        <option value="16:9" <?php selected($defaultRatio, '16:9'); ?>>16:9 (와이드)</option>
                    </select>
                </p>

                <p>
                    <label for="aicr-image-instructions"><?php esc_html_e('추가 지시사항', 'ai-content-rewriter'); ?></label>
                    <textarea id="aicr-image-instructions" name="aicr_image_instructions" rows="2" placeholder="<?php esc_attr_e('선택사항: 특정 요구사항 입력', 'ai-content-rewriter'); ?>"></textarea>
                </p>
            </div>

            <div class="aicr-image-actions">
                <input type="hidden" id="aicr-post-id" value="<?php echo esc_attr($post->ID); ?>">

                <button type="button" id="aicr-generate-images" class="button button-primary button-large">
                    <span class="dashicons dashicons-format-image"></span>
                    <?php esc_html_e('이미지 생성', 'ai-content-rewriter'); ?>
                </button>

                <?php if ($status['generated']): ?>
                    <button type="button" id="aicr-remove-images" class="button button-link-delete">
                        <?php esc_html_e('생성된 이미지 제거', 'ai-content-rewriter'); ?>
                    </button>
                <?php endif; ?>
            </div>

            <div id="aicr-image-progress" class="aicr-progress" style="display: none;">
                <div class="aicr-progress-bar">
                    <div class="aicr-progress-fill"></div>
                </div>
                <p class="aicr-progress-text"></p>
            </div>

            <div id="aicr-image-result" class="aicr-result" style="display: none;"></div>
        </div>
        <?php
    }
}
