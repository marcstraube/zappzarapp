# Task 04: Add Load Testing Configuration

## Priority

MEDIUM - Post-Release v1.1

## Estimated Effort

2-3 hours

## Context

During the Performance & Operations review, it was noted that while profiling
and benchmarking tools are documented, there's no included load testing
configuration. Adding k6 or Artillery configuration would help users:

- Establish performance baselines
- Identify bottlenecks before production
- Validate infrastructure capacity
- Regression test performance

## Current State

- PERFORMANCE.md mentions wrk and ab for benchmarking
- No load testing configuration included
- No baseline performance expectations documented

## Target State

1. Add k6 load testing configuration
2. Include sample test scenarios
3. Document baseline expectations
4. Add Makefile targets for easy execution

## Implementation Steps

### Step 1: Create Load Test Directory

```bash
mkdir -p tests/load
```

### Step 2: Create k6 Configuration

**Create `tests/load/k6.config.js`:**

```javascript
/**
 * k6 Load Testing Configuration
 *
 * Run with: make test-load
 * Or directly: k6 run tests/load/scenarios/smoke.js
 */

export const options = {
  // Default thresholds
  thresholds: {
    http_req_duration: ['p(95)<500'], // 95% of requests under 500ms
    http_req_failed: ['rate<0.01'],   // Less than 1% failures
  },
};

export const baseUrl = __ENV.BASE_URL || 'http://localhost:8080';

export function getHeaders() {
  return {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };
}
```

### Step 3: Create Test Scenarios

**Create `tests/load/scenarios/smoke.js`:**

```javascript
/**
 * Smoke Test
 *
 * Quick validation that the system works under minimal load.
 * Duration: ~1 minute
 * VUs: 1-5
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { baseUrl, getHeaders } from '../k6.config.js';

export const options = {
  vus: 5,
  duration: '1m',
  thresholds: {
    http_req_duration: ['p(95)<200'],
    http_req_failed: ['rate<0.01'],
  },
};

export default function () {
  // Health check
  const healthRes = http.get(`${baseUrl}/health`);
  check(healthRes, {
    'health status is 200': (r) => r.status === 200,
    'health response is ok': (r) => JSON.parse(r.body).overall_status === 'ok',
  });

  // Homepage
  const homeRes = http.get(`${baseUrl}/`);
  check(homeRes, {
    'homepage status is 200': (r) => r.status === 200,
    'homepage loads in <500ms': (r) => r.timings.duration < 500,
  });

  sleep(1);
}
```

**Create `tests/load/scenarios/load.js`:**

```javascript
/**
 * Load Test
 *
 * Sustained load to test normal operation.
 * Duration: ~5 minutes
 * VUs: 10-50
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { baseUrl, getHeaders } from '../k6.config.js';

export const options = {
  stages: [
    { duration: '1m', target: 20 },  // Ramp up to 20 VUs
    { duration: '3m', target: 50 },  // Stay at 50 VUs
    { duration: '1m', target: 0 },   // Ramp down
  ],
  thresholds: {
    http_req_duration: ['p(95)<500', 'p(99)<1000'],
    http_req_failed: ['rate<0.05'],
  },
};

export default function () {
  // Simulate typical user flow
  const pages = ['/', '/health'];

  for (const page of pages) {
    const res = http.get(`${baseUrl}${page}`);
    check(res, {
      [`${page} status 200`]: (r) => r.status === 200,
    });
    sleep(Math.random() * 2 + 1); // 1-3 seconds between requests
  }
}
```

**Create `tests/load/scenarios/stress.js`:**

```javascript
/**
 * Stress Test
 *
 * Push the system to find breaking points.
 * Duration: ~10 minutes
 * VUs: 50-200+
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { baseUrl } from '../k6.config.js';

export const options = {
  stages: [
    { duration: '2m', target: 50 },   // Normal load
    { duration: '2m', target: 100 },  // High load
    { duration: '2m', target: 150 },  // Very high load
    { duration: '2m', target: 200 },  // Peak load
    { duration: '2m', target: 0 },    // Recovery
  ],
  thresholds: {
    http_req_duration: ['p(95)<2000'], // More lenient under stress
    http_req_failed: ['rate<0.10'],    // Allow some failures under stress
  },
};

export default function () {
  const res = http.get(`${baseUrl}/health`);
  check(res, {
    'status is 200': (r) => r.status === 200,
  });
  sleep(0.5);
}
```

**Create `tests/load/scenarios/spike.js`:**

