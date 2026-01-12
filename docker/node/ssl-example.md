# Node.js SSL/TLS Configuration

## Overview

This document describes how to enable SSL/TLS for the Node.js backend server
(app-server mode).

## When to Use SSL for Node.js

### Scenario A: Nginx as Reverse Proxy (Recommended)
**Do NOT enable SSL in Node.js**

When using Nginx as a reverse proxy (default setup), SSL termination happens
at Nginx. Node.js backend communicates with Nginx over HTTP internally.

```
Client (HTTPS) → Nginx (SSL Termination) → Node.js (HTTP)
```

**Configuration:** Use the Nginx SSL setup (see docker/certs/README.md)

### Scenario B: Direct Node.js Exposure
**Enable SSL in Node.js**

If you expose the Node.js backend directly to the internet (without Nginx),
you need to configure SSL in the Node.js application.

```
Client (HTTPS) → Node.js (HTTPS)
```

## Enabling SSL in Node.js

### 1. Generate/Obtain Certificates

Use the same certificates as Nginx:

```bash
# Self-signed (development)
make ssl-selfsigned

# Let's Encrypt (production)
make ssl-letsencrypt
```

### 2. Update Node.js Server Code

Modify `src/node/server.ts` to use HTTPS:

```typescript
import https from 'https';
import fs from 'fs';
import express from 'express';

const app = express();

// Your Express configuration...

// SSL Configuration
const sslOptions = {
  key: fs.readFileSync('/app/certs/cert.key'),
  cert: fs.readFileSync('/app/certs/cert.crt'),
};

// Create HTTPS server
const server = https.createServer(sslOptions, app);

const PORT = process.env.PORT || 3000;
server.listen(PORT, () => {
  console.log(`HTTPS Server running on port ${PORT}`);
});
```

### 3. Mount Certificates

**Development (`compose.yaml`):**
```yaml
services:
  node:
    volumes:
      - ./docker/certs:/app/certs:ro
    ports:
      - "${NODE_SSL_PORT:-3443}:3000"
```

**Production (`compose.production.yaml`):**
```yaml
services:
  node:
    volumes:
      - ./docker/certs:/app/certs:ro
```

### 4. Environment Variables

Add to `.env`:

```bash
# Optional: Custom Node.js SSL port (if exposing directly)
NODE_SSL_PORT=3443
```

## Best Practices

1. **SSL Termination**: Prefer SSL termination at Nginx (Scenario A)
2. **Internal Communication**: HTTP is acceptable for internal service-to-service
   communication within Docker network
3. **Certificate Management**: Use the same certificates across all services
   for simplicity
4. **Environment-Specific**: Only enable SSL when exposing Node.js directly

## Troubleshooting

### "ENOENT: no such file or directory"

Certificate files not mounted. Check volumes in compose files.

### Performance Impact

SSL has minimal performance impact in modern systems. Benefits (security)
far outweigh costs.
