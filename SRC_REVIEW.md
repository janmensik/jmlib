# Source Review — Remaining Issues

Date: 2026-09-26. Original scope: all five classes in [src](src/), supporting [tests](tests/), [composer.json](composer.json), and [phpunit.xml](phpunit.xml).

Items marked **✅ done** have been implemented and are retained here for reference only.
All other items below are **open**.

---

## Prioritized Findings

### 1. High: raw SQL and executable expressions cross unsafe boundaries

Sources: [Modul::set](src/Modul.php#L499), [Modul.php](src/Modul.php), method `getId()`, and [Modul::getTotalEval](src/Modul.php#L457).

- `set()` interpolates column names, values and array IDs; `getId()` interpolates IDs; `syncManyToMany()` interpolates relation values. The array type does not constrain individual IDs to integers. Request-derived values reaching these paths can alter SQL. Raw `$where` is also an explicitly unsafe SQL-fragment API, not a safe filter API.
- `getTotalEval()` evaluates supplied key expressions and interpolates dataset values into another evaluated expression. Untrusted keys or values can become PHP code.

Recommendation: add a parameterized execution API in `Database`, whitelist identifiers, and migrate ordinary writes and ID lookups to bound values. Keep any intentional raw-SQL interface explicitly named and documented. Replace `eval()` with validated nested key paths and array traversal.

Verification: quote-containing strings, nulls, invalid array IDs, literal expression-like input, and nested aggregation tests. Check stored values against a real database, not just generated SQL strings.

---

### 2. High: relationship synchronization can lose data and ignores additional IDs

Source: [Modul.php](src/Modul.php#L499), methods `set()` and `syncManyToMany()`.

The relationship helper deletes existing relations before inserting replacements, without a transaction. An insert failure leaves the deletion committed. It uses only `reset($main_ids)`, so a bulk update synchronizes relations for just the first record. Query outcomes are ignored and the helper returns true; the caller also ignores its return value.

Recommendation: make the parent write and all relationship updates atomic, propagate failures, and explicitly support every supplied ID or reject bulk synchronization. Then consider differential updates, backed by a unique relationship key, to reduce writes.

Verification: fail the replacement insert and assert the previous relations remain; update two parent IDs; test empty relations and concurrent updates using a disposable database.

---

### 3. High: recursive deletion does not propagate child failures

Source: [JmLib::rmdirr](src/JmLib.php#L136).

✅ `is_link()` check added — directory symlinks are now removed as links instead of traversed.

**Still open:**
- Directory-open failure (`dir($dirname)`) is unchecked; if the handle is false the subsequent `read()` call on it will error.
- Child `unlink()` and recursive `rmdirr()` return values are discarded; the final `rmdir()` is called even when children were not fully removed, producing a misleading `true` return.

Recommendation: check the `dir()` result and return `false` immediately on failure. Collect child failure results and propagate them so the caller knows whether the full tree was deleted.

Verification: unreadable subdirectory, unremovable file, and a directory containing only a symlink to an external target.

---

### 4. High: generated HTML trusts the URL and raw attributes

Source: [UrlParameters::getLink](src/UrlParameters.php#L117).

The URL is interpolated into a quoted HTML attribute without escaping. A quote in an untrusted basename can break out of `href`; an unsafe URI scheme remains unsafe even after HTML escaping. Link content and `$options` are also inserted as raw HTML.

Recommendation: escape the final URL for an HTML attribute (`htmlspecialchars()`), enforce an application-appropriate scheme policy, and offer a text-label/structured-attributes API. Preserve explicitly trusted HTML through a separately documented compatibility path.

Verification: quotes and ampersands in the basename, unsafe schemes, normal relative URLs, text labels, and deliberately trusted markup.

---

### 5. High: database result contracts cause PHP type errors

Sources: [Database::numRows](src/Database.php#L112), [Database::getRow](src/Database.php#L131), [Database::getAllRows](src/Database.php#L158), method `freeResult()`.

- Successful writes return `true`, not `mysqli_result`. `numRows()`, fetch helpers and `freeResult()` can pass this boolean to native result functions. `@` does not suppress a `TypeError`.
- `getAllRows(): ?array` returns `false` when disconnected, violating its declared return type.
- Connection failure handling depends on mysqli reporting mode. PHP 8.1+ defaults to exception reporting; the `connect_error` branch does not establish a stable failure contract.

Recommendation: distinguish result sets from command outcomes with `instanceof mysqli_result`; consistently define empty, failed and disconnected returns. Establish an explicit connection/error policy.

Verification: real SELECT with zero rows, INSERT/UPDATE, SQL error, connection failure, repeated freeing, and disconnected helper calls.

---

### 6. High: the form-to-write path escapes twice and cannot save null

Sources: [Modul::mapFromPost](src/Modul.php#L74), [Modul::setter](src/Modul.php#L99), method `sanitize()`.

`mapFromPost()` escapes text through `sanitize()`, then `setter()` escapes it again. Quote/backslash-containing values can be corrupted on storage. Both depend on a connection that the lazy database wrapper may not yet have opened. `setter()` checks `isset()` before testing for null, making its SQL NULL branch unreachable. `validate()` is not called by the save path.

Recommendation: keep domain data unescaped, validate it independently, and bind it once at the database boundary. Use `array_key_exists()` where present-null differs from absent.

Verification: round-trip apostrophes, backslashes, Unicode and null on a fresh instance.

---

### 7. High: write completion leaves invalid state and return values

Source: [Modul::set](src/Modul.php#L499).

- Unsetting typed `$cache` and `$cache_total` leaves them uninitialized; reading `getRowsCount()` after a write can throw instead of returning null.
- A multi-ID update returns the ID array despite the `int|false` return declaration, causing a `TypeError` after the database work has happened.
- `foreach (array_keys($set) as $key => $value)` compares the numeric index with configured synchronization field names, so intended sync hooks can be skipped.
- Query success is inferred from affected rows rather than checked directly.

Recommendation: reset caches to `[]` and `null`; define a write-result contract for inserts and bulk updates; iterate field names directly; check the write result before proceeding.

Verification: write then read caches, update multiple IDs, trigger each sync hook, write unchanged values, and update only relationship data.

---

### 8. Medium: interval calculations depend on rollover and wall-clock state

Source: [JmLib::getInterval](src/JmLib.php#L283).

✅ `$output` initialized to `null` before the switch — unknown names and `all` now return `null` instead of crashing. `$return_only` uses `array_key_exists()` and is null-safe.

**Still open:**
- `nextmonth` calculated from January 31 rolls into March (`strtotime('+1 month', Jan 31)` = Mar 3). Anchor on the first of the month with `DateTimeImmutable` instead.
- `lastmonth` calls `date('m')` (today's actual month) rather than deriving the month from the supplied `$now` timestamp only, so it behaves differently depending on when the code runs.
- `thisyear` ends at the current month's end (year-to-date), which differs from full-year behavior. Decide which semantic is correct before changing it.

Recommendation: use `DateTimeImmutable` anchored to the first of the relevant month for all month-boundary arithmetic; remove the `date('m')` wall-clock read in `lastmonth`.

Verification: `nextmonth` from January 31 and March 31; `lastmonth` with an explicit past `$now`; `thisyear` boundary decision documented in a test comment.

---

### 9. Medium: URL parsing, key normalization and cache invalidation disagree

Source: [UrlParameters.php](src/UrlParameters.php#L20).

- Parsing stores encoded array keys, while `setParameter()` stores decoded array keys. Parsing `arr%5B%5D=a` then adding `arr[]=b` creates separate entries.
- `explode('?', $url)` without a limit drops parameters when a value contains another question mark. Fragments are not separated from the query.
- `getParameters()` returns a mutable reference. After `getUrl()` caches its output, mutation through that reference does not invalidate the cached URL.
- `fromCurrent()` still reads a missing `PHP_SELF` when `SCRIPT_NAME` is present.

Recommendation: normalize keys once and serialize them consistently; separate URI components with `parse_url()`; replace mutable-reference access with mutations that invalidate cache. Remove the unused `seenArrays` property.

Verification: encoded brackets, encoded spaces, duplicate values, fragments, embedded `?`, mutation after serialization, and missing server variables.

---

### 10. Medium: identifier lookup and random sampling use the wrong bounds

Sources: [Modul::getRandom](src/Modul.php#L349), methods `getId()`, `findId()` and `findRandomId()`.

`getId()` fetches missing IDs through the default paginated `get()`, so requests larger than the configured limit can silently omit records. Related find helpers still hardcode `id` instead of `id_format`.

`getRandom()` checks `cache_total`, not the number of fetched rows. A requested sample of 30 from a 20-row page can reach `array_rand()` and throw when the total is 100. Sampling is from a page, not uniformly from all matching records. Remove unnecessary `srand()` because it changes shared RNG state.

Recommendation: make ID batches independent of pagination; validate sample size against the actual dataset; explicitly distinguish page sampling from database-wide sampling.

Verification: more than 20 IDs, mixed cache hits, custom ID fields, empty samples, invalid counts, and a total larger than the loaded page.

---

### 11. Medium: temporary SQL state and ad hoc SQL parsing are fragile

Sources: [Modul::get](src/Modul.php#L133), [Modul::getCustom](src/Modul.php#L277), method `createFulltextSubquery()`.

`getCustom()` restores `sql_base` only on success; exceptions leave subsequent queries using the temporary SQL. The documented scalar `$columns` path in fulltext construction attempts `$columns[] = $columns` on a string, which PHP rejects.

Recommendation: use `try/finally` for temporary state and normalize scalar columns to an array at the top of the method.

Verification: a thrown query followed by a normal query, scalar columns, subqueries, punctuation-only search input.

---

### 12. Medium: AppData conflates absent values and request/session ownership

Source: [AppData.php](src/AppData.php#L29).

`getData('0')` returns all data because it tests truthiness instead of null. `registerFilters()` overwrites a page's stored filters with null, and `initiateFilters()` refuses to store an empty query-string value, so clearing a filter can leave an older value to reappear later. `hibernateMessages()` closes the application's session as a side effect. The singleton persists state across tests and long-lived worker requests unless explicitly reset.

Recommendation: use explicit key-presence rules; separate message and filter persistence; make session ownership explicit; provide a reset operation.

Verification: key `0`, absent versus empty filter, register after load, filters without messages, two simulated requests, and session writes after hibernation.

---

### 13. Medium: HTTP and authentication helper policies are incomplete

Sources: [JmLib::getUrl](src/JmLib.php#L176), [JmLib::createPassword](src/JmLib.php#L32).

✅ `filemtimeRemote()` — connect timeout (5 s) and total timeout (10 s) added.

**Still open:**
- `getUrl()` trusts `HTTP_HOST` unconditionally. An attacker-controlled host value can produce a malicious link if the output is used in a security-sensitive context (e.g., password-reset emails). Callers should supply a verified base URL instead.
- `createPassword()` uses a secure RNG but its default length of 5 hex characters contains only 20 bits of entropy — too little for an authentication secret. Consider a clearly named `createToken(int $bytes = 32)` API with an adequate default.

Recommendation: document that `getUrl()` is not safe for security-sensitive link generation without a trusted host; introduce a token generator separate from the password helper.

Verification: missing request context, untrusted hosts, and token-length/format contracts.

---

## Optimization Proposals

These are candidates, not measured speedup claims. Prioritize them after the correctness fixes.

| Priority | Source | Recommendation | Evidence to collect |
|---|---|---|---|
| High | [Modul::get](src/Modul.php#L133), [Database.php](src/Database.php) | Replace deprecated MySQL `SQL_CALC_FOUND_ROWS`/`FOUND_ROWS()` with optional explicit count queries; handle grouped results correctly. Reset totals between query modes. | Compare execution plans, rows examined, and latency on representative filters; do not assume two queries are always faster. |
| High | [Database::query](src/Database.php#L69) | Make SQL tracing opt-in and bounded; redact sensitive values. Use `hrtime(true)` for elapsed durations and retain numeric timing values. | Memory growth and logging cost in a long-running worker. Full SQL retention currently grows per query. |
| Medium | [Database::getResult](src/Database.php#L181), [Database::getAllRows](src/Database.php#L158) | Fetch numeric-only rows for scalar results; offer an iterator API for large result sets. Decide buffered versus unbuffered queries explicitly. | Peak memory and throughput for large reads. |
| Medium | [JmLib.php](src/JmLib.php), method `leastSquaresFittingLogarithmic()` | Accumulate regression sums in one pass and compute fitted values in a second, avoiding several temporary arrays. Validate finite numeric data before arithmetic. | Peak memory and numerical agreement for large series, constants and extreme values. |
| Medium | [JmLib.php](src/JmLib.php), method `movingAverage()` | Stream chunk accumulation rather than materializing `array_chunk()` for large inputs. | Peak memory and output equivalence for large datasets. |
| Low | [JmLib::utf2ascii](src/JmLib.php#L14), [Modul::get](src/Modul.php#L133) | Consider reusing the transliterator only if profiling shows repeated setup cost. | Benchmark realistic call volumes. |

For text search, `CONCAT_WS(CAST(...)) LIKE '%term%'` generally prevents ordinary index seeks. Consider a database fulltext index only after specifying tokenization, language, short-word and substring requirements.

---

## Refactor Boundaries

1. Keep `JmLib` as a compatibility facade. Pure date, array and numerical methods need not each become a separate class.
2. Put connection initialization, `utf8mb4` charset configuration, optional TLS configuration, parameter binding and transaction handling behind `Database`. Avoid publicly exposing credentials in new APIs.
3. Keep raw domain values in `Modul`; separate query construction, persistence and relationship synchronization. Replace integer "sanitization" with validation where rejection is required.
4. Make URL and request/session responsibilities explicit. Prefer immutable URL values or controlled mutations to an externally mutable cached representation.
5. Preserve camelCase public method names and PSR-4 layout. Introduce deprecations and migration notes for changed return types and numeric semantics.

---

## Test and Tooling Gaps

- [tests/Database.Test.php](tests/Database.Test.php#L15) and [tests/Modul.Test.php](tests/Modul.Test.php#L11) define mocks in the production namespace. When loaded alongside [tests/Integration.Test.php](tests/Integration.Test.php#L1), they intercept supposedly real query and escaping calls. Run integration tests in a separate process that does not load those mocks.
- The integration file returns before registering tests when `DB_HOST` is absent, so a green run need not demonstrate database coverage. Make the integration gate explicit. Use only a disposable database with dedicated credentials.
- Existing cURL mocks do not assert option values (timeout, protocol, redirect settings). Capture/assert those options to make unit tests deterministic and to verify that new options (timeouts added in finding #13) are actually passed.
- [composer.json](composer.json) declares PHP `>=8.0` but Pest 4 requires PHP `>=8.3` for development. Declare required extensions (`intl`, `curl`, `mysqli`, `mbstring`) explicitly. Missing `Transliterator` is not handled when its factory returns null.
- [phpunit.xml](phpunit.xml) references an 11.5 schema while Pest 4 uses PHPUnit 12. Verify configuration migration and make warning, deprecation and coverage policies explicit.
- Add PHPStan or Psalm incrementally, starting with return-type violations and uninitialized properties. Establish a small baseline only for genuinely deferred issues.

---

## Suggested Delivery Order

1. Fix result contracts, transaction handling and write-state defects (findings 5, 6, 7) in small, separately reviewable changes.
2. Add parameterized queries and whitelist identifiers (finding 1); wrap relationship sync in a transaction (finding 2).
3. Fix URL/date/filter edge cases with explicit compatibility decisions (findings 8, 9).
4. Address `getCustom` try/finally and scalar-columns normalization (finding 11) — small, isolated change.
5. Fix `AppData` truthiness and session ownership (finding 12).
6. Introduce a `createToken()` API and document `getUrl()` host-trust caveat (finding 13).
7. Profile representative workloads; implement database and memory optimizations with before/after measurements.
8. Extract abstractions justified by the changes above, keeping public compatibility wrappers where practical.