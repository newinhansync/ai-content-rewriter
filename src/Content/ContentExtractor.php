<?php
/**
 * Content Extractor
 *
 * @package AIContentRewriter\Content
 */

namespace AIContentRewriter\Content;

use AIContentRewriter\Security\UrlValidator;

/**
 * URL에서 콘텐츠를 추출하는 클래스
 */
class ContentExtractor {
    /**
     * URL에서 콘텐츠 추출
     *
     * @param string $url 대상 URL
     * @return ContentResult 추출 결과
     */
    public function extract_from_url(string $url): ContentResult {
        // URL 유효성 검사
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ContentResult::error(__('유효하지 않은 URL입니다.', 'ai-content-rewriter'));
        }

        // SSRF 방지: URL 보안 검증
        $url_validation = UrlValidator::validate($url);
        if (!$url_validation['valid']) {
            return ContentResult::error($url_validation['message']);
        }

        // HTTP 요청 (안전한 URL만)
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (compatible; AIContentRewriter/1.0)',
            'reject_unsafe_urls' => true, // WordPress 내장 보안 옵션
        ]);

        if (is_wp_error($response)) {
            return ContentResult::error($response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return ContentResult::error(
                sprintf(__('HTTP 오류: %d', 'ai-content-rewriter'), $status_code)
            );
        }

        $html = wp_remote_retrieve_body($response);
        if (empty($html)) {
            return ContentResult::error(__('콘텐츠를 가져올 수 없습니다.', 'ai-content-rewriter'));
        }

        return $this->parse_html($html, $url);
    }

    /**
     * HTML 파싱하여 콘텐츠 추출
     */
    private function parse_html(string $html, string $url): ContentResult {
        // DOM 파싱
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);

        // 제목 추출
        $title = $this->extract_title($xpath);

        // 메인 콘텐츠 추출
        $content = $this->extract_main_content($xpath, $doc);

        // 메타데이터 추출
        $metadata = $this->extract_metadata($xpath, $url);

        if (empty($content)) {
            return ContentResult::error(__('콘텐츠를 추출할 수 없습니다.', 'ai-content-rewriter'));
        }

        return ContentResult::success($content, $title, $metadata);
    }

    /**
     * 제목 추출
     */
    private function extract_title(\DOMXPath $xpath): string {
        // <h1> 태그 시도
        $h1_nodes = $xpath->query('//h1');
        if ($h1_nodes->length > 0) {
            return trim($h1_nodes->item(0)->textContent);
        }

        // <title> 태그 시도
        $title_nodes = $xpath->query('//title');
        if ($title_nodes->length > 0) {
            return trim($title_nodes->item(0)->textContent);
        }

        // og:title 메타 태그 시도
        $og_title = $xpath->query('//meta[@property="og:title"]/@content');
        if ($og_title->length > 0) {
            return trim($og_title->item(0)->nodeValue);
        }

        return '';
    }

    /**
     * 메인 콘텐츠 추출
     */
    private function extract_main_content(\DOMXPath $xpath, \DOMDocument $doc): string {
        // 불필요한 요소 제거
        $remove_selectors = [
            '//script',
            '//style',
            '//nav',
            '//header',
            '//footer',
            '//aside',
            '//form',
            '//iframe',
            '//*[contains(@class, "sidebar")]',
            '//*[contains(@class, "menu")]',
            '//*[contains(@class, "nav")]',
            '//*[contains(@class, "comment")]',
            '//*[contains(@class, "advertisement")]',
            '//*[contains(@class, "ad-")]',
            '//*[contains(@id, "sidebar")]',
            '//*[contains(@id, "menu")]',
            '//*[contains(@id, "nav")]',
            '//*[contains(@id, "comment")]',
        ];

        foreach ($remove_selectors as $selector) {
            $nodes = $xpath->query($selector);
            foreach ($nodes as $node) {
                $node->parentNode->removeChild($node);
            }
        }

        // 메인 콘텐츠 영역 찾기
        $content_selectors = [
            '//article',
            '//main',
            '//*[contains(@class, "content")]',
            '//*[contains(@class, "post")]',
            '//*[contains(@class, "article")]',
            '//*[contains(@class, "entry")]',
            '//*[contains(@id, "content")]',
            '//*[contains(@id, "post")]',
            '//*[contains(@id, "article")]',
            '//body',
        ];

        $content = '';
        foreach ($content_selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $content = $this->extract_text_from_node($nodes->item(0));
                if (mb_strlen($content) > 200) {
                    break;
                }
            }
        }

        return $this->clean_content($content);
    }

    /**
     * 노드에서 텍스트 추출
     */
    private function extract_text_from_node(\DOMNode $node): string {
        $text = '';
        $block_elements = ['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'br'];

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text .= $child->textContent;
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                $tag_name = strtolower($child->nodeName);

                if (in_array($tag_name, $block_elements)) {
                    $text .= "\n" . $this->extract_text_from_node($child) . "\n";
                } else {
                    $text .= $this->extract_text_from_node($child);
                }
            }
        }

        return $text;
    }

    /**
     * 콘텐츠 정리
     */
    private function clean_content(string $content): string {
        // 연속 공백 제거
        $content = preg_replace('/[^\S\n]+/', ' ', $content);

        // 연속 줄바꿈 정리
        $content = preg_replace('/\n{3,}/', "\n\n", $content);

        // 각 줄 앞뒤 공백 제거
        $lines = explode("\n", $content);
        $lines = array_map('trim', $lines);
        $content = implode("\n", $lines);

        // 빈 줄 연속 정리
        $content = preg_replace('/\n{3,}/', "\n\n", $content);

        return trim($content);
    }

    /**
     * 메타데이터 추출
     */
    private function extract_metadata(\DOMXPath $xpath, string $url): array {
        $metadata = [
            'source_url' => $url,
            'extracted_at' => current_time('mysql'),
        ];

        // 메타 설명
        $description = $xpath->query('//meta[@name="description"]/@content');
        if ($description->length > 0) {
            $metadata['description'] = trim($description->item(0)->nodeValue);
        }

        // 키워드
        $keywords = $xpath->query('//meta[@name="keywords"]/@content');
        if ($keywords->length > 0) {
            $metadata['keywords'] = trim($keywords->item(0)->nodeValue);
        }

        // 작성자
        $author = $xpath->query('//meta[@name="author"]/@content');
        if ($author->length > 0) {
            $metadata['author'] = trim($author->item(0)->nodeValue);
        }

        // 발행일
        $date_selectors = [
            '//meta[@property="article:published_time"]/@content',
            '//meta[@name="date"]/@content',
            '//time/@datetime',
        ];

        foreach ($date_selectors as $selector) {
            $date_node = $xpath->query($selector);
            if ($date_node->length > 0) {
                $metadata['published_date'] = trim($date_node->item(0)->nodeValue);
                break;
            }
        }

        // OG 이미지
        $og_image = $xpath->query('//meta[@property="og:image"]/@content');
        if ($og_image->length > 0) {
            $metadata['featured_image'] = trim($og_image->item(0)->nodeValue);
        }

        return $metadata;
    }
}
