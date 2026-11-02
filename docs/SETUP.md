# Setup

## Fastest: Docker

```bash
docker compose up --build
```

Web: http://localhost:8080, API: http://localhost:8000/api/v1. The first start migrates and seeds the demo accounts (password `RescueLensDemo2026!`). Change ports with `WEB_PORT=9090 API_PORT=9000 docker compose up --build`. For live Meta AI put `META_MODEL_API_KEY=...` in a `.env` file next to `docker-compose.yml`. Reset everything with `docker compose down -v`.

## Without Docker: setup scripts

```bash
DB_PASSWORD=your_mysql_password ./scripts/setup.sh --run
```
```powershell
$env:DB_PASSWORD="your_mysql_password"; .\scripts\setup.ps1 -Run
```

The script installs dependencies, creates `backend/.env`, creates the database, migrates, seeds the demo accounts and starts the API (8000) and web (5173). Optional variables: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `API_PORT`, `WEB_PORT`, `SKIP_INSTALL=1`.

The manual steps follow.

## Prerequisites

* PHP 8.3 or newer with `pdo_mysql`, `gd` or `imagick`, `mbstring`, `intl`
* Composer 2
* MySQL 8 (or MariaDB 10.11)
* Node.js 20 or newer and npm
* Flutter 3.35 or newer (Dart 3.13), Xcode for iOS, Android Studio for Android
* A **Meta AI API key** (Meta Model API, or Meta Llama API)
* Optional: a Google Maps API key for the map tiles

## Backend (Laravel API)

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
```

Edit `backend/.env`:

* `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` for your MySQL database (create it first: `CREATE DATABASE rescuelens CHARACTER SET utf8mb4;`)
* `META_MODEL_API_KEY` (see AI provider below)
* `SYSTEM_ADMIN_EMAIL` and `SYSTEM_ADMIN_PASSWORD` for your own admin. Choose a strong password. It is printed once by the seeder and never committed.

Then build the data. Use **either** option A or option B.

### Database

**Option A, migrations and seeders**

```bash
php artisan migrate
php artisan db:seed                          # demo users, organizations, incidents
php artisan db:seed --class=JudgeDemoSeeder  # optional: demo password from DEMO_ACCESS.md
php artisan rescuelens:analyze-seeded        # runs Meta AI on the seeded photos
```

**Option B, import the SQL dump**

`backend/database/rescuelens.sql` contains the structure plus sanitized demo data (demo accounts only, demo password from [DEMO_ACCESS.md](DEMO_ACCESS.md)).

```bash
mysql -u root -p -e "CREATE DATABASE rescuelens CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p rescuelens < database/rescuelens.sql
php artisan migrate --force   # no changes expected, confirms the schema is current
```

Photos are not included in the dump, so run `php artisan migrate:fresh --seed` (option A) if you want the seeded images. `backend/database/schema.sql` is the structure only, for reference or review.

### Run

```bash
php artisan storage:link
php artisan serve --host=0.0.0.0 --port=8000
php artisan test     # 51 tests, Meta AI calls are mocked
```

## AI provider

| `AI_PROVIDER` | Keys | Notes |
|---|---|---|
| `meta` (default) | `META_MODEL_API_KEY`, `META_MODEL_API_BASE_URL`, `META_VISION_MODEL`, `META_TEXT_MODEL`, `META_SEGMENTATION_MODEL` | Meta Model API with SAM evidence segmentation |
| `llama` | `LLAMA_API_KEY`, `LLAMA_BASE_URL`, `LLAMA_MODEL` | Meta Llama API (OpenAI compatible); evidence regions come from the model |

Other settings: `AI_REQUEST_TIMEOUT`, `AI_MAX_RETRIES`, `AI_DAILY_BUDGET`, `AI_ENABLE_SEGMENTATION`. Without a key the app runs in DEMO mode and every AI result is labeled DEMO. Keys stay in `.env` and are never sent to the browser or the app.

## Web (React + Vite)

```bash
cd frontend
cp .env.example .env    # VITE_API_URL, VITE_SHARE_BASE, VITE_GOOGLE_MAPS_API_KEY
npm install
npm run dev             # http://localhost:5173
npm run build           # production build in dist/
```

## App (Flutter)

APK download, iOS steps and API address options: [MOBILE_APP.md](MOBILE_APP.md).

```bash
cd mobile
flutter pub get
```

The API address is passed with a dart define.

```bash
# iOS simulator
flutter run -d ios --dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1
# Android emulator (10.0.2.2 is the host machine)
flutter run -d android --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
# Physical device: use your computer's LAN IP and serve the API with --host=0.0.0.0
```

### Maps keys

* **iOS**: create `mobile/ios/Flutter/Secrets.xcconfig` (gitignored) with `MAPS_API_KEY=your_key`. `Info.plist` reads it as `GMSApiKey`.
* **Android**: add `MAPS_API_KEY=your_key` to `mobile/android/local.properties` (gitignored), or export `MAPS_API_KEY` before building.
* **Web**: `VITE_GOOGLE_MAPS_API_KEY` in `frontend/.env`.

Restrict each key to its platform in the Google Cloud console.

## Troubleshooting

See [TROUBLESHOOTING.md](TROUBLESHOOTING.md).
