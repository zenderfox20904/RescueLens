# AI pipeline and auto detection

RescueLens uses AI to draft reports, never to decide what is true. Every AI output is labeled, and nothing becomes verified without a human moderator.

## Providers

| Setting | Provider | Label shown to users |
|---|---|---|
| `META_MODEL_API_KEY` set (default `AI_PROVIDER=meta`) | Meta Muse Spark vision plus SAM 3.1 evidence regions | AI suggestion from Meta Model API |
| No key | Demo fixtures | DEMO sample output |
| `AI_PROVIDER=llama` plus `LLAMA_API_KEY` | Meta Llama API (Llama 4 Maverick, vision) | Meta AI |

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

## Alternative provider: Meta Llama API

Set `AI_PROVIDER=llama` to use the Meta Llama API through its OpenAI compatible endpoint.

* `LLAMA_BASE_URL=https://api.llama.com/compat/v1`, `LLAMA_API_KEY` (from llama.developer.meta.com), `LLAMA_MODEL=Llama-4-Maverick-17B-128E-Instruct-FP8`. Keys live only in `.env`.
* Users see only "Meta AI".
* Code: `app/Services/AI/Llama` (OpenAiCompatClient, LlamaVisionProvider, LlamaTextTranslator). Output is validated against the same schema and retried once.
* This path has no segmentation model, so evidence regions are skipped (`NoSegmentationProvider`).

## Meta Model API (production provider)

Set `AI_PROVIDER=meta` (the default) and `META_MODEL_API_KEY` in `backend/.env` (never commit it). Everything below follows the official docs, checked on 2 Nov 2026.

| Item | Value | Source |
| --- | --- | --- |
| Base URL | `https://api.meta.ai/v1` (`META_MODEL_API_BASE_URL`) | https://dev.meta.ai/docs/api-reference |
| Auth | `Authorization: Bearer <key>`; keys look like `LLM_<id>_<secret>` | https://dev.meta.ai/docs/authentication |
| Endpoint we call | `POST /v1/responses` (OpenAI Responses compatible) | https://dev.meta.ai/docs/protocols/responses |
| Models | `META_VISION_MODEL` (default `muse-spark-1.3`), `META_TEXT_MODEL`, `META_SEGMENTATION_MODEL` (`sam-3.1`). Cheaper Contributor mode: `muse-spark-1.3-contributor` (your content may be used to train Meta models) | https://dev.meta.ai/docs/models, https://dev.meta.ai/docs/pricing-rate-limits |
| Image input | `input_image` block with `image_url` as a base64 `data:` URL; jpeg, png, gif, webp; max 50 MB inline, 50 images per request | https://dev.meta.ai/docs/image-understanding |
| Video input | mp4 via Files API `file_id` (`input_file`) or `input_video` with `video_url` | https://dev.meta.ai/docs/video-understanding |
| JSON output | `text.format` with `type: json_schema`, `strict: true` (Responses API). On Chat Completions use `response_format` | https://dev.meta.ai/docs/structured-output |
| Sampling | Muse Spark always reasons and is tuned for default temperature, so `AI_TEMPERATURE` is empty by default | https://dev.meta.ai/docs/protocols/chat-completions |
| Errors | Envelope `{error:{message,type,param,code}}`. We do not retry 400, 401, 402, 403, 404, 413, 504. We retry 429, 500, 503 with backoff and honor `Retry-After` | https://dev.meta.ai/docs/error-handling |
| Rate limits | Per team per model, for example `muse-spark-1.3` 3,000 RPM and 4M TPM; contributor 150 RPM. We store `x-ratelimit-remaining-*` headers in cache | https://dev.meta.ai/docs/pricing-rate-limits |

### Getting a key

1. Sign in to the Model API dashboard at https://dev.meta.ai, open the API keys tab, click Create API key and copy it once.
2. Put it in `backend/.env` as `META_MODEL_API_KEY`.

Billing: the docs describe pay as you go billing only (Muse Spark 1.3: $1.25 per 1M input tokens, $4.25 per 1M output; Contributor mode $0.10 and $0.20). No free tier is documented, and a billing problem returns `HTTP 402 billing_not_configured`, so expect to add a payment method. Without a key the app runs in the labeled DEMO mode.

### Contract test

`backend/tests/Feature/MetaModelApiContractTest.php` mocks the documented success object and error envelopes and checks the request shape (URL, Bearer header, `input_image` data URL, `json_schema` format, model taken from config), error mapping, which errors are retried, and that no request is made without a key.
