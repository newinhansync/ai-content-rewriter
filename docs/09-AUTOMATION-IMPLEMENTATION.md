# AI Content Rewriter - 완전 자동화 기능 구현 계획

**버전**: v1.1.0 → v1.2.0
**작성일**: 2026-01-07
**상태**: 계획됨

---

## 1. 개요

### 1.1 배경

웹호스팅 환경에서 WordPress WP-Cron의 한계:
- **방문자 트리거 방식**: 사이트 방문이 없으면 스케줄 작업 미실행
- **저트래픽 사이트**: RSS 피드 갱신 및 자동 재작성 지연/실패 가능
- **불확실성**: 정확한 실행 시간 예측 불가

### 1.2 해결책

외부 Cron 서비스를 통해 WP-Cron을 정기적으로 트리거:
```
외부 서비스 (EasyCron, cPanel Cron)
       ↓
5~15분 주기로 호출
       ↓
플러그인 전용 엔드포인트 또는 wp-cron.php
       ↓
예약된 작업 정상 실행
```

### 1.3 목표

1. **설정 페이지에 "자동화" 탭 추가** - Cron 상태 모니터링
2. **외부 Cron 설정 가이드** - cPanel, EasyCron 모두 문서화
3. **실행 이력 로깅** - 문제 진단 가능
4. **수동 실행 버튼** - 즉시 테스트 가능

---

## 2. 현재 구현 상태

### 2.1 기존 스케줄러 (FeedScheduler.php)

이미 완전 구현된 WP-Cron 기반 자동화:

| 훅 이름 | 주기 | 기능 |
|--------|------|------|
| `aicr_fetch_feeds` | 15분 | RSS 피드 자동 수집 |
| `aicr_auto_rewrite_items` | 30분 | 큐 기반 자동 재작성 |
| `aicr_cleanup_old_items` | 매일 | 오래된 아이템 정리 |

### 2.2 기존 메서드

```php
// 이미 구현됨
public function get_schedule_info(): array;  // 다음 실행 시간 조회
public function run_now(string $task): bool; // 수동 즉시 실행
```

### 2.3 피드별 설정

각 피드에 개별 설정 가능:
- `auto_rewrite`: 새 아이템 자동 재작성 여부
- `auto_publish`: 재작성 후 즉시 발행 여부

---

## 3. 구현 계획

### 3.1 새로 생성할 파일

| 파일 | 용도 |
|------|------|
| `src/Cron/CronMonitor.php` | Cron 상태 모니터링 및 건강 체크 |
| `src/Cron/CronLogger.php` | 실행 이력 로깅 |
| `src/Admin/views/automation.php` | 자동화 탭 UI |

### 3.2 수정할 파일

| 파일 | 변경 내용 |
|------|----------|
| `src/Database/Schema.php` | `aicr_cron_logs` 테이블 추가 |
| `src/RSS/FeedScheduler.php` | 실행 로깅 연동 |
| `src/Admin/AjaxHandler.php` | Cron 관련 AJAX 핸들러 추가 |
| `src/Admin/views/settings.php` | 자동화 탭 네비게이션 추가 |
| `src/Core/Plugin.php` | 외부 Cron 엔드포인트 등록 |
| `assets/js/admin.js` | 자동화 모듈 JavaScript |
| `assets/css/admin.css` | 대시보드 스타일 |

---

## 4. 상세 구현

### 4.1 데이터베이스 스키마

**새 테이블: `{prefix}aicr_cron_logs`**

```sql
CREATE TABLE {prefix}aicr_cron_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hook_name VARCHAR(100) NOT NULL,
    started_at DATETIME NOT NULL,
    completed_at DATETIME,
    status ENUM('running', 'completed', 'failed') DEFAULT 'running',
    items_processed INT DEFAULT 0,
    error_message TEXT,
    execution_time FLOAT,
    INDEX idx_hook_started (hook_name, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.2 CronLogger 클래스

```php
<?php
namespace AIContentRewriter\Cron;

