# AI pipeline and auto detection

RescueLens uses AI to draft reports, never to decide what is true. Every AI output is labeled, and nothing becomes verified without a human moderator.

## Providers

| Setting | Provider | Label shown to users |
|---|---|---|
| `META_MODEL_API_KEY` set (default `AI_PROVIDER=meta`) | Meta Muse Spark vision plus SAM 3.1 evidence regions | AI suggestion from Meta Model API |
| No key | Demo fixtures | DEMO sample output |
| `AI_PROVIDER=ollama` (DEV ONLY) | Local Ollama, see [DEV_OLLAMA.md](DEV_OLLAMA.md) | DEV ONLY local model |

All providers implement `App\Services\AI\VisionAnalysisProvider` and `ImageSegmentationProvider`. Output passes `AnalysisValidator` before it is stored.

## 1. One tap report from a photo

1. The user picks or captures a photo. EXIF and GPS metadata are stripped and the image is re encoded.
2. The vision provider returns a structured analysis (category, severity, title, summary, hazards, uncertainties).
3. The report form is filled from the analysis. The user reviews it and taps Post once.
4. Duplicate check runs first: a nearby open incident of the same category is offered for a confirmation instead.

## 2. Auto post (opt in)

* Off by default. Enabled in Settings, Privacy and AI, only after an explicit consent dialog. The API refuses `auto_post_enabled=true` without `auto_post_consent=true`; the consent time is stored in `users.auto_post_consent_at` and audited (`consent.auto_post.granted` or `revoked`).
* When on and the analysis says the photo is an incident scene, the report is posted at once with `source=auto_post` and the badge "AI detected, unverified".
* A 10 second undo bar appears. Undo withdraws the report. Nearby alerts are sent by a queued `NotifyNearby` job only after the undo window, and only if the report is still public.
* The server rejects `auto_post` for users who have not opted in, and for posts without a completed analysis.

## 3. CCTV and camera feeds

* Admins register feeds in the admin API (`/api/v1/mod/admin/camera-feeds`). Two kinds:
  * `snapshot_url`: https only, host must be listed in `CAMERA_ALLOWED_HOSTS`. Before each fetch the host is resolved and private or reserved addresses are refused; redirects are not followed; responses must be images up to 8 MB.
  * `upload`: an admin uploads frames (`POST camera-feeds/{id}/frames`).
* `php artisan cameras:poll` runs every minute through the scheduler and queues `PollCameraFeed` for feeds whose interval has passed. Frames are analyzed by the `AnalyzeCameraFrame` job on the queue (`php artisan queue:work`).
* A detection creates an incident with `source=camera`, `ai_unverified=true`, status `reported`, in the moderation queue. It is never auto verified.
* It appears in the public feed only when all of these hold: the model says it is an incident scene, severity is high or critical, and the best evidence region confidence is at least `CAMERA_MIN_CONFIDENCE` (default 0.7). Otherwise it stays hidden until a moderator verifies it. Verification clears the AI unverified label and makes it visible.
* Dedupe: if an open incident of the same category exists within the duplicate radius and window, the frame is attached to it instead of creating a new incident.
* Nearby users are notified only for feed eligible detections.
* Snapshot URLs are never returned by the API; only the host is shown. Image bytes and fetched responses are never logged.

## Safety rules that apply to every path

* AI never claims a road or area is safe.
* Precise reporter or camera coordinates are never public; public coordinates are rounded.
* Emergency numbers come only from the verified directory with a cited source.

## Tests

`backend/tests/Feature/AutoDetectionTest.php` covers consent gating, undo, allowlist, admin only access, moderation, feed thresholds, dedupe and provider selection.

## Default provider: Meta Llama (open weights)

RescueLens now defaults to `AI_PROVIDER=llama`, which runs Meta Llama open weights through any OpenAI compatible endpoint.

* Local: Ollama at `http://localhost:11434/v1` with `LLAMA_MODEL=llama3.2-vision` (`ollama pull llama3.2-vision`, about 7.8 GB on disk, 16 GB RAM is enough).
* Hosted later: set `LLAMA_BASE_URL`, `LLAMA_API_KEY` and `LLAMA_MODEL` for Baseten, Modal or Fireworks. Keys live only in `.env`.
* Users see only "Meta AI" plus `LLAMA_MODEL_LABEL` (default "Llama 3.2 Vision").
* Code: `app/Services/AI/Llama` (OpenAiCompatClient, LlamaVisionProvider, LlamaTextTranslator). Output is validated against the same schema and retried once.
* There is no segmentation model on this path, so region masks are skipped.
* `AI_PROVIDER=meta` still selects the Meta Model API; the legacy native Ollama provider remains DEV ONLY.
