<?php

namespace App\Services\I18n;

use App\Models\Language;
use App\Models\UiTranslation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * en and bn are hand translated files (resources/i18n). Every other enabled language is machine translated on demand
 * from English through the AI provider, validated (placeholders must match) and cached in ui_translations.
 * A changed English string invalidates only that key (source_hash).
 */
class TranslationService
{
    public function __construct(private TextTranslator $translator) {}

    public static function source(string $code = 'en'): array
    {
        $p = resource_path("i18n/{$code}.json");
        return is_file($p) ? json_decode(file_get_contents($p), true) : [];
    }

    /** @return array{translations: array, machine: bool, provider: ?string, missing: int} */
    public function bundle(Language $lang): array
    {
        if ($lang->hand_translated && is_file(resource_path("i18n/{$lang->code}.json"))) {
            return ['translations' => self::source($lang->code), 'machine' => false, 'provider' => null, 'missing' => 0];
        }
        $flat = Arr::dot(self::source('en'));
        $flat = array_filter($flat, 'is_string');
        $have = UiTranslation::where('lang', $lang->code)->get()->keyBy('key');
        $todo = [];
        foreach ($flat as $k => $v) {
            if (! isset($have[$k]) || $have[$k]->source_hash !== sha1($v)) $todo[$k] = $v;
        }
        $provider = null;
        if ($todo) {
            Cache::lock("i18n:{$lang->code}", 300)->block(200, function () use ($lang, $todo, &$provider, $flat) {
                foreach (array_chunk($todo, 70, true) as $chunk) {
                    try {
                        $out = $this->translator->translate($chunk, $lang->code, $lang->english_name);
                    } catch (\Throwable $e) {
                        Log::warning('i18n.translate_failed', ['lang' => $lang->code, 'error' => class_basename($e)]);
                        break;
                    }
                    foreach ($chunk as $k => $src) {
                        $val = $out[$k] ?? null;
                        if (! is_string($val) || $val === '' || mb_strlen($val) > 600 || ! self::placeholdersMatch($src, $val)) continue;
                        UiTranslation::updateOrCreate(['lang' => $lang->code, 'key' => $k], ['value' => strip_tags($val), 'source_hash' => sha1($src), 'provider' => $this->translator->name()]);
                    }
                }
            });
            $have = UiTranslation::where('lang', $lang->code)->get()->keyBy('key');
        }
        $merged = [];
        $missing = 0;
        foreach ($flat as $k => $v) {
            $t = $have[$k] ?? null;
            if ($t && $t->source_hash === sha1($v)) { $merged[$k] = $t->value; $provider ??= $t->provider; } else { $missing++; }
        }
        return ['translations' => Arr::undot($merged), 'machine' => true, 'provider' => $provider, 'missing' => $missing];
    }

    public static function placeholdersMatch(string $a, string $b): bool
    {
        preg_match_all('/\{\{\s*\w+\s*\}\}/', $a, $x);
        preg_match_all('/\{\{\s*\w+\s*\}\}/', $b, $y);
        $n = fn ($m) => collect($m[0])->map(fn ($s) => preg_replace('/\s/', '', $s))->sort()->values()->all();
        return $n($x) === $n($y);
    }
}
