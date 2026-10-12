# Restore a backup from a local machine

Send `POST /api/admin/database-backups/restore-upload` using an authenticated
Super Admin account and `multipart/form-data`:

| Field | Value |
| --- | --- |
| `backup` | A `.sql.gz` backup file, such as one downloaded from the existing backup API |
| `password` | The current administrator's password |
| `confirmation` | Exactly `RESTORE DATABASE` |

Example (replace the URL, token, password, and file path):

```sh
curl --request POST "https://your-server/api/admin/database-backups/restore-upload" \
  --header "Authorization: Bearer YOUR_TOKEN" \
  --header "Accept: application/json" \
  --form "backup=@/path/to/backup.sql.gz" \
  --form "password=YOUR_ADMIN_PASSWORD" \
  --form "confirmation=RESTORE DATABASE"
```

Let the HTTP client set the multipart Content-Type and boundary.

A successful request returns HTTP 202 with `success`, `message`, and `data`
(the registered backup). This acknowledges the restore request; it does not
confirm successful restoration. Use the existing backup detail/list endpoints
to inspect status and errors.

The API validates file size, the `.sql.gz` filename suffix, and gzip magic bytes.
It stores the file with a generated name, calculates a SHA-256 checksum, and
registers it as a manual backup with `metadata.source = upload` and the original
filename in metadata. Password verification happens before storage. This format
check does not guarantee that the dump contains valid SQL or a compatible schema;
use a trusted MySQL dump of this application database.

The existing restore job creates a safety backup, enables maintenance mode,
verifies the checksum, restores the dump, and marks reconciliation as required.
Execution follows the configured queue connection: an asynchronous connection
requires a running worker; `sync` executes during the request. The uploaded file
is retained as a manual backup and can be downloaded or deleted through the
existing endpoints. Existing server-backup restore requests continue to work.

`DB_BACKUP_UPLOAD_MAX_KB` configures the upload limit in KiB (default 102400,
100 MiB). Configure PHP `upload_max_filesize`, `post_max_size` (allow multipart
overhead), and any web-server request size/time limits accordingly. After changing
environment configuration, refresh any cached Laravel configuration. No schema
migration is needed.
