# Docker

One Apache + mod_php 8.5 image serves the Laravel API **and** the two built Vue
SPAs over three vhosts, mirroring production subdomain routing.

| Host                       | Serves                                        |
| -------------------------- | --------------------------------------------- |
| `api.<domain>`             | Laravel API (`src/api/public`)                |
| `console.<domain>`         | Developer Console SPA                         |
| `*.<domain>` (wildcard)    | Public Release History SPA (slug from hostname) |

`<domain>` is `rylees.test` in dev and `rylees.ai` in prod (set via `APP_DOMAIN`).

All real config lives in this `docker/` directory. The repo root holds only two
thin pointer stubs that `include:` these files:

- `compose.yaml` → dev (this dir's `compose.base.yaml` + `compose.dev.yaml`)
- `compose.prod.yaml` → prod (`compose.base.yaml` + `compose.prod.yaml`)

## Development

PostgreSQL is bundled **for dev only**. Mail is not containerised — it uses the
existing Mailtrap account via the `MAIL_*` values in `src/api/.env`.

```bash
# from the repo root
cp docker/env.example .env        # optional: override ports / db creds
docker compose up --build
```

The entrypoint copies `.env` if missing, runs `key:generate`, waits for
Postgres, then `migrate` + `db:seed`. The `frontend` container rebuilds both
SPAs into `src/frontend/build` on change (`vite build --watch`); Apache serves
that build dir, so subdomain routing matches production.

### Host names

`/etc/hosts` cannot wildcard, so add the subdomains you want to test:

```
127.0.0.1  api.rylees.test console.rylees.test acme.rylees.test
```

For arbitrary `*.rylees.test` slugs without editing hosts each time, point a
local dnsmasq at `127.0.0.1` for `.rylees.test`.

Then open `http://console.rylees.test`, `http://acme.rylees.test/<project-key>`,
and call the API at `http://api.rylees.test/v1/...`.

### Port 80 already in use?

```bash
WEB_PORT=8080 docker compose up --build   # or set WEB_PORT in .env
```

(With a non-80 port you must include it in the URL, e.g.
`curl -H 'Host: api.rylees.test' http://localhost:8080/v1/ref/industries`.)

### Instant HMR (optional)

The watch-build above has no hot reload. For HMR, run the Vite dev server on the
host instead and let it proxy `/v1` to the dockerized API:

```bash
cd src/frontend && npm run dev   # serves /console and /history on :5173
```

### Connecting to PostgreSQL

The dev `postgres` container publishes its port to the host, so any local client
connects to `localhost`. Credentials come from the compose defaults (override
them in the root `.env`):

| Field    | Value       | `.env` key     |
| :------- | :---------- | :------------- |
| Host     | `localhost` | —              |
| Port     | `5432`      | `DB_PORT_HOST` |
| Database | `rylees`    | `DB_DATABASE`  |
| User     | `rylees`    | `DB_USERNAME`  |
| Password | `rylees`    | `DB_PASSWORD`  |

Connection URL (psql, TablePlus, DBeaver, DataGrip, pgAdmin):

```
postgresql://rylees:rylees@localhost:5432/rylees
```

SSL is not required (local container — use `disable`/`prefer`). If port `5432` is
already taken, set `DB_PORT_HOST=5433` in `.env` and connect on that port (the
container still listens on 5432 internally).

To open a shell client without anything installed on the host:

```bash
docker compose exec postgres psql -U rylees -d rylees
```

### Queue worker (optional)

Only needed if `QUEUE_CONNECTION` is not `sync`:

```bash
docker compose --profile worker up
```

## Production

No database container — the image points at the **external** PostgreSQL via env.
The API and both SPAs are baked into the image at build time. Provide secrets via
a root `.env` or the deployment environment (see the prod section of
`docker/env.example`):

```bash
docker compose -f compose.prod.yaml build
docker compose -f compose.prod.yaml up -d
```

Required prod env: `APP_KEY`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`,
`DB_PASSWORD` (plus `MAIL_*`, `OPENAI_API_KEY`). Generate a key with
`docker run --rm --entrypoint php rylees-web:latest artisan key:generate --show`.

**Migrations are a deploy step, not run on container start** — run
`docker compose -f compose.prod.yaml exec web php artisan migrate --force`
against the external database as part of your release.

**DNS:** production needs a wildcard A record `*.rylees.ai → server IP` (plus
`api.` and `console.`) so customer-slug subdomains reach the history vhost.

## CLI

The Python CLI (`src/cli`) is intentionally **not** containerised — it runs on a
developer's machine against a local git repo and is distributed via
`pip install rylees`.
