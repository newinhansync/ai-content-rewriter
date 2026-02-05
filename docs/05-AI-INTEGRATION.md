# Part 5: AI 통합 및 프롬프팅 시스템

## 5.1 AI 서비스 아키텍처

### 5.1.1 어댑터 패턴 구조

```
┌─────────────────────────────────────────────────────────────────┐
│                         AI Manager                               │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │                    AIManagerInterface                    │    │
│  │  + setAdapter(name: string): void                       │    │
│  │  + rewrite(content: string, config: Config): Response   │    │
│  │  + translate(content: string, lang: string): Response   │    │
│  │  + generateMeta(content: string): Metadata              │    │
│  └─────────────────────────────────────────────────────────┘    │
│                              │                                   │
│              ┌───────────────┼───────────────┐                  │
│              │               │               │                  │
│              ▼               ▼               ▼                  │
│  ┌───────────────┐  ┌───────────────┐  ┌───────────────┐       │
│  │   ChatGPT-5   │  │   Gemini-3    │  │    Future     │       │
│  │    Adapter    │  │    Adapter    │  │   Adapters    │       │
│  └───────┬───────┘  └───────┬───────┘  └───────────────┘       │
│          │                  │                                   │
│          ▼                  ▼                                   │
│  ┌───────────────┐  ┌───────────────┐                          │
│  │  OpenAI API   │  │  Google API   │                          │
│  └───────────────┘  └───────────────┘                          │
└─────────────────────────────────────────────────────────────────┘
```

### 5.1.2 AI 어댑터 인터페이스

```php
<?php
namespace WPAIRewriter\AI;

interface AIAdapterInterface {
    /**
     * API 키 설정
     */
    public function setApiKey(string $key): void;

    /**
     * 모델 설정
     */
    public function setModel(string $model): void;

    /**
     * 콘텐츠 재작성
     */
    public function rewrite(string $content, PromptConfig $config): AIResponse;

    /**
     * 콘텐츠 번역
     */
    public function translate(string $content, string $targetLang, PromptConfig $config): AIResponse;

    /**
     * 메타데이터 생성 (제목, 태그, 설명 등)
     */
    public function generateMetadata(string $content): MetadataResponse;

    /**
     * 토큰 수 계산
     */
    public function countTokens(string $text): int;

    /**
     * API 키 유효성 검증
     */
    public function validateApiKey(): bool;

    /**
     * 사용 가능한 모델 목록
     */
    public function getAvailableModels(): array;

    /**
     * 현재 모델 정보
     */
    public function getModelInfo(): ModelInfo;
}
```

## 5.2 ChatGPT-5 어댑터

### 5.2.1 구현 코드

