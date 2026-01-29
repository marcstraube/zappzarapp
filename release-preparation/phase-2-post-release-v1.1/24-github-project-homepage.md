# 24: GitHub Project Homepage

## Goal

Create a dedicated project homepage (GitHub Pages or similar) for zappzarapp
with detailed marketing content, comparisons, and visual documentation.

## Why Separate from README?

README should be concise (elevator pitch + quick start). The homepage can:
- Go deeper into features
- Show screenshots and demos
- Compare with alternatives
- Target different audiences (devs vs. decision makers)

## Proposed Content

### 1. Landing Page
- Hero section with tagline
- Key value propositions (3-4 bullets)
- "Get Started" CTA
- Visual: architecture diagram or screenshot

### 2. Features Page
Detailed breakdown of:
- Docker infrastructure (21 services explained)
- Kubernetes support
- Stack presets (visual comparison)
- IDE integration
- AI workflow tooling
- Security features

### 3. Comparison Page
| Feature | zappzarapp | Laravel Sail | Docker Compose templates | create-react-app |
|---------|------------|--------------|--------------------------|------------------|
| Multi-language | PHP + Node | PHP only | Varies | JS only |
| Kubernetes | Full Helm | No | No | No |
| IDE configs | Yes | No | No | No |
| Make targets | 244 | ~20 | ~10 | ~5 |
| ... | | | | |

### 4. Use Cases
- "Starting a new SaaS project"
- "Modernizing legacy PHP with Node frontend"
- "Team onboarding in minutes"
- "From prototype to production"

### 5. Documentation Hub
- Links to all 76 markdown docs
- Organized by topic
- Search functionality

### 6. Screenshots / Demo
- Dev Dashboard screenshots
- Terminal showing `make setup`
- IDE with pre-configured settings
- Optional: Video walkthrough

## Technical Implementation Options

### Option A: GitHub Pages + Jekyll/Hugo
- Free hosting on GitHub
- Markdown-based
- Easy to maintain
- Limited interactivity

### Option B: Docusaurus
- React-based
- Great for documentation sites
- Versioning support
- More setup required

### Option C: VitePress
- Vue-based, very fast
- Great DX
- Modern look
- Good for technical docs

### Option D: Astro
- Multi-framework support
- Fast static output
- Good for marketing + docs hybrid

## Recommended Approach

**VitePress or Docusaurus** - Both are well-suited for developer platforms:
- Markdown-first (easy to maintain)
- Good search
- Modern, professional appearance
- Active communities

## Directory Structure

```
docs-site/           # or gh-pages branch
├── .vitepress/      # or docusaurus.config.js
├── index.md         # Landing page
├── features/
│   ├── docker.md
│   ├── kubernetes.md
│   ├── ide.md
│   └── ...
├── comparison.md
├── use-cases.md
└── guide/           # Link to main docs or embed
```

## Tasks

1. **Choose technology** (VitePress vs Docusaurus vs other)
2. **Design landing page** structure
3. **Write comparison content**
4. **Create screenshots/visuals**
5. **Set up GitHub Pages deployment**
6. **Link from README**

## Priority

Low - Nice-to-have after v1.1 release. README update (Phase 1) is sufficient
for initial release.

## Dependencies

- README repositioning (#24 Phase 1) should be done first
- Final feature set should be stable
