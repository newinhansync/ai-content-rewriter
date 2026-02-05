<?php
/**
 * Image Generator
 *
 * @package AIContentRewriter\Image
 */

namespace AIContentRewriter\Image;

use AIContentRewriter\AI\GeminiAdapter;
use AIContentRewriter\AI\GeminiImageAdapter;

/**
 * 이미지 생성 통합 매니저
 *
 * 완전히 분리된 세션으로 이미지 생성:
 * - 표지 이미지: Editorial Photo 스타일 (cover-image-prompt.md)
 * - 콘텐츠 이미지: Flat 2D 인포그래픽 스타일 (infographic-prompt.md)
 *
 * 각 이미지는 독립적인 API 세션에서 생성됨
 */
class ImageGenerator {
    /**
     * 프롬프트 매니저
     */
    private ImagePromptManager $promptManager;

    /**
     * 콘텐츠 섹션 분할기
     */
    private ContentSectionizer $sectionizer;

    /**
     * 이미지 삽입기
     */
    private ImageInserter $inserter;

    /**
     * 생성된 첨부파일 ID (롤백용)
     */
    private array $generatedAttachments = [];

    /**
     * 생성자
     *
     * 주의: GeminiImageAdapter는 각 이미지 생성 시 새로 인스턴스화하여
     * 완전히 분리된 API 세션을 사용합니다.
     */
    public function __construct() {
        $this->promptManager = ImagePromptManager::get_instance();
        $this->sectionizer = new ContentSectionizer();
        $this->inserter = new ImageInserter();
    }

    /**
     * 새로운 이미지 어댑터 인스턴스 생성
     * 각 이미지 생성 시 독립적인 세션 보장
     */
    private function createNewImageAdapter(): GeminiImageAdapter {
        return new GeminiImageAdapter();
    }

