# Generic Website Deployment Update Flow

## Purpose

This document explains a generic Git-based website update flow.

It can be used for:

- Static HTML websites
- PHP-supported static websites
- Landing pages
- Business websites
- Portfolio websites
- Catalog websites
- Admin UI prototypes

---

## Main Flow

```txt
Developer updates website
      ↓
Changes pushed/synced to Git repository
      ↓
Git webhook calls server update URL
      ↓
PHP update script verifies the request
      ↓
Script downloads latest approved branch
      ↓
Script extracts files to temporary folder
      ↓
Script validates required website files
      ↓
Script creates backup of current live website
      ↓
Script copies new files to live website folder
      ↓
Script writes deployment log
      ↓
Update UI shows success or failure
```

---

## Security Requirements

Never allow public requests to control:

```txt
Repository URL
Branch name
Server destination path
Shell commands
Delete paths
Backup paths
```

These must be fixed in the server configuration.

---

## Recommended Webhook URL

```txt
https://yourdomain.com/deploy/update.php
```

---

## GitHub Webhook Settings

```txt
Payload URL: https://yourdomain.com/deploy/update.php
Content type: application/json
Secret: your strong deployment secret
Events: push only
```

The updater should verify:

```txt
X-Hub-Signature-256
```

---

## Generic Token Header

For non-GitHub webhook systems, use:

```txt
X-Deploy-Token: your-secret-token
```

HMAC is preferred where available.

---

## Update UI Requirements

The update UI should be beautiful and easy to understand.

Required sections:

```txt
Hero status card
Last deployment status
Last success time
Last failure time
Repository display name
Branch name
Backup status
Recent logs
Manual update button, only if enabled
Security notes
```

Do not show:

```txt
Webhook secret
Private token
Full server paths
Sensitive request payloads
```

---

## Validation Before Updating

The script should check that the extracted repository has required files.

For a generic static website:

```txt
index.html
assets/
css/
```

Optional project-specific checks:

```txt
js/
pages/
README.md
```

---

## Backup Strategy

Before replacing files:

```txt
deploy/backups/backup-YYYYMMDD-HHMMSS/
```

Do not commit backups to Git.

---

## Rollback Strategy

Basic rollback:

1. Open hosting file manager or SSH.
2. Open latest backup folder.
3. Copy files back to website root.
4. Test website.
5. Record rollback in deployment log.

Future advanced rollback can be added with admin authentication.

---

## Testing Checklist

```txt
[ ] Test on staging first
[ ] Correct secret triggers deployment
[ ] Wrong secret returns 403
[ ] Backup is created
[ ] Required files are validated
[ ] Lock file prevents double update
[ ] Logs are written
[ ] UI does not expose secrets
[ ] Website loads after update
[ ] Assets load after update
```

---

## Common Failure Messages

```txt
Invalid webhook signature
Deployment already running
Repository download failed
Zip extraction failed
Required files missing
Backup failed
Copy failed
Permission denied
```
