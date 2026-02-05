# Part 4: 플러그인 개발 명세서

## 4.1 플러그인 기본 정보

### 4.1.1 플러그인 헤더

```php
<?php
/**
 * Plugin Name:       AI Content Rewriter
 * Plugin URI:        https://example.com/wp-ai-rewriter
 * Description:       URL 또는 텍스트를 AI로 재작성하여 블로그 게시글을 자동 생성하는 플러그인
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Developer Name
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-ai-rewriter
 * Domain Path:       /languages
 */
```

### 4.1.2 플러그인 상수

```php
<?php
// 플러그인 버전
define('AIR_VERSION', '1.0.0');

// 플러그인 경로
define('AIR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AIR_PLUGIN_BASENAME', plugin_basename(__FILE__));

// 최소 요구사항
define('AIR_MIN_WP_VERSION', '6.0');
define('AIR_MIN_PHP_VERSION', '8.0');

// 데이터베이스 테이블 접두사
define('AIR_TABLE_PREFIX', 'air_');
```

## 4.2 관리자 페이지 구조

### 4.2.1 메뉴 구조

```
AI Rewriter (메인 메뉴)
├── 콘텐츠 재작성     # 수동 재작성 기능
├── 스케줄러         # 자동화 스케줄 관리
├── 히스토리         # 작업 이력 조회
└── 설정            # 플러그인 설정
```

### 4.2.2 메뉴 등록 코드

```php
<?php
class Admin {
    public function register_menus(): void {
        // 메인 메뉴
        add_menu_page(
            __('AI Content Rewriter', 'wp-ai-rewriter'),
            __('AI Rewriter', 'wp-ai-rewriter'),
            'air_use_rewriter',
            'air-rewriter',
            [$this, 'render_rewriter_page'],
            'dashicons-edit-large',
            30
        );

        // 서브메뉴: 콘텐츠 재작성
        add_submenu_page(
            'air-rewriter',
            __('콘텐츠 재작성', 'wp-ai-rewriter'),
            __('콘텐츠 재작성', 'wp-ai-rewriter'),
            'air_use_rewriter',
            'air-rewriter',
            [$this, 'render_rewriter_page']
        );

        // 서브메뉴: 스케줄러
        add_submenu_page(
            'air-rewriter',
            __('스케줄러', 'wp-ai-rewriter'),
            __('스케줄러', 'wp-ai-rewriter'),
            'air_manage_schedules',
            'air-scheduler',
            [$this, 'render_scheduler_page']
        );

        // 서브메뉴: 히스토리
        add_submenu_page(
            'air-rewriter',
            __('히스토리', 'wp-ai-rewriter'),
            __('히스토리', 'wp-ai-rewriter'),
            'air_view_history',
            'air-history',
            [$this, 'render_history_page']
        );

        // 서브메뉴: 설정
        add_submenu_page(
            'air-rewriter',
            __('설정', 'wp-ai-rewriter'),
            __('설정', 'wp-ai-rewriter'),
            'air_manage_settings',
            'air-settings',
            [$this, 'render_settings_page']
        );
    }
}
```

## 4.3 콘텐츠 재작성 페이지

### 4.3.1 UI 구성요소

