import { defineWorkersConfig } from '@cloudflare/vitest-pool-workers/config';

export default defineWorkersConfig({
  test: {
    // Use Cloudflare Workers pool for realistic testing
    poolOptions: {
      workers: {
        wrangler: { configPath: './wrangler.toml' },
        miniflare: {
          // Mock bindings for testing
          kvNamespaces: ['CONFIG_KV', 'LOCK_KV'],
          d1Databases: ['DB'],
          r2Buckets: ['IMAGES'],
          bindings: {
            ENVIRONMENT: 'test',
            LOG_LEVEL: 'debug',
            WORKER_SECRET: 'test-worker-secret',
            HMAC_SECRET: 'test-hmac-secret',
            WP_API_KEY: 'test-wp-api-key',
            OPENAI_API_KEY: 'test-openai-key',
            GEMINI_API_KEY: 'test-gemini-key',
            WORDPRESS_URL: 'http://localhost:8080',
          },
        },
      },
    },
    // Test configuration
    globals: true,
    environment: 'miniflare',
    setupFiles: ['./tests/setup.ts'],
    include: ['tests/**/*.test.ts'],
    exclude: ['**/node_modules/**', '**/dist/**'],
    // Coverage configuration
    coverage: {
      provider: 'v8',
      reporter: ['text', 'lcov', 'html'],
      include: ['src/**/*.ts'],
      exclude: ['src/types/**', '**/*.d.ts'],
      thresholds: {
        statements: 70,
        branches: 60,
        functions: 70,
        lines: 70,
      },
    },
    // Test timeout (AI calls can be slow)
    testTimeout: 30000,
    // Reporter
    reporters: ['verbose'],
  },
});
