# Mantelzorg Checker

A PHP-based check-in system for informal caregiving (mantelzorg) situations. It allows care recipients (clients) to confirm their well-being daily (or on a set interval) by pressing a button on a personal web link. If they fail to check in before a configured time, their caregiver(s) (mantelzorgers) receive a push notification via [ntfy.sh](https://ntfy.sh).

---

## Features

- **Personal check-in links** — each client gets a unique token-based URL they can bookmark or save as a shortcut
- **Configurable check interval** — daily, every other day, weekly, or any custom interval
- **Configurable alert time** — alerts are only sent after a set hour (05:00–20:00)
- **Push notifications via ntfy.sh** — alerts and late check-in notifications are sent as push messages, no email required
- **Late check-in detection** — if a client checks in after the alert time, caregivers are notified separately
- **Pause/resume per client** — alerts can be paused indefinitely or until a specific date (e.g. for holidays)
- **Multiple caregivers per client** — each caregiver has their own ntfy topic so notifications are independent
- **Missed check-in history** — the dashboard shows how many check-ins were missed in the last 30 days
- **System logs** — all alert activity is logged and viewable in the admin panel

---

## How it Works

1. A client receives their personal check-in URL (e.g. `https://example.com/checkin/check-in.php?token=abc123`)
2. Each day (or per configured interval), they open the link and press **"Ik ben er nog!"** ("I'm still here!")
3. A scheduled script (`alert-check.php`) runs every hour via a cron job
4. If a client has not checked in before their alert time, all linked caregivers receive a push notification on their ntfy topic

---

## Hosting & Infrastructure

### Strato Basic Hosting

This application is deployed on a **Strato Basic hosting package**. Due to the constraints of this shared hosting environment:

- **No SSH access** — file management and deployment happen via FTP/SFTP or the Strato file manager
- **Cron jobs are configured via the Strato control panel** — the alert check script (`alert-check.php`) must be scheduled there as a URL-based or PHP cron task
- **No command-line PHP** — the cron job calls `alert-check.php` as a web request (or via Strato's built-in cron PHP runner)
- **Database is hosted on Strato's shared MySQL** — credentials are stored outside the webroot in `config-secure.php` for security
- **`config-secure.php` lives one level above the webroot** — on Strato this means it is placed in the parent directory of `public_html` / `httpdocs`, inaccessible from the browser

### Cron Job Setup (Strato)

In the Strato control panel, add a cron job that runs `alert-check.php` every hour:

```
URL: https://yourdomain.nl/checkin/alert-check.php
Interval: every hour (or every 30 minutes for more precision)
```

Because Strato Basic does not support native cron expressions with per-minute granularity, the script itself checks whether the current time is past the configured alert time for each client, preventing duplicate alerts.

---

## File Structure

```
mantelzorg-checker/
├── config-secure.php          # Database credentials (outside webroot on production)
└── checkin/
    ├── .htaccess              # Blocks direct access to config files
    ├── config.php             # Loads DB config, sets timezone
    ├── index.php              # Admin dashboard (status overview + settings)
    ├── check-in.php           # Public check-in page (token-based, no login)
    ├── alert-check.php        # Cron script: checks overdue clients & sends alerts
    ├── alert-check-debug.php  # Debug version of alert-check with verbose output
    ├── index-debug.php        # Debug version of dashboard
    ├── login.php              # Caregiver login
    ├── logout.php             # Session logout
    ├── forgot-password.php    # Password reset request
    ├── reset-password.php     # Password reset via token
    ├── change-password.php    # Change password when logged in
    ├── manage-clients.php     # Add/edit/remove clients and their caregivers
    ├── manage-mantelzorgers.php # Add/edit/remove caregivers and their ntfy topics
    ├── view-logs.php          # View system logs
    └── includes/
        ├── auth.php           # Login/session/password functions
        ├── database.php       # All DB functions (check-ins, alerts, clients, etc.)
        └── email.php          # ntfy.sh push notification functions
```

---

## Database

Four main tables:

| Table | Purpose |
|---|---|
| `clients` | Care recipients with token, interval, alert time, pause state |
| `mantelzorgers` | Caregivers with name, email, and personal ntfy topic |
| `client_mantelzorgers` | Many-to-many link between clients and caregivers |
| `check_in_history` | Log of all check-ins, late check-ins, and alerts sent |
| `system_logs` | Application-level event log |

---

## Notifications

Alerts are sent via **[ntfy.sh](https://ntfy.sh)** — a free, open-source push notification service. Each caregiver gets a unique auto-generated ntfy topic when they are added to the system. They subscribe to this topic in the ntfy app (Android/iOS/web) to receive alerts.

- Alert notification priority: **max** (urgent)
- Late check-in notification priority: **default**

Email notifications are present in the codebase but disabled — ntfy is the active notification method.

---

## Security Notes

- `config-secure.php` with database credentials is stored **outside the webroot** (one directory above `httpdocs`) so it cannot be served by the web server
- `.htaccess` additionally blocks direct access to `config.php` and hidden files within the `checkin/` directory
- Check-in links use 64-character hex tokens (`bin2hex(random_bytes(32))`) — no login required for clients
- The admin dashboard requires login; passwords are hashed with `password_hash()`
- All database queries use prepared statements to prevent SQL injection

---

## Local Development

The project was developed locally using **XAMPP**. The working directory is `c:\xampp\htdocs\mantelzorg-checker\`.

For local development, place `config-secure.php` one level above the `checkin/` folder (i.e. directly in `mantelzorg-checker/`) with your local database credentials.
