# Bucket (MakeHaven)

Lightweight, member-friendly file drop:
- `/bucket` shows recent uploads (public, no login to view).
- `/bucket/upload` lets logged-in members upload multiple files.
- `/bucket/my` shows a member's own uploads.
- `/bucket/d/{fid}` streams a file download and marks it as downloaded.
- Auto-expiration by cron based on TTL; optional delete-on-first-download.

## Settings
Admin → Configuration → Media → **Bucket settings**

- **Time-to-live (hours)** — files older than this are deleted by cron.
- **Delete immediately after download** — if enabled, files are removed by cron after first download.
- **Use blocklist** — allow everything except blocked extensions. If off, an allowlist is used.
- **Blocked extensions** — space-separated, e.g. `php js exe sh bat`.
- **Allowed extensions (allowlist)** — only used if blocklist is OFF.
- **Permissive extensions** — used internally to override Drupal’s default strict list so common formats (e.g. zip, svg) upload cleanly in blocklist mode.
- **List page limit** — cap rows on `/bucket` and `/bucket/my`.
- **Description** — text shown on `/bucket` and `/bucket/upload`; tokens: `[ttl_hours]`, `[delete_on_download]`.

## Permissions
- Upload bucket files
- View bucket list
- Delete own bucket file
- Delete any bucket file

## Notes
Drupal core enforces a conservative default allowlist on uploads. In **blocklist** mode this module sets an explicit **Permissive extensions** allowlist to override core’s default. It is still filtered by your **Blocked extensions**.
