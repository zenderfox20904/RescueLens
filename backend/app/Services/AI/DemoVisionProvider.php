<?php

namespace App\Services\AI;

class DemoVisionProvider implements VisionAnalysisProvider
{
    public function analyze(string $imageBytes, string $mime, string $language, ?string $userNote, ?string $demoScenario = null): array
    {
        $s = DemoFixtures::pick($demoScenario, hash('sha256', $imageBytes));
        $r = DemoFixtures::analysis($s, $language);
        unset($r['regions']);
        return ['result' => $r, 'model' => 'demo-fixture:'.$s];
    }

    public function mode(): string { return 'demo'; }
    public function name(): string { return 'demo-fixtures'; }
}
