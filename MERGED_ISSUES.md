# JmLib - Merged Code Analysis

Generated: 2026-05-27  
Sources merged:
- CODE_ISSUES.md (20 findings)
- issues.md (38 findings)

This document is a deduplicated, prioritized merge of both analyses.

---

## Summary

- Shared findings: 17 core topics (same issue, different framing/detail).
- Unique to issues.md: 20 additional findings (mainly performance, API design, and correctness edge-cases).
- Unique emphasis in CODE_ISSUES.md: 1 architectural item on fragile SQL-string parsing as a standalone migration concern.

Recommended execution order:
1. Critical security risks (injection and eval).
2. Connection safety and SQL execution reliability.
3. Correctness bugs that can cause warnings or silent wrong behavior.
4. Performance improvements.
5. API and clean-code refactors.

---

## Priority A - Critical

### A1. SQL injection in set()
- Severity: Critical
- Source mapping:
  - CODE_ISSUES.md: #9
  - issues.md: S1
- Merge decision:
  - Keep as top fix.
  - Introduce prepared statement path and identifier whitelist for columns.

### A2. SQL injection in syncManyToMany()
- Severity: Critical
- Source mapping:
  - CODE_ISSUES.md: #10
  - issues.md: S2
- Merge decision:
  - Validate table/key/column identifiers against a whitelist.
  - Parameterize inserted values.
  - Wrap delete + insert in transaction.

### A3. eval() in getTotalEval() enables code execution
- Severity: Critical
- Source mapping:
  - CODE_ISSUES.md: #11
  - issues.md: S4
- Merge decision:
  - Replace eval() with safe nested-path resolver and explicit aggregation logic.

### A4. SQL injection via raw where/order fragments in get()
- Severity: Critical
- Source mapping:
  - issues.md: S3
- Merge decision:
  - Add builder/criteria API with bound parameters.
  - Keep raw SQL only as explicitly internal trusted escape hatch.

---

## Priority B - High

### B1. Connection error handling is weak/silent
- Severity: High
- Source mapping:
  - CODE_ISSUES.md: #2
  - issues.md: S8 (related)
- Merge decision:
  - Enable strict mysqli error reporting and fail fast with exceptions.

### B2. Charset/TLS/strict SQL mode not enforced
- Severity: High
- Source mapping:
  - CODE_ISSUES.md: #4
  - issues.md: S7
- Merge decision:
  - set_charset('utf8mb4') immediately after connect.
  - Add strict SQL mode session config.
  - Add optional TLS/port/socket configuration.

### B3. Public DB credentials and public mutable internals
- Severity: High
- Source mapping:
  - CODE_ISSUES.md: #1, #16
  - issues.md: S6, C5
- Merge decision:
  - Move credentials and DB handle to private fields.
  - Convert public cache internals to protected/private with accessors.

### B4. createFulltextSubquery() LIKE handling is unsafe
- Severity: High
- Source mapping:
  - CODE_ISSUES.md: #20
  - issues.md: S5
- Merge decision:
  - Escape LIKE wildcards and backslash, or parameterize LIKE inputs.
  - Add minimum token length and sanitize consistently.

### B5. sanitize() contract is unsafe/ambiguous
- Severity: High
- Source mapping:
  - CODE_ISSUES.md: #19
  - issues.md: S9, S10, B6
- Merge decision:
  - Separate validation from SQL escaping.
  - Prefer typed validators and prepared statements over generic escaping.

### B6. No prepared-statement API at Database layer
- Severity: High
- Source mapping:
  - CODE_ISSUES.md: #8
  - issues.md: implied by S1-S3/S10/C6
- Merge decision:
  - Add queryPrepared/prepare API and migrate risky paths first.

---

## Priority C - Medium

### C1. Fragile SQL parsing by substring operations
- Severity: Medium
- Source mapping:
  - CODE_ISSUES.md: #12
  - issues.md: partially related to B5/C4-style concerns
- Merge decision:
  - Move to structured query parts (select/where/group/having) to avoid parsing SQL text.

