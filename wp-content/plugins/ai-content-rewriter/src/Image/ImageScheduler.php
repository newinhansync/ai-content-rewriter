<?php
/**
 * Image Scheduler
 *
 * @package AIContentRewriter\Image
 */

namespace AIContentRewriter\Image;

use AIContentRewriter\Cron\CronLogger;

/**
 * 이미지 생성 스케줄러
 *
 * 기존 자동화 시스템(FeedScheduler, automation.php)과 통합
 */
class ImageScheduler {
    /**
     * Cron 훅 이름
     */
    public const HOOK_GENERATE_IMAGES = 'aicr_generate_images';

    /**
     * 락 키
     */
    private const LOCK_KEY = 'aicr_image_generation_lock';

    /**
     * Cron 로거
     */
    private CronLogger $logger;

    /**
     * 이미지 생성기
     */
    private ImageGenerator $generator;

    /**
     * 생성자
     */
    public function __construct() {
        $this->logger = new CronLogger();
        $this->generator = new ImageGenerator();
    }

    /**
     * 스케줄러 초기화 - Plugin.php에서 호출
     */
    public function init(): void {
        // Cron 훅 등록
        add_action(self::HOOK_GENERATE_IMAGES, [$this, 'processPendingPosts']);

        // 스케줄이 없으면 등록
        if (!wp_next_scheduled(self::HOOK_GENERATE_IMAGES)) {
            $enabled = get_option('aicr_image_schedule_enabled', false);

            if ($enabled) {
                $interval = get_option('aicr_image_schedule_interval', 'hourly');
                wp_schedule_event(time(), $interval, self::HOOK_GENERATE_IMAGES);
            }
        }
    }

    /**
     * 스케줄 활성화
     */
    public function enableSchedule(): void {
        $interval = get_option('aicr_image_schedule_interval', 'hourly');

        // 기존 스케줄 제거
        $this->disableSchedule();

        // 새 스케줄 등록
        wp_schedule_event(time(), $interval, self::HOOK_GENERATE_IMAGES);

        update_option('aicr_image_schedule_enabled', true);
    }

    /**
     * 스케줄 비활성화
     */
    public function disableSchedule(): void {
        $timestamp = wp_next_scheduled(self::HOOK_GENERATE_IMAGES);

        if ($timestamp) {
            wp_unschedule_event($timestamp, self::HOOK_GENERATE_IMAGES);
        }

        update_option('aicr_image_schedule_enabled', false);
    }

    /**
     * 스케줄 간격 변경
     */
    public function updateInterval(string $interval): void {
        $validIntervals = ['hourly', 'twicedaily', 'daily'];

        if (!in_array($interval, $validIntervals, true)) {
            $interval = 'hourly';
        }

        update_option('aicr_image_schedule_interval', $interval);

        // 스케줄이 활성화되어 있으면 재등록
        if (get_option('aicr_image_schedule_enabled', false)) {
            $this->enableSchedule();
        }
    }

    /**
     * 이미지 없는 게시글에 자동으로 이미지 생성
     */
    public function processPendingPosts(): void {
        // 스케줄 활성화 여부 확인
        if (!get_option('aicr_image_schedule_enabled', false)) {
            return;
        }

        // 락 확인 (동시 실행 방지)
        if (get_transient(self::LOCK_KEY)) {
            return;
        }

        // 락 설정 (5분)
        set_transient(self::LOCK_KEY, true, 300);

        $logId = $this->logger->start(self::HOOK_GENERATE_IMAGES);
        $processed = 0;

        try {
            $limit = (int) get_option('aicr_image_batch_size', 5);
            $posts = $this->getPostsWithoutImages($limit);

            foreach ($posts as $post) {
                if ($this->shouldSkipPost($post->ID)) {
                    continue;
                }

                try {
                    // 개별 게시글 락 획득 시도
                    if (!$this->acquirePostLock($post->ID)) {
                        continue;
                    }

                    $this->generator->generateForPost($post->ID);
                    $processed++;

                    // API Rate Limit 대비 딜레이
                    sleep(3);

                } catch (\Exception $e) {
                    // 개별 게시글 실패는 로깅만 하고 계속 진행
                    if (get_option('aicr_debug_mode', false)) {
                        error_log('[AICR ImageScheduler] Post ' . $post->ID . ' failed: ' . $e->getMessage());
                    }
                } finally {
                    $this->releasePostLock($post->ID);
                }
            }

            $this->logger->complete($logId, $processed);

        } catch (\Exception $e) {
            $this->logger->complete($logId, $processed, $e->getMessage());

        } finally {
            delete_transient(self::LOCK_KEY);
        }
    }

