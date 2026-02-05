<?php
/**
 * Content Sectionizer
 *
 * @package AIContentRewriter\Image
 */

namespace AIContentRewriter\Image;

use AIContentRewriter\AI\GeminiAdapter;

/**
 * 콘텐츠 섹션 분할 클래스
 *
 * 콘텐츠를 N개의 섹션으로 분할하고 각 섹션의 주제/키워드 추출
 */
class ContentSectionizer {
    /**
     * 텍스트 AI 어댑터 (주제 추출용)
     */
    private ?GeminiAdapter $textAdapter = null;

    /**
     * AI 사용 여부
     */
    private bool $useAI = true;

    /**
     * 생성자
     */
    public function __construct(?GeminiAdapter $textAdapter = null) {
        $this->textAdapter = $textAdapter;
    }

    /**
     * AI 사용 여부 설정
     */
    public function setUseAI(bool $useAI): self {
        $this->useAI = $useAI;
        return $this;
    }

    /**
     * 콘텐츠를 N개의 섹션으로 분할하고 각 섹션의 주제 추출
     *
     * 인포그래픽 이미지 생성에 사용:
     * - 콘텐츠를 N개로 분할
     * - 각 섹션에 대해 인포그래픽 이미지 생성
     * - 각 섹션의 중간에 해당 인포그래픽 삽입
     *
     * @param string $content HTML 콘텐츠
     * @param int $sectionCount 분할할 섹션 수 (인포그래픽 수)
     * @return array 섹션 배열 ['content', 'topic', 'keywords', 'section_index']
     * @throws \InvalidArgumentException 콘텐츠가 유효하지 않을 경우
     */
    public function sectionize(string $content, int $sectionCount): array {
        // 빈 콘텐츠 체크
        $content = trim($content);
        if (empty($content)) {
            throw new \InvalidArgumentException(__('콘텐츠가 비어있습니다.', 'ai-content-rewriter'));
        }

        // 최소 콘텐츠 길이 체크 (100자 이상)
        $plainText = wp_strip_all_tags($content);
        if (mb_strlen($plainText) < 100) {
            throw new \InvalidArgumentException(__('콘텐츠가 너무 짧습니다. (최소 100자)', 'ai-content-rewriter'));
        }

        // 섹션 수 제한 (1~4, 인포그래픽 최대 4개)
        $sectionCount = max(1, min(4, $sectionCount));

        // H2 태그가 있는지 확인
        $hasH2 = preg_match('/<h2[^>]*>/i', $content);

        // 분할 전략 선택
        if ($hasH2) {
            $sections = $this->splitByHeadings($content, $sectionCount);
        } else {
            $sections = $this->splitByParagraphs($content, $sectionCount);
        }

        // 단락 수가 섹션 수보다 적으면 조정
        if (count($sections) < $sectionCount) {
            $sectionCount = count($sections);
        }

        // 각 섹션의 주제/키워드 추출
        foreach ($sections as $index => &$section) {
            // content 필드가 있는지 확인하고 없으면 빈 문자열 설정
            if (!isset($section['content'])) {
                $section['content'] = '';
            }
            $section['topic'] = $this->extractTopic($section['content']);
            $section['keywords'] = $this->extractKeywords($section['content']);
            $section['section_index'] = $index;
            $section['type'] = 'infographic'; // 타입 명시
        }

        error_log("[AICR Sectionizer] Split content into " . count($sections) . " sections for infographic generation");

        return $sections;
    }

    /**
     * H2 태그 기준 분할
     */
    private function splitByHeadings(string $content, int $count): array {
        // H2로 콘텐츠 분할
        $parts = preg_split('/(?=<h2[^>]*>)/i', $content, -1, PREG_SPLIT_NO_EMPTY);
        $totalParts = count($parts);

        if ($totalParts <= $count) {
            // H2 섹션 수가 이미지 수 이하면 그대로 사용
            return array_map(fn($p) => ['content' => trim($p)], $parts);
        }

        // H2 섹션을 N개 그룹으로 병합
        $sections = [];
        $groupSize = ceil($totalParts / $count);

        for ($i = 0; $i < $count; $i++) {
            $start = $i * $groupSize;
            $group = array_slice($parts, (int) $start, (int) $groupSize);
            $sections[] = ['content' => trim(implode('', $group))];
        }

        return $sections;
    }

