# AGENTS.md for Politeia Learning

Scope: /Users/nicolaspavez/Local Sites/nupoliteia/app/public/wp-content/plugins/politeia-learning

## Purpose
This document provides guidance for AI agents working on the Politeia Learning plugin, ensuring consistency in database interactions and architecture.

## Start Here (for coding agents)

When you get a task inside this plugin:

1. Read `/Users/nicolaspavez/Local Sites/nupoliteia/app/public/wp-content/plugins/politeia-learning/README.md` (module index).
2. Read the relevant module doc: `/Users/nicolaspavez/Local Sites/nupoliteia/app/public/wp-content/plugins/politeia-learning/modules/<module>/README.md`.
3. Jump to the module entrypoint: `/Users/nicolaspavez/Local Sites/nupoliteia/app/public/wp-content/plugins/politeia-learning/modules/<module>/init.php`.

## Project Constraints

- Assume **pure WordPress** (core + our custom plugins/theme). Avoid relying on external community/profile/LMS plugin APIs.
- Prefer WordPress-native routes (rewrites), shortcodes, blocks, templates, and REST routes we own.

## Code Size Rule (~500 lines)

- Keep PHP/JS files around **500 lines**.
- If a file grows significantly beyond that, split it into cohesive, functioning files (e.g., separate classes, traits, helpers, or renderers) and keep entrypoints thin.

## Database Connection (Local Environment)

To run raw SQL queries or database commands from the terminal in this "Local" environment (macOS), the standard `wp db` or `mysql` commands may fail due to path configuration.

**Successful Connection Method:**

1.  **Find the MySQL Binary**:
    Located at: `/Users/nicolaspavez/Library/Application Support/Local/lightning-services/mysql-8.0.35+4/bin/darwin-arm64/bin/mysql`

2.  **Find the Socket File**:
    Located at: `/Users/nicolaspavez/Library/Application Support/Local/run/3CaoRaeYl/mysql/mysqld.sock`
    *(Note: The hash `3CaoRaeYl` might change if the site is re-imported or re-created, check `~/Library/Application Support/Local/run/` if it fails).*

### Command Template
```bash
"/Users/nicolaspavez/Library/Application Support/Local/lightning-services/mysql-8.0.35+4/bin/darwin-arm64/bin/mysql" \
  -u root \
  -proot \
  --socket="/Users/nicolaspavez/Library/Application Support/Local/run/3CaoRaeYl/mysql/mysqld.sock" \
  local \
  -e "YOUR SQL QUERY HERE;"
```
