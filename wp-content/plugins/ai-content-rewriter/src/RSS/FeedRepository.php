<?php
/**
 * Feed Repository
 *
 * @package AIContentRewriter\RSS
 */

namespace AIContentRewriter\RSS;

/**
 * RSS 피드 저장소 클래스
 */
class FeedRepository {
    /**
     * 테이블 이름
     */
    private string $table_name;

    /**
     * 생성자
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aicr_feeds';
    }

    /**
     * ID로 피드 조회
     */
    public function find(int $id): ?Feed {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        return new Feed($row);
    }

    /**
     * 모든 피드 조회 (관리자용)
     */
    public function find_all(array $filters = []): array {
        global $wpdb;

        $sql = "SELECT * FROM {$this->table_name} WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND status = %s";
            $params[] = $filters['status'];
        }

        $sql .= " ORDER BY created_at DESC";

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT %d";
            $params[] = (int) $filters['limit'];

            if (!empty($filters['offset'])) {
                $sql .= " OFFSET %d";
                $params[] = (int) $filters['offset'];
            }
        }

        if (!empty($params)) {
            $rows = $wpdb->get_results(
                $wpdb->prepare($sql, ...$params),
                ARRAY_A
            );
        } else {
            $rows = $wpdb->get_results($sql, ARRAY_A);
        }

        return array_map(fn($row) => new Feed($row), $rows);
    }

    /**
     * 사용자의 모든 피드 조회
     */
    public function find_by_user(int $user_id, array $filters = []): array {
        global $wpdb;

        $sql = "SELECT * FROM {$this->table_name} WHERE user_id = %d";
        $params = [$user_id];

        if (!empty($filters['status'])) {
            $sql .= " AND status = %s";
            $params[] = $filters['status'];
        }

        $sql .= " ORDER BY created_at DESC";

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT %d";
            $params[] = (int) $filters['limit'];

            if (!empty($filters['offset'])) {
                $sql .= " OFFSET %d";
                $params[] = (int) $filters['offset'];
            }
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare($sql, ...$params),
            ARRAY_A
        );

        return array_map(fn($row) => new Feed($row), $rows);
    }

    /**
     * 활성 피드 조회
     */
    public function find_active(int $user_id): array {
        return $this->find_by_user($user_id, ['status' => Feed::STATUS_ACTIVE]);
    }

    /**
     * 갱신 필요한 피드 조회
     */
    public function find_due_for_fetch(int $limit = 10): array {
        global $wpdb;

        $sql = "SELECT * FROM {$this->table_name}
                WHERE status = %s
                AND (
                    last_fetched_at IS NULL
                    OR DATE_ADD(last_fetched_at, INTERVAL fetch_interval SECOND) <= NOW()
                )
                ORDER BY last_fetched_at ASC
                LIMIT %d";

        $rows = $wpdb->get_results(
            $wpdb->prepare($sql, Feed::STATUS_ACTIVE, $limit),
            ARRAY_A
        );

        return array_map(fn($row) => new Feed($row), $rows);
    }

    /**
     * 피드 저장
     */
    public function save(Feed $feed): int {
        global $wpdb;

        $data = $feed->to_db_array();

        if ($feed->get_id()) {
            $wpdb->update(
                $this->table_name,
                $data,
                ['id' => $feed->get_id()]
            );
            return $feed->get_id();
        }

        $wpdb->insert($this->table_name, $data);
        $id = $wpdb->insert_id;
        $feed->set_id($id);

        return $id;
    }

    /**
     * 피드 삭제
     */
    public function delete(int $id): bool {
        global $wpdb;

        // 연관된 아이템도 함께 삭제 (CASCADE가 없는 경우를 대비)
        $items_table = $wpdb->prefix . 'aicr_feed_items';
        $wpdb->delete($items_table, ['feed_id' => $id]);

        return $wpdb->delete($this->table_name, ['id' => $id]) !== false;
    }

    /**
     * 피드 상태 업데이트
     */
    public function update_status(int $id, string $status, ?string $error = null): bool {
        global $wpdb;

        $data = ['status' => $status];
        if ($error !== null) {
            $data['last_error'] = $error;
        }

        return $wpdb->update($this->table_name, $data, ['id' => $id]) !== false;
    }

    /**
     * 피드 가져오기 완료 표시
     */
    public function mark_as_fetched(int $id, int $new_items = 0): bool {
        global $wpdb;

        $sql = "UPDATE {$this->table_name}
                SET last_fetched_at = NOW(),
                    last_error = NULL,
                    item_count = item_count + %d,
                    unread_count = unread_count + %d
                WHERE id = %d";

        return $wpdb->query(
            $wpdb->prepare($sql, $new_items, $new_items, $id)
        ) !== false;
    }

    /**
     * 미읽음 카운트 업데이트
     */
    public function update_unread_count(int $id): bool {
        global $wpdb;

        $items_table = $wpdb->prefix . 'aicr_feed_items';

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$items_table} WHERE feed_id = %d AND status = %s",
                $id,
                FeedItem::STATUS_UNREAD
            )
        );

        return $wpdb->update(
            $this->table_name,
            ['unread_count' => (int) $count],
            ['id' => $id]
        ) !== false;
    }

    /**
     * 아이템 카운트 업데이트
     */
    public function update_item_count(int $id): bool {
        global $wpdb;

        $items_table = $wpdb->prefix . 'aicr_feed_items';

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$items_table} WHERE feed_id = %d",
                $id
            )
        );

        return $wpdb->update(
            $this->table_name,
            ['item_count' => (int) $count],
            ['id' => $id]
        ) !== false;
    }

    /**
     * URL로 피드 존재 확인
     */
    public function exists_by_url(string $url, int $user_id): bool {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE feed_url = %s AND user_id = %d",
                $url,
                $user_id
            )
        );

        return (int) $count > 0;
    }

    /**
     * 사용자의 피드 통계
     */
    public function get_stats(int $user_id): array {
        global $wpdb;

        $sql = "SELECT
                    COUNT(*) as total_feeds,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_feeds,
                    SUM(item_count) as total_items,
                    SUM(unread_count) as total_unread
                FROM {$this->table_name}
                WHERE user_id = %d";

        $row = $wpdb->get_row($wpdb->prepare($sql, $user_id), ARRAY_A);

        return [
            'total_feeds' => (int) ($row['total_feeds'] ?? 0),
            'active_feeds' => (int) ($row['active_feeds'] ?? 0),
            'total_items' => (int) ($row['total_items'] ?? 0),
            'total_unread' => (int) ($row['total_unread'] ?? 0),
        ];
    }

    /**
     * 피드 수 조회
     */
    public function count(int $user_id, ?string $status = null): int {
        global $wpdb;

        $sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE user_id = %d";
        $params = [$user_id];

        if ($status) {
            $sql .= " AND status = %s";
            $params[] = $status;
        }

        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
    }
}
