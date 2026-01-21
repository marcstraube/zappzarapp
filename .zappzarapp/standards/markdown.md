# Markdown Standards

**Important:** `.claude/` is also linted (`make lint-md`)!

## Code Blocks - ALWAYS Specify Language

| Wrong   | Correct     |
| ------- | ----------- |
| ` ``` ` | ` ```bash ` |
| ` ``` ` | ` ```php `  |
| ` ``` ` | ` ```text ` |

**Common languages:**

| Content             | Language     |
| ------------------- | ------------ |
| Shell/Terminal      | `bash`       |
| PHP                 | `php`        |
| TypeScript          | `typescript` |
| JavaScript          | `javascript` |
| JSON                | `json`       |
| YAML                | `yaml`       |
| SQL                 | `sql`        |
| Dockerfile          | `dockerfile` |
| Plain text/Diagrams | `text`       |
| Makefile            | `makefile`   |

## Other Rules (markdownlint)

- **MD012**: No multiple blank lines
- **MD022**: Blank line before/after headings
- **MD031**: Blank line before/after code blocks
- **MD032**: Blank line before/after lists
- **MD034**: No "bare URLs" — always use `[Text](URL)`
- **MD040**: Code blocks MUST have language (see above)
- **MD047**: File must end with newline

## Tables

- Separate columns with `|`
- Separate header row with `---`
- No emojis in tables (alignment issues)