    /**
     * 이미지 없는 게시글 조회
     */
    private function getPostsWithoutImages(int $limit): array {
        global $wpdb;

        // AICR로 재작성된 게시글 중 이미지 없는 것
        $sql = "
            SELECT p.ID
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_thumbnail_id'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'aicr_images_generated'
            WHERE p.post_status = 'publish'
              AND p.post_type = 'post'
              AND pm1.meta_value IS NULL
              AND pm2.meta_value IS NULL
              AND EXISTS (
                  SELECT 1 FROM {$wpdb->postmeta} pm3
                  WHERE pm3.post_id = p.ID AND pm3.meta_key = '_aicr_rewritten_at'
              )
            ORDER BY p.post_date DESC
            LIMIT %d
        ";

        return $wpdb->get_results($wpdb->prepare($sql, $limit));
    }

    /**
     * 스킵 로직 - 이미 이미지가 있는 게시글 건너뛰기
     */
    private function shouldSkipPost(int $postId): bool {
        // 1. 이미 이미지 생성됨
        if (get_post_meta($postId, 'aicr_images_generated', true)) {
            return true;
        }

        // 2. Featured Image 이미 있음
        if (has_post_thumbnail($postId) && get_option('aicr_image_skip_with_thumbnail', true)) {
            return true;
        }

        // 3. 콘텐츠에 이미지 태그 이미 있음
        $content = get_post_field('post_content', $postId);
        if (strpos($content, '<img') !== false && get_option('aicr_image_skip_with_images', true)) {
            return true;
        }

        // 4. 연속 3회 이상 실패한 게시글 스킵
        global $wpdb;
        $tableName = $wpdb->prefix . 'aicr_image_history';

        $recentFailures = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tableName}
             WHERE post_id = %d AND status = 'failed'
             AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            $postId
        ));

        if ($recentFailures >= 3) {
            return true;
        }

        return false;
    }

    /**
     * 개별 게시글 락 획득
     */
    private function acquirePostLock(int $postId): bool {
        $lockKey = 'aicr_image_gen_' . $postId;
        $existing = get_transient($lockKey);

        if ($existing) {
            return false;
        }

        return set_transient($lockKey, time(), 600); // 10분 락
    }

    /**
     * 개별 게시글 락 해제
     */
    private function releasePostLock(int $postId): void {
        delete_transient('aicr_image_gen_' . $postId);
    }

    /**
     * 수동 실행 (자동화 탭에서 호출)
     */
    public function runNow(): array {
        // 락 확인
        if (get_transient(self::LOCK_KEY)) {
            return [
                'status' => 'locked',
                'message' => __('이미지 생성이 이미 진행 중입니다.', 'ai-content-rewriter'),
            ];
        }

        $this->processPendingPosts();

        return [
            'status' => 'completed',
            'message' => __('이미지 생성이 완료되었습니다.', 'ai-content-rewriter'),
        ];
    }

    /**
     * 대기 중인 게시글 수 조회
     */
    public function getPendingCount(): int {
        global $wpdb;

        $sql = "
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_thumbnail_id'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'aicr_images_generated'
            WHERE p.post_status = 'publish'
              AND p.post_type = 'post'
              AND pm1.meta_value IS NULL
              AND pm2.meta_value IS NULL
              AND EXISTS (
                  SELECT 1 FROM {$wpdb->postmeta} pm3
                  WHERE pm3.post_id = p.ID AND pm3.meta_key = '_aicr_rewritten_at'
              )
        ";

        return (int) $wpdb->get_var($sql);
    }

    /**
     * 스케줄 상태 조회
     */
    public function getStatus(): array {
        $nextRun = wp_next_scheduled(self::HOOK_GENERATE_IMAGES);

        return [
            'enabled' => (bool) get_option('aicr_image_schedule_enabled', false),
            'interval' => get_option('aicr_image_schedule_interval', 'hourly'),
            'batch_size' => (int) get_option('aicr_image_batch_size', 5),
            'next_run' => $nextRun ? date('Y-m-d H:i:s', $nextRun) : null,
            'pending_count' => $this->getPendingCount(),
            'is_running' => (bool) get_transient(self::LOCK_KEY),
        ];
    }
}