```php
<?php
namespace WPAIRewriter\AI;

class ChatGPTAdapter implements AIAdapterInterface {
    private const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    private string $apiKey;
    private string $model = 'gpt-5';
    private int $maxTokens = 4000;
    private float $temperature = 0.7;
    private int $timeout = 120;

    /**
     * 콘텐츠 재작성
     */
    public function rewrite(string $content, PromptConfig $config): AIResponse {
        $messages = $this->buildMessages($content, $config);

        $payload = [
            'model'       => $this->model,
            'messages'    => $messages,
            'max_tokens'  => $this->maxTokens,
            'temperature' => $this->temperature,
        ];

        return $this->sendRequest($payload);
    }

    /**
     * 메시지 배열 구성
     */
    private function buildMessages(string $content, PromptConfig $config): array {
        $messages = [];

        // 시스템 프롬프트
        $messages[] = [
            'role'    => 'system',
            'content' => $config->getSystemPrompt(),
        ];

        // 사용자 프롬프트 (콘텐츠 포함)
        $userPrompt = $config->getUserPrompt();
        $userPrompt = str_replace('{{content}}', $content, $userPrompt);
        $userPrompt = str_replace('{{target_language}}', $config->getTargetLanguage(), $userPrompt);

        if ($config->hasCustomInstructions()) {
            $userPrompt = str_replace(
                '{{custom_instructions}}',
                $config->getCustomInstructions(),
                $userPrompt
            );
        }

        $messages[] = [
            'role'    => 'user',
            'content' => $userPrompt,
        ];

        return $messages;
    }

    /**
     * API 요청 전송
     */
    private function sendRequest(array $payload): AIResponse {
        $response = wp_remote_post(self::API_ENDPOINT, [
            'timeout' => $this->timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            throw new AIException('API 요청 실패: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            throw new AIException('API 오류: ' . $body['error']['message']);
        }

        return new AIResponse(
            content: $body['choices'][0]['message']['content'],
            tokensUsed: $body['usage']['total_tokens'],
            model: $body['model'],
            finishReason: $body['choices'][0]['finish_reason']
        );
    }

    /**
     * 토큰 수 계산 (tiktoken 근사치)
     */
    public function countTokens(string $text): int {
        // GPT 모델의 경우 대략 4문자 = 1토큰
        // 한글의 경우 대략 1.5문자 = 1토큰
        $koreanChars = preg_match_all('/[\x{AC00}-\x{D7AF}]/u', $text);
        $otherChars = mb_strlen($text) - $koreanChars;

        return (int) ceil($koreanChars / 1.5) + (int) ceil($otherChars / 4);
    }

    /**
     * 사용 가능한 모델 목록
     */
    public function getAvailableModels(): array {
        return [
            'gpt-5' => [
                'name'        => 'GPT-5',
                'description' => '최신 GPT-5 모델 (권장)',
                'max_tokens'  => 128000,
                'cost_per_1k' => 0.03,
            ],
            'gpt-4-turbo' => [
                'name'        => 'GPT-4 Turbo',
                'description' => 'GPT-4 Turbo 모델',
                'max_tokens'  => 128000,
                'cost_per_1k' => 0.01,
            ],
            'gpt-4o' => [
                'name'        => 'GPT-4o',
                'description' => 'GPT-4o 옴니 모델',
                'max_tokens'  => 128000,
                'cost_per_1k' => 0.005,
            ],
        ];
    }
}
```

## 5.3 Gemini-3 어댑터

### 5.3.1 구현 코드

```php
<?php
namespace WPAIRewriter\AI;

class GeminiAdapter implements AIAdapterInterface {
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1/models/';

    private string $apiKey;
    private string $model = 'gemini-3-pro';
    private int $maxTokens = 4000;
    private float $temperature = 0.7;

    /**
     * 콘텐츠 재작성
     */
    public function rewrite(string $content, PromptConfig $config): AIResponse {
        $endpoint = self::API_BASE . $this->model . ':generateContent?key=' . $this->apiKey;

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $this->buildPrompt($content, $config)]
                    ]
                ]
            ],
            'generationConfig' => [
                'maxOutputTokens' => $this->maxTokens,
                'temperature'     => $this->temperature,
                'topP'            => 0.95,
                'topK'            => 40,
            ],
            'safetySettings' => $this->getSafetySettings(),
        ];

        return $this->sendRequest($endpoint, $payload);
    }

    /**
     * 프롬프트 구성
     */
    private function buildPrompt(string $content, PromptConfig $config): string {
        $prompt = $config->getSystemPrompt() . "\n\n";
        $prompt .= "---\n\n";

        $userPrompt = $config->getUserPrompt();
        $userPrompt = str_replace('{{content}}', $content, $userPrompt);
        $userPrompt = str_replace('{{target_language}}', $config->getTargetLanguage(), $userPrompt);

        $prompt .= $userPrompt;

        return $prompt;
    }

    /**
     * 안전 설정
     */
    private function getSafetySettings(): array {
        return [
            ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
            ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
        ];
    }

    /**
     * API 요청 전송
     */
    private function sendRequest(string $endpoint, array $payload): AIResponse {
        $response = wp_remote_post($endpoint, [
            'timeout' => $this->timeout,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            throw new AIException('Gemini API 요청 실패: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            throw new AIException('Gemini API 오류: ' . $body['error']['message']);
        }

        $content = $body['candidates'][0]['content']['parts'][0]['text'];
        $tokensUsed = $body['usageMetadata']['totalTokenCount'] ?? 0;

        return new AIResponse(
            content: $content,
            tokensUsed: $tokensUsed,
            model: $this->model,
            finishReason: $body['candidates'][0]['finishReason'] ?? 'STOP'
        );
    }

    /**
     * 사용 가능한 모델 목록
     */
    public function getAvailableModels(): array {
        return [
            'gemini-3-pro' => [
                'name'        => 'Gemini 3 Pro',
                'description' => '최신 Gemini 3 Pro 모델 (권장)',
                'max_tokens'  => 32768,
                'cost_per_1k' => 0.0005,
            ],
            'gemini-2-pro' => [
                'name'        => 'Gemini 2 Pro',
                'description' => 'Gemini 2 Pro 모델',
                'max_tokens'  => 32768,
                'cost_per_1k' => 0.00025,
            ],
            'gemini-2-flash' => [
                'name'        => 'Gemini 2 Flash',
                'description' => '빠른 응답의 Gemini 2 Flash',
                'max_tokens'  => 8192,
                'cost_per_1k' => 0.0001,
            ],
        ];
    }
}
```

