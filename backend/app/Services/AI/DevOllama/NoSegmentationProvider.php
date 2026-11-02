<?php

namespace App\Services\AI\DevOllama;

use App\Services\AI\ImageSegmentationProvider;

/** DEV ONLY. Ollama has no SAM equivalent, so no evidence regions are produced (rather than showing fake demo boxes). */
class NoSegmentationProvider implements ImageSegmentationProvider
{
    public function segment(string $imageBytes, string $mime, string $concept, int $width, int $height, ?string $demoScenario = null): array { return []; }
    public function mode(): string { return 'dev'; }
}
