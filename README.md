# RescueLens

AI assisted community incident reporting. Snap a photo, Meta Muse Spark drafts the report, SAM 3.1 highlights the evidence, you confirm, and nearby people are alerted while moderators verify.

* `backend/`: Laravel 13 API (Sanctum, MariaDB)
* `frontend/`: React 19 and Vite PWA
* `mobile/`: Flutter app
* `docs/`: architecture, AI pipeline, internationalization, threat model, traceability

## Quick start (local)
```bash
cd backend && cp .env.example .env && composer install && php artisan key:generate
php artisan migrate --seed && php artisan serve
cd ../frontend && npm install && npm run dev
```

## Local demo credentials
These exist only on your machine and only for the demo.
* System admin: set `SYSTEM_ADMIN_EMAIL` and `SYSTEM_ADMIN_PASSWORD` in `backend/.env`, then run `php artisan db:seed`. The seeder prints the credentials once in your terminal.
* Demo citizens and moderator: `rina@rescuelens.test`, `moderator@rescuelens.test`, password `password`.
Never reuse these values outside local development.

## Safety
* RescueLens never claims a road or place is safe.
* The only emergency numbers shown are admin verified entries with a cited source (seeded: Bangladesh 999, source 999.gov.bd).
* Nothing is published without the user's confirmation, except opt in Auto post, which is labeled "AI detected, unverified" and can be undone for 10 seconds.
* Without a Meta API key the app runs in a clearly labeled DEMO mode.

## Screenshots
`docs/screenshots/` (English and Bangla). Regenerate with `cd frontend && npm run screenshots`.

## License
See LICENSE. Author: Arifuzzaman Raihan.
