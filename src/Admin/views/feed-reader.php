<?php
/**
 * 피드 리더 페이지
 *
 * @package AIContentRewriter\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

use AIContentRewriter\RSS\FeedRepository;
use AIContentRewriter\RSS\FeedItemRepository;
use AIContentRewriter\RSS\FeedItem;

// 설정에서 기본값 가져오기
$default_ai_provider = get_option('aicr_default_ai_provider', 'chatgpt');
$default_language = get_option('aicr_default_language', 'ko');
$default_post_status = get_option('aicr_default_post_status', 'draft');

$feed_repository = new FeedRepository();
$item_repository = new FeedItemRepository();
$user_id = get_current_user_id();

$feeds = $feed_repository->find_by_user($user_id);
$feed_ids = array_map(fn($f) => $f->get_id(), $feeds);

// 필터 파라미터
$current_feed = isset($_GET['feed_id']) ? (int) $_GET['feed_id'] : 0;
$current_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$current_page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
$per_page = 20;

// 필터 적용
$filter_feed_ids = $current_feed ? [$current_feed] : $feed_ids;
$filters = [
    'limit' => $per_page,
    'offset' => ($current_page - 1) * $per_page,
];

if ($current_status) {
    $filters['status'] = $current_status;
}

$items = !empty($filter_feed_ids) ? $item_repository->find_by_feeds($filter_feed_ids, $filters) : [];
$total_items = !empty($filter_feed_ids) ? $item_repository->count_by_feeds($filter_feed_ids, $current_status ?: null) : 0;
$total_pages = ceil($total_items / $per_page);

// 상태별 카운트
$status_counts = [
    'all' => $item_repository->count_by_feeds($feed_ids),
    'unread' => $item_repository->count_by_feeds($feed_ids, FeedItem::STATUS_UNREAD),
    'read' => $item_repository->count_by_feeds($feed_ids, FeedItem::STATUS_READ),
    'completed' => $item_repository->count_by_feeds($feed_ids, FeedItem::STATUS_COMPLETED),
    'queued' => $item_repository->count_by_feeds($feed_ids, FeedItem::STATUS_QUEUED),
];
?>

<div class="wrap aicr-wrap aicr-feed-reader">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('피드 리더', 'ai-content-rewriter'); ?>
    </h1>
    <hr class="wp-header-end">

    <?php if (empty($feeds)) : ?>
        <div class="aicr-empty-state">
            <span class="dashicons dashicons-rss"></span>
            <h3><?php esc_html_e('등록된 RSS 피드가 없습니다', 'ai-content-rewriter'); ?></h3>
            <p><?php esc_html_e('먼저 RSS 피드를 등록해주세요.', 'ai-content-rewriter'); ?></p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=ai-content-rewriter-feeds')); ?>" class="button button-primary">
                <?php esc_html_e('피드 관리로 이동', 'ai-content-rewriter'); ?>
            </a>
        </div>
    <?php else : ?>
        <div class="aicr-reader-layout">
            <!-- 사이드바 -->
            <div class="aicr-reader-sidebar">
                <!-- 피드 필터 -->
                <div class="aicr-filter-section">
                    <h3><?php esc_html_e('피드', 'ai-content-rewriter'); ?></h3>
                    <ul class="aicr-feed-filter">
                        <li class="<?php echo !$current_feed ? 'active' : ''; ?>">
                            <a href="<?php echo esc_url(add_query_arg(['feed_id' => 0, 'paged' => 1])); ?>">
                                <span class="dashicons dashicons-rss"></span>
                                <?php esc_html_e('모든 피드', 'ai-content-rewriter'); ?>
                                <span class="count">(<?php echo esc_html($status_counts['all']); ?>)</span>
                            </a>
                        </li>
                        <?php foreach ($feeds as $feed) : ?>
                            <li class="<?php echo $current_feed === $feed->get_id() ? 'active' : ''; ?>">
                                <a href="<?php echo esc_url(add_query_arg(['feed_id' => $feed->get_id(), 'paged' => 1])); ?>">
                                    <?php if ($feed->is_active()) : ?>
                                        <span class="aicr-status-dot active"></span>
                                    <?php elseif ($feed->has_error()) : ?>
                                        <span class="aicr-status-dot error"></span>
                                    <?php else : ?>
                                        <span class="aicr-status-dot paused"></span>
                                    <?php endif; ?>
                                    <?php echo esc_html($feed->get_name()); ?>
                                    <?php if ($feed->get_unread_count() > 0) : ?>
                                        <span class="count">(<?php echo esc_html($feed->get_unread_count()); ?>)</span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- 상태 필터 -->
                <div class="aicr-filter-section">
                    <h3><?php esc_html_e('상태', 'ai-content-rewriter'); ?></h3>
                    <ul class="aicr-status-filter">
                        <li class="<?php echo !$current_status ? 'active' : ''; ?>">
                            <a href="<?php echo esc_url(add_query_arg(['status' => '', 'paged' => 1])); ?>">
                                <?php esc_html_e('전체', 'ai-content-rewriter'); ?>
                            </a>
                        </li>
                        <li class="<?php echo $current_status === 'unread' ? 'active' : ''; ?>">
                            <a href="<?php echo esc_url(add_query_arg(['status' => 'unread', 'paged' => 1])); ?>">
                                <?php esc_html_e('미읽음', 'ai-content-rewriter'); ?>
                                <span class="count">(<?php echo esc_html($status_counts['unread']); ?>)</span>
                            </a>
                        </li>
                        <li class="<?php echo $current_status === 'read' ? 'active' : ''; ?>">
                            <a href="<?php echo esc_url(add_query_arg(['status' => 'read', 'paged' => 1])); ?>">
                                <?php esc_html_e('읽음', 'ai-content-rewriter'); ?>
                            </a>
                        </li>
                        <li class="<?php echo $current_status === 'completed' ? 'active' : ''; ?>">
                            <a href="<?php echo esc_url(add_query_arg(['status' => 'completed', 'paged' => 1])); ?>">
                                <?php esc_html_e('완료', 'ai-content-rewriter'); ?>
                                <span class="count">(<?php echo esc_html($status_counts['completed']); ?>)</span>
                            </a>
                        </li>
                        <li class="<?php echo $current_status === 'queued' ? 'active' : ''; ?>">
                            <a href="<?php echo esc_url(add_query_arg(['status' => 'queued', 'paged' => 1])); ?>">
                                <?php esc_html_e('대기중', 'ai-content-rewriter'); ?>
                                <span class="count">(<?php echo esc_html($status_counts['queued']); ?>)</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- 일괄 작업 -->
                <div class="aicr-bulk-actions" style="display: none;">
                    <h3><?php esc_html_e('선택된 항목', 'ai-content-rewriter'); ?></h3>
                    <p class="selected-count">0개 선택됨</p>
                    <button type="button" class="button" id="aicr-bulk-rewrite">
                        <?php esc_html_e('일괄 재작성', 'ai-content-rewriter'); ?>
                    </button>
                    <button type="button" class="button" id="aicr-bulk-read">
                        <?php esc_html_e('읽음 처리', 'ai-content-rewriter'); ?>
                    </button>
                    <button type="button" class="button" id="aicr-bulk-skip">
                        <?php esc_html_e('건너뛰기', 'ai-content-rewriter'); ?>
                    </button>
                </div>
            </div>

            <!-- 메인 콘텐츠 -->
            <div class="aicr-reader-main">
                <?php if (empty($items)) : ?>
                    <div class="aicr-empty-state">
                        <span class="dashicons dashicons-admin-post"></span>
                        <h3><?php esc_html_e('아이템이 없습니다', 'ai-content-rewriter'); ?></h3>
                        <p><?php esc_html_e('선택한 필터에 해당하는 아이템이 없습니다.', 'ai-content-rewriter'); ?></p>
                    </div>
                <?php else : ?>
                    <!-- 전체 선택 -->
                    <div class="aicr-items-header">
                        <label class="aicr-checkbox-label">
                            <input type="checkbox" id="aicr-select-all">
                            <?php esc_html_e('전체 선택', 'ai-content-rewriter'); ?>
                        </label>
                        <span class="aicr-items-info">
                            <?php
                            printf(
                                esc_html__('%d개 중 %d-%d', 'ai-content-rewriter'),
                                $total_items,
                                (($current_page - 1) * $per_page) + 1,
                                min($current_page * $per_page, $total_items)
                            );
                            ?>
                        </span>
                    </div>

                    <!-- 아이템 목록 -->
                    <div class="aicr-items-list">
                        <?php foreach ($items as $item) : ?>
                            <div class="aicr-item-card <?php echo $item->is_unread() ? 'unread' : ''; ?>"
                                 data-item-id="<?php echo esc_attr($item->get_id()); ?>">
                                <div class="aicr-item-checkbox">
                                    <input type="checkbox" class="aicr-item-select" value="<?php echo esc_attr($item->get_id()); ?>">
                                </div>
                                <div class="aicr-item-status">
                                    <?php if ($item->is_unread()) : ?>
                                        <span class="aicr-unread-dot" title="<?php esc_attr_e('미읽음', 'ai-content-rewriter'); ?>"></span>
                                    <?php endif; ?>
                                </div>
                                <div class="aicr-item-content">
                                    <h4 class="aicr-item-title">
                                        <a href="#" class="aicr-preview-item">
                                            <?php echo esc_html($item->get_title()); ?>
                                        </a>
                                    </h4>
                                    <div class="aicr-item-meta">
                                        <?php if (isset($item->to_array()['feed_name'])) : ?>
                                            <span class="aicr-item-feed">
                                                <?php echo esc_html($item->to_array()['feed_name']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="aicr-item-date">
                                            <?php echo esc_html($item->get_pub_date_formatted()); ?>
                                        </span>
                                        <?php if ($item->get_author()) : ?>
                                            <span class="aicr-item-author">
                                                <?php echo esc_html($item->get_author()); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="aicr-item-excerpt">
                                        <?php echo esc_html($item->get_excerpt(150)); ?>
                                    </p>
                                    <?php if ($item->is_completed() && $item->get_rewritten_post_id()) : ?>
                                        <div class="aicr-item-result">
                                            <span class="dashicons dashicons-yes-alt"></span>
                                            <a href="<?php echo esc_url(get_edit_post_link($item->get_rewritten_post_id())); ?>" target="_blank">
                                                <?php esc_html_e('게시글 보기', 'ai-content-rewriter'); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="aicr-item-actions">
                                    <button type="button" class="button aicr-preview-item" title="<?php esc_attr_e('미리보기', 'ai-content-rewriter'); ?>">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </button>
                                    <?php if ($item->can_rewrite()) : ?>
                                        <button type="button" class="button button-primary aicr-rewrite-item" title="<?php esc_attr_e('재작성', 'ai-content-rewriter'); ?>">
                                            <span class="dashicons dashicons-edit"></span>
                                        </button>
                                    <?php endif; ?>
                                    <a href="<?php echo esc_url($item->get_link()); ?>" target="_blank" rel="noopener"
                                       class="button" title="<?php esc_attr_e('원본 보기', 'ai-content-rewriter'); ?>">
                                        <span class="dashicons dashicons-external"></span>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- 페이지네이션 -->
                    <?php if ($total_pages > 1) : ?>
                        <div class="aicr-pagination">
                            <?php
                            echo paginate_links([
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $total_pages,
                                'current' => $current_page,
                            ]);
                            ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- 아이템 미리보기 모달 -->
<div id="aicr-preview-modal" class="aicr-modal" style="display: none;">
    <div class="aicr-modal-content aicr-modal-large">
        <div class="aicr-modal-header">
            <h2 id="aicr-preview-title"></h2>
            <button type="button" class="aicr-modal-close">&times;</button>
        </div>
        <div class="aicr-modal-body">
            <div id="aicr-preview-meta" class="aicr-preview-meta"></div>
            <div id="aicr-preview-content" class="aicr-preview-content"></div>
        </div>
        <div class="aicr-modal-footer">
            <a href="#" id="aicr-preview-original" target="_blank" rel="noopener" class="button">
                <?php esc_html_e('원본 보기', 'ai-content-rewriter'); ?>
            </a>
            <button type="button" class="button button-primary" id="aicr-preview-rewrite">
                <?php esc_html_e('재작성', 'ai-content-rewriter'); ?>
            </button>
        </div>
    </div>
</div>

<!-- 재작성 모달 -->
<div id="aicr-rewrite-modal" class="aicr-modal" style="display: none;">
    <div class="aicr-modal-content">
        <div class="aicr-modal-header">
            <h2><?php esc_html_e('RSS 아이템 재작성', 'ai-content-rewriter'); ?></h2>
            <button type="button" class="aicr-modal-close">&times;</button>
        </div>
        <div class="aicr-modal-body">
            <!-- 재작성 폼 (진행 중에는 숨겨짐) -->
            <div class="aicr-rewrite-form-content">
            <form id="aicr-rewrite-form">
                <input type="hidden" name="item_id" id="rewrite_item_id" value="">

                <div class="aicr-item-info">
                    <p><strong><?php esc_html_e('원본 제목:', 'ai-content-rewriter'); ?></strong> <span id="rewrite_title"></span></p>
                    <p><strong><?php esc_html_e('출처:', 'ai-content-rewriter'); ?></strong> <span id="rewrite_source"></span></p>
                </div>

                <div class="aicr-form-row">
                    <div class="aicr-form-group">
                        <label for="rewrite_ai_provider"><?php esc_html_e('AI 모델', 'ai-content-rewriter'); ?></label>
                        <select name="ai_provider" id="rewrite_ai_provider">
                            <option value="chatgpt" <?php selected($default_ai_provider, 'chatgpt'); ?>>ChatGPT (GPT-5)</option>
                            <option value="gemini" <?php selected($default_ai_provider, 'gemini'); ?>>Gemini 3</option>
                        </select>
                    </div>
                    <div class="aicr-form-group">
                        <label for="rewrite_template"><?php esc_html_e('프롬프트 템플릿', 'ai-content-rewriter'); ?></label>
                        <select name="template_id" id="rewrite_template">
                            <option value=""><?php esc_html_e('기본 템플릿', 'ai-content-rewriter'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="aicr-form-row">
                    <div class="aicr-form-group">
                        <label for="rewrite_language"><?php esc_html_e('대상 언어', 'ai-content-rewriter'); ?></label>
                        <select name="target_language" id="rewrite_language">
                            <option value="ko" <?php selected($default_language, 'ko'); ?>><?php esc_html_e('한국어', 'ai-content-rewriter'); ?></option>
                            <option value="en" <?php selected($default_language, 'en'); ?>><?php esc_html_e('English', 'ai-content-rewriter'); ?></option>
                            <option value="ja" <?php selected($default_language, 'ja'); ?>><?php esc_html_e('日本語', 'ai-content-rewriter'); ?></option>
                        </select>
                    </div>
                    <div class="aicr-form-group">
                        <label for="rewrite_category"><?php esc_html_e('카테고리', 'ai-content-rewriter'); ?></label>
                        <?php
                        wp_dropdown_categories([
                            'name' => 'category',
                            'id' => 'rewrite_category',
                            'show_option_none' => __('선택 안함', 'ai-content-rewriter'),
                            'option_none_value' => '',
                            'hierarchical' => true,
                            'hide_empty' => false,
                        ]);
                        ?>
                    </div>
                </div>

                <div class="aicr-form-group">
                    <label><?php esc_html_e('게시 상태', 'ai-content-rewriter'); ?></label>
                    <div class="aicr-radio-group">
                        <label>
                            <input type="radio" name="post_status" value="draft" <?php checked($default_post_status, 'draft'); ?>>
                            <?php esc_html_e('초안', 'ai-content-rewriter'); ?>
                        </label>
                        <label>
                            <input type="radio" name="post_status" value="pending" <?php checked($default_post_status, 'pending'); ?>>
                            <?php esc_html_e('검토 대기', 'ai-content-rewriter'); ?>
                        </label>
                        <label>
                            <input type="radio" name="post_status" value="publish" <?php checked($default_post_status, 'publish'); ?>>
                            <?php esc_html_e('바로 게시', 'ai-content-rewriter'); ?>
                        </label>
                    </div>
                </div>
            </form>
            </div><!-- /.aicr-rewrite-form-content -->
        </div>
        <div class="aicr-modal-footer aicr-rewrite-form-content">
            <button type="button" class="button" id="aicr-rewrite-cancel">
                <?php esc_html_e('취소', 'ai-content-rewriter'); ?>
            </button>
            <button type="button" class="button button-primary" id="aicr-rewrite-start">
                <?php esc_html_e('재작성 시작', 'ai-content-rewriter'); ?>
            </button>
        </div>
    </div>
</div>
