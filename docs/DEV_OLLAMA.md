# DEV ONLY: temporary Ollama provider

This provider exists only so the team can test real AI behavior on a laptop while Meta Model API access is pending. It is not used in production and must be deleted before launch.

## Enable

In `backend/.env` (never committed):

```
AI_PROVIDER=ollama
OLLAMA_URL=http://localhost:11434
OLLAMA_MODEL=gemma4:31b-cloud
```

It is ignored when `APP_ENV=production`. Health (`/api/v1/health`) reports `ai.mode = dev`, and every analysis is labeled "DEV ONLY local model (Ollama). Not Meta AI".

## How it works

`App\Services\AI\DevOllama\OllamaVisionProvider` calls Ollama `/api/chat` with the image as base64, the same system prompt and JSON schema as the Meta provider, `think=false` and temperature 0.2. The cloud model sometimes wraps JSON in a markdown fence and ignores `format`, so the schema is also given in the prompt and fences are stripped. Output still goes through `AnalysisValidator`, with one retry. No segmentation is produced (`NoSegmentationProvider`) so no fake evidence boxes are shown.

## Remove before production

1. Delete `backend/app/Services/AI/DevOllama/`.
2. Remove the `AI_PROVIDER` block at the top of `AppServiceProvider::register`, the `dev` branch in `MetaController::health` and `Present::analysis`, and `ai_provider` plus `dev_ollama` in `config/rescuelens.php`.
3. Delete this file and `docs/eval/dev_ollama_*`.

## Real run on 2 November 2026 (through the app API)

Script: `docs/eval/dev_ollama_eval.py` (registers a test user, uploads, analyzes, posts). Raw output: `docs/eval/dev_ollama_results_2026_11_02.json`. Model `gemma4:31b-cloud` (vision capable per `ollama show`).

Images are open licensed from Wikimedia Commons and are not stored in the repo:

* accident1: [Car accident in Haraz Road 2](https://commons.wikimedia.org/wiki/File:Car_accident_in_Haraz_Road-2.jpg)
* accident2: [Bike accident on the road](https://commons.wikimedia.org/wiki/File:Bike_accident_on_the_road.jpg)
* accident3: [Japanese car accident](https://commons.wikimedia.org/wiki/File:Japanese_car_accident.jpg)
* control: [Sunflower from Silesia2](https://commons.wikimedia.org/wiki/File:Sunflower_from_Silesia2.jpg)

| Image | Lang | Seconds | Category | Severity | Incident scene | Title |
|---|---|---|---|---|---|---|
| accident1 | en | 3.9 | road_accident | high | yes | Overturned truck on roadway |
| accident1 | bn | 2.3 | road_accident | high | yes | রাস্তায় ট্রাক উল্টে পড়া |
| accident2 | en | 3.7 | road_accident | moderate | yes | Possible bicycle and car collision |
| accident3 | en | 2.1 | road_accident | moderate | yes | Road crash at pedestrian crossing |
| control | en | 1.6 | other | low | no | Sunflower Image |
| control | bn | 1.5 | other | low | no | কোনো দুর্ঘটনা নেই |

Post generation: the accident1 English draft was posted through `POST /api/v1/incidents` with consent and returned 201, title "Overturned truck on roadway", `ai_assisted=true`.

Observations: all three accidents were classified as road accidents and the control image was correctly rejected in both languages. Bangla output is natural Bengali script. The first attempt failed until fence stripping and the in prompt schema were added. Labeled DEV, not a Meta result and not a benchmark.