    /**
     * 게시글에 이미지 생성 및 삽입
     *
     * @param int $postId 게시글 ID
     * @param array $options 옵션 ['count', 'style', 'ratio', 'instructions']
     * @return array 생성된 이미지 정보 배열
     * @throws \Exception 에러 발생 시
     */
    public function generateForPost(int $postId, array $options = []): array {
        $this->generatedAttachments = [];

        $post = get_post($postId);
        if (!$post) {
            throw new \Exception(__('게시글을 찾을 수 없습니다.', 'ai-content-rewriter'));
        }

        // WordPress 포스트 락 확인 (관리자 환경에서만)
        if (function_exists('wp_check_post_lock')) {
            $lock = wp_check_post_lock($postId);
            if ($lock) {
                $user = get_userdata($lock);
                throw new \Exception(
                    sprintf(__('%s님이 이 게시글을 편집 중입니다. 나중에 다시 시도해주세요.', 'ai-content-rewriter'), $user->display_name)
                );
            }
        }

        // 처리 중 락 설정 (관리자 환경에서만)
        if (function_exists('wp_set_post_lock')) {
            wp_set_post_lock($postId);
        }

        try {
            $imageCount = $options['count'] ?? (int) get_option('aicr_image_default_count', 2);
            $style = $options['style'] ?? get_option('aicr_image_default_style', '일러스트레이션');
            $ratio = $options['ratio'] ?? get_option('aicr_image_default_ratio', '16:9');
            $instructions = $options['instructions'] ?? '';

            // 이미지 수 제한
            $imageCount = max(1, min(5, $imageCount));

            // 1. 콘텐츠를 섹션으로 분할
            $sections = $this->sectionizer->sectionize($post->post_content, $imageCount);
            $insertionPoints = $this->sectionizer->getInsertionPoints($post->post_content, $imageCount);

            // 실제 섹션 수에 맞게 조정
            $imageCount = min($imageCount, count($sections));

            // 2. 각 섹션에 대해 이미지 생성 (세션 분리)
            $generatedImages = [];

            for ($index = 0; $index < $imageCount; $index++) {
                $section = $sections[$index] ?? null;
                if (!$section) {
                    continue;
                }

                // 새로운 API 세션으로 어댑터 생성 (세션 분리)
                $imageAdapter = $this->createNewImageAdapter();

                // 프롬프트 빌드: 표지 vs 인포그래픽
                $isCover = $index === 0;

                if ($isCover) {
                    // 표지 이미지: Editorial Photo 스타일
                    $prompt = $this->promptManager->build_cover_prompt(
                        $section['topic'],
                        $section['content'] ?? '',
                        $instructions
                    );
                } else {
                    // 콘텐츠 이미지: 플랫 2D 인포그래픽 스타일
                    // 섹션 콘텐츠와 키워드를 전달하여 한글 텍스트 정확도 향상
                    $prompt = $this->promptManager->build_content_prompt(
                        $section['topic'],
                        $style,
                        $instructions,
                        $section['content'] ?? '', // 실제 섹션 콘텐츠
                        $section['keywords'] ?? [] // 핵심 키워드
                    );
                }

                // 이미지 생성 (독립 세션)
                $response = $imageAdapter->generate($prompt, [
                    'aspect_ratio' => $ratio,
                ]);

                if (!$response->isSuccess()) {
                    $this->logFailure($postId, $response->getErrorMessage(), $index);
                    continue;
                }

                // 미디어 라이브러리에 저장
                $attachmentId = $this->saveToMediaLibrary(
                    $response->getBase64(),
                    $postId,
                    $section['topic']
                );

                $this->generatedAttachments[] = $attachmentId;

                $imageData = [
                    'attachment_id' => $attachmentId,
                    'alt' => $section['topic'],
                    'caption' => '',
                    'section_index' => $index,
                ];

                $generatedImages[] = $imageData;

                // 이미지 히스토리 기록
                $this->logImageHistory($postId, $attachmentId, $prompt, $style, $ratio, $index, $response->getResponseTime());

                // 3. 첫 번째 이미지를 Featured Image로 설정
                if ($index === 0 && get_option('aicr_image_auto_featured', true)) {
                    $this->setFeaturedImage($postId, $attachmentId);
                }
            }

            if (empty($generatedImages)) {
                throw new \Exception(__('이미지를 생성하지 못했습니다.', 'ai-content-rewriter'));
            }

            // 4. 콘텐츠에 이미지 삽입
            $newContent = $this->inserter->insert(
                $post->post_content,
                $generatedImages,
                $insertionPoints
            );

            // 5. 게시글 업데이트
            $result = wp_update_post([
                'ID' => $postId,
                'post_content' => $newContent,
            ]);

            if (is_wp_error($result)) {
                throw new \Exception($result->get_error_message());
            }

            // 6. 메타데이터 저장
            update_post_meta($postId, 'aicr_images_generated', true);
            update_post_meta($postId, 'aicr_images_generated_at', current_time('mysql'));
            update_post_meta($postId, 'aicr_images_count', count($generatedImages));

            return $generatedImages;

        } catch (\Exception $e) {
            // 롤백: 생성된 첨부파일 삭제
            foreach ($this->generatedAttachments as $attachmentId) {
                wp_delete_attachment($attachmentId, true);
            }
            throw $e;

        } finally {
            // 락 해제
            delete_post_meta($postId, '_edit_lock');
        }
    }

    /**
     * Base64 이미지를 미디어 라이브러리에 저장
     */
    private function saveToMediaLibrary(string $base64, int $postId, string $title): int {
        $uploadDir = wp_upload_dir();
        $filename = 'aicr-image-' . $postId . '-' . time() . '-' . wp_rand(100, 999) . '.png';
        $filePath = $uploadDir['path'] . '/' . $filename;

        // Base64 디코딩 및 파일 저장
        $imageData = base64_decode($base64);
        if ($imageData === false) {
            throw new \Exception(__('이미지 데이터 디코딩 실패', 'ai-content-rewriter'));
        }

        $result = file_put_contents($filePath, $imageData);
        if ($result === false) {
            throw new \Exception(__('이미지 파일 저장 실패', 'ai-content-rewriter'));
        }

        // 메모리 해제
        unset($imageData);

        // 미디어 라이브러리에 등록
        $fileType = wp_check_filetype($filename, null);

        $attachment = [
            'post_mime_type' => $fileType['type'] ?? 'image/png',
            'post_title' => sanitize_file_name($title),
            'post_content' => '',
            'post_status' => 'inherit',
        ];

        $attachmentId = wp_insert_attachment($attachment, $filePath, $postId);

        if (is_wp_error($attachmentId)) {
            @unlink($filePath);
            throw new \Exception($attachmentId->get_error_message());
        }

        // 메타데이터 생성 (이미지 리사이징 포함)
        // 대용량 이미지 처리 시 시간이 오래 걸릴 수 있으므로 타임아웃 연장
        set_time_limit(300);
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($attachmentId, $filePath);
        wp_update_attachment_metadata($attachmentId, $metadata);

        // Alt 텍스트 설정
        update_post_meta($attachmentId, '_wp_attachment_image_alt', $title);

        return $attachmentId;
    }