## 5.4 프롬프트 템플릿 시스템

### 5.4.1 기본 프롬프트 템플릿

#### 재작성 기본 템플릿 (rewrite-default)

```php
<?php
// templates/rewrite-default.php

return [
    'name' => '기본 재작성',
    'description' => '원본 콘텐츠를 독창적인 블로그 게시글로 재작성합니다.',
    'type' => 'rewrite',

    'system_prompt' => <<<'PROMPT'
당신은 전문 블로그 콘텐츠 작성자입니다. 다음 원칙을 따라 콘텐츠를 재작성합니다:

## 역할
- 주어진 원본 콘텐츠를 분석하고 핵심 정보를 추출합니다
- 독창적이고 매력적인 블로그 게시글로 재구성합니다
- 읽기 쉽고 SEO에 최적화된 구조로 작성합니다

## 작성 원칙
1. 원본의 핵심 정보와 사실을 정확하게 유지
2. 표절이 아닌 완전히 새로운 문장과 표현 사용
3. 독자의 관심을 끄는 도입부 작성
4. 명확한 소제목과 단락 구분
5. 자연스러운 흐름과 논리적 전개
6. 결론에서 핵심 메시지 강조

## 출력 형식
- HTML 형식으로 작성 (WordPress 호환)
- 적절한 <h2>, <h3> 태그 사용
- <p>, <ul>, <ol> 등 의미있는 마크업
- 가독성을 위한 적절한 단락 분리
PROMPT,

    'user_prompt' => <<<'PROMPT'
다음 콘텐츠를 {{target_language}}로 재작성해주세요.

## 원본 콘텐츠:
{{content}}

{{custom_instructions}}

## 요구사항:
- 원본의 핵심 정보를 유지하면서 완전히 새롭게 작성
- 블로그 독자에게 적합한 친근하고 전문적인 톤
- 읽기 쉬운 구조와 적절한 소제목 사용
- HTML 형식으로 출력
PROMPT,

    'variables' => ['content', 'target_language', 'custom_instructions'],
];
```

#### 번역 및 재구성 템플릿 (translate-rewrite)

