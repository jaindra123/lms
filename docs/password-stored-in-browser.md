# Password stored in browser

## Finding

Chrome “Save your password?” on `/admin/tool/mfa/auth.php` after login, with username and the real password.

`autocomplete="username"` + `autocomplete="current-password"` tells Chrome this is a login form. After the POST, Chrome offers to store the credentials on the MFA page.

## Fix (`theme_iiidem2` ≥ `2024101084`)

| Control | Behaviour |
|---------|-----------|
| Login fields | `autocomplete="off"` (not `username` / `current-password`) |
| Login form | `autocomplete="off"` + password-manager ignore flags |
| After encrypt | Visible password is cleared before submit so the save dialog has no plaintext |
| MFA OTP | `autocomplete="one-time-code"` — not treated as a password |
| Change password | Still `autocomplete="new-password"` (create/reset only) |

Chrome can still show a save UI on some versions; it must not receive the real password from the form.

## Retest

1. Log in → MFA page: “Save password” should not list the real password (or should not appear).
2. View source of login: password `autocomplete="off"`, not `current-password`.
3. OTP field is `one-time-code`.

## Deploy

Upgrade theme, purge caches, hard-refresh the login page.
