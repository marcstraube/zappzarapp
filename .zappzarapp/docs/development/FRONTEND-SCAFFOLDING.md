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

Scaffolds run **non-interactively by default** (headless, CI-friendly). Three
optional knobs:

| Variable         | Effect                                                                                                                                                 | Example                                                              |
| ---------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------- |
| `TEMPLATE=`      | Template/variant for Nuxt, SvelteKit and React Router (Next.js has no template concept — it uses its defaults). Default for Nuxt/SvelteKit: `minimal`. | `make node-frontend-nuxt TEMPLATE=ui`                                |
| `INTERACTIVE=1`  | Restore the framework CLI's own interactive picker (template **and** every other option). Needs a real TTY.                                            | `make node-frontend-next INTERACTIVE=1`                              |
| `SCAFFOLD_ARGS=` | Extra flags passed straight through to the framework CLI (headless power-use).                                                                         | `make node-frontend-sveltekit SCAFFOLD_ARGS="--add prettier eslint"` |

Follow this sequence when scaffolding:

```bash
make down               # 1. Stop containers
make node-frontend-nuxt # 2. Scaffold framework (or -next, -remix, -sveltekit)
make pnpm-sync          # 3. Re-resolve lockfile and install dependencies
make up                 # 4. Start containers
```

`make pnpm-sync` re-resolves the lockfile to match the changed `package.json`
(in a temp location, since bind-mounted lockfiles cannot be rewritten in place)
and installs from it. Use `make pnpm-update` only when you want to upgrade
dependency versions within their ranges.

Each scaffold also generates `tests/goss/services/node-frontend.framework.yaml`
— a GOSS check that pins the framework's build output (e.g. Nuxt's `.output/`).
The `test-framework` image stage validates it together with the
framework-agnostic base spec, so a production framework image whose build
silently produced nothing fails at build time. Commit the file with the
scaffold; `make node-frontend-clean` removes it.

## Switching Frameworks

To remove an existing frontend and scaffold a different one:

```bash
make down                # 1. Stop containers
make node-frontend-clean # 2. Remove current frontend (asks for confirmation; FORCE=1 or non-TTY/CI skips it)
make node-frontend-next  # 3. Scaffold new framework
make pnpm-sync           # 4. Re-resolve lockfile and install dependencies
make up                  # 5. Start containers
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
