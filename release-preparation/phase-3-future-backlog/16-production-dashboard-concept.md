# Task 16: Production-Ready Dashboard Concept

## Priority

LOW - Future Backlog

## Estimated Effort

6-8 hours (planning + documentation)

## Context

Currently, the DevDashboard is development-only with no authentication. As zappzarapp evolves into a developer platform, there's a need for production-safe monitoring and administration capabilities. Rather than creating a separate admin dashboard, this concept extends the existing DevDashboard with environment-aware features and role-based access.

### Key Questions Addressed

1. **Admin data needs in production**: What metrics and tools do admins need?
2. **Architecture decision**: Extend DevDashboard vs. separate admin dashboard?
3. **Service integration**: Which additional services provide value?
4. **Kubernetes context**: How does the dashboard adapt in K8s deployments?

## Current State

- DevDashboard exists with comprehensive development features
- Disabled in production (`ENABLE_DEV_DASHBOARD=false`)
- No authentication mechanism
- Shows sensitive data (phpinfo, env vars, logs)
- Docker-centric (container logs, service health)

## Target State

A unified dashboard with environment-specific features:

```
DevDashboard
├── Development Mode (ENV=development, no auth)
│   ├── All features (phpinfo, logs, debugging)
│   ├── Sensitive data visible
│   └── No restrictions
│
└── Production Mode (ENV=production, with auth)
    ├── Admin Role
    │   ├── Application metrics
    │   ├── Error monitoring
    │   ├── Audit log viewer
    │   ├── User management (optional)
    │   └── Selective sensitive data
    │
    └── Operator Role
        ├── Health checks
        ├── Service status
        └── Basic metrics
```

## Concept Overview

### 1. Feature Matrix

| Feature | Development | Production Admin | Production Operator | K8s Production |
|---------|-------------|------------------|---------------------|----------------|
| **System Information** |
| phpinfo() | ✅ Full | ❌ None | ❌ None | ❌ None |
| PHP Version/Extensions | ✅ | ✅ | ✅ | ✅ |
| Environment Variables | ✅ All | ✅ Masked | ❌ None | ❌ None |
| Git Status | ✅ | ✅ | ✅ | ✅ |
| **Health & Monitoring** |
| Service Health | ✅ Containers | ✅ App Services | ✅ App Services | ✅ K8s Probes |
| Database Connection | ✅ Full Details | ✅ Status Only | ✅ Status Only | ✅ Status Only |
| SSL Certificate Status | ✅ | ✅ | ✅ | ✅ |
| Resource Metrics | ✅ Container | ✅ Application | ✅ Application | 🔗 Link to K8s |
| **Application Monitoring** |
| Error Rate | ✅ | ✅ | ✅ | ✅ |
| Response Times | ✅ | ✅ | ✅ | ✅ |
| Cache Hit Rates | ✅ | ✅ | ✅ | ✅ |
| Queue Depths | ✅ | ✅ | ✅ | ✅ |
| **Logs & Debugging** |
| Application Logs | ✅ All | ✅ Error/Warning | ✅ Error Only | 🔗 Link to Loki |
| Container Logs | ✅ All | ❌ None | ❌ None | 🔗 K8s Logs |
| Real-time Log Stream | ✅ | ✅ (limited) | ❌ None | 🔗 Link to Loki |
| **Security & Audit** |
| Audit Log Viewer | ✅ | ✅ | ✅ Read-only | ✅ |
| Failed Auth Attempts | ✅ | ✅ | ✅ | ✅ |
| Security Events | ✅ | ✅ | ✅ Read-only | ✅ |
| **Code Quality** |
| Quality Tools | ✅ | ❌ None | ❌ None | ❌ None |
| Coverage Reports | ✅ | ❌ None | ❌ None | ❌ None |
| **Administration** |
| User Management | N/A | ✅ (if multi-tenant) | ❌ None | ✅ (if applicable) |
| Feature Flags | ✅ | ✅ | ❌ None | ✅ |
| Configuration Viewer | ✅ | ✅ Masked | ❌ None | ✅ Masked |
| Quick Actions | ✅ All | ✅ Safe Only | ❌ None | ❌ None |

✅ = Available | ❌ = Not available | 🔗 = Link to external tool

### 2. Authentication Strategy

