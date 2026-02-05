/**
 * Auth Utility Tests
 *
 * Tests for HMAC signature generation and verification
 */

import { describe, it, expect } from 'vitest';
import { generateHmacSignature, verifyHmacSignature, verifyBearerToken } from '../../src/utils/auth';

describe('Auth Utilities', () => {
  describe('generateHmacSignature', () => {
    it('should generate consistent HMAC signature', async () => {
      const payload = JSON.stringify({ test: 'data' });
      const secret = 'test-secret';
      const timestamp = 1707123456;

      const signature1 = await generateHmacSignature(payload, secret, timestamp);
      const signature2 = await generateHmacSignature(payload, secret, timestamp);

      expect(signature1).toBe(signature2);
      expect(signature1).toMatch(/^[a-f0-9]{64}$/); // SHA-256 hex
    });

    it('should generate different signatures for different payloads', async () => {
      const secret = 'test-secret';
      const timestamp = 1707123456;

      const sig1 = await generateHmacSignature('payload1', secret, timestamp);
      const sig2 = await generateHmacSignature('payload2', secret, timestamp);

      expect(sig1).not.toBe(sig2);
    });

    it('should generate different signatures for different secrets', async () => {
      const payload = 'test-payload';
      const timestamp = 1707123456;

      const sig1 = await generateHmacSignature(payload, 'secret1', timestamp);
      const sig2 = await generateHmacSignature(payload, 'secret2', timestamp);

      expect(sig1).not.toBe(sig2);
    });

    it('should generate different signatures for different timestamps', async () => {
      const payload = 'test-payload';
      const secret = 'test-secret';

      const sig1 = await generateHmacSignature(payload, secret, 1707123456);
      const sig2 = await generateHmacSignature(payload, secret, 1707123457);

      expect(sig1).not.toBe(sig2);
    });
  });

  describe('verifyHmacSignature', () => {
    it('should verify valid signature', async () => {
      const payload = JSON.stringify({ task_id: 'test-123' });
      const secret = 'webhook-secret';
      const timestamp = Math.floor(Date.now() / 1000);

      const signature = await generateHmacSignature(payload, secret, timestamp);
      const isValid = await verifyHmacSignature(payload, secret, signature, timestamp);

      expect(isValid).toBe(true);
    });

    it('should reject invalid signature', async () => {
      const payload = JSON.stringify({ task_id: 'test-123' });
      const timestamp = Math.floor(Date.now() / 1000);

      const isValid = await verifyHmacSignature(payload, 'secret', 'invalid-signature', timestamp);

      expect(isValid).toBe(false);
    });

    it('should reject expired timestamp (more than 5 minutes old)', async () => {
      const payload = JSON.stringify({ task_id: 'test-123' });
      const secret = 'webhook-secret';
      const oldTimestamp = Math.floor(Date.now() / 1000) - 400; // 6+ minutes ago

      const signature = await generateHmacSignature(payload, secret, oldTimestamp);
      const isValid = await verifyHmacSignature(payload, secret, signature, oldTimestamp);

      expect(isValid).toBe(false);
    });

    it('should accept timestamp within 5 minutes', async () => {
      const payload = JSON.stringify({ task_id: 'test-123' });
      const secret = 'webhook-secret';
      const recentTimestamp = Math.floor(Date.now() / 1000) - 200; // 3+ minutes ago

      const signature = await generateHmacSignature(payload, secret, recentTimestamp);
      const isValid = await verifyHmacSignature(payload, secret, signature, recentTimestamp);

      expect(isValid).toBe(true);
    });
  });

  describe('verifyBearerToken', () => {
    it('should verify valid bearer token', () => {
      const header = 'Bearer test-secret-token';
      const secret = 'test-secret-token';

      expect(verifyBearerToken(header, secret)).toBe(true);
    });

    it('should reject invalid bearer token', () => {
      const header = 'Bearer wrong-token';
      const secret = 'correct-token';

      expect(verifyBearerToken(header, secret)).toBe(false);
    });

    it('should reject malformed authorization header', () => {
      expect(verifyBearerToken('InvalidFormat', 'secret')).toBe(false);
      expect(verifyBearerToken('Basic abc123', 'abc123')).toBe(false);
      expect(verifyBearerToken('', 'secret')).toBe(false);
    });

    it('should handle null/undefined header', () => {
      expect(verifyBearerToken(null as unknown as string, 'secret')).toBe(false);
      expect(verifyBearerToken(undefined as unknown as string, 'secret')).toBe(false);
    });
  });
});
