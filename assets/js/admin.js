/**
 * AI Content Rewriter - Admin JavaScript
 *
 * @package AIContentRewriter
 */

(function($) {
    'use strict';

    // AICR Admin Object
    const AICR = {
        /**
         * Initialize
         */
        init: function() {
            this.fixErrorCardPosition();
            this.bindEvents();
            this.initTabs();
            this.initModals();
        },

        /**
         * Fix error card position if it's outside its container
         * (Workaround for DOM parsing issue)
         */
        fixErrorCardPosition: function() {
            const $errorCard = $('.wrap.aicr-wrap > .aicr-result-card.error');
            const $errorPreview = $('#aicr-error-preview');

            // If error card is outside its container, move it back
            if ($errorCard.length && $errorPreview.length && $errorPreview.children('.aicr-result-card.error').length === 0) {
                $errorCard.appendTo($errorPreview);
                $errorPreview.hide();
            }
        },

        /**
         * Bind Events
         */
        bindEvents: function() {
            // Source type tabs
            $(document).on('click', '.aicr-source-tabs .aicr-tab', this.handleSourceTab);

            // Rewrite form submission
            $('#aicr-rewrite-form').on('submit', this.handleRewriteSubmit);

            // Settings form submission
            const $settingsForm = $('.aicr-settings-tabs').closest('form');
            if ($settingsForm.length > 0) {
                $settingsForm.on('submit', this.handleSettingsSubmit);
            }

            // Settings tabs
            $(document).on('click', '.nav-tab', this.handleSettingsTab);

            // Toggle password visibility
            $(document).on('click', '.aicr-toggle-password', this.togglePassword);

            // Template actions
            $(document).on('click', '.aicr-edit-template', this.openTemplateModal);
            $(document).on('click', '#aicr-add-template', this.openNewTemplateModal);

            // Schedule actions
            $(document).on('click', '#aicr-add-schedule', this.openScheduleModal);

            // Modal close
            $(document).on('click', '.aicr-modal-close, .aicr-modal-cancel', this.closeModal);

            // Close modal on outside click
            $(document).on('click', '.aicr-modal', function(e) {
                if ($(e.target).hasClass('aicr-modal')) {
                    AICR.closeModal();
                }
            });

            // 새 콘텐츠 / 다시 시도 버튼
            $(document).on('click', '#aicr-new-content, #aicr-retry', function() {
                AICR.resetContentForm();
            });
        },

        /**
         * Initialize Tabs
         */
        initTabs: function() {
            // Show first tab by default
            $('.aicr-tab-content:first').addClass('active');
            $('.aicr-tab-panel:first').addClass('active');
        },

        /**
         * Initialize Modals
         */
        initModals: function() {
            // Hide all modals initially
            $('.aicr-modal').hide();
        },

        /**
         * Handle Source Tab Click
         */
        handleSourceTab: function(e) {
            e.preventDefault();
            const tab = $(this).data('tab');

            // Update active tab
            $('.aicr-source-tabs .aicr-tab').removeClass('active');
            $(this).addClass('active');

            // Show corresponding content
            $('.aicr-tab-content').removeClass('active');
            $('#aicr-tab-' + tab).addClass('active');
        },

        /**
         * Handle Settings Tab Click
         */
        handleSettingsTab: function(e) {
            e.preventDefault();
            const targetId = $(this).attr('href');

            // Update active tab
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            // Show corresponding panel
            $('.aicr-tab-panel').removeClass('active');
            $(targetId).addClass('active');
        },

        /**
         * Handle Settings Form Submit
         */
        handleSettingsSubmit: function(e) {
            e.preventDefault();

            const $form = $(this);
            const $submitBtn = $form.find('input[type="submit"], button[type="submit"]');
            const originalText = $submitBtn.val() || $submitBtn.text();

            // Show loading state
            $submitBtn.prop('disabled', true);
            if ($submitBtn.is('input')) {
                $submitBtn.val('저장 중...');
            } else {
                $submitBtn.text('저장 중...');
            }

            // Prepare data
            const formData = {
                action: 'aicr_save_settings',
                nonce: aicr_ajax.nonce,
                chatgpt_api_key: $('#chatgpt_api_key').val(),
                gemini_api_key: $('#gemini_api_key').val(),
                default_ai_provider: $('#default_ai_provider').val(),
                default_language: $('#default_language').val(),
                default_post_status: $('#default_post_status').val(),
                chunk_size: $('#chunk_size').val(),
                auto_generate_metadata: $('input[name="auto_generate_metadata"]').is(':checked') ? 1 : 0,
                log_retention_days: $('#log_retention_days').val(),
                debug_mode: $('input[name="debug_mode"]').is(':checked') ? 1 : 0
            };

            // AJAX request
            $.post(aicr_ajax.ajax_url, formData)
                .done(function(response) {
                    if (response.success) {
                        // Show success notice
                        const $notice = $('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
                        $('.wrap.aicr-wrap h1').after($notice);

                        // Clear API key fields (show masked version)
                        if ($('#chatgpt_api_key').val()) {
                            $('#chatgpt_api_key').val('');
                            $('#chatgpt_api_key').attr('placeholder', '••••••••••••••••');
                        }
                        if ($('#gemini_api_key').val()) {
                            $('#gemini_api_key').val('');
                            $('#gemini_api_key').attr('placeholder', '••••••••••••••••');
                        }

                        // Auto-dismiss after 3 seconds
                        setTimeout(function() {
                            $notice.fadeOut(300, function() { $(this).remove(); });
                        }, 3000);
                    } else {
                        alert('오류: ' + (response.data.message || '설정 저장에 실패했습니다.'));
                    }
                })
                .fail(function() {
                    alert('설정 저장 중 오류가 발생했습니다.');
                })
                .always(function() {
                    $submitBtn.prop('disabled', false);
                    if ($submitBtn.is('input')) {
                        $submitBtn.val(originalText);
                    } else {
                        $submitBtn.text(originalText);
                    }
                });
        },

        /**
         * Polling interval for content rewrite
         */
        contentPollingInterval: null,

        /**
         * Handle Rewrite Form Submit (비동기 방식)
         */
        handleRewriteSubmit: function(e) {
            e.preventDefault();

            const $form = $(this);
            const $submitBtn = $form.find('button[type="submit"]');

            // Validate input
            const sourceUrl = $('#source_url').val();
            const sourceText = $('#source_text').val();
            const activeTab = $('.aicr-source-tabs .aicr-tab.active').data('tab');

            if (activeTab === 'url' && !sourceUrl) {
                alert(aicr_ajax.strings.error + ': URL을 입력하세요.');
                return;
            }

            if (activeTab === 'text' && !sourceText) {
                alert(aicr_ajax.strings.error + ': 텍스트를 입력하세요.');
                return;
            }

            // 진행 상황 UI 표시
            AICR.showContentProgressUI();

            // Prepare data
            const formData = {
                action: 'aicr_start_content_task',
                nonce: aicr_ajax.nonce,
                source_type: activeTab,
                source_url: sourceUrl,
                source_text: sourceText,
                ai_provider: $('#ai_provider').val(),
                language: $('#target_language').val(),
                template_id: $('#prompt_template').val() || '',
                post_status: $('#post_status').val(),
                category: $('#post_category').val() || ''
            };

            // 비동기 작업 시작
            $.post(aicr_ajax.ajax_url, formData)
                .done(function(response) {
                    if (response.success) {
                        // 폴링 시작
                        AICR.startContentPolling(response.data.task_id);
                    } else {
                        AICR.hideContentProgressUI();
                        AICR.showContentError(response.data.message);
                    }
                })
                .fail(function() {
                    AICR.hideContentProgressUI();
                    AICR.showContentError('재작성 시작 중 오류가 발생했습니다.');
                });
        },

        /**
         * 콘텐츠 진행 상황 UI 표시
         */
        showContentProgressUI: function() {
            // 폼 숨기기
            $('.aicr-main-form').hide();
            $('#aicr-result-preview').hide();
            $('#aicr-error-preview').hide();

            // 진행 상황 UI 표시
            $('#aicr-content-progress').show();

            // 상태 초기화
            $('#aicr-content-progress-bar').css('width', '0%');
            $('#aicr-content-progress-message').text('작업을 시작하는 중...');
            $('.aicr-content-progress .aicr-progress-step').removeClass('active completed');

            // 스크롤
            $('html, body').animate({
                scrollTop: $('#aicr-content-progress').offset().top - 50
            }, 300);
        },

        /**
         * 콘텐츠 진행 상황 UI 숨기기
         */
        hideContentProgressUI: function() {
            $('#aicr-content-progress').hide();
        },

        /**
         * 콘텐츠 폴링 시작
         */
        startContentPolling: function(taskId) {
            const pollInterval = 1500; // 1.5초마다 확인
            const maxAttempts = 200; // 최대 5분
            let attempts = 0;

            AICR.contentPollingInterval = setInterval(function() {
                attempts++;

                if (attempts > maxAttempts) {
                    AICR.stopContentPolling();
                    AICR.hideContentProgressUI();
                    AICR.showContentError('작업 시간이 초과되었습니다. 잠시 후 다시 시도해주세요.');
                    return;
                }

                $.post(aicr_ajax.ajax_url, {
                    action: 'aicr_check_content_status',
                    nonce: aicr_ajax.nonce,
                    task_id: taskId
                }).done(function(response) {
                    if (response.success) {
                        AICR.updateContentProgressUI(response.data);

                        // 완료 또는 실패 시 폴링 중지
                        if (response.data.status === 'completed') {
                            AICR.stopContentPolling();
                            AICR.handleContentComplete(response.data);
                        } else if (response.data.status === 'failed') {
                            AICR.stopContentPolling();
                            AICR.handleContentError(response.data);
                        }
                    }
                }).fail(function() {
                    console.error('Polling error');
                });
            }, pollInterval);
        },

        /**
         * 콘텐츠 폴링 중지
         */
        stopContentPolling: function() {
            if (AICR.contentPollingInterval) {
                clearInterval(AICR.contentPollingInterval);
                AICR.contentPollingInterval = null;
            }
        },

        /**
         * 콘텐츠 진행 상황 UI 업데이트
         */
        updateContentProgressUI: function(data) {
            // 프로그레스 바 업데이트
            $('#aicr-content-progress-bar').css('width', data.progress + '%');

            // 메시지 업데이트
            $('#aicr-content-progress-message').text(data.message || '처리 중...');

            // 현재 단계 표시
            const stepOrder = ['extracting', 'rewriting', 'publishing', 'completed'];
            const currentStepIndex = stepOrder.indexOf(data.step);

            $('.aicr-content-progress .aicr-progress-step').each(function() {
                const $step = $(this);
                const stepName = $step.data('step');
                const stepIndex = stepOrder.indexOf(stepName);

                if (stepIndex < currentStepIndex) {
                    $step.removeClass('active').addClass('completed');
                } else if (stepIndex === currentStepIndex) {
                    $step.addClass('active').removeClass('completed');
                } else {
                    $step.removeClass('active completed');
                }
            });
        },

        /**
         * 콘텐츠 재작성 완료 처리
         */
        handleContentComplete: function(data) {
            AICR.hideContentProgressUI();

            const result = data.result || {};

            // 결과 표시
            $('#aicr-result-post-title').text(result.post_title || '새 게시글');

            if (result.category_name) {
                $('#aicr-result-category').html('<span class="category-badge">' + result.category_name + '</span>').show();
            } else {
                $('#aicr-result-category').hide();
            }

            $('#aicr-edit-post').attr('href', result.edit_url || '#');
            $('#aicr-view-post').attr('href', result.view_url || '#');

            $('#aicr-result-preview').show();

            // 스크롤
            $('html, body').animate({
                scrollTop: $('#aicr-result-preview').offset().top - 50
            }, 300);
        },

        /**
         * 콘텐츠 재작성 오류 처리
         */
        handleContentError: function(data) {
            AICR.hideContentProgressUI();
            AICR.showContentError(data.error || data.message || '알 수 없는 오류가 발생했습니다.');
        },

        /**
         * 오류 표시
         */
        showContentError: function(message) {
            $('#aicr-error-message').text(message);
            $('#aicr-error-preview').show();

            // 스크롤
            $('html, body').animate({
                scrollTop: $('#aicr-error-preview').offset().top - 50
            }, 300);
        },

        /**
         * 폼으로 돌아가기
         */
        resetContentForm: function() {
            $('#aicr-content-progress').hide();
            $('#aicr-result-preview').hide();
            $('#aicr-error-preview').hide();
            $('.aicr-main-form').show();

            // 스크롤
            $('html, body').animate({
                scrollTop: $('.aicr-main-form').offset().top - 50
            }, 300);
        },

        /**
         * Toggle Password Visibility
         */
        togglePassword: function() {
            const $input = $(this).prev('input');
            const type = $input.attr('type') === 'password' ? 'text' : 'password';
            $input.attr('type', type);
            $(this).text(type === 'password' ? '표시' : '숨기기');
        },

        /**
         * Open Template Modal
         */
        openTemplateModal: function(e) {
            e.preventDefault();
            const $card = $(this).closest('.aicr-template-card');
            const templateId = $card.data('template-id');

            // TODO: Load template data
            $('#template_id').val(templateId);
            $('#aicr-template-modal-title').text('템플릿 편집');
            $('#aicr-template-modal').show();
        },

        /**
         * Open New Template Modal
         */
        openNewTemplateModal: function(e) {
            e.preventDefault();
            $('#aicr-template-form')[0].reset();
            $('#template_id').val('');
            $('#aicr-template-modal-title').text('새 템플릿 추가');
            $('#aicr-template-modal').show();
        },

        /**
         * Open Schedule Modal
         */
        openScheduleModal: function(e) {
            e.preventDefault();
            $('#aicr-schedule-form')[0].reset();
            $('#schedule_id').val('');
            $('#aicr-schedule-modal').show();
        },

        /**
         * Close Modal
         */
        closeModal: function() {
            $('.aicr-modal').hide();
        }
    };

    // ================================
    // RSS Feed Management Module
    // ================================
    const AICR_RSS = {
        /**
         * Initialize RSS Module
         */
        init: function() {
            this.bindFeedEvents();
            this.bindReaderEvents();
        },

        /**
         * Bind Feed Management Events
         */
        bindFeedEvents: function() {
            // Add Feed Button
            $(document).on('click', '#aicr-add-feed-btn, #aicr-add-feed-btn-empty', this.openFeedModal.bind(this));

            // Feed Modal Actions
            $(document).on('click', '#aicr-validate-feed', this.validateFeed.bind(this));
            $(document).on('click', '#aicr-feed-save', this.saveFeed.bind(this));
            $(document).on('click', '#aicr-feed-cancel, #aicr-feed-modal .aicr-modal-close', this.closeFeedModal.bind(this));

            // Feed Card Actions
            $(document).on('click', '.aicr-refresh-feed', this.refreshFeed.bind(this));
            $(document).on('click', '.aicr-toggle-feed', this.toggleFeed.bind(this));
            $(document).on('click', '.aicr-edit-feed', this.editFeed.bind(this));
            $(document).on('click', '.aicr-delete-feed', this.deleteFeed.bind(this));

            // Auto rewrite checkbox toggle
            $(document).on('change', '#auto_rewrite', function() {
                $('#auto_publish_group').toggle(this.checked);
            });
        },

        /**
         * Bind Feed Reader Events
         */
        bindReaderEvents: function() {
            // Search
            $(document).on('click', '#aicr-search-btn', this.searchItems.bind(this));
            $(document).on('keypress', '#aicr-search-input', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    AICR_RSS.searchItems();
                }
            });

            // Filter changes
            $(document).on('change', '.aicr-feed-filter-list input, .aicr-status-filters input', this.applyFilters.bind(this));

            // Item actions
            $(document).on('click', '.aicr-preview-item', this.previewItem.bind(this));
            $(document).on('click', '.aicr-rewrite-item', this.openRewriteModal.bind(this));

            // Bulk actions
            $(document).on('click', '#aicr-apply-bulk', this.applyBulkAction.bind(this));
            $(document).on('change', '#aicr-select-all', this.toggleSelectAll.bind(this));

            // Preview modal
            $(document).on('click', '#aicr-preview-modal .aicr-modal-close, #aicr-close-preview', this.closePreviewModal.bind(this));

            // Rewrite modal
            $(document).on('click', '#aicr-rewrite-modal .aicr-modal-close, #aicr-rewrite-cancel', this.closeRewriteModal.bind(this));
            $(document).on('click', '#aicr-rewrite-start', this.rewriteItem.bind(this));

            // Preview modal rewrite button
            $(document).on('click', '#aicr-preview-rewrite', this.openRewriteFromPreview.bind(this));

            // Load more
            $(document).on('click', '#aicr-load-more', this.loadMoreItems.bind(this));
        },

        /**
         * Open Feed Modal
         */
        openFeedModal: function(e) {
            e.preventDefault();
            this.resetFeedForm();
            $('#aicr-feed-modal-title').text(aicr_ajax.strings.add_feed || '새 피드 추가');
            $('#aicr-feed-modal').show();
        },

        /**
         * Close Feed Modal
         */
        closeFeedModal: function() {
            $('#aicr-feed-modal').hide();
        },

        /**
         * Reset Feed Form
         */
        resetFeedForm: function() {
            $('#aicr-feed-form')[0].reset();
            $('#feed_id').val('');
            $('#aicr-feed-validation-result').hide().empty();
            $('#auto_publish_group').hide();
        },

        /**
         * Validate Feed URL
         */
        validateFeed: function() {
            const url = $('#feed_url').val().trim();
            const $result = $('#aicr-feed-validation-result');
            const $btn = $('#aicr-validate-feed');

            if (!url) {
                $result.removeClass('success').addClass('error').text('URL을 입력해주세요.').show();
                return;
            }

            $btn.prop('disabled', true).text('검증 중...');

            $.post(aicr_ajax.ajax_url, {
                action: 'aicr_validate_feed',
                nonce: aicr_ajax.nonce,
                url: url
            }).done(function(response) {
                if (response.success) {
                    const data = response.data;
                    $result.removeClass('error').addClass('success')
                        .html(`<strong>✓ 유효한 피드</strong><br>제목: ${data.title || '없음'}<br>아이템 수: ${data.item_count}개`)
                        .show();

                    // Auto-fill name if empty
                    if (!$('#feed_name').val() && data.title) {
                        $('#feed_name').val(data.title);
                    }
                } else {
                    $result.removeClass('success').addClass('error')
                        .text('오류: ' + response.data.message).show();
                }
            }).fail(function() {
                $result.removeClass('success').addClass('error')
                    .text('피드 검증 중 오류가 발생했습니다.').show();
            }).always(function() {
                $btn.prop('disabled', false).text('검증');
            });
        },

        /**
         * Save Feed
         */
        saveFeed: function() {
            const feedId = $('#feed_id').val();
            const action = feedId ? 'aicr_update_feed' : 'aicr_add_feed';

            const data = {
                action: action,
                nonce: aicr_ajax.nonce,
                feed_id: feedId,
                feed_url: $('#feed_url').val(),
                name: $('#feed_name').val(),
                fetch_interval: $('#fetch_interval').val(),
                default_category: $('#default_category').val(),
                default_template_id: $('#default_template_id').val(),
                default_language: $('#default_language').val(),
                auto_rewrite: $('#auto_rewrite').is(':checked') ? 1 : 0,
                auto_publish: $('#auto_publish').is(':checked') ? 1 : 0
            };

            const $btn = $('#aicr-feed-save');
            $btn.prop('disabled', true).text('저장 중...');

            $.post(aicr_ajax.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert('오류: ' + response.data.message);
                    }
                })
                .fail(function() {
                    alert('피드 저장 중 오류가 발생했습니다.');
                })
                .always(function() {
                    $btn.prop('disabled', false).text('저장');
                });
        },

        /**
         * Edit Feed
         */
        editFeed: function(e) {
            e.preventDefault();
            const $card = $(e.target).closest('.aicr-feed-card');
            const feedId = $card.data('feed-id');

            $.post(aicr_ajax.ajax_url, {
                action: 'aicr_get_feed',
                nonce: aicr_ajax.nonce,
                feed_id: feedId
            }).done(function(response) {
                if (response.success) {
                    const feed = response.data.feed;
                    AICR_RSS.populateFeedForm(feed);
                    $('#aicr-feed-modal-title').text('피드 편집');
                    $('#aicr-feed-modal').show();
                } else {
                    alert('오류: ' + response.data.message);
                }
            });
        },

        /**
         * Populate Feed Form
         */
        populateFeedForm: function(feed) {
            $('#feed_id').val(feed.id);
            $('#feed_url').val(feed.feed_url);
            $('#feed_name').val(feed.name);
            $('#fetch_interval').val(feed.fetch_interval);
            $('#default_category').val(feed.default_category || '');
            $('#default_template_id').val(feed.default_template_id || '');
            $('#default_language').val(feed.default_language || 'ko');
            $('#auto_rewrite').prop('checked', feed.auto_rewrite);
            $('#auto_publish').prop('checked', feed.auto_publish);
            $('#auto_publish_group').toggle(feed.auto_rewrite);
        },

        /**
         * Refresh Feed
         */
        refreshFeed: function(e) {
            e.preventDefault();
            const $card = $(e.target).closest('.aicr-feed-card');
            const feedId = $card.data('feed-id');
            const $btn = $(e.target).closest('.button');

            $btn.prop('disabled', true);
            $btn.find('.dashicons').addClass('spin');

            $.post(aicr_ajax.ajax_url, {
                action: 'aicr_refresh_feed',
                nonce: aicr_ajax.nonce,
                feed_id: feedId
            }).done(function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('오류: ' + response.data.message);
                }
            }).fail(function() {
                alert('피드 새로고침 중 오류가 발생했습니다.');
            }).always(function() {
                $btn.prop('disabled', false);
                $btn.find('.dashicons').removeClass('spin');
            });
        },

        /**
         * Toggle Feed Status
         */
        toggleFeed: function(e) {
            e.preventDefault();
            const $card = $(e.target).closest('.aicr-feed-card');
            const feedId = $card.data('feed-id');

            $.post(aicr_ajax.ajax_url, {
                action: 'aicr_toggle_feed',
                nonce: aicr_ajax.nonce,
                feed_id: feedId
            }).done(function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('오류: ' + response.data.message);
                }
            });
        },

        /**
         * Delete Feed
         */
        deleteFeed: function(e) {
            e.preventDefault();

            if (!confirm('이 피드를 삭제하시겠습니까? 모든 관련 아이템도 함께 삭제됩니다.')) {
                return;
            }

            const $card = $(e.target).closest('.aicr-feed-card');
            const feedId = $card.data('feed-id');

            $.post(aicr_ajax.ajax_url, {
                action: 'aicr_delete_feed',
                nonce: aicr_ajax.nonce,
                feed_id: feedId
            }).done(function(response) {
                if (response.success) {
                    $card.fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    alert('오류: ' + response.data.message);
                }
            });
        },

        /**
         * Search Items
         */
        searchItems: function() {
            const search = $('#aicr-search-input').val();
            this.loadItems({ search: search, offset: 0 });
        },

        /**
         * Apply Filters
         */
        applyFilters: function() {
            const feedIds = [];
            const statuses = [];

            $('.aicr-feed-filter-list input:checked').each(function() {
                feedIds.push($(this).val());
            });

            $('.aicr-status-filters input:checked').each(function() {
                statuses.push($(this).val());
            });

            this.loadItems({
                feed_id: feedIds.length === 1 ? feedIds[0] : null,
                status: statuses,
                offset: 0
            });
        },

        /**
         * Load Items
         */
        loadItems: function(filters) {
            filters = $.extend({
                action: 'aicr_get_feed_items',
                nonce: aicr_ajax.nonce,
                limit: 20,
                offset: 0
            }, filters);

            const $list = $('.aicr-items-list');
            const isAppend = filters.offset > 0;

            if (!isAppend) {
                $list.html('<div class="aicr-loading">로딩 중...</div>');
            }

            $.post(aicr_ajax.ajax_url, filters)
                .done(function(response) {
                    if (response.success) {
                        if (isAppend) {
                            $('#aicr-load-more').remove();
                            AICR_RSS.appendItems(response.data.items);
                        } else {
                            AICR_RSS.renderItems(response.data.items);
                        }

                        if (response.data.has_more) {
                            AICR_RSS.showLoadMore(filters.offset + response.data.items.length);
                        }
                    }
                });
        },

        /**
         * Render Items
         */
        renderItems: function(items) {
            const $list = $('.aicr-items-list');

            if (items.length === 0) {
                $list.html('<div class="aicr-empty-state"><span class="dashicons dashicons-rss"></span><h3>아이템이 없습니다</h3></div>');
                return;
            }

            $list.empty();
            items.forEach(function(item) {
                $list.append(AICR_RSS.renderItemCard(item));
            });
        },

        /**
         * Append Items
         */
        appendItems: function(items) {
            const $list = $('.aicr-items-list');
            items.forEach(function(item) {
                $list.append(AICR_RSS.renderItemCard(item));
            });
        },

        /**
         * Render Item Card
         */
        renderItemCard: function(item) {
            const statusClass = item.status === 'unread' ? 'unread' : '';
            const thumbnail = item.thumbnail_url ? `<div class="aicr-item-thumbnail"><img src="${item.thumbnail_url}" alt=""></div>` : '';
            const excerpt = item.summary ? item.summary.substring(0, 150) + '...' : '';

            return `
                <div class="aicr-item-card ${statusClass}" data-item-id="${item.id}">
                    <div class="aicr-item-checkbox">
                        <input type="checkbox" name="item_ids[]" value="${item.id}">
                    </div>
                    ${thumbnail}
                    <div class="aicr-item-content">
                        <div class="aicr-item-header">
                            <h4 class="aicr-item-title">
                                <a href="${item.link}" target="_blank">${item.title}</a>
                            </h4>
                            <span class="aicr-status aicr-status-${item.status}">${item.status}</span>
                        </div>
                        <div class="aicr-item-excerpt">${excerpt}</div>
                        <div class="aicr-item-meta">
                            <span><span class="dashicons dashicons-calendar"></span>${item.pub_date}</span>
                            <span><span class="dashicons dashicons-admin-users"></span>${item.author || '익명'}</span>
                            ${item.feed_name ? `<span><span class="dashicons dashicons-rss"></span>${item.feed_name}</span>` : ''}
                        </div>
                    </div>
                    <div class="aicr-item-actions">
                        <button type="button" class="button aicr-preview-item" title="미리보기">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                        <button type="button" class="button button-primary aicr-rewrite-item" title="재작성">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                    </div>
                </div>
            `;
        },

        /**
         * Show Load More Button
         */
        showLoadMore: function(offset) {
            const $list = $('.aicr-items-list');
            $list.append(`<button type="button" id="aicr-load-more" class="button" data-offset="${offset}">더 보기</button>`);
        },

        /**
         * Load More Items
         */
        loadMoreItems: function(e) {
            const offset = $(e.target).data('offset');
            this.loadItems({ offset: offset });
        },

        /**
         * Toggle Select All
         */
        toggleSelectAll: function(e) {
            const checked = $(e.target).is(':checked');
            $('.aicr-item-checkbox input').prop('checked', checked);
        },

        /**
         * Current preview item ID
         */
        currentPreviewItemId: null,

        /**
         * Preview Item
         */
        previewItem: function(e) {
            e.preventDefault();
            const $card = $(e.target).closest('.aicr-item-card');
            const itemId = $card.data('item-id');

            // Store current item ID for rewrite from preview
            this.currentPreviewItemId = itemId;

            $.post(aicr_ajax.ajax_url, {
                action: 'aicr_get_feed_item',
                nonce: aicr_ajax.nonce,
                item_id: itemId
            }).done(function(response) {
                if (response.success) {
                    const item = response.data.item;
                    $('#aicr-preview-title').text(item.title);
                    $('#aicr-preview-meta').html(`
                        <span>${item.pub_date || ''}</span>
                        <span>${item.author || '익명'}</span>
                        <a href="${item.link}" target="_blank">원본 보기</a>
                    `);
                    $('#aicr-preview-content').html(item.content || item.summary || '');
                    $('#aicr-preview-original').attr('href', item.link);
                    $('#aicr-preview-modal').show();

                    // Mark as read
                    $card.removeClass('unread');
                    $card.find('.aicr-unread-dot').hide();
                } else {
                    alert('오류: ' + (response.data.message || '아이템을 불러올 수 없습니다.'));
                }
            }).fail(function() {
                alert('아이템을 불러오는 중 오류가 발생했습니다.');
            });
        },

        /**
         * Close Preview Modal
         */
        closePreviewModal: function() {
            $('#aicr-preview-modal').hide();
        },

        /**
         * Open Rewrite Modal
         */
        openRewriteModal: function(e) {
            e.preventDefault();
            const $card = $(e.target).closest('.aicr-item-card');
            const itemId = $card.data('item-id');

            // 제목 찾기 - 여러 방법 시도
            let title = $card.find('.aicr-item-title a').text().trim();
            if (!title) {
                title = $card.find('.aicr-item-title').text().trim();
            }

            // 피드명 찾기
            let feedName = $card.find('.aicr-item-feed').text().trim();
            if (!feedName) {
                feedName = $card.find('.aicr-item-meta span:first').text().trim();
            }

            console.log('Rewrite modal - Item ID:', itemId, 'Title:', title, 'Feed:', feedName);

            $('#rewrite_item_id').val(itemId);
            $('#rewrite_title').text(title || '제목 없음');
            $('#rewrite_source').text(feedName || '');
            $('#aicr-rewrite-modal').show();
        },

        /**
         * Close Rewrite Modal
         */
        closeRewriteModal: function() {
            $('#aicr-rewrite-modal').hide();
        },

        /**
         * Current task polling interval
         */
        pollingInterval: null,

        /**
         * Rewrite Item (비동기 방식)
         */
        rewriteItem: function() {
            const itemId = $('#rewrite_item_id').val();
            const $btn = $('#aicr-rewrite-start');
            const $modal = $('#aicr-rewrite-modal');

            if (!itemId) {
                alert('아이템 ID가 없습니다.');
                return;
            }

            // 진행 상황 UI 표시
            AICR_RSS.showProgressUI();

            $.post(aicr_ajax.ajax_url, {
                action: 'aicr_start_rewrite_task',
                nonce: aicr_ajax.nonce,
                item_id: itemId,
                template_id: $('#rewrite_template').val() || '',
                category: $('#rewrite_category').val() || '',
                language: $('#rewrite_language').val() || 'ko',
                post_status: $('input[name="post_status"]:checked').val() || 'draft'
            }).done(function(response) {
                if (response.success) {
                    // 폴링 시작
                    AICR_RSS.startPolling(response.data.task_id, itemId);
                } else {
                    AICR_RSS.hideProgressUI();
                    alert('오류: ' + response.data.message);
                }
            }).fail(function(xhr, status, error) {
                console.error('Rewrite start error:', status, error);
                AICR_RSS.hideProgressUI();
                alert('재작성 시작 중 오류가 발생했습니다.');
            });
        },

        /**
         * 진행 상황 UI 표시
         */
        showProgressUI: function() {
            const $modalContent = $('#aicr-rewrite-modal .aicr-modal-content');

            // 기존 내용 숨기기
            $modalContent.find('.aicr-rewrite-form-content').hide();

            // 진행 상황 UI가 없으면 생성
            if ($('#aicr-rewrite-progress').length === 0) {
                $modalContent.append(`
                    <div id="aicr-rewrite-progress" class="aicr-rewrite-progress">
                        <div class="aicr-progress-header">
                            <span class="dashicons dashicons-update spin"></span>
                            <span id="aicr-progress-title">재작성 진행 중...</span>
                        </div>
                        <div class="aicr-progress-bar-container">
                            <div class="aicr-progress-bar" id="aicr-progress-bar" style="width: 0%"></div>
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
                        <div class="aicr-progress-message" id="aicr-progress-message">작업을 시작하는 중...</div>
                        <div class="aicr-progress-actions" style="display: none;">
                            <button type="button" class="button" id="aicr-progress-cancel">취소</button>
                        </div>
                    </div>
                `);

                // 취소 버튼 이벤트
                $('#aicr-progress-cancel').on('click', function() {
                    AICR_RSS.stopPolling();
                    AICR_RSS.hideProgressUI();
                });
            } else {
                $('#aicr-rewrite-progress').show();
                // 상태 초기화
                $('#aicr-progress-bar').css('width', '0%');
                $('#aicr-progress-message').text('작업을 시작하는 중...');
                $('.aicr-progress-step').removeClass('active completed');
            }

            // 버튼 비활성화
            $('#aicr-rewrite-start').prop('disabled', true).hide();
            $('#aicr-rewrite-cancel').hide();
        },

        /**
         * 진행 상황 UI 숨기기
         */
        hideProgressUI: function() {
            $('#aicr-rewrite-progress').hide();

            const $modalContent = $('#aicr-rewrite-modal .aicr-modal-content');
            $modalContent.find('.aicr-rewrite-form-content').show();

            // 버튼 복원
            $('#aicr-rewrite-start').prop('disabled', false).show().text('재작성 시작');
            $('#aicr-rewrite-cancel').show();
        },

        /**
         * 폴링 시작
         */
        startPolling: function(taskId, itemId) {
            const pollInterval = 1500; // 1.5초마다 확인
            const maxAttempts = 200; // 최대 5분 (1.5초 * 200)
            let attempts = 0;

            AICR_RSS.pollingInterval = setInterval(function() {
                attempts++;

                if (attempts > maxAttempts) {
                    AICR_RSS.stopPolling();
                    AICR_RSS.hideProgressUI();
                    alert('작업 시간이 초과되었습니다. 잠시 후 다시 시도해주세요.');
                    return;
                }

                $.post(aicr_ajax.ajax_url, {
                    action: 'aicr_check_rewrite_status',
                    nonce: aicr_ajax.nonce,
                    task_id: taskId
                }).done(function(response) {
                    if (response.success) {
                        AICR_RSS.updateProgressUI(response.data);

                        // 완료 또는 실패 시 폴링 중지
                        if (response.data.status === 'completed') {
                            AICR_RSS.stopPolling();
                            AICR_RSS.handleRewriteComplete(response.data, itemId);
                        } else if (response.data.status === 'failed') {
                            AICR_RSS.stopPolling();
                            AICR_RSS.handleRewriteError(response.data);
                        }
                    } else {
                        console.error('Status check error:', response.data.message);
                    }
                }).fail(function(xhr, status, error) {
                    console.error('Polling error:', status, error);
                    // 네트워크 오류 시에도 계속 폴링
                });
            }, pollInterval);
        },

        /**
         * 폴링 중지
         */
        stopPolling: function() {
            if (AICR_RSS.pollingInterval) {
                clearInterval(AICR_RSS.pollingInterval);
                AICR_RSS.pollingInterval = null;
            }
        },

        /**
         * 진행 상황 UI 업데이트
         */
        updateProgressUI: function(data) {
            // 프로그레스 바 업데이트
            $('#aicr-progress-bar').css('width', data.progress + '%');

            // 메시지 업데이트
            $('#aicr-progress-message').text(data.message || '처리 중...');

            // 현재 단계 표시
            const stepOrder = ['extracting', 'rewriting', 'publishing', 'completed'];
            const currentStepIndex = stepOrder.indexOf(data.step);

            $('.aicr-progress-step').each(function() {
                const $step = $(this);
                const stepName = $step.data('step');
                const stepIndex = stepOrder.indexOf(stepName);

                if (stepIndex < currentStepIndex) {
                    $step.removeClass('active').addClass('completed');
                } else if (stepIndex === currentStepIndex) {
                    $step.addClass('active').removeClass('completed');
                } else {
                    $step.removeClass('active completed');
                }
            });
        },

        /**
         * 재작성 완료 처리
         */
        handleRewriteComplete: function(data, itemId) {
            // 성공 UI 표시
            $('#aicr-progress-title').html('<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> 재작성 완료!');
            $('.aicr-progress-header .dashicons.spin').removeClass('spin').addClass('dashicons-yes-alt').css('color', '#46b450');

            // 아이템 카드 상태 업데이트
            const $card = $(`.aicr-item-card[data-item-id="${itemId}"]`);
            $card.removeClass('unread');
            $card.find('.aicr-unread-dot').remove();

            // 2초 후 모달 닫기 및 결과 확인 제안
            setTimeout(function() {
                AICR_RSS.hideProgressUI();
                AICR_RSS.closeRewriteModal();

                if (data.result && data.result.edit_url) {
                    if (confirm('게시글이 생성되었습니다. 편집 페이지로 이동하시겠습니까?')) {
                        window.open(data.result.edit_url, '_blank');
                    }
                }

                // 페이지 새로고침 (상태 업데이트 반영)
                location.reload();
            }, 1500);
        },

        /**
         * 재작성 오류 처리
         */
        handleRewriteError: function(data) {
            // 오류 UI 표시
            $('#aicr-progress-title').html('<span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span> 재작성 실패');
            $('.aicr-progress-header .dashicons.spin').removeClass('spin').addClass('dashicons-dismiss').css('color', '#dc3232');
            $('#aicr-progress-message').text(data.error || '알 수 없는 오류가 발생했습니다.');

            // 3초 후 UI 복원
            setTimeout(function() {
                AICR_RSS.hideProgressUI();
            }, 3000);
        },

        /**
         * Open Rewrite Modal from Preview
         */
        openRewriteFromPreview: function(e) {
            e.preventDefault();
            const itemId = this.currentPreviewItemId;
            if (itemId) {
                this.closePreviewModal();
                const $card = $(`.aicr-item-card[data-item-id="${itemId}"]`);
                const title = $card.find('.aicr-item-title a').text();
                $('#rewrite_item_id').val(itemId);
                $('#rewrite_title').text(title);
                $('#rewrite_source').text($card.find('.aicr-item-feed').text() || '');
                $('#aicr-rewrite-modal').show();
            }
        },

        /**
         * Apply Bulk Action
         */
        applyBulkAction: function() {
            const action = $('#aicr-bulk-action').val();
            const itemIds = [];

            $('.aicr-item-checkbox input:checked').each(function() {
                itemIds.push($(this).val());
            });

            if (!action || itemIds.length === 0) {
                alert('작업과 아이템을 선택해주세요.');
                return;
            }

            if (action === 'rewrite') {
                if (!confirm(`${itemIds.length}개의 아이템을 재작성하시겠습니까?`)) {
                    return;
                }

                $.post(aicr_ajax.ajax_url, {
                    action: 'aicr_rewrite_items',
                    nonce: aicr_ajax.nonce,
                    item_ids: itemIds
                }).done(function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert('오류: ' + response.data.message);
                    }
                });
            } else if (action.startsWith('mark_')) {
                const status = action.replace('mark_', '');

                $.post(aicr_ajax.ajax_url, {
                    action: 'aicr_update_item_status',
                    nonce: aicr_ajax.nonce,
                    item_ids: itemIds,
                    status: status
                }).done(function(response) {
                    if (response.success) {
                        location.reload();
                    }
                });
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        AICR.init();
        AICR_RSS.init();
    });

    // Add CSS for spinning animation
    $('<style>.dashicons.spin { animation: aicr-spin 1s linear infinite; } @keyframes aicr-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }</style>').appendTo('head');

})(jQuery);