```php
<?php
// templates/translate-rewrite.php

return [
    'name' => '번역 및 재구성',
    'description' => '영문 콘텐츠를 한국어로 번역하면서 한국 독자에 맞게 재구성합니다.',
    'type' => 'translate',

    'system_prompt' => <<<'PROMPT'
당신은 전문 번역가이자 콘텐츠 로컬라이저입니다. 다음 원칙을 따릅니다:

## 역할
- 원문의 의미를 정확하게 이해하고 번역합니다
- 단순 번역이 아닌, 목표 언어 독자에게 자연스러운 콘텐츠로 재구성합니다
- 문화적 맥락을 고려하여 필요시 현지화합니다

## 번역 원칙
1. 의미 전달의 정확성 우선
2. 목표 언어의 자연스러운 표현 사용
3. 전문 용어는 적절히 번역하거나 원어 병기
4. 문화적 레퍼런스는 필요시 설명 추가
5. 원문의 톤과 스타일 유지

## 한국어 작성 시 주의사항
- 존댓말 사용 (합니다체)
- 한국 독자에게 익숙한 표현 선호
- 불필요한 영어 직역 표현 지양
- 문장 호흡을 한국어에 맞게 조절
PROMPT,

    'user_prompt' => <<<'PROMPT'
다음 영문 콘텐츠를 {{target_language}}로 번역하고 재구성해주세요.

## 원본 콘텐츠 (영문):
{{content}}

{{custom_instructions}}

## 요구사항:
- 정확한 의미 전달
- 한국 블로그 독자에게 자연스러운 문체
- 필요시 문화적 맥락 보충
- HTML 형식으로 출력
PROMPT,

    'variables' => ['content', 'target_language', 'custom_instructions'],
];
```

#### SEO 최적화 템플릿 (seo-optimize)

```php
<?php
// templates/seo-optimize.php

return [
    'name' => 'SEO 최적화 재작성',
    'description' => 'SEO에 최적화된 구조와 키워드를 포함하여 재작성합니다.',
    'type' => 'seo',

    'system_prompt' => <<<'PROMPT'
당신은 SEO 전문가이자 콘텐츠 마케터입니다. 다음 원칙을 따릅니다:

## 역할
- 검색 엔진 최적화를 고려한 콘텐츠 작성
- 키워드 배치와 밀도 최적화
- 사용자 의도에 맞는 콘텐츠 구조화

## SEO 작성 원칙
1. 제목(H1)에 주요 키워드 포함
2. 소제목(H2, H3)에 관련 키워드 자연스럽게 배치
3. 첫 문단에 주요 키워드 언급
4. 키워드 밀도 2-3% 유지
5. 내부 링크 제안 위치 표시
6. 메타 설명에 적합한 요약 포함

## 콘텐츠 구조
- 매력적인 도입부 (Hook)
- 명확한 본문 구조 (문제-해결 또는 정보 전달)
- 행동 유도 결론 (CTA)
- 스키마 마크업 고려
PROMPT,

    'user_prompt' => <<<'PROMPT'
다음 콘텐츠를 SEO에 최적화하여 {{target_language}}로 재작성해주세요.

## 원본 콘텐츠:
{{content}}

{{custom_instructions}}

## 요구사항:
- 주요 키워드 자연스럽게 배치
- SEO 친화적인 제목과 소제목 구조
- 메타 설명으로 사용할 수 있는 요약 (별도 표시)
- 추천 태그 목록 (별도 표시)
- HTML 형식으로 출력
PROMPT,

    'variables' => ['content', 'target_language', 'custom_instructions'],
];
```

### 5.4.2 프롬프트 관리자 클래스

