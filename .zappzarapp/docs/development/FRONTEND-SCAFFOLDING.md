# Frontend Scaffolding

The `src/node/frontend/` directory is the workspace for Node.js frontend
frameworks.

## Scaffolding

Choose your preferred framework using one of the following commands:

```bash
make node-frontend-nuxt      # Nuxt 3
make node-frontend-next      # Next.js
make node-frontend-remix     # React Router (formerly Remix v2)
make node-frontend-sveltekit # SvelteKit
```

After scaffolding, install dependencies:

```bash
make pnpm-install
```

## Switching Frameworks

To remove an existing frontend and scaffold a different one:

```bash
make node-frontend-clean # Removes current frontend (asks for confirmation)
make node-frontend-next  # Scaffold new framework
make pnpm-install        # Install dependencies
```

## Development

The frontend runs on port 3001 and is accessible via Nginx at:

- **Development**: `https://localhost:8443/app/`
- **Production**: `https://yourdomain.com/app/`

## Architecture

- Frontend proxied through Nginx (SSL termination)
- API proxy configured: `/api/backend/*` routes to Express backend on port 3000
- Hot Module Replacement (HMR) supported via WebSocket
