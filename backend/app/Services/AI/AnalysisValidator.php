<?php

namespace App\Services\AI;

/** Server side validation and sanitization of untrusted model output. */
class AnalysisValidator
{
    public static function clean(array $r): array
    {
        $s = fn ($v, $max = 400) => is_string($v) ? mb_substr(trim(strip_tags($v)), 0, $max) : '';
        $list = fn ($v, $max = 8) => array_values(array_filter(array_map(fn ($x) => $s($x), array_slice(is_array($v) ? $v : [], 0, $max))));

        $obs = [];
        foreach (array_slice($r['observations'] ?? [], 0, 10) as $o) {
            if (! is_array($o) || ! $s($o['text'] ?? '')) continue;
            $obs[] = ['text' => $s($o['text']), 'kind' => in_array($o['kind'] ?? '', ['visible', 'inferred']) ? $o['kind'] : 'inferred'];
        }
        $haz = [];
        foreach (array_slice($r['possible_hazards'] ?? [], 0, 8) as $h) {
            if (! is_array($h) || ! in_array($h['type'] ?? '', AnalysisSchema::HAZARDS, true)) continue;
            $haz[] = ['type' => $h['type'], 'evidence' => $s($h['evidence'] ?? ''),
                'likelihood' => in_array($h['likelihood'] ?? '', ['possible', 'likely', 'clearly_visible']) ? $h['likelihood'] : 'possible'];
        }
        if (! $haz && ! $obs) {
            throw new AiProviderException('invalid_output', 'Model output had no usable observations');
        }
        $severity = in_array($r['severity'] ?? '', AnalysisSchema::SEVERITIES, true) ? $r['severity'] : 'moderate';
        $concepts = array_values(array_filter(array_map(
            fn ($c) => preg_match('/^[a-z ]{2,40}$/i', $c = $s($c, 40)) ? strtolower($c) : null,
            array_slice($r['segmentation_concepts'] ?? [], 0, 3))));

        // Safety: precautions may not contain phone numbers other than the official 999.
        $prec = array_map(fn ($p) => preg_replace('/\b(?!999\b)\d{3,}\b/u', '[number removed]', $p), $list($r['recommended_precautions'] ?? []));

        return [
            'summary' => $s($r['summary'] ?? '', 600),
            'suggested_title' => $s($r['suggested_title'] ?? '', 120),
            'observations' => $obs,
            'possible_hazards' => $haz,
            'severity' => $severity,
            'uncertainties' => $list($r['uncertainties'] ?? []),
            'recommended_precautions' => $prec,
            // Human review is forced for high/critical regardless of model output.
            'requires_human_review' => (bool) ($r['requires_human_review'] ?? true) || in_array($severity, ['high', 'critical']),
            'segmentation_concepts' => $concepts,
        ];
    }

    public static function primaryCategory(array $clean): string
    {
        $map = ['fire' => 'fire', 'smoke' => 'fire', 'flood' => 'flood', 'road_accident' => 'road_accident',
            'building_collapse' => 'building_collapse', 'electrical' => 'electrical', 'injury' => 'injury'];
        $rank = ['clearly_visible' => 3, 'likely' => 2, 'possible' => 1];
        $best = null;
        foreach ($clean['possible_hazards'] as $h) {
            if (isset($map[$h['type']]) && (! $best || $rank[$h['likelihood']] > $rank[$best['likelihood']])) $best = $h;
        }
        return $best ? $map[$best['type']] : 'other';
    }
}
