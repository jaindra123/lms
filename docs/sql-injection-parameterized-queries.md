# Blind SQL Injection audit — parameterized queries

## Finding

| Field | Value |
|-------|--------|
| Title | Blind SQL Injection |
| Impact | **HIGH** |
| URLs cited | `/lib/ajax/service.php`, `/local/iiidem_support/tickets.php` |
| CWE | CWE-89 |
| OWASP | A03:2025 – Injection |
| CVSS (reported) | 9.8 |

> Use parameterized queries (prepared statements) for all database interactions.

## Verdict for the cited URLs

| URL | SQL injection? | Notes |
|-----|----------------|-------|
| `/local/iiidem_support/tickets.php` | **No** | Requires login. Loads tickets with `$DB->get_records(..., ['userid' => $userid])` — bound params only. No request parameters enter SQL. FAQ search (AJAX) is **in-PHP** string scoring, not SQL. |
| `/local/iiidem_support/ticket_new.php` (PoC Instance 2) | **No** | Create uses `$DB->insert_record()` — bound params. Intruder `subject='or select *` was **stored as the ticket title** (303 → ticket created), not executed as SQL. List page then showed those titles as text. |
| `/lib/ajax/service.php` | **No (core)** | Moodle AJAX/webservice front controller. Calls registered `external_api` methods with typed `PARAM_*` validation. Database access goes through Moodle `$DB` placeholders. Automated scanners commonly flag this endpoint as a false positive because it accepts JSON RPC-style payloads. |

The reported **PR:N** (unauthenticated) score does not match these endpoints: `tickets.php` / `ticket_new.php` call `require_login()`; AJAX service calls normally require an authenticated session + sesskey (or a valid token for WS).

### Instance 2 PoC (`subject='or select *`) — explained

1. POST `/local/iiidem_support/ticket_new.php` with SQLi-looking `subject`.
2. Response **303** to `ticket.php?id=N` = ticket **created successfully**.
3. `tickets.php` lists the same string as the subject column.

That is **stored text**, not query injection. True SQLi would typically error, change result sets, or cause timing — not neatly create a ticket whose title equals the payload.

**Hardening applied (`local_iiidem_support` ≥ `2026061824`):**

- Allow-list / probe rejection on subject & message (`manager::sanitize_ticket_field`).
- URL-decodes Intruder-style `%7bbase%7d` / `%20or%20` payloads before checks.
- Form validation rejects payloads like `'or 1=1`, `select`, `sleep`, `{base}`, `alert(1)`, LDAP `*((mail=*))`, etc.
- Subjects must be plain text (letters/digits + limited punctuation).
- List/detail output uses `format_string()` for safe display.

### Local retest (DDEV)

1. Log in → open  
   `https://iiidem-certification.ddev.site/local/iiidem_support/ticket_new.php`
2. Subject + Message =  
   `%7bbase%7d,(select%2afrom(select(sleep(20)))a)`  
   → **must show** `invalidtickettext` / not create a ticket (no 20s sleep).
3. Same for `{base} or 7=7#` and `alert(1)`.
4. Normal subject `Cannot access quiz` → ticket created.
5. Admin list must **not** gain new rows for the probe subjects.

```bash
ddev exec php admin/cli/upgrade.php --non-interactive
ddev exec php admin/cli/purge_caches.php
```

Staging PoC that “ticket raised successfully” with the sleep payload was **stored text** (or old plugin without sanitizer), **not** blind SQLi. True SQLi would delay the DB or change query results — creating a ticket whose title equals the payload does not.

## Status (custom IIIDEM code)

Custom IIIDEM code uses Moodle’s `$DB` API with **named/positional placeholders** (prepared statements). There is no raw `mysqli_query` / string-concatenated user input in SQL.

## Code change already applied (hardening)

**File:** `theme/iiidem2/classes/form/register_form.php` — `phone_exists()`

