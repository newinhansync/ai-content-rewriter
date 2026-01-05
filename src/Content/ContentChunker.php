<?php
/**
 * Content Chunker
 *
 * @package AIContentRewriter\Content
 */

namespace AIContentRewriter\Content;

/**
 * 긴 콘텐츠를 청크로 분할하는 클래스
 */
class ContentChunker {
    /**
     * 기본 청크 크기 (문자 수)
     */
    private int $chunk_size;

    /**
     * 청크 간 오버랩 크기
     */
    private int $overlap_size;

    /**
     * 분할 우선순위 구분자
     */
    private array $delimiters = [
        "\n\n\n",      // 3줄 바꿈
        "\n\n",        // 2줄 바꿈 (문단)
        ".\n",         // 문장 끝 + 줄바꿈
        ". ",          // 문장 끝
        "! ",          // 느낌표
        "? ",          // 물음표
        ".\n",         // 마침표 + 줄바꿈
        "\n",          // 줄바꿈
        ". ",          // 마침표
        ", ",          // 쉼표
        " ",           // 공백
    ];

    /**
     * 생성자
     */
    public function __construct(?int $chunk_size = null, int $overlap_size = 200) {
        $this->chunk_size = $chunk_size ?? (int) get_option('aicr_chunk_size', 3000);
        $this->overlap_size = $overlap_size;
    }

    /**
     * 콘텐츠를 청크로 분할
     *
     * @param string $content 원본 콘텐츠
     * @return array<ContentChunk> 청크 배열
     */
    public function chunk(string $content): array {
        $content = $this->normalize_content($content);
        $content_length = mb_strlen($content);

        // 청크 크기보다 작으면 그대로 반환
        if ($content_length <= $this->chunk_size) {
            return [
                new ContentChunk(
                    content: $content,
                    index: 0,
                    total: 1,
                    start_position: 0,
                    end_position: $content_length
                ),
            ];
        }

        $chunks = [];
        $position = 0;
        $index = 0;

        while ($position < $content_length) {
            $chunk_end = min($position + $this->chunk_size, $content_length);

            // 청크 끝이 콘텐츠 끝이 아니면 적절한 분할점 찾기
            if ($chunk_end < $content_length) {
                $chunk_end = $this->find_break_point($content, $position, $chunk_end);
            }

            $chunk_content = mb_substr($content, $position, $chunk_end - $position);

            $chunks[] = new ContentChunk(
                content: trim($chunk_content),
                index: $index,
                total: 0, // 나중에 설정
                start_position: $position,
                end_position: $chunk_end
            );

            // 다음 시작점 (오버랩 적용)
            $position = max($position + 1, $chunk_end - $this->overlap_size);
            $index++;
        }

        // 총 청크 수 설정
        $total = count($chunks);
        foreach ($chunks as $chunk) {
            $chunk->set_total($total);
        }

        return $chunks;
    }

    /**
     * 콘텐츠 정규화
     */
    private function normalize_content(string $content): string {
        // HTML 태그 제거 (선택적)
        $content = wp_strip_all_tags($content);

        // 연속 공백 정리
        $content = preg_replace('/[^\S\n]+/', ' ', $content);

        // 연속 줄바꿈 정리 (3개 이상 -> 2개)
        $content = preg_replace('/\n{3,}/', "\n\n", $content);

        return trim($content);
    }

    /**
     * 적절한 분할점 찾기
     */
    private function find_break_point(string $content, int $start, int $end): int {
        $search_start = max($start, $end - 500); // 마지막 500자 내에서 검색
        $search_content = mb_substr($content, $search_start, $end - $search_start);

        foreach ($this->delimiters as $delimiter) {
            $pos = mb_strrpos($search_content, $delimiter);
            if ($pos !== false) {
                return $search_start + $pos + mb_strlen($delimiter);
            }
        }

        // 구분자를 찾지 못하면 원래 위치 반환
        return $end;
    }

    /**
     * 청크들을 다시 합치기
     *
     * @param array<ContentChunk> $chunks
     * @return string
     */
    public function merge(array $chunks): string {
        // 인덱스순으로 정렬
        usort($chunks, fn($a, $b) => $a->get_index() <=> $b->get_index());

        $merged = '';
        foreach ($chunks as $chunk) {
            $content = $chunk->get_processed_content() ?? $chunk->get_content();
            $merged .= $content . "\n\n";
        }

        return trim($merged);
    }

    /**
     * 청킹이 필요한지 확인
     */
    public function needs_chunking(string $content): bool {
        return mb_strlen($content) > $this->chunk_size;
    }

    /**
     * 예상 청크 수 계산
     */
    public function estimate_chunk_count(string $content): int {
        $length = mb_strlen($content);
        if ($length <= $this->chunk_size) {
            return 1;
        }

        // 오버랩을 고려한 예상 청크 수
        $effective_chunk_size = $this->chunk_size - $this->overlap_size;
        return (int) ceil($length / $effective_chunk_size);
    }
}
