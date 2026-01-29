# 28: Frontend Scaffolder CSP Compatibility

## Context

With strict Content Security Policy (CSP) enabled in production and development (see task 27),
all frontend scaffolders must generate CSP-compatible code. Currently, Next.js scaffolder
generates JSX inline styles which violate CSP `style-src 'self'` and would require
`'unsafe-inline'` to function.

**zappzarapp Philosophy:** Security by design - suitable for small/private and enterprise apps.

## Current Scaffolder Status

| Framework     | Location                                    | Styling Approach        | CSP Status |
| ------------- | ------------------------------------------- | ----------------------- | ---------- |
| Next.js       | `docker/node/frontend-patches/next.*.sh`    | JSX inline styles       | ❌ Blocked  |
| Nuxt          | `docker/node/frontend-patches/nuxt.*.sh`    | `<style scoped>` (SFC)  | ✅ External |
| SvelteKit     | `docker/node/frontend-patches/sveltekit.*.sh` | `<style>` (Svelte)    | ✅ External |
| React Router  | `docker/node/frontend-patches/remix.*.sh`   | Tailwind classes        | ✅ External |

## Problem: Next.js Inline Styles

**Current Next.js Scaffolder Output:**

`app/layout.tsx` (Line 79):
```tsx
<body style={{ margin: 0, fontFamily: '-apple-system, BlinkMacSystemFont, ...' }}>
```

`app/page.tsx` (Multiple locations):
```tsx
<div style={{ maxWidth: '800px', margin: '0 auto', padding: '2rem' }}>
<h1 style={{ color: '#0070f3', fontSize: '2.5rem' }}>
<div style={{ background: '#f8f9fa', borderRadius: '8px', padding: '1rem' }}>
```

**CSP Violation:**
```
Content-Security-Policy: style-src 'self' 'nonce-xxx'
→ Inline styles blocked (requires 'unsafe-inline')
```

## Why Nonce-Based Solution Doesn't Work

**Architecture constraint:**
```
Request → Nginx (generates CSP nonce) → Next.js SSR (generates HTML)
```

**Problems with Next.js Middleware Nonce:**
1. ❌ CSP management split between Nginx and Next.js (architecture inconsistency)
2. ❌ PHP app uses Nginx CSP, Next.js uses own middleware (dual systems)
3. ❌ Complexity for users (need to understand two CSP systems)
4. ❌ Performance overhead (nonce generation per request in Node)
5. ❌ Next.js would override Nginx CSP headers (conflict)

**Nonce propagation from Nginx to Next.js is theoretically possible but:**
- Requires complex Nginx header forwarding
- Next.js middleware must parse and use forwarded nonce
- Still creates dual CSP systems (one for PHP, one for Next.js)
- Violates "Security by Design" principle (too complex to understand)

## Proposed Solution: CSS Modules

**Use Next.js standard CSS Modules** - external CSS files, CSP-compatible, scalable.

### Implementation for Next.js Scaffolder

**File:** `docker/node/frontend-patches/next.post-install.sh`

#### 1. Create Global Styles

```bash
# Create app/globals.css
cat > app/globals.css << 'EOF'
/**
 * Global Styles - zappzarapp Next.js Frontend
 * Minimal global resets, component styles use CSS Modules
 */

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  line-height: 1.5;
  color: #333;
}

code {
  font-family: 'SF Mono', Monaco, 'Courier New', monospace;
  background: rgba(59, 130, 246, 0.1);
  padding: 0.2rem 0.4rem;
  border-radius: 0.25rem;
  font-size: 0.875em;
}

pre code {
  display: block;
  padding: 1rem;
  overflow-x: auto;
  background: #1a1a2e;
  color: #0070f3;
}
EOF
```

#### 2. Update layout.tsx

```tsx
import type { Metadata } from 'next';
import './globals.css';

export const metadata: Metadata = {
  title: 'zappzarapp Frontend',
  description: 'Next.js SSR Frontend running on Node.js',
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
```

