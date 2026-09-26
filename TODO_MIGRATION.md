# `/todo` Folder Migration & Test Finish-Up Instructions

**Date:** 2026-09-26
**Context:** This machine has no local PHP/Composer runtime, so the changes below were made
by reading/writing source files directly and could **not** be executed or verified with
`pest`/`phpunit`. Please run the verification steps in this document on a machine with PHP
installed before merging.

## What was migrated

Every legacy file in [todo/](todo/) has now been accounted for, either by porting it into
[src/JmLib.php](src/JmLib.php) (or [src/UrlParameters.php](src/UrlParameters.php)), or by
confirming it's already superseded by existing code. Nothing in `todo/` needs to be included
verbatim anymore.

| Legacy file | Status | New location |
|---|---|---|
| `class.url_parameters.php` | Already fully ported (modern OOP rewrite) | [src/UrlParameters.php](src/UrlParameters.php) |
| `function.array_column.php` | **Not needed** — polyfill for PHP < 5.5; `composer.json` requires PHP >= 8.0, so the native `array_column()` is always available | – |
| `function.createcalendar.php` | Ported | `JmLib::createCalendar()` |
| `function.datetimeboundary.php` | Ported | `JmLib::datetimeBoundary()` |
| `function.doubleimplode.php` | Ported | `JmLib::doubleImplode()` |
| `function.filemtime_remote.php` | Already ported previously | `JmLib::filemtimeRemote()` |
| `function.getdir.php` | Ported | `JmLib::getDir()` |
| `function.kurzy_cnb.php` | Ported (modernized: cURL + SSRF-safe HTTPS-only fetch, `unserialize` locked to `allowed_classes: false`) | `JmLib::kurzyCnb()` (private helper `kurzyCnbFetch()`) |
| `function.leastSquaresFittingLogarithmic.php` | Ported (removed deprecated/removed `create_function()`, uses closures) | `JmLib::leastSquaresFittingLogarithmic()` |
| `function.movingAverage.php` | Ported **with a behavior fix** — see note below | `JmLib::movingAverage()` |
| `function.oneFromArray.php` | Ported (avoids "undefined array key" warnings) | `JmLib::oneFromArray()` |
| `function.text2timeinterval.php` | Already superseded | `JmLib::getInterval()` (already existed, already tested) |

### ⚠️ Behavior change worth a manual review: `movingAverage()`

The legacy `movingAverage()` had a bug: for `$samecount = true` its sliding-window loop read
array indices past the end of the input (`$nidata[$i + $subsetsize - 1]`), which is undefined
behavior/warnings under PHP 8. It also never implemented the documented `$samecount = false`
branch at all (the parameter was ignored).

The new `JmLib::movingAverage()`:
- `$sameCount = true` (default): returns a **trailing** simple moving average with the same
  element count as the input. The window shrinks near the start (e.g. element 0 is just
  itself, element 1 is the average of elements 0-1, etc.) instead of reading out-of-bounds data.
- `$sameCount = false`: splits the input into non-overlapping chunks of `$subsetSize` and
  averages each chunk (≈ `count($data) / $subsetSize` elements), which matches the original
  docblock's stated (but never implemented) intent.

If any existing caller depended on the old out-of-bounds/undefined behavior, double-check call
sites after upgrading. There are currently no callers in this repository.

### `kurzyCnb()` notes

- Fetches `https://www.cnb.cz/.../denni_kurz.txt` over HTTPS via cURL (HTTP-only URLs, redirects,
  and other protocols are disallowed, mirroring the SSRF hardening already used in
  `filemtimeRemote()`).
- Caches the parsed array (serialized) to the `$cacheFile` path for `$cacheDuration` seconds
  (default 1 hour), same as the legacy function.
- The parsing regex was changed from a fragile `[a-ž]` byte-range character class (which assumed
  a Latin-2/Windows-1250 encoding) to a generic `[^|]+` column matcher with the `u` (UTF-8) flag,
  since the CNB file/country names may contain non-ASCII characters.
- Uses `unserialize($data, ['allowed_classes' => false])` instead of bare `unserialize()` to
  avoid PHP object-injection risk when reading the cache file.

## Files changed

- [src/JmLib.php](src/JmLib.php) — added `createCalendar()`, `datetimeBoundary()`, `doubleImplode()`,
  `getDir()`, `kurzyCnb()` (+ private `kurzyCnbFetch()`), `leastSquaresFittingLogarithmic()`,
  `movingAverage()`, `oneFromArray()`.
- [tests/JmLib.Test.php](tests/JmLib.Test.php) — added Pest tests for all of the above, reusing
  the existing namespaced `curl_*` mocks already in that file for `filemtimeRemote()` so
  `kurzyCnb()` tests don't hit the network.

No changes were needed in [src/UrlParameters.php](src/UrlParameters.php) or its tests.

## How to finish verification (on a machine with PHP)

1. **Install PHP 8.x and Composer** if not already available.
   - Windows: install via [php.net/downloads](https://www.php.net/downloads) or `winget install PHP.PHP`,
     and [getcomposer.org](https://getcomposer.org/download/).
   - Ensure the `curl`, `mbstring`, and `intl` PHP extensions are enabled (`intl` is required by
     `JmLib::utf2ascii()`, which was already in use before this change).
2. From the repository root, install dependencies (vendor/ is already checked in, but refresh it
   to be safe):
   ```powershell
   composer install
   ```
3. Run the full test suite with Pest:
   ```powershell
   vendor/bin/pest
   ```
   or with PHPUnit directly:
   ```powershell
   vendor/bin/phpunit
   ```
4. Confirm there are **no warnings/deprecations**, since [phpunit.xml](phpunit.xml) has
   `failOnWarning="true"` — any stray "undefined array key"/deprecation notice will fail the run.
5. If everything passes, run static checks you normally use (if any) and open a PR as usual.
6. Optional cleanup once you're confident the ported code is equivalent: delete the now-redundant
   files under [todo/](todo/) (`git rm -r todo`). This wasn't done automatically since it's a
   destructive/irreversible-in-place change best done after you've verified the tests pass.

## Quick manual sanity checks (if you want to eyeball behavior beyond the automated tests)

```php
require 'vendor/autoload.php';
use Janmensik\Jmlib\JmLib;

var_dump(JmLib::createCalendar(11, 2023, false, 'day'));
var_dump(JmLib::datetimeBoundary('month', time(), true));
var_dump(JmLib::doubleImplode(',', ';', [['a', 'b'], ['c', 'd']])); // "a,b;c,d"
var_dump(JmLib::getDir(__DIR__));
var_dump(JmLib::movingAverage([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], 3));
var_dump(JmLib::oneFromArray([['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']], 'name'));
// kurzyCnb() hits the real network — only call it manually, it's mocked in the test suite.
var_dump(JmLib::kurzyCnb(sys_get_temp_dir() . '/kurzy_cnb.txt'));
```