```
┌─────────────────────────────────────────────────────────────────────┐
│  AI Content Rewriter - 콘텐츠 재작성                                  │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │ 입력 소스 선택:  ○ URL  ○ 텍스트 직접 입력                    │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                      │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │ URL 입력:                                                    │   │
│  │ [https://example.com/article                              ]  │   │
│  │                                           [URL 확인] 버튼    │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                      │
│  ┌─ 추출된 콘텐츠 미리보기 ─────────────────────────────────────┐   │
│  │ 제목: Original Article Title                                 │   │
│  │ 글자수: 3,456자 | 예상 토큰: ~1,200                           │   │
│  │ ─────────────────────────────────────────────────────────── │   │
│  │ Lorem ipsum dolor sit amet, consectetur adipiscing elit...  │   │
│  │                                                              │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                      │
│  ┌─ 재작성 옵션 ────────────────────────────────────────────────┐   │
│  │                                                              │   │
│  │ AI 모델:     [ChatGPT-5     ▼]                               │   │
│  │ 출력 언어:   [한국어        ▼]                               │   │
│  │ 프롬프트:    [기본 재작성    ▼]  [커스텀 프롬프트 편집]       │   │
│  │                                                              │   │
│  │ ┌─ 커스텀 지시사항 (선택) ─────────────────────────────────┐ │   │
│  │ │                                                          │ │   │
│  │ │ [자유 형식으로 추가 지시사항 입력...                    ]│ │   │
│  │ │                                                          │ │   │
│  │ └──────────────────────────────────────────────────────────┘ │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                      │
│  ┌─ 게시글 설정 ────────────────────────────────────────────────┐   │
│  │                                                              │   │
│  │ 카테고리:    [미분류        ▼]                               │   │
│  │ 상태:        ○ 초안  ○ 검토 대기  ○ 즉시 발행                │   │
│  │ 태그 자동생성: [✓]                                           │   │
│  │ SEO 메타 자동생성: [✓]                                       │   │
│  │                                                              │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                      │
│                    [미리보기]  [재작성 실행]                         │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

### 4.3.2 재작성 워크플로우

```php
<?php
class RewriterController {
    /**
     * 재작성 요청 처리
     */
    public function handle_rewrite_request(array $data): RewriteResult {
        // 1. 입력 검증
        $validated = $this->validate_input($data);

        // 2. 콘텐츠 추출 (URL인 경우)
        if ($data['source_type'] === 'url') {
            $content = $this->contentExtractor->fetch($data['source_url']);
        } else {
            $content = $data['source_text'];
        }

        // 3. 콘텐츠 길이 확인 및 청킹
        if ($this->tokenCalculator->count($content) > 3000) {
            $chunks = $this->contentChunker->chunk($content);
            $results = [];
            foreach ($chunks as $chunk) {
                $results[] = $this->processChunk($chunk, $data);
            }
            $rewritten = $this->contentChunker->merge($results);
        } else {
            $rewritten = $this->aiManager->rewrite($content, $data);
        }

        // 4. 메타데이터 생성
        $metadata = $this->metaGenerator->generate($rewritten, $data);

        // 5. 게시글 생성
        $postId = $this->postCreator->create($rewritten, $metadata, $data);

        // 6. 히스토리 기록
        $this->historyLogger->log($data, $postId, $rewritten);

        return new RewriteResult($postId, $rewritten, $metadata);
    }
}
```

## 4.4 스케줄러 페이지

### 4.4.1 UI 구성요소

```
┌─────────────────────────────────────────────────────────────────────┐
│  AI Content Rewriter - 스케줄러                                      │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  [+ 새 스케줄 추가]                                                  │
│                                                                      │
│  ┌─ 등록된 스케줄 목록 ─────────────────────────────────────────┐   │
│  │                                                              │   │
│  │  ☐  │ 이름          │ 소스      │ 주기    │ 다음 실행      │ 상태 │
│  │  ───┼───────────────┼───────────┼─────────┼───────────────┼──── │
│  │  ☐  │ 기술 뉴스     │ RSS 피드  │ 매일    │ 2025-12-29 09:00│ 활성│
│  │  ☐  │ 마케팅 블로그 │ URL 목록  │ 매주 월 │ 2025-12-30 10:00│ 활성│
│  │  ☐  │ 경쟁사 분석   │ 단일 URL  │ 1회     │ 완료           │ 완료│
│  │                                                              │   │
│  │  선택한 항목: [편집] [삭제] [즉시 실행] [활성화/비활성화]     │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

### 4.4.2 스케줄 등록 모달

