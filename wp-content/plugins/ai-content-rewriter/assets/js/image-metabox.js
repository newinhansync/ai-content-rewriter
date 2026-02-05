/**
 * AI Image Generation Metabox JavaScript
 *
 * 점진적 이미지 생성 (Progressive Generation)
 * - 다중 이미지 생성 시 HTTP 타임아웃 방지
 * - 각 이미지를 개별 AJAX 요청으로 생성
 */
(function($) {
    'use strict';

    var ImageMetabox = {
        // 현재 진행 중인 세션
        currentSession: null,

        // 생성된 이미지 정보
        generatedImages: [],

        // 취소 플래그
        isCancelled: false,

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            $(document).on('click', '#aicr-generate-images', this.handleGenerate.bind(this));
            $(document).on('click', '#aicr-remove-images', this.handleRemove.bind(this));
            $(document).on('click', '#aicr-cancel-generation', this.handleCancel.bind(this));
        },

        /**
         * Handle generate button click
         * 점진적 생성 방식 사용
         */
        handleGenerate: function(e) {
            e.preventDefault();

            var self = this;
            var $button = $('#aicr-generate-images');
            var postId = $('#aicr-post-id').val();

            // Get post content from editor
            var content = this.getPostContent();

            if (!content || content.trim().length < 100) {
                alert(aicr_image.strings.no_content);
                return;
            }

            // Check if already generated
            if ($('.aicr-status-success').length > 0) {
                if (!confirm(aicr_image.strings.confirm_regenerate)) {
                    return;
                }
            }

            // Get options
            var options = {
                count: parseInt($('#aicr-image-count').val(), 10),
                style: $('#aicr-image-style').val(),
                ratio: $('#aicr-image-ratio').val(),
                instructions: $('#aicr-image-instructions').val()
            };

            // 초기화
            this.currentSession = null;
            this.generatedImages = [];
            this.isCancelled = false;

            // Disable button and show progress
            $button.prop('disabled', true).html('<span class="aicr-spinner"></span> 준비 중...');
            this.showProgress(0, '이미지 생성 준비 중...');
            this.showCancelButton(true);

            // 1단계: 점진적 생성 준비
            $.ajax({
                url: aicr_image.ajax_url,
                type: 'POST',
                data: {
                    action: 'aicr_prepare_progressive_images',
                    nonce: aicr_image.nonce,
                    post_id: postId,
                    count: options.count,
                    style: options.style,
                    ratio: options.ratio,
                    instructions: options.instructions
                },
                success: function(response) {
                    if (response.success) {
                        self.currentSession = response.data.session_key;
                        self.startProgressiveGeneration(
                            response.data.session_key,
                            response.data.total_count,
                            response.data.sections
                        );
                    } else {
                        self.showResult('error', response.data.message || aicr_image.strings.error);
                        self.resetButton();
                    }
                },
                error: function(xhr, status, error) {
                    self.showResult('error', aicr_image.strings.error + ': ' + error);
                    self.resetButton();
                }
            });
        },

        /**
         * 점진적 이미지 생성 시작
         */
        startProgressiveGeneration: function(sessionKey, totalCount, sections) {
            var self = this;
            this.generatedImages = [];

            // 순차적으로 이미지 생성
            this.generateNextImage(sessionKey, 0, totalCount, sections);
        },

        /**
         * 다음 이미지 생성 (재귀적)
         */
        generateNextImage: function(sessionKey, currentIndex, totalCount, sections) {
            var self = this;

            // 취소 확인
            if (this.isCancelled) {
                this.cancelGeneration(sessionKey);
                return;
            }

            // 모든 이미지 생성 완료
            if (currentIndex >= totalCount) {
                this.finalizeGeneration(sessionKey);
                return;
            }

            // 진행률 업데이트
            var percent = Math.floor((currentIndex / totalCount) * 100);
            var section = sections[currentIndex];
            var topicPreview = section.topic.substring(0, 30) + (section.topic.length > 30 ? '...' : '');

            // 첫 번째는 표지, 나머지는 콘텐츠
            var imageType = currentIndex === 0 ? '표지' : '콘텐츠 ' + currentIndex;
            this.showProgress(percent, imageType + ' 생성 중: ' + topicPreview);

            // 버튼 텍스트 업데이트
            var buttonLabel = currentIndex === 0 ? '표지 생성 중...' : (currentIndex) + '/' + (totalCount - 1) + ' 콘텐츠 생성 중...';
            $('#aicr-generate-images').html(
                '<span class="aicr-spinner"></span> ' + buttonLabel
            );

            // 단일 이미지 생성 요청
            $.ajax({
                url: aicr_image.ajax_url,
                type: 'POST',
                timeout: 120000, // 2분 타임아웃 (단일 이미지)
                data: {
                    action: 'aicr_generate_single_image',
                    nonce: aicr_image.nonce,
                    session_key: sessionKey,
                    index: currentIndex
                },
                success: function(response) {
                    if (response.success) {
                        self.generatedImages.push(response.data.image);
                        self.showImagePreview(response.data.image, currentIndex);

                        // 다음 이미지 생성
                        self.generateNextImage(sessionKey, currentIndex + 1, totalCount, sections);
                    } else {
                        // 개별 이미지 실패 시 계속 진행 (선택적)
                        console.warn('이미지 ' + (currentIndex + 1) + ' 생성 실패:', response.data.message);
                        self.showImageError(currentIndex, response.data.message);

                        // 다음 이미지 시도 (실패해도 계속)
                        self.generateNextImage(sessionKey, currentIndex + 1, totalCount, sections);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('이미지 ' + (currentIndex + 1) + ' 생성 오류:', error);
                    self.showImageError(currentIndex, error);

                    // 다음 이미지 시도 (실패해도 계속)
                    self.generateNextImage(sessionKey, currentIndex + 1, totalCount, sections);
                }
            });
        },

        /**
         * 이미지 미리보기 표시
         */
        showImagePreview: function(imageData, index) {
            var $preview = $('#aicr-image-previews');
            if ($preview.length === 0) {
                $('#aicr-image-result').after('<div id="aicr-image-previews" class="aicr-image-previews"></div>');
                $preview = $('#aicr-image-previews');
            }

            // 첫 번째는 표지, 나머지는 콘텐츠 이미지
            var label = index === 0 ? '표지 이미지' : '콘텐츠 ' + index;
            var itemClass = index === 0 ? 'aicr-image-preview-item aicr-cover-image' : 'aicr-image-preview-item';

            var html = '<div class="' + itemClass + '">' +
                '<img src="' + imageData.url + '" alt="' + imageData.alt + '">' +
                '<span class="aicr-image-preview-label">' + label + '</span>' +
                '</div>';
            $preview.append(html);
        },

        /**
         * 이미지 생성 실패 표시
         */
        showImageError: function(index, error) {
            var $preview = $('#aicr-image-previews');
            if ($preview.length === 0) {
                $('#aicr-image-result').after('<div id="aicr-image-previews" class="aicr-image-previews"></div>');
                $preview = $('#aicr-image-previews');
            }

            // 첫 번째는 표지, 나머지는 콘텐츠 이미지
            var label = index === 0 ? '표지 실패' : '콘텐츠 ' + index + ' 실패';

            var html = '<div class="aicr-image-preview-item aicr-image-preview-error">' +
                '<span class="dashicons dashicons-warning"></span>' +
                '<span class="aicr-image-preview-label">' + label + '</span>' +
                '</div>';
            $preview.append(html);
        },

        /**
         * 생성 완료 및 콘텐츠에 삽입
         */
        finalizeGeneration: function(sessionKey) {
            var self = this;

            if (this.generatedImages.length === 0) {
                this.showResult('error', '생성된 이미지가 없습니다.');
                this.resetButton();
                return;
            }

            this.showProgress(95, '콘텐츠에 이미지 삽입 중...');

            $.ajax({
                url: aicr_image.ajax_url,
                type: 'POST',
                data: {
                    action: 'aicr_finalize_progressive_images',
                    nonce: aicr_image.nonce,
                    session_key: sessionKey
                },
                success: function(response) {
                    if (response.success) {
                        self.showProgress(100, aicr_image.strings.success);
                        self.showResult('success', response.data.message);
                        self.updateStatus(response.data);
                        self.showCancelButton(false);

                        // 페이지 새로고침
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    } else {
                        self.showResult('error', response.data.message || aicr_image.strings.error);
                        self.resetButton();
                    }
                },
                error: function(xhr, status, error) {
                    self.showResult('error', '콘텐츠 삽입 중 오류: ' + error);
                    self.resetButton();
                }
            });
        },

        /**
         * 생성 취소 핸들러
         */
        handleCancel: function(e) {
            e.preventDefault();

            if (!confirm('이미지 생성을 취소하시겠습니까? 생성된 이미지는 삭제됩니다.')) {
                return;
            }

            this.isCancelled = true;
            $('#aicr-cancel-generation').prop('disabled', true).text('취소 중...');
        },

        /**
         * 생성 취소 처리
         */
        cancelGeneration: function(sessionKey) {
            var self = this;

            $.ajax({
                url: aicr_image.ajax_url,
                type: 'POST',
                data: {
                    action: 'aicr_cancel_progressive_images',
                    nonce: aicr_image.nonce,
                    session_key: sessionKey
                },
                success: function(response) {
                    self.showResult('info', '이미지 생성이 취소되었습니다.');
                    self.resetButton();
                    $('#aicr-image-previews').remove();
                },
                error: function() {
                    self.resetButton();
                }
            });
        },

        /**
         * 취소 버튼 표시/숨김
         */
        showCancelButton: function(show) {
            var $cancel = $('#aicr-cancel-generation');
            if (show) {
                if ($cancel.length === 0) {
                    $('#aicr-generate-images').after(
                        '<button type="button" id="aicr-cancel-generation" class="button">' +
                        '<span class="dashicons dashicons-no"></span> 취소' +
                        '</button>'
                    );
                }
                $('#aicr-cancel-generation').show().prop('disabled', false).html(
                    '<span class="dashicons dashicons-no"></span> 취소'
                );
            } else {
                $cancel.hide();
            }
        },

        /**
         * 버튼 초기화
         */
        resetButton: function() {
            var $button = $('#aicr-generate-images');
            $button.prop('disabled', false).html(
                '<span class="dashicons dashicons-format-image"></span> 이미지 생성'
            );
            this.hideProgress();
            this.showCancelButton(false);
            this.currentSession = null;
        },

        /**
         * Handle remove button click
         */
        handleRemove: function(e) {
            e.preventDefault();

            if (!confirm('생성된 AI 이미지를 모두 제거하시겠습니까?')) {
                return;
            }

            var self = this;
            var $button = $('#aicr-remove-images');
            var postId = $('#aicr-post-id').val();

            $button.prop('disabled', true).text('제거 중...');

            $.ajax({
                url: aicr_image.ajax_url,
                type: 'POST',
                data: {
                    action: 'aicr_remove_images',
                    nonce: aicr_image.nonce,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        self.showResult('success', '이미지가 제거되었습니다.');
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        self.showResult('error', response.data || aicr_image.strings.error);
                    }
                },
                error: function() {
                    self.showResult('error', aicr_image.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text('생성된 이미지 제거');
                }
            });
        },

        /**
         * Get post content from editor
         */
        getPostContent: function() {
            var content = '';

            // Try Gutenberg editor first
            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                var editorContent = wp.data.select('core/editor').getEditedPostContent();
                if (editorContent) {
                    content = editorContent;
                }
            }

            // Fallback to Classic Editor
            if (!content) {
                if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
                    content = tinymce.activeEditor.getContent();
                } else {
                    content = $('#content').val() || '';
                }
            }

            return content;
        },

        /**
         * Show progress bar
         */
        showProgress: function(percent, text) {
            var $progress = $('#aicr-image-progress');
            $progress.show();
            $progress.find('.aicr-progress-fill').css('width', percent + '%');
            $progress.find('.aicr-progress-text').text(text);
        },

        /**
         * Hide progress bar
         */
        hideProgress: function() {
            $('#aicr-image-progress').hide();
        },

        /**
         * Show result message
         */
        showResult: function(type, message) {
            var $result = $('#aicr-image-result');
            $result.removeClass('success error info').addClass(type);
            $result.html('<p>' + message + '</p>').show();
        },

        /**
         * Update status display
         */
        updateStatus: function(data) {
            var $status = $('.aicr-status');
            $status.removeClass('aicr-status-pending aicr-status-error').addClass('aicr-status-success');
            $status.html('<span class="dashicons dashicons-yes-alt"></span> ' + data.count + '개 이미지 생성됨');
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        ImageMetabox.init();
    });

})(jQuery);
