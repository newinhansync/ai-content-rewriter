<?php
/**
 * Feed Parser
 *
 * RSS/Atom 피드 파싱 클래스
 *
 * @package AIContentRewriter\RSS
 */

namespace AIContentRewriter\RSS;

use SimplePie;
use SimplePie_Item;
use WP_Error;

/**
 * RSS/Atom 피드 파서 클래스
 */
class FeedParser {
    /**
     * 파싱 에러 메시지
     */
    private ?string $last_error = null;

    /**
     * SimplePie 인스턴스
     */
    private ?SimplePie $simplepie = null;

    /**
     * URL에서 피드 파싱
     *
     * @param string $url 피드 URL
     * @return array|WP_Error 파싱된 아이템 배열 또는 에러
     */
    public function parse(string $url): array|WP_Error {
        $this->last_error = null;

        // WordPress의 fetch_feed 사용
        $feed = fetch_feed($url);

        if (is_wp_error($feed)) {
            $this->last_error = $feed->get_error_message();
            return $feed;
        }

        $this->simplepie = $feed;

        return $this->extract_items($feed);
    }

    /**
     * 피드 URL 검증
     *
     * @param string $url 피드 URL
     * @return array 검증 결과
     */
    public function validate(string $url): array {
        $result = [
            'valid' => false,
            'type' => null,
            'title' => null,
            'description' => null,
            'site_url' => null,
            'item_count' => 0,
            'error' => null,
        ];

        // URL 형식 검증
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $result['error'] = __('유효하지 않은 URL 형식입니다.', 'ai-content-rewriter');
            return $result;
        }

        // 피드 가져오기
        $feed = fetch_feed($url);

        if (is_wp_error($feed)) {
            $result['error'] = $feed->get_error_message();
            return $result;
        }

        // 피드 정보 추출
        $result['valid'] = true;
        $result['type'] = $this->detect_feed_type($feed);
        $result['title'] = $feed->get_title();
        $result['description'] = $feed->get_description();
        $result['site_url'] = $feed->get_link();
        $result['item_count'] = $feed->get_item_quantity();