    /**
     * 단락 균등 분할 (H2 없는 경우)
     */
    private function splitByParagraphs(string $content, int $count): array {
        $paragraphs = $this->extractParagraphs($content);
        $totalParagraphs = count($paragraphs);

        if ($totalParagraphs === 0) {
            // 단락이 없으면 전체를 하나의 섹션으로
            return [['content' => $content]];
        }

        $sections = [];
        $groupSize = max(1, ceil($totalParagraphs / $count));

        for ($i = 0; $i < $count; $i++) {
            $start = $i * $groupSize;
            $group = array_slice($paragraphs, (int) $start, (int) $groupSize);

            if (!empty($group)) {
                $sections[] = ['content' => trim(implode("\n", $group))];
            }
        }

        return $sections;
    }

    /**
     * HTML에서 단락 요소 추출
     */
    private function extractParagraphs(string $content): array {
        preg_match_all('/<p[^>]*>.*?<\/p>/is', $content, $matches);
        return $matches[0] ?? [];
    }

    /**
     * 섹션의 핵심 주제 추출
     */
    private function extractTopic(string $content): string {
        $plainText = wp_strip_all_tags($content);

        // AI 사용 가능하고 어댑터가 있으면 AI로 추출
        if ($this->useAI && $this->textAdapter !== null) {
            return $this->extractTopicWithAI($plainText);
        }

        // AI 없이 간단한 추출 (첫 문장 또는 H2 텍스트)
        return $this->extractTopicSimple($content, $plainText);
    }

    /**
     * AI를 사용하여 주제 추출
     */
    private function extractTopicWithAI(string $plainText): string {
        try {
            $prompt = "다음 텍스트의 핵심 주제를 이미지 생성에 적합한 한 문장(30자 이내)으로 요약해주세요. 구체적인 시각적 이미지가 떠오르도록 작성해주세요:\n\n" . mb_substr($plainText, 0, 1000);

            $response = $this->textAdapter->generate($prompt, [
                'max_tokens' => 100,
                'temperature' => 0.5,
            ]);

            if ($response->isSuccess()) {
                return trim($response->getContent());
            }
        } catch (\Exception $e) {
            // AI 실패 시 간단한 추출로 폴백
        }

        return $this->extractTopicSimple('', $plainText);
    }

    /**
     * 간단한 주제 추출 (AI 없이)
     */
    private function extractTopicSimple(string $html, string $plainText): string {
        // H2 태그에서 추출 시도
        if (preg_match('/<h2[^>]*>([^<]+)<\/h2>/i', $html, $matches)) {
            return mb_substr(trim($matches[1]), 0, 50);
        }

        // 첫 문장 추출
        $sentences = preg_split('/[.!?。]+/u', $plainText, 2, PREG_SPLIT_NO_EMPTY);
        if (!empty($sentences[0])) {
            return mb_substr(trim($sentences[0]), 0, 50);
        }

        // 처음 50자
        return mb_substr($plainText, 0, 50);
    }

    /**
     * 섹션에서 키워드 추출
     */
    private function extractKeywords(string $content): array {
        $plainText = wp_strip_all_tags($content);

        // AI 사용 가능하고 어댑터가 있으면 AI로 추출
        if ($this->useAI && $this->textAdapter !== null) {
            return $this->extractKeywordsWithAI($plainText);
        }

        // AI 없이 간단한 키워드 추출
        return $this->extractKeywordsSimple($plainText);
    }

