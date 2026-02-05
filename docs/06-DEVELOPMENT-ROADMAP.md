# Part 6: 개발 로드맵 및 마일스톤

> **작업 추적**: 상세 작업 진행 상황은 [TASKS.md](../TASKS.md)에서 관리됩니다.
> **작업 가이드**: 작업 수행 규칙은 [CLAUDE.md](../CLAUDE.md)를 참조하세요.

## 6.1 개발 단계 개요

```
┌─────────────────────────────────────────────────────────────────────┐
│                         개발 로드맵                                   │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  Phase 1          Phase 2          Phase 3          Phase 4         │
│  ┌─────────┐      ┌─────────┐      ┌─────────┐      ┌─────────┐    │
│  │ 환경 및  │ ──▶ │ 핵심    │ ──▶ │ 고급    │ ──▶ │ 최적화  │    │
│  │ 기반    │      │ 기능    │      │ 기능    │      │ 및 배포 │    │
│  └─────────┘      └─────────┘      └─────────┘      └─────────┘    │
│                                                                      │
│  - 환경 구축       - URL 추출       - 스케줄러       - 성능 최적화   │
│  - 플러그인 골격   - AI 통합        - 배치 처리      - 보안 강화     │
│  - DB 스키마       - 재작성 UI      - 히스토리       - 테스트        │
│  - 기본 설정       - 게시글 생성    - 알림 시스템    - 문서화        │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

## 6.2 Phase 1: 환경 구축 및 기반 작업

### 6.2.1 작업 목록

| # | 작업 | 설명 | 상태 |
|---|------|------|------|
| 1.1 | 로컬 WordPress 설치 | Homebrew로 PHP, MySQL, Nginx 설치 및 WordPress 구성 | 대기 |
| 1.2 | 개발 도구 설정 | VS Code, Xdebug, Query Monitor 설정 | 대기 |
| 1.3 | 플러그인 기본 구조 | 디렉토리 구조, 메인 파일, 활성화/비활성화 훅 | 대기 |
| 1.4 | 오토로더 구현 | PSR-4 오토로딩 (Composer 또는 커스텀) | 대기 |
| 1.5 | 데이터베이스 스키마 | 커스텀 테이블 생성 (히스토리, 스케줄, 프롬프트) | 대기 |
| 1.6 | 설정 프레임워크 | 옵션 등록, 암호화, 기본값 설정 | 대기 |
| 1.7 | 관리자 메뉴 등록 | 메뉴 구조 및 페이지 라우팅 | 대기 |

### 6.2.2 상세 작업 내용

#### 1.1 로컬 WordPress 설치

```bash
# 실행할 명령어 순서
brew update
brew install php@8.2 mysql nginx wp-cli

brew services start mysql
brew services start nginx
brew services start php@8.2

# MySQL 설정
mysql -u root -p
CREATE DATABASE wp_ai_rewriter CHARACTER SET utf8mb4;
CREATE USER 'wp_dev'@'localhost' IDENTIFIED BY 'dev_password_2025';
GRANT ALL PRIVILEGES ON wp_ai_rewriter.* TO 'wp_dev'@'localhost';

# Nginx 설정
# /opt/homebrew/etc/nginx/servers/wordpress-dev.conf 생성

# WordPress 설치
cd /Users/hansync/Dropbox/Project2025-dev/wordpress
wp core download --locale=ko_KR
wp config create --dbname=wp_ai_rewriter --dbuser=wp_dev --dbpass=dev_password_2025
wp core install --url=http://localhost:8080 --title="AI Rewriter Dev" --admin_user=admin --admin_password=admin123!
```

#### 1.3 플러그인 기본 구조

```php
<?php
// wp-content/plugins/wp-ai-rewriter/wp-ai-rewriter.php

/**
 * Plugin Name: AI Content Rewriter
 * Version: 1.0.0
 * Requires PHP: 8.0
 */

defined('ABSPATH') || exit;

// 상수 정의
define('AIR_VERSION', '1.0.0');
define('AIR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIR_PLUGIN_URL', plugin_dir_url(__FILE__));

// 오토로더
require_once AIR_PLUGIN_DIR . 'includes/class-autoloader.php';

// 플러그인 초기화
function air_init() {
    $plugin = new \WPAIRewriter\PluginCore();
    $plugin->run();
}
add_action('plugins_loaded', 'air_init');