    /**
     * Featured Image (대표 이미지) 설정
     */
    private function setFeaturedImage(int $postId, int $attachmentId): void {
        set_post_thumbnail($postId, $attachmentId);
    }

    /**
     * 이미지 생성 이력 기록
     */
    private function logImageHistory(
        int $postId,
        int $attachmentId,
        string $prompt,
        string $style,
        string $ratio,
        int $sectionIndex,
        float $generationTime
    ): void {
        global $wpdb;

        $tableName = $wpdb->prefix . 'aicr_image_history';

        $wpdb->insert($tableName, [
            'post_id' => $postId,
            'attachment_id' => $attachmentId,
            'prompt' => $prompt,
            'style' => $style,
            'aspect_ratio' => $ratio,
            'section_index' => $sectionIndex,
            'generation_time' => $generationTime,
            'status' => 'success',
            'created_at' => current_time('mysql'),
        ]);
    }

    /**
     * 실패 기록
     */
    private function logFailure(int $postId, string $error, int $sectionIndex): void {
        global $wpdb;

        $tableName = $wpdb->prefix . 'aicr_image_history';

        $wpdb->insert($tableName, [
            'post_id' => $postId,
            'attachment_id' => null,
            'prompt' => '',
            'section_index' => $sectionIndex,
            'status' => 'failed',
            'error_message' => $error,
            'created_at' => current_time('mysql'),
        ]);
    }

    /**
     * 게시글의 AI 생성 이미지 삭제
     */
    public function removeImagesFromPost(int $postId): bool {
        $post = get_post($postId);
        if (!$post) {
            return false;
        }

        // 콘텐츠에서 이미지 제거
        $newContent = $this->inserter->removeGeneratedImages($post->post_content);

        // 게시글 업데이트
        wp_update_post([
            'ID' => $postId,
            'post_content' => $newContent,
        ]);

        // 메타데이터 제거
        delete_post_meta($postId, 'aicr_images_generated');
        delete_post_meta($postId, 'aicr_images_generated_at');
        delete_post_meta($postId, 'aicr_images_count');

        return true;
    }

    /**
     * 게시글에 이미지가 필요한지 확인
     */
    public function needsImages(int $postId): bool {
        // 이미 이미지 생성됨
        if (get_post_meta($postId, 'aicr_images_generated', true)) {
            return false;
        }

        // Featured Image 이미 있음
        if (has_post_thumbnail($postId) && get_option('aicr_image_skip_with_thumbnail', true)) {
            return false;
        }

        // 콘텐츠에 이미지 태그 이미 있음
        $content = get_post_field('post_content', $postId);
        if ($this->inserter->hasImages($content) && get_option('aicr_image_skip_with_images', true)) {
            return false;
        }

        return true;
    }

    /**
     * 이미지 생성 상태 조회
     */
    public function getGenerationStatus(int $postId): array {
        $generated = get_post_meta($postId, 'aicr_images_generated', true);
        $generatedAt = get_post_meta($postId, 'aicr_images_generated_at', true);
        $count = get_post_meta($postId, 'aicr_images_count', true);

        return [
            'generated' => (bool) $generated,
            'generated_at' => $generatedAt ?: null,
            'count' => (int) $count,
            'has_featured' => has_post_thumbnail($postId),
        ];
    }

    // =========================================================================
    // 점진적 이미지 생성 (Progressive Generation) - 세션 분리 방식
    // =========================================================================

