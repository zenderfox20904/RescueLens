<?php

namespace App\Services\AI;

class DemoSegmentationProvider implements ImageSegmentationProvider
{
    public function segment(string $imageBytes, string $mime, string $concept, int $width, int $height, ?string $demoScenario = null): array
    {
        $s = DemoFixtures::pick($demoScenario, hash('sha256', $imageBytes));
        $out = [];
        foreach (DemoFixtures::analysis($s, 'en')['regions'] as [$label, $a, $b, $c, $d]) {
            if ($label !== $concept) continue;
            $out[] = ['label' => $label, 'x1' => (int) ($a * ($width - 1)), 'y1' => (int) ($b * ($height - 1)),
                'x2' => (int) ($c * ($width - 1)), 'y2' => (int) ($d * ($height - 1)), 'w' => $width, 'h' => $height, 'c' => null];
        }
        return $out;
    }

    public function mode(): string { return 'demo'; }
}
