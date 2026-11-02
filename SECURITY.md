# Security policy

## Reporting a vulnerability

Please do not open a public issue. Use GitHub's **Report a vulnerability** button on the Security tab of this repository (private advisory). Include steps to reproduce and the impact. We aim to reply within 72 hours.

## How RescueLens protects users

* Secrets live only in `.env` files, which are gitignored. AI and maps keys are never sent to clients except restricted maps keys.
* Uploaded photos have EXIF and location metadata stripped and are stored privately.
* The precise reporter location is visible only to moderators and admins; everyone else sees an approximate location.
* The `role` field cannot be set by users; roles change only through admin actions, which are audit logged.
* API authentication uses Laravel Sanctum tokens; sensitive endpoints are rate limited.
* Demo accounts use a documented demo password and exist only on local installs.

## Supported versions

Only the latest commit on `main` is supported.
