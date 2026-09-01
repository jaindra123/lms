# 32. Risk of OS Command Injection — **False positive**

## Finding

| Field | Report |
|-------|--------|
| Title | Risk of OS Command Injection |
| Impact claimed | HIGH / CVSS 8.8 / CWE-78 |
| URL | `https://staginglms.eci.gov.in/grade/report/grader/index.php?id=…&sifirst=A&silast=` |
| OWASP | A03:2025 – Injection |

> Claim: attacker-controlled input is passed to an OS command without neutralization.

## PoC (auditor)

Fuzzed `id` with payloads such as:

- `;echo '<script>alert(1)</script>'`
- `; echo "<?php include($_GET['page']); ?>" > rfi.php` (URL-encoded)

Observed responses (depending on payload / missing `id`):

- Moodle **“You are trying to use an invalid course ID”**, or
- Moodle **“A required parameter (id) was missing”** with “More information” → public docs  
  `https://docs.moodle.org/405/en/error/moodle/missingparam`

Neither response is command output, shell errors, or file creation (`rfi.php`). The docs link is **Moodle’s generic help for a missing required parameter**, not evidence of OS command execution.

### Report recommendation already met

> “Validate and sanitize all input using allow-list validation.”

Moodle’s `required_param('id', PARAM_INT)` is allow-list style: only integer values are accepted; everything else is rejected or coerced before any business logic. CWE-78 does not apply when input never reaches an OS command.

## Why this is not OS command injection

### 1. `id` is forced to an integer

```php
// grade/report/grader/index.php
$courseid = required_param('id', PARAM_INT);
```

Moodle `PARAM_INT` / `clean_param()` strips non-numeric content. A payload like  
`4; echo "…">rfi.php` becomes integer **`4`** (or `0` / invalid), never a shell string.

### 2. Course load is a DB lookup, not a shell

```php
if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    throw new \moodle_exception('invalidcourseid');
}
```

That matches the PoC response (“invalid course ID”).

### 3. No OS exec in this report path

There is no `exec`, `shell_exec`, `system`, `passthru`, `proc_open`, or backtick shell use in `grade/report/grader/` for request parameters.

### 4. Other query params are typed

| Param | Type |
|-------|------|
| `page`, `perpage`, … | `PARAM_INT` |
| `sifirst`, `silast` | `PARAM_NOTAGS` (stored in session for filter; not passed to shell) |

## Verdict for auditors

| Claim | Result |
|-------|--------|
| OS command injection via `id` | **Not present** — false positive / scanner heuristic |
| Successful RCE / file write (`rfi.php`) | **Not demonstrated** — error page only |
| Correct handling | Invalid `id` → `invalidcourseid` exception |

**Dispute finding #32** for this LMS. Retest evidence: after payload, confirm no new PHP file under the web root and no shell side effects.

## Optional hardening (already satisfied)

- Keep staging/production `$CFG->debugdisplay = 0` so exceptions do not dump SQL/stack ([verbose-error-messages.md](verbose-error-messages.md)).
- Hide `Server` / `X-Powered-By` at Apache ([version-disclosure.md](version-disclosure.md)).

## Related

Parameter tampering / IDOR findings are separate: [web-parameter-tampering.md](web-parameter-tampering.md).