```
┌─────────────────────────────────────────────────────────────────────┐
│  새 스케줄 등록                                               [X]   │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  스케줄 이름: [                                              ]      │
│                                                                      │
│  소스 유형:   ○ 단일 URL  ○ URL 목록  ○ RSS 피드                   │
│                                                                      │
│  소스 데이터:                                                        │
│  [                                                              ]    │
│  [                                                              ]    │
│  [+ URL 추가]                                                        │
│                                                                      │
│  ─────────────────────────────────────────────────────────────────  │
│                                                                      │
│  실행 주기:   ○ 1회만  ○ 매일  ○ 매주  ○ 사용자 정의               │
│                                                                      │
│  실행 시간:   [09] : [00]                                            │
│  요일 선택:   ☐월 ☐화 ☐수 ☐목 ☐금 ☐토 ☐일  (매주 선택시)         │
│                                                                      │
│  ─────────────────────────────────────────────────────────────────  │
│                                                                      │
│  AI 모델:     [ChatGPT-5     ▼]                                      │
│  출력 언어:   [한국어        ▼]                                      │
│  프롬프트:    [기본 재작성    ▼]                                     │
│                                                                      │
│  게시글 상태: ○ 초안  ○ 검토 대기  ○ 즉시 발행                      │
│  카테고리:    [미분류        ▼]                                      │
│                                                                      │
│                              [취소]  [스케줄 저장]                   │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

### 4.4.3 스케줄러 클래스

```php
<?php
class SchedulerManager {
    /**
     * 스케줄 등록
     */
    public function create_schedule(array $data): int {
        global $wpdb;

        $schedule = [
            'name'            => sanitize_text_field($data['name']),
            'source_type'     => $data['source_type'],
            'source_data'     => wp_json_encode($data['source_data']),
            'ai_model'        => $data['ai_model'],
            'prompt_template' => $data['prompt_template'],
            'target_language' => $data['target_language'],
            'post_status'     => $data['post_status'],
            'post_category'   => absint($data['post_category']),
            'schedule_type'   => $data['schedule_type'],
            'schedule_time'   => $data['schedule_time'],
            'schedule_days'   => implode(',', $data['schedule_days'] ?? []),
            'is_active'       => 1,
            'next_run'        => $this->calculate_next_run($data),
        ];

        $wpdb->insert(
            $wpdb->prefix . AIR_TABLE_PREFIX . 'schedules',
            $schedule
        );

        $schedule_id = $wpdb->insert_id;

        // WordPress Cron 등록
        $this->schedule_cron_event($schedule_id, $schedule);

        return $schedule_id;
    }

    /**
     * 크론 이벤트 등록
     */
    private function schedule_cron_event(int $id, array $schedule): void {
        $timestamp = strtotime($schedule['next_run']);
        $hook = 'air_run_schedule_' . $id;

        if (!wp_next_scheduled($hook)) {
            wp_schedule_single_event($timestamp, $hook, ['schedule_id' => $id]);
        }
    }

    /**
     * 스케줄 실행
     */
    public function execute_schedule(int $schedule_id): void {
        $schedule = $this->get_schedule($schedule_id);

        if (!$schedule || !$schedule->is_active) {
            return;
        }

        $sources = json_decode($schedule->source_data, true);

        foreach ($sources as $source) {
            try {
                $this->rewriterController->handle_rewrite_request([
                    'source_type'     => $schedule->source_type,
                    'source_url'      => $source,
                    'ai_model'        => $schedule->ai_model,
                    'prompt_template' => $schedule->prompt_template,
                    'target_language' => $schedule->target_language,
                    'post_status'     => $schedule->post_status,
                    'post_category'   => $schedule->post_category,
                ]);
            } catch (Exception $e) {
                $this->log_error($schedule_id, $source, $e);
            }
        }

        // 다음 실행 시간 업데이트
        $this->update_next_run($schedule_id);
    }
}
```

## 4.5 설정 페이지

### 4.5.1 설정 탭 구조

```
┌─────────────────────────────────────────────────────────────────────┐
│  AI Content Rewriter - 설정                                          │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  [API 설정] [기본값] [프롬프트 템플릿] [고급 설정]                    │
│                                                                      │
│  ═══════════════════════════════════════════════════════════════════│
│                                                                      │
│  ┌─ API 설정 ───────────────────────────────────────────────────┐   │
│  │                                                              │   │
│  │  OpenAI API 키:                                              │   │
│  │  [sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx    ] [확인]    │   │
│  │  상태: ✓ 연결됨 | 잔여 크레딧: $45.23                         │   │
│  │                                                              │   │
│  │  ─────────────────────────────────────────────────────────  │   │
│  │                                                              │   │
│  │  Google Gemini API 키:                                       │   │
│  │  [AIzaxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx        ] [확인]    │   │
│  │  상태: ✓ 연결됨                                              │   │
│  │                                                              │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                      │
│                                           [변경사항 저장]            │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

### 4.5.2 기본값 설정 탭