#### Development Mode
```php
// config/dev-dashboard-config.php
return [
    'auth' => [
        'enabled' => getenv('APP_ENV') === 'production',
        'provider' => getenv('DEVDASH_AUTH_PROVIDER') ?: 'jwt',
    ],
];
```

No authentication required when `APP_ENV=development`.

#### Production Mode Options

**Option A: JWT-based (Recommended for API-first apps)**

```php
// Middleware: DevDashboard\Middleware\JwtAuth
$token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$payload = JWT::decode($token, $publicKey);

if (!in_array('dashboard:admin', $payload->roles)) {
    http_response_code(403);
    exit('Insufficient permissions');
}
```

**Option B: Session-based (For traditional apps)**

```php
// Middleware: DevDashboard\Middleware\SessionAuth
session_start();
if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    header('Location: /login?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}
```

**Option C: Basic Auth (Simple, for internal tools)**

```php
// Nginx config
location /_dev {
    auth_basic "Admin Access";
    auth_basic_user_file /etc/nginx/.htpasswd;
    proxy_pass http://php:9000;
}
```

**Recommendation**: Start with **Option C** (Basic Auth via Nginx) for simplicity, migrate to JWT when needed.

### 3. Role-Based Access Control

```php
// src/php/DevDashboard/Services/AuthorizationService.php
class AuthorizationService
{
    private const ROLE_PERMISSIONS = [
        'admin' => [
            'view_all_logs',
            'view_audit_logs',
            'view_sensitive_config',
            'execute_commands',
            'manage_users',
            'view_phpinfo',
        ],
        'operator' => [
            'view_health',
            'view_metrics',
            'view_error_logs',
        ],
    ];

    public function __construct(
        private readonly string $role,
    ) {}

    public function can(string $permission): bool
    {
        return in_array($permission, self::ROLE_PERMISSIONS[$this->role] ?? []);
    }
}
```

Usage in controllers:

```php
public function system(): void
{
    if (!$this->auth->can('view_phpinfo')) {
        $this->render('403');
        return;
    }

    $info = $this->systemInfoService->getBasicInfo();
    $this->render('system', ['info' => $info]);
}
```

### 4. K8s-Aware Features

```php
// src/php/DevDashboard/Services/EnvironmentDetector.php
class EnvironmentDetector
{
    public function isKubernetes(): bool
    {
        return file_exists('/var/run/secrets/kubernetes.io/serviceaccount');
    }

    public function getEnvironmentType(): string
    {
        if ($this->isKubernetes()) {
            return 'kubernetes';
        }

        if (getenv('DOCKER_CONTAINER') === 'true') {
            return 'docker';
        }

        return 'bare-metal';
    }
}
```

Adapt features based on environment:

```php
// In HealthCheckService
public function getServices(): array
{
    $env = $this->envDetector->getEnvironmentType();

    if ($env === 'kubernetes') {
        // In K8s, show application services only
        return [
            'app' => $this->checkApplicationHealth(),
            'database' => $this->checkDatabaseHealth(),
            'cache' => $this->checkCacheHealth(),
        ];
    }

    // In Docker, show container health
    return [
        'containers' => $this->checkContainerHealth(),
        'services' => $this->checkDockerServices(),
    ];
}
```

### 5. Service Integration

#### Prometheus Integration

**PHP Metrics Exporter:**

```bash
composer require promphp/prometheus_client_php
```

```php
// src/php/DevDashboard/Services/PrometheusExporter.php
class PrometheusExporter
{
    public function exportMetrics(): void
    {
        $registry = CollectorRegistry::getDefault();

        // Application metrics
        $counter = $registry->getOrRegisterCounter(
            'app',
            'http_requests_total',
            'Total HTTP requests',
            ['method', 'status']
        );

        $renderer = new RenderTextFormat();
        header('Content-Type: ' . RenderTextFormat::MIME_TYPE);
        echo $renderer->render($registry->getMetricFamilySamples());
    }
}
```

**DevDashboard Integration:**

```php
// Show Grafana links in dashboard
<div class="metric-card">
    <h3>Response Times</h3>
    <p>See detailed metrics in Grafana</p>
    <a href="<?= $grafana_url ?>/d/app-metrics" target="_blank">
        Open Dashboard
    </a>
</div>
```

#### Loki/ELK Integration

