# Requirements Traceability

Status as of 2026-11-02 15:30 (Asia/Dhaka). Status values: **Done** (implemented and covered by a passing test or a manual check), **Partial**, **Not started**. Only verified results are marked Done.

Tests: `backend/tests/Feature/RescueLensApiTest.php` (5 tests) and `backend/tests/Unit/AiValidationTest.php` (4 tests). 9 of 9 pass (`php artisan test`).

## 5. AI providers

| Requirement | Files | Tests | Status |
|---|---|---|---|
| VisionAnalysisProvider interface, Meta and demo implementations | backend/app/Services/AI/VisionAnalysisProvider.php, MetaVisionProvider.php, DemoVisionProvider.php | RescueLensApiTest report flow | Done |
| ImageSegmentationProvider (SAM 3.1 boxes) | ImageSegmentationProvider.php, MetaSamProvider.php, DemoSegmentationProvider.php | report flow (demo) | Partial: boxes only, masks not stored; live call unverified |
| EmergencyGuidanceProvider | | | Not started |
| NotificationProvider | | | Not started (in app notifications exist without a provider interface) |
| Env vars META_MODEL_API_KEY, BASE_URL, VISION_MODEL, SEGMENTATION_MODEL, AI_REQUEST_TIMEOUT, AI_MAX_RETRIES, AI_DAILY_BUDGET | backend/config/rescuelens.php, .env.example | | Done |
| META_TRANSCRIPTION_MODEL | | | Not started |
| Strict structured output (json_schema, strict true), reasoning.effort high, low temperature | AnalysisSchema.php, MetaVisionProvider.php | AiValidationTest::test_strict_schema..., test_provider_retries... | Done in code; live model acceptance of temperature unverified (no key) |
| Retry once on schema failure | MetaVisionProvider.php | test_provider_retries_once_on_schema_failure | Done |
| Evidence vs inference, people_at_risk, bilingual summary, severity reason | AnalysisSchema.php, AnalysisValidator.php | test_cleaning_enforces_safety_and_enums | Done |
| Fields language, model_metadata on stored analysis | AnalysisPipeline.php | | Partial (model and mode stored; language field to confirm) |
| No invented confidence | AnalysisSchema prompt, likelihood enum only | | Done |
| Demo mode labeled | DemoFixtures.php, mode = demo | report flow | Done |
| Backoff, circuit breaker, daily budget | MetaModelClient.php | | Partial (implemented, no dedicated test) |
| Health check distinguishing config/auth/rate limit/outage | routes/api.php health, AiProviderException codes | | Partial |
| Global hazards and region context (country param) | AnalysisSchema.php, AnalysisController.php | | Done in code |
| Evaluation set and per category accuracy script | | | Not started |
| Auto detect, one tap post, auto post with undo, CCTV feed mode | | | Not started |

## 6 to 12. Safety, data, API, security, privacy, states, notifications

| Requirement | Files | Status |
|---|---|---|
| Only 999 shown, with source; phone numbers stripped from AI output | AnalysisValidator.php, emergency_resources seed | Done (test) |
| Emergency resource directory fields and admin verify | migration emergency_resources, POST emergency-resources/{id}/verify | Done (country selector UI not started) |
| Tables users, incidents, incident_media, ai_analyses, ai_evidence_regions, confirmations, comments, status_history, emergency_resources, user_notifications, idempotency_keys, audit_logs | database/migrations | Done |
| user_profiles, roles/permissions, responder_verifications, incident_reports, incident_assignments, moderation_cases, notification_preferences, notification_deliveries, consent_records, privacy_requests, system_settings | | Not started |
| /api/v1 endpoints (36 routes) | routes/api.php | Partial vs spec |
| Idempotency keys | IdempotencyKey model, IncidentController | Partial |
| OpenAPI | docs/openapi.yaml | Not started |
| Argon2id | HASH_DRIVER=argon2id in .env.example, HASH_VERIFY=false for rehash of legacy bcrypt | Done (tests pass under argon2id) |
| Security headers / CSP | Http/Middleware/SecurityHeaders.php | Done |
| Magic bytes, EXIF strip, resize, pixel limit | ImageProcessor.php, max_image_pixels | Done |
| Signed media URLs | media/{media}/file | Partial (verify signing) |
| Approximate public location | public_location_decimals | Done |
| Anonymous reporting, data export, retention config | | Not started |
| Account deletion | DELETE auth/me | Done |
| Email verify, password reset, MFA for privileged roles | | Not started |
| Status states, stale labeling | incidents.status, stale_hours | Partial (appeals not started) |
| Notifications in app | user_notifications, GET notifications | Done; email/SMS/push Not started |
| Rate limits configurable | AppServiceProvider, RATE_LIMIT_API_PER_MIN | Done |
| Load test results | loadtest/run.sh | Partial: runs returned 0 req/s, cause not yet found |

## Product and UI

| Requirement | Status |
|---|---|
| Footer "© 2026 RescueLens" web and Flutter | Done (web build passes; Flutter analyze rerun pending) |
| Facebook precise UI on every screen (signup, login, feed, admin, map, profile, settings, notifications, detail) | Not started (palette done; layout pass pending) |
| Seeded system admin with local demo credentials | Not started |
| react-i18next, bn/en, RTL ready, browser language detect, timezone, units | Done: i18next with browser detection, RTL dir switch, Intl dates/numbers in viewer time zone, km/mi, country selector in Profile, verified per country emergency directory (only BD 999 seeded). Tests: InternationalizationTest (2). See docs/INTERNATIONALIZATION.md |
| Flutter ARB localization | Not started |
| Google Maps web and Flutter | Done (rendered); clustering and side list Not started |
| Screenshots in docs/screenshots | Not started |
| Docs set (ARCHITECTURE, THREAT_MODEL, INTERNATIONALIZATION, AI_PIPELINE, etc.) | Not started |
| CI workflow | Not started |
| Push to GitHub | Done: normal pushes to origin main |
