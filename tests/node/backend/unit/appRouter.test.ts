/**
 * Tests for the App Router
 *
 * Covers the @zappzarapp/browser-utils integration: validated query input
 * (CommonValidator + Result) and Accept-Language negotiation (resolveLocale
 * + formatDateResult).
 */

import { describe, it, expect, beforeAll } from 'vitest';
import request from 'supertest';
import type { Express } from 'express';
import { createApp } from '@backend/app';

describe('App Router', () => {
  let app: Express;

  beforeAll(() => {
    app = createApp();
  });

  describe('GET /api/hello', () => {
    it('should return the default greeting without a name parameter', async () => {
      const response = await request(app).get('/api/hello').expect(200);

      expect(response.body).toHaveProperty('message', 'Hello, World!');
      expect(response.body).toHaveProperty('server', 'Node.js + Express');
      expect(response.body).toHaveProperty('timestamp');
    });

    it('should greet with a valid custom name', async () => {
      const response = await request(app).get('/api/hello?name=Alice').expect(200);

      expect(response.body).toHaveProperty('message', 'Hello, Alice!');
    });

    it('should reject an empty name with 422', async () => {
      const response = await request(app).get('/api/hello?name=').expect(422);

      expect(response.body).toHaveProperty('error', 'Validation failed');
      expect(response.body).toHaveProperty('field', 'name');
      expect(response.body).toHaveProperty('message');
    });

    it('should reject a whitespace-only name with 422', async () => {
      const response = await request(app).get('/api/hello?name=%20%20').expect(422);

      expect(response.body).toHaveProperty('field', 'name');
    });

    it('should fall back to the default locale without Accept-Language', async () => {
      const response = await request(app).get('/api/hello').expect(200);

      expect(response.body).toHaveProperty('locale', 'en-US');
    });

    it('should negotiate a supported locale from Accept-Language', async () => {
      const response = await request(app)
        .get('/api/hello')
        .set('Accept-Language', 'de-DE,de;q=0.9,en;q=0.8')
        .expect(200);

      expect(response.body).toHaveProperty('locale', 'de-DE');
    });

    it('should fall back to the default locale for unsupported languages', async () => {
      const response = await request(app)
        .get('/api/hello')
        .set('Accept-Language', 'ja-JP')
        .expect(200);

      expect(response.body).toHaveProperty('locale', 'en-US');
    });

    it('should format the timestamp for the negotiated locale', async () => {
      const response = await request(app)
        .get('/api/hello')
        .set('Accept-Language', 'de-DE')
        .expect(200);

      const body = response.body as { timestamp: string };
      // Localized long format, not the ISO fallback
      expect(body.timestamp).not.toMatch(/^\d{4}-\d{2}-\d{2}T/);
      expect(body.timestamp.length).toBeGreaterThan(10);
    });
  });

  describe('POST /api/echo', () => {
    it('should echo the request body', async () => {
      const payload = { message: 'ping', count: 2 };
      const response = await request(app).post('/api/echo').send(payload).expect(200);

      expect(response.body).toHaveProperty('echo');
      expect(response.body.echo).toEqual(payload);
      expect(response.body).toHaveProperty('timestamp');
    });
  });
});
