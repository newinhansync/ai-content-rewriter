# Part 2: 기술 아키텍처

## 2.1 시스템 아키텍처 개요

```
┌─────────────────────────────────────────────────────────────────┐
│                      WordPress Admin Dashboard                   │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │  URL Input   │  │ Text Input   │  │  Scheduler   │          │
│  │   Module     │  │   Module     │  │   Module     │          │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘          │
│         │                 │                 │                   │
│         └────────────┬────┴─────────────────┘                   │
│                      │                                          │
│              ┌───────▼───────┐                                  │
│              │  Content      │                                  │
│              │  Extractor    │                                  │
│              └───────┬───────┘                                  │
│                      │                                          │
│              ┌───────▼───────┐                                  │
│              │   AI Service  │                                  │
│              │   Manager     │──────┐                           │
│              └───────┬───────┘      │                           │
│                      │              │                           │
│         ┌────────────┼──────────────┼───────────┐              │
│         │            │              │           │              │
│  ┌──────▼─────┐ ┌────▼────┐  ┌─────▼─────┐     │              │
│  │  ChatGPT-5 │ │Gemini-3 │  │  Future   │     │              │
│  │  Adapter   │ │ Adapter │  │  Models   │     │              │
│  └──────┬─────┘ └────┬────┘  └─────┬─────┘     │              │
│         │            │             │            │              │
│         └────────────┼─────────────┘            │              │
│                      │                          │              │
│              ┌───────▼───────┐          ┌──────▼──────┐       │
│              │   Content     │          │   Prompt    │       │
│              │   Processor   │◄─────────│   Manager   │       │
│              └───────┬───────┘          └─────────────┘       │
│                      │                                         │
│              ┌───────▼───────┐                                 │
│              │  Post Creator │                                 │
│              │  & Publisher  │                                 │
│              └───────┬───────┘                                 │
│                      │                                         │
│              ┌───────▼───────┐                                 │
│              │   WordPress   │                                 │
│              │   Database    │                                 │
│              └───────────────┘                                 │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

## 2.2 디렉토리 구조

```
wp-ai-rewriter/
├── wp-ai-rewriter.php              # 메인 플러그인 파일
├── uninstall.php                   # 언인스톨 훅
├── readme.txt                      # WordPress 플러그인 설명
│
├── includes/                       # 핵심 클래스
│   ├── class-plugin-core.php       # 플러그인 코어
│   ├── class-activator.php         # 활성화 로직
│   ├── class-deactivator.php       # 비활성화 로직
│   └── class-loader.php            # 오토로더
│
├── admin/                          # 관리자 영역
│   ├── class-admin.php             # 관리자 메인
│   ├── class-settings-page.php     # 설정 페이지
│   ├── class-rewriter-page.php     # 재작성 페이지
│   ├── class-scheduler-page.php    # 스케줄러 페이지
│   ├── class-history-page.php      # 히스토리 페이지
│   ├── css/
│   │   └── admin-style.css
│   ├── js/
│   │   └── admin-script.js
│   └── partials/                   # 뷰 템플릿
│       ├── settings-display.php
│       ├── rewriter-display.php
│       └── scheduler-display.php
│
├── src/                            # 비즈니스 로직
│   ├── Content/
│   │   ├── class-url-fetcher.php       # URL 콘텐츠 추출
│   │   ├── class-content-parser.php    # 콘텐츠 파싱
│   │   └── class-content-chunker.php   # 긴 콘텐츠 분할
│   │
│   ├── AI/
│   │   ├── interface-ai-adapter.php    # AI 어댑터 인터페이스
│   │   ├── class-ai-manager.php        # AI 서비스 매니저
│   │   ├── class-chatgpt-adapter.php   # ChatGPT 어댑터
│   │   ├── class-gemini-adapter.php    # Gemini 어댑터
│   │   └── class-token-calculator.php  # 토큰 계산기
│   │
│   ├── Prompt/
│   │   ├── class-prompt-manager.php    # 프롬프트 관리
│   │   ├── class-prompt-template.php   # 프롬프트 템플릿
│   │   └── templates/                  # 기본 프롬프트 템플릿
│   │       ├── rewrite-default.php
│   │       ├── translate-default.php
│   │       └── seo-optimize.php
│   │
│   ├── Post/
│   │   ├── class-post-creator.php      # 게시글 생성
│   │   ├── class-meta-generator.php    # 메타데이터 생성
│   │   └── class-seo-handler.php       # SEO 처리
│   │
│   └── Scheduler/
│       ├── class-cron-manager.php      # 크론 작업 관리
│       ├── class-queue-handler.php     # 작업 큐 처리
│       └── class-batch-processor.php   # 배치 처리
│
├── languages/                      # 다국어 지원
│   ├── wp-ai-rewriter-ko_KR.po
│   └── wp-ai-rewriter-ko_KR.mo
│
├── assets/                         # 정적 자원
│   ├── images/
│   └── icons/
│
└── tests/                          # 테스트
    ├── phpunit.xml
    ├── bootstrap.php
    └── unit/
        ├── test-content-fetcher.php
        ├── test-ai-manager.php
        └── test-post-creator.php
