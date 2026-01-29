# 27: PHP CSP Nonce Helper

## Context

Content Security Policy (CSP) with nonce-based inline script/style protection is now
active in `public/index.php`. Currently, templates must use the global constant
`CSP_NONCE` to access the nonce value. A dedicated helper would improve developer
experience and provide a cleaner, more extensible API.

## Current Implementation (26. Jan 2026)

**Location:** `public/index.php:74-123`

```php
// Generate nonce
$nonce = base64_encode(random_bytes(16));

// Set CSP header
header("Content-Security-Policy: {$csp_header}");

// Expose as constant
define('CSP_NONCE', $nonce);
```

**Template Usage:**
```html
<script nonce="<?= CSP_NONCE ?>">console.log('test')</script>
<style nonce="<?= CSP_NONCE ?>">body { margin: 0; }</style>
```

## Problems with Current Approach

1. **Global Namespace Pollution**
   - `CSP_NONCE` constant is globally defined
   - No namespacing or encapsulation

2. **Not Extensible**
   - Hard to add multiple nonces (e.g., script-nonce, style-nonce)
   - Difficult to extend with additional CSP features

3. **Template Verbosity**
   - `<?= CSP_NONCE ?>` is repetitive
   - No IDE autocompletion for constant

4. **No Central Management**
   - CSP logic scattered in entry point
   - Hard to test or modify behavior

## Proposed Solution

Create a `CspNonceHelper` class with clean API:

### Implementation

**File:** `src/php/App/Security/CspNonceHelper.php`

```php
<?php

declare(strict_types=1);

namespace App\Security;

final class CspNonceHelper
{
    private static ?string $nonce = null;

    /**
     * Generate and store nonce for current request
     */
    public static function generate(): string
    {
        if (self::$nonce === null) {
            self::$nonce = base64_encode(random_bytes(16));
        }
        return self::$nonce;
    }

    /**
     * Get current nonce (generates if not exists)
     */
    public static function get(): string
    {
        return self::$nonce ?? self::generate();
    }

    /**
     * Build CSP header with nonce (auto-detects environment)
     */
    public static function buildCspHeader(): string
    {
        $isDevelopment = getenv('ENV') === 'development';
        return $isDevelopment
            ? self::buildDevelopmentCspHeader()
            : self::buildProductionCspHeader();
    }

    /**
     * Build development CSP header (allows unsafe-eval for Vite HMR)
     */
    public static function buildDevelopmentCspHeader(): string
    {
        $nonce = self::get();

        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic' 'unsafe-eval'",
            "style-src 'self' 'nonce-{$nonce}'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self' wss://localhost:8443 https://localhost:8443",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        return implode('; ', $directives);
    }

    /**
     * Build production CSP header (strict, no unsafe-*)
     */
    public static function buildProductionCspHeader(): string
    {
        $nonce = self::get();

        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic'",
            "style-src 'self' 'nonce-{$nonce}'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        return implode('; ', $directives);
    }

    /**
     * Reset nonce (for testing)
     */
    public static function reset(): void
    {
        self::$nonce = null;
    }
}
```

### Global Helper Function (Optional)

**File:** `src/php/App/helpers.php` (or include in `index.php`)

```php
<?php

use App\Security\CspNonceHelper;

if (!function_exists('nonce')) {
    function nonce(): string
    {
        return CspNonceHelper::get();
    }
}
```

### Usage in public/index.php

```php
use App\Security\CspNonceHelper;

// Generate nonce and build CSP (auto-detects environment)
$cspHeader = CspNonceHelper::buildCspHeader();

// Send CSP header
header("Content-Security-Policy: {$cspHeader}");

// Optional: Keep constant for backwards compatibility
define('CSP_NONCE', CspNonceHelper::get());
```

### Template Usage (Improved)

