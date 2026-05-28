# JmLib – Deep Code Analysis & Improvement Proposals

**Scope:** [src/Database.php](src/Database.php), [src/Modul.php](src/Modul.php)
**Date:** 2026-05-27

This document is the result of a deep code review focused on **security**, **performance**,
**optimization**, **clean code**, and **API ergonomics**. Each finding has a severity rating,
a concrete description and a proposed remediation with code snippets.

> Severity legend: 🔴 Critical · 🟠 High · 🟡 Medium · 🟢 Low / Style

---

## Table of Contents

### Security

- [S1 – SQL injection in `Modul::set()`](#s1--sql-injection-in-modulset-)
- [S2 – SQL injection in `Modul::syncManyToMany()`](#s2--sql-injection-in-modulsyncmanytomany-)
- [S3 – SQL injection via `Modul::get()` `$where` / `$order`](#s3--sql-injection-via-modulget-where--order-)
- [S4 – Remote code execution via `eval()` in `getTotalEval()`](#s4--remote-code-execution-via-eval-in-gettotaleval-)
- [S5 – `Modul::createFulltextSubquery()` injects raw user input into LIKE](#s5--modulcreatefulltextsubquery-injects-raw-user-input-into-like-)
- [S6 – Public DB credentials, no encapsulation](#s6--public-db-credentials-no-encapsulation-)
- [S7 – No TLS / charset / strict-mode enforcement on connection](#s7--no-tls--charset--strict-mode-enforcement-on-connection-)
- [S8 – `mysqli` not configured to throw exceptions; silent failures](#s8--mysqli-not-configured-to-throw-exceptions-silent-failures-)
- [S9 – `Modul::sanitize()` uses `FILTER_SANITIZE_NUMBER_INT` which is not a validator](#s9--modulsanitize-uses-filter_sanitize_number_int-which-is-not-a-validator-)
- [S10 – `mysqli_real_escape_string` used as a generic sanitizer](#s10--mysqli_real_escape_string-used-as-a-generic-sanitizer-)

### Performance

- [P1 – `SQL_CALC_FOUND_ROWS` is deprecated and slow](#p1--sql_calc_found_rows-is-deprecated-and-slow-)
- [P2 – `Modul::getRandom()` loads the entire result set into PHP](#p2--modulgetrandom-loads-the-entire-result-set-into-php-)
- [P3 – `Database::getResult()` allocates whole row to read one value](#p3--databasegetresult-allocates-whole-row-to-read-one-value-)
- [P4 – Repeated `function_exists("strripos")` runtime check](#p4--repeated-function_existsstrripos-runtime-check-)
- [P5 – Building the same `$text_mappings` for every `get()` call](#p5--building-the-same-text_mappings-for-every-get-call-)
- [P6 – `microtime()` parsing instead of `microtime(true)`](#p6--microtime-parsing-instead-of-microtimetrue-)
- [P7 – Unbounded `messages['queries']` array – memory leak](#p7--unbounded-messagesqueries-array--memory-leak-)
- [P8 – Cache invalidation in `set()` is incorrect (`unset($this->cache)`)](#p8--cache-invalidation-in-set-is-incorrect-unsetthis-cache-)
- [P9 – `Modul::getId()` re-queries when cache is partial](#p9--modulgetid-re-queries-when-cache-is-partial-)
- [P10 – `syncManyToMany()` deletes all rows then re-inserts](#p10--syncmanytomany-deletes-all-rows-then-re-inserts-)

### Correctness / Bugs

- [B1 – `$last_from` may be undefined](#b1--last_from-may-be-undefined-)
- [B2 – Reconnect inside `connect()` resets counters every time](#b2--reconnect-inside-connect-resets-counters-every-time-)
- [B3 – `Database::getRow()` returns `false` at end-of-result instead of `null`](#b3--databasegetrow-returns-false-at-end-of-result-instead-of-null-)
- [B4 – `numRows()` uses `@` to hide a real error](#b4--numrows-uses--to-hide-a-real-error-)
- [B5 – `Modul::get()` `$where` may be `null` then appended to](#b5--modulget-where-may-be-null-then-appended-to-)
- [B6 – `Modul::sanitize()` reports `false` as a legit value for some types](#b6--modulsanitize-reports-false-as-a-legit-value-for-some-types-)
- [B7 – `Modul::getTotal()` ignores `$values['id']` keys that don't exist](#b7--modulgettotal-ignores-valuesid-keys-that-dont-exist-)
- [B8 – `findId()` returns `null` vs `false` vs `0` inconsistently](#b8--findid-returns-null-vs-false-vs-0-inconsistently-)
- [B9 – `Modul::getId()` uses `$this->cache[$id]` without `isset` (Notice)](#b9--modulgetid-uses-this-cacheid-without-isset-notice-)
- [B10 – Constructor returning a value (anti-pattern)](#b10--constructor-returning-a-value-anti-pattern-)

### Clean Code / API

- [C1 – Pass-by-reference of object in constructor is meaningless](#c1--pass-by-reference-of-object-in-constructor-is-meaningless-)
- [C2 – Boolean / type mixing in signatures (`array|false|null`)](#c2--boolean--type-mixing-in-signatures-arrayfalsenull-)
- [C3 – Mix of Czech / English comments, no PHPDoc on most methods](#c3--mix-of-czech--english-comments-no-phpdoc-on-most-methods-)
- [C4 – Numeric column-index ordering API is opaque](#c4--numeric-column-index-ordering-api-is-opaque-)
- [C5 – Public mutable state (`$cache`, `$cache_total`, `$cache_sql`, `$DB`)](#c5--public-mutable-state-cache-cache_total-cache_sql-db-)
- [C6 – No interface / no DI – `Modul` is tightly coupled to `mysqli`](#c6--no-interface--no-di--modul-is-tightly-coupled-to-mysqli-)
- [C7 – Dead / commented-out code](#c7--dead--commented-out-code-)
- [C8 – `return (false);` style with redundant parentheses](#c8--return-false-style-with-redundant-parentheses-)

---

## Security

### S1 – SQL injection in `Modul::set()` 🔴

**File:** [src/Modul.php](src/Modul.php)

```php
foreach ($set as $key => $value) {
    $sql_temp[] = $this->sql_table . '.' . $key . ' = ' . $value;
}
...
$sql = $this->sql_insert . ' (' . implode(', ', array_keys($set))
     . ') VALUES (' . implode(', ', $set) . ');';
...
$sql = $this->sql_update . ' SET ' . implode(', ', $sql_temp)
     . ' WHERE ' . $this->sql_table . '.' . $this->id_format
     . ' IN ("' . implode('", "', $ids) . '");';
```

Both keys and values from `$set` and `$ids` are concatenated directly into SQL. The caller is
expected to pre-quote every value – an easy mistake to forget – and even then the column names
are never validated. A single un-quoted `$value` or a poisoned `$ids[]` results in a full SQL
injection.

**Fix:** Use prepared statements with `mysqli::prepare()` / `bind_param()`. Whitelist column
names against a known schema list, never accept arbitrary identifiers from the caller.

```php
$columns = array_intersect(array_keys($set), $this->allowed_columns);
$placeholders = array_fill(0, count($columns), '?');
$sql = sprintf(
    'INSERT INTO %s (%s) VALUES (%s)',
    $this->sql_table,
    implode(', ', array_map([$this, 'quoteIdentifier'], $columns)),
    implode(', ', $placeholders)
);
$stmt = $this->DB->prepare($sql);
$stmt->bind_param(str_repeat('s', count($columns)), ...array_values($set));
$stmt->execute();
```

---

### S2 – SQL injection in `Modul::syncManyToMany()` 🔴

```php
$this->DB->query('DELETE FROM ' . $config['table']
    . ' WHERE ' . $config['main_key'] . ' = ' . (int)$main_id . ';');
...
foreach ($config['columns'] as $column_name) {
    $values[] = $relation_data[$column_name] ?? 'NULL';
}
$sql_rows[] = '(' . implode(', ', $values) . ')';
```

`$main_id` is cast to `int` (good), but **`$config['table']`, `$config['main_key']`, `$config['columns']`
and the row values are concatenated unescaped**. If `$many_to_many` is ever populated from
untrusted input – or if a relation row contains a string – this is direct SQL injection.

**Fix:**

1. Validate `table`, `main_key`, `columns` against a registered whitelist when the model is
   constructed; reject anything not matching `^[A-Za-z_][A-Za-z0-9_]*$`.
2. Use prepared statements + multi-row binding for the `INSERT`.
3. Wrap delete + insert in a transaction to avoid leaving the table empty if the insert fails.

---

### S3 – SQL injection via `Modul::get()` `$where` / `$order` 🔴

```php
if (!is_array($where) && isset($where)) {
    $where = array($where);
}
...
$sql .= ' WHERE ' . implode(' AND ', $where);
```

`$where` is treated as a raw SQL fragment. Every caller must remember to escape every value.
The pattern `findId('email = "' . $userInput . '"')` is the dominant usage and trivially
injectable.

**Fix:** Introduce a small criteria/builder API:

```php
$modul->where('email', '=', $email)->where('status', 'IN', $statuses)->get();
```

Internally produce parameterized SQL. Keep a temporary raw-fragment escape hatch only for
trusted callers, and clearly mark it `@internal`.

---

### S4 – Remote code execution via `eval()` in `getTotalEval()` 🔴

```php
public function getTotalEval($dataset = null, $values = null) {
    ...
    if (strpos($key, ']')) {
        eval('$in = $row' . $key . ';');
    }
    ...
    eval('$output' . $key . '+=' . $add . ';');
}
```

The function evaluates a string built from `$key` (an array key supplied by the caller). If
that key comes anywhere near user input, this is RCE. Even if not directly exploited, `eval()`
makes static analysis impossible and the function 5–10× slower than `array_walk_recursive`.

**Fix:** Replace with a real recursive accessor:

```php
private function valueByPath(array $row, array $path) {
    foreach ($path as $segment) {
        if (!is_array($row) || !array_key_exists($segment, $row)) return null;
        $row = $row[$segment];
    }
    return $row;
}
```

Accept `$values` as `['path' => [['a','b','c'], 'sum'], ...]` and drop `eval()` entirely.

---

### S5 – `Modul::createFulltextSubquery()` injects raw user input into LIKE 🟠

```php
$input = mb_strtolower(preg_replace('/[^a-ž0-9 ]+/i', ' ', $input));
...
foreach ($words as $word) {
    $query[] = $word_query . ' LIKE "%' . $word . '%"';
}
```

- The regex `[^a-ž0-9 ]+` does **not** mean "letters and digits" – the range `a-ž` is locale-
  dependent and matches a giant slice of Unicode code-points including `\` and quote-like
  glyphs in some encodings.
- LIKE wildcards `%` and `_` in `$word` are not escaped.
- The final string is concatenated into the SQL, so a survived `"` ends the literal.

**Fix:**

```php
$word = str_replace(['\\', '%', '_', '"'], ['\\\\', '\\%', '\\_', '\\"'], $word);
```

…and pass `$word` as a parameter through a prepared statement instead of inlining it.

---

### S6 – Public DB credentials, no encapsulation 🟠

```php
public $user;
public $password;
public $database;
public $server;
public $db;
```

Anything in the application can read or rewrite the password. Visibility leaks credentials into
`var_dump`, logs, exceptions, and serialization.

**Fix:**

```php
private string $server;
private string $database;
private string $user;
private string $password;
private ?mysqli $db = null;

public function __debugInfo(): array {
    return ['server' => $this->server, 'database' => $this->database, 'user' => $this->user];
}
```

Also implement `__sleep()` to exclude `$password` from serialization.

---

### S7 – No TLS / charset / strict-mode enforcement on connection 🟠

```php
$this->db = new mysqli($this->server, $this->user, $this->password, $this->database);
```

Issues:

- No charset → defaults to server's, frequently `latin1`. **Mismatched charsets defeat
  `mysqli_real_escape_string` and reintroduce SQL injection** with multibyte payloads.
- No `SET sql_mode = 'STRICT_ALL_TABLES,...'` → silent truncations.
- No TLS option even when reaching cross-host DBs.
- No port / socket parameters – not configurable.

**Fix:**

```php
$this->db = mysqli_init();
$this->db->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
// optionally: $this->db->ssl_set(...);
$this->db->real_connect($host, $user, $pass, $db, $port, $socket, $flags);
$this->db->set_charset('utf8mb4');
$this->db->query("SET SESSION sql_mode='STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION'");
```

---

### S8 – `mysqli` not configured to throw exceptions; silent failures 🟠

`mysqli` defaults to error-as-return-value. The current code never inspects
`mysqli_error($this->db)` and silently continues with `$this->result = false`, which means
**every callsite that does `while ($DB->getRow())`** silently treats a failed query as an
empty result.

**Fix:** Enable exceptions globally:

```php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
```

Wrap `query()` so it logs/throws `\RuntimeException` on failure. Callers can decide to catch.

---

### S9 – `Modul::sanitize()` uses `FILTER_SANITIZE_NUMBER_INT` which is not a validator 🟡

```php
case 'int':
    return (filter_var($value, FILTER_SANITIZE_NUMBER_INT));
```

This sanitizer only strips characters – it returns the string `"-1+5"` for `"-1+5"`. It does
not produce a safe integer. The result is then inserted into SQL without quoting.

**Fix:** Cast or validate:

```php
case 'int':
    $v = filter_var($value, FILTER_VALIDATE_INT);
    return $v === false ? ($required ? false : 0) : $v;
```

---

### S10 – `mysqli_real_escape_string` used as a generic sanitizer 🟡

```php
return ($value ? mysqli_real_escape_string($this->DB->db, (string)$value) : "");
```

`real_escape_string` only escapes for inclusion **between quotes**. Callers happily concatenate
the result without quotes (`WHERE id = ` . sanitize($id)`), which makes the escape ineffective.
Also it triggers connection-on-use through `$this->DB->db`, which may be `null`.

**Fix:** Provide explicit helpers `quoteString()`, `quoteInt()`, `quoteIdentifier()` that
include the quotes, and route everything through prepared statements where possible.

---

## Performance

### P1 – `SQL_CALC_FOUND_ROWS` is deprecated and slow 🟠

`SQL_CALC_FOUND_ROWS` was deprecated in MySQL 8.0.17 and is consistently slower than running
the query twice (`SELECT COUNT(*)` + `SELECT … LIMIT`). MariaDB has also de-emphasized it.

**Fix:** Drop `SQL_CALC_FOUND_ROWS`. When the caller asks for a total, run a second optimized
`SELECT COUNT(*)` (without `ORDER BY`, without selected columns) reusing the same `$where`.
Cache the result for the request lifetime.

---

### P2 – `Modul::getRandom()` loads the entire result set into PHP 🟠

```php
public function getRandom(...) {
    $data = $this->get(...);
    $keys = array_rand($data, $count);
    ...
}
```

For a table with 1M rows this loads 1M rows into memory just to throw away all but one.

**Fix:** Push randomness to the DB:

```sql
SELECT * FROM t WHERE id >= (SELECT FLOOR(MIN(id) + RAND() * (MAX(id)-MIN(id))) FROM t) LIMIT N;
-- or, for small tables:
SELECT * FROM t ORDER BY RAND() LIMIT N;
```

Document the trade-off (skewed if many holes in IDs) and let callers choose.

---

### P3 – `Database::getResult()` allocates whole row to read one value 🟢

```php
$row = mysqli_fetch_array($result ? $result : $this->result);
return ($row[0]);
```

`mysqli_fetch_array` builds both numeric and assoc copy. Use `mysqli_fetch_row()` (numeric
only) or `fetch_column()` (PHP 8.1+).

---

### P4 – Repeated `function_exists("strripos")` runtime check 🟢

```php
if (function_exists("strripos")) { ... }
```

`strripos()` has existed since PHP 5.0. The fallback branch is dead and just makes the code
harder to follow. Remove both checks.

---

### P5 – Building the same `$text_mappings` for every `get()` call 🟡

```php
foreach ($this->text as $t_lang => $t_data) {
    foreach ($t_data as $t_key => $t_value) {
        $text_mappings[$t_key][] = [$t_key . '_' . $t_lang, $t_value];
    }
}
```

`$this->text` is set in the model and rarely changes. Compute `$text_mappings` lazily once
and cache it on the instance (invalidate when `text` is changed via setter).

---

### P6 – `microtime()` parsing instead of `microtime(true)` 🟢

```php
list($usec, $sec) = explode(" ", microtime());
$time_start = (float) $usec + (float) $sec;
```

Replace with `$time_start = microtime(true);` everywhere. Identical precision, simpler, faster.

---

### P7 – Unbounded `messages['queries']` array – memory leak 🟡

Every executed query is appended to `$this->messages['queries']`. On long-running scripts
(CLI workers, batch jobs) this grows without bound and inflates memory until OOM.

**Fix:**

- Add a constructor flag `bool $debug` that defaults to `false` in production.
- When enabled, cap the buffer (`array_slice($queries, -1000)` or use a fixed-size ring).
- Provide `Database::resetMessages()` for use between batches.

---

### P8 – Cache invalidation in `set()` is incorrect (`unset($this->cache)`) 🟡

```php
unset($this->cache);
unset($this->cache_total);
```

`unset` removes the property entirely. The next read of `$this->cache[$id]` will create it
back as `null` with an `E_NOTICE` ("Trying to access offset on null").

**Fix:**

```php
$this->cache = [];
$this->cache_total = null;
```

---

### P9 – `Modul::getId()` re-queries when cache is partial 🟢

When some IDs are in the cache and some are not, the function builds a `WHERE id IN (...)` for
just the missing ones – correct – but then merges results with `array_merge`. The cache itself
is **not** populated with the new rows: only `get()` writes to it. Subsequent calls with the
same IDs hit the DB again.

**Fix:** After fetching, store rows back into `$this->cache` indexed by `id_format`.

---

### P10 – `syncManyToMany()` deletes all rows then re-inserts 🟢

The current strategy `DELETE … WHERE main_id = X; INSERT …` always rewrites the whole set
and breaks `ON DELETE CASCADE` triggers and audit logs.

**Fix:** Compute diff:

```php
$existing = fetch existing relations by main_id;
$to_delete = array_diff_key($existing, $desired);
$to_insert = array_diff_key($desired, $existing);
```

Use a transaction to keep it atomic.

---

## Correctness / Bugs

### B1 – `$last_from` may be undefined 🟠

```php
if (function_exists("strripos")) {
    $pos_group_by = strripos($sql, 'GROUP BY ');
    $last_from = strripos($sql, 'FROM ');
    ...
}
...
if (function_exists("strripos")) {
    $pos_where = strripos($sql, 'WHERE ');
    if ($pos_where > $last_from) { ... }
}
```

If `GROUP BY` is not detected the first branch may still set `$last_from`, but if the
two `function_exists` checks ever diverged (refactor risk), `$last_from` would be an
**undefined variable** at the `>` comparison – PHP 8 raises a warning. Also `strripos`
returns `false` for "not found", which `>` will compare as `0`, leading to subtle truncation
of the SQL.

**Fix:** Initialize `$last_from = false;` before the block and check with `!==`:

```php
$last_from = false;
if (($p = strripos($sql, 'FROM ')) !== false) { $last_from = $p; }
```

---

### B2 – Reconnect inside `connect()` resets counters every time 🟢

```php
private function connect() {
    ...
    $this->messages['total_time'] = 0;
    $this->messages['total_queries'] = 0;
}
```

Both fields are already set in the constructor. Resetting them again on every reconnect makes
metrics inaccurate.

**Fix:** Remove these two lines from `connect()`.

---

### B3 – `Database::getRow()` returns `false` at end-of-result instead of `null` 🟢

`mysqli_fetch_assoc` returns `null` when there are no more rows and `false` only on errors.
The current code conflates the two.

**Fix:**

```php
$row = mysqli_fetch_assoc($result ?? $this->result);
return $row; // null = end, false = error, array = data
```

Callers should check `=== null` to stop iteration and `=== false` to treat as failure.

---

### B4 – `numRows()` uses `@` to hide a real error 🟢

```php
$output = @mysqli_num_rows($this->result);
```

If `$this->result` is `false` (failed query) or not a `mysqli_result`, `mysqli_num_rows`
errors. `@` silences it instead of fixing the cause.

**Fix:** Check the type first:

```php
if ($this->result instanceof mysqli_result) {
    return mysqli_num_rows($this->result);
}
return mysqli_affected_rows($this->db);
```

---

### B5 – `Modul::get()` `$where` may be `null` then appended to 🟢

```php
if (!is_array($where) && isset($where)) {
    $where = array($where);
}
if ($sql_where) {
    $where[] = $sql_where; // <-- $where may still be null here
}
```

In PHP 8 implicit array creation from `null` produces a deprecation warning.

**Fix:**

```php
$where = isset($where) ? (array)$where : [];
```

---

### B6 – `Modul::sanitize()` reports `false` as a legit value for some types 🟢

```php
case 'email':
    return (filter_var($value, FILTER_VALIDATE_EMAIL));
```

`filter_var()` returns the email **or `false`**. Concatenated into SQL, `false` becomes the
empty string. Callers cannot distinguish "no value" from "invalid email".

**Fix:** Return `null` for invalid and document the contract.

---

### B7 – `Modul::getTotal()` ignores `$values['id']` keys that don't exist 🟢

```php
$output[$key] = $output[$key] / (((int)$output['id'] && $values['id'] == 'sum') ? $output['id'] : $counter[$key]);
```

This silently divides by `$counter[$key]` even if it is `0` (E_WARNING) and assumes `id` is
both in `$values` and in `$output`. Add explicit guards.

---

### B8 – `findId()` returns `null` vs `false` vs `0` inconsistently 🟢

`findId()` may return `null` (no `$where`), `$data['id']` (string), an array, or fall through
to `null`. Callers using `===` cannot rely on the return type. Standardize on `?int`.

---

### B9 – `Modul::getId()` uses `$this->cache[$id]` without `isset` (Notice) 🟢

```php
if ($this->cache[$id]) { ... }
```

When `$id` is not present this is `null` → no error in PHP 7, **warning in PHP 8**.

**Fix:**

```php
if (isset($this->cache[$id])) { ... }
```

---

### B10 – Constructor returning a value (anti-pattern) 🟢

```php
public function __construct(Database &$database) {
    ...
    if (!is_object($this->DB)) {
        return (false);
    }
    return (true);
}
```

Constructor return values are ignored by PHP. Use exceptions for failures:

```php
public function __construct(Database $database) {
    $this->DB = $database;
}
```

---

## Clean Code / API

### C1 – Pass-by-reference of object in constructor is meaningless 🟢

```php
public function __construct(Database &$database) {
    $this->DB = &$database;
}
```

Objects in PHP are already passed by handle. The `&` only matters when the caller wants to
re-seat the variable from inside the constructor – which never happens. Remove the `&`.

---

### C2 – Boolean / type mixing in signatures (`array|false|null`) 🟢

```php
public function set(array|false|null $set = null, array|int|null $ids = null, ...
```

`false` as an input type is a smell. Callers should pass `null` or an empty array. Returning
`int|false` should become `?int` and the callers updated to check `=== null`.

---

### C3 – Mix of Czech / English comments, no PHPDoc on most methods 🟢

Comments mix Czech (`# zaklad SQL dotazu`) and English. For a public library this is a
barrier for adoption.

**Fix:** Translate to English, add `@param` / `@return` PHPDoc everywhere. Consider running
**PHPStan** (level 6+) and **Psalm** in CI – many of the bugs in this list would be caught.

---

### C4 – Numeric column-index ordering API is opaque 🟡

```php
protected $order = -6;
...
foreach (explode(',', $order) as $part_order) {
    if (is_numeric($part_order)) {
        if ($part_order < 0) {
            $orders[] = (-1 * $part_order) . ' DESC';
```

`$order = -6` means "order by column 6 desc". This is fragile (column re-ordering breaks
behaviour) and not self-documenting.

**Fix:** Accept column names with a leading `-` for DESC:

```php
$order = '-created_at, name'; // ORDER BY created_at DESC, name ASC
```

Whitelist names against a list of sortable columns.

---

### C5 – Public mutable state (`$cache`, `$cache_total`, `$cache_sql`, `$DB`) 🟢

Exposing internal caches as public properties breaks encapsulation and lets callers corrupt
state. Make them `protected` (or `private`) and add readonly accessors where genuinely needed.

---

### C6 – No interface / no DI – `Modul` is tightly coupled to `mysqli` 🟢

`Modul` reaches into `$this->DB->db` to escape strings. A change of driver (PDO, MySQLi
async, a fake for tests) requires modifying every model.

**Fix:** Define a `DatabaseInterface` with the minimal API (`query`, `prepare`, `quote`,
`getRow`, `getAllRows`, `getId`, `getNumAffected`, `getRowsCount`). Inject it. Provide a
`PdoDatabase` adapter for migration off `mysqli`.

---

### C7 – Dead / commented-out code 🟢

- `// echo ('INSERT INTO ' ...);` left in `syncManyToMany()` – debug print.
- `/* if ($result) return (mysql_result …); */` referring to long-removed `ext/mysql`.
- Branches behind `function_exists("strripos")`.

Remove. Use `git log` if you need history.

---

### C8 – `return (false);` style with redundant parentheses 🟢

PHP's `return` is a statement, not a function. `return (false);` is identical to
`return false;`. Pick one style (PSR-12 favours no parentheses) and run `php-cs-fixer`.

---

## Recommended Roadmap

1. **Stop the bleeding (security):** S1 → S4, then S5 → S10. Add `mysqli_report` exceptions
   and `set_charset('utf8mb4')` first – they are 4-line changes that immediately reduce risk.
2. **Adopt prepared statements** via a new `DatabaseInterface` (C6). Migrate `set()`, `get()`
   and `syncManyToMany()` first.
3. **Replace `SQL_CALC_FOUND_ROWS`** (P1) and the in-PHP randomness (P2).
4. **Static analysis:** introduce PHPStan level 6 + Psalm in CI; existing bugs B1, B5, B9 are
   caught automatically.
5. **Clean-up sweep:** translate comments, drop dead branches (P4, C7), unify return types
   (C2, B3, B8).
6. **Tests:** the project already uses Pest – add cases for SQL injection regressions, M:N
   sync diff, `ORDER BY` whitelisting, and cache invalidation.

---

## Quick wins (one-liner fixes)

| Where | Change |
| --- | --- |
| `Database::__construct` | `private` properties for `$password`, `$user`, `$server`, `$database` |
| `Database::connect`     | add `mysqli_report(MYSQLI_REPORT_ERROR \| MYSQLI_REPORT_STRICT)` and `set_charset('utf8mb4')` |
| `Database::query`       | replace `microtime()` parsing with `microtime(true)` |
| `Database::getRow`      | return `null` at end-of-result, drop the `is_array` wrapper |
| `Database::numRows`     | remove `@`, check `instanceof mysqli_result` |
| `Modul::__construct`    | drop `&` reference and `return (true/false)` |
| `Modul::get`            | initialize `$where = []`, `$last_from = false` |
| `Modul::getId`          | guard with `isset()` |
| `Modul::set`            | `$this->cache = []; $this->cache_total = null;` instead of `unset` |
| `Modul::getTotalEval`   | delete; replace callers with array-path helper |

These alone cut around half of the listed issues without touching the public API.