```javascript
/**
 * Spike Test
 *
 * Test sudden traffic spikes.
 * Duration: ~5 minutes
 * VUs: 0 -> 100 -> 0 (sudden)
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { baseUrl } from '../k6.config.js';

export const options = {
  stages: [
    { duration: '30s', target: 10 },   // Warm up
    { duration: '10s', target: 100 },  // Spike!
    { duration: '1m', target: 100 },   // Stay at spike
    { duration: '10s', target: 10 },   // Back to normal
    { duration: '2m', target: 10 },    // Recovery observation
    { duration: '30s', target: 0 },    // Ramp down
  ],
  thresholds: {
    http_req_duration: ['p(95)<1000'],
    http_req_failed: ['rate<0.05'],
  },
};

export default function () {
  const res = http.get(`${baseUrl}/`);
  check(res, {
    'status is 200': (r) => r.status === 200,
  });
  sleep(0.3);
}
```

### Step 4: Add Makefile Targets

Add to `Makefile`:

```makefile
##@ Load Testing

.PHONY: test-load-smoke test-load test-load-stress test-load-spike

test-load-smoke: ## Run smoke test (quick validation)
	@echo "Running smoke test..."
	k6 run tests/load/scenarios/smoke.js

test-load: ## Run load test (sustained load)
	@echo "Running load test..."
	k6 run tests/load/scenarios/load.js

test-load-stress: ## Run stress test (find breaking points)
	@echo "Running stress test..."
	k6 run tests/load/scenarios/stress.js

test-load-spike: ## Run spike test (sudden traffic)
	@echo "Running spike test..."
	k6 run tests/load/scenarios/spike.js

test-load-all: test-load-smoke test-load test-load-stress test-load-spike ## Run all load tests
```

### Step 5: Add Documentation

**Create `tests/load/README.md`:**

```markdown
# Load Testing

This directory contains k6 load testing configurations.

## Prerequisites

Install k6:

```bash
# macOS
brew install k6

# Linux
sudo gpg -k
sudo gpg --no-default-keyring --keyring /usr/share/keyrings/k6-archive-keyring.gpg \
  --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69
echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" \
  | sudo tee /etc/apt/sources.list.d/k6.list
sudo apt-get update
sudo apt-get install k6

# Docker
docker run -i grafana/k6 run - <script.js
```

## Test Scenarios

| Scenario | Duration | VUs | Purpose |
|----------|----------|-----|---------|
| `smoke` | 1 min | 5 | Quick validation |
| `load` | 5 min | 20-50 | Normal operation |
| `stress` | 10 min | 50-200 | Find limits |
| `spike` | 5 min | 10-100 | Traffic spikes |

## Running Tests

```bash
# Start application first
make up

# Run smoke test
make test-load-smoke

# Run full load test
make test-load

# Run stress test (caution: high load)
make test-load-stress

# Custom base URL
BASE_URL=https://staging.example.com k6 run tests/load/scenarios/load.js
```

## Baseline Expectations

Based on default configuration (single server):

| Metric | Smoke | Load | Stress |
|--------|-------|------|--------|
| p95 response time | <200ms | <500ms | <2000ms |
| Error rate | <1% | <5% | <10% |
| Throughput | N/A | ~100 req/s | ~300 req/s |

## Interpreting Results

```
✓ checks.........................: 100.00% ✓ 1000 ✗ 0
✓ http_req_duration..............: avg=45ms min=10ms max=200ms p(95)=100ms
```

- **checks**: Assertion pass rate
- **http_req_duration**: Response time statistics
- **p(95)**: 95th percentile (95% of requests are faster)

## Output Formats

```bash
# JSON output
k6 run --out json=results.json tests/load/scenarios/load.js

# InfluxDB (for Grafana)
k6 run --out influxdb=http://localhost:8086/k6 tests/load/scenarios/load.js
```
```

### Step 6: Update Documentation Index

Add to `.zappzarapp/docs/infrastructure/PERFORMANCE.md`:

```markdown
## Load Testing

See [tests/load/README.md](../../../tests/load/README.md) for k6 load testing.

Quick start:

```bash
make test-load-smoke  # Quick validation
make test-load        # Sustained load test
```
```

## Verification

1. **k6 installed:**

   ```bash
   k6 version
   ```

2. **Smoke test runs:**

   ```bash
   make up
   make test-load-smoke
   ```

3. **Results are meaningful:**
   - All checks pass
   - Response times within thresholds
   - No unexpected errors

## Files to Create

1. `tests/load/k6.config.js` - Base configuration
2. `tests/load/scenarios/smoke.js` - Smoke test
3. `tests/load/scenarios/load.js` - Load test
4. `tests/load/scenarios/stress.js` - Stress test
5. `tests/load/scenarios/spike.js` - Spike test
6. `tests/load/README.md` - Documentation
7. `Makefile` - Add targets

## Notes

- k6 is preferred over Artillery for its performance and JavaScript DSL
- Tests assume default ports (8080); configure BASE_URL for other environments
- Stress tests should be run on staging, not production
- Results can be sent to InfluxDB/Grafana for visualization
