[![CodeQL](https://github.com/ringostarr80/phpPgAdmin/actions/workflows/github-code-scanning/codeql/badge.svg)](https://github.com/ringostarr80/phpPgAdmin/actions/workflows/github-code-scanning/codeql)
[![codecov](https://codecov.io/gh/ringostarr80/phpPgAdmin/graph/badge.svg)](https://codecov.io/gh/ringostarr80/phpPgAdmin)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fringostarr80%2FphpPgAdmin%2Fmain)](https://dashboard.stryker-mutator.io/reports/github.com/ringostarr80/phpPgAdmin/main)

# phpPgAdmin

## About this project

This project is a fork of [ReimuHakurei/phpPgAdmin](https://github.com/ReimuHakurei/phpPgAdmin).  
The goal is to maintain and actively develop phpPgAdmin, ensuring it stays robust and up to date.  
To achieve this, I plan to gradually rewrite the codebase, following Martin Fowler’s [Strangler Fig](https://martinfowler.com/bliki/StranglerFigApplication.html) approach step by step.

I appreciate any form of help or feedback!

## ⚠️ Important Notice

**This project is currently under active development and is not ready for production use.**  
Many parts of the codebase are still being refactored, and breaking changes may occur frequently.  
Use it at your own risk and feel free to contribute to its progress!

## Project Progress

For an overview of the software quality over time and the evolving metrics, check out the 
[Progress Table](./PROGRESS.md).

## Refactoring Status

### .php files in the root directory

| file               | status | since      |
| ------------------ | ------ | ---------- |
| admin.php          | ❌     | yyyy-mm-dd |
| aggregates.php     | ❌     | yyyy-mm-dd |
| ajax-ac-insert.php | ❌     | yyyy-mm-dd |
| all_db.php         | ✅     | 2025-08-08 |
| autoload.php       | ✅     | 2024-12-19 |
| browser.php        | ✅     | 2024-12-29 |
| casts.php          | ❌     | yyyy-mm-dd |
| colproperties.php  | ❌     | yyyy-mm-dd |
| constraints.php    | ❌     | yyyy-mm-dd |
| conversions.php    | ❌     | yyyy-mm-dd |
| database.php       | ❌     | yyyy-mm-dd |
| dataexport.php     | ❌     | yyyy-mm-dd |
| dataimport.php     | ❌     | yyyy-mm-dd |
| dbexport.php       | ✅     | 2026-03-01 |
| display.php        | ❌     | yyyy-mm-dd |
| domains.php        | ❌     | yyyy-mm-dd |
| fulltext.php       | ❌     | yyyy-mm-dd |
| functions.php      | ❌     | yyyy-mm-dd |
| groups.php         | ❌     | yyyy-mm-dd |
| help.php           | ❌     | yyyy-mm-dd |
| history.php        | ✅     | 2025-09-14 |
| index.php          | ✅     | 2024-12-26 |
| indexes.php        | ❌     | yyyy-mm-dd |
| info.php           | ❌     | yyyy-mm-dd |
| intro.php          | ✅     | 2025-01-17 |
| languages.php      | ✅     | 2026-11-16 |
| login.php          | ✅     | 2025-01-29 |
| logout.php         | ✅     | 2025-03-02 |
| opclasses.php      | ❌     | yyyy-mm-dd |
| operators.php      | ❌     | yyyy-mm-dd |
| plugin.php         | ❌     | yyyy-mm-dd |
| privileges.php     | ❌     | yyyy-mm-dd |
| redirect.php       | ❌     | yyyy-mm-dd |
| roles.php          | ✅     | 2025-11-27 |
| rules.php          | ❌     | yyyy-mm-dd |
| schemas.php        | ❌     | yyyy-mm-dd |
| sequences.php      | ❌     | yyyy-mm-dd |
| servers.php        | ✅     | 2025-01-19 |
| sql.php            | ❌     | yyyy-mm-dd |
| sqledit.php        | ✅     | 2025-08-26 |
| tables.php         | ❌     | yyyy-mm-dd |
| tablespaces.php    | ✅     | 2026-03-18 |
| tblproperties.php  | ❌     | yyyy-mm-dd |
| triggers.php       | ❌     | yyyy-mm-dd |
| types.php          | ❌     | yyyy-mm-dd |
| users.php          | ❌     | yyyy-mm-dd |
| viewproperties.php | ❌     | yyyy-mm-dd |
| views.php          | ❌     | yyyy-mm-dd |