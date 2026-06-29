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

The API and both SPAs are baked into the image at build time. Prod runs its own
**local PostgreSQL container** whose port is **not** published to the host, so the
database is reachable only by `web`/`queue` over the internal Docker network —
never from the internet. Provide secrets via a root `.env` or the deployment
environment (see the prod section of `docker/env.example`):

```bash
docker compose -f compose.prod.yaml build
docker compose -f compose.prod.yaml up -d
```

Required prod env: `APP_KEY`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (plus
`MAIL_*`, `OPENAI_API_KEY`). `DB_HOST` defaults to the `postgres` service — only
set it to point at an external database instead. Generate a key with
`docker run --rm --entrypoint php rylees-web:latest artisan key:generate --show`.

**First deploy — initialise the database** (the image entrypoint does not
auto-migrate). Once the stack is up and `postgres` is healthy:

```bash
docker compose -f compose.prod.yaml exec web php artisan migrate --force
docker compose -f compose.prod.yaml exec web php artisan db:seed --force   # reference data
```

On later releases, re-run `migrate --force` as part of your deploy.

> **Why the DB is safe:** the `postgres` service has no `ports:` mapping, so
> Docker binds it to no host interface. To connect for maintenance, tunnel in
> from the host instead of opening a port:
> `docker compose -f compose.prod.yaml exec postgres psql -U "$DB_USERNAME" -d "$DB_DATABASE"`.

**DNS:** production needs a wildcard A record `*.rylees.ai → server IP` (plus
`api.` and `console.`) so customer-slug subdomains reach the history vhost.

### Deploying to Fly.io

The Dockerfile lives at `docker/api/Dockerfile`, not the repo root, so Fly is
told where to find it (and which target to build) by the `[build]` block in the
root `fly.toml`. Without it `fly deploy` fails with "no Dockerfile found". The
build context is the repo root, governed by the root `.dockerignore`.

> **Fly does not run `docker compose`** — it runs only the web image. The
> `postgres` container in `compose.prod.yaml` is for the self-hosted compose
> deployment above, **not** for Fly. On Fly the database is the same
> `postgres:16-alpine` container deployed as a **separate Fly app**
> (`fly.postgres.toml`, next section).

First-time setup:

```bash
fly launch --no-deploy --copy-config   # reuse the committed fly.toml; pick app name/region

# App key (clean value — bypass the image entrypoint):
fly secrets set APP_KEY="$(docker run --rm --entrypoint php rylees-web:latest artisan key:generate --show)"

# Mail + OpenAI:
fly secrets set \
  MAIL_MAILER=smtp MAIL_HOST=... MAIL_PORT=... MAIL_USERNAME=... MAIL_PASSWORD=... MAIL_FROM_ADDRESS=... \
  OPENAI_API_KEY=...
```

#### Database (self-hosted Postgres container)

The database runs as its **own Fly app** (`fly.postgres.toml`) — the stock
`postgres:16-alpine` container backed by a persistent Fly **volume**, on Fly's
private **6PN** network with no public service, so it's not reachable from the
internet. The web app reaches it at the private hostname `rylees-db.internal:5432`.

Provision and start it **before the first web deploy** (the web deploy's
`release_command` migrates against it):

```bash
# one-time: create the app and its data volume in the same region as the web app
fly apps create rylees-db
fly volumes create pg_data --app rylees-db --region fra --size 10   # GB

# the postgres superuser password — must match the web app's DB_PASSWORD below
fly secrets set --app rylees-db POSTGRES_PASSWORD=<strong-password>

# deploy the postgres container (reads fly.postgres.toml)
fly deploy --config fly.postgres.toml
```

`POSTGRES_DB` (`rylees`) and `POSTGRES_USER` (`rylees`) are set in
`fly.postgres.toml [env]`; the container initialises that database and role on
first boot. Point the web app at it — only the password is a secret, and it must
match the one set above:

```bash
fly secrets set --app rylees DB_PASSWORD=<same-strong-password>
```

`DB_HOST=rylees-db.internal`, `DB_DATABASE=rylees`, `DB_USERNAME=rylees`,
`DB_CONNECTION=pgsql` and `DB_PORT=5432` are already in the web app's
`fly.toml [env]`.

> **Why the DB is safe:** `fly.postgres.toml` declares no public service, so the
> Postgres app has no public IP and is reachable only by other apps in the org
> over 6PN (`rylees-db.internal`). Data lives on the `pg_data` volume and
> survives restarts and redeploys.
>
> **Backups:** a single-volume container has no managed backups. Snapshot the
> volume (`fly volumes snapshots list <vol-id>`) or run periodic `pg_dump` if you
> need point-in-time recovery.

Deploy the web app (after the Postgres app is up):

```bash
fly deploy                # builds locally; add --remote-only if Docker isn't running
```

`fly.toml` sets `release_command = "php artisan migrate --force"`, so each deploy
runs migrations against Fly Postgres before the new version goes live. Seed the
reference data once, after the first successful deploy:

```bash
fly ssh console --app rylees -C "php /var/www/api/artisan db:seed --force"
```

Custom domains + TLS for the three vhosts (the Apache config matches on the Host
header Fly forwards):

```bash
fly certs add api.rylees.ai
fly certs add console.rylees.ai
fly certs add rylees.ai
fly certs add "*.rylees.ai"     # wildcard for customer slugs (DNS-01 challenge)
```

Then point DNS (`fly ips list`) at the app: `A`/`AAAA` for `rylees.ai` and the
`api.`/`console.` hosts, plus the wildcard `*.rylees.ai`.

## CLI

The Python CLI (`src/cli`) is intentionally **not** containerised — it runs on a
developer's machine against a local git repo and is distributed via
`pip install rylees`.
