# Task 05: Add Video/GIF Documentation

## Priority

LOW - Future Backlog

## Estimated Effort

4-6 hours

## Context

Visual documentation significantly improves onboarding and reduces support
questions. Short GIFs or videos demonstrating common workflows help users
understand the boilerplate faster than text documentation alone.

## Current State

- All documentation is text-based Markdown
- No visual aids for complex workflows
- Users must read and mentally simulate steps
- No standardized approach for creating visual docs

## Target State

1. Add GIF recordings for key workflows (setup, common tasks)
2. Create asciinema recordings for terminal workflows
3. Establish guidelines for creating visual documentation
4. Optional: Add short video tutorials

## Implementation Outline

### Recommended Tools

| Tool | Use Case | Format |
|------|----------|--------|
| asciinema | Terminal recordings | Web embed or GIF |
| Peek | Linux screen recording | GIF |
| Kap | macOS screen recording | GIF |
| LICEcap | Windows/macOS | GIF |
| ttygif | Convert terminal to GIF | GIF |

### Key Workflows to Document

**Priority 1 - Getting Started:**

```
docs/videos/
├── 01-quick-start.gif          # make setup && make up
├── 02-first-page-load.gif      # Opening localhost:443
└── 03-dev-dashboard-tour.gif   # Dev Dashboard features
```

**Priority 2 - Common Tasks:**

```
docs/videos/
├── 04-adding-php-route.gif     # Creating a new PHP endpoint
├── 05-adding-node-endpoint.gif # Creating a new Node.js endpoint
├── 06-running-tests.gif        # make test workflow
└── 07-debugging-container.gif  # docker compose logs, exec
```

**Priority 3 - Advanced:**

```
docs/videos/
├── 08-adding-service.gif       # Adding optional service profile
├── 09-database-migration.gif   # Running migrations
└── 10-production-build.gif     # Building for production
```

### Asciinema Setup

**Install asciinema:**

```bash
# Linux
apt install asciinema

# macOS
brew install asciinema

# pip
pip install asciinema
```

**Record terminal session:**

```bash
# Start recording
asciinema rec docs/videos/quick-start.cast

# Run commands
make setup
make up
curl -k https://localhost

# Stop recording (Ctrl+D or exit)
```

**Convert to GIF (optional):**

```bash
# Install agg (asciinema gif generator)
cargo install --git https://github.com/asciinema/agg

# Convert
agg docs/videos/quick-start.cast docs/videos/quick-start.gif
```

### Embedding in Documentation

**Option 1: Asciinema embed (interactive):**

```markdown
[![asciicast](https://asciinema.org/a/123456.svg)](https://asciinema.org/a/123456)
```

**Option 2: GIF embed (static):**

```markdown
## Quick Start

![Quick Start Demo](docs/videos/quick-start.gif)

1. Clone the repository
2. Run `make setup`
3. Run `make up`
```

**Option 3: HTML video (with controls):**

```html
<video width="100%" controls>
  <source src="docs/videos/quick-start.webm" type="video/webm">
  <source src="docs/videos/quick-start.mp4" type="video/mp4">
</video>
```

### Documentation Guidelines

**Create `docs/VISUAL-DOCS-GUIDE.md`:**

```markdown
# Visual Documentation Guidelines

## Recording Best Practices

1. **Terminal size:** Use 80x24 or 120x30 for readability
2. **Font size:** 14px minimum for clarity
3. **Speed:** Real-time for simple tasks, 1.5x for long operations
4. **Duration:** Keep GIFs under 30 seconds
5. **Looping:** Use infinite loop for demos, single play for tutorials

## File Naming

- Use numbered prefix: `01-`, `02-` for ordering
- Use descriptive kebab-case: `quick-start.gif`
- Include format suffix: `.gif`, `.cast`, `.webm`

## File Sizes

Target sizes for web embedding:
- GIF: < 2MB (use lossy compression if needed)
- WebM: < 5MB
- asciinema: No limit (text-based)

## Compression

```bash
# GIF optimization with gifsicle
gifsicle -O3 --lossy=80 input.gif -o output.gif

# WebM compression with ffmpeg
ffmpeg -i input.mp4 -c:v libvpx-vp9 -crf 30 -b:v 0 output.webm
```

## Accessibility

- Provide text descriptions for all videos
- Use asciinema when possible (screen reader friendly)
- Include step-by-step text alongside GIFs
```

### Makefile Integration

```makefile
##@ Documentation

.PHONY: docs-record docs-convert

docs-record: ## Start asciinema recording (interactive)
	@echo "Starting terminal recording..."
	@echo "Commands will be recorded. Exit shell to stop."
	asciinema rec docs/videos/recording-$$(date +%Y%m%d-%H%M%S).cast

docs-convert: ## Convert .cast files to GIF (requires agg)
	@for f in docs/videos/*.cast; do \
		[ -f "$$f" ] && agg "$$f" "$${f%.cast}.gif"; \
	done
```

### GitHub README Integration

**Update README.md with demo:**

```markdown
## Quick Start

<p align="center">
  <img src="docs/videos/quick-start.gif" alt="Quick Start Demo" width="600">
</p>

```bash
git clone https://github.com/marcstraube/zappzarapp.git
cd zappzarapp
make setup
make up
```

Open https://localhost - that's it!
```

## Directory Structure

```
docs/
└── videos/
    ├── README.md              # Index of all recordings
    ├── 01-quick-start.gif
    ├── 01-quick-start.cast    # Source for GIF
    ├── 02-first-page-load.gif
    └── ...
```

## Notes

- GIFs increase repository size; consider Git LFS for large files
- Asciinema.org hosting is free and reduces repo size
- Update recordings when workflows change significantly
- Consider automated recording in CI for consistency
- Terminal recordings are more accessible than GUI recordings

