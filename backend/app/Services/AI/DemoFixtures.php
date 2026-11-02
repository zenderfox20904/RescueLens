<?php

namespace App\Services\AI;

/**
 * DEMO MODE ONLY. Deterministic sample outputs used when META_MODEL_API_KEY is missing.
 * These are not model outputs and the API marks every result with mode = "demo".
 */
class DemoFixtures
{
    public const SCENARIOS = ['road_accident', 'flood', 'fire', 'electrical', 'building_collapse'];

    public static function pick(?string $scenario, string $sha): string
    {
        if ($scenario && in_array($scenario, self::SCENARIOS, true)) {
            return $scenario;
        }
        return self::SCENARIOS[hexdec(substr($sha, 0, 4)) % count(self::SCENARIOS)];
    }

    public static function analysis(string $s, string $lang): array
    {
        $bn = $lang === 'bn';
        $common = [
            'uncertainties' => $bn
                ? ['এটি ডেমো নমুনা ফলাফল, ছবির প্রকৃত বিশ্লেষণ নয়।', 'একটি ছবি থেকে মানুষের অবস্থা বা ঝুঁকির মাত্রা নিশ্চিত করা যায় না।']
                : ['This is a DEMO sample result, not a real analysis of your photo.', 'A single photo cannot confirm the condition of people or the true level of risk.'],
            'requires_human_review' => true,
        ];
        $d = [
            'road_accident' => [
                'severity' => 'high',
                'suggested_title' => $bn ? 'মহাসড়কে যানবাহন সংঘর্ষ' : 'Vehicle collision on highway',
                'summary' => $bn ? 'রাস্তায় দুটি যানবাহনের সংঘর্ষের মতো দৃশ্য দেখা যাচ্ছে।' : 'The scene appears to show a collision between two vehicles on a road.',
                'observations' => [
                    ['text' => $bn ? 'একটি গাড়ির সামনের অংশ ক্ষতিগ্রস্ত দেখা যাচ্ছে।' : 'A car with visible front end damage.', 'kind' => 'visible'],
                    ['text' => $bn ? 'রাস্তায় ভাঙা অংশ ছড়িয়ে আছে।' : 'Debris scattered on the road.', 'kind' => 'visible'],
                    ['text' => $bn ? 'যান চলাচল বাধাগ্রস্ত হতে পারে।' : 'Traffic flow may be blocked.', 'kind' => 'inferred'],
                ],
                'possible_hazards' => [
                    ['type' => 'road_accident', 'evidence' => $bn ? 'ক্ষতিগ্রস্ত যানবাহন' : 'Damaged vehicles', 'likelihood' => 'clearly_visible'],
                    ['type' => 'injury', 'evidence' => $bn ? 'সংঘর্ষে আহত হওয়ার সম্ভাবনা' : 'Collisions can cause injuries; none confirmed', 'likelihood' => 'possible'],
                    ['type' => 'debris', 'evidence' => $bn ? 'রাস্তায় ধ্বংসাবশেষ' : 'Debris on the road', 'likelihood' => 'likely'],
                ],
                'recommended_precautions' => $bn
                    ? ['নিরাপদ দূরত্বে থাকুন এবং অন্য যানবাহনকে সতর্ক করুন।', 'কেউ আহত মনে হলে জাতীয় জরুরি সেবা ৯৯৯ এ কল করুন।', 'প্রশিক্ষণ না থাকলে আহত ব্যক্তিকে সরাবেন না।']
                    : ['Stay at a safe distance and warn other traffic.', 'If anyone may be hurt, call National Emergency Service 999.', 'Do not move injured people unless you are trained.'],
                'segmentation_concepts' => ['car', 'debris'],
                'regions' => [['car', .18, .35, .62, .82], ['debris', .55, .70, .85, .92]],
            ],
            'flood' => [
                'severity' => 'moderate',
                'suggested_title' => $bn ? 'রাস্তায় জলাবদ্ধতা' : 'Waterlogged street',
                'summary' => $bn ? 'রাস্তা পানিতে ঢেকে গেছে বলে মনে হচ্ছে।' : 'The street appears to be covered by standing water.',
                'observations' => [
                    ['text' => $bn ? 'রাস্তার উপর পানি জমে আছে।' : 'Water covering the road surface.', 'kind' => 'visible'],
                    ['text' => $bn ? 'রিকশা ও যানবাহন পানির মধ্যে দিয়ে চলছে।' : 'Rickshaws and vehicles moving through water.', 'kind' => 'visible'],
                    ['text' => $bn ? 'পানির গভীরতা ছবি থেকে নির্ধারণ করা সম্ভব নয়।' : 'Water depth cannot be determined from the image.', 'kind' => 'inferred'],
                ],
                'possible_hazards' => [
                    ['type' => 'flood', 'evidence' => $bn ? 'জমে থাকা পানি' : 'Standing water on the street', 'likelihood' => 'clearly_visible'],
                    ['type' => 'electrical', 'evidence' => $bn ? 'পানির কাছে বৈদ্যুতিক খুঁটি' : 'Utility poles near water; condition unknown', 'likelihood' => 'possible'],
                ],
                'recommended_precautions' => $bn
                    ? ['পানির গভীরতা ও স্রোত না জেনে পানিতে নামবেন না।', 'বৈদ্যুতিক খুঁটি ও তার থেকে দূরে থাকুন।', 'স্থানীয় কর্তৃপক্ষের নির্দেশনা অনুসরণ করুন।']
                    : ['Do not enter the water; depth and current are unknown.', 'Keep away from electric poles and wires.', 'Follow guidance from local authorities.'],
                'segmentation_concepts' => ['water', 'rickshaw'],
                'regions' => [['water', .0, .55, 1.0, 1.0], ['rickshaw', .30, .40, .55, .80]],
            ],
            'fire' => [
                'severity' => 'critical',
                'suggested_title' => $bn ? 'ভবনে আগুন' : 'Building fire',
                'summary' => $bn ? 'একটি ভবন থেকে আগুন ও ঘন ধোঁয়া বের হচ্ছে বলে দেখা যাচ্ছে।' : 'Flames and thick smoke appear to be coming from a building.',
                'observations' => [
                    ['text' => $bn ? 'জানালা দিয়ে আগুনের শিখা দেখা যাচ্ছে।' : 'Flames visible through windows.', 'kind' => 'visible'],
                    ['text' => $bn ? 'ঘন কালো ধোঁয়া উপরে উঠছে।' : 'Thick dark smoke rising.', 'kind' => 'visible'],
                    ['text' => $bn ? 'ভেতরে মানুষ থাকতে পারে।' : 'People may be inside.', 'kind' => 'inferred'],
                ],
                'possible_hazards' => [
                    ['type' => 'fire', 'evidence' => $bn ? 'দৃশ্যমান আগুন' : 'Visible flames', 'likelihood' => 'clearly_visible'],
                    ['type' => 'smoke', 'evidence' => $bn ? 'ঘন ধোঁয়া' : 'Dense smoke', 'likelihood' => 'clearly_visible'],
                    ['type' => 'building_collapse', 'evidence' => $bn ? 'দীর্ঘ আগুনে কাঠামো দুর্বল হতে পারে' : 'Prolonged fire can weaken structures; not assessable from photo', 'likelihood' => 'possible'],
                ],
                'recommended_precautions' => $bn
                    ? ['ভবন থেকে দূরে সরে যান এবং ধোঁয়া এড়িয়ে চলুন।', 'ফায়ার সার্ভিসের জন্য জাতীয় জরুরি সেবা ৯৯৯ এ কল করুন।', 'আগুন নেভাতে ভেতরে প্রবেশ করবেন না।']
                    : ['Move away from the building and avoid the smoke.', 'Call National Emergency Service 999 for the fire service.', 'Do not go inside to fight the fire.'],
                'segmentation_concepts' => ['fire', 'smoke'],
                'regions' => [['fire', .35, .30, .70, .65], ['smoke', .20, .0, .90, .35]],
            ],
            'electrical' => [
                'severity' => 'high',
                'suggested_title' => $bn ? 'রাস্তায় বৈদ্যুতিক তার পড়ে আছে' : 'Fallen electric wire on street',
                'summary' => $bn ? 'একটি বৈদ্যুতিক তার রাস্তায় পড়ে আছে বলে মনে হচ্ছে।' : 'An electric cable appears to be lying across the street.',
                'observations' => [
                    ['text' => $bn ? 'খুঁটি থেকে একটি তার নিচে ঝুলে আছে।' : 'A cable hanging down from a pole.', 'kind' => 'visible'],
                    ['text' => $bn ? 'তারটি সচল কিনা ছবি থেকে বোঝা সম্ভব নয়।' : 'Whether the wire is live cannot be known from the image.', 'kind' => 'inferred'],
                ],
                'possible_hazards' => [
                    ['type' => 'electrical', 'evidence' => $bn ? 'পতিত বৈদ্যুতিক তার' : 'Fallen cable near pedestrians', 'likelihood' => 'clearly_visible'],
                ],
                'recommended_precautions' => $bn
                    ? ['তারটিকে সচল ধরে নিন এবং কমপক্ষে কয়েক মিটার দূরে থাকুন।', 'অন্যদের, বিশেষ করে শিশুদের সতর্ক করুন।', 'বিদ্যুৎ বিতরণ কর্তৃপক্ষ বা ৯৯৯ এ জানান।']
                    : ['Treat the wire as live and stay well away.', 'Warn others, especially children.', 'Report to the power distribution authority or 999.'],
                'segmentation_concepts' => ['power line', 'utility pole'],
                'regions' => [['power line', .10, .45, .90, .75], ['utility pole', .05, .05, .20, .95]],
            ],
            'building_collapse' => [
                'severity' => 'critical',
                'suggested_title' => $bn ? 'ভবনের আংশিক ধস' : 'Partial building collapse',
                'summary' => $bn ? 'একটি ভবনের অংশ ধসে পড়েছে বলে মনে হচ্ছে।' : 'Part of a building appears to have collapsed.',
                'observations' => [
                    ['text' => $bn ? 'ধ্বংসস্তূপ ও ভাঙা কংক্রিট দেখা যাচ্ছে।' : 'Rubble and broken concrete visible.', 'kind' => 'visible'],
                    ['text' => $bn ? 'ধ্বংসস্তূপের নিচে মানুষ থাকতে পারে।' : 'People could be trapped; not visible.', 'kind' => 'inferred'],
                ],
                'possible_hazards' => [
                    ['type' => 'building_collapse', 'evidence' => $bn ? 'ধসে পড়া কাঠামো' : 'Collapsed structure', 'likelihood' => 'clearly_visible'],
                    ['type' => 'injury', 'evidence' => $bn ? 'আটকে পড়ার সম্ভাবনা' : 'Possible trapped people', 'likelihood' => 'possible'],
                    ['type' => 'debris', 'evidence' => $bn ? 'ধ্বংসাবশেষ' : 'Rubble', 'likelihood' => 'clearly_visible'],
                ],
                'recommended_precautions' => $bn
                    ? ['ক্ষতিগ্রস্ত ভবনে প্রবেশ করবেন না।', 'জাতীয় জরুরি সেবা ৯৯৯ এ কল করুন।', 'উদ্ধারকর্মীদের জন্য পথ খালি রাখুন।']
                    : ['Do not enter the damaged building.', 'Call National Emergency Service 999.', 'Keep access clear for rescue teams.'],
                'segmentation_concepts' => ['rubble'],
                'regions' => [['rubble', .10, .40, .90, .95]],
            ],
        ][$s];
        return array_merge($common, $d);
    }
}
