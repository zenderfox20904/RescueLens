<div align="center">

# RescueLens

**Snap a photo of an emergency. Meta AI drafts the report, highlights the evidence, and alerts people nearby while moderators verify.**

![Meta AI](https://img.shields.io/badge/AI-Meta%20AI-0866FF)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![React](https://img.shields.io/badge/React-19-61DAFB?logo=react&logoColor=black)
![Vite](https://img.shields.io/badge/Vite-8-646CFF?logo=vite&logoColor=white)
![Flutter](https://img.shields.io/badge/Flutter-3-02569B?logo=flutter&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white)
![Tests](https://img.shields.io/badge/backend%20tests-51%20passing-2ea44f)
![License](https://img.shields.io/badge/license-MIT-blue)

[Demo video](https://youtu.be/REPLACE_WITH_DEMO_VIDEO) · [Judging guide](docs/JUDGING_GUIDE.md) · [Android APK](https://github.com/zenderfox20904/RescueLens/releases/latest) · [Setup](docs/SETUP.md) · [Demo access](docs/DEMO_ACCESS.md) · [Database](docs/DATABASE.md) · [Docs](docs/README.md)

</div>

## Quick Start for Judges (5 minutes)

**Option 1, Docker (recommended).** Needs Docker Desktop only.

```bash
git clone https://github.com/zenderfox20904/RescueLens.git
cd RescueLens
docker compose up --build
```

Open **http://localhost:8080** and sign in as **rina@rescuelens.test** with password **RescueLensDemo2026!**

To see live Meta AI analysis, create a file named `.env` next to `docker-compose.yml` containing `META_MODEL_API_KEY=your_key` before running. Without a key, AI results are clearly labeled DEMO.

**Option 2, without Docker.** Needs PHP 8.3+, Composer, Node 20+, MySQL 8.

```bash
DB_PASSWORD=your_mysql_root_password ./scripts/setup.sh --run     # macOS and Linux
```
```powershell
$env:DB_PASSWORD="your_mysql_root_password"; .\scripts\setup.ps1 -Run   # Windows
```

Then open **http://localhost:5173**.

**Android app:** [download the APK](https://github.com/zenderfox20904/RescueLens/releases/download/v1.0.0/RescueLens-android.apk) and install it on an Android emulator while the API runs on your computer. Details in [docs/MOBILE_APP.md](docs/MOBILE_APP.md).

**Full walkthrough:** [docs/JUDGING_GUIDE.md](docs/JUDGING_GUIDE.md)

### Demo logins (password for all: `RescueLensDemo2026!`)

| Portal | Email | What to try |
|---|---|---|
| Citizen | rina@rescuelens.test | Report a photo, read the Meta AI draft, confirm it, comment, translate a post |
| Citizen | karim@rescuelens.test | Tap "I see this too" on Rina's report, get nearby alerts |
| Moderator | moderator@rescuelens.test | Verify, dispute, merge or resolve reports in Moderation |
| Admin | admin@rescuelens.test | Admin panel: users, organizations and verified emergency contacts |
| Fire service | fire@rescuelens.test | Responder portal: incidents dispatched to the fire service |
| Ambulance | ambulance@rescuelens.test | Portal with incidents dispatched to the ambulance service |
| Hospital | hospital@rescuelens.test | Portal with incidents dispatched to the hospital |
| NGO | ngo@rescuelens.test | Portal with nearby incidents the NGO can support |
| Help centre | helpcenter@rescuelens.test | Portal with nearby incidents and help listings |
| Responder | tanvir@rescuelens.test | Responder view with alerted incidents and call actions |

## The problem

When a road accident, flood, fire or building collapse happens, the first people on the scene usually have a phone but not the time or calm to write a clear report. Reports arrive late, in many languages, without location or severity, and responders cannot tell which ones are real. Worse, many apps quietly suggest that an area is "safe" when they simply have no data.

## The solution

RescueLens turns one photo into a structured, verifiable incident report.

1. The reporter takes or uploads a photo. Location metadata is stripped from the file.
2. Meta AI analyses the scene: category, severity, observations, possible hazards, a suggested title and summary, and evidence regions drawn on the photo.
3. The reporter reviews and confirms. Nothing is published without confirmation.
4. People nearby get an alert, responders (fire, ambulance, hospital, NGO, help centre) see it in their portal, and moderators verify, dispute, merge or resolve it.
5. Posts and comments are translated with Meta AI so a Bangla report is readable in English, Arabic and 37 more languages.

RescueLens never claims a road or place is safe, and it only shows emergency numbers that an admin verified with a cited source.

## Key features

* **Meta AI photo analysis** with severity, hazards and highlighted evidence regions
* **Human in the loop**: the reporter confirms every draft; moderators verify
* **Live map** with category icons, a heatmap and nearby alerts
* **Role portals** for citizens, moderators, admins, fire service, ambulance, hospital, NGO and help centre
* **Privacy first**: the precise reporter location is private; the public sees an approximate location
* **Verified emergency contacts only**, each with its source
* **40 languages** including Bangla and right to left Arabic, with Meta AI translation of posts and comments
* **Threaded comments, confirmations ("I see this too"), trash with undo**
* **Dark mode by default**, plus a light theme
* **Web PWA and Flutter app** (iOS and Android) on one Laravel API

## How Meta AI is used

All AI runs through Meta AI. The provider is chosen with `AI_PROVIDER` in `backend/.env`.

| Provider | API | Used for |
|---|---|---|
| `meta` (default) | Meta Model API, `https://api.meta.ai/v1` | Vision analysis (`muse-spark-1.3`), text and translation, SAM segmentation of evidence (`sam-3.1`) |
| `llama` | Meta Llama API, `https://api.llama.com/compat/v1` | Vision analysis and translation with `Llama-4-Maverick-17B-128E-Instruct-FP8` |

The model returns strict JSON that the backend validates before anything is shown. Without a key the app runs in a clearly labeled DEMO mode. Details: [docs/AI_PIPELINE.md](docs/AI_PIPELINE.md).

## Architecture

```mermaid
flowchart LR
    subgraph Clients
        W[React + Vite PWA]
        M[Flutter app<br/>iOS and Android]
    end
    subgraph API[Laravel 13 API]
        R[REST /api/v1<br/>Sanctum auth]
        P[Analysis pipeline]
        T[Translation service]
        N[Alerts and dispatch]
    end
    DB[(MySQL)]
    S[(Private storage<br/>EXIF stripped photos)]
    MA[Meta Model API<br/>muse-spark + SAM]
    LL[Meta Llama API]
    W --> R
    M --> R
    R --> P --> MA
    P -.-> LL
    R --> T --> MA
    R --> N
    R --> DB
    P --> S
    W --> GM[Google Maps]
    M --> GM
```

## Screenshots

### Meta AI analysis and reporting

| | | |
|---|---|---|
| ![Report](docs/screenshots/report-en.png) | ![Meta AI on the app](docs/screenshots/app/home_media_en.png) | ![Meta AI translation](docs/screenshots/post-translation.png) |
| Create a report from a photo | Meta AI drafted post in the app | Meta AI translation of a post |

### Feed, details and comments

| | | |
|---|---|---|
| ![Feed](docs/screenshots/feed-en.png) | ![Incident](docs/screenshots/incident-en.png) | ![Comments](docs/screenshots/app/web_detail_threaded_comments.png) |
| Live feed | Incident details with moderation | Threaded comments |

### Map, heatmap and alerts

| | | |
|---|---|---|
| ![Map](docs/screenshots/map-icons.png) | ![Heatmap](docs/screenshots/map-heatmap.png) | ![Alerts](docs/screenshots/alerts-rina.png) |
| Map with category icons | Heatmap | Nearby alerts |

### Portals

| | | |
|---|---|---|
| ![Admin](docs/screenshots/portal-admin.png) | ![Moderator](docs/screenshots/portal-moderator.png) | ![Fire](docs/screenshots/portal-fire.png) |
| Admin | Moderator | Fire service |
| ![Ambulance](docs/screenshots/portal-ambulance.png) | ![Hospital](docs/screenshots/portal-hospital.png) | ![NGO](docs/screenshots/portal-ngo.png) |
| Ambulance | Hospital | NGO |

### Profile, settings and trash

| | | |
|---|---|---|
| ![Profile](docs/screenshots/app/web_profile_timeline_own.png) | ![Settings](docs/screenshots/settings-en.png) | ![Trash](docs/screenshots/app/web_trash_undo_toast.png) |
| Profile timeline | Settings | Trash with undo |

### Themes and languages

| | | |
|---|---|---|
| ![Dark](docs/screenshots/feed-bn-dark.png) | ![Light](docs/screenshots/feed-bn.png) | ![Arabic](docs/screenshots/rtl-arabic.png) |
| Dark theme (Bangla) | Light theme (Bangla) | Right to left Arabic |

### Mobile app

| | | | |
|---|---|---|---|
| ![App feed](docs/screenshots/app/feed-live.png) | ![App map](docs/screenshots/app/map-heatmap.png) | ![App profile](docs/screenshots/app/profile_en.png) | ![App trash](docs/screenshots/app/trash.png) |
| Feed | Map heatmap | Profile | Trash |

More in [docs/screenshots](docs/screenshots).

## Tech stack

| Layer | Technology |
|---|---|
| API | PHP 8.3+, Laravel 13, Sanctum, MySQL 8 |
| Web | React 19, TypeScript, Vite 8, PWA, Google Maps |
| App | Flutter 3, Dart, Google Maps |
| AI | Meta Model API (muse-spark, SAM), Meta Llama API |
| Tests | PHPUnit with mocked Meta providers |

## Repository layout

| Folder | What is inside |
|---|---|
| [`backend/`](backend) | Laravel 13 API: controllers, Meta AI services (`app/Services/AI`), migrations, seeders, tests, SQL files in `database/` |
| [`frontend/`](frontend) | React 19 + Vite web app (PWA) |
| [`mobile/`](mobile) | Flutter app for Android and iOS |
| [`docs/`](docs) | Judging guide, setup, demo access, database, AI pipeline, FAQ, screenshots |
| [`scripts/`](scripts) | `setup.sh`, `setup.ps1` (run without Docker), `loadtest.sh` |
| [`.github/`](.github) | Issue and pull request templates |
| `docker-compose.yml` | One command stack: MySQL, API and web |

## Team

* **Arifuzzaman Raihan**, design and engineering

## License

[MIT](LICENSE). © 2026 RescueLens
