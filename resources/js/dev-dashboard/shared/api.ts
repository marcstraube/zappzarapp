/**
 * Shared RequestInterceptor factory for the DevDashboard API
 *
 * The interceptor validates absolute URLs only — resolve the dashboard's
 * relative API paths against the current origin. Timeout is per instance, so
 * pages create separate instances for quick lookups and long-running actions
 * (backup creation, coverage/docs generation).
 */

import {
  RequestInterceptor,
  type RequestInterceptorInstance,
} from '@zappzarapp/browser-utils/request';

export function createDashboardApi(timeout = 10_000): RequestInterceptorInstance {
  return RequestInterceptor.create({
    baseUrl: window.location.origin,
    timeout,
    expectedContentType: 'application/json',
  });
}
