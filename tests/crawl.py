#!/usr/bin/env python3
"""
Log in to a running phpPgAdmin and click through every page we can reach.

Fails if a page returns 5xx, prints a PHP error, or shows an SQL error.
PHP warnings that only go to the log are checked by run.sh, not here.

    python3 tests/crawl.py http://127.0.0.1:8080 db:5432:allow ppa ppa
"""

import hashlib
import html
import re
import sys
import urllib.error
import urllib.parse
import urllib.request
from collections import Counter, deque
from http.cookiejar import CookieJar

MAX_PAGES = 2000
MAX_PER_ACTION = 6

# Actions that change something even on GET, or that would log us out.
SKIP_ACTION = re.compile(
    r"^(drop|del|delete|empty|signal|logout|clear|vacuum|analyze|cluster|reindex"
    r"|nextval|setval|reset|restart|enable|disable|grant|revoke|save|add_|import)"
)
SKIP_PAGE = {"logout.php", "help.php", "redirect.php"}

PAGE_ERRORS = re.compile(
    r"(<b>(Fatal error|Parse error|Warning|Notice|Deprecated)</b>:"
    r"|Fatal error: |Uncaught |SQL error:)"
)

LINK = re.compile(r"""(?:href|src|action)\s*=\s*["']([^"'#]+)""", re.I)


def main(base, server, user, password):
    base = base.rstrip("/") + "/"
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(CookieJar()))

    def fetch(url, data=None):
        body = urllib.parse.urlencode(data).encode() if data is not None else None
        try:
            with opener.open(url, body, timeout=60) as resp:
                return resp.status, resp.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode("utf-8", "replace")

    status, page = fetch(base + "redirect.php", {
        "subject": "server",
        "server": server,
        "loginServer": server,
        "loginUsername": user,
        "loginPassword_" + hashlib.md5(server.encode()).hexdigest(): password,
        "loginSubmit": "Login",
    })
    if status != 200 or "loginUsername" in page:
        print("login failed (HTTP %d)" % status)
        print(page[:2000])
        return 1

    q = urllib.parse.quote(server)
    seeds = [
        "intro.php", "browser.php", "servers.php", "servers.php?action=tree",
        "all_db.php?subject=server&server=" + q,
        "roles.php?subject=server&server=" + q,
        "tablespaces.php?subject=server&server=" + q,
        "database.php?subject=database&database=ppa_test&server=" + q,
        "schemas.php?subject=database&database=ppa_test&server=" + q,
        "tables.php?subject=schema&schema=shop&database=ppa_test&server=" + q,
        "tblproperties.php?subject=table&table=orders&schema=shop&database=ppa_test&server=" + q,
        "display.php?subject=table&table=orders&schema=shop&database=ppa_test&server=" + q,
        "fulltext.php?subject=schema&schema=shop&database=ppa_test&server=" + q,
        "history.php?server=" + q + "&database=ppa_test",
        "sqledit.php?action=sql&server=" + q,
    ]

    # Things that need a POST or odd parameters, so the crawler would never find them.
    db = {"server": server, "database": "ppa_test"}
    posts = [
        ("sql.php", dict(db, query="SELECT * FROM shop.orders; SELECT 1/1;", search_path="shop,public")),
        ("dataexport.php", dict(db, subject="table", table="orders", schema="shop", what="structureanddata",
                                sd_format="sql", output="show", action="export")),
        ("dataexport.php", dict(db, subject="table", table="customer", schema="shop", what="dataonly",
                                d_format="csv", output="show", action="export")),
        ("dbexport.php", dict(db, subject="database", what="structureanddata", sd_format="sql",
                              output="show", action="export")),
        ("dbexport.php", dict(db, subject="table", table="orders", schema="shop", what="structureonly",
                              s_format="sql", output="show", action="export")),
        ("display.php", dict(db, subject="schema", schema="shop", query="SELECT * FROM shop.customer",
                             action="", strings="collapsed")),
    ]

    failures = []
    seen = set()
    per_action = Counter()
    todo = deque(base + s for s in seeds)
    visited = 0

    def check(url, status, body):
        if status >= 500:
            failures.append("%s -> HTTP %d" % (url, status))
            return
        m = PAGE_ERRORS.search(body)
        if m:
            start = max(0, m.start() - 100)
            snippet = re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", body[start:m.end() + 400]))
            failures.append("%s -> %s" % (url, snippet.strip()))

    for path, data in posts:
        status, body = fetch(base + path, data)
        check("POST " + path + " " + str(data.get("subject", "")), status, body)
        if path == "dbexport.php" and "CREATE TABLE" not in body:
            failures.append("POST dbexport.php -> no CREATE TABLE in dump: " + body[:300])

    while todo and visited < MAX_PAGES:
        url = todo.popleft()
        if url in seen:
            continue
        seen.add(url)

        parts = urllib.parse.urlsplit(url)
        page_name = parts.path.rsplit("/", 1)[-1]
        params = urllib.parse.parse_qs(parts.query)
        action = params.get("action", [""])[0]
        key = (page_name, action, params.get("subject", [""])[0])
        if per_action[key] >= MAX_PER_ACTION:
            continue
        per_action[key] += 1

        status, body = fetch(url)
        visited += 1
        check(url, status, body)

        for link in LINK.findall(body):
            link = urllib.parse.urljoin(url, html.unescape(link))
            p = urllib.parse.urlsplit(link)
            name = p.path.rsplit("/", 1)[-1]
            if not link.startswith(base) or not name.endswith(".php") or name in SKIP_PAGE:
                continue
            act = urllib.parse.parse_qs(p.query).get("action", [""])[0]
            if SKIP_ACTION.match(act):
                continue
            todo.append(urllib.parse.urlunsplit(p._replace(fragment="")))

    print("visited %d pages, %d failures" % (visited, len(failures)))
    for f in failures:
        print("  " + f)

    if visited < 100:
        print("too few pages visited, the crawler probably got stuck")
        return 1
    return 1 if failures else 0


if __name__ == "__main__":
    if len(sys.argv) != 5:
        sys.exit(__doc__)
    sys.exit(main(*sys.argv[1:]))
