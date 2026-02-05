<?php
/**
 * Feed Item Repository
 *
 * @package AIContentRewriter\RSS
 */

namespace AIContentRewriter\RSS;

/**
 * RSS 피드 아이템 저장소 클래스
 */
class FeedItemRepository {
    /**
     * 테이블 이름
     */
    private string $table_name;

    /**
     * 생성자
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aicr_feed_items';
    }

    /**
     * ID로 아이템 조회
     */
    public function find(int $id): ?FeedItem {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        return new FeedItem($row);
    }

    /**
     * GUID로 아이템 조회
     */
    public function find_by_guid(int $feed_id, string $guid): ?FeedItem {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE feed_id = %d AND guid = %s",
                $feed_id,
                $guid
            ),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        return new FeedItem($row);
    }

    /**
     * 피드의 아이템 목록 조회
     */
    public function find_by_feed(int $feed_id, array $filters = []): array {
        global $wpdb;

        $sql = "SELECT * FROM {$this->table_name} WHERE feed_id = %d";
        $params = [$feed_id];

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $placeholders = implode(',', array_fill(0, count($filters['status']), '%s'));
                $sql .= " AND status IN ({$placeholders})";
                $params = array_merge($params, $filters['status']);
            } else {
                $sql .= " AND status = %s";
                $params[] = $filters['status'];
            }
        }

        $sql .= " ORDER BY pub_date DESC";

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

        return array_map(fn($row) => new FeedItem($row), $rows);
    }

    /**
     * 여러 피드의 아이템 조회
     */
    public function find_by_feeds(array $feed_ids, array $filters = []): array {
        global $wpdb;

        if (empty($feed_ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($feed_ids), '%d'));
        $sql = "SELECT i.*, f.name as feed_name
                FROM {$this->table_name} i
                LEFT JOIN {$wpdb->prefix}aicr_feeds f ON i.feed_id = f.id
                WHERE i.feed_id IN ({$placeholders})";
        $params = $feed_ids;

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $status_placeholders = implode(',', array_fill(0, count($filters['status']), '%s'));
                $sql .= " AND i.status IN ({$status_placeholders})";
                $params = array_merge($params, $filters['status']);
            } else {
                $sql .= " AND i.status = %s";
                $params[] = $filters['status'];
            }
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (i.title LIKE %s OR i.content LIKE %s)";
            $search = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = $search;
            $params[] = $search;
        }

        $sql .= " ORDER BY i.pub_date DESC";

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

        return array_map(fn($row) => new FeedItem($row), $rows);
    }

    /**
     * 아이템 저장 (Upsert)
     */
    public function save(FeedItem $item): int {
        global $wpdb;

        $data = $item->to_db_array();

        if ($item->get_id()) {
            $wpdb->update(
                $this->table_name,
                $data,
                ['id' => $item->get_id()]
            );
            return $item->get_id();
        }

        // GUID 중복 체크
        $existing = $this->find_by_guid($item->get_feed_id(), $item->get_guid());
        if ($existing) {
            // 기존 아이템 업데이트 (콘텐츠가 변경된 경우)
            $wpdb->update(
                $this->table_name,
                $data,
                ['id' => $existing->get_id()]
            );
            return $existing->get_id();
        }

        $wpdb->insert($this->table_name, $data);
        $id = $wpdb->insert_id;
        $item->set_id($id);

        return $id;
    }

    /**
     * 여러 아이템 일괄 저장
     */
    public function save_many(array $items): int {
        $saved = 0;
        foreach ($items as $item) {
            if ($this->save($item)) {
                $saved++;
            }
        }
        return $saved;
    }

    /**
     * 아이템 삭제
     */
    public function delete(int $id): bool {
        global $wpdb;
        return $wpdb->delete($this->table_name, ['id' => $id]) !== false;
    }

    /**
     * 피드의 모든 아이템 삭제
     */
    public function delete_by_feed(int $feed_id): int {
        global $wpdb;
        return $wpdb->delete($this->table_name, ['feed_id' => $feed_id]);
    }

    /**
     * 오래된 아이템 삭제
     */
    public function delete_old_items(int $days = 30): int {
        global $wpdb;

        $sql = "DELETE FROM {$this->table_name}
                WHERE fetched_at < DATE_SUB(NOW(), INTERVAL %d DAY)
                AND status NOT IN (%s, %s)";

        return $wpdb->query(
            $wpdb->prepare(
                $sql,
                $days,
                FeedItem::STATUS_QUEUED,
                FeedItem::STATUS_PROCESSING
            )
        );
    }

    /**
     * 상태 업데이트
     */
    public function update_status(int $id, string $status, ?string $error = null): bool {
        global $wpdb;

        $data = ['status' => $status];

        if ($status === FeedItem::STATUS_COMPLETED || $status === FeedItem::STATUS_FAILED) {
            $data['processed_at'] = current_time('mysql');
        }

        if ($error !== null) {
            $data['error_message'] = $error;
        }

        return $wpdb->update($this->table_name, $data, ['id' => $id]) !== false;
    }

    /**
     * 여러 아이템 상태 업데이트
     */
    public function update_status_many(array $ids, string $status): int {
        global $wpdb;

        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $sql = "UPDATE {$this->table_name} SET status = %s WHERE id IN ({$placeholders})";

        $params = array_merge([$status], $ids);

        return $wpdb->query($wpdb->prepare($sql, ...$params));
    }

    /**
     * 재작성된 게시글 ID 저장
     */
    public function set_rewritten_post(int $id, int $post_id): bool {
        global $wpdb;

        return $wpdb->update(
            $this->table_name,
            [
                'rewritten_post_id' => $post_id,
                'status' => FeedItem::STATUS_COMPLETED,
                'processed_at' => current_time('mysql'),
            ],
            ['id' => $id]
        ) !== false;
    }

    /**
     * 미읽음 아이템 수 조회
     */
    public function count_unread(int $feed_id): int {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE feed_id = %d AND status = %s",
                $feed_id,
                FeedItem::STATUS_UNREAD
            )
        );
    }

    /**
     * 아이템 수 조회
     */
    public function count(int $feed_id, ?string $status = null): int {
        global $wpdb;

        $sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE feed_id = %d";
        $params = [$feed_id];

        if ($status) {
            $sql .= " AND status = %s";
            $params[] = $status;
        }

        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
    }

    /**
     * 여러 피드의 아이템 수 조회
     */
    public function count_by_feeds(array $feed_ids, ?string $status = null): int {
        global $wpdb;

        if (empty($feed_ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($feed_ids), '%d'));
        $sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE feed_id IN ({$placeholders})";
        $params = $feed_ids;

        if ($status) {
            $sql .= " AND status = %s";
            $params[] = $status;
        }

        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
    }

    /**
     * GUID 존재 확인
     */
    public function exists_by_guid(int $feed_id, string $guid): bool {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE feed_id = %d AND guid = %s",
                $feed_id,
                $guid
            )
        );

        return (int) $count > 0;
    }

    /**
     * 모든 아이템 읽음 처리
     */
    public function mark_all_as_read(int $feed_id): int {
        global $wpdb;

        return $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->table_name} SET status = %s WHERE feed_id = %d AND status = %s",
                FeedItem::STATUS_READ,
                $feed_id,
                FeedItem::STATUS_UNREAD
            )
        );
    }
}
