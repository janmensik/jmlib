# JmLib – Code Issues & Fix Instructions

Generated: 2026-05-25  
Scope: `src/Database.php`, `src/Modul.php`

---

## Table of Contents

1. [#1 – Credentials stored as public properties](#1--credentials-stored-as-public-properties)
2. [#2 – No connection error handling](#2--no-connection-error-handling)
3. [#3 – Reconnect resets debug counters](#3--reconnect-resets-debug-counters)
4. [#4 – No character set enforcement](#4--no-character-set-enforcement)
5. [#5 – `getRow()` returns `false` instead of `null` at end-of-result](#5--getrow-returns-false-instead-of-null-at-end-of-result)
6. [#6 – `@` error suppression in `numRows()`](#6----error-suppression-in-numrows)
7. [#7 – `getResult()` fetches a full array to get `$row[0]`](#7--getresult-fetches-a-full-array-to-get-row0)
8. [#8 – No prepared-statement API](#8--no-prepared-statement-api)
9. [#9 – SQL injection in `set()` — CRITICAL](#9--sql-injection-in-set--critical)
10. [#10 – SQL injection in `syncManyToMany()`— CRITICAL](#10--sql-injection-in-syncmanytomany-critical)
11. [#11 – `eval()` in `getTotalEval()` — CRITICAL](#11--eval-in-gettotaleval--critical)
12. [#12 – Fragile SQL parsing via string manipulation](#12--fragile-sql-parsing-via-string-manipulation)
13. [#13 – Dead `strripos` existence check](#13--dead-strripos-existence-check)
14. [#14 – Numeric column-index ordering](#14--numeric-column-index-ordering)
15. [#15 – `getRandom()` loads all rows into PHP memory](#15--getrandom-loads-all-rows-into-php-memory)
16. [#16 – `$cache` and `$cache_total` are public](#16--cache-and-cache_total-are-public)
17. [#17 – Constructor returns a value (PHP anti-pattern)](#17--constructor-returns-a-value-php-anti-pattern)
18. [#18 – `$last_from` used without being defined](#18--last_from-used-without-being-defined)
19. [#19 – `sanitize()` mixes validation and escaping](#19--sanitize-mixes-validation-and-escaping)
20. [#20 – `createFulltextSubquery()` inserts user input raw into LIKE](#20--createfulltextsubquery-inserts-user-input-raw-into-like)

---

## #1 – Credentials stored as public properties

**File:** `Database.php`  
**Risk:** High — any code with access to the `Database` object can read or overwrite the DB password.

### Problem

```php
public $user;
public $password;
public $database;
public $server;
public $db;
```

### Fix

Change visibility to `private`. Add read-only getters only for properties that must be observable from outside (e.g. `$server`, `$database`).

```php
private string $user;
private string $password;
private string $database;
private string $server;
private ?\mysqli $db = null;
```

Because `Modul::sanitize()` currently accesses `$this->DB->db` directly, introduce a package-internal accessor or move the escape call into `Database` itself:

```php
// In Database.php
public function escape(string $value): string {
    if (!$this->db) {
        $this->connect();
    }
    return mysqli_real_escape_string($this->db, $value);
}
```

Then in `Modul::sanitize()`:

```php
// Before:
return ($value ? mysqli_real_escape_string($this->DB->db, (string)$value) : "");

// After:
return ($value ? $this->DB->escape((string)$value) : '');
```

---

## #2 – No connection error handling

**File:** `Database.php` → `connect()`  
**Risk:** High — failed connections are silently swallowed; callers receive no error and subsequent queries fail with cryptic messages.

### Problem

```php
$this->db = new mysqli($this->server, $this->user, $this->password, $this->database);

if (!$this->db) {        // new mysqli() NEVER returns false
    $this->messages['system'] = 'DB not connected!';
    return (false);
}
```

`new mysqli()` always returns an object. Whether the connection actually succeeded must be checked via `connect_error`.

### Fix

```php
private function connect(): \mysqli {
    $this->db = new \mysqli($this->server, $this->user, $this->password, $this->database);

    if ($this->db->connect_error) {
        throw new \RuntimeException(
            'Database connection failed: ' . $this->db->connect_error,
            $this->db->connect_errno
        );
    }

    $this->db->set_charset('utf8mb4'); // see also issue #4
    $this->messages['system'] = 'DB connected.';
    return $this->db;
}
```

Update `query()` to not check `if (!$this->db)` before calling — let the exception propagate, or catch it there and store in `$this->messages['system']` if you want soft failure behavior.

---

## #3 – Reconnect resets debug counters

**File:** `Database.php` → `connect()`  
**Risk:** Medium — if `connect()` is called more than once (e.g. after a timeout), `total_time` and `total_queries` are zeroed, making profiling data unreliable.

### Problem

```php
private function connect() {
    // ...
    $this->messages['total_time'] = 0;   // resets accumulated stats!
    $this->messages['total_queries'] = 0;
}
```

The same initialization also lives in `__construct()`, which is the correct place.

### Fix

Remove the counter resets from `connect()`. They belong only in `__construct()`:

```php
private function connect(): \mysqli {
    $this->db = new \mysqli($this->server, $this->user, $this->password, $this->database);

    if ($this->db->connect_error) {
        throw new \RuntimeException('Database connection failed: ' . $this->db->connect_error);
    }

    // Do NOT reset total_time / total_queries here
    $this->messages['system'] = 'DB connected.';
    return $this->db;
}
```

---

## #4 – No character set enforcement

**File:** `Database.php` → `connect()`  
**Risk:** High — without explicitly setting the charset to `utf8mb4`, multi-byte characters can be corrupted and certain encoding-based SQL injection techniques become possible.

### Problem

`connect()` opens the connection but never calls `set_charset()`. PHP's default may not match the server's charset.

### Fix

Add immediately after a successful connection (ideally as part of the fix for #2):

```php
$this->db->set_charset('utf8mb4');
```

If your database uses a different collation (e.g. `utf8`), replace accordingly — but `utf8mb4` is the recommended default for all new MySQL/MariaDB work.

---

## #5 – `getRow()` returns `false` instead of `null` at end-of-result

**File:** `Database.php` → `getRow()`  
**Risk:** Low — causes semantic confusion; callers cannot distinguish "no connection" from "no more rows".

### Problem

```php
$radka = mysqli_fetch_assoc($this->result);
if (is_array($radka)) {
    return ($radka);
} else {
    return (false);  // mysqli_fetch_assoc returns null at end, not false
}
```

### Fix

The docblock already says the function can return `null`; align the implementation:

```php
public function getRow($result = null): array|false|null {
    if (!$this->db) {
        return false; // connection error sentinel
    }

    return mysqli_fetch_assoc($result ?? $this->result); // returns array or null
}
```

Update all callers that use `while ($row = $this->DB->getRow())` — these still work correctly because both `null` and `false` are falsy. Only callers doing `=== false` checks need updating.

---

## #6 – `@` error suppression in `numRows()`

**File:** `Database.php` → `numRows()`  
**Risk:** Low — hides PHP warnings and makes debugging harder.

### Problem

```php
$output = @mysqli_num_rows($this->result);
```

### Fix

Check whether `$this->result` is a valid `mysqli_result` before calling:

```php
public function numRows(): int|false {
    if (!$this->db) {
        return false;
    }

    if ($this->result instanceof \mysqli_result) {
        return mysqli_num_rows($this->result);
    }

    return mysqli_affected_rows($this->db);
}
```

---

## #7 – `getResult()` fetches a full array to get `$row[0]`

**File:** `Database.php` → `getResult()`  
**Risk:** Low — minor inefficiency; also contains dead commented-out `mysql_result()` code.

### Problem

```php
$row = mysqli_fetch_array($result ? $result : $this->result);
return ($row[0]);
/*
if ($result)
    return (mysql_result ($result, 0, 0));   // dead code
...
*/
```

### Fix

Use `mysqli_fetch_row()` which returns a numerically-indexed array (same behaviour, semantically clearer), and remove the dead comment block:

```php
public function getResult($result = null): mixed {
    if (!$this->db) {
        return false;
    }

    $row = mysqli_fetch_row($result ?? $this->result);
    return $row[0] ?? null;
}
```

---

## #8 – No prepared-statement API

**File:** `Database.php`  
**Risk:** High — the entire class relies on callers pre-escaping every value. One missed escape call equals an injection.

### Problem

There is no `prepare()` / `execute()` wrapper, so every caller must manually escape and quote values.

### Fix

Add a `queryPrepared()` method that wraps `mysqli::prepare()`:

```php
/**
 * Executes a parameterized query.
 * @param string $query Query with ? placeholders.
 * @param string $types  Bind types string, e.g. 'ssi' (string, string, int).
 * @param array  $params Values to bind.
 * @return \mysqli_result|bool
 */
public function queryPrepared(string $query, string $types, array $params): \mysqli_result|bool {
    if (!$this->db) {
        $this->connect();
    }

    $stmt = $this->db->prepare($query);
    if (!$stmt) {
        throw new \RuntimeException('Prepare failed: ' . $this->db->error);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $this->result = $stmt->get_result();
    $this->messages['total_queries']++;

    return $this->result;
}
```

Gradually migrate the most sensitive `set()` and `get()` calls to use this method.

---

## #9 – SQL injection in `set()` — CRITICAL

**File:** `Modul.php` → `set()`  
**Risk:** Critical — values from `$set` are placed directly into SQL strings with no escaping.

### Problem

```php
// UPDATE path:
$sql_temp[] = $this->sql_table . '.' . $key . ' = ' . $value;

// INSERT path:
$sql = $this->sql_insert . ' (' . implode(', ', array_keys($set)) . ')
       VALUES (' . implode(', ', $set) . ');';

// IODU path:
$sql = $this->sql_insert . ' (' . implode(', ', array_keys($set)) . ')
       VALUES (' . implode(', ', $set) . ') ON DUPLICATE KEY UPDATE ...';
```

If `$value` is a string like `', 1); DROP TABLE users; --`, the query is malformed.

### Fix — Short Term (escape before insert)

Escape all values in `$set` at the top of the method before any SQL construction:

```php
public function set(array|false|null $set = null, array|int|null $ids = null, string|null $special = null): int|false {
    if (!is_array($set)) {
        return false;
    }

    // Escape all scalar values. Numeric literals and SQL expressions
    // (e.g. 'NOW()') must be passed pre-formatted with a known-safe wrapper.
    $escaped = [];
    foreach ($set as $key => $value) {
        if (is_null($value)) {
            $escaped[$key] = 'NULL';
        } elseif (is_int($value) || is_float($value)) {
            $escaped[$key] = $value; // safe numeric literal
        } else {
            $escaped[$key] = '"' . $this->DB->escape((string)$value) . '"';
        }
    }
    // Use $escaped instead of $set for all SQL construction below
    ...
}
```

> **Note:** The `escape()` helper referenced here is introduced in fix #1.

### Fix — Long Term

Migrate `set()` to use the `queryPrepared()` helper from fix #8, passing each column value as a bound parameter.

---

## #10 – SQL injection in `syncManyToMany()` — CRITICAL

**File:** `Modul.php` → `syncManyToMany()`  
**Risk:** Critical — relation values are inserted into SQL with no escaping.

### Problem

```php
$values[] = $relation_data[$column_name] ?? 'NULL';
// ...
$sql_rows[] = '(' . implode(', ', $values) . ')';
$this->DB->query('INSERT INTO ' . $config['table'] . ' (...) VALUES ' . implode(', ', $sql_rows) . ';');
```

### Fix

Apply the same pattern as fix #9 — escape every value individually:

```php
foreach ($config['columns'] as $column_name) {
    $raw = $relation_data[$column_name] ?? null;
    if (is_null($raw)) {
        $values[] = 'NULL';
    } elseif (is_int($raw) || is_float($raw)) {
        $values[] = $raw;
    } else {
        $values[] = '"' . $this->DB->escape((string)$raw) . '"';
    }
}
```

Also validate `$config['table']` and `$config['main_key']` against an allowlist of known table/column names to prevent second-order injection via configuration.

---

## #11 – `eval()` in `getTotalEval()` — CRITICAL

**File:** `Modul.php` → `getTotalEval()`  
**Risk:** Critical — `$key` originates from the caller-supplied `$values` parameter and is executed as PHP code.

### Problem

```php
eval('$in = $row' . $key . ';');
eval('$output' . $key . '+=' . $add . ';');
```

Any `$key` value like `['id']; system('rm -rf /'); $x = $row['x` would execute arbitrary code.

### Fix

Replace `eval()` with a safe nested-array accessor. The pattern `$row[key1][key2]` can be walked with `array_reduce`:

```php
private function resolveNestedKey(array $row, string $key): mixed {
    // Convert "[key1][key2]" notation to ["key1", "key2"]
    preg_match_all('/\[([^\]]+)\]/', $key, $matches);
    if (empty($matches[1])) {
        return $row[$key] ?? null;
    }

    return array_reduce($matches[1], function ($carry, $segment) {
        return is_array($carry) ? ($carry[$segment] ?? null) : null;
    }, $row);
}

private function setNestedKey(array &$output, string $key, float|int $add): void {
    preg_match_all('/\[([^\]]+)\]/', $key, $matches);
    if (empty($matches[1])) {
        $output[$key] = ($output[$key] ?? 0) + $add;
        return;
    }

    $ref = &$output;
    foreach ($matches[1] as $segment) {
        $ref[$segment] ??= 0;
        $ref = &$ref[$segment];
    }
    $ref += $add;
}
```

Then rewrite `getTotalEval()`:

```php
public function getTotalEval(array $dataset = null, array $values = null): array|false {
    if (!is_array($values) || !is_array($dataset)) {
        return false;
    }

    $output = [];
    foreach ($dataset as $row) {
        foreach ($values as $key => $function) {
            $in = $this->resolveNestedKey($row, $key);

            if ($function === 'count' && isset($in)) {
                $this->setNestedKey($output, $key, 1);
            } elseif ($function === 'sum' && isset($in)) {
                $this->setNestedKey($output, $key, (float)$in);
            }
        }
    }

    return $output;
}
```

---

## #12 – Fragile SQL parsing via string manipulation

**File:** `Modul.php` → `get()`, `getGroupTotal()`  
**Risk:** Medium — breaks silently on subqueries, aliases, or keywords inside string literals.

### Problem

The code extracts `WHERE` and `GROUP BY` clauses from a plain string `$sql_base` using `strripos` + `substr`. A `$sql_base` like:

```sql
SELECT * FROM orders WHERE status = 'group by status'
```

…would be incorrectly split.

### Fix

Refactor `$sql_base` into structured components stored separately:

```php
protected string $sql_select  = '';  // "SELECT ... FROM table ..."
protected string $sql_where   = '';  // base WHERE conditions (without the WHERE keyword)
protected string $sql_group   = '';  // GROUP BY clause (without the keyword)
protected string $sql_having  = '';  // HAVING clause (optional)
```

Then `get()` assembles them without parsing:

```php
$sql = $this->sql_select;

$conditions = array_filter(array_merge(
    $this->sql_where ? [$this->sql_where] : [],
    is_array($where) ? $where : ($where ? [$where] : [])
));

if ($conditions) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

if ($this->sql_group) {
    $sql .= ' GROUP BY ' . $this->sql_group;
}
```

This is a **breaking change** for subclasses that set `$sql_base`. Provide a migration shim that keeps `$sql_base` working while logging a deprecation notice.

---

## #13 – Dead `strripos` existence check

**File:** `Modul.php` → `get()`  
**Risk:** Low — dead code that increases maintenance burden.

### Problem

```php
if (function_exists("strripos")) {
    // main path
} elseif (strpos(strtolower($sql), 'group by')) {
    // never reached on PHP >= 5.0
}
```

### Fix

Delete the `function_exists` wrapper and the entire `elseif` branch. Keep only the body of the `if` block:

```php
$pos_group_by = strripos($sql, 'GROUP BY ');
$last_from    = strripos($sql, 'FROM ');
if ($pos_group_by !== false && $pos_group_by > $last_from) {
    $sql_group_by = substr($sql, $pos_group_by);
    $sql          = substr($sql, 0, $pos_group_by);
}
```

Note the `!== false` fix — see also issue #18.

---

## #14 – Numeric column-index ordering

**File:** `Modul.php` → `$order`, `get()`  
**Risk:** Low — fragile; breaks silently when `SELECT` columns are reordered.

### Problem

```php
protected $order = -6;  // means "ORDER BY 6 DESC"
```

Column positions in `ORDER BY` are positional, not named. Adding/removing a `SELECT` column silently changes what is sorted.

### Fix

Replace the numeric default with an explicit column name + direction pair:

```php
protected string $order = '';        // e.g. 'created_at DESC'
protected array  $orders = [];       // e.g. [['column' => 'name', 'dir' => 'ASC']]
```

Update `get()` to append `$this->order` as a literal string when it is non-empty and non-numeric:

```php
if ($order && !is_numeric($order)) {
    $sql .= ' ORDER BY ' . $order;
} elseif ($this->order && !is_numeric($this->order)) {
    $sql .= ' ORDER BY ' . $this->order;
}
```

For backward compatibility, keep the numeric path but add a `@trigger_error()` deprecation notice when a numeric value is detected.

---

## #15 – `getRandom()` loads all rows into PHP memory

**File:** `Modul.php` → `getRandom()`  
**Risk:** Medium — on large tables this fetches thousands of rows into PHP just to pick one.

### Problem

```php
$data = $this->get($where, $order, $limit, $limit_from);
// ...
$keys = array_rand($data, $count);
```

### Fix

Delegate randomness to the database engine using `ORDER BY RAND()` with a small `LIMIT`:

```php
public function getRandom(
    array|string|null $where = null,
    int $count = 1
): array|false {
    $count = max(1, (int)$count);

    $data = $this->get($where, null, $count, null);  // use RAND order

    if (!is_array($data)) {
        return false;
    }

    return $data;
}
```

Set `$sql_base` to include `ORDER BY RAND()`, or pass a literal order string. If `SQL_CALC_FOUND_ROWS` is needed, remove it for random queries to avoid an extra full-table scan.

> For very large tables, `ORDER BY RAND()` is itself slow. A more scalable approach: fetch a random offset using `SELECT FLOOR(RAND() * COUNT(*))` and then `LIMIT 1 OFFSET ?`.

---

## #16 – `$cache` and `$cache_total` are public

**File:** `Modul.php`  
**Risk:** Medium — external code can corrupt internal cache state.

### Problem

```php
public $cache;
public $cache_total;
public $cache_sql;
```

### Fix

Change to `protected` and expose read-only accessors:

```php
protected array  $cache       = [];
protected ?int   $cache_total = null;
protected string $cache_sql   = '';

public function getCacheTotal(): ?int {
    return $this->cache_total;
}

public function getLastSql(): string {
    return $this->cache_sql;
}
```

Check whether any external code (outside `Modul` subclasses) reads or writes these properties and update it to use the accessors.

---

## #17 – Constructor returns a value (PHP anti-pattern)

**File:** `Modul.php` → `__construct()`  
**Risk:** Low — `return` in a constructor is silently ignored by PHP; the guard logic never works as intended.

### Problem

```php
public function __construct(Database &$database) {
    $this->DB = &$database;
    $this->cache = array();

    if (!is_object($this->DB)) {
        return (false);   // ignored by PHP
    }
    return (true);        // ignored by PHP
}
```

### Fix

Throw an exception for the invalid-argument case; remove the pointless `return (true)`:

```php
public function __construct(Database $database) {
    $this->DB    = $database;
    $this->cache = [];
}
```

The `is_object()` check is also redundant because PHP's type system enforces the `Database` type hint. If you need to allow `null` (optional DB), use `?Database` and add a null guard in methods that use `$this->DB`.

Note: the pass-by-reference `&$database` is also unnecessary here. `Database` is an object; PHP passes objects by handle automatically. Removing `&` avoids subtle aliasing bugs.

---

## #18 – `$last_from` used without being defined

**File:** `Modul.php` → `get()`  
**Risk:** Medium — causes a PHP notice / warning; the `WHERE` extraction may use an uninitialized variable if the code ever enters the `elseif` branch.

### Problem

```php
if (function_exists("strripos")) {
    $pos_group_by = strripos($sql, 'GROUP BY ');
    $last_from = strripos($sql, 'FROM ');          // defined here
    if ($pos_group_by > $last_from) { ... }
}

// ...

if (function_exists("strripos")) {
    $pos_where = strripos($sql, 'WHERE ');
    if ($pos_where > $last_from) { ... }           // $last_from may be undefined here
                                                    // if first block was not entered
} elseif (...) { ... }
```

`$last_from` is set inside the first `if` block, but the second `if` block assumes it is always defined.

### Fix

Ensure `$last_from` is always assigned before the second block (and after the fix from #13, this simplifies to one straight-line path):

```php
$last_from    = strripos($sql, 'FROM ');
$pos_group_by = strripos($sql, 'GROUP BY ');

if ($pos_group_by !== false && $last_from !== false && $pos_group_by > $last_from) {
    $sql_group_by = substr($sql, $pos_group_by);
    $sql          = substr($sql, 0, $pos_group_by);
}

$pos_where = strripos($sql, 'WHERE ');
if ($pos_where !== false && $last_from !== false && $pos_where > $last_from) {
    $sql_where = substr($sql, $pos_where + 6);
    $sql       = substr($sql, 0, $pos_where);
}
```

---

## #19 – `sanitize()` mixes validation and escaping

**File:** `Modul.php` → `sanitize()`  
**Risk:** Medium — `'email'` type only validates (does not escape), while `'text'` only escapes (does not validate). A caller who receives a truthy result assumes the value is safe to insert.

### Problem

```php
case 'email':
    return (filter_var($value, FILTER_VALIDATE_EMAIL)); // returns value or false — NOT escaped
case 'text':
    return ($value ? mysqli_real_escape_string($this->DB->db, (string)$value) : ""); // escaped, not validated
```

An email address like `' OR 1=1--@example.com` passes validation and is returned unescaped.

### Fix

Separate concerns: validate first, then escape the result for the intended context (SQL vs. HTML). For SQL insertion, always escape regardless of type:

```php
public function sanitize(mixed $value, string $type = 'text', bool $required = false, mixed $extra_data = null): mixed {
    if ($required && ($value === null || $value === '')) {
        return false;
    }

    switch ($type) {
        case 'float':
            $clean = filter_var($value, FILTER_VALIDATE_FLOAT);
            return ($clean !== false) ? $clean : false;

        case 'int':
            $clean = filter_var($value, FILTER_VALIDATE_INT);
            return ($clean !== false) ? (int)$clean : false;

        case 'email':
            $clean = filter_var($value, FILTER_VALIDATE_EMAIL);
            // Return escaped value so it is safe for SQL insertion too
            return ($clean !== false) ? $this->DB->escape($clean) : false;

        case 'inarray':
        case 'in_array':
            return (is_array($extra_data) && in_array($value, $extra_data, true)) ? $value : false;

        case 'text':
        default:
            if ($required && !$value) {
                return false;
            }
            return $this->DB->escape((string)$value);
    }
}
```

`$this->DB->escape()` is the helper introduced in fix #1.

---

## #20 – `createFulltextSubquery()` inserts user input raw into LIKE

**File:** `Modul.php` → `createFulltextSubquery()`  
**Risk:** High — LIKE special characters `%` and `_` in search terms cause unexpected wildcard matching; incomplete regex means injection-adjacent characters can still pass through.

### Problem

```php
$input = mb_strtolower(preg_replace('/[^a-ž0-9 ]+/i', ' ', $input));
// ...
$query[] = $word_query . ' LIKE "%' . $word . '%"';
```

The regex strips most special characters but does not escape `%` and `_`, which are special inside a SQL `LIKE` expression. A search for `100%` matches every row.

### Fix

After the regex sanitization, additionally escape LIKE wildcards:

```php
foreach ($words as $word) {
    if ($word === '') {
        continue;
    }
    // Escape SQL LIKE special characters
    $word_escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $word);
    // Escape for SQL string context
    $word_safe    = $this->DB->escape($word_escaped);

    $query[] = $word_query . ' LIKE "%' . $word_safe . '%"';
}
```

Also add a minimum word-length guard to prevent single-character searches from matching everything:

```php
$words = array_filter(explode(' ', $input), fn(string $w) => mb_strlen($w) >= 2);
```

---

## Recommended Fix Order

| Step | Issues | Reason |
|------|--------|--------|
| 1 | #11 (`eval`) | Code execution risk — fix immediately |
| 2 | #9, #10 (SQL injection in `set`, M:N) | Data integrity and injection |
| 3 | #2, #4 (connect error, charset) | Connection reliability and encoding safety |
| 4 | #1 (public credentials) | Encapsulation / credential exposure |
| 5 | #20 (LIKE injection) | User-input handling |
| 6 | #8 (prepared statements) | Long-term injection prevention |
| 7 | #19 (sanitize semantics) | Clarify contract for callers |
| 8 | #12, #18 (SQL parsing, undefined var) | Correctness |
| 9 | #15 (getRandom memory) | Performance |
| 10 | #3, #5, #6, #7, #13, #14, #16, #17 | Code quality |