// 활성화 훅
register_activation_hook(__FILE__, ['WPAIRewriter\Activator', 'activate']);

// 비활성화 훅
register_deactivation_hook(__FILE__, ['WPAIRewriter\Deactivator', 'deactivate']);
```

### 6.2.3 완료 기준

- [ ] WordPress가 http://localhost:8080 에서 정상 동작
- [ ] 관리자 로그인 및 기본 설정 완료
- [ ] 플러그인 활성화 시 오류 없음
- [ ] 커스텀 DB 테이블 생성 확인
- [ ] 관리자 메뉴에 "AI Rewriter" 표시

---

## 6.3 Phase 2: 핵심 기능 개발

### 6.3.1 작업 목록

| # | 작업 | 설명 | 상태 |
|---|------|------|------|
| 2.1 | URL 콘텐츠 추출기 | URL에서 본문 콘텐츠 추출 | 대기 |
| 2.2 | ChatGPT 어댑터 | OpenAI API 연동 | 대기 |
| 2.3 | Gemini 어댑터 | Google Gemini API 연동 | 대기 |
| 2.4 | AI 매니저 | 어댑터 관리 및 요청 처리 | 대기 |
| 2.5 | 프롬프트 시스템 | 템플릿 로드 및 변수 치환 | 대기 |
| 2.6 | 재작성 UI | 관리자 재작성 페이지 | 대기 |
| 2.7 | 게시글 생성기 | WordPress 게시글 생성 | 대기 |
| 2.8 | 메타데이터 생성 | 제목, 태그, 설명 자동 생성 | 대기 |
| 2.9 | 설정 페이지 | API 키, 기본값 설정 UI | 대기 |

### 6.3.2 상세 작업 내용

#### 2.1 URL 콘텐츠 추출기

```php
<?php
namespace WPAIRewriter\Content;

class UrlFetcher {
    public function fetch(string $url): FetchResult {
        // 1. URL 유효성 검사
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidUrlException("유효하지 않은 URL: {$url}");
        }

        // 2. HTTP 요청
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'user-agent' => 'WP-AI-Rewriter/1.0',
        ]);

        if (is_wp_error($response)) {
            throw new FetchException($response->get_error_message());
        }

        $html = wp_remote_retrieve_body($response);

        // 3. 콘텐츠 파싱
        $parser = new ContentParser();
        return $parser->parse($html, $url);
    }
}