    /**
     * 점진적 생성 준비
     *
     * 이미지 생성 전략:
     * - 첫 번째 이미지 (index 0): 표지 이미지 (Editorial Photo 스타일)
     *   → 블로그 타이틀 + 전체 내용 기반
     *   → Featured Image로 설정
     *
     * - 나머지 이미지 (index 1~N-1): 인포그래픽 이미지
     *   → 블로그 콘텐츠를 (N-1)개로 분할
     *   → 각 분할된 섹션의 중간에 삽입
     *
     * @param int $postId 게시글 ID
     * @param array $options 옵션 ['count', 'style', 'ratio', 'instructions']
     * @return array 준비 정보 ['sections', 'insertion_points', 'total_count', 'session_key']
     * @throws \Exception 에러 발생 시
     */
    public function prepareProgressiveGeneration(int $postId, array $options = []): array {
        $post = get_post($postId);
        if (!$post) {
            throw new \Exception(__('게시글을 찾을 수 없습니다.', 'ai-content-rewriter'));
        }

        $imageCount = $options['count'] ?? (int) get_option('aicr_image_default_count', 2);
        $style = $options['style'] ?? get_option('aicr_image_default_style', '인포그래픽');
        $ratio = $options['ratio'] ?? get_option('aicr_image_default_ratio', '16:9');
        $instructions = $options['instructions'] ?? '';

        // 이미지 수 제한 (최소 2개: 표지 1개 + 콘텐츠 1개 이상)
        $imageCount = max(2, min(5, $imageCount));

        // 인포그래픽 이미지 수 (표지 제외)
        $infographicCount = $imageCount - 1;

        // =====================================================================
        // 섹션 분할: 콘텐츠를 인포그래픽 수(N-1)개로 분할
        // =====================================================================
        $contentSections = $this->sectionizer->sectionize($post->post_content, $infographicCount);
        $insertionPoints = $this->sectionizer->getInsertionPoints($post->post_content, $infographicCount);

        // =====================================================================
        // 섹션 구성: 표지(1개) + 인포그래픽(N-1개)
        // =====================================================================

        // 표지 섹션: 블로그 전체 내용 기반 (index 0)
        $coverSection = [
            'type' => 'cover',
            'topic' => $post->post_title,
            'content' => wp_trim_words(wp_strip_all_tags($post->post_content), 100, '...'),
            'is_cover' => true,
        ];

        // 인포그래픽 섹션: 분할된 콘텐츠 기반 (index 1~N-1)
        $infographicSections = array_map(function ($section) {
            $section['type'] = 'infographic';
            $section['is_cover'] = false;
            return $section;
        }, $contentSections);

        // 전체 섹션: 표지 + 인포그래픽들
        $sections = array_merge([$coverSection], $infographicSections);

        // 실제 섹션 수에 맞게 조정
        $actualCount = min($imageCount, count($sections));

        // 디버그 로깅
        error_log("[AICR Prepare] Total images: {$actualCount}");
        error_log("[AICR Prepare] Cover: 1개, Infographic: " . ($actualCount - 1) . "개");
        error_log("[AICR Prepare] Content sections: " . count($contentSections));

        // 세션 키 생성 (중복 방지)
        $sessionKey = 'aicr_progressive_' . $postId . '_' . time();

        // 세션 데이터 저장 (트랜지언트 사용, 30분 유효)
        $sessionData = [
            'post_id' => $postId,
            'post_title' => $post->post_title,
            'sections' => $sections,
            'insertion_points' => $insertionPoints,
            'infographic_count' => $infographicCount,
            'options' => [
                'style' => $style,
                'ratio' => $ratio,
                'instructions' => $instructions,
            ],
            'generated_images' => [],
            'created_at' => current_time('mysql'),
        ];

        set_transient($sessionKey, $sessionData, 1800);

        return [
            'session_key' => $sessionKey,
            'total_count' => $actualCount,
            'sections' => array_map(function ($section, $index) {
                return [
                    'index' => $index,
                    'type' => $section['type'] ?? ($index === 0 ? 'cover' : 'infographic'),
                    'topic' => $section['topic'],
                ];
            }, array_slice($sections, 0, $actualCount), array_keys(array_slice($sections, 0, $actualCount))),
        ];
    }