#### 3. Create page.module.css

```bash
cat > app/page.module.css << 'EOF'
/**
 * Home Page Styles
 * CSS Modules provide scoped styles, CSP-compatible
 */

.main {
  max-width: 800px;
  margin: 0 auto;
  padding: 2rem;
}

.header {
  text-align: center;
  margin-bottom: 3rem;
}

.title {
  color: #0070f3;
  font-size: 2.5rem;
  margin-bottom: 0.5rem;
}

.subtitle {
  color: #666;
  font-size: 1.1rem;
}

.section {
  margin-bottom: 2rem;
}

.sectionTitle {
  color: #333;
  font-size: 1.3rem;
  margin-bottom: 1rem;
  border-bottom: 2px solid #0070f3;
  padding-bottom: 0.5rem;
}

.statusCard {
  background: #f8f9fa;
  border-radius: 8px;
  padding: 1rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.statusIndicator {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: #0070f3;
  box-shadow: 0 0 8px #0070f3;
}

.apiCard {
  background: #f8f9fa;
  border-radius: 8px;
  padding: 1rem;
}

.apiCardOffline {
  composes: apiCard;
  background: #fef2f2;
  border: 1px solid #fecaca;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.statusIndicatorOffline {
  composes: statusIndicator;
  background: #ef4444;
  box-shadow: 0 0 8px #ef4444;
}

.apiPre {
  background: #1a1a2e;
  color: #0070f3;
  padding: 1rem;
  border-radius: 4px;
  overflow: auto;
  font-size: 0.875rem;
}
EOF
```

#### 4. Update page.tsx

```tsx
import styles from './page.module.css';

async function getHealth() {
  try {
    const res = await fetch('http://localhost:3000/health', {
      cache: 'no-store',
    });
    if (!res.ok) return null;
    return res.json();
  } catch {
    return null;
  }
}

export default async function Home() {
  const health = await getHealth();

  return (
    <main className={styles.main}>
      <header className={styles.header}>
        <h1 className={styles.title}>zappzarapp Frontend</h1>
        <p className={styles.subtitle}>
          Next.js SSR Frontend running on Node.js
        </p>
      </header>

      <section className={styles.section}>
        <h2 className={styles.sectionTitle}>Frontend Status</h2>
        <div className={styles.statusCard}>
          <span className={styles.statusIndicator}></span>
          <span>Next.js SSR Active</span>
        </div>
      </section>

      <section className={styles.section}>
        <h2 className={styles.sectionTitle}>Backend API Status</h2>
        {health ? (
          <div className={styles.apiCard}>
            <pre className={styles.apiPre}>
              {JSON.stringify(health, null, 2)}
            </pre>
          </div>
        ) : (
          <div className={styles.apiCardOffline}>
            <span className={styles.statusIndicatorOffline}></span>
            <span>Backend not available</span>
          </div>
        )}
      </section>
    </main>
  );
}
```

## Benefits

### CSP Compliance
- ✅ No inline styles → CSP `style-src 'self'` satisfied
- ✅ External CSS files loaded via `<link>` tags
- ✅ Consistent with other frameworks (Nuxt/SvelteKit)
- ✅ No Nonce complexity required

### Developer Experience
- ✅ **Next.js Best Practice** - CSS Modules are the recommended approach
- ✅ **IDE Support** - TypeScript autocomplete for class names
- ✅ **Scoped Styles** - No global namespace pollution
- ✅ **Maintainable** - Styles co-located with components

### Enterprise Ready
- ✅ **Scalable** - Each component can have its own `.module.css`
- ✅ **Testable** - Styles can be tested independently
- ✅ **Consistent Architecture** - All CSP management in Nginx
- ✅ **Security by Design** - No CSP workarounds needed

## Verification

### CSP Header Check
```bash
# Test that generated Next.js app respects CSP
curl -I https://localhost:8443/ | grep Content-Security-Policy

# Should show:
# Content-Security-Policy: style-src 'self' 'nonce-xxx'
# (without 'unsafe-inline')
```

