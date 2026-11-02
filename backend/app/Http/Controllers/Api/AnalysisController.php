<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiAnalysis;
use App\Models\IncidentMedia;
use App\Services\AI\DemoFixtures;
use App\Services\AnalysisPipeline;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnalysisController extends Controller
{
    public function store(Request $r, AnalysisPipeline $p)
    {
        $d = $r->validate([
            'media_id' => 'required|string|size:26', 'language' => 'nullable|string|regex:/^[a-z]{2}(-[A-Z]{2})?$/', 'country' => 'nullable|string|size:2|alpha', 'note' => 'nullable|string|max:300',
            'ai_consent' => 'accepted', 'demo_scenario' => ['nullable', Rule::in(DemoFixtures::SCENARIOS)],
        ]);
        $media = IncidentMedia::where('public_id', $d['media_id'])->where('user_id', $r->user()->id)->firstOrFail();
        $a = $p->run($media, $r->user()->id, $d['language'] ?? $r->user()->locale, $d['note'] ?? null, $d['demo_scenario'] ?? null, strtoupper($d['country'] ?? 'BD'));
        return response()->json(['analysis' => Present::analysis($a->load('media'))], $a->status === 'completed' ? 201 : 502);
    }

    public function show(Request $r, string $id)
    {
        $a = AiAnalysis::where('public_id', $id)->where('user_id', $r->user()->id)->with('regions', 'media')->firstOrFail();
        return ['analysis' => Present::analysis($a)];
    }
}