```
┌─ 기본값 설정 ───────────────────────────────────────────────────────┐
│                                                                      │
│  기본 AI 모델:         [ChatGPT-5     ▼]                             │
│  기본 출력 언어:       [한국어        ▼]                             │
│  기본 프롬프트 템플릿: [기본 재작성    ▼]                            │
│  기본 게시글 상태:     [초안          ▼]                             │
│  기본 카테고리:        [미분류        ▼]                             │
│                                                                      │
│  ─────────────────────────────────────────────────────────────────  │
│                                                                      │
│  자동 태그 생성:       [✓] 활성화                                    │
│  자동 SEO 메타 생성:   [✓] 활성화                                    │
│  출처 표기:            [✓] 원본 URL을 게시글에 포함                  │
│                                                                      │
│  ─────────────────────────────────────────────────────────────────  │
│                                                                      │
│  최대 토큰 (응답):     [4000       ]                                 │
│  청크 크기 (입력):     [3000       ] 토큰                            │
│  API 타임아웃:         [120        ] 초                              │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

### 4.5.3 프롬프트 템플릿 관리 탭

```
┌─ 프롬프트 템플릿 관리 ──────────────────────────────────────────────┐
│                                                                      │
│  [+ 새 템플릿 추가]                                                  │
│                                                                      │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │ 템플릿 이름    │ 유형      │ 기본값 │ 작업                    │   │
│  │ ──────────────┼───────────┼────────┼─────────────────────── │   │
│  │ 기본 재작성   │ 재작성    │  ★     │ [보기] [편집] [복제]   │   │
│  │ 번역 및 재구성│ 번역      │        │ [보기] [편집] [복제]   │   │
│  │ SEO 최적화    │ SEO       │        │ [보기] [편집] [복제]   │   │
│  │ 요약형        │ 재작성    │        │ [보기] [편집] [삭제]   │   │
│  └──────────────────────────────────────────────────────────────┘   │
│                                                                      │
│  ─────────────────────────────────────────────────────────────────  │
│                                                                      │
│  선택된 템플릿: [기본 재작성]                                        │
│                                                                      │
│  시스템 프롬프트:                                                    │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │ 당신은 전문 블로그 콘텐츠 작성자입니다. 주어진 콘텐츠를      │   │
│  │ 분석하고, 독창적이고 매력적인 블로그 게시글로 재구성합니다.  │   │
│  │ ...                                                          │   │
│  └──────────────────────────────────────────────────────────────┘   │
│                                                                      │
│  사용자 프롬프트 템플릿:                                             │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │ 다음 콘텐츠를 {{target_language}}로 재작성해주세요:          │   │
│  │                                                              │   │
│  │ {{content}}                                                  │   │
│  │                                                              │   │
│  │ 요구사항:                                                    │   │
│  │ - 원본의 핵심 정보를 유지하면서 새로운 관점으로 작성         │   │
│  │ - 자연스러운 문체 사용                                       │   │
│  │ - SEO 친화적인 구조                                          │   │
│  └──────────────────────────────────────────────────────────────┘   │
│                                                                      │
│  사용 가능한 변수: {{content}}, {{target_language}}, {{title}},      │
│                   {{custom_instructions}}                            │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

### 4.5.4 설정 저장 클래스

```php
<?php
class SettingsManager {
    private const OPTION_GROUP = 'air_settings';

    /**
     * 설정 필드 정의
     */
    private array $settings = [
        'api' => [
            'air_openai_api_key'  => ['type' => 'encrypted', 'default' => ''],
            'air_gemini_api_key'  => ['type' => 'encrypted', 'default' => ''],
        ],
        'defaults' => [
            'air_default_model'     => ['type' => 'string', 'default' => 'chatgpt-5'],
            'air_default_language'  => ['type' => 'string', 'default' => 'ko'],
            'air_default_prompt'    => ['type' => 'string', 'default' => 'rewrite-default'],
            'air_default_status'    => ['type' => 'string', 'default' => 'draft'],
            'air_default_category'  => ['type' => 'integer', 'default' => 1],
            'air_auto_tags'         => ['type' => 'boolean', 'default' => true],
            'air_auto_seo'          => ['type' => 'boolean', 'default' => true],
            'air_include_source'    => ['type' => 'boolean', 'default' => true],
        ],
        'advanced' => [
            'air_max_tokens'        => ['type' => 'integer', 'default' => 4000],
            'air_chunk_size'        => ['type' => 'integer', 'default' => 3000],
            'air_api_timeout'       => ['type' => 'integer', 'default' => 120],
            'air_enable_logging'    => ['type' => 'boolean', 'default' => true],
        ],
    ];

    /**
     * 설정 등록
     */
    public function register_settings(): void {
        foreach ($this->settings as $section => $fields) {
            foreach ($fields as $name => $config) {
                register_setting(
                    self::OPTION_GROUP,
                    $name,
                    [
                        'type'              => $this->map_type($config['type']),
                        'sanitize_callback' => [$this, 'sanitize_' . $config['type']],
                        'default'           => $config['default'],
                    ]
                );
            }
        }
    }

    /**
     * API 키 검증
     */
    public function validate_api_key(string $provider, string $key): array {
        $adapter = $this->aiManager->getAdapter($provider);

        try {
            $valid = $adapter->validateKey($key);
            return ['success' => true, 'message' => '연결 성공'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
```