```php
<?php
namespace WPAIRewriter\Prompt;

class PromptManager {
    private array $templates = [];
    private string $templatesDir;

    public function __construct() {
        $this->templatesDir = AIR_PLUGIN_DIR . 'src/Prompt/templates/';
        $this->loadDefaultTemplates();
        $this->loadCustomTemplates();
    }

    /**
     * 기본 템플릿 로드
     */
    private function loadDefaultTemplates(): void {
        $defaultTemplates = [
            'rewrite-default',
            'translate-rewrite',
            'seo-optimize',
        ];

        foreach ($defaultTemplates as $name) {
            $file = $this->templatesDir . $name . '.php';
            if (file_exists($file)) {
                $template = require $file;
                $this->templates[$name] = new PromptTemplate($template);
            }
        }
    }

    /**
     * 커스텀 템플릿 로드 (DB)
     */
    private function loadCustomTemplates(): void {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}" . AIR_TABLE_PREFIX . "prompts"
        );

        foreach ($results as $row) {
            $this->templates[$row->name] = new PromptTemplate([
                'name'          => $row->name,
                'description'   => $row->description,
                'system_prompt' => $row->system_prompt,
                'user_prompt'   => $row->user_prompt_template,
                'variables'     => json_decode($row->variables, true),
                'is_custom'     => true,
            ]);
        }
    }

    /**
     * 템플릿 조회
     */
    public function getTemplate(string $name): ?PromptTemplate {
        return $this->templates[$name] ?? null;
    }

    /**
     * 모든 템플릿 목록
     */
    public function listTemplates(): array {
        $list = [];
        foreach ($this->templates as $name => $template) {
            $list[$name] = [
                'name'        => $template->getName(),
                'description' => $template->getDescription(),
                'is_default'  => !$template->isCustom(),
            ];
        }
        return $list;
    }

    /**
     * 커스텀 템플릿 저장
     */
    public function saveTemplate(string $name, array $data): bool {
        global $wpdb;

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}" . AIR_TABLE_PREFIX . "prompts WHERE name = %s",
            $name
        ));

        $record = [
            'name'                 => $name,
            'description'          => $data['description'] ?? '',
            'system_prompt'        => $data['system_prompt'],
            'user_prompt_template' => $data['user_prompt'],
            'variables'            => wp_json_encode($data['variables'] ?? []),
            'updated_at'           => current_time('mysql'),
        ];

        if ($exists) {
            return $wpdb->update(
                $wpdb->prefix . AIR_TABLE_PREFIX . 'prompts',
                $record,
                ['name' => $name]
            ) !== false;
        }

        $record['created_at'] = current_time('mysql');
        return $wpdb->insert(
            $wpdb->prefix . AIR_TABLE_PREFIX . 'prompts',
            $record
        ) !== false;
    }
}
```

## 5.5 긴 콘텐츠 처리 (청킹)

### 5.5.1 콘텐츠 청커