class ContentParser {
    public function parse(string $html, string $url): ParsedContent {
        // DOM 파싱
        $doc = new \DOMDocument();
        @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($doc);

        // 제목 추출
        $title = $this->extractTitle($xpath);

        // 본문 추출 (article, main, .content 등 우선순위)
        $content = $this->extractMainContent($xpath);

        // 메타 정보 추출
        $meta = $this->extractMeta($xpath);

        return new ParsedContent($title, $content, $meta, $url);
    }
}
```

#### 2.6 재작성 UI

```php
<?php
// admin/partials/rewriter-display.php
?>
<div class="wrap air-rewriter-page">
    <h1><?php esc_html_e('콘텐츠 재작성', 'wp-ai-rewriter'); ?></h1>

    <form id="air-rewrite-form" method="post">
        <?php wp_nonce_field('air_rewrite_action', 'air_nonce'); ?>

        <!-- 입력 소스 선택 -->
        <div class="air-source-selector">
            <label>
                <input type="radio" name="source_type" value="url" checked>
                <?php esc_html_e('URL', 'wp-ai-rewriter'); ?>
            </label>
            <label>
                <input type="radio" name="source_type" value="text">
                <?php esc_html_e('텍스트 직접 입력', 'wp-ai-rewriter'); ?>
            </label>
        </div>

        <!-- URL 입력 -->
        <div class="air-url-input" id="url-input-section">
            <input type="url" name="source_url" placeholder="https://example.com/article">
            <button type="button" id="fetch-content-btn" class="button">
                <?php esc_html_e('URL 확인', 'wp-ai-rewriter'); ?>
            </button>
        </div>

        <!-- 텍스트 입력 -->
        <div class="air-text-input hidden" id="text-input-section">
            <textarea name="source_text" rows="10" placeholder="재작성할 텍스트를 입력하세요..."></textarea>
        </div>

        <!-- 미리보기 -->
        <div class="air-preview" id="content-preview" style="display:none;">
            <h3><?php esc_html_e('추출된 콘텐츠', 'wp-ai-rewriter'); ?></h3>
            <div class="preview-content"></div>
            <div class="preview-stats"></div>
        </div>

        <!-- 재작성 옵션 -->
        <div class="air-options">
            <div class="option-row">
                <label for="ai_model"><?php esc_html_e('AI 모델', 'wp-ai-rewriter'); ?></label>
                <select name="ai_model" id="ai_model">
                    <option value="chatgpt-5">ChatGPT-5</option>
                    <option value="gemini-3">Gemini-3</option>
                </select>
            </div>

            <div class="option-row">
                <label for="target_language"><?php esc_html_e('출력 언어', 'wp-ai-rewriter'); ?></label>
                <select name="target_language" id="target_language">
                    <option value="ko">한국어</option>
                    <option value="en">English</option>
                    <option value="ja">日本語</option>
                </select>
            </div>

            <div class="option-row">
                <label for="prompt_template"><?php esc_html_e('프롬프트', 'wp-ai-rewriter'); ?></label>
                <select name="prompt_template" id="prompt_template">
                    <?php foreach ($this->get_templates() as $key => $template): ?>
                        <option value="<?php echo esc_attr($key); ?>">
                            <?php echo esc_html($template['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- 커스텀 지시사항 -->
        <div class="air-custom-instructions">
            <label for="custom_instructions">
                <?php esc_html_e('추가 지시사항 (선택)', 'wp-ai-rewriter'); ?>
            </label>
            <textarea name="custom_instructions" id="custom_instructions" rows="3"></textarea>
        </div>

        <!-- 게시글 설정 -->
        <div class="air-post-settings">
            <div class="option-row">
                <label for="post_category"><?php esc_html_e('카테고리', 'wp-ai-rewriter'); ?></label>
                <?php wp_dropdown_categories([
                    'name' => 'post_category',
                    'hide_empty' => false,
                ]); ?>
            </div>

            <div class="option-row">
                <label><?php esc_html_e('게시 상태', 'wp-ai-rewriter'); ?></label>
                <label><input type="radio" name="post_status" value="draft" checked> 초안</label>
                <label><input type="radio" name="post_status" value="pending"> 검토 대기</label>
                <label><input type="radio" name="post_status" value="publish"> 즉시 발행</label>
            </div>
        </div>

        <!-- 버튼 -->
        <div class="air-actions">
            <button type="button" id="preview-btn" class="button button-secondary">
                <?php esc_html_e('미리보기', 'wp-ai-rewriter'); ?>
            </button>
            <button type="submit" id="rewrite-btn" class="button button-primary">
                <?php esc_html_e('재작성 실행', 'wp-ai-rewriter'); ?>
            </button>
        </div>
    </form>

    <!-- 결과 모달 -->
    <div id="result-modal" class="air-modal" style="display:none;">
        <div class="modal-content">
            <h2><?php esc_html_e('재작성 결과', 'wp-ai-rewriter'); ?></h2>
            <div class="result-preview"></div>
            <div class="modal-actions">
                <button id="save-draft" class="button">초안 저장</button>
                <button id="publish-now" class="button button-primary">발행</button>
            </div>
        </div>
    </div>
</div>
```

### 6.3.3 완료 기준

- [ ] URL 입력 시 콘텐츠 정상 추출
- [ ] ChatGPT-5 API 연동 및 재작성 동작
- [ ] Gemini-3 API 연동 및 재작성 동작
- [ ] 프롬프트 템플릿 적용 확인
- [ ] 게시글 생성 및 메타데이터 저장
- [ ] 설정 페이지에서 API 키 저장/검증

---

## 6.4 Phase 3: 고급 기능 개발

### 6.4.1 작업 목록

| # | 작업 | 설명 | 상태 |
|---|------|------|------|
| 3.1 | 스케줄러 UI | 스케줄 등록/관리 페이지 | 대기 |
| 3.2 | WordPress Cron 통합 | 예약 작업 등록 및 실행 | 대기 |
| 3.3 | 배치 처리 | 다중 URL 순차 처리 | 대기 |
| 3.4 | 긴 콘텐츠 청킹 | 대용량 콘텐츠 분할 처리 | 대기 |
| 3.5 | 히스토리 시스템 | 작업 이력 저장 및 조회 | 대기 |
| 3.6 | 통계 대시보드 | 사용량, 비용, 성공률 표시 | 대기 |
| 3.7 | 알림 시스템 | 이메일/관리자 알림 | 대기 |
| 3.8 | 프롬프트 편집기 | 커스텀 프롬프트 CRUD | 대기 |
| 3.9 | REST API | 외부 연동용 API 엔드포인트 | 대기 |

### 6.4.2 스케줄러 구현

```php
<?php
namespace WPAIRewriter\Scheduler;

class CronManager {
    /**
     * 커스텀 크론 주기 등록
     */
    public function register_schedules(array $schedules): array {
        $schedules['air_every_hour'] = [
            'interval' => HOUR_IN_SECONDS,
            'display'  => __('매시간', 'wp-ai-rewriter'),
        ];

        $schedules['air_twice_daily'] = [
            'interval' => 12 * HOUR_IN_SECONDS,
            'display'  => __('하루 2회', 'wp-ai-rewriter'),
        ];

        return $schedules;
    }

    /**
     * 스케줄 실행 콜백
     */
    public function execute_scheduled_task(int $schedule_id): void {
        $schedule = $this->get_schedule($schedule_id);

        if (!$schedule || !$schedule->is_active) {
            return;
        }

        // 실행 로그 시작
        $this->log_execution_start($schedule_id);

        try {
            $sources = json_decode($schedule->source_data, true);

            foreach ($sources as $source) {
                $result = $this->rewriter->process([
                    'source_type'     => $schedule->source_type,
                    'source_url'      => $source,
                    'ai_model'        => $schedule->ai_model,
                    'prompt_template' => $schedule->prompt_template,
                    'target_language' => $schedule->target_language,
                    'post_status'     => $schedule->post_status,
                    'post_category'   => $schedule->post_category,
                ]);

                $this->log_item_result($schedule_id, $source, $result);
            }

            $this->log_execution_complete($schedule_id, 'success');

            // 다음 실행 스케줄
            $this->schedule_next_run($schedule_id);

        } catch (\Exception $e) {
            $this->log_execution_complete($schedule_id, 'failed', $e->getMessage());
            $this->send_failure_notification($schedule_id, $e);
        }
    }

    /**
     * 다음 실행 시간 계산 및 등록
     */
    private function schedule_next_run(int $schedule_id): void {
        $schedule = $this->get_schedule($schedule_id);

        if ($schedule->schedule_type === 'once') {
            // 1회성 작업은 비활성화
            $this->deactivate_schedule($schedule_id);
            return;
        }

        $next_run = $this->calculate_next_run($schedule);

        // DB 업데이트
        $this->update_next_run($schedule_id, $next_run);

        // WordPress Cron 등록
        wp_schedule_single_event(
            $next_run->getTimestamp(),
            'air_execute_schedule',
            ['schedule_id' => $schedule_id]
        );
    }
}
```

### 6.4.3 완료 기준

- [ ] 스케줄 등록 및 목록 표시
- [ ] 예약된 시간에 자동 실행 확인
- [ ] 다중 URL 배치 처리 동작
- [ ] 10,000자 이상 콘텐츠 청킹 처리
- [ ] 히스토리 목록 및 상세 조회
- [ ] 통계 데이터 정확성 확인

---

## 6.5 Phase 4: 최적화 및 배포 준비

### 6.5.1 작업 목록

| # | 작업 | 설명 | 상태 |
|---|------|------|------|
| 4.1 | 성능 최적화 | 캐싱, 쿼리 최적화 | 대기 |
| 4.2 | 보안 강화 | 입력 검증, 권한 체크 | 대기 |
| 4.3 | 에러 처리 | 예외 처리, 사용자 피드백 | 대기 |
| 4.4 | 단위 테스트 | PHPUnit 테스트 작성 | 대기 |
| 4.5 | 통합 테스트 | E2E 시나리오 테스트 | 대기 |
| 4.6 | 다국어 지원 | POT 파일 생성, 번역 | 대기 |
| 4.7 | 코드 리뷰 | 코딩 표준 점검 | 대기 |
| 4.8 | 문서화 | 사용자 가이드, API 문서 | 대기 |
| 4.9 | 배포 패키징 | ZIP 생성, readme.txt | 대기 |

### 6.5.2 테스트 케이스

```php
<?php
// tests/unit/ContentParserTest.php

use PHPUnit\Framework\TestCase;
use WPAIRewriter\Content\ContentParser;

class ContentParserTest extends TestCase {
    private ContentParser $parser;

    protected function setUp(): void {
        $this->parser = new ContentParser();
    }

    public function test_extracts_title_from_h1(): void {
        $html = '<html><body><h1>테스트 제목</h1><p>본문</p></body></html>';
        $result = $this->parser->parse($html, 'https://example.com');

        $this->assertEquals('테스트 제목', $result->getTitle());
    }

    public function test_extracts_main_content(): void {
        $html = '<html><body><article><p>주요 콘텐츠</p></article><aside>사이드바</aside></body></html>';
        $result = $this->parser->parse($html, 'https://example.com');

        $this->assertStringContainsString('주요 콘텐츠', $result->getContent());
        $this->assertStringNotContainsString('사이드바', $result->getContent());
    }

    public function test_handles_empty_content(): void {
        $html = '<html><body></body></html>';

        $this->expectException(\WPAIRewriter\Exception\EmptyContentException::class);
        $this->parser->parse($html, 'https://example.com');
    }
}
```

### 6.5.3 보안 체크리스트

```php
<?php
// 보안 검증 예시

class SecurityValidator {
    /**
     * 입력값 새니타이징
     */
    public function sanitize_input(array $data): array {
        return [
            'source_url'      => esc_url_raw($data['source_url'] ?? ''),
            'source_text'     => wp_kses_post($data['source_text'] ?? ''),
            'ai_model'        => sanitize_key($data['ai_model'] ?? 'chatgpt-5'),
            'target_language' => sanitize_key($data['target_language'] ?? 'ko'),
            'post_category'   => absint($data['post_category'] ?? 1),
            'custom_instructions' => sanitize_textarea_field($data['custom_instructions'] ?? ''),
        ];
    }

    /**
     * 권한 검증
     */
    public function verify_permissions(string $action): bool {
        $required_caps = [
            'rewrite'  => 'air_use_rewriter',
            'schedule' => 'air_manage_schedules',
            'settings' => 'air_manage_settings',
            'history'  => 'air_view_history',
        ];

        if (!isset($required_caps[$action])) {
            return false;
        }

        return current_user_can($required_caps[$action]);
    }

    /**
     * Nonce 검증
     */
    public function verify_nonce(string $action): bool {
        $nonce = $_REQUEST['air_nonce'] ?? '';
        return wp_verify_nonce($nonce, $action);
    }
}
```

### 6.5.4 완료 기준

- [ ] 모든 단위 테스트 통과 (커버리지 80% 이상)
- [ ] 보안 취약점 스캔 통과
- [ ] WordPress Coding Standards 준수
- [ ] 번역 파일 생성 완료
- [ ] readme.txt 작성 완료
- [ ] 배포용 ZIP 파일 생성

---

## 6.6 마일스톤 요약

| 마일스톤 | 주요 산출물 | 완료 기준 |
|---------|------------|----------|
| **M1: 환경 구축** | 동작하는 WordPress, 플러그인 골격 | 관리자 메뉴 표시 |
| **M2: MVP 완성** | URL → 재작성 → 게시글 파이프라인 | 단일 URL 재작성 성공 |
| **M3: 기능 완성** | 스케줄러, 히스토리, 다중 모델 | 자동화 스케줄 동작 |
| **M4: 배포 준비** | 테스트, 문서, 패키징 | 배포 가능 상태 |

---

## 6.7 기술 부채 관리

### 6.7.1 알려진 제한사항

| 항목 | 현재 상태 | 개선 계획 |
|-----|----------|----------|
| 토큰 계산 | 근사치 사용 | tiktoken 라이브러리 통합 |
| 이미지 처리 | 텍스트만 추출 | 이미지 다운로드/첨부 기능 |
| 캐싱 | 없음 | Transient API 활용 |
| 큐 시스템 | WordPress Cron | Action Scheduler 통합 |

### 6.7.2 향후 확장 계획

1. **추가 AI 모델 지원**
   - Claude (Anthropic)
   - Llama (Meta)
   - 로컬 LLM 연동

2. **고급 기능**
   - 이미지 자동 생성 (DALL-E, Midjourney)
   - 음성 → 텍스트 변환
   - 멀티사이트 지원

3. **통합 기능**
   - Yoast SEO 통합
   - WooCommerce 상품 설명 생성
   - 소셜 미디어 자동 공유

---
*문서 버전: 1.0*
*작성일: 2025-12-28*
*이전 문서: [05-AI-INTEGRATION.md](./05-AI-INTEGRATION.md)*
