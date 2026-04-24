# KyuubiSoft MCP Server

An MCP (Model Context Protocol) server that gives Claude its own working tools
inside this project: checklists, time tracking, and a lightweight decision log.

The server speaks MCP over **Streamable HTTP**, so it runs as a regular
container in `docker-compose` / Portainer rather than as a stdio subprocess.

## Tools

### Checklists
- `checklist_create` — create a checklist (optionally with initial items)
- `checklist_list` / `checklist_get`
- `checklist_add_item` / `checklist_update_item` / `checklist_remove_item`
- `checklist_toggle_item` (toggles or sets `done` explicitly)
- `checklist_delete`

### Time tracking
- `timer_start` / `timer_stop` / `timer_active`
- `time_log` — log a manual entry without a running timer
- `time_report` — aggregate by task (period: `today` / `week` / `all`)
- `time_entries` — list raw entries
- `time_entry_delete`

### Decision log
- `decision_log` — record an ADR-style decision
- `decision_list`

## Storage

JSON files in `MCP_DATA_DIR` (default `/data`, mounted via the
`mcp_data` Docker volume). Writes are atomic (`fs.rename`) and serialised
per-collection.

## Running locally

```bash
cd tools/mcp-server
npm install
MCP_DATA_DIR=./data npm start
# server on http://localhost:3333/mcp
```

Health check: `GET /health` returns `{ status: "ok", ... }`.

## Configuration

| Variable          | Default | Purpose                                          |
| ----------------- | ------- | ------------------------------------------------ |
| `PORT`            | `3333`  | TCP port                                         |
| `HOST`            | `0.0.0.0` | Bind address                                   |
| `MCP_DATA_DIR`    | `/data` | Where JSON state files are stored                |
| `MCP_AUTH_TOKEN`  | _unset_ | If set, requests must send `Authorization: Bearer <token>` |

## Wiring Claude Code

The repo ships `.mcp.json` at the project root, so any Claude Code session
inside `KyuubiSoft/` auto-discovers the server at `http://localhost:8092/mcp`
(the dev port published by `docker-compose.yml`).
