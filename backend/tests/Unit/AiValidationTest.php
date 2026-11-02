<?php

namespace Tests\Unit;

use App\Services\AI\AiProviderException;
use App\Services\AI\AnalysisSchema;
use App\Services\AI\AnalysisValidator;
use App\Services\AI\MetaVisionProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiValidationTest extends TestCase
{
    private function valid(): array
    {
        return ['summary' => 'Bus overturned. Call 01711000000', 'severity' => 'moderate', 'people_at_risk' => 'yes',
            'observations' => [['text' => 'Bus on its side', 'kind' => 'visible']],
            'possible_hazards' => [['type' => 'injury', 'evidence' => 'person on ground', 'likelihood' => 'likely'], ['type' => 'road_accident', 'evidence' => 'bus on side', 'likelihood' => 'likely']],
            'vehicles_involved' => ['bus', 'spaceship'], 'segmentation_concepts' => ['overturned bus', 'DROP TABLE;']];
    }

    public function test_strict_schema_lists_every_property_as_required(): void
    {
        $walk = function ($s) use (&$walk) {
            if (($s['type'] ?? null) === 'object') {
                $this->assertFalse($s['additionalProperties']);
                $this->assertEqualsCanonicalizing(array_keys($s['properties']), $s['required']);
                foreach ($s['properties'] as $p) $walk($p);
            }
            if (($s['type'] ?? null) === 'array') $walk($s['items']);
        };
        $walk(AnalysisSchema::schema());
    }

    public function test_cleaning_enforces_safety_and_enums(): void
    {
        $c = AnalysisValidator::clean($this->valid());
        $this->assertStringNotContainsString('01711000000', $c['summary']);
        $this->assertTrue($c['requires_human_review']); // people_at_risk yes forces review
        $this->assertSame(['bus'], $c['vehicles_involved']);
        $this->assertSame(['overturned bus'], $c['segmentation_concepts']);
        $this->assertSame('road_accident', AnalysisValidator::primaryCategory($c)); // cause preferred over injury on tie
    }

    public function test_invalid_output_is_rejected(): void
    {
        $this->expectException(AiProviderException::class);
        AnalysisValidator::clean(['summary' => 'x', 'severity' => 'apocalyptic', 'observations' => [], 'possible_hazards' => []]);
    }

    public function test_provider_retries_once_on_schema_failure(): void
    {
        config(['rescuelens.meta.api_key' => 'test', 'rescuelens.meta.max_retries' => 0]);
        Http::fake(['*' => Http::sequence()
            ->push(['output_text' => '{"oops":true}'])
            ->push(['output_text' => json_encode($this->valid()), 'model' => 'muse-spark-1.3'])]);
        $r = app(MetaVisionProvider::class)->analyze('img', 'image/jpeg', 'bn', null);
        $this->assertSame(2, $r['attempts']);
        Http::assertSentCount(2);
        Http::assertSent(fn ($req) => $req['text']['format']['strict'] === true && $req['reasoning']['effort'] === 'high');
    }
}
