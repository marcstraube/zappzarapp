# Project Customization

After cloning zappzarapp, customize these files to make it your own project.

## Find What Still Needs Customization

The shipped documentation templates (README, agent context, …) contain
placeholder markers. Run:

```bash
make customize
```

It lists every template that still holds a `zappzarapp:customize` placeholder,
with a count per file. Replace each marker with your content and re-run until it
reports that all templates are customized. (Claude Code offers to help with this
automatically on a fresh conversation.)

## Quick Checklist

- [ ] `composer.json` - PHP package metadata
- [ ] `package.json` - Node.js package metadata
- [ ] `LICENSE` - Copyright holder
- [ ] `README.md` - Project name, description, license
- [ ] `.claude/context/project.md` - Your application's context for AI agents
- [ ] `.claude/CLAUDE.md` - Project structure & key make targets
- [ ] `AGENTS.md` - Project description for AI agents
- [ ] `.env` - Team environment defaults (committed)
- [ ] `.env.local` - Your local overrides (created by `make setup`)
- [ ] `public/favicon.svg` - App icon

> **Tip:** `make customize` tracks the README and agent-context files above via
> their placeholder markers — run it any time to see what is left.

## Package Files

### composer.json

```json
{
  "name": "your-vendor/your-project",
  "version": "1.0.0",
  "description": "Your project description",
  "license": "MIT",
  "homepage": "https://github.com/your-username/your-project",
  "authors": [
    {
      "name": "Your Name",
      "email": "you@example.com",
      "homepage": "https://github.com/your-username"
    }
  ],
  "support": {
    "issues": "https://github.com/your-username/your-project/issues",
    "source": "https://github.com/your-username/your-project"
  }
}
```

> **Note:** If you plan to publish your package on Packagist, remove the
> `version` field. Packagist derives the version automatically from Git tags.
> The `version` field is only needed for the dynamic API documentation titles.

### package.json

```json
{
  "name": "your-project",
  "version": "1.0.0",
  "description": "Your project description",
  "license": "MIT",
  "repository": {
    "type": "git",
    "url": "https://github.com/your-username/your-project.git"
  },
  "bugs": {
    "url": "https://github.com/your-username/your-project/issues"
  },
  "homepage": "https://github.com/your-username/your-project#readme",
  "author": {
    "name": "Your Name",
    "email": "you@example.com",
    "url": "https://github.com/your-username"
  }
}
```

> **Note:** The project name from these files is used in the API documentation
> titles. After changes, run `make docs` to regenerate documentation.

## License

Edit `LICENSE` to update the copyright holder:

```text
MIT License

Copyright (c) 2025 Your Name

...
```

For a different license, replace the entire file. Popular options:

- [MIT](https://choosealicense.com/licenses/mit/) - Permissive, simple
- [Apache 2.0](https://choosealicense.com/licenses/apache-2.0/) - Permissive,
  patent protection
- [GPL 3.0](https://choosealicense.com/licenses/gpl-3.0/) - Copyleft

Update the `license` field in `composer.json` and `package.json` accordingly.

## Environment Configuration

The project uses a 3-tier environment file structure:

| File              | Purpose                    | Git Status |
| ----------------- | -------------------------- | ---------- |
| `.env`            | Team defaults              | Committed  |
| `.env.production` | Production-specific values | Committed  |
| `.env.local`      | Your local overrides       | Gitignored |

Make targets load the files in that order, so later files override earlier ones.
A `ZAPPZARAPP_ENV` passed to make itself
(`ZAPPZARAPP_ENV=production make build`) beats all three files; only the values
`development` and `production` are accepted.

`make setup` will interactively offer to create `.env.local` with your
USER_ID/GROUP_ID. You can also run `make init` separately at any time.

**Team-level customization** - Edit `.env` (committed):

| Variable               | Description             | Example                 |
| ---------------------- | ----------------------- | ----------------------- |
| `COMPOSE_PROJECT_NAME` | Docker container prefix | `myapp`                 |
| `DB_TYPE`              | Database type           | `postgres` or `mariadb` |
| `TZ`                   | Timezone                | `Europe/Berlin`         |

**Local overrides** - Edit `.env.local` (gitignored):

| Variable               | Description             | Example |
| ---------------------- | ----------------------- | ------- |
| `USER_ID` / `GROUP_ID` | Match your host user    | `1000`  |
| `NGINX_PORT`           | Override port conflicts | `8081`  |
| `POSTGRES_PORT`        | Override port conflicts | `5433`  |

## Git Remote

Set up your own repository:

```bash
# Remove existing remote (if any)
git remote remove origin

# Add your repository
git remote add origin git@github.com:your-username/your-project.git

# Push initial commit
git push -u origin main
```

## Branding

### Favicon

Replace `public/favicon.svg` with your own icon. The file is used by:

- Main application (`/`)
- API documentation (`/docs/api/php/`, `/docs/api/node-backend/`,
  `/docs/api/node-frontend/`)

The Dev Dashboard and Docsify guides use separate favicons in:

- `public/assets/dev-dashboard/favicon.svg`
- `docs/assets/favicon.svg`

### API Documentation Titles

Titles are generated automatically from package files:

```text
{ProjectName} - PHP API - v{version}
{ProjectName} - Backend API - v{version}
{ProjectName} - Frontend - v{version}
```

- **ProjectName**: Extracted from `composer.json` / `package.json` `name` field
- **Version**: From the `version` field

Regenerate after changes:

```bash
make docs
```

## Optional: README

Update `README.md` with your project's:

- Name and description
- Installation instructions
- Usage examples
- Contributing guidelines

## Verification

After customization, verify everything works:

```bash
# Rebuild containers with new project name
make down
make build
make up

# Regenerate documentation
make docs

# Run tests
make check
```