### C2. last_from may be undefined
- Severity: Medium
- Source mapping:
  - CODE_ISSUES.md: #18
  - issues.md: B1
- Merge decision:
  - Initialize variables in straight-line logic and guard false returns from strripos.

### C3. Reconnect resets profiling counters
- Severity: Medium
- Source mapping:
  - CODE_ISSUES.md: #3
  - issues.md: B2
- Merge decision:
  - Initialize counters only in constructor, not in connect().

### C4. getRandom() can be expensive and memory-heavy
- Severity: Medium
- Source mapping:
  - CODE_ISSUES.md: #15
  - issues.md: P2
- Merge decision:
  - Avoid loading full data set in PHP.
  - Use DB-side random strategy appropriate to table size.

### C5. SQL_CALC_FOUND_ROWS deprecated/slow
- Severity: Medium
- Source mapping:
  - issues.md: P1
- Merge decision:
  - Replace with separate optimized COUNT(*) query.

### C6. Cache invalidation and cache-read edge cases
- Severity: Medium
- Source mapping:
  - issues.md: P8, P9, B9
- Merge decision:
  - Fix set() invalidation logic.
  - Use isset checks before cache index reads.

### C7. Query log growth can leak memory
- Severity: Medium
- Source mapping:
  - issues.md: P7
- Merge decision:
  - Cap stored query history or make it optional in debug mode.

---

## Priority D - Low

### D1. getRow() end-of-result semantics (null vs false)
- Severity: Low
- Source mapping:
  - CODE_ISSUES.md: #5
  - issues.md: B3
- Merge decision:
  - Align return contract and callers.

### D2. numRows() uses @ suppression
- Severity: Low
- Source mapping:
  - CODE_ISSUES.md: #6
  - issues.md: B4
- Merge decision:
  - Remove @ and branch by result type safely.

### D3. getResult() fetch style inefficiency
- Severity: Low
- Source mapping:
  - CODE_ISSUES.md: #7
  - issues.md: P3
- Merge decision:
  - Use fetch_row for first column access.

### D4. Dead strripos compatibility branch
- Severity: Low
- Source mapping:
  - CODE_ISSUES.md: #13
  - issues.md: P4
- Merge decision:
  - Remove legacy branch.

### D5. Constructor anti-pattern and unnecessary pass-by-reference
- Severity: Low
- Source mapping:
  - CODE_ISSUES.md: #17
  - issues.md: B10, C1
- Merge decision:
  - Remove return values from constructor and reference passing.

### D6. Numeric order index API is opaque
- Severity: Low
- Source mapping:
  - CODE_ISSUES.md: #14
  - issues.md: C4
- Merge decision:
  - Replace positional ordering with column-name whitelist + direction.

### D7. Additional low-priority cleanups from issues.md
- Severity: Low
- Source mapping:
  - issues.md: P5, P6, B5, B7, B8, C2, C3, C6, C7, C8
- Merge decision:
  - Batch into a refactor pass after security/correctness fixes.

---

## Crosswalk (One-Line Mapping)

- #1 <-> S6/C5
- #2 <-> S8 (related)
- #3 <-> B2
- #4 <-> S7
- #5 <-> B3
- #6 <-> B4
- #7 <-> P3
- #8 <-> (enabler for S1/S2/S3/S10)
- #9 <-> S1
- #10 <-> S2
- #11 <-> S4
- #12 <-> (partially covered by broader design issues)
- #13 <-> P4
- #14 <-> C4
- #15 <-> P2
- #16 <-> C5
- #17 <-> B10/C1
- #18 <-> B1
- #19 <-> S9/S10/B6
- #20 <-> S5

---

## First Implementation Slice (Practical)

1. Remove eval() in getTotalEval().
2. Add Database prepared API and escape helper.
3. Migrate set() and syncManyToMany() to prepared statements and identifier whitelist.
4. Enforce utf8mb4 + strict mysqli error behavior on connect.
5. Add tests for injection regressions and M:N sync transaction behavior.

This slice addresses the highest-risk vulnerabilities while creating foundations for remaining fixes.
