<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmergencyResource;
use App\Models\Incident;
use App\Services\AI\MetaModelClient;
use Illuminate\Support\Facades\DB;

class MetaController extends Controller
{
    public function health()
    {
        $db = true;
        try { DB::select('select 1'); } catch (\Throwable) { $db = false; }
        $live = app(MetaModelClient::class)->configured();
        return response()->json([
            'status' => $db ? 'ok' : 'degraded', 'database' => $db,
            'ai' => app(\App\Services\AI\VisionAnalysisProvider::class)->mode() === 'dev'
                ? ['mode' => 'dev', 'configured' => true, 'provider' => 'ollama-dev', 'vision_model' => config('rescuelens.dev_ollama.model'), 'segmentation_model' => null,
                    'message' => 'DEV ONLY: local Ollama provider. Not Meta AI; do not use in production.']
                : ['mode' => $live ? 'live' : 'demo', 'configured' => $live,
                'vision_model' => config('rescuelens.meta.vision_model'), 'segmentation_model' => config('rescuelens.meta.segmentation_model'),
                'message' => $live ? 'Meta Model API key configured.' : 'Live AI analysis is unavailable: META_MODEL_API_KEY is not set. Results are clearly labeled DEMO samples.'],
            'social' => ['facebook' => filled(config('services.facebook.client_id')), 'google' => filled(config('services.google.client_id'))],
            'time' => now()->toIso8601String(),
        ], $db ? 200 : 503);
    }

    public function resources(\Illuminate\Http\Request $r)
    {
        // Only admin verified entries are public. Nothing is ever invented for a country without verified data.
        $country = strtoupper(substr((string) $r->query('country', 'BD'), 0, 2));
        return ['country' => $country, 'data' => EmergencyResource::where('is_active', true)->whereNotNull('verified_at')->where('country_code', $country)->get()->map(fn ($r) => [
            'id' => $r->id, 'country_code' => $r->country_code, 'name' => $r->name, 'name_bn' => $r->name_bn, 'category' => $r->category, 'phone' => $r->phone,
            'jurisdiction' => $r->jurisdiction, 'source_name' => $r->source_name, 'source_url' => $r->source_url,
            'verified' => $r->verified_at !== null, 'verified_at' => $r->verified_at?->toIso8601String(),
            'last_reviewed_at' => $r->last_reviewed_at?->toIso8601String(),
            'stale' => ! $r->last_reviewed_at || $r->last_reviewed_at->lt(now()->subDays(180)),
        ])];
    }

    public function stats()
    {
        $q = Incident::public();
        return ['data' => [
            'active' => (clone $q)->whereNotIn('status', ['resolved', 'expired'])->count(),
            'verified' => (clone $q)->where('status', 'verified')->count(),
            'last_24h' => (clone $q)->where('created_at', '>=', now()->subDay())->count(),
            'by_category' => (clone $q)->selectRaw('category, count(*) c')->groupBy('category')->pluck('c', 'category'),
        ]];
    }
}
