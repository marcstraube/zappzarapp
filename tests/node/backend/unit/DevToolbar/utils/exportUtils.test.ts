/**
 * exportUtils Unit Tests
 *
 * Tests for JSON export and browser download functionality.
 *
 * @vitest-environment happy-dom
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import {
  exportRequestAsJson,
  downloadJson,
  downloadFile,
} from '@backend/DevToolbar/utils/exportUtils';
import { mockRequestMetadata } from '../mocks/browserMocks';
import type { RequestData } from '@backend/DevToolbar/types';

describe('exportUtils', () => {
  describe('exportRequestAsJson', () => {
    it('should create export data structure with correct fields', () => {
      const requestId = 'test-request-123';
      const rawData = {
        request: { method: 'GET', uri: '/', status_code: 200 },
        queries: { count: 5, queries: [] },
      };
      const requestData: RequestData = {
        id: requestId,
        metadata: mockRequestMetadata({ id: requestId }),
        tabs: {
          request: '<div>Request content</div>',
          database: '<div>Database queries</div>',
        },
        raw_data: rawData,
      };

      const result = exportRequestAsJson(requestId, requestData);

      expect(result).toHaveProperty('toolbar_version', '2.1.0');
      expect(result).toHaveProperty('export_time');
      expect(result).toHaveProperty('request_id', requestId);
      expect(result).toHaveProperty('metadata');
      expect(result).toHaveProperty('data');
    });

    it('should include request metadata', () => {
      const metadata = mockRequestMetadata({
        method: 'POST',
        uri: '/api/test',
        status: 201,
      });

      const requestData: RequestData = {
        id: 'test-123',
        metadata,
        tabs: {},
        raw_data: { request: {}, queries: {} },
      };

      const result = exportRequestAsJson('test-123', requestData);

      expect(result.metadata.method).toBe('POST');
      expect(result.metadata.uri).toBe('/api/test');
      expect(result.metadata.status).toBe(201);
    });

    it('should exclude badge_counts from metadata (redundant)', () => {
      const metadata = mockRequestMetadata({
        badge_counts: {
          request: 1,
          queries: 5,
          exceptions: 2,
        },
      });

      const requestData: RequestData = {
        id: 'test-123',
        metadata,
        tabs: {},
        raw_data: { request: {}, queries: {} },
      };

      const result = exportRequestAsJson('test-123', requestData);

      expect(result.metadata).not.toHaveProperty('badge_counts');
      expect(result.metadata).toHaveProperty('method');
      expect(result.metadata).toHaveProperty('uri');
      expect(result.metadata).toHaveProperty('status');
    });

    it('should export structured collector data', () => {
      const rawData = {
        request: { method: 'GET', uri: '/', status_code: 200 },
        queries: { count: 9, queries: [], n_plus_one: [] },
        exceptions: { count: 3, exceptions: [] },
        http: { count: 2, requests: [] },
      };

      const requestData: RequestData = {
        id: 'test-123',
        metadata: mockRequestMetadata(),
        tabs: {},
        raw_data: rawData,
      };

      const result = exportRequestAsJson('test-123', requestData);

      expect(result.data).toEqual(rawData);
      expect(Object.keys(result.data)).toHaveLength(4);
    });

    it('should have valid ISO export_time', () => {
      const requestData: RequestData = {
        id: 'test-123',
        metadata: mockRequestMetadata(),
        tabs: {},
        raw_data: {},
      };

      const result = exportRequestAsJson('test-123', requestData);

      // Should be valid ISO 8601 format
      expect(result.export_time).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/);
      expect(new Date(result.export_time).toString()).not.toBe('Invalid Date');
    });

    it('should handle empty collector data', () => {
      const requestData: RequestData = {
        id: 'test-123',
        metadata: mockRequestMetadata(),
        tabs: {},
        raw_data: {},
      };

      const result = exportRequestAsJson('test-123', requestData);

      expect(result.data).toEqual({});
    });

    it('should export only structured data (no HTML)', () => {
      const rawData = {
        request: { method: 'GET', uri: '/', status_code: 200 },
        queries: { count: 5, queries: [] },
        exceptions: { count: 0, exceptions: [] },
      };

      const requestData: RequestData = {
        id: 'test-123',
        metadata: mockRequestMetadata(),
        tabs: { request: '<div>HTML</div>' },
        raw_data: rawData,
      };

      const result = exportRequestAsJson('test-123', requestData);

      expect(result.data).toEqual(rawData);
      expect(result).not.toHaveProperty('html_data');
    });

    it('should throw error when raw_data is not available', () => {
      const requestData: RequestData = {
        id: 'test-legacy-123',
        metadata: mockRequestMetadata(),
        tabs: { request: '<div>HTML</div>' },
      };

      expect(() => exportRequestAsJson('test-legacy-123', requestData)).toThrow(
        'Cannot export request test-legacy-123: No structured data available'
      );
    });
  });

  describe('downloadJson', () => {
    interface MockAnchor {
      href: string;
      download: string;
      click: ReturnType<typeof vi.fn>;
    }

    let createElementSpy: ReturnType<typeof vi.spyOn>;
    let createObjectURLSpy: ReturnType<typeof vi.spyOn>;
    let revokeObjectURLSpy: ReturnType<typeof vi.spyOn>;
    let mockAnchor: MockAnchor;

    beforeEach(() => {
      // Mock anchor element
      mockAnchor = {
        href: '',
        download: '',
        click: vi.fn(),
      };

      createElementSpy = vi
        .spyOn(document, 'createElement')
        .mockReturnValue(mockAnchor as unknown as HTMLElement);
      createObjectURLSpy = vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:mock-url');
      revokeObjectURLSpy = vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {});
    });

    afterEach(() => {
      vi.restoreAllMocks();
    });

    it('should create download link with correct filename', () => {
      const data = { foo: 'bar' };
      const filename = 'test-export.json';

      downloadJson(data, filename);

      expect(createElementSpy).toHaveBeenCalledWith('a');
      expect(mockAnchor.download).toBe(filename);
    });

    it('should stringify object content', () => {
      const data = { foo: 'bar', baz: 123 };

      downloadJson(data, 'test.json');

      expect(createObjectURLSpy).toHaveBeenCalled();
      const blob = createObjectURLSpy.mock.calls[0][0];
      expect(blob).toBeInstanceOf(Blob);
      expect(blob.type).toBe('application/json');
    });

    it('should handle string content directly', () => {
      const jsonString = '{"already":"stringified"}';

      downloadJson(jsonString, 'test.json');

      expect(createObjectURLSpy).toHaveBeenCalled();
    });

    it('should trigger click on anchor element', () => {
      downloadJson({ test: 'data' }, 'test.json');

      expect(mockAnchor.click).toHaveBeenCalled();
    });

    it('should revoke object URL after download', () => {
      downloadJson({ test: 'data' }, 'test.json');

      expect(revokeObjectURLSpy).toHaveBeenCalledWith('blob:mock-url');
    });

    it('should set blob URL as href', () => {
      downloadJson({ test: 'data' }, 'test.json');

      expect(mockAnchor.href).toBe('blob:mock-url');
    });

    it('should format JSON with 2-space indentation', async () => {
      const data = { foo: 'bar', nested: { baz: 123 } };
      let blobContent = '';

      createObjectURLSpy = vi
        .spyOn(URL, 'createObjectURL')
        .mockImplementation((blob: Blob | MediaSource) => {
          // Read blob content for verification
          if (blob instanceof Blob) {
            void blob.text().then((text) => {
              blobContent = text;
            });
          }
          return 'blob:mock-url';
        });

      downloadJson(data, 'test.json');

      // Wait for blob to be read
      await new Promise((resolve) => setTimeout(resolve, 10));

      expect(blobContent).toContain('  "foo"'); // 2-space indent
      expect(blobContent).toContain('  "nested"');
    });
  });

  describe('downloadFile', () => {
    interface MockAnchor {
      href: string;
      download: string;
      click: ReturnType<typeof vi.fn>;
    }

    let createObjectURLSpy: ReturnType<typeof vi.spyOn>;
    let revokeObjectURLSpy: ReturnType<typeof vi.spyOn>;
    let mockAnchor: MockAnchor;

    beforeEach(() => {
      mockAnchor = {
        href: '',
        download: '',
        click: vi.fn(),
      };

      vi.spyOn(document, 'createElement').mockReturnValue(mockAnchor as unknown as HTMLElement);
      createObjectURLSpy = vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:mock-url');
      revokeObjectURLSpy = vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {});
    });

    afterEach(() => {
      vi.restoreAllMocks();
    });

    it('should use default MIME type application/json', () => {
      downloadFile('{"test":"data"}', 'test.json');

      const blob = createObjectURLSpy.mock.calls[0][0];
      expect(blob.type).toBe('application/json');
    });

    it('should use custom MIME type when provided', () => {
      downloadFile('a,b,c\n1,2,3', 'test.csv', 'text/csv');

      const blob = createObjectURLSpy.mock.calls[0][0];
      expect(blob.type).toBe('text/csv');
    });

    it('should set correct filename', () => {
      downloadFile('content', 'custom-file.txt', 'text/plain');

      expect(mockAnchor.download).toBe('custom-file.txt');
    });

    it('should trigger download', () => {
      downloadFile('content', 'file.txt');

      expect(mockAnchor.click).toHaveBeenCalled();
    });

    it('should revoke URL after download', () => {
      downloadFile('content', 'file.txt');

      expect(revokeObjectURLSpy).toHaveBeenCalledWith('blob:mock-url');
    });

    it('should handle CSV content', () => {
      const csvContent = 'Name,Age\nJohn,30\nJane,25';

      downloadFile(csvContent, 'data.csv', 'text/csv');

      const blob = createObjectURLSpy.mock.calls[0][0];
      expect(blob.type).toBe('text/csv');
      expect(mockAnchor.download).toBe('data.csv');
    });

    it('should handle plain text content', () => {
      const textContent = 'Some plain text content';

      downloadFile(textContent, 'notes.txt', 'text/plain');

      const blob = createObjectURLSpy.mock.calls[0][0];
      expect(blob.type).toBe('text/plain');
    });
  });
});
