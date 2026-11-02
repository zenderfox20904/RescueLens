<?php

namespace App\Jobs;

use App\Models\Language;
use App\Models\UiTranslation;
use App\Services\I18n\TextTranslator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TranslateContent implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public string $lang, public array $texts) {}

    public function handle(TextTranslator $t): void
    {
        $l = Language::where('code', $this->lang)->first();
        if (! $l || $t->name() === 'none') return;
        $out = $t->translate($this->texts, $l->code, $l->english_name.'. If a string is already in '.$l->english_name.', return it unchanged');
        foreach ($this->texts as $k => $src) {
            $v = $out[$k] ?? null;
            if (! is_string($v) || $v === '' || mb_strlen($v) > 400) continue;
            UiTranslation::updateOrCreate(['lang' => $l->code, 'key' => $k], ['value' => strip_tags($v), 'source_hash' => sha1($src), 'provider' => $t->name()]);
        }
    }
}
