# Source Review and Refactor Recommendations

Date: 2026-09-26. Scope: all five classes in [src](src/), with supporting inspection of [tests](tests/), [composer.json](composer.json), and [phpunit.xml](phpunit.xml).

This is a source-inspection review, not an executed test or benchmark report. PHP is unavailable on this PC. Findings below describe identifiable code paths; security impact depends on how applications supply input. No implementation changes were made. Earlier issue documents are historical context, not proof that a finding remains open.

## Prioritized Findings

### 1. High: raw SQL and executable expressions cross unsafe boundaries

Sources: [Modul::set](src/Modul.php#L499), [Modul.php](src/Modul.php), method `getId()`, and [Modul::getTotalEval](src/Modul.php#L457).

- `set()` interpolates column names, values and array IDs; `getId()` interpolates IDs; `syncManyToMany()` interpolates relation values. The array type does not constrain individual IDs to integers. Request-derived values reaching these paths can alter SQL. Raw `$where` is also an explicitly unsafe SQL-fragment API, not a safe filter API. The numeric-only order handling is not the same issue.
- `getTotalEval()` evaluates supplied key expressions and interpolates dataset values into another evaluated expression. Untrusted keys or values can become PHP code.
- Recommendation: add a parameterized execution API in `Database`, whitelist identifiers, and migrate ordinary writes and ID lookups to bound values. Keep any intentional raw-SQL interface explicitly named and documented. Replace `eval()` with validated nested key paths and array traversal.
- Verification: quote-containing strings, nulls, invalid array IDs, literal expression-like input, and nested aggregation tests. Check stored values against a real database, not just generated SQL strings.

### 2. High: relationship synchronization can lose data and ignores additional IDs

Source: [Modul.php](src/Modul.php#L499), methods `set()` and `syncManyToMany()`.

The relationship helper deletes existing relations before inserting replacements, without a transaction. An insert failure leaves the deletion committed. It uses only `reset($main_ids)`, so a bulk update synchronizes relations for just the first record. Query outcomes are ignored and the helper returns true; the caller also ignores its return value.

Recommendation: first make the parent write and all relationship updates atomic, propagate failures, and explicitly support every supplied ID or reject bulk synchronization. Then consider differential updates, backed by a unique relationship key, to reduce writes. Define concurrency and transaction ownership before introducing nested transactions.

Verification: fail the replacement insert and assert the previous relations remain; update two parent IDs; test empty relations and concurrent updates using a disposable database.

### 3. High: recursive deletion follows directory links

Source: [JmLib::rmdirr](src/JmLib.php#L136).

`is_dir()` follows directory symlinks, so recursion can delete files outside the requested tree. Directory-open failure is also unchecked, and child deletion failures are not propagated reliably.

Recommendation: detect links before directories and remove the link rather than traversing it. Define a Windows junction/reparse-point policy separately; do not assume ordinary symlink checks cover every reparse point. Check open/delete results and return failure consistently.

Verification: use temporary directories with an external target and confirm target contents survive. Add unreadable-directory and failed-child-deletion cases where the platform permits them.

### 4. High: generated HTML trusts the URL and raw attributes

Source: [UrlParameters::getLink](src/UrlParameters.php#L117).

The URL is interpolated into a quoted HTML attribute without escaping. A quote in an untrusted basename can break out of `href`; an unsafe URI scheme remains unsafe even after HTML escaping. Link content and `$options` are also inserted as raw HTML, consistent with the legacy API but dangerous for ordinary text input.

Recommendation: escape the final URL for an HTML attribute, enforce an application-appropriate scheme policy, and offer a text-label/structured-attributes API. Preserve explicitly trusted HTML through a separately documented compatibility path.

Verification: quotes and ampersands in the basename, unsafe schemes, normal relative URLs, text labels, and deliberately trusted markup.

### 5. High: database result contracts cause PHP type errors

Sources: [Database::numRows](src/Database.php#L112), [Database::getRow](src/Database.php#L131), [Database::getAllRows](src/Database.php#L158), [Database.php](src/Database.php), method `freeResult()`.

- Successful writes return `true`, not `mysqli_result`. `numRows()`, fetch helpers and `freeResult()` can pass this boolean to native result functions. `@` does not suppress a `TypeError`.
- `getAllRows(): ?array` returns `false` when disconnected, violating its declared return type.
- Connection failure handling depends on mysqli reporting mode. PHP 8.1+ defaults to exception reporting; the current `connect_error` branch does not establish a stable failure contract. Under non-throwing reporting, a failed connection object is still assigned to `$db` and tested only for truthiness.

Recommendation: distinguish result sets from command outcomes with `instanceof mysqli_result`; consistently define empty, failed and disconnected returns. Establish an explicit connection/error policy and keep failed connections out of usable state. Avoid silently changing the application's global mysqli reporting policy.

Verification: real SELECT with zero rows, INSERT/UPDATE, SQL error, connection failure, repeated freeing, and disconnected helper calls. Existing permissive namespace mocks do not enforce native parameter types.

### 6. High: the form-to-write path escapes twice and cannot save null

Sources: [Modul::mapFromPost](src/Modul.php#L74), [Modul::setter](src/Modul.php#L99), [Modul.php](src/Modul.php), method `sanitize()`.

`mapFromPost()` escapes text through `sanitize()`, then `setter()` escapes it again. Quote/backslash-containing values can be corrupted on storage. Both depend on a connection that the lazy database wrapper may not yet have opened. `setter()` checks `isset()` before testing for null, making its SQL NULL branch unreachable. `validate()` is not called by the save path.

Recommendation: keep domain data unescaped, validate it independently, and bind it once at the database boundary. Use `array_key_exists()` where present-null differs from absent. Define whether validation is the caller's responsibility or part of a new validated-save method.

Verification: round-trip apostrophes, backslashes, Unicode and null on a fresh instance. Exercise the complete `mapFromPost()` to save workflow.

### 7. High: write completion leaves invalid state and return values

Source: [Modul::set](src/Modul.php#L499).

- Unsetting typed `$cache` and `$cache_total` leaves them uninitialized; reading `getRowsCount()` after a write can throw instead of returning null.
- A multi-ID update returns the ID array despite the `int|false` return declaration, causing a `TypeError` after the database work has happened.
- `foreach (array_keys($set) as $key => $value)` compares the numeric index with configured synchronization field names, so intended sync hooks can be skipped.
- Query success is inferred from affected rows rather than checked directly. A relation-only update also needs an explicit path to avoid generating an empty SET clause.

Recommendation: reset caches to `[]` and `null`; define a write-result contract for inserts and bulk updates; iterate field names directly; check the write result before proceeding. Keep cache invalidation after successful transaction completion.

Verification: write then read caches, update multiple IDs, trigger each sync hook, write unchanged values, and update only relationship data.

### 8. Medium: CNB failure responses overwrite useful cache data

Source: [JmLib.php](src/JmLib.php), methods `kurzyCnb()` and `kurzyCnbFetch()`.

A nonempty HTML error response parses to `[]`, which is treated as success and overwrites the cache. HTTP status is not checked. The rate regex accepts numeric prefixes instead of validating the entire rate field. Cache writes are non-atomic and errors are suppressed; fresh cached arrays have no record-shape validation.

Recommendation: separate fetch, parsing and cache policy. Require a successful HTTP status and valid records, parse pipe-separated fields with `str_getcsv()`, and validate complete numeric fields. Preserve the last valid cache on fetch/parse failure. Use atomic replacement with a tested Windows-compatible strategy and bounded cache freshness. `allowed_classes => false` is useful protection already present, not schema validation.

Verification: stale cache plus HTML/500 response, malformed rates, empty body, partial cache file, unwritable cache, and concurrent readers/writers. Retain deterministic fixture-based unit tests.

### 9. Medium: filled calendars skip the first days of the next month

Source: [JmLib::createCalendar](src/JmLib.php#L490).

Trailing padding uses weekday minus one as an offset from the final date. November 2023 ends on Thursday; its Friday cell becomes December 4 instead of December 1. The existing test named "fills leading/trailing days" checks only leading days.

Recommendation: advance a date cursor by one calendar day per trailing cell, or calculate the offset relative to the first padding weekday. Keep noon timestamps and false placeholders intact. Review the artificial 2038 year limit separately from this fix.

Verification: November 2023 must end with December 1, 2, 3. Cover every possible final weekday, leap February, and a DST transition in an explicit timezone.

### 10. Medium: interval calculations depend on rollover and wall-clock state

Source: [JmLib::getInterval](src/JmLib.php#L283).

Unknown names and `all` leave `$output` undefined; an unknown `$return_only` reads an undefined key. `nextmonth` calculated from January 31 rolls into March. `lastmonth` consults today's month rather than only the supplied timestamp. `thisyear` ends at the current month's end, which an existing test explicitly expects, but differs from the removed legacy helper's full-year behavior.

Recommendation: anchor month operations on the first of the month using `DateTimeImmutable`, initialize unknown results, and validate selectors. Specify week boundaries and full-year versus year-to-date behavior before changing established results. Use explicit null checks if timestamp zero must be valid.

Verification: January 31, March 31, leap years, Monday/Sunday references, unknown names/selectors, timestamp zero, and Europe/Prague DST transitions. Tests must not depend on the date they run.

### 11. Medium: URL parsing, key normalization and cache invalidation disagree

Source: [UrlParameters.php](src/UrlParameters.php#L20).

- Parsing stores encoded array keys, while `setParameter()` stores decoded array keys. Parsing `arr%5B%5D=a` then adding `arr[]=b` creates separate entries; lookup and deletion also disagree.
- `explode('?', $url)` without a limit drops parameters when a value contains another question mark. Fragments are not separated from the query.
- `getParameters()` returns a mutable reference. After `getUrl()` caches its output, mutation through that reference does not invalidate the cached URL.
- `fromCurrent()` still reads a missing `PHP_SELF` when `SCRIPT_NAME` is present.

Recommendation: normalize keys once and serialize them consistently; separate URI components; replace mutable-reference access with mutations that invalidate cache, or remove this small serialization cache. Do not blindly replace parsing with `parse_str()`: its key normalization and duplicate-key behavior may break the current contract. Remove the unused `seenArrays` property.

Verification: encoded brackets, encoded spaces, duplicate values, fragments, embedded `?`, mutation after serialization, and missing server variables.

### 12. Medium: identifier lookup and random sampling use the wrong bounds

Sources: [Modul::getRandom](src/Modul.php#L349), [Modul.php](src/Modul.php), methods `getId()`, `findId()` and `findRandomId()`.

`getId()` fetches missing IDs through the default paginated `get()`, so requests larger than the configured limit can silently omit records. Mixed cache hits and misses are merged in database/cache order rather than requested order. Related find helpers still hardcode `id` instead of `id_format`.

`getRandom()` checks `cache_total`, not the number of fetched rows. A requested sample of 30 from a 20-row page can reach `array_rand()` and throw when the total is 100. Without a populated total it can return the entire page instead of the requested sample. Sampling is from a page, not uniformly from all matching records.

Recommendation: make ID batches independent of pagination and define ordering/duplicate semantics. Validate sample size against the actual dataset; explicitly distinguish page sampling from database-wide sampling. Remove unnecessary `srand()` because it changes shared RNG state.

Verification: more than 20 IDs, mixed cache hits, custom ID fields, empty samples, invalid counts, and a total larger than the loaded page.

### 13. Medium: temporary SQL state and ad hoc SQL parsing are fragile

Sources: [Modul::get](src/Modul.php#L133), [Modul::getCustom](src/Modul.php#L277), [Modul.php](src/Modul.php), method `createFulltextSubquery()`.

`getCustom()` restores `sql_base` only on success; exceptions leave subsequent queries using the temporary SQL. Splitting SQL by substring searches is unsafe for nested queries, quoted keywords and more complex clauses. The documented scalar `$columns` path in fulltext construction attempts `$columns[] = $columns` on a string, which PHP rejects.

Recommendation: immediately use `try/finally` for temporary state and normalize scalar columns to an array. Longer term, keep SELECT/FROM/WHERE/GROUP/ORDER as explicit components or adopt a maintained query builder if needed by consumers. Avoid writing a larger homegrown SQL parser. Bind LIKE values and allowlist column identifiers.

Verification: a thrown query followed by a normal query, scalar columns, subqueries, quoted keywords, punctuation-only search input and Unicode words.

### 14. Medium: basic helper input contracts need explicit boundaries

Sources: [JmLib::pagination](src/JmLib.php#L215), [JmLib::strripos](src/JmLib.php#L114), [JmLib.php](src/JmLib.php), method `oneFromArray()`.

- `pagination(0, 10)` divides by zero. Very small link budgets cannot satisfy the assumed first/last/adjacent layout.
- The custom `strripos()` does not implement native offset semantics. For example, searching `ababa` for `a` with offset 1 returns 3 even though that position contains `b`.
- `oneFromArray()` rejects the valid column key `0` and accepts unsupported key types through `mixed`.

Recommendation: validate pagination inputs; delegate case-insensitive string searches to the native functions with a compatibility test matrix; restrict column keys to supported types and distinguish null/empty from zero. Keep key-preserving extraction separate from native `array_column()` because missing-value behavior differs.

Verification: zero/negative sizes, minimum link budgets, positive/negative string offsets, missing needles, column zero, missing keys and object properties.

### 15. Medium: AppData conflates absent values and request/session ownership

Source: [AppData.php](src/AppData.php#L29).

`getData('0')` returns all data because it tests truthiness instead of null. `registerFilters()` overwrites a page's stored filters with null, and `initiateFilters()` refuses to store an empty query-string value, so clearing a filter can leave an older value to reappear later. Loading filters is coupled to successful message loading. `hibernateMessages()` closes the application's session as a side effect. The singleton persists state across tests and long-lived worker requests unless explicitly reset.

Recommendation: use explicit key-presence rules and test the register/load/initiate lifecycle. Separate message and filter persistence, make session ownership explicit, and provide request-scoped state or a reset operation. Preserve the singleton facade initially if existing applications depend on it.

Verification: key `0`, absent versus empty filter, register after load, filters without messages, two simulated requests, and session writes after hibernation.

### 16. Medium: HTTP and authentication helper policies are incomplete

Sources: [JmLib::filemtimeRemote](src/JmLib.php#L405), [JmLib::getUrl](src/JmLib.php#L176), [JmLib::createPassword](src/JmLib.php#L32).

`filemtimeRemote()` has no explicit connect/total timeout or cache expiry and does not require a successful HTTP status before accepting a timestamp. Its DNS validation/pinning and disabled redirects are useful existing protections and should be preserved during refactoring. `getUrl()` trusts `HTTP_HOST` and assumes request variables exist; consumers must not use an attacker-controlled host to build security-sensitive links. `createPassword()` uses a secure RNG but its five-hex-character default contains only 20 bits of entropy, too little for an authentication secret.

Recommendation: bound network work and cache lifetime; configure a trusted base URL for sensitive link generation; introduce a clearly named token API with an adequate default length, expiry and application-level attempt limits. Do not describe the current password generator as suitable for authentication merely because its RNG is secure.

Verification: timeouts, error statuses, cache expiry, missing request context, untrusted hosts, and token-length/format contracts. DNS behavior needs a controlled integration check in addition to unit mocks.

## Optimization Proposals

These are candidates, not measured speedup claims. Prioritize them after the correctness fixes.

| Priority | Source | Recommendation | Evidence to collect |
|---|---|---|---|
| High | [Modul::get](src/Modul.php#L133), [Database.php](src/Database.php) | Replace deprecated MySQL `SQL_CALC_FOUND_ROWS`/`FOUND_ROWS()` with optional explicit count queries; handle grouped results correctly. Reset totals between query modes. | Compare execution plans, rows examined, and latency on representative filters; do not assume two queries are always faster. |
| High | [Database::query](src/Database.php#L69) | Make SQL tracing opt-in and bounded; redact sensitive values. Use `hrtime(true)` for elapsed durations and retain numeric timing values. | Memory growth and logging cost in a long-running worker. Full SQL retention currently grows per query. |
| Medium | [Database::getResult](src/Database.php#L181), [Database::getAllRows](src/Database.php#L158) | Fetch numeric-only rows for scalar results; offer an iterator API for large result sets. Decide buffered versus unbuffered queries explicitly. | Peak memory and throughput for large reads; an iterator alone does not remove mysqli's buffered result memory. |
| Medium | [JmLib.php](src/JmLib.php), method `leastSquaresFittingLogarithmic()` | Accumulate regression sums in one pass and compute fitted values in a second, avoiding several temporary arrays. Validate finite numeric data before arithmetic. | Peak memory and numerical agreement for large series, constants and extreme values. Preserve the current invalid-input contract or version its change. |
| Medium | [JmLib.php](src/JmLib.php), method `movingAverage()` | Keep the existing linear-time rolling sum. Stream chunk accumulation rather than materializing `array_chunk()` for large inputs; define numeric output types consistently. | Peak memory, precision drift and output equivalence. Do not replace the rolling sum with repeated window slicing. |
| Low | [JmLib::utf2ascii](src/JmLib.php#L14), [Modul::get](src/Modul.php#L133) | Consider reusing the transliterator and precomputing translation mappings only if profiling shows repeated setup cost. | Benchmark realistic call volumes; public mutable mappings require a reliable invalidation policy. |

For text search, `CONCAT_WS(CAST(...)) LIKE '%term%'` generally prevents ordinary index seeks. Consider a database fulltext index only after specifying tokenization, language, short-word and substring requirements. It is not a behavior-equivalent drop-in optimization.

## Refactor Boundaries

1. Keep `JmLib` as a compatibility facade. Extract the stateful CNB HTTP/cache behavior first; pure date, array and numerical methods need not each become a separate class.
2. Put connection initialization, `utf8mb4` charset configuration, optional TLS configuration, parameter binding and transaction handling behind `Database`. Avoid publicly exposing credentials in new APIs; stage visibility changes because consumers may use the current public fields.
3. Keep raw domain values in `Modul`; separate query construction, persistence and relationship synchronization. Add precise return types and array-shape documentation after behavior is established. Replace integer "sanitization" with validation where rejection is required: stripping characters can silently change an identifier.
4. Make URL and request/session responsibilities explicit. Prefer immutable URL values or controlled mutations to an externally mutable cached representation.
5. Preserve camelCase public method names and PSR-4 layout. Avoid a package-wide naming/style rewrite in the same change as behavioral fixes. Introduce deprecations and migration notes for changed return types, year intervals, raw HTML handling and numeric semantics.

## Test and Tooling Gaps

- [tests/JmLib.Test.php](tests/JmLib.Test.php#L420): the trailing moving-average sum starts as a float, but strict `toBe()` expects mostly integers. Conversely, exact integer division in the chunk path can return integers while that test expects floats. Set a deliberate output-type policy and align both implementation and assertions; do not simply weaken every assertion.
- [tests/Database.Test.php](tests/Database.Test.php#L15) and [tests/Modul.Test.php](tests/Modul.Test.php#L11) define mocks in the production namespace. When loaded alongside [tests/Integration.Test.php](tests/Integration.Test.php#L1), they can intercept supposedly real query and escaping calls. Run integration tests in a separate process that does not load those mocks, then replace global function mocking with injected collaborators where useful.
- The integration file returns before registering tests when `DB_HOST` is absent, so a green run need not demonstrate database coverage. Make the integration gate explicit. It creates, truncates and drops `test_users`; use only a disposable database with dedicated credentials. A `.env` file alone is not loaded by the shown test code.
- Existing cURL mocks do not assert option values, and remote timestamp tests still perform DNS resolution. Capture/assert timeout, protocol, pinning and redirect options, and isolate DNS to make unit tests deterministic.
- [composer.json](composer.json) declares PHP >=8.0 but Pest 4 requires PHP >=8.3 for development. This is a tooling distinction, not by itself proof that runtime support must be raised. Declare required extensions (`intl`, `curl`, `mysqli`, `mbstring`) or document them as optional features with explicit guards. Missing `Transliterator` is not handled by checking whether its factory returned null.
- [phpunit.xml](phpunit.xml) references an 11.5 schema while Pest 4 uses PHPUnit 12. Verify configuration migration with the installed version and make warning, deprecation and coverage policies explicit. Use Pest to run Pest tests, not direct PHPUnit as an assumed equivalent.
- Add PHPStan or Psalm incrementally, starting with return-type violations and uninitialized properties. Establish a small baseline only for genuinely deferred issues; do not silence the findings above wholesale.

## Suggested Delivery Order

1. Add regression tests for the security/data-loss paths and establish isolated database integration tests.
2. Fix result contracts, transaction handling and write-state defects in small, separately reviewable changes.
3. Correct the migrated calendar/CNB helpers and numeric test expectations before treating the migration as verified.
4. Fix URL/date/filter edge cases with explicit compatibility decisions.
5. Profile representative workloads; implement database and memory optimizations with before/after measurements.
6. Extract only the abstractions justified by those changes, keeping public compatibility wrappers where practical.

On a PHP 8.3+ development machine, start with `composer install`, `composer check-platform-reqs`, and `php vendor/bin/pest tests/JmLib.Test.php`. Then run the non-integration suite and separately configured disposable-database integration suite. Test the advertised minimum runtime independently using a compatible harness. Neither test execution nor runtime compatibility has been verified in this review.