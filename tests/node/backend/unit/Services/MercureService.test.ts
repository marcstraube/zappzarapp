/**
 * Tests for MercureService
 *
 * All HTTP I/O is intercepted via mocked http/https modules — no real hub
 * connection is made. The JWT signature is verified with real crypto.
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { EventEmitter } from 'node:events';
import { createHmac } from 'crypto';
import { MercureService, MercureError } from '@backend/App/Services/MercureService';
import * as http from 'http';
import * as https from 'https';

vi.mock('http', () => ({ request: vi.fn() }));
vi.mock('https', () => ({ request: vi.fn() }));

interface CapturedRequest {
  options?: { method?: string; headers?: Record<string, string | number>; path?: string };
  body?: string;
}

/**
 * Install a one-shot https.request mock that captures the request options and
 * written body, then replies with the given status code and body.
 */
function mockHttpsOnce(
  reply: { statusCode?: number; body?: string },
  captured?: CapturedRequest
): void {
  vi.mocked(https.request).mockImplementationOnce(((
    options: unknown,
    cb: (res: unknown) => void
  ) => {
    if (captured) {
      captured.options = options as CapturedRequest['options'];
    }
    let written = '';
    const req = new EventEmitter() as EventEmitter & {
      write: (chunk: string) => boolean;
      end: () => void;
      destroy: () => void;
    };
    req.destroy = (): void => undefined;
    req.write = (chunk: string): boolean => {
      written += chunk;
      return true;
    };
    req.end = (): void => {
      if (captured) {
        captured.body = written;
      }
      const res = new EventEmitter() as EventEmitter & { statusCode: number };
      res.statusCode = reply.statusCode ?? 200;
      cb(res);
      queueMicrotask(() => {
        if (reply.body !== undefined && reply.body !== '')
          res.emit('data', Buffer.from(reply.body));
        res.emit('end');
      });
    };
    return req;
  }) as unknown as typeof https.request);
}

/**
 * Install a one-shot https.request mock that fails with a transport error.
 */
function mockHttpsErrorOnce(message: string): void {
  vi.mocked(https.request).mockImplementationOnce(((
    _options: unknown,
    _cb: (res: unknown) => void
  ) => {
    const req = new EventEmitter() as EventEmitter & {
      write: () => boolean;
      end: () => void;
      destroy: () => void;
    };
    req.destroy = (): void => undefined;
    req.write = (): boolean => true;
    req.end = (): void => {
      queueMicrotask(() => req.emit('error', new Error(message)));
    };
    return req;
  }) as unknown as typeof https.request);
}

function base64UrlDecode(value: string): string {
  return Buffer.from(value, 'base64url').toString('utf-8');
}

