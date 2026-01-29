# Review 08: Node.js & Frontend

**Role**: Node.js and Frontend Development Expert

**Weight**: 6% of final score

**Report Location**: `reports/review-08-nodejs-frontend.md`

---

## Verification Commands

```bash
# Check Node version
docker compose exec node node --version

# Check TypeScript config
cat tsconfig.json

# Run Vite frontend build
make node-build

# Check bundle size
ls -la public/build/

# For framework testing, scaffold a frontend first:
make node-frontend-nuxt  # or: node-frontend-next, node-frontend-remix, node-frontend-sveltekit
make pnpm-sync
make node-frontend-build
```

---

## Analysis Checklist

### A. Node.js Configuration

- [ ] Version current (24 LTS)
- [ ] TypeScript strict mode enabled
- [ ] ESM modules used correctly
- [ ] Package.json complete and correct
- [ ] Scripts defined appropriately
- [ ] pnpm workspace configured correctly

### B. Vite Configuration (Assets Mode)

- [ ] Development server configured
- [ ] HMR working
- [ ] Production build optimized
- [ ] Asset handling correct
- [ ] Environment variables handled

### C. Framework Scaffolding (Framework Mode)

Test at least one framework scaffold:

- [ ] `make node-frontend-nuxt` works without errors
- [ ] `make pnpm-sync` updates lockfile correctly
- [ ] `make node-frontend-dev` starts dev server
- [ ] `make node-frontend-build` produces production build
- [ ] `make node-frontend-start` serves production build

### D. Express API (Backend)

- [ ] Error handling middleware
- [ ] Request validation
- [ ] Response formatting consistent
- [ ] Health check endpoint
- [ ] Graceful shutdown

### E. TypeScript Quality

- [ ] Strict mode enabled
- [ ] No `any` types (or justified)
- [ ] Proper type exports
- [ ] Path aliases configured
- [ ] Source maps for development

### F. PM2 Configuration

- [ ] Cluster mode appropriate
- [ ] Log rotation configured
- [ ] Restart policies correct
- [ ] Environment handling

---

## Output Format

See `00-overview.md` for standard report format.