    /**
     * 단일 이미지 생성 (점진적 생성의 각 단계)
     *
     * 핵심: 각 이미지 생성 시 새로운 API 세션 사용 (GeminiImageAdapter 인스턴스 분리)
     *
     * @param string $sessionKey 세션 키
     * @param int $index 이미지 인덱스 (0-based)
     * @return array 생성된 이미지 정보
     * @throws \Exception 에러 발생 시
     */
    public function generateSingleImage(string $sessionKey, int $index): array {
        // 세션 데이터 조회
        $sessionData = get_transient($sessionKey);
        if (!$sessionData) {
            throw new \Exception(__('세션이 만료되었습니다. 처음부터 다시 시도해주세요.', 'ai-content-rewriter'));
        }

        $postId = $sessionData['post_id'];
        $postTitle = $sessionData['post_title'] ?? '';
        $sections = $sessionData['sections'];
        $options = $sessionData['options'];

        // 인덱스 유효성 검증
        if (!isset($sections[$index])) {
            throw new \Exception(__('유효하지 않은 이미지 인덱스입니다.', 'ai-content-rewriter'));
        }

        $section = $sections[$index];
        $style = $options['style'];
        $ratio = $options['ratio'];
        $instructions = $options['instructions'];

        // =====================================================================
        // 핵심: 새로운 API 세션으로 이미지 어댑터 생성 (세션 분리)
        // 각 이미지가 완전히 독립적인 API 요청으로 처리됨
        // =====================================================================
        $imageAdapter = $this->createNewImageAdapter();

        // =====================================================================
        // 프롬프트 분기: 표지 vs 인포그래픽
        // - index 0: 표지 이미지 (Editorial Photo)
        // - index > 0: 인포그래픽 이미지 (Flat 2D Vector)
        // =====================================================================
        $isCover = ($section['type'] ?? '') === 'cover' || $index === 0;
        $imageType = $isCover ? 'cover' : 'infographic';

        if ($isCover) {
            // =====================================================================
            // 표지 이미지: Editorial Photo 스타일
            // docs/cover-image-prompt.md 기반
            // =====================================================================
            $contentSummary = $section['content'] ?? '';
            $prompt = $this->promptManager->build_cover_prompt(
                $postTitle ?: $section['topic'],
                $contentSummary,
                $instructions
            );

            error_log("=================================================================");
            error_log("[AICR Image] COVER IMAGE - Editorial Photo Style");
            error_log("[AICR Image] Index: {$index}, Type: COVER");
            error_log("[AICR Image] Title: {$postTitle}");
            error_log("[AICR Image] Prompt (first 400 chars): " . substr($prompt, 0, 400));
            error_log("=================================================================");

        } else {
            // =====================================================================
            // 인포그래픽 이미지: Flat 2D Vector 스타일
            // docs/infographic-prompt.md 기반
            // 섹션 콘텐츠와 키워드를 전달하여 한글 텍스트 정확도 향상
            // =====================================================================
            $prompt = $this->promptManager->build_content_prompt(
                $section['topic'],
                $style,
                $instructions,
                $section['content'] ?? '', // 실제 섹션 콘텐츠
                $section['keywords'] ?? [] // 핵심 키워드
            );

            error_log("=================================================================");
            error_log("[AICR Image] INFOGRAPHIC IMAGE - TEXT-FREE Visual Style (Nano Banana Pro)");
            error_log("[AICR Image] Index: {$index}, Type: INFOGRAPHIC (NO TEXT)");
            error_log("[AICR Image] Original Topic (Korean): " . $section['topic']);
            error_log("[AICR Image] Keywords (for icon mapping): " . implode(', ', $section['keywords'] ?? []));
            error_log("[AICR Image] Prompt uses ICONS/SHAPES instead of text to avoid Korean rendering issues");
            error_log("[AICR Image] Prompt (first 500 chars): " . substr($prompt, 0, 500));
            error_log("=================================================================");
        }

        // =====================================================================
        // 이미지 생성 (독립 API 세션)
        // =====================================================================
        $response = $imageAdapter->generate($prompt, [
            'aspect_ratio' => $ratio,
        ]);

        if (!$response->isSuccess()) {
            $this->logFailure($postId, $response->getErrorMessage(), $index);
            throw new \Exception($response->getErrorMessage());
        }

        // 미디어 라이브러리에 저장
        $attachmentId = $this->saveToMediaLibrary(
            $response->getBase64(),
            $postId,
            $section['topic']
        );

        // 이미지 히스토리 기록
        $this->logImageHistory($postId, $attachmentId, $prompt, $style, $ratio, $index, $response->getResponseTime());

        // 첫 번째 이미지(표지)를 Featured Image로 설정
        if ($index === 0 && get_option('aicr_image_auto_featured', true)) {
            $this->setFeaturedImage($postId, $attachmentId);
            error_log("[AICR Image] Set as Featured Image: Attachment ID {$attachmentId}");
        }

        $imageData = [
            'attachment_id' => $attachmentId,
            'url' => wp_get_attachment_url($attachmentId),
            'alt' => $section['topic'],
            'caption' => '',
            'section_index' => $index,
            'type' => $imageType,
            'is_cover' => $isCover,
        ];

        // 세션 데이터 업데이트
        $sessionData['generated_images'][$index] = $imageData;
        set_transient($sessionKey, $sessionData, 1800);

        error_log("[AICR Image] Generated successfully: Index {$index}, Type {$imageType}, Attachment {$attachmentId}");

        return $imageData;
    }