class CronLogger {
    private string $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aicr_cron_logs';
    }

    /**
     * 실행 시작 기록
     */
    public function start(string $hook_name): int {
        global $wpdb;
        $wpdb->insert($this->table_name, [
            'hook_name' => $hook_name,
            'started_at' => current_time('mysql'),
            'status' => 'running',
        ]);
        return $wpdb->insert_id;
    }

    /**
     * 실행 완료 기록
     */
    public function complete(int $log_id, int $items_processed = 0, ?string $error = null): void {
        global $wpdb;

        $started = $wpdb->get_var($wpdb->prepare(
            "SELECT started_at FROM {$this->table_name} WHERE id = %d",
            $log_id
        ));

        $execution_time = $started ?
            (strtotime(current_time('mysql')) - strtotime($started)) : 0;

        $wpdb->update($this->table_name, [
            'completed_at' => current_time('mysql'),
            'status' => $error ? 'failed' : 'completed',
            'items_processed' => $items_processed,
            'error_message' => $error,
            'execution_time' => $execution_time,
        ], ['id' => $log_id]);
    }

    /**
     * 최근 로그 조회
     */
    public function get_recent(int $hours = 24, int $limit = 50): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE started_at > DATE_SUB(NOW(), INTERVAL %d HOUR)
             ORDER BY started_at DESC
             LIMIT %d",
            $hours, $limit
        ), ARRAY_A);
    }

    /**
     * 오래된 로그 정리
     */
    public function cleanup(int $days = 7): int {
        global $wpdb;
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name}
             WHERE started_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }
}
```

### 4.3 CronMonitor 클래스

```php
<?php
namespace AIContentRewriter\Cron;

class CronMonitor {

    /**
     * 전체 Cron 상태 조회
     */
    public function get_health_status(): array {
        return [
            'overall_status' => $this->calculate_overall_status(),
            'wp_cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
            'alternate_cron' => defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON,
            'external_cron_confirmed' => get_option('aicr_external_cron_confirmed', false),
            'last_execution' => $this->get_last_execution_time(),
            'schedules' => $this->get_schedule_status(),
            'recommendations' => $this->get_recommendations(),
        ];
    }

    /**
     * 각 스케줄 상태 조회
     */
    public function get_schedule_status(): array {
        $scheduler = new \AIContentRewriter\RSS\FeedScheduler();
        $info = $scheduler->get_schedule_info();

        // 로그에서 마지막 실행 정보 추가
        $logger = new CronLogger();
        foreach ($info as $hook => &$data) {
            $logs = $logger->get_recent(24, 1);
            $last_log = array_filter($logs, fn($l) => $l['hook_name'] === $hook);
            $last_log = reset($last_log);

            $data['last_run'] = $last_log ? $last_log['completed_at'] : null;
            $data['last_status'] = $last_log ? $last_log['status'] : null;
            $data['last_items'] = $last_log ? (int)$last_log['items_processed'] : null;
        }

        return $info;
    }

    /**
     * 전체 상태 계산
     */
    private function calculate_overall_status(): string {
        $wp_cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $external_confirmed = get_option('aicr_external_cron_confirmed', false);
        $last_execution = $this->get_last_execution_time();

        // Critical: DISABLE_WP_CRON인데 외부 cron 미설정
        if ($wp_cron_disabled && !$external_confirmed) {
            return 'critical';
        }

        // Warning: 2시간 이상 실행 없음
        if ($last_execution && (time() - strtotime($last_execution)) > 7200) {
            return 'warning';
        }

        // Warning: WP-Cron 의존 (불안정)
        if (!$wp_cron_disabled && !$external_confirmed) {
            return 'warning';
        }

        return 'healthy';
    }

