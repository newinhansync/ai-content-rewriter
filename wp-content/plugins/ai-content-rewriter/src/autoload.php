<?php
/**
 * PSR-4 Autoloader
 *
 * @package AIContentRewriter
 */

spl_autoload_register(function (string $class): void {
    // 플러그인 네임스페이스
    $prefix = 'AIContentRewriter\\';

    // 네임스페이스가 일치하지 않으면 반환
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // 상대 클래스명 추출
    $relative_class = substr($class, $len);

    // 클래스명을 파일 경로로 변환
    $file = AICR_PLUGIN_DIR . 'src/' . str_replace('\\', '/', $relative_class) . '.php';

    // 파일이 존재하면 로드
    if (file_exists($file)) {
        require_once $file;
    }
});