```php
// src/php/DevDashboard/Services/LogAggregatorService.php
class LogAggregatorService
{
    public function searchLogs(string $query, int $limit = 100): array
    {
        $lokiUrl = getenv('LOKI_URL');
        if (!$lokiUrl) {
            return $this->searchLocalLogs($query, $limit);
        }

        // Query Loki API
        $response = $this->httpClient->get($lokiUrl . '/loki/api/v1/query_range', [
            'query' => '{app="zappzarapp"} |= "' . $query . '"',
            'limit' => $limit,
        ]);

        return json_decode($response->getBody(), true);
    }
}
```

#### Sentry Integration

```php
// src/php/DevDashboard/Services/ErrorTrackingService.php
class ErrorTrackingService
{
    public function getRecentErrors(): array
    {
        $sentryDsn = getenv('SENTRY_DSN');
        if (!$sentryDsn) {
            return $this->getLocalErrors();
        }

        // Use Sentry API to fetch recent issues
        // Return summary for dashboard widget
        return [
            'total_errors' => 42,
            'new_today' => 5,
            'url' => 'https://sentry.io/organizations/.../issues/',
        ];
    }
}
```

### 6. New Features for Production

#### Application Metrics Widget

```php
// src/php/DevDashboard/Services/ApplicationMetricsService.php
class ApplicationMetricsService
{
    public function getMetrics(): array
    {
        return [
            'requests' => [
                'total' => $this->getRequestCount(),
                'per_minute' => $this->getRequestRate(),
                'error_rate' => $this->getErrorRate(),
            ],
            'response_times' => [
                'p50' => $this->getPercentile(50),
                'p95' => $this->getPercentile(95),
                'p99' => $this->getPercentile(99),
            ],
            'cache' => [
                'hit_rate' => $this->getCacheHitRate(),
                'memory_usage' => $this->getCacheMemoryUsage(),
            ],
        ];
    }

    private function getRequestCount(): int
    {
        // From database, Redis, or metrics store
        return (int) $this->redis->get('metrics:requests:total') ?? 0;
    }
}
```

#### Audit Log Viewer

```php
// src/php/DevDashboard/Controllers/AuditController.php
class AuditController
{
    public function index(): void
    {
        $filters = [
            'user_id' => $_GET['user_id'] ?? null,
            'action' => $_GET['action'] ?? null,
            'date_from' => $_GET['date_from'] ?? null,
        ];

        $logs = $this->auditService->getLogs($filters, limit: 50);
        $this->render('audit', ['logs' => $logs, 'filters' => $filters]);
    }
}
```

#### Feature Flags Management

```php
// src/php/DevDashboard/Services/FeatureFlagService.php
class FeatureFlagService
{
    public function getFlags(): array
    {
        // From database or config
        return [
            'new_checkout_flow' => ['enabled' => true, 'rollout' => 50],
            'dark_mode' => ['enabled' => false],
        ];
    }

    public function toggle(string $flag, bool $enabled): void
    {
        if (!$this->auth->can('manage_feature_flags')) {
            throw new UnauthorizedException();
        }

        $this->redis->set("feature_flag:$flag", $enabled ? '1' : '0');
        $this->auditLogger->log('feature_flag_toggled', [
            'flag' => $flag,
            'enabled' => $enabled,
        ]);
    }
}
```

## Implementation Roadmap

### Phase 1: Authentication & Authorization (2-3 hours)

1. Add `AuthorizationService` with role-based permissions
2. Implement Basic Auth via Nginx (simplest start)
3. Add role detection (from JWT payload or session)
4. Protect sensitive routes

**Files to modify:**
- `config/dev-dashboard-config.php` (new)
- `src/php/DevDashboard/Services/AuthorizationService.php` (new)
- `src/php/DevDashboard/Controllers/DashboardController.php` (add auth checks)
- `docker/nginx/snippets/development-tools-proxy.conf` (add auth_basic)

### Phase 2: Feature Matrix Implementation (2-3 hours)

1. Add environment detection
2. Implement feature visibility based on role + environment
3. Add data masking for production
4. Update views to show/hide based on permissions

**Files to modify:**
- `src/php/DevDashboard/Services/EnvironmentDetector.php` (new)
- `src/php/DevDashboard/Services/SystemInfoService.php` (add masking)
- `templates/dev-dashboard/*.html.twig` (conditional rendering)

### Phase 3: Service Integration (2-3 hours)

1. Add Prometheus metrics endpoint
2. Integrate Grafana links
3. Add Loki/ELK log search (optional)
4. Add Sentry error summary widget (optional)