    /**
     * 권고사항 생성
     */
    public function get_recommendations(): array {
        $recommendations = [];

        if (!defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => __('WP-Cron은 사이트 방문에 의존합니다. 안정적인 자동화를 위해 외부 Cron을 설정하고 DISABLE_WP_CRON을 활성화하세요.', 'ai-content-rewriter'),
            ];
        }

        $last_execution = $this->get_last_execution_time();
        if (!$last_execution || (time() - strtotime($last_execution)) > 7200) {
            $recommendations[] = [
                'type' => 'error',
                'message' => __('최근 2시간 동안 Cron 실행 기록이 없습니다. 설정을 확인하세요.', 'ai-content-rewriter'),
            ];
        }

        return $recommendations;
    }

    /**
     * 보안 토큰 포함 Cron URL 생성
     */
    public function get_cron_urls(): array {
        $token = $this->get_or_create_token();

        return [
            'wp_cron' => site_url('/wp-cron.php'),
            'plugin_endpoint' => add_query_arg([
                'aicr_cron' => '1',
                'token' => $token,
            ], site_url('/')),
        ];
    }

    /**
     * 토큰 생성 또는 조회
     */
    public function get_or_create_token(): string {
        $token = get_option('aicr_cron_secret_token');
        if (empty($token)) {
            $token = wp_generate_password(32, false);
            update_option('aicr_cron_secret_token', $token);
        }
        return $token;
    }

    /**
     * 토큰 재생성
     */
    public function regenerate_token(): string {
        $token = wp_generate_password(32, false);
        update_option('aicr_cron_secret_token', $token);
        return $token;
    }

    private function get_last_execution_time(): ?string {
        $logger = new CronLogger();
        $logs = $logger->get_recent(24, 1);
        return $logs ? $logs[0]['completed_at'] : null;
    }
}
```

### 4.4 외부 Cron 엔드포인트

**Plugin.php에 추가:**

```php
// init 훅에서 외부 Cron 요청 처리
add_action('init', function() {
    if (!isset($_GET['aicr_cron']) || $_GET['aicr_cron'] !== '1') {
        return;
    }

    $token = sanitize_text_field($_GET['token'] ?? '');
    $stored_token = get_option('aicr_cron_secret_token');

    if (empty($stored_token) || !hash_equals($stored_token, $token)) {
        status_header(403);
        wp_die('Unauthorized', 'Unauthorized', ['response' => 403]);
    }

    // 외부 Cron 확인 플래그 설정
    update_option('aicr_external_cron_confirmed', true);

    // 스케줄러 실행
    $scheduler = new \AIContentRewriter\RSS\FeedScheduler();
    $scheduler->fetch_due_feeds();
    $scheduler->process_auto_rewrite();

    echo 'OK: ' . current_time('mysql');
    exit;
});
```

### 4.5 AJAX 핸들러

**AjaxHandler.php에 추가:**

```php
/**
 * Cron 상태 조회
 */
