# Demo access

These accounts exist **only on a local or seeded install** (after `db:seed` plus `JudgeDemoSeeder`, or after importing `backend/database/rescuelens.sql`). They are not real people and they do not exist on any production server.

**Demo password for every account: `RescueLensDemo2026!`**

This password is for demos only. Do not reuse it anywhere.

| Portal | Email |
|---|---|
| Admin | admin@rescuelens.test |
| Moderator | moderator@rescuelens.test |
| Citizen | rina@rescuelens.test |
| Citizen | karim@rescuelens.test |
| Responder | tanvir@rescuelens.test |
| Fire service | fire@rescuelens.test |
| Ambulance | ambulance@rescuelens.test |
| Hospital | hospital@rescuelens.test |
| NGO | ngo@rescuelens.test |
| Help centre | helpcenter@rescuelens.test |

## How the password is set

* `php artisan db:seed` creates the demo accounts. Then `php artisan db:seed --class=JudgeDemoSeeder` sets the demo password above on every demo account. It skips your system admin.
* `rescuelens.sql` already contains the demo password hashes.

## Your own admin

The system admin is created from `SYSTEM_ADMIN_EMAIL` and `SYSTEM_ADMIN_PASSWORD` in `backend/.env`. That account is never in the repository or the SQL dump. If you keep a private list of local logins, name it `DEMO_ACCOUNTS.local.txt`; files matching `*.local.txt` are gitignored.
