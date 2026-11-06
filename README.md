# phpPgAdmin

A web UI for managing PostgreSQL, written in PHP.

The original project at [phppgadmin/phppgadmin](https://github.com/phppgadmin/phppgadmin)
stopped getting releases in 2020. [ReimuHakurei/phpPgAdmin](https://github.com/ReimuHakurei/phpPgAdmin)
kept it alive with PHP 8 fixes, and this repo builds on that. What's added here:

- a Docker image that runs on current PHP and ships a current `pg_dump`
- a test setup that logs in and clicks through the whole UI against several
  PostgreSQL versions, and fails on any PHP warning or SQL error
- GitHub Actions that run those tests and push the image to Docker Hub when a
  release is published
- a few fixes for things that broke on newer PostgreSQL (see `HISTORY`)
- a reworked UI: one page instead of frames, a SQL editor, faster browsing of
  big tables (see below)

## Using it

- Every page has its own address, so bookmarks, reloads, new tabs and the
  back button work. Pages load in place; the object tree on the left keeps
  its state.
- **Ctrl+K** jumps to the tree filter. Type part of a name, **Enter** opens the
  first match. Only branches you've opened are searched. The tree can be
  resized by dragging its edge (double-click resets it); on a phone it slides
  in from the ☰ button.
- In the SQL editor, **Ctrl+Enter** (Cmd+Enter on a Mac) runs the query, or
  only the selected text if there is a selection. Keywords, schemas, tables and
  columns are suggested as you type (and after `schema.` or `table.`).
  Picking a schema lists its tables; tables outside the search path are
  offered by their bare name and inserted as `schema.table`. **Ctrl+Space**
  shows suggestions any time. "Explain analyze" really runs the statement. The
  last 50 queries per database are kept in your browser under "Recent
  queries".
- When browsing a table, the row above the data filters each column
  (`=value` for an exact match), and the page size can be changed. Big tables
  show an estimated row count ("about 1.2M rows") instead of counting every
  row; there's a link to count exactly. Double-click a cell to copy its full
  value. Edit opens the row in a panel next to the grid, Delete asks in a
  small dialog; either way you stay on the same page, filters and scroll
  position. Drop/empty confirmations elsewhere open as dialogs too.
  Ctrl/Cmd+click opens the full page instead.

## Running it

```sh
docker run -d --name phppgadmin -p 8080:8080 \
  -e PPA_SERVERS="db.example.internal:5432" \
  syamsf/phppgadmin
```

Then open http://localhost:8080.

With Compose, next to an existing database:

```yaml
services:
  phppgadmin:
    image: syamsf/phppgadmin
    ports:
      - "127.0.0.1:8080:8080"
    environment:
      PPA_SERVERS: "Main=postgres:5432"
    depends_on:
      - postgres
```

### One login per database

If each database on a server has its own user, list the databases after a `/`,
separated by `+`:

```sh
-e PPA_SERVERS="Shop=db1.internal:5432/orders+billing, Temp=db2.internal/scratch"
```

Each database then shows up under its server in the sidebar. Clicking one asks
for that database's username and password, and the login is checked against
that database (not `postgres`), so a user that can only connect there gets in.
A per-database login only sees its own database: the server-wide pages
(database list, roles, tablespaces, full export) aren't available with it.
Servers without a `/` part keep a single login for the whole server.

By default you can't log in as `postgres` (or `root`, `pgsql`, `administrator`)
or with an empty password. Create a normal login role for yourself, or set
`PPA_EXTRA_LOGIN_SECURITY=false` if you really want the old behaviour.

### Settings

| Variable | Default | |
|---|---|---|
| `PPA_SERVERS` | `localhost` | Servers shown on the login page, separated by commas or spaces. Each one is `host`, `host:port` or `Name=host:port`, optionally followed by `/db1+db2` for a login per database (see above). IPv6 goes in brackets: `[::1]:5432`. Names can't contain spaces. |
| `PPA_SSLMODE` | `allow` | `disable`, `allow`, `prefer`, `require`. Same for all servers. |
| `PPA_DEFAULTDB` | `postgres` | Database to connect to before you pick one. |
| `PPA_EXTRA_LOGIN_SECURITY` | `true` | Block superuser names and empty passwords on the login page. |
| `PPA_OWNED_ONLY` | `false` | Only list databases the user owns. This only hides them in the list. |
| `PPA_SHOW_ADVANCED` | `false` | Show aggregates, types, operators, casts and so on. |
| `PPA_SHOW_SYSTEM` | `false` | Show system objects. |
| `PPA_SHOW_COMMENTS` | `true` | |
| `PPA_SHOW_OIDS` | `false` | PostgreSQL 11 and older only. |
| `PPA_DEFAULT_LANG` | `auto` | `auto` follows the browser. Otherwise a file name from `lang/`, e.g. `english`. |
| `PPA_THEME` | `default` | `default`, `bootstrap`, `cappuccino`, `dark`, `gotar`. |
| `PPA_MAX_ROWS` | `30` | Rows per page when browsing. |
| `PPA_MAX_CHARS` | `50` | Characters shown per field before cutting it off. |
| `PPA_BROWSE_EXACT_COUNT_LIMIT` | `100000` | Tables the planner estimates to have more rows than this are browsed without counting them: the page shows "about N rows" and a link to count exactly. |
| `PPA_MAX_RESULT_ROWS` | `1000` | Rows shown on the SQL results page without pagination. The rest is cut off with a notice. |
| `PPA_MIN_PASSWORD_LENGTH` | `1` | |
| `PPA_LEFT_WIDTH` | `200` | Width of the tree on the left, in pixels. |
| `PPA_AJAX_REFRESH` | `3` | Seconds between refreshes on the locks and processes pages. `0` turns it off. |
| `PPA_AUTOCOMPLETE` | `default on` | `default on`, `default off` or `disable`. |
| `PPA_PLUGINS` | | Comma-separated, e.g. `Report`. |
| `PPA_STATEMENT_TIMEOUT` | | Cancel statements that run longer than this, e.g. `30s` or `5min` (PostgreSQL's `statement_timeout`, also applies to the SQL editor). Unset means no limit. |
| `PGCONNECT_TIMEOUT` | `10` | Seconds to wait for a server to accept a connection. |
| `PHP_SESSION_COOKIE_SECURE` | `0` | Set to `1` when the site is served over HTTPS. |

If you need something the variables can't do (server groups, a different
sslmode per server, per-user themes), copy `conf/config.inc.php-dist`, edit it
and mount it over `/var/www/html/conf/config.inc.php`. The variables are ignored
then.

### About the image

- PHP 8.5 with Apache, listening on port 8080 as `www-data`, not root.
- `pg_dump` and `pg_dumpall` from PostgreSQL 18, which can dump any server
  from 9.2 up. Build with `--build-arg PG_CLIENT_VERSION=17` if you want an
  older client.
- `conf/`, `classes/`, `lang/` and the ADOdb library return 403 over HTTP.
- PHP errors go to the container log (`docker logs`), not the page.
- OPcache doesn't check files for changes. If you mount your own
  `config.inc.php`, restart the container after editing it.
- `/healthz` is a static file for health checks; the image's `HEALTHCHECK`
  uses it.
- Release images show their tag as the version, e.g. `v1.2.0-mod`. Local builds
  can set it with `--build-arg PPA_RELEASE=v1.2.0`; without it they show the
  version in `libraries/lib.inc.php`.

Don't put this on the public internet. It's a login form that hands out
database sessions and has a long history of XSS and CSRF reports. Keep it on
a private network, behind a VPN, or behind a reverse proxy with its own
authentication, and use HTTPS.

## Without Docker

You need PHP 7.4 or newer with the `pgsql` and `mbstring` extensions, and a web
server that runs PHP.

```sh
cp conf/config.inc.php-dist conf/config.inc.php
# edit conf/config.inc.php, at least the server host
```

Point the web server at the repo directory and open `index.php`. There's
nothing to build and no Composer dependencies are needed at runtime.

## Supported versions

| | Tested in CI | Expected to work |
|---|---|---|
| PHP | 8.1, 8.5 and the 8.6 RC; syntax checked on 7.4 to 8.5 | 7.4 and up |
| PostgreSQL | 9.6, 12, 14, 16, 17, 18 | 7.4 and up (older versions have their own driver class, but nobody tests them) |

## Tests

There are no unit tests. Instead `tests/run.sh` starts the app and a database
in Docker, loads `tests/fixtures.sql`, logs in, and crawls every page it can
find (about 700). It fails if a page returns a 5xx, shows an SQL error, or if
PHP logged a warning, notice, deprecation or fatal error while the crawl ran.

```sh
tests/run.sh                              # PHP 8.5, PostgreSQL 18
PG_VERSION=12 tests/run.sh
PHP_VERSION=8.1 PG_VERSION=9.6 tests/run.sh
KEEP=1 tests/run.sh                       # leave it running on :8080 afterwards
BROWSER=1 tests/run.sh                    # also run the headless Chrome checks
```

You need Docker with the Compose plugin and Python 3. `BROWSER=1` also runs
the scripts in `tests/browser` against the app in headless Chrome (the app
shell, the SQL editor and the data grid); it needs Node and Chrome
(`npm ci --prefix tests/browser` once; set `CHROME` if Chrome isn't at
`/usr/bin/google-chrome`). The crawler skips
actions that drop, empty or change things on a GET, but it does click a lot,
so don't point `crawl.py` at a database you care about.

## Releasing

1. Turn `Unreleased` in `HISTORY` into `Version vX.Y.Z-mod` with the date, and
   set `$appVersion` in `libraries/lib.inc.php` to `vX.Y.Z-mod`. That's what
   non-Docker installs and local builds show.
2. Tag it as `vX.Y.Z` and publish a GitHub release from that tag. The image
   shows the tag as the version, `vX.Y.Z-mod`.

The release workflow runs the tests, then builds for amd64 and arm64 and
pushes `X.Y.Z`, `X.Y` and `latest`. A pre-release (`v7.15.0-rc.1`) only gets
its own tag and doesn't move `latest`.

It needs these in the repo settings (Settings > Secrets and variables > Actions):

- variable `DOCKERHUB_USERNAME`: your Docker Hub user or organisation
- variable `DOCKERHUB_IMAGE`: repository name, optional, defaults to `phppgadmin`
- secret `DOCKERHUB_TOKEN`: a Docker Hub access token with read and write
  access. Don't use your account password.

To pick up security updates in the base image without a new release, open
Actions > Release > Run workflow and select the tag you want to rebuild.

## Keeping up with upstream

```sh
git remote add upstream https://github.com/ReimuHakurei/phpPgAdmin.git
git fetch upstream
git merge upstream/master
```

Then run `tests/run.sh` before pushing.

## License

GPL-2.0-or-later, see `LICENSE`. ADOdb (in `libraries/adodb`) is BSD-3-Clause
or LGPL-2.1-or-later. The people who wrote this over the last twenty-odd years
are listed in `CREDITS`.