```php
<?php
namespace WPAIRewriter\Content;

class ContentChunker {
    private int $maxChunkTokens;
    private int $overlapTokens = 100; // 청크 간 오버랩

    public function __construct(int $maxChunkTokens = 3000) {
        $this->maxChunkTokens = $maxChunkTokens;
    }

    /**
     * 콘텐츠를 청크로 분할
     */
    public function chunk(string $content): array {
        $paragraphs = $this->splitIntoParagraphs($content);
        $chunks = [];
        $currentChunk = '';
        $currentTokens = 0;

        foreach ($paragraphs as $paragraph) {
            $paragraphTokens = $this->estimateTokens($paragraph);

            // 단일 문단이 최대 크기를 초과하는 경우
            if ($paragraphTokens > $this->maxChunkTokens) {
                if (!empty($currentChunk)) {
                    $chunks[] = $currentChunk;
                    $currentChunk = '';
                    $currentTokens = 0;
                }
                // 문장 단위로 더 분할
                $chunks = array_merge($chunks, $this->chunkLargeParagraph($paragraph));
                continue;
            }

            // 현재 청크에 추가 가능한지 확인
            if ($currentTokens + $paragraphTokens > $this->maxChunkTokens) {
                $chunks[] = $currentChunk;
                // 오버랩 처리: 이전 청크의 마지막 부분 포함
                $currentChunk = $this->getOverlapText($currentChunk) . "\n\n" . $paragraph;
                $currentTokens = $this->estimateTokens($currentChunk);
            } else {
                $currentChunk .= "\n\n" . $paragraph;
                $currentTokens += $paragraphTokens;
            }
        }

        if (!empty(trim($currentChunk))) {
            $chunks[] = $currentChunk;
        }

        return $this->prepareChunks($chunks);
    }

    /**
     * 청크 준비 (컨텍스트 정보 추가)
     */
    private function prepareChunks(array $chunks): array {
        $total = count($chunks);
        $prepared = [];

        foreach ($chunks as $index => $chunk) {
            $prepared[] = [
                'content'  => trim($chunk),
                'index'    => $index,
                'total'    => $total,
                'is_first' => $index === 0,
                'is_last'  => $index === $total - 1,
                'context'  => $this->generateChunkContext($index, $total),
            ];
        }

        return $prepared;
    }

    /**
     * 청크별 컨텍스트 생성
     */
    private function generateChunkContext(int $index, int $total): string {
        if ($total === 1) {
            return '';
        }

        if ($index === 0) {
            return "[이 콘텐츠는 {$total}개 부분 중 첫 번째입니다. 도입부를 작성해주세요.]";
        }

        if ($index === $total - 1) {
            return "[이 콘텐츠는 {$total}개 부분 중 마지막입니다. 결론을 작성해주세요.]";
        }

        $current = $index + 1;
        return "[이 콘텐츠는 {$total}개 부분 중 {$current}번째입니다. 본문을 이어서 작성해주세요.]";
    }

    /**
     * 청크 결과 병합
     */
    public function mergeResults(array $processedChunks): string {
        $merged = [];

        foreach ($processedChunks as $chunk) {
            $content = $chunk['content'];

            // 중복 제거 및 연결 처리
            if (!empty($merged)) {
                $content = $this->removeOverlap($merged[count($merged) - 1], $content);
            }

            $merged[] = $content;
        }

        return $this->smoothJoin($merged);
    }

    /**
     * 자연스러운 연결
     */
    private function smoothJoin(array $parts): string {
        $result = '';

        foreach ($parts as $index => $part) {
            if ($index > 0) {
                // 이전 부분의 끝과 현재 부분의 시작이 자연스럽게 연결되도록
                $result = rtrim($result);
                $part = ltrim($part);

                // 중복된 문장 시작 제거
                $result .= "\n\n";
            }
            $result .= $part;
        }

        return $result;
    }
}
```

## 5.6 메타데이터 자동 생성

### 5.6.1 메타데이터 생성기

```php
<?php
namespace WPAIRewriter\Post;

class MetaGenerator {
    private AIManagerInterface $aiManager;

    /**
     * 메타데이터 생성 프롬프트
     */
    private const META_PROMPT = <<<'PROMPT'
주어진 블로그 콘텐츠를 분석하고 다음 메타데이터를 JSON 형식으로 생성해주세요:

## 필요한 메타데이터:
1. title: SEO 최적화된 제목 (60자 이내)
2. slug: URL 슬러그 (영문, 하이픈 연결)
3. meta_description: 메타 설명 (160자 이내)
4. tags: 관련 태그 배열 (5개 이내)
5. keywords: 주요 키워드 (쉼표 구분, SEO용)
6. excerpt: 요약문 (200자 이내)
7. reading_time: 예상 읽기 시간 (분)

## 콘텐츠:
{{content}}

## 출력 형식 (JSON만 출력):
```json
{
    "title": "...",
    "slug": "...",
    "meta_description": "...",
    "tags": ["태그1", "태그2"],
    "keywords": "키워드1, 키워드2",
    "excerpt": "...",
    "reading_time": 5
}
```
PROMPT;

    /**
     * 메타데이터 생성
     */
    public function generate(string $content): MetadataResult {
        $prompt = str_replace('{{content}}', $this->truncateForMeta($content), self::META_PROMPT);

        $response = $this->aiManager->sendRaw($prompt);

        // JSON 추출
        $json = $this->extractJson($response->getContent());

        if (!$json) {
            // 폴백: 기본 메타데이터 생성
            return $this->generateFallback($content);
        }

        return new MetadataResult(
            title: $json['title'] ?? $this->generateTitle($content),
            slug: $json['slug'] ?? $this->generateSlug($json['title'] ?? ''),
            metaDescription: $json['meta_description'] ?? '',
            tags: $json['tags'] ?? [],
            keywords: $json['keywords'] ?? '',
            excerpt: $json['excerpt'] ?? '',
            readingTime: $json['reading_time'] ?? $this->calculateReadingTime($content)
        );
    }

    /**
     * 폴백 메타데이터 생성
     */
    private function generateFallback(string $content): MetadataResult {
        // AI 없이 기본 메타데이터 생성
        $firstParagraph = $this->getFirstParagraph($content);
        $title = $this->extractTitle($content) ?: mb_substr(strip_tags($firstParagraph), 0, 60);

        return new MetadataResult(
            title: $title,
            slug: $this->generateSlug($title),
            metaDescription: mb_substr(strip_tags($firstParagraph), 0, 160),
            tags: $this->extractKeywords($content, 5),
            keywords: implode(', ', $this->extractKeywords($content, 10)),
            excerpt: mb_substr(strip_tags($firstParagraph), 0, 200),
            readingTime: $this->calculateReadingTime($content)
        );
    }

    /**
     * 읽기 시간 계산
     */
    private function calculateReadingTime(string $content): int {
        $wordCount = str_word_count(strip_tags($content));
        // 한글 고려: 분당 약 500자
        $charCount = mb_strlen(strip_tags($content));

        $minutes = max(1, (int) ceil($charCount / 500));
        return $minutes;
    }
}
```

