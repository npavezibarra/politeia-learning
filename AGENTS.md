# AGENTS.md for Politeia Learning

Scope: /Users/nicolaspavez/Local Sites/nupoliteia/app/public/wp-content/plugins/politeia-learning

## Purpose
This document provides guidance for AI agents working on the Politeia Learning plugin, ensuring consistency in database interactions and architecture.

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
