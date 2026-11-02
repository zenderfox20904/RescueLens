<?php

namespace App\Services\AI;

/** SAM 3.1 concept segmentation via Responses API. Parses <0f>id<|box;...|><|mask;...|> records (boxes only stored). */
class MetaSamProvider implements ImageSegmentationProvider
{
    public function __construct(private MetaModelClient $client) {}

    public function segment(string $imageBytes, string $mime, string $concept, int $width, int $height, ?string $demoScenario = null): array
    {
        $res = $this->client->responses([
            'model' => config('rescuelens.meta.segmentation_model'),
            'input' => [[
                'type' => 'message', 'role' => 'user',
                'content' => [
                    ['type' => 'input_text', 'text' => $concept],
                    ['type' => 'input_image', 'image_url' => 'data:'.$mime.';base64,'.base64_encode($imageBytes)],
                ],
            ]],
            'metadata' => ['include_confidence' => 'true', 'mask_encoding' => 'one_bit'],
        ]);
        return self::parse(MetaModelClient::outputText($res), $concept);
    }

    public static function parse(string $text, string $label): array
    {
        $out = [];
        preg_match_all('/<\|box;([^|]*)\|>/', $text, $m);
        foreach ($m[1] as $body) {
            $f = [];
            foreach (explode(';', $body) as $kv) {
                [$k, $v] = array_pad(explode('=', $kv, 2), 2, null);
                $f[$k] = $v;
            }
            if (! isset($f['x1'], $f['y1'], $f['x2'], $f['y2'], $f['w'], $f['h'])) {
                continue;
            }
            $out[] = ['label' => $label, 'x1' => (int) $f['x1'], 'y1' => (int) $f['y1'], 'x2' => (int) $f['x2'], 'y2' => (int) $f['y2'],
                'w' => (int) $f['w'], 'h' => (int) $f['h'], 'c' => isset($f['c']) ? (float) $f['c'] : null];
        }
        return $out;
    }

    public function mode(): string { return 'live'; }
}
