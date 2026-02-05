<?php
/**
 * Cloudflare Worker Configuration Manager
 *
 * @package AIContentRewriter\Worker
 * @since 2.0.0
 */

namespace AIContentRewriter\Worker;

/**
 * Worker Configuration Class
 */
class WorkerConfig {

    /**
     * Option keys
     */
    const OPTION_WORKER_URL = 'aicr_worker_url';
    const OPTION_WORKER_SECRET = 'aicr_worker_secret';
    const OPTION_HMAC_SECRET = 'aicr_hmac_secret';
    const OPTION_WP_API_KEY = 'aicr_worker_api_key';
    const OPTION_PROCESSING_MODE = 'aicr_processing_mode';
    const OPTION_AUTO_PUBLISH = 'aicr_auto_publish';
    const OPTION_PUBLISH_THRESHOLD = 'aicr_publish_threshold';
    const OPTION_DAILY_LIMIT = 'aicr_daily_publish_limit';
    const OPTION_CURATION_THRESHOLD = 'aicr_curation_threshold';

    /**
     * Processing modes
     */
    const MODE_LOCAL = 'local';
    const MODE_CLOUDFLARE = 'cloudflare';

    /**
     * Get Worker URL
     */
    public function get_worker_url(): string {
        return get_option(self::OPTION_WORKER_URL, '');
    }

    /**
     * Set Worker URL
     */
    public function set_worker_url(string $url): bool {
        return update_option(self::OPTION_WORKER_URL, esc_url_raw($url));
    }

    /**
     * Get Worker Secret (Bearer Token for WP → Worker)
     */
    public function get_worker_secret(): string {
        return get_option(self::OPTION_WORKER_SECRET, '');
    }

    /**
     * Set Worker Secret
     */
    public function set_worker_secret(string $secret): bool {
        return update_option(self::OPTION_WORKER_SECRET, $secret);
    }

    /**
     * Get HMAC Secret (for Worker → WP webhook signature)
     */
    public function get_hmac_secret(): string {
        $secret = get_option(self::OPTION_HMAC_SECRET, '');

        if (empty($secret)) {
            $secret = $this->generate_secret();
            $this->set_hmac_secret($secret);
        }

        return $secret;
    }

    /**
     * Set HMAC Secret
     */
    public function set_hmac_secret(string $secret): bool {
        return update_option(self::OPTION_HMAC_SECRET, $secret);
    }

    /**
     * Regenerate HMAC Secret
     */
    public function regenerate_hmac_secret(): string {
        $secret = $this->generate_secret();
        $this->set_hmac_secret($secret);
        return $secret;
    }

    /**
     * Get WordPress API Key (for Worker → WP REST API)
     */
    public function get_wp_api_key(): string {
        $key = get_option(self::OPTION_WP_API_KEY, '');

        if (empty($key)) {
            $key = $this->generate_secret();
            $this->set_wp_api_key($key);
        }

        return $key;
    }

    /**
     * Set WordPress API Key
     */
    public function set_wp_api_key(string $key): bool {
        return update_option(self::OPTION_WP_API_KEY, $key);
    }

    /**
     * Regenerate WordPress API Key
     */
    public function regenerate_wp_api_key(): string {
        $key = $this->generate_secret();
        $this->set_wp_api_key($key);
        return $key;
    }

    /**
     * Get processing mode
     */
    public function get_processing_mode(): string {
        return get_option(self::OPTION_PROCESSING_MODE, self::MODE_LOCAL);
    }

    /**
     * Set processing mode
     */
    public function set_processing_mode(string $mode): bool {
        if (!in_array($mode, [self::MODE_LOCAL, self::MODE_CLOUDFLARE], true)) {
            return false;
        }
        return update_option(self::OPTION_PROCESSING_MODE, $mode);
    }

    /**
     * Check if Cloudflare mode is enabled
     */
    public function is_cloudflare_mode(): bool {
        return $this->get_processing_mode() === self::MODE_CLOUDFLARE;
    }

    /**
     * Get auto publish setting
     */
    public function is_auto_publish(): bool {
        return (bool)get_option(self::OPTION_AUTO_PUBLISH, true);
    }

    /**
     * Set auto publish setting
     */
    public function set_auto_publish(bool $enabled): bool {
        return update_option(self::OPTION_AUTO_PUBLISH, $enabled);
    }

    /**
     * Get publish threshold (quality score)
     */
    public function get_publish_threshold(): int {
        return (int)get_option(self::OPTION_PUBLISH_THRESHOLD, 8);
    }

    /**
     * Set publish threshold
     */
    public function set_publish_threshold(int $threshold): bool {
        $threshold = max(1, min(10, $threshold));
        return update_option(self::OPTION_PUBLISH_THRESHOLD, $threshold);
    }

    /**
     * Get daily publish limit
     */
    public function get_daily_limit(): int {
        return (int)get_option(self::OPTION_DAILY_LIMIT, 10);
    }

    /**
     * Set daily publish limit
     */
    public function set_daily_limit(int $limit): bool {
        return update_option(self::OPTION_DAILY_LIMIT, max(1, $limit));
    }

    /**
     * Get curation confidence threshold
     */
    public function get_curation_threshold(): float {
        return (float)get_option(self::OPTION_CURATION_THRESHOLD, 0.8);
    }

    /**
     * Set curation confidence threshold
     */
    public function set_curation_threshold(float $threshold): bool {
        $threshold = max(0.0, min(1.0, $threshold));
        return update_option(self::OPTION_CURATION_THRESHOLD, $threshold);
    }

    /**
     * Check if Worker is configured
     */
    public function is_configured(): bool {
        return !empty($this->get_worker_url()) && !empty($this->get_worker_secret());
    }

    /**
     * Get all configuration as array
     */
    public function get_all(): array {
        return [
            'worker_url'          => $this->get_worker_url(),
            'worker_secret'       => !empty($this->get_worker_secret()) ? '********' : '',
            'hmac_secret'         => !empty($this->get_hmac_secret()) ? '********' : '',
            'wp_api_key'          => !empty($this->get_wp_api_key()) ? '********' : '',
            'processing_mode'     => $this->get_processing_mode(),
            'auto_publish'        => $this->is_auto_publish(),
            'publish_threshold'   => $this->get_publish_threshold(),
            'daily_limit'         => $this->get_daily_limit(),
            'curation_threshold'  => $this->get_curation_threshold(),
            'is_configured'       => $this->is_configured(),
        ];
    }

    /**
     * Get Webhook URL for Worker to call back
     */
    public function get_webhook_url(): string {
        return rest_url('aicr/v1/webhook');
    }

    /**
     * Get full config URL for Worker
     */
    public function get_config_url(): string {
        return rest_url('aicr/v1/config');
    }

    /**
     * Generate secure random secret
     */
    private function generate_secret(int $length = 32): string {
        return bin2hex(random_bytes($length));
    }

    /**
     * Test Worker connection
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function test_connection(): array {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'message' => 'Worker is not configured. Please set Worker URL and Secret.',
            ];
        }

        $worker_url = rtrim($this->get_worker_url(), '/') . '/api/health';

        $response = wp_remote_get($worker_url, [
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->get_worker_secret(),
                'Content-Type'  => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200) {
            return [
                'success' => false,
                'message' => "Worker returned status {$status_code}",
                'data'    => $data,
            ];
        }

        return [
            'success' => true,
            'message' => 'Connection successful',
            'data'    => [
                'worker_version' => $data['data']['version'] ?? 'unknown',
                'worker_status'  => $data['data']['status'] ?? 'unknown',
                'response_time'  => $data['data']['timestamp'] ?? null,
            ],
        ];
    }
}
