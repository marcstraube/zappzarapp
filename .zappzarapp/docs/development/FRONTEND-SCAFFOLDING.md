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

Follow this sequence when scaffolding:

```bash
make down               # 1. Stop containers
make node-frontend-nuxt # 2. Scaffold framework (or -next, -remix, -sveltekit)
make pnpm-update        # 3. Re-resolve lockfile (package.json changed)
make pnpm-sync          # 4. Install dependencies from the lockfile
make up                 # 5. Start containers
```

Run `make pnpm-update` before `make pnpm-sync`: both `pnpm-install` and
`pnpm-sync` enforce `--frozen-lockfile` (the sync container sets `CI=true`), so
they fail while the lockfile does not match the changed `package.json`.

## Switching Frameworks

To remove an existing frontend and scaffold a different one:

```bash
make down                # 1. Stop containers
make node-frontend-clean # 2. Remove current frontend (asks for confirmation)
make node-frontend-next  # 3. Scaffold new framework
make pnpm-update         # 4. Re-resolve lockfile
make pnpm-sync           # 5. Install dependencies from the lockfile
make up                  # 6. Start containers
```

## Port Configuration

The dual-container architecture assigns fixed ports:

- `node` (frontend): port 3001
- `node-backend` (Express API): port 3000

Framework dev-server settings such as Nuxt's `devServer.port` only apply in
development. Production builds (e.g. Nitro) ignore them — set the `NITRO_PORT`
or `PORT` environment variable instead.

## Development

The frontend runs on port 3001 and is accessible via Nginx at:

- **Development**: `https://localhost:8443/app/`
- **Production**: `https://yourdomain.com/app/`

## Architecture

- Frontend proxied through Nginx (SSL termination)
- API proxy configured: `/api/backend/*` routes to Express backend on port 3000
- Hot Module Replacement (HMR) supported via WebSocket
