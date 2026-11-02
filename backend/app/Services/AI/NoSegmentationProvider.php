<?php

namespace App\Services\AI;


/** Used with the Llama API, which has no segmentation model, so no evidence regions are produced (rather than showing fake demo boxes). */
class NoSegmentationProvider implements ImageSegmentationProvider
{
    public function segment(string $imageBytes, string $mime, string $concept, int $width, int $height, ?string $demoScenario = null): array { return []; }
    public function mode(): string { return 'live'; }
}