        return $result;
    }

    /**
     * SimplePie에서 아이템 추출
     *
     * @param SimplePie $feed SimplePie 인스턴스
     * @return array FeedItem 배열
     */
    private function extract_items(SimplePie $feed): array {
        $items = [];
        $max_items = (int) get_option('aicr_rss_max_items_per_feed', 50);

        foreach ($feed->get_items(0, $max_items) as $item) {
            $items[] = $this->convert_to_feed_item($item);
        }

        return $items;
    }

    /**
     * SimplePie_Item을 FeedItem으로 변환
     *
     * @param SimplePie_Item $item SimplePie 아이템
     * @return FeedItem
     */
    private function convert_to_feed_item(SimplePie_Item $item): FeedItem {
        $data = [
            'guid' => $this->get_item_guid($item),
            'title' => $this->sanitize_text($item->get_title()),
            'link' => $item->get_link(),
            'content' => $this->get_item_content($item),
            'summary' => $this->get_item_summary($item),
            'author' => $this->get_item_author($item),
            'pub_date' => $item->get_date('Y-m-d H:i:s'),
            'categories' => $this->get_item_categories($item),
            'enclosures' => $this->get_item_enclosures($item),
            'thumbnail_url' => $this->get_item_thumbnail($item),
            'status' => FeedItem::STATUS_UNREAD,
            'fetched_at' => current_time('mysql'),
        ];

        return new FeedItem($data);
    }

    /**
     * 아이템 GUID 가져오기
     */
    private function get_item_guid(SimplePie_Item $item): string {
        $guid = $item->get_id();

        if (empty($guid)) {
            // GUID가 없으면 링크 또는 제목의 해시 사용
            $guid = $item->get_link() ?: md5($item->get_title() . $item->get_date());
        }

        return $guid;
    }

    /**
     * 아이템 콘텐츠 가져오기
     */
    private function get_item_content(SimplePie_Item $item): string {
        $content = $item->get_content();

        if (empty($content)) {
            $content = $item->get_description();
        }

        return $this->sanitize_content($content);
    }

    /**
     * 아이템 요약 가져오기
     */
    private function get_item_summary(SimplePie_Item $item): string {
        $summary = $item->get_description();

        if (empty($summary)) {
            $content = $item->get_content();
            if (!empty($content)) {
                // 콘텐츠에서 요약 생성
                $summary = wp_trim_words(wp_strip_all_tags($content), 55, '...');
            }
        }

        return $this->sanitize_text($summary);
    }

    /**
     * 아이템 작성자 가져오기
     */
    private function get_item_author(SimplePie_Item $item): string {
        $author = $item->get_author();

        if ($author) {
            return $this->sanitize_text($author->get_name() ?: $author->get_email());
        }

        return '';
    }

    /**
     * 아이템 카테고리 가져오기
     */
    private function get_item_categories(SimplePie_Item $item): array {
        $categories = [];
        $cats = $item->get_categories();

        if ($cats) {
            foreach ($cats as $cat) {
                $label = $cat->get_label();
                if (!empty($label)) {
                    $categories[] = $this->sanitize_text($label);
                }
            }
        }

        return $categories;
    }

    /**
     * 아이템 첨부파일 가져오기
     */
    private function get_item_enclosures(SimplePie_Item $item): array {
        $enclosures = [];
        $encs = $item->get_enclosures();

        if ($encs) {
            foreach ($encs as $enc) {
                $enclosure = [
                    'url' => $enc->get_link(),
                    'type' => $enc->get_type(),
                    'length' => $enc->get_length(),
                ];

                if (!empty($enclosure['url'])) {
                    $enclosures[] = $enclosure;
                }
            }
        }

        return $enclosures;
    }

    /**
     * 아이템 썸네일 가져오기
     */
    private function get_item_thumbnail(SimplePie_Item $item): string {
        // 1. 미디어 썸네일 확인
        $thumbnail = $item->get_thumbnail();
        if (!empty($thumbnail['url'])) {
            return $thumbnail['url'];
        }

        // 2. 첨부파일에서 이미지 찾기
        $enclosures = $item->get_enclosures();
        if ($enclosures) {
            foreach ($enclosures as $enc) {
                $type = $enc->get_type();
                if ($type && strpos($type, 'image') !== false) {
                    return $enc->get_link();
                }
            }
        }

        // 3. 콘텐츠에서 첫 번째 이미지 추출
        $content = $item->get_content();
        if (!empty($content)) {
            return $this->extract_first_image($content);
        }

        return '';
    }

    /**
     * HTML에서 첫 번째 이미지 URL 추출
     */
    private function extract_first_image(string $html): string {
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $html, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * 피드 타입 감지
     */
    private function detect_feed_type(SimplePie $feed): string {
        $type = $feed->get_type();

        if ($type & SIMPLEPIE_TYPE_RSS_ALL) {
            if ($type & SIMPLEPIE_TYPE_RSS_20) {
                return Feed::TYPE_RSS2;
            } elseif ($type & SIMPLEPIE_TYPE_RSS_10) {
                return Feed::TYPE_RSS1;
            }
            return Feed::TYPE_RSS2;
        }

        if ($type & SIMPLEPIE_TYPE_ATOM_ALL) {
            return Feed::TYPE_ATOM;
        }

        return Feed::TYPE_RSS2;
    }

    /**
     * 텍스트 정제
     */
    private function sanitize_text(?string $text): string {
        if (empty($text)) {
            return '';
        }

        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = wp_strip_all_tags($text);
        $text = trim($text);

        return $text;
    }

    /**
     * HTML 콘텐츠 정제
     */
    private function sanitize_content(?string $content): string {
        if (empty($content)) {
            return '';
        }

        // 허용할 HTML 태그
        $allowed_tags = [
            'p', 'br', 'strong', 'em', 'b', 'i', 'u',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'ul', 'ol', 'li',
            'blockquote', 'pre', 'code',
            'a', 'img',
            'table', 'thead', 'tbody', 'tr', 'th', 'td',
            'figure', 'figcaption',
        ];

        $allowed_html = [];
        foreach ($allowed_tags as $tag) {
            $allowed_html[$tag] = [
                'href' => [],
                'src' => [],
                'alt' => [],
                'title' => [],
                'class' => [],
            ];
        }

        return wp_kses($content, $allowed_html);
    }

    /**
     * 피드 정보 가져오기
     */
    public function get_feed_info(): array {
        if (!$this->simplepie) {
            return [];
        }

        return [
            'title' => $this->simplepie->get_title(),
            'description' => $this->simplepie->get_description(),
            'link' => $this->simplepie->get_link(),
            'language' => $this->simplepie->get_language(),
            'copyright' => $this->simplepie->get_copyright(),
            'type' => $this->detect_feed_type($this->simplepie),
        ];
    }

    /**
     * 마지막 에러 가져오기
     */
    public function get_last_error(): ?string {
        return $this->last_error;
    }
}
