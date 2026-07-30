---
name: MariaDB reserved words in SQL aliases
description: Words like LEAVE, GROUP, RANK are reserved in MariaDB and cause syntax errors when used bare as column aliases or table aliases.
---

## Rule
Always backtick-quote SQL aliases (and column/table names) that match MariaDB reserved keywords.

**Why:** MariaDB (and MySQL) reserve words like `LEAVE`, `GROUP`, `RANK`, `KEY`, `VALUES`, `STATUS`, `READ` etc. Using them bare as aliases causes a 1064 syntax error even though the query looks valid. PHP's PDO gives no hint about which word is the problem.

**How to apply:** Any time you write `SUM(...) AS leave` or `COUNT(...) AS rank`, wrap it: `` SUM(...) AS `leave` ``. When in doubt about a short English word used as an alias, backtick it.

## Known offenders encountered in this project
- `leave` — used as alias for `SUM(status='Leave')` in staff_attendance monthly summary query
