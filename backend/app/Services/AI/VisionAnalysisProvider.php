<?php

namespace App\Services\AI;

interface VisionAnalysisProvider
{
    /** @return array{result: array, model: string} raw, unvalidated structured result */
    public function analyze(string $imageBytes, string $mime, string $language, ?string $userNote, ?string $demoScenario = null): array;

    public function mode(): string; // live | demo

    public function name(): string;
}