```

## 2.3 핵심 컴포넌트 상세

### 2.3.1 Content Extractor Module

```php
<?php
/**
 * URL에서 콘텐츠를 추출하는 모듈
 */
interface ContentExtractorInterface {
    public function fetch(string $url): ContentResult;
    public function parse(string $html): ParsedContent;
    public function extractMainContent(ParsedContent $content): string;
    public function extractMetadata(ParsedContent $content): array;
}

class ContentResult {
    public string $title;
    public string $content;
    public string $author;
    public string $publishDate;
    public array $images;
    public array $metadata;
}
```

### 2.3.2 AI Service Manager

```php
<?php
/**
 * AI 서비스 추상화 레이어
 */
interface AIAdapterInterface {
    public function configure(array $options): void;
    public function rewrite(string $content, PromptConfig $prompt): AIResponse;
    public function translate(string $content, string $targetLang): AIResponse;
    public function generateMetadata(string $content): MetadataResult;
    public function getTokenCount(string $text): int;
    public function getModelInfo(): ModelInfo;
}

class AIManager {
    private array $adapters = [];
    private string $activeAdapter;

    public function registerAdapter(string $name, AIAdapterInterface $adapter): void;
    public function setActiveAdapter(string $name): void;
    public function process(ContentRequest $request): ProcessResult;
}
```

### 2.3.3 Prompt Management System

```php
<?php
/**
 * 프롬프트 템플릿 시스템
 */
class PromptTemplate {
    private string $systemPrompt;
    private string $userPromptTemplate;
    private array $variables;

    public function render(array $data): string;
    public function validate(): bool;
}

class PromptManager {
    public function getDefaultTemplate(string $type): PromptTemplate;
    public function saveCustomTemplate(string $name, PromptTemplate $template): void;
    public function loadTemplate(string $name): PromptTemplate;
    public function listTemplates(): array;
}
```

## 2.4 데이터베이스 스키마

### 2.4.1 커스텀 테이블

```sql
-- 작업 히스토리 테이블
CREATE TABLE {prefix}_air_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_url VARCHAR(2048),
    source_type ENUM('url', 'text') DEFAULT 'url',
    original_content LONGTEXT,
    processed_content LONGTEXT,
    ai_model VARCHAR(50),
    prompt_template VARCHAR(100),
    target_language VARCHAR(10),
    post_id BIGINT UNSIGNED,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    tokens_used INT UNSIGNED,
    processing_time FLOAT,
    error_message TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME,
    INDEX idx_status (status),
    INDEX idx_created (created_at)
);

-- 스케줄 작업 테이블
CREATE TABLE {prefix}_air_schedules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    source_type ENUM('url', 'rss', 'url_list') DEFAULT 'url',
    source_data TEXT,
    ai_model VARCHAR(50),
    prompt_template VARCHAR(100),
    target_language VARCHAR(10),
    post_status ENUM('draft', 'publish', 'pending') DEFAULT 'draft',
    post_category BIGINT UNSIGNED,
    schedule_type ENUM('once', 'daily', 'weekly', 'custom') DEFAULT 'once',
    schedule_time TIME,
    schedule_days VARCHAR(20),
    cron_expression VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    last_run DATETIME,
    next_run DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active_next (is_active, next_run)
);

-- 프롬프트 템플릿 테이블
CREATE TABLE {prefix}_air_prompts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE,
    description TEXT,
    system_prompt TEXT,
    user_prompt_template TEXT,
    variables JSON,
    is_default TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### 2.4.2 WordPress Options 사용

```php
<?php
// 설정 옵션 키
$options = [
    'air_openai_api_key'     => '암호화된 OpenAI API 키',
    'air_gemini_api_key'     => '암호화된 Gemini API 키',
    'air_default_model'      => 'chatgpt-5',
    'air_default_language'   => 'ko',
    'air_default_prompt'     => 'rewrite-default',
    'air_max_tokens'         => 4000,
    'air_chunk_size'         => 3000,
    'air_auto_publish'       => false,
    'air_default_category'   => 1,
    'air_enable_logging'     => true,
    'air_notification_email' => '',
];
```

## 2.5 API 연동 구조

### 2.5.1 OpenAI ChatGPT-5 연동

