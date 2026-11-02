# Troubleshooting

| Problem | Fix |
|---|---|
| `docker compose up` says a port is in use | Use other ports: `WEB_PORT=9090 API_PORT=9000 docker compose up --build` |
| Docker start is slow the first time | The API waits for MySQL, then migrates and seeds. Wait until the api logs show the server is serving on port 8000 |
| I want a clean demo again | `docker compose down -v`, then `docker compose up --build` |
| Login fails | Use the password `RescueLensDemo2026!` and an email from the README table |
| Everything is labeled DEMO | No AI key is set. Add `META_MODEL_API_KEY` (or `AI_PROVIDER=llama` and `LLAMA_API_KEY`), then `php artisan config:clear` or restart Docker |
| `setup.sh: permission denied` | `chmod +x scripts/setup.sh` or run `bash scripts/setup.sh --run` |
| `SQLSTATE[HY000] [1045]` | Check the `DB_*` values (or the `DB_PASSWORD` you passed to the setup script) |
| 419 or 401 after changing `.env` | `php artisan config:clear` and sign in again |
| Web loads but shows network errors | Check the API is on port 8000, or set `VITE_API_URL` and rebuild the web app |
| Images do not load | `php artisan storage:link` |
| App map shows OpenStreetMap instead of Google Maps | Expected without a key. Add a Google Maps key to switch. See [MOBILE_APP.md](MOBILE_APP.md) |
| Web map is grey or shows a watermark | Set `VITE_GOOGLE_MAPS_API_KEY` and rebuild the web app |
| APK cannot reach the API | The APK targets the Android emulator (`10.0.2.2:8000`). For a phone, rebuild with `--dart-define=API_BASE_URL=http://YOUR_LAN_IP:8000/api/v1` and serve the API with `--host=0.0.0.0` |
| Android blocks the APK install | Allow installs from unknown sources, or use `adb install` |
| iOS build fails on pods | `cd mobile/ios && pod install --repo-update` |