    /**
     * 점진적 생성 완료 - 콘텐츠에 이미지 삽입
     *
     * 삽입 전략:
     * - 표지 이미지 (index 0): Featured Image로만 사용, 콘텐츠 내 삽입하지 않음
     * - 인포그래픽 이미지 (index 1~N-1): 각 분할된 섹션의 중간에 삽입
     *
     * @param string $sessionKey 세션 키
     * @return array 완료 정보 ['images', 'post_id']
     * @throws \Exception 에러 발생 시
     */
    public function finalizeProgressiveGeneration(string $sessionKey): array {
        // 세션 데이터 조회
        $sessionData = get_transient($sessionKey);
        if (!$sessionData) {
            throw new \Exception(__('세션이 만료되었습니다.', 'ai-content-rewriter'));
        }

        $postId = $sessionData['post_id'];
        $insertionPoints = $sessionData['insertion_points'];
        $generatedImages = $sessionData['generated_images'];

        if (empty($generatedImages)) {
            throw new \Exception(__('생성된 이미지가 없습니다.', 'ai-content-rewriter'));
        }

        // 인덱스 순서대로 정렬
        ksort($generatedImages);

        // =====================================================================
        // 이미지 분류: 표지 vs 인포그래픽
        // =====================================================================
        $coverImage = null;
        $infographicImages = [];

        foreach ($generatedImages as $index => $imageData) {
            if ($imageData['is_cover'] ?? ($index === 0)) {
                $coverImage = $imageData;
                error_log("[AICR Finalize] Cover image: Attachment {$imageData['attachment_id']}");
            } else {
                $infographicImages[] = $imageData;
                error_log("[AICR Finalize] Infographic image: Attachment {$imageData['attachment_id']}");
            }
        }

        error_log("[AICR Finalize] Total: " . count($generatedImages) . " images (1 cover + " . count($infographicImages) . " infographics)");

        // 게시글 가져오기
        $post = get_post($postId);
        if (!$post) {
            throw new \Exception(__('게시글을 찾을 수 없습니다.', 'ai-content-rewriter'));
        }

        // =====================================================================
        // 인포그래픽 이미지만 콘텐츠에 삽입
        // 표지 이미지는 Featured Image로만 사용 (이미 generateSingleImage에서 설정됨)
        // =====================================================================
        $newContent = $post->post_content;

        if (!empty($infographicImages)) {
            // 인포그래픽 이미지의 section_index를 0부터 재조정
            $reindexedImages = [];
            foreach ($infographicImages as $idx => $img) {
                $reindexedImage = $img;
                $reindexedImage['section_index'] = $idx;
                $reindexedImages[] = $reindexedImage;
            }

            $newContent = $this->inserter->insert(
                $post->post_content,
                $reindexedImages,
                $insertionPoints
            );

            error_log("[AICR Finalize] Inserted " . count($reindexedImages) . " infographic images into content");
        }

        // 게시글 업데이트
        $result = wp_update_post([
            'ID' => $postId,
            'post_content' => $newContent,
        ]);

        if (is_wp_error($result)) {
            throw new \Exception($result->get_error_message());
        }

        // 메타데이터 저장
        update_post_meta($postId, 'aicr_images_generated', true);
        update_post_meta($postId, 'aicr_images_generated_at', current_time('mysql'));
        update_post_meta($postId, 'aicr_images_count', count($generatedImages));
        update_post_meta($postId, 'aicr_cover_image_id', $coverImage['attachment_id'] ?? null);
        update_post_meta($postId, 'aicr_infographic_count', count($infographicImages));

        // 세션 데이터 삭제
        delete_transient($sessionKey);

        error_log("[AICR Finalize] Complete: Post {$postId} updated with " . count($generatedImages) . " images");

        return [
            'images' => array_values($generatedImages),
            'post_id' => $postId,
            'count' => count($generatedImages),
            'cover_image' => $coverImage,
            'infographic_images' => $infographicImages,
        ];
    }

    /**
     * 점진적 생성 취소/롤백
     *
     * @param string $sessionKey 세션 키
     * @return bool 성공 여부
     */
    public function cancelProgressiveGeneration(string $sessionKey): bool {
        $sessionData = get_transient($sessionKey);
        if (!$sessionData) {
            return true; // 이미 없음
        }

        // 생성된 첨부파일 삭제
        $generatedImages = $sessionData['generated_images'] ?? [];
        foreach ($generatedImages as $imageData) {
            if (isset($imageData['attachment_id'])) {
                wp_delete_attachment($imageData['attachment_id'], true);
            }
        }

        // 세션 데이터 삭제
        delete_transient($sessionKey);

        return true;
    }
}