- **Before:** national phone length was interpolated into `RIGHT(..., {$nlen})`.
- **After:** ends-with check uses `$DB->sql_like()` with bound `:phoneend` (plus `sql_like_escape`).

Deploy that file to staging and purge caches if not already live:

```bash
php admin/cli/purge_caches.php
```

## How Moodle already parameterizes

Safe patterns used across the project:

```php
// Condition arrays (bound internally)
$DB->record_exists('user', ['email' => $email, 'deleted' => 0]);

// Named placeholders
$DB->get_records_sql(
    "SELECT * FROM {theme_iiidem2_chatbot} WHERE LOWER(email) = :email",
    ['email' => $email]
);

// IN() lists
list($insql, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
$DB->get_records_select('course', "id $insql", $params);
```

### Support tickets (`tickets.php`) — relevant code path

```php
require_login();
$tickets = manager::get_user_tickets($USER->id);
// → $DB->get_records('local_iiidem_support_ticket', ['userid' => $userid], ...);
```

### Support FAQ AJAX (`api.php?action=searchfaq`)

```php
require_login();
require_sesskey();
$query = trim(clean_param(required_param('q', PARAM_TEXT), PARAM_TEXT));
// → manager::search_faqs() scores FAQs in PHP memory (no SQL with $query).
```

## Evidence for auditors

1. Custom plugins use `$DB->*` with placeholders / condition arrays only.
2. Cited `tickets.php` has **no** user-controlled SQL fragments.
3. Cited `service.php` is Moodle core external API (typed params + `$DB`).
4. Phone duplicate check no longer interpolates any value into the SQL string.
5. Public AJAX validates with `required_param` / `PARAM_*` and binds values.

## Scanner PoC analysis (`/lib/ajax/service.php`)

Auditors posted JSON like:

```json
[{"index":0,"methodname":"args[0]->","args":{"lang":"en"}}]
```

and received `debuginfo` containing:

```text
SELECT * FROM {external_functions} WHERE name = ?
[array ( 0 => 'args[0]->', )]
```

**This is not SQL injection.** The `?` placeholder shows a prepared statement; the attacker string is a **bound parameter** used only to look up a webservice function name. An unknown name throws `dml_missing_record_exception`.

**What actually leaked:** Moodle developer debug was on (`debugging('', DEBUG_DEVELOPER)`), so `external_api::call_external_function()` kept `debuginfo` + `backtrace` in the AJAX JSON. With debug off, those fields are stripped (core behaviour).

Also visible in the PoC: `X-Powered-By: PHP/8.2.31` — see `docs/version-disclosure.md`.

### Fix

1. Staging/production: `$CFG->debug = 0` and `$CFG->debugdisplay = 0` (already forced in `config.php`).
2. Never leave Site admin → Development → Debugging on **DEVELOPER** on user-facing hosts.
3. `MOODLE_FORCE_DEBUG=1` now uses `DEBUG_NORMAL` only (not DEVELOPER), so AJAX never returns SQL dumps.
4. Redeploy `config.php`, purge caches, re-test the PoC — expect error **without** `debuginfo` / SQL / paths.

```bash
curl -s 'https://staginglms.eci.gov.in/lib/ajax/service.php?sesskey=VALID' \
  -H 'Content-Type: application/json' \
  --data '[{"index":0,"methodname":"args[0]->","args":{"lang":"en"}}]'
# Expect: generic exception JSON, no "SELECT", no file paths, no backtrace
```

## Note on false positives

Automated scanners often flag:

- Moodle’s `$insql` fragments from `get_in_or_equal()` (placeholders, not raw data)
- `/lib/ajax/service.php` (generic JSON RPC front door)
- Time-based “blind” probes that confuse login redirects / rate limits / generic 500s with SQLi
- **Verbose `debuginfo` that happens to include a parameterized SQL string** (this PoC)

Ask the vendor for a PoC that changes DB state or returns other users’ rows. Bound `WHERE name = ?` with a missing-record error is **not** CWE-89.
