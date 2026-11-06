#!/usr/bin/env python3
"""
Check per-database logins against a running phpPgAdmin.

Expects a server configured as "Scoped=db:5432/ppa_other+ppa_test" and a
role that can only connect to ppa_other (see fixtures.sql).

    python3 tests/dblogin.py http://127.0.0.1:8080 scoped scoped
"""

import hashlib
import sys
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET
from http.cookiejar import CookieJar

OTHER = "db:5432:allow:ppa_other"
TEST = "db:5432:allow:ppa_test"


def main(base, user, password):
    base = base.rstrip("/") + "/"
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(CookieJar()))
    failures = []

    def fetch(path, data=None):
        body = urllib.parse.urlencode(data).encode() if data is not None else None
        try:
            with opener.open(base + path, body, timeout=60) as resp:
                return resp.geturl(), resp.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as e:
            return e.geturl(), e.read().decode("utf-8", "replace")

    def check(ok, what):
        print(("ok    " if ok else "FAIL  ") + what)
        if not ok:
            failures.append(what)

    def tree(path):
        if not path:
            return {}
        _, body = fetch(path)
        try:
            return {n.get("text"): n for n in ET.fromstring(body.encode())}
        except ET.ParseError:
            print(body[:1000])
            return {}

    def db_page(server, database):
        return "redirect.php?" + urllib.parse.urlencode(
            {"subject": "database", "server": server, "database": database})

    def is_login(body):
        return "loginUsername" in body

    def database_of(url):
        return urllib.parse.parse_qs(urllib.parse.urlsplit(url).query).get("database", [""])[0]

    # The sidebar shows the server, with its databases under it, before any login.
    root = tree("servers.php?action=tree")
    check("Scoped" in root, "sidebar lists the Scoped server")
    dbs = {}
    if "Scoped" in root and root["Scoped"].get("src"):
        dbs = tree(root["Scoped"].get("src"))
    check(set(dbs) == {"ppa_other", "ppa_test"}, "Scoped lists ppa_other and ppa_test, got %s" % sorted(dbs))
    check("ppa_other" in dbs and not dbs["ppa_other"].get("src"), "ppa_other has no branch before login")

    # Opening a database asks for that database's login.
    _, body = fetch(db_page(OTHER, "ppa_other"))
    check(is_login(body), "opening ppa_other shows a login form")
    check("Scoped / ppa_other" in body, "login form names the server and database")

    # A user that can only connect to ppa_other gets in.
    url, body = fetch("redirect.php", {
        "subject": "database",
        "server": OTHER,
        "database": "ppa_other",
        "loginServer": OTHER,
        "loginUsername": user,
        "loginPassword_" + hashlib.md5(OTHER.encode()).hexdigest(): password,
        "loginSubmit": "Login",
    })
    check(not is_login(body), "login to ppa_other works")
    check("schemas.php" in body and "public" in body, "ppa_other page lists its schemas")
    check("logoutServer=" + urllib.parse.quote(OTHER, safe="") in body.replace("&amp;", "&"),
          "logout link logs out of ppa_other only")

    # The other database on the same server still needs its own login.
    _, body = fetch(db_page(TEST, "ppa_test"))
    check(is_login(body), "ppa_test still asks for its own login")

    # Server-wide pages aren't available with a per-database login.
    url, body = fetch("all_db.php?subject=server&server=" + urllib.parse.quote(OTHER))
    check(database_of(url) == "ppa_other" and not is_login(body),
          "database list sends you to ppa_other instead (ended at %s)" % url)
    url, body = fetch("redirect.php?subject=server&server=" + urllib.parse.quote(OTHER))
    check(database_of(url) == "ppa_other", "server page sends you to ppa_other instead (ended at %s)" % url)
    url, body = fetch("roles.php?subject=server&server=" + urllib.parse.quote(OTHER))
    check(database_of(url) == "ppa_other", "roles page sends you to ppa_other instead (ended at %s)" % url)

    # The ppa_other login can't be pointed at another database.
    url, body = fetch("schemas.php?subject=database&database=ppa_test&server=" + urllib.parse.quote(OTHER))
    check(database_of(url) == "ppa_other", "ppa_other login can't open ppa_test (ended at %s)" % url)

    # The sidebar now lets you expand ppa_other.
    dbs = tree(root["Scoped"].get("src")) if "Scoped" in root else {}
    check("ppa_other" in dbs and dbs["ppa_other"].get("src"), "ppa_other has a branch after login")
    check("ppa_test" in dbs and not dbs["ppa_test"].get("src"), "ppa_test still has no branch")
    if "ppa_other" in dbs and dbs["ppa_other"].get("src"):
        schemas = tree(dbs["ppa_other"].get("src"))
        check(len(schemas) > 0, "ppa_other branch opens")

    # Logging out of ppa_other only affects ppa_other.
    fetch("servers.php?action=logout&logoutServer=" + urllib.parse.quote(OTHER))
    _, body = fetch(db_page(OTHER, "ppa_other"))
    check(is_login(body), "after logout ppa_other asks for a login again")

    print("%d failures" % len(failures))
    return 1 if failures else 0


if __name__ == "__main__":
    if len(sys.argv) != 4:
        sys.exit(__doc__)
    sys.exit(main(*sys.argv[1:]))
