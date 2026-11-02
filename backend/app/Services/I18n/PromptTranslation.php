<?php

namespace App\Services\I18n;

final class PromptTranslation
{
    public static function system(string $code, string $name): string
    {
        return "You translate the user interface of RescueLens, an emergency incident reporting app, from English to {$name} ({$code}). "
            ."Return ONE JSON object with exactly the same keys. Keep placeholders like {{n}} and {{s}} unchanged, keep emoji, keep brand names RescueLens, Meta, SAM, Facebook, Google unchanged. "
            .'Use short, clear, calm wording suitable for emergencies. Never add claims that a place is safe. No markdown.';
    }

    public static function parse(string $raw): array
    {
        $raw = preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($raw));
        $j = json_decode($raw, true);
        return is_array($j) ? $j : [];
    }
}