describe('MercureService', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.clearAllMocks();
  });

  describe('publish', () => {
    it('should POST to the hub and return the event id', async () => {
      const captured: CapturedRequest = {};
      mockHttpsOnce({ statusCode: 200, body: 'urn:uuid:event-1' }, captured);

      const service = new MercureService({ jwtKey: 'test-key' });
      const eventId = await service.publish('https://example.com/books/1', '{"status":"sold"}');

      expect(eventId).toBe('urn:uuid:event-1');
      expect(captured.options?.method).toBe('POST');
    });

    it('should send topic and data in the body', async () => {
      const captured: CapturedRequest = {};
      mockHttpsOnce({ statusCode: 200, body: 'id-1' }, captured);

      const service = new MercureService({ jwtKey: 'test-key' });
      await service.publish('https://example.com/books/1', 'hello world');

      const params = new URLSearchParams(captured.body ?? '');
      expect(params.get('topic')).toBe('https://example.com/books/1');
      expect(params.get('data')).toBe('hello world');
    });

    it('should encode multiple topics and options', async () => {
      const captured: CapturedRequest = {};
      mockHttpsOnce({ statusCode: 201, body: 'id-2' }, captured);

      const service = new MercureService({ jwtKey: 'test-key' });
      await service.publish(['https://example.com/a', 'https://example.com/b'], 'payload', {
        private: true,
        id: 'msg-42',
        type: 'chat',
        retry: 3000,
      });

      const body = captured.body ?? '';
      const params = new URLSearchParams(body);
      expect(params.getAll('topic')).toEqual(['https://example.com/a', 'https://example.com/b']);
      expect(params.get('private')).toBe('on');
      expect(params.get('id')).toBe('msg-42');
      expect(params.get('type')).toBe('chat');
      expect(params.get('retry')).toBe('3000');
    });

    it('should sign a valid HS256 publisher token', async () => {
      const captured: CapturedRequest = {};
      mockHttpsOnce({ statusCode: 200, body: 'id-3' }, captured);

      const service = new MercureService({ jwtKey: 'the-signing-key' });
      await service.publish('https://example.com/books/1', 'data');

      const authHeader = captured.options?.headers?.['Authorization'];
      expect(typeof authHeader).toBe('string');
      const token = String(authHeader).replace('Bearer ', '');
      const parts = token.split('.');
      expect(parts).toHaveLength(3);
      const [header64, payload64, signature64] = parts as [string, string, string];

      const header = JSON.parse(base64UrlDecode(header64)) as { alg: string };
      const payload = JSON.parse(base64UrlDecode(payload64)) as { mercure: { publish: string[] } };
      expect(header.alg).toBe('HS256');
      expect(payload.mercure.publish).toEqual(['*']);

      const expected = createHmac('sha256', 'the-signing-key')
        .update(`${header64}.${payload64}`)
        .digest('base64url');
      expect(signature64).toBe(expected);
    });

    it('should throw when the JWT key is missing', async () => {
      const service = new MercureService({ jwtKey: '' });

      await expect(service.publish('https://example.com/books/1', 'data')).rejects.toBeInstanceOf(
        MercureError
      );
      await expect(service.publish('https://example.com/books/1', 'data')).rejects.toThrow(
        'JWT key is not configured'
      );
    });

    it('should throw on a non-2xx status', async () => {
      mockHttpsOnce({ statusCode: 401, body: 'Unauthorized' });

      const service = new MercureService({ jwtKey: 'test-key' });

      await expect(service.publish('https://example.com/books/1', 'data')).rejects.toThrow(
        'HTTP 401'
      );
    });

    it('should throw when the hub is not reachable', async () => {
      mockHttpsErrorOnce('connection refused');

      const service = new MercureService({ jwtKey: 'test-key' });

      await expect(service.publish('https://example.com/books/1', 'data')).rejects.toThrow(
        'not reachable'
      );
    });
  });

  describe('isAvailable', () => {
    it('should return true when the hub responds', async () => {
      const captured: CapturedRequest = {};
      mockHttpsOnce({ statusCode: 400, body: '' }, captured);

      const service = new MercureService({ jwtKey: 'test-key' });

      expect(await service.isAvailable()).toBe(true);
      expect(captured.options?.method).toBe('GET');
    });

    it('should return false on a transport error', async () => {
      mockHttpsErrorOnce('timeout');

      const service = new MercureService({ jwtKey: 'test-key' });

      expect(await service.isAvailable()).toBe(false);
    });
  });

  describe('plain HTTP hub', () => {
    it('should use the http transport for http:// urls', async () => {
      vi.mocked(http.request).mockImplementationOnce(((
        _options: unknown,
        cb: (res: unknown) => void
      ) => {
        const req = new EventEmitter() as EventEmitter & {
          write: () => boolean;
          end: () => void;
          destroy: () => void;
        };
        req.destroy = (): void => undefined;
        req.write = (): boolean => true;
        req.end = (): void => {
          const res = new EventEmitter() as EventEmitter & { statusCode: number };
          res.statusCode = 200;
          cb(res);
          queueMicrotask(() => {
            res.emit('data', Buffer.from('id-http'));
            res.emit('end');
          });
        };
        return req;
      }) as unknown as typeof http.request);

      const service = new MercureService({
        url: 'http://mercure/.well-known/mercure',
        jwtKey: 'test-key',
      });

      const eventId = await service.publish('https://example.com/books/1', 'data');
      expect(eventId).toBe('id-http');
      expect(vi.mocked(http.request)).toHaveBeenCalled();
    });
  });
});