public function get_cron_status(): void {
    check_ajax_referer('aicr_ajax', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $monitor = new \AIContentRewriter\Cron\CronMonitor();
    wp_send_json_success($monitor->get_health_status());
}

/**
 * Cron 작업 수동 실행
 */
public function run_cron_task(): void {
    check_ajax_referer('aicr_ajax', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $task = sanitize_text_field($_POST['task'] ?? '');
    $scheduler = new \AIContentRewriter\RSS\FeedScheduler();

    if ($scheduler->run_now($task)) {
        wp_send_json_success([
            'message' => sprintf(__('%s 작업이 실행되었습니다.', 'ai-content-rewriter'), $task)
        ]);
    } else {
        wp_send_json_error(['message' => __('알 수 없는 작업입니다.', 'ai-content-rewriter')]);
    }
}

/**
 * Cron 로그 조회
 */
public function get_cron_logs(): void {
    check_ajax_referer('aicr_ajax', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $hours = (int)($_POST['hours'] ?? 24);
    $logger = new \AIContentRewriter\Cron\CronLogger();
    wp_send_json_success($logger->get_recent($hours));
}

/**
 * Cron 로그 삭제
 */
public function clear_cron_logs(): void {
    check_ajax_referer('aicr_ajax', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $logger = new \AIContentRewriter\Cron\CronLogger();
    $deleted = $logger->cleanup(0); // 모든 로그 삭제
    wp_send_json_success(['deleted' => $deleted]);
}

/**
 * Cron 토큰 재생성
 */
public function regenerate_cron_token(): void {
    check_ajax_referer('aicr_ajax', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $monitor = new \AIContentRewriter\Cron\CronMonitor();
    $new_token = $monitor->regenerate_token();
    wp_send_json_success([
        'urls' => $monitor->get_cron_urls(),
        'message' => __('토큰이 재생성되었습니다.', 'ai-content-rewriter'),
    ]);
}
```

---

## 5. UI 설계

### 5.1 자동화 탭 구조

```
┌─────────────────────────────────────────────────────────┐
│ 자동화 설정                                              │
├─────────────────────────────────────────────────────────┤
│                                                          │
│ [섹션 1: Cron 상태 대시보드]                              │
│ ┌──────────────────────────────────────────────────────┐│
│ │ 상태: 🟢 정상 / 🟡 경고 / 🔴 위험                      ││
│ │                                                      ││
│ │ ┌────────────┐ ┌────────────┐ ┌────────────┐        ││
│ │ │ 피드 갱신   │ │ 자동 재작성 │ │ 정리 작업   │        ││
│ │ │ ⏱️ 15분    │ │ ⏱️ 30분    │ │ ⏱️ 매일    │        ││
│ │ │ 다음: 12:45│ │ 다음: 12:30│ │ 다음: 00:00│        ││
│ │ │ 이전: ✅   │ │ 이전: ✅   │ │ 이전: ✅   │        ││
│ │ │ [▶ 실행]  │ │ [▶ 실행]  │ │ [▶ 실행]  │        ││
│ │ └────────────┘ └────────────┘ └────────────┘        ││
│ └──────────────────────────────────────────────────────┘│
│                                                          │
│ [섹션 2: 권고사항]                                        │
│ ┌──────────────────────────────────────────────────────┐│
│ │ ⚠️ WP-Cron은 사이트 방문에 의존합니다.                 ││
│ │    안정적 자동화를 위해 외부 Cron을 설정하세요.         ││
│ └──────────────────────────────────────────────────────┘│
│                                                          │
│ [섹션 3: 외부 Cron 설정 가이드]                           │
│ ┌──────────────────────────────────────────────────────┐│
│ │ [cPanel] [EasyCron] [Cron-Job.org] [수동]            ││
│ │                                                      ││
│ │ 📋 Cron URL:                                         ││
│ │ ┌────────────────────────────────────────┐ [복사]    ││
│ │ │ https://yoursite.com/?aicr_cron=1&... │           ││
│ │ └────────────────────────────────────────┘           ││
│ │                                                      ││
│ │ 권장 실행 주기: 5분마다                               ││
│ │ [🔄 토큰 재생성]                                      ││
│ └──────────────────────────────────────────────────────┘│
│                                                          │
│ [섹션 4: 실행 이력]                                       │
│ ┌──────────────────────────────────────────────────────┐│
│ │ 최근 24시간 (실시간 갱신)                              ││
│ │                                                      ││
│ │ 시간       │ 작업       │ 상태 │ 처리 │ 소요시간     ││
│ │ ──────────┼────────────┼──────┼──────┼────────────  ││
│ │ 12:30:45  │ 피드 갱신  │ ✅   │ 15   │ 2.3초        ││
│ │ 12:00:00  │ 자동 재작성│ ✅   │ 5    │ 45.2초       ││
│ │ 11:45:30  │ 피드 갱신  │ ✅   │ 8    │ 1.8초        ││
│ │                                                      ││
│ │                        [로그 삭제] [새로고침]         ││
│ └──────────────────────────────────────────────────────┘│
│                                                          │
└─────────────────────────────────────────────────────────┘
```

### 5.2 외부 Cron 설정 가이드 내용

**cPanel 탭:**
```
1. cPanel 대시보드에 로그인합니다
2. "Cron Jobs" (고급 섹션)으로 이동합니다
3. "Add New Cron Job"에서:
   - 주기 선택: "Once Per 5 Minutes" (*/5 * * * *)
   - 명령어 입력:
     wget -q -O /dev/null "YOUR_CRON_URL" >/dev/null 2>&1

   또는 curl 사용:
     curl -s "YOUR_CRON_URL" >/dev/null 2>&1

4. "Add New Cron Job" 클릭
```

**EasyCron 탭:**
```
1. https://www.easycron.com 에서 무료 계정 생성
2. "Create New Cron Job" 클릭
3. 설정:
   - URL: YOUR_CRON_URL (위에서 복사)
   - When to execute: Every 5 minutes
   - HTTP Method: GET
   - Timeout: 300 seconds
4. 저장

무료 플랜: 월 200회 실행 (충분함)
```

**Cron-Job.org 탭:**
```
1. https://cron-job.org 에서 무료 계정 생성
2. "Create cronjob" 클릭
3. 설정:
   - Title: AI Content Rewriter
   - URL: YOUR_CRON_URL
   - Schedule: Every 5 minutes
4. 생성

무료 플랜: 무제한 Cron Job
```

**wp-config.php 설정 (권장):**
```php
// wp-config.php 파일의 상단에 추가
define('DISABLE_WP_CRON', true);

// 이 설정은 WP-Cron의 자동 실행을 비활성화하고
// 외부 Cron에 의해서만 실행되도록 합니다.
```

---

## 6. wp_options 추가 항목

| 옵션 키 | 기본값 | 설명 |
|--------|-------|------|
| `aicr_cron_secret_token` | (자동 생성) | 외부 Cron 인증용 보안 토큰 |
| `aicr_external_cron_confirmed` | `false` | 외부 Cron 호출 확인 여부 |

---

## 7. 구현 순서 및 체크리스트

### Phase 1: 백엔드 인프라
- [ ] `Schema.php`에 `aicr_cron_logs` 테이블 정의 추가
- [ ] `src/Cron/CronLogger.php` 생성
- [ ] `src/Cron/CronMonitor.php` 생성
- [ ] `FeedScheduler.php`에 로깅 연동 추가

### Phase 2: AJAX 엔드포인트
- [ ] `AjaxHandler.php`에 Cron 관련 AJAX 핸들러 추가
  - [ ] `aicr_get_cron_status`
  - [ ] `aicr_run_cron_task`
  - [ ] `aicr_get_cron_logs`
  - [ ] `aicr_clear_cron_logs`
  - [ ] `aicr_regenerate_cron_token`
- [ ] `Plugin.php`에 외부 Cron 엔드포인트 추가

### Phase 3: UI 구현
- [ ] `settings.php`에 자동화 탭 네비게이션 추가
- [ ] `src/Admin/views/automation.php` 생성
- [ ] `admin.js`에 AICR_Automation 모듈 추가
- [ ] `admin.css`에 대시보드 스타일 추가

### Phase 4: 테스트
- [ ] 수동 실행 버튼 테스트
- [ ] 외부 Cron 엔드포인트 테스트
- [ ] 로그 기록 및 조회 테스트
- [ ] 상태 모니터링 테스트
- [ ] E2E 테스트 (Playwright)

---

## 8. 버전 정보

- **현재 버전**: v1.1.0
- **목표 버전**: v1.2.0
- **변경 유형**: 기능 추가 (마이너 버전 업)

---

*최종 수정: 2026-01-07*
