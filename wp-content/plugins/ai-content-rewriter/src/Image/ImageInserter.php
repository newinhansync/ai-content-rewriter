<?php
/**
 * Image Inserter
 *
 * @package AIContentRewriter\Image
 */

namespace AIContentRewriter\Image;

/**
 * 콘텐츠에 이미지 삽입 클래스
 *
 * 인포그래픽 이미지만 콘텐츠에 삽입
 * 표지 이미지는 Featured Image로만 사용되며, 이 클래스를 통해 삽입되지 않음
 */
class ImageInserter {
    /**
     * 생성된 인포그래픽 이미지를 콘텐츠에 삽입
     *
     * 주의: 표지 이미지는 이 메서드를 통해 삽입되지 않음
     * ImageGenerator.finalizeProgressiveGeneration()에서 인포그래픽만 전달함
     *
     * @param string $content HTML 콘텐츠
     * @param array $images 인포그래픽 이미지 배열 [['attachment_id' => int, 'alt' => string, 'section_index' => int], ...]
     * @param array $insertionPoints 삽입 위치 배열
     * @return string 이미지가 삽입된 콘텐츠
     */
    public function insert(string $content, array $images, array $insertionPoints): string {
        if (empty($images) || empty($insertionPoints)) {
            error_log("[AICR Inserter] No images or insertion points provided");
            return $content;
        }

        error_log("[AICR Inserter] Inserting " . count($images) . " infographic images");

        // H2 기반 삽입인지 단락 기반 삽입인지 확인
        $isH2Based = isset($insertionPoints[0]['before_h2_index']);

        if ($isH2Based) {
            error_log("[AICR Inserter] Using H2-based insertion");
            return $this->insertBeforeH2($content, $images, $insertionPoints);
        }

        error_log("[AICR Inserter] Using paragraph-based insertion");
        return $this->insertAfterParagraph($content, $images, $insertionPoints);
    }

    /**
     * H2 태그 앞에 이미지 삽입
     */
    private function insertBeforeH2(string $content, array $images, array $insertionPoints): string {
        // H2 태그 위치 찾기
        preg_match_all('/<h2[^>]*>/i', $content, $matches, PREG_OFFSET_CAPTURE);

        if (empty($matches[0])) {
            return $content;
        }

        $h2Positions = $matches[0];

        // 역순으로 처리 (앞에서부터 하면 인덱스가 밀림)
        $points = array_reverse($insertionPoints);

        foreach ($points as $point) {
            $h2Index = $point['before_h2_index'];
            $sectionIndex = $point['section_index'];

            if (!isset($h2Positions[$h2Index]) || !isset($images[$sectionIndex])) {
                continue;
            }

            $position = $h2Positions[$h2Index][1];
            $imageHtml = $this->createImageBlock($images[$sectionIndex]);

            // H2 태그 앞에 이미지 삽입
            $content = substr_replace($content, $imageHtml . "\n\n", $position, 0);
        }

        return $content;
    }

    /**
     * 단락 뒤에 이미지 삽입
     */
    private function insertAfterParagraph(string $content, array $images, array $insertionPoints): string {
        // 단락 종료 태그 위치 찾기
        preg_match_all('/<\/p>/i', $content, $matches, PREG_OFFSET_CAPTURE);

        if (empty($matches[0])) {
            // 단락이 없으면 콘텐츠 끝에 추가
            foreach ($images as $image) {
                $content .= "\n\n" . $this->createImageBlock($image);
            }
            return $content;
        }

        $paragraphEndPositions = $matches[0];

        // 역순으로 처리
        $points = array_reverse($insertionPoints);

        foreach ($points as $point) {
            $paragraphIndex = $point['after_paragraph'];
            $sectionIndex = $point['section_index'];

            if (!isset($paragraphEndPositions[$paragraphIndex]) || !isset($images[$sectionIndex])) {
                continue;
            }

            // </p> 태그 뒤의 위치
            $position = $paragraphEndPositions[$paragraphIndex][1] + 4; // strlen('</p>') = 4
            $imageHtml = $this->createImageBlock($images[$sectionIndex]);

            // 단락 뒤에 이미지 삽입
            $content = substr_replace($content, "\n\n" . $imageHtml, $position, 0);
        }

        return $content;
    }