```php
<?php
class ChatGPTAdapter implements AIAdapterInterface {
    private const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
    private string $apiKey;
    private string $model = 'gpt-5';

    public function rewrite(string $content, PromptConfig $prompt): AIResponse {
        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $prompt->getSystemPrompt()],
                ['role' => 'user', 'content' => $prompt->format($content)]
            ],
            'max_tokens' => $this->maxTokens,
            'temperature' => 0.7
        ];

        return $this->sendRequest($payload);
    }
}
```

### 2.5.2 Google Gemini-3 연동

```php
<?php
class GeminiAdapter implements AIAdapterInterface {
    private const API_ENDPOINT = 'https://generativelanguage.googleapis.com/v1/models/';
    private string $apiKey;
    private string $model = 'gemini-3-pro';

    public function rewrite(string $content, PromptConfig $prompt): AIResponse {
        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt->format($content)]]]
            ],
            'generationConfig' => [
                'maxOutputTokens' => $this->maxTokens,
                'temperature' => 0.7
            ]
        ];

        return $this->sendRequest($payload);
    }
}
```

## 2.6 보안 아키텍처

### 2.6.1 API 키 암호화

```php
<?php
class ApiKeyEncryption {
    public static function encrypt(string $key): string {
        $cipher = 'aes-256-cbc';
        $secret = wp_salt('auth');
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($key, $cipher, $secret, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    public static function decrypt(string $encrypted): string {
        $cipher = 'aes-256-cbc';
        $secret = wp_salt('auth');
        $data = base64_decode($encrypted);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, $cipher, $secret, 0, $iv);
    }
}
```

### 2.6.2 권한 체계

```php
<?php
// 커스텀 권한 정의
$capabilities = [
    'air_manage_settings' => '설정 관리',
    'air_use_rewriter'    => '재작성 기능 사용',
    'air_manage_schedules'=> '스케줄 관리',
    'air_view_history'    => '히스토리 조회',
    'air_delete_history'  => '히스토리 삭제',
];

// 역할별 기본 권한
$role_caps = [
    'administrator' => ['air_manage_settings', 'air_use_rewriter', 'air_manage_schedules', 'air_view_history', 'air_delete_history'],
    'editor'        => ['air_use_rewriter', 'air_view_history'],
];
```

## 2.7 성능 최적화 전략

### 2.7.1 긴 콘텐츠 청크 처리

```php
<?php
class ContentChunker {
    private int $chunkSize = 3000; // 토큰 기준

    public function chunk(string $content): array {
        $chunks = [];
        $paragraphs = $this->splitByParagraph($content);
        $currentChunk = '';

        foreach ($paragraphs as $paragraph) {
            if ($this->getTokenCount($currentChunk . $paragraph) > $this->chunkSize) {
                $chunks[] = $currentChunk;
                $currentChunk = $paragraph;
            } else {
                $currentChunk .= $paragraph;
            }
        }

        if (!empty($currentChunk)) {
            $chunks[] = $currentChunk;
        }

        return $chunks;
    }

    public function mergeResults(array $processedChunks): string {
        // 자연스러운 연결을 위한 후처리
        return implode("\n\n", $processedChunks);
    }
}
```

### 2.7.2 비동기 처리

```php
<?php
class AsyncProcessor {
    public function enqueue(ProcessRequest $request): string {
        $jobId = wp_generate_uuid4();

        as_schedule_single_action(
            time(),
            'air_process_content',
            ['job_id' => $jobId, 'request' => serialize($request)],
            'wp-ai-rewriter'
        );

        return $jobId;
    }

    public function getStatus(string $jobId): JobStatus {
        // Action Scheduler를 통한 작업 상태 조회
    }
}
```

## 2.8 확장 포인트 (Hooks & Filters)

```php
<?php
// 액션 훅
do_action('air_before_content_fetch', $url);
do_action('air_after_content_fetch', $content, $url);
do_action('air_before_ai_process', $content, $model);
do_action('air_after_ai_process', $result, $model);
do_action('air_before_post_create', $data);
do_action('air_after_post_create', $postId, $data);

// 필터 훅
$content = apply_filters('air_fetched_content', $content, $url);
$prompt = apply_filters('air_prompt_template', $prompt, $type);
$result = apply_filters('air_ai_response', $result, $model);
$postData = apply_filters('air_post_data', $postData);
$metadata = apply_filters('air_generated_metadata', $metadata, $content);
```

---
*문서 버전: 1.0*
*작성일: 2025-12-28*
*이전 문서: [01-PROJECT-OVERVIEW.md](./01-PROJECT-OVERVIEW.md)*
*다음 문서: [03-ENVIRONMENT-SETUP.md](./03-ENVIRONMENT-SETUP.md)*
