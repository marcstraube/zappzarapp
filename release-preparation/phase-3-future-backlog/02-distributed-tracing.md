# Task 02: Add Distributed Tracing (OpenTelemetry)

## Priority

LOW - Future Backlog

## Estimated Effort

6-8 hours

## Context

Distributed tracing helps debug complex request flows across services. As the
boilerplate supports multiple services (PHP, Node.js, databases), tracing would
help users understand performance bottlenecks and debug issues.

## Current State

- No tracing instrumentation
- Logging is per-service
- No correlation IDs across services

## Target State

1. Add OpenTelemetry instrumentation for PHP
2. Add OpenTelemetry instrumentation for Node.js
3. Include Jaeger for local trace visualization
4. Propagate trace context across services

## Implementation Outline

### PHP (OpenTelemetry)

```bash
composer require open-telemetry/sdk open-telemetry/exporter-otlp
```

**Auto-instrumentation:**

```php
use OpenTelemetry\SDK\Trace\TracerProviderFactory;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;

$transport = (new OtlpHttpTransportFactory())->create('http://jaeger:4318/v1/traces');
$tracerProvider = (new TracerProviderFactory())->create($transport);
```

### Node.js (OpenTelemetry)

```bash
pnpm add @opentelemetry/sdk-node @opentelemetry/auto-instrumentations-node @opentelemetry/exporter-trace-otlp-http
```

**Tracing setup:**

```typescript
import { NodeSDK } from '@opentelemetry/sdk-node';
import { getNodeAutoInstrumentations } from '@opentelemetry/auto-instrumentations-node';
import { OTLPTraceExporter } from '@opentelemetry/exporter-trace-otlp-http';

const sdk = new NodeSDK({
  traceExporter: new OTLPTraceExporter({ url: 'http://jaeger:4318/v1/traces' }),
  instrumentations: [getNodeAutoInstrumentations()],
});
sdk.start();
```

### Jaeger Service

```yaml
# compose.monitoring.yaml
services:
  jaeger:
    image: jaegertracing/all-in-one:1.54
    ports:
      - '16686:16686'  # UI
      - '4318:4318'    # OTLP HTTP
    environment:
      - COLLECTOR_OTLP_ENABLED=true
```

## Notes

- OpenTelemetry is the industry standard (CNCF project)
- Auto-instrumentation reduces manual work
- Jaeger provides excellent local development experience
- Production can export to Datadog, New Relic, etc.
