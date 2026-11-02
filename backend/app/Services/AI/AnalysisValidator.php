<?php

namespace App\Services\AI;

/** Server side validation and sanitization of untrusted model output. Throws on schema failure so the provider can retry once. */
class AnalysisValidator
{
    /** Fields that must be present with the right type, otherwise the output is rejected. */
    public static function assertShape(mixed $r): void
    {
        if (! is_array($r)) throw new AiProviderException('invalid_output', 'Model output was not a JSON object');
        foreach (['summary' => 'string', 'severity' => 'string', 'possible_hazards' => 'array', 'observations' => 'array'] as $k => $type) {
            if (! isset($r[$k]) || gettype($r[$k]) !== $type) throw new AiProviderException('invalid_output', "Model output field {$k} missing or wrong type");
        }
        if (! in_array($r['severity'], AnalysisSchema::SEVERITIES, true)) throw new AiProviderException('invalid_output', 'Unknown severity');
    }

    public static function clean(array $r): array
    {
        self::assertShape($r);
        $s = fn ($v, $max = 400) => is_string($v) ? mb_substr(trim(strip_tags($v)), 0, $max) : '';
        $list = fn ($v, $max = 8) => array_values(array_filter(array_map(fn ($x) => $s($x), array_slice(is_array($v) ? $v : [], 0, $max))));
        $noNumbers = fn ($p) => preg_replace('/\b(?!999\b)\d{3,}\b/u', '[number removed]', $p);

        $obs = [];
        foreach (array_slice($r['observations'] ?? [], 0, 10) as $o) {
            if (! is_array($o) || ! $s($o['text'] ?? '')) continue;
            $obs[] = ['text' => $s($o['text']), 'kind' => in_array($o['kind'] ?? '', ['visible', 'inferred']) ? $o['kind'] : 'inferred'];
        }
        $haz = [];
        foreach (array_slice($r['possible_hazards'] ?? [], 0, 8) as $h) {
            if (! is_array($h) || ! in_array($h['type'] ?? '', AnalysisSchema::HAZARDS, true)) continue;
            $haz[] = ['type' => $h['type'], 'evidence' => $s($h['evidence'] ?? ''),
                'likelihood' => in_array($h['likelihood'] ?? '', AnalysisSchema::LIKELIHOOD) ? $h['likelihood'] : 'possible'];
        }
        if (! $haz && ! $obs) {
            throw new AiProviderException('invalid_output', 'Model output had no usable observations');
        }
        $severity = $r['severity'];
        $risk = in_array($r['people_at_risk'] ?? '', ['yes', 'possible', 'no_visible', 'unclear'], true) ? $r['people_at_risk'] : 'unclear';
        $concepts = array_values(array_unique(array_filter(array_map(
            fn ($c) => preg_match('/^[a-z ]{2,40}$/i', $c = $s($c, 40)) ? strtolower($c) : null,
            array_slice($r['segmentation_concepts'] ?? [], 0, 3)))));
        $vehicles = array_values(array_unique(array_filter(is_array($r['vehicles_involved'] ?? null) ? $r['vehicles_involved'] : [],
            fn ($v) => in_array($v, AnalysisSchema::VEHICLES, true))));

        return [
            'is_incident_scene' => (bool) ($r['is_incident_scene'] ?? true),
            'summary' => $noNumbers($s($r['summary'], 600)),
            'summary_en' => $noNumbers($s($r['summary_en'] ?? '', 600)),
            'suggested_title' => $s($r['suggested_title'] ?? '', 120),
            'suggested_title_en' => $s($r['suggested_title_en'] ?? '', 120),
            'observations' => $obs,
            'possible_hazards' => $haz,
            'vehicles_involved' => $vehicles,
            'people_at_risk' => $risk,
            'people_at_risk_reason' => $s($r['people_at_risk_reason'] ?? '', 300),
            'severity' => $severity,
            'severity_reason' => $s($r['severity_reason'] ?? '', 300),
            'uncertainties' => $list($r['uncertainties'] ?? []),
            // Safety: precautions may not contain phone numbers other than the official 999.
            'recommended_precautions' => array_map($noNumbers, $list($r['recommended_precautions'] ?? [])),
            // Human review is forced for high/critical or people at risk regardless of model output.
            'requires_human_review' => (bool) ($r['requires_human_review'] ?? true) || in_array($severity, ['high', 'critical']) || in_array($risk, ['yes', 'possible']),
            'segmentation_concepts' => $concepts,
        ];
    }

    public static function primaryCategory(array $clean): string
    {
        $map = ['fire' => 'fire', 'smoke' => 'fire', 'flood' => 'flood', 'road_accident' => 'road_accident', 'building_collapse' => 'building_collapse',
            'electrical' => 'electrical', 'injury' => 'injury', 'crowd_stampede' => 'crowd_stampede', 'landslide' => 'landslide', 'earthquake' => 'earthquake', 'wildfire' => 'wildfire', 'tsunami' => 'tsunami', 'cyclone_storm' => 'cyclone_storm', 'snow_ice' => 'snow_ice'];
        $rank = ['clearly_visible' => 3, 'likely' => 2, 'possible' => 1];
        $best = null;
        foreach ($clean['possible_hazards'] as $h) {
            // Injury is usually a consequence; prefer the cause on ties.
            if (isset($map[$h['type']]) && (! $best || $rank[$h['likelihood']] > $rank[$best['likelihood']] || ($rank[$h['likelihood']] === $rank[$best['likelihood']] && $best['type'] === 'injury'))) $best = $h;
        }
        return $best ? $map[$best['type']] : 'other';
    }
}
