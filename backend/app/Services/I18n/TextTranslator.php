<?php

namespace App\Services\I18n;

/** Machine translation of UI strings through the configured AI provider. Returns key => translated text; missing keys fall back to English. */
interface TextTranslator
{
    /** @param array<string,string> $strings */
    public function translate(array $strings, string $targetCode, string $targetName): array;
    public function name(): string;
}
