/**
 * Authentication Utilities
 * @since 2.0.0
 */

/**
 * Verify Bearer Token from Authorization header
 * @param authHeader - Full Authorization header (e.g., "Bearer token123")
 * @param expectedToken - The expected token value
 */
export function verifyBearerToken(authHeader: string, expectedToken: string): boolean {
  if (!authHeader || !expectedToken) {
    return false;
  }

  // Parse Bearer token from header
  if (!authHeader.startsWith('Bearer ')) {
    return false;
  }

  const providedToken = authHeader.slice(7); // Remove "Bearer " prefix

  if (providedToken.length !== expectedToken.length) {
    return false;
  }

  // Constant-time comparison to prevent timing attacks
  let result = 0;
  for (let i = 0; i < providedToken.length; i++) {
    result |= providedToken.charCodeAt(i) ^ expectedToken.charCodeAt(i);
  }

  return result === 0;
}

/**
 * Generate HMAC-SHA256 signature for webhook payload
 */
export async function generateHmacSignature(
  payload: string,
  secret: string,
  timestamp: number
): Promise<string> {
  const encoder = new TextEncoder();
  const data = `${timestamp}.${payload}`;

  const key = await crypto.subtle.importKey(
    'raw',
    encoder.encode(secret),
    { name: 'HMAC', hash: 'SHA-256' },
    false,
    ['sign']
  );

  const signature = await crypto.subtle.sign('HMAC', key, encoder.encode(data));

  return Array.from(new Uint8Array(signature))
    .map((b) => b.toString(16).padStart(2, '0'))
    .join('');
}

/**
 * Verify HMAC-SHA256 signature
 */
export async function verifyHmacSignature(
  payload: string,
  secret: string,
  providedSignature: string,
  timestamp: number
): Promise<boolean> {
  // Check timestamp (5 minute window)
  const now = Math.floor(Date.now() / 1000);
  if (Math.abs(now - timestamp) > 300) {
    return false;
  }

  const expectedSignature = await generateHmacSignature(payload, secret, timestamp);

  // Constant-time comparison
  if (expectedSignature.length !== providedSignature.length) {
    return false;
  }

  let result = 0;
  for (let i = 0; i < expectedSignature.length; i++) {
    result |= expectedSignature.charCodeAt(i) ^ providedSignature.charCodeAt(i);
  }

  return result === 0;
}

/**
 * Generate UUID v4
 */
export function generateUUID(): string {
  return crypto.randomUUID();
}