## 5.7 에러 처리 및 재시도

### 5.7.1 AI 요청 재시도 로직

```php
<?php
namespace WPAIRewriter\AI;

class AIRequestHandler {
    private int $maxRetries = 3;
    private array $retryDelays = [1, 3, 5]; // 초 단위

    /**
     * 재시도 로직이 포함된 요청
     */
    public function sendWithRetry(AIAdapterInterface $adapter, string $method, array $args): AIResponse {
        $lastException = null;

        for ($attempt = 0; $attempt < $this->maxRetries; $attempt++) {
            try {
                return call_user_func_array([$adapter, $method], $args);
            } catch (RateLimitException $e) {
                // 속도 제한: 대기 후 재시도
                $this->logRetry($attempt, 'rate_limit', $e->getMessage());
                sleep($this->retryDelays[$attempt] ?? 5);
                $lastException = $e;
            } catch (TimeoutException $e) {
                // 타임아웃: 즉시 재시도
                $this->logRetry($attempt, 'timeout', $e->getMessage());
                $lastException = $e;
            } catch (ServerException $e) {
                // 서버 오류: 대기 후 재시도
                $this->logRetry($attempt, 'server_error', $e->getMessage());
                sleep($this->retryDelays[$attempt] ?? 3);
                $lastException = $e;
            } catch (InvalidResponseException $e) {
                // 잘못된 응답: 즉시 재시도
                $this->logRetry($attempt, 'invalid_response', $e->getMessage());
                $lastException = $e;
            } catch (AIException $e) {
                // 기타 AI 오류: 재시도하지 않음
                throw $e;
            }
        }

        throw new MaxRetriesExceededException(
            "최대 재시도 횟수({$this->maxRetries})를 초과했습니다.",
            previous: $lastException
        );
    }

    /**
     * 재시도 로그
     */
    private function logRetry(int $attempt, string $type, string $message): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[WP-AI-Rewriter] Retry %d/%d (%s): %s',
                $attempt + 1,
                $this->maxRetries,
                $type,
                $message
            ));
        }
    }
}
```

---
*문서 버전: 1.0*
*작성일: 2025-12-28*
*이전 문서: [04-PLUGIN-SPECIFICATIONS.md](./04-PLUGIN-SPECIFICATIONS.md)*
*다음 문서: [06-DEVELOPMENT-ROADMAP.md](./06-DEVELOPMENT-ROADMAP.md)*
