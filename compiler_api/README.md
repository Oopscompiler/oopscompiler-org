# Docker Compiler API (Node)

This folder is the **real compiler** that runs C/C++ inside a Docker sandbox.

Your PHP UI calls `coding/compiler/run_code.php`.
That PHP file proxies requests to this API (default: `http://127.0.0.1:3001`).

## Prereqs
- Node.js 18+
- Docker installed + running
- Docker image available: `code-sandbox:latest`

> If your image name is different, update it inside `index.js` (constant `IMAGE`).

## Setup
```bash
cd coding/compiler_api
npm install
cp .env.example .env
# edit .env if your MySQL creds/port differ

node index.js
# Compiler API will start on http://localhost:3001
```

## PHP proxy config (optional)
`coding/compiler/run_code.php` uses:
- `COMPILER_API_URL` env var if set
- otherwise defaults to `http://127.0.0.1:3001`

So if your Node API runs elsewhere:
```bash
export COMPILER_API_URL=http://127.0.0.1:3001
```

## Endpoints used
- `POST /run` (custom input)
- `POST /run-question` (runs all DB testcases for a question)

