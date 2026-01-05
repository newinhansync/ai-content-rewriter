<?php
/**
 * Prompt Template
 *
 * @package AIContentRewriter\Content
 */

namespace AIContentRewriter\Content;

/**
 * 프롬프트 템플릿 클래스
 */
class PromptTemplate {
    /**
     * 템플릿 ID
     */
    private ?int $id;

    /**
     * 템플릿 이름
     */
    private string $name;

    /**
     * 템플릿 유형
     */
    private string $type;

    /**
     * 템플릿 콘텐츠
     */
    private string $content;

    /**
     * 사용 가능한 변수 목록
     */
    private array $variables;

    /**
     * 기본 템플릿 여부
     */
    private bool $is_default;

    /**
     * 생성자
     */
    public function __construct(array $data = []) {
        $this->id = $data['id'] ?? null;
        $this->name = $data['name'] ?? '';
        $this->type = $data['type'] ?? 'rewrite';
        $this->content = $data['content'] ?? '';
        $this->variables = $data['variables'] ?? [];
        $this->is_default = $data['is_default'] ?? false;
    }

    /**
     * 템플릿 렌더링 (변수 치환)
     *
     * @param array $variables 변수 값 배열
     * @return string 렌더링된 프롬프트
     */
    public function render(array $variables = []): string {
        $prompt = $this->content;

        // 변수 치환
        foreach ($variables as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $prompt = str_replace($placeholder, $value, $prompt);
        }

        // 남은 플레이스홀더 제거
        $prompt = preg_replace('/\{\{[^}]+\}\}/', '', $prompt);

        return trim($prompt);
    }

    /**
     * 템플릿에서 사용되는 변수 추출
     */
    public function extract_variables(): array {
        preg_match_all('/\{\{([^}]+)\}\}/', $this->content, $matches);
        return array_unique($matches[1] ?? []);
    }

    /**
     * 변수 유효성 검사
     */
    public function validate_variables(array $provided_variables): array {
        $required = $this->extract_variables();
        $missing = array_diff($required, array_keys($provided_variables));
        return $missing;
    }

    // Getters
    public function get_id(): ?int {
        return $this->id;
    }

    public function get_name(): string {
        return $this->name;
    }

    public function get_type(): string {
        return $this->type;
    }

    public function get_content(): string {
        return $this->content;
    }

    public function get_variables(): array {
        return $this->variables;
    }

    public function is_default(): bool {
        return $this->is_default;
    }

    // Setters
    public function set_name(string $name): self {
        $this->name = $name;
        return $this;
    }

    public function set_content(string $content): self {
        $this->content = $content;
        return $this;
    }

    /**
     * 배열로 변환
     */
    public function to_array(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'content' => $this->content,
            'variables' => $this->variables,
            'is_default' => $this->is_default,
            'extracted_variables' => $this->extract_variables(),
        ];
    }
}
