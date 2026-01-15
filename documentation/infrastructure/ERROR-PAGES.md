# Error Pages Configuration

This document describes how error pages are handled across the different
components of the stack.

## Architecture Overview

```text
                            Request
                               │
                               ▼
                            nginx
                               │
               ┌───────────────┼───────────────┐
               ▼               ▼               ▼
         Static Files        PHP            Node
               │               │               │
               ▼               ▼               ▼
       nginx Error Page  Content-Neg.     JSON only
        (static HTML)          │
                       ┌───────┴───────┐
                       ▼               ▼
                   Browser?          API?
                       │               │
                       ▼               ▼
               PHP Error Page    JSON Response
                 (dynamic)
```

## Error Page Responsibilities

| Component | Error Type                                   | Response Format | Location               |
| --------- | -------------------------------------------- | --------------- | ---------------------- |
| **nginx** | Static file not found (`.txt`, `.jpg`, etc.) | HTML            | `docker/nginx/errors/` |
| **nginx** | Backend unavailable (502, 503, 504)          | HTML            | `docker/nginx/errors/` |
| **PHP**   | Route not found (browser)                    | HTML            | `ErrorPage::render()`  |
| **PHP**   | Route not found (API client)                 | JSON            | Router                 |
| **PHP**   | Non-existent `.php` file                     | HTML/JSON       | Router (via nginx)     |
| **Node**  | Any error                                    | JSON            | Express error handler  |

## Content Negotiation

PHP uses the `Accept` header to determine the response format:

| Accept Header            | Response                                       |
| ------------------------ | ---------------------------------------------- |
| `application/json`       | JSON: `{"error": "Not Found", "path": "/foo"}` |
| `text/html` (or default) | HTML error page with status code               |

## Customizing Error Pages

### nginx Static Error Pages

Location: `docker/nginx/errors/`

```text
docker/nginx/errors/
├── 404.html    # Not Found
└── 50x.html    # Server Errors (500, 502, 503, 504)
```

These are served when:

- A non-PHP static file doesn't exist (e.g., `/nonexistent.txt`)
- A backend service is unavailable (PHP-FPM down → 502)
- nginx itself encounters an error

**Note:** Non-existent `.php` files are routed through PHP for consistent error
handling with content negotiation.

**Configuration** in `docker/nginx/snippets/error-pages.conf` (included by SSL
templates):

```nginx
error_page 404 /errors/404.html;
error_page 500 502 503 504 /errors/50x.html;

location ^~ /errors/ {
    internal;
    alias /usr/share/nginx/html/errors/;
}
```

**PHP file routing** (routes non-existent `.php` files through PHP):

```nginx
location ~ \.php$ {
    try_files $uri @php_router;
    # ... fastcgi config for existing files
}

location @php_router {
    fastcgi_pass unix:/var/run/php-fpm/php-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    fastcgi_param REQUEST_URI $request_uri;
    # ... other fastcgi params
}
```

### PHP Dynamic Error Pages

- **Class:** `src/php/App/Http/ErrorPage.php`
- **Template:** `templates/app/error.php`

The `ErrorPage` class renders dynamic error pages with:

- Correct HTTP status code
- Requested path information
- Consistent styling

**Supported error codes:**

| Code | Title                 | Use Case                |
| ---- | --------------------- | ----------------------- |
| 400  | Bad Request           | Malformed request       |
| 401  | Unauthorized          | Authentication required |
| 403  | Forbidden             | Access denied           |
| 404  | Page Not Found        | Route doesn't exist     |
| 500  | Internal Server Error | Application error       |
| 502  | Bad Gateway           | Upstream error          |
| 503  | Service Unavailable   | Maintenance mode        |

**Usage in controllers:**

```php
use App\Http\ErrorPage;

// Render 404 with path info
ErrorPage::render(404, $requestPath);

// Render 403 without path
ErrorPage::render(403);

// Render 500 for errors
ErrorPage::render(500);
```

**Extending the ErrorPage class:**

You can extend the class to add:

- User session information
- "Did you mean..." suggestions
- Logging/analytics
- Custom branding per error type

### Node.js Error Responses

Location: `src/node/backend/app.ts`

Node.js always returns JSON for errors:

```typescript
// 404 Handler
app.use((req: Request, res: Response): void => {
  res.status(404).json({
    error: 'Not Found',
    path: req.path,
    method: req.method,
  });
});

// 500 Handler
app.use(
  (err: Error, req: Request, res: Response, _next: NextFunction): void => {
    res.status(500).json({
      error: NODE_ENV === 'development' ? err.message : 'Internal Server Error',
      timestamp: new Date().toISOString(),
    });
  }
);
```

This is intentional because Node.js serves as:

1. **Vite Dev Server** - Asset delivery (no HTML pages)
2. **API Backend** - JSON responses only

#### Node.js API Proxy Behavior

The nginx configuration includes Node.js API proxy locations (`/api/node/*`) in
all environments (development and production). These locations use dynamic
upstream resolution:

```nginx
location ~ ^/api/node/(.*)$ {
    set $upstream_backend node:3000;
    proxy_pass http://$upstream_backend/api/$1$is_args$args;
    # ...
}
```

**Behavior when Node is not running:**

| Scenario                   | Result                     |
| -------------------------- | -------------------------- |
| Node container running     | JSON response from Node.js |
| Node container stopped     | nginx 502 Bad Gateway page |
| Node container not started | nginx 502 Bad Gateway page |

This design means:

- nginx starts successfully even if Node is not running
- No manual configuration needed to enable/disable Node.js API
- Requests to `/api/node/*` automatically work when Node starts
- Clear 502 error indicates the backend is unavailable

## Testing Error Pages

```bash
# Browser request (HTML error page from PHP)
curl -H "Accept: text/html" https://localhost:8443/nonexistent

# API request (JSON error from PHP)
curl -H "Accept: application/json" https://localhost:8443/nonexistent

# Non-existent .php file (PHP error page, not nginx)
curl https://localhost:8443/nonexistent.php

# Node API (JSON error)
curl https://localhost:8443/api/node/nonexistent

# Backend unavailable (nginx 502 page)
docker compose stop php
curl https://localhost:8443/
docker compose start php
```

**Expected Results:**

| Request                 | All Services Up     | PHP Down        | Node Down           |
| ----------------------- | ------------------- | --------------- | ------------------- |
| `/nonexistent`          | PHP 404 (HTML/JSON) | nginx 502 page  | PHP 404 (HTML/JSON) |
| `/nonexistent.php`      | PHP 404 (HTML/JSON) | nginx 502 page  | PHP 404 (HTML/JSON) |
| `/api/node/nonexistent` | Node 404 (JSON)     | Node 404 (JSON) | nginx 502 page      |
| `/nonexistent.txt`      | nginx 404 page      | nginx 404 page  | nginx 404 page      |

## Styling Guidelines

Both nginx and PHP error pages use consistent styling:

- **Background**: Dark gradient (`#1a1a2e` to `#16213e`)
- **Accent color**: Blue (`#3b82f6`)
- **Font**: System font stack (Apple, Windows, Linux compatible)
- **Layout**: Centered, responsive

To maintain consistency when customizing, use the same color palette and
typography.