**Files to add:**
- `src/php/DevDashboard/Services/PrometheusExporter.php`
- `src/php/DevDashboard/Services/LogAggregatorService.php`
- `src/php/DevDashboard/Services/ErrorTrackingService.php`
- `src/php/DevDashboard/Controllers/MetricsController.php`

### Phase 4: K8s Awareness (1-2 hours)

1. Detect K8s environment
2. Adapt health checks for K8s
3. Add links to K8s Dashboard, Grafana, etc.
4. Hide Docker-specific features in K8s

**Files to modify:**
- `src/php/DevDashboard/Services/HealthCheckService.php`
- `templates/dev-dashboard/health.html.twig`

### Phase 5: New Production Features (3-4 hours)

1. Application metrics widget
2. Audit log viewer
3. Feature flags management
4. User management (optional, if multi-tenant)

**Files to add:**
- `src/php/DevDashboard/Services/ApplicationMetricsService.php`
- `src/php/DevDashboard/Controllers/AuditController.php`
- `src/php/DevDashboard/Services/FeatureFlagService.php`
- `templates/dev-dashboard/audit.html.twig`
- `templates/dev-dashboard/feature-flags.html.twig`

## Configuration Examples

### Environment Variables

```bash
# .env - Production configuration
APP_ENV=production
ENABLE_DEV_DASHBOARD=true                    # Enable in production
DEVDASH_AUTH_ENABLED=true                    # Require authentication
DEVDASH_AUTH_PROVIDER=basic                  # Options: basic, jwt, session
DEVDASH_ADMIN_USERNAME=admin                 # For basic auth
DEVDASH_ADMIN_PASSWORD_HASH=<bcrypt-hash>    # For basic auth

# Service integration (optional)
GRAFANA_URL=https://grafana.internal.example.com
LOKI_URL=https://loki.internal.example.com
SENTRY_DSN=https://...@sentry.io/...
```

### Nginx Configuration

```nginx
# docker/nginx/snippets/development-tools-proxy.conf
location /_dev {
    # Basic auth in production only
    if ($app_env = "production") {
        auth_basic "Dashboard Admin Access";
        auth_basic_user_file /run/secrets/devdash_htpasswd;
    }

    # Rate limiting in production
    limit_req zone=dashboard burst=10 nodelay;

    proxy_pass http://php:9000;
    include snippets/proxy-common.conf;
}
```

### Docker Secret

```bash
# Create htpasswd file for basic auth
docker run --rm -it httpd:alpine htpasswd -nB admin > devdash_htpasswd

# Add to compose.yaml
secrets:
  devdash_htpasswd:
    file: ./docker/secrets/devdash_htpasswd
```

### Kubernetes Deployment

```yaml
# kubernetes/templates/devdashboard/ingress.yaml
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: devdashboard-admin
  namespace: production
  annotations:
    nginx.ingress.kubernetes.io/auth-type: basic
    nginx.ingress.kubernetes.io/auth-secret: devdash-basic-auth
    nginx.ingress.kubernetes.io/auth-realm: "Dashboard Admin"
spec:
  ingressClassName: nginx
  rules:
    - host: admin.example.com
      http:
        paths:
          - path: /_dev
            pathType: Prefix
            backend:
              service:
                name: zappzarapp-php
                port:
                  number: 9000
  tls:
    - hosts:
        - admin.example.com
      secretName: admin-tls
---
apiVersion: v1
kind: Secret
metadata:
  name: devdash-basic-auth
  namespace: production
type: Opaque
data:
  auth: <base64-encoded-htpasswd>
```

## Architecture Decision Record

### ADR: Unified Dashboard with Environment-Specific Features

**Status**: Proposed

**Context**:
- Existing DevDashboard provides development tools
- Need for production monitoring and admin capabilities
- Options: Extend DevDashboard vs. create separate admin dashboard

**Decision**:
Extend DevDashboard for production use with role-based access instead of creating a separate admin dashboard.

**Rationale**:
1. **Code Reuse**: Existing health checks, services, DI container can be leveraged
2. **Single Maintenance Point**: No duplicate code, consistent UI/UX
3. **Gradual Enhancement**: Can add features incrementally
4. **Flexibility**: Environment-aware features adapt to deployment context
5. **Developer Experience**: Familiar interface across environments

**Consequences**:

*Positive*:
- Faster implementation (build on existing foundation)
- Consistent tooling from development to production
- Easier to maintain and extend
- Natural separation via environment + roles

*Negative*:
- More complex routing logic (environment + role checks)
- Risk of accidentally exposing sensitive data if misconfigured
- Single codebase must serve multiple use cases

**Mitigation**:
- Strong default-deny security stance
- Comprehensive tests for role-based access
- Environment detection with safe fallbacks
- Clear documentation on production configuration

**Alternatives Considered**:

1. **Separate AdminDashboard**
   - Pros: Clear separation, simpler security model
   - Cons: Duplicate code, maintenance burden, inconsistent UX
   - Rejected: Violates DRY principle

2. **No Dashboard, Use External Tools Only**
   - Pros: Leverage existing tools (Grafana, K8s Dashboard)
   - Cons: No application-specific insights, fragmented experience
   - Rejected: External tools don't provide app-level context

3. **Keep DevDashboard Development-Only**
   - Pros: Simplest security model
   - Cons: Admins have no integrated monitoring
   - Rejected: Leaves gap in production tooling

## Testing Strategy

### Security Tests

```php
// tests/php/DevDashboard/Security/AuthorizationTest.php
class AuthorizationTest extends TestCase
{
    public function test_admin_can_view_phpinfo(): void
    {
        $auth = new AuthorizationService('admin');
        $this->assertTrue($auth->can('view_phpinfo'));
    }

    public function test_operator_cannot_view_phpinfo(): void
    {
        $auth = new AuthorizationService('operator');
        $this->assertFalse($auth->can('view_phpinfo'));
    }

    public function test_sensitive_data_masked_in_production(): void
    {
        putenv('APP_ENV=production');
        $service = new SystemInfoService();
        $env = $service->getEnvironmentVariables();

        $this->assertStringContainsString('***', $env['DB_PASSWORD']);
    }
}
```

### Integration Tests

```typescript
// tests/node/backend/integration/devdashboard-production.test.ts
describe('DevDashboard Production Mode', () => {
  it('requires authentication in production', async () => {
    process.env.APP_ENV = 'production';

    const response = await request(app).get('/_dev');

    expect(response.status).toBe(401);
  });

  it('shows limited features for operator role', async () => {
    const token = generateToken({ role: 'operator' });

    const response = await request(app)
      .get('/_dev/system')
      .set('Authorization', `Bearer ${token}`);

    expect(response.status).toBe(403);
  });
});
```

## Documentation Updates

1. **Update `.zappzarapp/docs/development/DEV-DASHBOARD.md`**:
   - Add "Production Mode" section
   - Document authentication setup
   - Add role-based feature matrix
   - K8s deployment instructions

2. **Create `.zappzarapp/docs/operations/ADMIN-DASHBOARD.md`**:
   - Production configuration guide
   - Security best practices
   - Service integration setup
   - Troubleshooting

3. **Update `.zappzarapp/docs/infrastructure/KUBERNETES.md`**:
   - Add DevDashboard Ingress example
   - Document K8s-aware features

## Related Tasks

- **08-devdashboard-features.md**: Implements features from this concept
- **09-livelogs-websocket.md**: Real-time log streaming for dashboard
- **10-devdashboard-code-examples.md**: Interactive code examples in dashboard

## Open Questions

1. **JWT Implementation**: Should we provide JWT validation out-of-the-box or document integration points?
   - Recommendation: Document integration, let users bring their own JWT library

2. **Multi-tenancy**: Should user management be included in Phase 1?
   - Recommendation: Make it optional, document how to add it

3. **Metrics Storage**: Where to store application metrics (Redis, Prometheus, database)?
   - Recommendation: Support multiple backends, start with Redis

4. **Rate Limiting**: Should dashboard have separate rate limits?
   - Recommendation: Yes, add `limit_req zone=dashboard` in Nginx

## Success Criteria

- [ ] Dashboard works in production with authentication
- [ ] Role-based features are properly enforced
- [ ] Sensitive data is masked appropriately
- [ ] K8s deployments show relevant features only
- [ ] External service integration (Grafana, Loki) works
- [ ] Documentation covers all configuration options
- [ ] Tests verify security model
- [ ] Performance impact < 5ms per request

## Next Steps

1. Review and refine this concept document
2. Create ADR for architecture decision
3. Break down into smaller implementation tasks
4. Start with Phase 1 (Authentication & Authorization)
