# Class videos (Webex recordings)

Plugin: `local_iiidem_classvideos`

## Best process (v1)

| Choice | Implementation |
|--------|----------------|
| Access | **Per video:** Public to enrolled class **or** Request → teacher/admin approve |
| Storage | **Prefer URL** (Webex / SharePoint / OneDrive). Optional small file upload for short clips |
| Scope | Teacher/admin manage page + student list + watch page |

Later (not in v1): auto-pull recordings via Webex OAuth API; chunked/resumable LMS upload.

## Large recordings (50–200+ MB)

**Do not upload the full file into Moodle.** Browser → PHP → moodledata is slow and often times out.

| Approach | When |
|----------|------|
| **Paste Webex / SharePoint URL** (recommended) | Full class recordings |
| Upload MP4 in the form | Short clips only (~under 50 MB) |
| Compress then upload | Only if you must host in Moodle and can keep size small |

Teacher flow for Webex:

1. Open the recording in Webex → copy share / playback link  
2. Class videos → paste into **Webex / recording URL**  
3. Leave file upload empty → Save  

Students still use Request / Approve / Watch; Watch opens or embeds the linked recording.

## Teacher / admin

1. Dashboard → **Class videos** (or `/local/iiidem_classvideos/manage.php`)
2. **Add class video**: course, title, session date, access type, URL and/or file
3. **Pending access requests**: Approve / Reject
4. Video list: Watch, Hide/Show

## Student

1. Dashboard → **Recordings** (or `/local/iiidem_classvideos/index.php`)
2. Public videos → **Watch** immediately
3. Request-required → **Request access** → wait for approval → **Watch**

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Ensure teacher roles have `local/iiidem_classvideos:manage` and students have `…:view` + `…:request` (defaults from plugin archetypes).

## Caps

| Capability | Roles (default) |
|------------|-----------------|
| `local/iiidem_classvideos:manage` | teacher, editingteacher, manager |
| `local/iiidem_classvideos:view` | student + teachers |
| `local/iiidem_classvideos:request` | student |
