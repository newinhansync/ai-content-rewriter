<?php
/**
 * RSS 피드 관리 페이지
 *
 * @package AIContentRewriter\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

use AIContentRewriter\RSS\FeedRepository;

$feed_repository = new FeedRepository();
$user_id = get_current_user_id();
$feeds = $feed_repository->find_by_user($user_id);
$stats = $feed_repository->get_stats($user_id);
?>

<div class="wrap aicr-wrap">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('RSS 피드 관리', 'ai-content-rewriter'); ?>
    </h1>
    <a href="#" class="page-title-action" id="aicr-add-feed-btn">
        <?php esc_html_e('새 피드 추가', 'ai-content-rewriter'); ?>
    </a>
    <hr class="wp-header-end">

    <!-- 통계 카드 -->
    <div class="aicr-stats-row">
        <div class="aicr-stat-card">
            <span class="aicr-stat-number"><?php echo esc_html($stats['total_feeds']); ?></span>
            <span class="aicr-stat-label"><?php esc_html_e('전체 피드', 'ai-content-rewriter'); ?></span>
        </div>
        <div class="aicr-stat-card">
            <span class="aicr-stat-number"><?php echo esc_html($stats['active_feeds']); ?></span>
            <span class="aicr-stat-label"><?php esc_html_e('활성 피드', 'ai-content-rewriter'); ?></span>
        </div>
        <div class="aicr-stat-card">
            <span class="aicr-stat-number"><?php echo esc_html($stats['total_items']); ?></span>
            <span class="aicr-stat-label"><?php esc_html_e('전체 아이템', 'ai-content-rewriter'); ?></span>
        </div>
        <div class="aicr-stat-card">
            <span class="aicr-stat-number"><?php echo esc_html($stats['total_unread']); ?></span>
            <span class="aicr-stat-label"><?php esc_html_e('미읽음', 'ai-content-rewriter'); ?></span>
        </div>
    </div>

    <!-- 피드 목록 -->
    <div class="aicr-feeds-list">
        <?php if (empty($feeds)) : ?>
            <div class="aicr-empty-state">
                <span class="dashicons dashicons-rss"></span>
                <h3><?php esc_html_e('등록된 RSS 피드가 없습니다', 'ai-content-rewriter'); ?></h3>
                <p><?php esc_html_e('새 피드 추가 버튼을 클릭하여 RSS 피드를 등록하세요.', 'ai-content-rewriter'); ?></p>
                <button type="button" class="button button-primary" id="aicr-add-feed-btn-empty">
                    <?php esc_html_e('첫 번째 피드 추가', 'ai-content-rewriter'); ?>
                </button>
            </div>
        <?php else : ?>
            <?php foreach ($feeds as $feed) : ?>
                <div class="aicr-feed-card" data-feed-id="<?php echo esc_attr($feed->get_id()); ?>">
                    <div class="aicr-feed-header">
                        <div class="aicr-feed-status">
                            <?php if ($feed->is_active()) : ?>
                                <span class="aicr-status-dot active" title="<?php esc_attr_e('활성', 'ai-content-rewriter'); ?>"></span>
                            <?php elseif ($feed->has_error()) : ?>
                                <span class="aicr-status-dot error" title="<?php esc_attr_e('오류', 'ai-content-rewriter'); ?>"></span>
                            <?php else : ?>
                                <span class="aicr-status-dot paused" title="<?php esc_attr_e('일시정지', 'ai-content-rewriter'); ?>"></span>
                            <?php endif; ?>
                        </div>
                        <h3 class="aicr-feed-name"><?php echo esc_html($feed->get_name()); ?></h3>
                        <div class="aicr-feed-actions">
                            <button type="button" class="button aicr-refresh-feed" title="<?php esc_attr_e('새로고침', 'ai-content-rewriter'); ?>">
                                <span class="dashicons dashicons-update"></span>
                            </button>
                            <button type="button" class="button aicr-toggle-feed" title="<?php echo $feed->is_active() ? esc_attr__('일시정지', 'ai-content-rewriter') : esc_attr__('활성화', 'ai-content-rewriter'); ?>">
                                <span class="dashicons dashicons-<?php echo $feed->is_active() ? 'controls-pause' : 'controls-play'; ?>"></span>
                            </button>
                            <button type="button" class="button aicr-edit-feed" title="<?php esc_attr_e('편집', 'ai-content-rewriter'); ?>">
                                <span class="dashicons dashicons-edit"></span>
                            </button>
                            <button type="button" class="button aicr-delete-feed" title="<?php esc_attr_e('삭제', 'ai-content-rewriter'); ?>">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </div>
                    <div class="aicr-feed-url">
                        <a href="<?php echo esc_url($feed->get_feed_url()); ?>" target="_blank" rel="noopener">
                            <?php echo esc_html($feed->get_feed_url()); ?>
                        </a>
                    </div>
                    <?php if ($feed->has_error()) : ?>
                        <div class="aicr-feed-error">
                            <span class="dashicons dashicons-warning"></span>
                            <?php echo esc_html($feed->get_last_error()); ?>
                        </div>
                    <?php endif; ?>
                    <div class="aicr-feed-meta">
                        <span>
                            <span class="dashicons dashicons-clock"></span>
                            <?php echo esc_html($feed->get_time_since_last_fetch()); ?>
                        </span>
                        <span>
                            <span class="dashicons dashicons-admin-post"></span>
                            <?php
                            printf(
                                esc_html__('아이템: %d개', 'ai-content-rewriter'),
                                $feed->get_item_count()
                            );
                            ?>
                        </span>
                        <span>
                            <span class="dashicons dashicons-visibility"></span>
                            <?php
                            printf(
                                esc_html__('미읽음: %d개', 'ai-content-rewriter'),
                                $feed->get_unread_count()
                            );
                            ?>
                        </span>
                        <span>
                            <span class="dashicons dashicons-update"></span>
                            <?php echo esc_html($feed->get_interval_label()); ?>
                        </span>
                        <?php if ($feed->is_auto_rewrite()) : ?>
                            <span class="aicr-badge">
                                <?php esc_html_e('자동 재작성', 'ai-content-rewriter'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- 피드 추가/편집 모달 -->
<div id="aicr-feed-modal" class="aicr-modal" style="display: none;">
    <div class="aicr-modal-content">
        <div class="aicr-modal-header">
            <h2 id="aicr-feed-modal-title"><?php esc_html_e('새 피드 추가', 'ai-content-rewriter'); ?></h2>
            <button type="button" class="aicr-modal-close">&times;</button>
        </div>
        <div class="aicr-modal-body">
            <form id="aicr-feed-form">
                <input type="hidden" name="feed_id" id="feed_id" value="">

                <div class="aicr-form-group">
                    <label for="feed_url"><?php esc_html_e('피드 URL', 'ai-content-rewriter'); ?> <span class="required">*</span></label>
                    <div class="aicr-input-group">
                        <input type="url" name="feed_url" id="feed_url" class="regular-text" required
                               placeholder="https://example.com/feed/">
                        <button type="button" class="button" id="aicr-validate-feed">
                            <?php esc_html_e('검증', 'ai-content-rewriter'); ?>
                        </button>
                    </div>
                    <div id="aicr-feed-validation-result"></div>
                </div>

                <div class="aicr-form-group">
                    <label for="feed_name"><?php esc_html_e('피드 이름', 'ai-content-rewriter'); ?> <span class="required">*</span></label>
                    <input type="text" name="feed_name" id="feed_name" class="regular-text" required
                           placeholder="<?php esc_attr_e('피드 이름을 입력하세요', 'ai-content-rewriter'); ?>">
                </div>

                <div class="aicr-form-row">
                    <div class="aicr-form-group">
                        <label for="fetch_interval"><?php esc_html_e('갱신 주기', 'ai-content-rewriter'); ?></label>
                        <select name="fetch_interval" id="fetch_interval">
                            <option value="3600"><?php esc_html_e('1시간마다', 'ai-content-rewriter'); ?></option>
                            <option value="43200"><?php esc_html_e('12시간마다', 'ai-content-rewriter'); ?></option>
                            <option value="86400" selected><?php esc_html_e('매일', 'ai-content-rewriter'); ?></option>
                            <option value="604800"><?php esc_html_e('매주', 'ai-content-rewriter'); ?></option>
                        </select>
                    </div>
                    <div class="aicr-form-group">
                        <label for="default_category"><?php esc_html_e('기본 카테고리', 'ai-content-rewriter'); ?></label>
                        <?php
                        wp_dropdown_categories([
                            'name' => 'default_category',
                            'id' => 'default_category',
                            'show_option_none' => __('선택 안함', 'ai-content-rewriter'),
                            'option_none_value' => '',
                            'hierarchical' => true,
                            'hide_empty' => false,
                        ]);
                        ?>
                    </div>
                </div>

                <div class="aicr-form-row">
                    <div class="aicr-form-group">
                        <label for="default_template_id"><?php esc_html_e('기본 템플릿', 'ai-content-rewriter'); ?></label>
                        <select name="default_template_id" id="default_template_id">
                            <option value=""><?php esc_html_e('기본 템플릿', 'ai-content-rewriter'); ?></option>
                        </select>
                    </div>
                    <div class="aicr-form-group">
                        <label for="default_language"><?php esc_html_e('대상 언어', 'ai-content-rewriter'); ?></label>
                        <select name="default_language" id="default_language">
                            <option value="ko"><?php esc_html_e('한국어', 'ai-content-rewriter'); ?></option>
                            <option value="en"><?php esc_html_e('English', 'ai-content-rewriter'); ?></option>
                            <option value="ja"><?php esc_html_e('日本語', 'ai-content-rewriter'); ?></option>
                            <option value="zh"><?php esc_html_e('中文', 'ai-content-rewriter'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="aicr-form-group">
                    <label class="aicr-checkbox-label">
                        <input type="checkbox" name="auto_rewrite" id="auto_rewrite" value="1">
                        <?php esc_html_e('새 아이템 자동 재작성', 'ai-content-rewriter'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('새로운 피드 아이템이 발견되면 자동으로 AI 재작성을 수행합니다.', 'ai-content-rewriter'); ?>
                    </p>
                </div>

                <div class="aicr-form-group" id="auto_publish_group" style="display: none;">
                    <label class="aicr-checkbox-label">
                        <input type="checkbox" name="auto_publish" id="auto_publish" value="1">
                        <?php esc_html_e('재작성 후 자동 게시', 'ai-content-rewriter'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('재작성이 완료되면 자동으로 게시글을 발행합니다.', 'ai-content-rewriter'); ?>
                    </p>
                </div>
            </form>
        </div>
        <div class="aicr-modal-footer">
            <button type="button" class="button" id="aicr-feed-cancel">
                <?php esc_html_e('취소', 'ai-content-rewriter'); ?>
            </button>
            <button type="button" class="button button-primary" id="aicr-feed-save">
                <?php esc_html_e('저장', 'ai-content-rewriter'); ?>
            </button>
        </div>
    </div>
</div>
