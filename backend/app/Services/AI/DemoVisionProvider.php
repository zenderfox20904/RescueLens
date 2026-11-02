<?php

namespace App\Services\AI;

class DemoVisionProvider implements VisionAnalysisProvider
{
    public function analyze(string $imageBytes, string $mime, string $language, ?string $userNote, ?string $demoScenario = null, ?string $country = null): array
    {
        $s = DemoFixtures::pick($demoScenario, hash('sha256', $imageBytes));
        $r = DemoFixtures::analysis($s, $language);
        unset($r["regions"]);
        $en = DemoFixtures::analysis($s, "en");
        $extra = [
            "road_accident" => [["car"], "possible", "People may be near the damaged vehicles."],
            "flood" => [[], "possible", "People are wading through water."],
            "fire" => [[], "possible", "Buildings near the fire may be occupied."],
            "electrical" => [[], "possible", "The fallen wire is near a public path."],
            "building_collapse" => [[], "unclear", "It is not visible whether anyone is inside."],
        ][$s];
        $r += ["is_incident_scene" => true, "summary_en" => $en["summary"], "suggested_title_en" => $en["suggested_title"],
            "vehicles_involved" => $extra[0], "people_at_risk" => $extra[1], "people_at_risk_reason" => $extra[2], "severity_reason" => "DEMO sample value."];
        return ['result' => $r, 'model' => 'demo-fixture:'.$s];
    }

    public function mode(): string { return 'demo'; }
    public function name(): string { return 'demo-fixtures'; }
}