## 4.6 히스토리 페이지

### 4.6.1 UI 구성요소

```
┌─────────────────────────────────────────────────────────────────────┐
│  AI Content Rewriter - 작업 히스토리                                 │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  필터: [모든 상태 ▼] [모든 모델 ▼] [기간: 전체 ▼]  [검색...]        │
│                                                                      │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │ 날짜시간          │ 소스         │ 모델     │ 상태  │ 게시글 │   │
│  │ ──────────────────┼──────────────┼──────────┼───────┼─────── │   │
│  │ 2025-12-28 14:30  │ example.com  │ GPT-5    │ ✓완료 │ [보기] │   │
│  │ 2025-12-28 14:25  │ news.com     │ Gemini-3 │ ✓완료 │ [보기] │   │
│  │ 2025-12-28 14:20  │ blog.com     │ GPT-5    │ ✗실패 │ -      │   │
│  │ 2025-12-28 14:15  │ (텍스트)     │ GPT-5    │ ✓완료 │ [보기] │   │
│  └──────────────────────────────────────────────────────────────┘   │
│                                                                      │
│  페이지: [◀ 이전] 1 2 3 ... 10 [다음 ▶]                              │
│                                                                      │
│  ─────────────────────────────────────────────────────────────────  │
│                                                                      │
│  통계:  총 처리: 156건 | 성공: 149건(95.5%) | 실패: 7건              │
│        총 토큰 사용: 234,567 | 예상 비용: $4.69                      │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

## 4.7 REST API 엔드포인트

### 4.7.1 API 등록

```php
<?php
class RestApiController {
    private const NAMESPACE = 'wp-ai-rewriter/v1';

    public function register_routes(): void {
        // 콘텐츠 재작성
        register_rest_route(self::NAMESPACE, '/rewrite', [
            'methods'             => 'POST',
            'callback'            => [$this, 'rewrite_content'],
            'permission_callback' => [$this, 'check_permission'],
            'args'                => $this->get_rewrite_args(),
        ]);

        // URL 콘텐츠 추출
        register_rest_route(self::NAMESPACE, '/extract', [
            'methods'             => 'POST',
            'callback'            => [$this, 'extract_content'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // 스케줄 CRUD
        register_rest_route(self::NAMESPACE, '/schedules', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_schedules'],
            'permission_callback' => [$this, 'check_schedule_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/schedules', [
            'methods'             => 'POST',
            'callback'            => [$this, 'create_schedule'],
            'permission_callback' => [$this, 'check_schedule_permission'],
        ]);

        // 히스토리 조회
        register_rest_route(self::NAMESPACE, '/history', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_history'],
            'permission_callback' => [$this, 'check_history_permission'],
        ]);

        // API 키 검증
        register_rest_route(self::NAMESPACE, '/validate-key', [
            'methods'             => 'POST',
            'callback'            => [$this, 'validate_api_key'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);
    }
}
```

### 4.7.2 API 응답 형식

```json
// 성공 응답
{
    "success": true,
    "data": {
        "post_id": 123,
        "title": "재작성된 제목",
        "content": "재작성된 콘텐츠...",
        "metadata": {
            "tags": ["태그1", "태그2"],
            "meta_description": "SEO 설명...",
            "tokens_used": 1234
        }
    }
}

// 실패 응답
{
    "success": false,
    "error": {
        "code": "CONTENT_TOO_LONG",
        "message": "콘텐츠가 처리 가능한 최대 길이를 초과했습니다.",
        "details": {
            "max_tokens": 4000,
            "received_tokens": 5500
        }
    }
}
```

---
*문서 버전: 1.0*
*작성일: 2025-12-28*
*이전 문서: [03-ENVIRONMENT-SETUP.md](./03-ENVIRONMENT-SETUP.md)*
*다음 문서: [05-AI-INTEGRATION.md](./05-AI-INTEGRATION.md)*
