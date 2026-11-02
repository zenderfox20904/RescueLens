<?php

namespace App\Services;

use App\Models\AiAnalysis;
use App\Models\IncidentMedia;
use App\Services\AI\AiProviderException;
use App\Services\AI\AnalysisValidator;
use App\Services\AI\ImageSegmentationProvider;
use App\Services\AI\VisionAnalysisProvider;
use Illuminate\Support\Facades\Storage;

class AnalysisPipeline
{
    public function __construct(private VisionAnalysisProvider $vision, private ImageSegmentationProvider $seg) {}

    public function run(IncidentMedia $media, int $userId, string $lang, ?string $note, ?string $demoScenario, ?string $country = null): AiAnalysis
    {
        $a = AiAnalysis::create([
            'user_id' => $userId, 'media_id' => $media->id, 'mode' => $this->vision->mode(), 'provider' => $this->vision->name(),
            'status' => 'processing', 'language' => $lang,
        ]);
        $t0 = microtime(true);
        $bytes = Storage::disk($media->disk)->get($media->path);
        try {
            $raw = $this->vision->analyze($bytes, $media->mime, $lang, $note, $demoScenario, $country);
            $clean = AnalysisValidator::clean($raw['result']);
            $segModel = null;
            $regions = [];
            if (config('rescuelens.meta.segmentation_enabled')) {
                foreach ($clean['segmentation_concepts'] as $concept) {
                    try {
                        foreach ($this->seg->segment($bytes, $media->mime, $concept, $media->width, $media->height, $demoScenario) as $r) {
                            // Validate coordinates against the analyzed image before storing.
                            if ($r['w'] !== $media->width || $r['h'] !== $media->height) continue;
                            if ($r['x1'] < 0 || $r['y1'] < 0 || $r['x2'] >= $r['w'] || $r['y2'] >= $r['h'] || $r['x1'] > $r['x2'] || $r['y1'] > $r['y2']) continue;
                            $regions[] = $r;
                            if (count($regions) >= 12) break 2;
                        }
                        $segModel = $this->seg->mode() === 'demo' ? 'demo-fixture' : config('rescuelens.meta.segmentation_model');
                    } catch (AiProviderException $e) {
                        // Segmentation is optional; analysis still succeeds.
                    }
                }
            }
            foreach ($regions as $r) {
                $a->regions()->create(['label' => $r['label'], 'source' => $this->seg->mode() === 'demo' ? 'demo' : 'sam-3.1',
                    'x1' => $r['x1'], 'y1' => $r['y1'], 'x2' => $r['x2'], 'y2' => $r['y2'],
                    'image_width' => $r['w'], 'image_height' => $r['h'], 'confidence' => $r['c']]);
            }
            $a->update([
                'status' => 'completed', 'vision_model' => $raw['model'], 'segmentation_model' => $segModel,
                'primary_hazard' => AnalysisValidator::primaryCategory($clean), 'severity' => $clean['severity'],
                'requires_human_review' => $clean['requires_human_review'], 'result' => $clean,
                'latency_ms' => (int) ((microtime(true) - $t0) * 1000),
            ]);
        } catch (AiProviderException $e) {
            $a->update(['status' => 'failed', 'error_code' => $e->errorCode, 'latency_ms' => (int) ((microtime(true) - $t0) * 1000)]);
        }
        return $a->load('regions');
    }
}