    /**
     * Gutenberg 호환 이미지 블록 HTML 생성
     *
     * 인포그래픽 이미지용 블록 생성
     * 표지 이미지는 Featured Image로만 사용되므로 이 메서드에서 생성되지 않음
     */
    private function createImageBlock(array $image): string {
        $attachmentId = $image['attachment_id'] ?? 0;

        if (empty($attachmentId)) {
            return '';
        }

        $imageUrl = wp_get_attachment_url($attachmentId);
        if (!$imageUrl) {
            return '';
        }

        $alt = esc_attr($image['alt'] ?? '');
        $caption = $image['caption'] ?? '';
        $imageType = $image['type'] ?? 'infographic';

        // 이미지 타입에 따른 클래스
        $typeClass = $imageType === 'infographic' ? 'aicr-infographic' : 'aicr-generated-image';
        $className = "aicr-generated-image {$typeClass}";

        // Gutenberg 이미지 블록 포맷
        $captionHtml = '';
        if (!empty($caption)) {
            $captionHtml = '<figcaption class="wp-element-caption">' . esc_html($caption) . '</figcaption>';
        }

        error_log("[AICR Inserter] Creating image block: Attachment {$attachmentId}, Type: {$imageType}");

        return sprintf(
            '<!-- wp:image {"id":%d,"sizeSlug":"large","linkDestination":"none","className":"%s"} -->
<figure class="wp-block-image size-large %s"><img src="%s" alt="%s" class="wp-image-%d"/>%s</figure>
<!-- /wp:image -->',
            $attachmentId,
            $className,
            $className,
            esc_url($imageUrl),
            $alt,
            $attachmentId,
            $captionHtml
        );
    }

    /**
     * 클래식 에디터용 이미지 HTML 생성 (필요 시)
     */
    public function createClassicImageHtml(array $image): string {
        $attachmentId = $image['attachment_id'] ?? 0;

        if (empty($attachmentId)) {
            return '';
        }

        $imageUrl = wp_get_attachment_url($attachmentId);
        if (!$imageUrl) {
            return '';
        }

        $alt = esc_attr($image['alt'] ?? '');
        $caption = $image['caption'] ?? '';

        $html = sprintf(
            '<figure class="aicr-generated-image">
    <img src="%s" alt="%s" class="aicr-ai-image" />
    %s
</figure>',
            esc_url($imageUrl),
            $alt,
            $caption ? '<figcaption>' . esc_html($caption) . '</figcaption>' : ''
        );

        return $html;
    }

    /**
     * 콘텐츠에서 AICR 생성 이미지 제거
     */
    public function removeGeneratedImages(string $content): string {
        // Gutenberg 블록 제거
        $content = preg_replace(
            '/<!-- wp:image[^>]*"className":"[^"]*aicr-generated-image[^"]*"[^>]*-->.*?<!-- \/wp:image -->/s',
            '',
            $content
        );

        // 클래식 에디터 이미지 제거
        $content = preg_replace(
            '/<figure[^>]*class="[^"]*aicr-generated-image[^"]*"[^>]*>.*?<\/figure>/s',
            '',
            $content
        );

        // 연속된 빈 줄 정리
        $content = preg_replace('/\n{3,}/', "\n\n", $content);

        return trim($content);
    }

    /**
     * 콘텐츠에 이미지가 있는지 확인
     */
    public function hasImages(string $content): bool {
        return strpos($content, '<img') !== false;
    }

    /**
     * AICR 생성 이미지가 있는지 확인
     */
    public function hasGeneratedImages(string $content): bool {
        return strpos($content, 'aicr-generated-image') !== false;
    }

    /**
     * 콘텐츠 내 이미지 수 카운트
     */
    public function countImages(string $content): int {
        preg_match_all('/<img[^>]*>/i', $content, $matches);
        return count($matches[0]);
    }

    /**
     * AICR 생성 이미지 수 카운트
     */
    public function countGeneratedImages(string $content): int {
        preg_match_all('/aicr-generated-image/', $content, $matches);
        return count($matches[0]);
    }
}
