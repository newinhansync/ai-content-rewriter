<?php
/**
 * Feed Fetcher
 *
 * RSS 피드 가져오기 클래스
 *
 * @package AIContentRewriter\RSS
 */

namespace AIContentRewriter\RSS;

use WP_Error;

/**
 * RSS 피드 가져오기 클래스
 */
class FeedFetcher {
    /**
     * 파서 인스턴스
     */
    private FeedParser $parser;

    /**
     * 피드 저장소
     */
    private FeedRepository $feed_repository;

    /**
     * 아이템 저장소
     */
    private FeedItemRepository $item_repository;

    /**
     * 생성자
     */
    public function __construct() {
        $this->parser = new FeedParser();
        $this->feed_repository = new FeedRepository();
        $this->item_repository = new FeedItemRepository();
    }

    /**
     * 단일 피드 가져오기
     *
     * @param Feed $feed 피드 객체
     * @return array 결과 배열
     */
    public function fetch(Feed $feed): array {
        $result = [
            'success' => false,
            'new_items' => 0,
            'updated_items' => 0,
            'error' => null,
        ];

        try {
            // 피드 파싱
            $items = $this->parser->parse($feed->get_feed_url());

            if (is_wp_error($items)) {
                throw new \Exception($items->get_error_message());
            }

            // 피드 정보 업데이트
            $feed_info = $this->parser->get_feed_info();
            if (!empty($feed_info['title']) && empty($feed->get_name())) {
                $feed->set_name($feed_info['title']);
            }
            if (!empty($feed_info['link'])) {
                $feed->set_site_url($feed_info['link']);
            }
            if (!empty($feed_info['title'])) {
                $feed->set_site_name($feed_info['title']);
            }
            if (!empty($feed_info['type'])) {
                $feed->set_feed_type($feed_info['type']);
            }

            // 아이템 저장
            $new_count = 0;
            $updated_count = 0;

            foreach ($items as $item) {
                /** @var FeedItem $item */
                $item->set_feed_id($feed->get_id());

                // 기존 아이템 확인
                $existing = $this->item_repository->find_by_guid(
                    $feed->get_id(),
                    $item->get_guid()
                );

                if ($existing) {
                    // 콘텐츠 변경 확인
                    if ($this->has_content_changed($existing, $item)) {
                        $item->set_id($existing->get_id());
                        $item->set_status($existing->get_status());
                        $this->item_repository->save($item);
                        $updated_count++;
                    }
                } else {
                    // 새 아이템 저장
                    $this->item_repository->save($item);
                    $new_count++;

                    // 자동 재작성 큐에 추가
                    if ($feed->is_auto_rewrite()) {
                        $this->queue_for_rewrite($item, $feed);
                    }
                }
            }

            // 피드 상태 업데이트
            $this->feed_repository->mark_as_fetched($feed->get_id(), $new_count);

            $result['success'] = true;
            $result['new_items'] = $new_count;
            $result['updated_items'] = $updated_count;

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            $this->feed_repository->update_status(
                $feed->get_id(),
                Feed::STATUS_ERROR,
                $e->getMessage()
            );
        }

        return $result;
    }

    /**
     * 갱신 필요한 모든 피드 가져오기
     *
     * @param int $limit 최대 피드 수
     * @return array 결과 배열
     */
    public function fetch_due_feeds(int $limit = 0): array {
        if ($limit <= 0) {
            $limit = (int) get_option('aicr_rss_concurrent_fetch', 5);
        }

        $feeds = $this->feed_repository->find_due_for_fetch($limit);
        $results = [];

        foreach ($feeds as $feed) {
            $results[$feed->get_id()] = $this->fetch($feed);
        }

        return $results;
    }

    /**
     * 사용자의 모든 활성 피드 가져오기
     *
     * @param int $user_id 사용자 ID
     * @return array 결과 배열
     */
    public function fetch_user_feeds(int $user_id): array {
        $feeds = $this->feed_repository->find_active($user_id);
        $results = [];

        foreach ($feeds as $feed) {
            $results[$feed->get_id()] = $this->fetch($feed);
        }

        return $results;
    }

    /**
     * 콘텐츠 변경 확인
     */
    private function has_content_changed(FeedItem $existing, FeedItem $new): bool {
        // 제목 또는 콘텐츠가 변경되었는지 확인
        if ($existing->get_title() !== $new->get_title()) {
            return true;
        }

        // 콘텐츠 해시 비교
        $existing_hash = md5($existing->get_content());
        $new_hash = md5($new->get_content());

        return $existing_hash !== $new_hash;
    }

    /**
     * 재작성 큐에 추가
     */
    private function queue_for_rewrite(FeedItem $item, Feed $feed): void {
        // 큐 제한 확인
        $queue_limit = (int) get_option('aicr_rss_rewrite_queue_limit', 10);
        $queued_count = $this->item_repository->count($feed->get_id(), FeedItem::STATUS_QUEUED);

        if ($queued_count >= $queue_limit) {
            return;
        }

        // 상태를 QUEUED로 변경
        $this->item_repository->update_status($item->get_id(), FeedItem::STATUS_QUEUED);
    }

    /**
     * 피드 URL 검증
     *
     * @param string $url 피드 URL
     * @return array 검증 결과
     */
    public function validate_feed(string $url): array {
        return $this->parser->validate($url);
    }

    /**
     * 피드 URL 자동 탐색
     *
     * @param string $site_url 사이트 URL
     * @return array 발견된 피드 URL 배열
     */
    public function discover_feeds(string $site_url): array {
        $feeds = [];

        // URL 정규화
        $site_url = trailingslashit($site_url);

        // HTML 페이지 가져오기
        $response = wp_remote_get($site_url, [
            'timeout' => 15,
            'user-agent' => 'Mozilla/5.0 (compatible; AI Content Rewriter/1.0)',
        ]);

        if (is_wp_error($response)) {
            return $feeds;
        }

        $html = wp_remote_retrieve_body($response);
        if (empty($html)) {
            return $feeds;
        }

        // link 태그에서 피드 URL 추출
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);

        $links = $dom->getElementsByTagName('link');
        foreach ($links as $link) {
            $type = $link->getAttribute('type');
            $href = $link->getAttribute('href');

            if (empty($href)) {
                continue;
            }

            // RSS 또는 Atom 피드인지 확인
            if (
                $type === 'application/rss+xml' ||
                $type === 'application/atom+xml' ||
                $type === 'application/feed+json'
            ) {
                // 상대 URL을 절대 URL로 변환
                if (!filter_var($href, FILTER_VALIDATE_URL)) {
                    $href = $site_url . ltrim($href, '/');
                }

                $title = $link->getAttribute('title') ?: '';

                $feeds[] = [
                    'url' => $href,
                    'title' => $title,
                    'type' => $type,
                ];
            }
        }

        // 일반적인 피드 경로도 확인
        $common_paths = [
            'feed/',
            'feed/rss/',
            'feed/rss2/',
            'feed/atom/',
            'rss/',
            'rss.xml',
            'atom.xml',
            'index.xml',
        ];

        foreach ($common_paths as $path) {
            $feed_url = $site_url . $path;

            // 이미 발견된 피드인지 확인
            $found = false;
            foreach ($feeds as $feed) {
                if ($feed['url'] === $feed_url) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                // 피드 유효성 검증
                $validation = $this->parser->validate($feed_url);
                if ($validation['valid']) {
                    $feeds[] = [
                        'url' => $feed_url,
                        'title' => $validation['title'] ?: '',
                        'type' => 'application/rss+xml',
                    ];
                }
            }
        }

        return $feeds;
    }
}
