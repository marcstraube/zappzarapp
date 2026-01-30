/**
 * Export Utilities for DevToolbar
 *
 * Functions for exporting request data as JSON and triggering browser downloads.
 */

import type { RequestData } from '../types/index.js';

/**
 * Export structure for DevToolbar request data
 */
export interface ExportData {
  toolbar_version: string;
  export_time: string;
  request_id: string;
  metadata: RequestData['metadata'];
  data: Record<string, unknown>; // JSON collector data
}

/**
 * Create export data structure from request
 *
 * Exports JSON collector data only.
 *
 * @param requestId Request ID
 * @param requestData Full request data from storage
 * @returns Export-ready JSON object
 *
 * @example
 * const exportData = exportRequestAsJson('req-123', requestData);
 * downloadJson(exportData, 'devtoolbar-req-123.json');
 */
export function exportRequestAsJson(requestId: string, requestData: RequestData): ExportData {
  // Validate that JSON data is available
  if (!requestData.json_data) {
    throw new Error(
      `Cannot export request ${requestId}: No JSON data available`
    );
  }

  // Remove badge_counts from metadata (redundant information)
  const { badge_counts: _badge_counts, ...exportMetadata } = requestData.metadata;

  return {
    toolbar_version: '2.1.0',
    export_time: new Date().toISOString(),
    request_id: requestId,
    metadata: exportMetadata,
    data: requestData.json_data,
  };
}

/**
 * Trigger browser download of JSON content
 *
 * Creates a Blob, generates a download URL, and triggers download via <a> element.
 *
 * @param content Object or string to download as JSON
 * @param filename Filename for download (e.g., "devtoolbar-req-123.json")
 *
 * @example
 * downloadJson({ foo: 'bar' }, 'data.json');
 * downloadJson(exportData, `devtoolbar-${requestId}.json`);
 */
export function downloadJson(content: unknown, filename: string): void {
  const json = typeof content === 'string' ? content : JSON.stringify(content, null, 2);
  const blob = new Blob([json], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

/**
 * Trigger browser download of any file content
 *
 * Generic download function for any content type.
 *
 * @param content File content (string or object)
 * @param filename Filename for download
 * @param mimeType MIME type (default: 'application/json')
 *
 * @example
 * downloadFile('{"foo":"bar"}', 'data.json', 'application/json');
 * downloadFile('a,b,c\n1,2,3', 'data.csv', 'text/csv');
 */
export function downloadFile(
  content: string,
  filename: string,
  mimeType: string = 'application/json'
): void {
  const blob = new Blob([content], { type: mimeType });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}
