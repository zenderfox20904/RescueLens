<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Services\I18n\TranslationService;

class LanguageController extends Controller
{
    public function index()
    {
        return ['data' => Language::where('enabled', true)->orderBy('sort')->get()->map(fn ($l) => Language::present($l)), 'default' => 'en'];
    }

    /** i18next http backend: GET /api/v1/i18n/{code}. English for anything not yet translated (fallbackLng). */
    public function bundle(string $code, TranslationService $svc)
    {
        $lang = Language::where('code', $code)->where('enabled', true)->first();
        abort_unless($lang, 404, 'Language not available.');
        $b = $svc->bundle($lang);
        return response()->json($b['translations'])->withHeaders([
            'X-RL-Machine-Translated' => $b['machine'] ? '1' : '0',
            'X-RL-Translation-Provider' => (string) $b['provider'],
            'X-RL-Missing' => (string) $b['missing'],
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
