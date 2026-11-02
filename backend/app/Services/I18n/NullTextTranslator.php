<?php

namespace App\Services\I18n;

/** Used in DEMO mode (no AI key): nothing is machine translated, English is served and labeled as such. */
class NullTextTranslator implements TextTranslator
{
    public function translate(array $strings, string $targetCode, string $targetName): array { return []; }
    public function name(): string { return 'none'; }
}
