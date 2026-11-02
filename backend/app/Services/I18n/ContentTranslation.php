<?php

namespace App\Services\I18n;

use App\Jobs\TranslateContent;
use App\Models\UiTranslation;

/**
 * Shows user generated titles in the reader's UI language. Translations are cached in ui_translations under
 * keys "c:<sha1>" and filled by a queued job, so lists never wait for the AI. The original text is always kept.
 */
final class ContentTranslation
{
    public static function key(string $text): string { return 'c:'.sha1($text); }

    /** @param array<int,array> $rows rows with a 'title' field; adds 'title_translated' when a cached translation exists. */
    public static function apply(array $rows, ?string $lang, string $field = 'title'): array
    {
        if (! $lang || ! preg_match('/^[a-z]{2,3}$/', $lang)) return $rows;
        $texts = array_values(array_unique(array_filter(array_map(fn ($r) => $r[$field] ?? null, $rows))));
        if (! $texts) return $rows;
        $keys = array_map([self::class, 'key'], $texts);
        $have = UiTranslation::where('lang', $lang)->whereIn('key', $keys)->pluck('value', 'key');
        $missing = [];
        foreach ($rows as &$r) {
            $t = $r[$field] ?? null;
            if (! $t) continue;
            $v = $have[self::key($t)] ?? null;
            if ($v !== null) { if ($v !== $t) $r[$field.'_translated'] = $v; } else { $missing[self::key($t)] = $t; }
        }
        if ($missing) TranslateContent::dispatch($lang, array_slice($missing, 0, 40, true))->afterResponse();
        return $rows;
    }
}