### Browser Console Check
```javascript
// Should show no CSP violations
// Chrome DevTools → Console → Filter: "Content Security Policy"
```

### Visual Inspection
- Page should render correctly with styles
- No missing styles or broken layout
- Developer toolbar should show external CSS loaded

## Files to Modify

### Scaffolder Scripts
- `docker/node/frontend-patches/next.post-install.sh` - Update template generation

### Files to Create (by scaffolder)
- `app/globals.css` - Global styles and resets
- `app/page.module.css` - Home page scoped styles
- `app/layout.tsx` - Import globals.css
- `app/page.tsx` - Use CSS Modules classes

## Other Frameworks (No Changes Needed)

### Nuxt
- ✅ Already CSP-compatible via `<style scoped>`
- Vite compiles to external CSS

### SvelteKit
- ✅ Already CSP-compatible via `<style>`
- Vite compiles to external CSS

### React Router (Remix)
- ✅ Already CSP-compatible via Tailwind classes
- Tailwind compiles to external CSS

## Documentation Updates

### README.md Section
```markdown
## Frontend Scaffolding

All frontend scaffolders generate CSP-compatible code:

- **Next.js**: Uses CSS Modules for scoped, external styles
- **Nuxt**: Vue SFC `<style scoped>` compiles to external CSS
- **SvelteKit**: Svelte `<style>` compiles to external CSS
- **React Router**: Tailwind classes compile to external CSS

No framework requires `'unsafe-inline'` CSP directive.
```

### Developer Guide
Add section on styling best practices:
- How to add CSS Modules in Next.js
- When to use global vs. scoped styles
- CSP implications of inline styles

## Testing Requirements

### Manual Testing
1. Generate fresh Next.js scaffold: `make frontend-init FRAMEWORK=next`
2. Install dependencies: `make pnpm-sync`
3. Start dev server: `make pnpm CMD="run dev"`
4. Open browser: `https://localhost:8443/`
5. Verify:
   - Page renders with correct styles
   - No CSP violations in browser console
   - Response has CSP header without `'unsafe-inline'`

### Automated Testing
```bash
# Test CSP header in response
test_csp_header() {
  local response=$(curl -s -I https://localhost:8443/)
  if echo "$response" | grep -q "style-src 'self'"; then
    echo "✅ CSP header correct"
  else
    echo "❌ CSP header missing or incorrect"
    return 1
  fi

  if echo "$response" | grep -q "unsafe-inline"; then
    echo "❌ CSP contains 'unsafe-inline' (security issue)"
    return 1
  fi
}
```

## Definition of Done

- [ ] Next.js scaffolder generates CSS Modules instead of inline styles
- [ ] `app/globals.css` created with minimal global resets
- [ ] `app/page.module.css` created with scoped component styles
- [ ] `app/layout.tsx` imports `globals.css`
- [ ] `app/page.tsx` uses CSS Module classes
- [ ] No inline `style={{}}` attributes in generated JSX
- [ ] Manual testing confirms no CSP violations
- [ ] Page renders correctly with styles
- [ ] Browser console shows no CSP errors
- [ ] Documentation updated with CSP compliance notes
- [ ] All four frameworks (Next/Nuxt/SvelteKit/Remix) confirmed CSP-compatible

## Priority

**High** - Required for v1.0 release.

Security by design is a core principle. Next.js scaffolder must not generate
CSP-violating code out of the box.

## References

- Next.js CSS Modules: https://nextjs.org/docs/app/building-your-application/styling/css-modules
- CSP style-src directive: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Security-Policy/style-src
- Current CSP implementation: `public/index.php`, task 27
- Frontend scaffolder directory: `docker/node/frontend-patches/`

## Notes

- CSS Modules are Next.js standard - not adding external dependencies
- Tailwind could be alternative, but adds opinionated dependency
- CSS Modules strike balance: CSP-safe, framework-native, minimal
- Future: Consider Tailwind as optional addon (not default)