```html
<!-- Option 1: Using helper function -->
<script nonce="<?= nonce() ?>">console.log('test')</script>

<!-- Option 2: Using class directly -->
<script nonce="<?= \App\Security\CspNonceHelper::get() ?>">test</script>

<!-- Option 3: Backwards compatible -->
<script nonce="<?= CSP_NONCE ?>">console.log('test')</script>
```

## Benefits

### Developer Experience
- ✅ **Cleaner API**: `nonce()` instead of `CSP_NONCE`
- ✅ **IDE Support**: Autocompletion for function/class
- ✅ **Namespaced**: No global constant pollution

### Architecture
- ✅ **Testable**: Can unit test helper independently
- ✅ **Extensible**: Easy to add script-specific vs style-specific nonces
- ✅ **Centralized**: All CSP logic in one place
- ✅ **Reusable**: Can use in middleware, controllers, etc.

### Future Extensions
- Multiple nonces per request (script vs style)
- CSP violation reporting endpoint
- Nonce rotation policies
- Integration with template engines (Twig, Blade)

## Migration Path

### Phase 1: Add Helper (Non-Breaking)
1. Create `CspNonceHelper` class
2. Create `nonce()` helper function
3. Update `index.php` to use helper
4. Keep `CSP_NONCE` constant for backwards compatibility
5. Add unit tests

### Phase 2: Update Templates (Breaking)
1. Replace `CSP_NONCE` with `nonce()` in all templates
2. Remove `CSP_NONCE` constant
3. Update documentation

## Files to Create

### New Files
- `src/php/App/Security/CspNonceHelper.php` - Main helper class
- `src/php/App/helpers.php` - Global helper functions (optional)
- `tests/php/App/Unit/Security/CspNonceHelperTest.php` - Unit tests

### Files to Modify
- `public/index.php` - Use helper instead of inline logic
- `composer.json` - Add `src/php/App/helpers.php` to autoload files (if created)

### Templates to Update (Phase 2)
- Search for `CSP_NONCE` usage: `grep -r "CSP_NONCE" templates/`
- Replace with `nonce()` helper

## Testing Requirements

### Unit Tests
```php
public function testGenerateCreatesValidNonce(): void
{
    $nonce = CspNonceHelper::generate();
    $this->assertNotEmpty($nonce);
    $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/=]+$/', $nonce);
}

public function testNonceIsSameWithinRequest(): void
{
    $nonce1 = CspNonceHelper::get();
    $nonce2 = CspNonceHelper::get();
    $this->assertSame($nonce1, $nonce2);
}

public function testBuildCspHeaderDevelopment(): void
{
    $header = CspNonceHelper::buildCspHeader(true);
    $this->assertStringContainsString("'unsafe-eval'", $header);
    $this->assertStringContainsString('wss://localhost:8443', $header);
}

public function testBuildCspHeaderProduction(): void
{
    $header = CspNonceHelper::buildCspHeader(false);
    $this->assertStringNotContainsString("'unsafe-eval'", $header);
    $this->assertStringNotContainsString('wss://', $header);
}
```

### Integration Test
- Verify CSP header is set correctly in dev/prod
- Verify inline scripts with nonce work
- Verify inline scripts without nonce are blocked

## Definition of Done

- [ ] `CspNonceHelper` class created with full functionality
- [ ] Global `nonce()` helper function available
- [ ] `public/index.php` refactored to use helper
- [ ] Unit tests with 100% coverage
- [ ] Documentation updated (README, inline comments)
- [ ] Backwards compatible (CSP_NONCE still available)
- [ ] No breaking changes to existing templates

## Priority

**Medium** - Nice-to-have improvement for developer experience.
Not blocking for v1.0 release, but good enhancement for v1.1.

## References

- Current implementation: `public/index.php:74-123`
- CSP Documentation: https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP
- Related: Security headers in `docker/nginx/snippets/security-headers.conf`

## Notes

- Consider integration with template engines (Twig/Blade) in future
- Could extend to other security headers (CSRF tokens, etc.)
- Keep implementation simple - don't over-engineer