    /**
     * AI를 사용하여 키워드 추출
     */
    private function extractKeywordsWithAI(string $plainText): array {
        try {
            $prompt = "다음 텍스트에서 이미지 생성에 사용할 핵심 키워드 3-5개를 쉼표로 구분하여 추출해주세요. 시각적으로 표현 가능한 구체적인 단어를 선택하세요:\n\n" . mb_substr($plainText, 0, 1000);

            $response = $this->textAdapter->generate($prompt, [
                'max_tokens' => 100,
                'temperature' => 0.5,
            ]);

            if ($response->isSuccess()) {
                $keywords = explode(',', $response->getContent());
                return array_map('trim', $keywords);
            }
        } catch (\Exception $e) {
            // AI 실패 시 간단한 추출로 폴백
        }

        return $this->extractKeywordsSimple($plainText);
    }

    /**
     * 간단한 키워드 추출 (AI 없이)
     */
    private function extractKeywordsSimple(string $plainText): array {
        // 불용어 목록 (한국어 + 영어)
        $stopwords = ['이', '그', '저', '것', '수', '등', '의', '를', '을', '에', '와', '과', '는', '가', '도', '로', 'the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'can', 'to', 'of', 'in', 'for', 'on', 'with', 'at', 'by', 'from', 'up', 'about', 'into', 'through', 'during', 'before', 'after', 'above', 'below', 'between', 'under', 'again', 'further', 'then', 'once', 'here', 'there', 'when', 'where', 'why', 'how', 'all', 'each', 'few', 'more', 'most', 'other', 'some', 'such', 'no', 'nor', 'not', 'only', 'own', 'same', 'so', 'than', 'too', 'very'];

        // 단어 빈도 계산
        $words = preg_split('/[\s,.\-!?()[\]{}:;\'"]+/u', $plainText, -1, PREG_SPLIT_NO_EMPTY);
        $wordCounts = [];

        foreach ($words as $word) {
            $word = mb_strtolower(trim($word));

            // 2자 미만 또는 불용어 스킵
            if (mb_strlen($word) < 2 || in_array($word, $stopwords, true)) {
                continue;
            }

            $wordCounts[$word] = ($wordCounts[$word] ?? 0) + 1;
        }

        // 빈도순 정렬
        arsort($wordCounts);

        // 상위 5개 키워드 반환
        return array_slice(array_keys($wordCounts), 0, 5);
    }

    /**
     * 이미지 삽입 위치 계산
     *
     * @param string $content HTML 콘텐츠
     * @param int $imageCount 삽입할 이미지 수
     * @return array 삽입 위치 배열
     */
    public function getInsertionPoints(string $content, int $imageCount): array {
        $hasH2 = preg_match_all('/<h2[^>]*>/i', $content, $h2Matches, PREG_OFFSET_CAPTURE);

        if ($hasH2 && count($h2Matches[0]) > 1) {
            return $this->getH2InsertionPoints($h2Matches[0], $imageCount);
        }

        return $this->getParagraphInsertionPoints($content, $imageCount);
    }

    /**
     * H2 기준 삽입 위치 계산
     */
    private function getH2InsertionPoints(array $h2Matches, int $imageCount): array {
        $points = [];
        $totalH2 = count($h2Matches);

        // 첫 번째 H2는 제외하고 분배
        $availableH2 = $totalH2 - 1;

        if ($availableH2 <= 0) {
            return [['before_h2_index' => 1, 'section_index' => 0]];
        }

        $interval = max(1, floor($availableH2 / $imageCount));

        for ($i = 0; $i < $imageCount; $i++) {
            $h2Index = min($i * $interval + 1, $availableH2);
            $points[] = [
                'before_h2_index' => (int) $h2Index,
                'section_index' => $i,
            ];
        }

        return $points;
    }

    /**
     * 단락 기준 삽입 위치 계산
     */
    private function getParagraphInsertionPoints(string $content, int $imageCount): array {
        $paragraphs = $this->extractParagraphs($content);
        $totalParagraphs = count($paragraphs);

        if ($totalParagraphs === 0) {
            return [['after_paragraph' => 0, 'section_index' => 0]];
        }

        $points = [];
        $interval = floor($totalParagraphs / ($imageCount + 1));

        for ($i = 1; $i <= $imageCount; $i++) {
            $position = $interval * $i;
            $points[] = [
                'after_paragraph' => min((int) $position, $totalParagraphs - 1),
                'section_index' => $i - 1,
            ];
        }

        return $points;
    }
}
