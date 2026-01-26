/**
 * Sample API responses for testing
 */

export const mockHealthResponse = {
  status: 'ok',
  service: 'node-backend',
  timestamp: '2024-01-01T00:00:00.000Z',
};

export const mockReadinessResponse = {
  status: 'ok',
  timestamp: '2024-01-01T00:00:00.000Z',
  service: 'node-backend',
  environment: 'test',
  uptime: 12345,
  checks: {
    database: { status: 'ok', type: 'postgres', latency_ms: 5 },
    redis: { status: 'disabled' },
  },
};

export const mockStatusResponse = {
  status: 'ok',
  timestamp: '2024-01-01T00:00:00.000Z',
  service: 'node-backend',
  environment: 'test',
  uptime: 12345,
  checks: {
    database: { status: 'ok', type: 'postgres', latency_ms: 5 },
    redis: { status: 'disabled' },
    rabbitmq: { status: 'disabled' },
    meilisearch: { status: 'disabled' },
    elasticsearch: { status: 'disabled' },
  },
};

export const mockErrorResponse = {
  error: 'Internal Server Error',
  timestamp: '2024-01-01T00:00:00.000Z',
};

export const mock404Response = {
  error: 'Not Found',
  path: '/nonexistent',
  method: 'GET',
};
