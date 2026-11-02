<?php

namespace App\Services\AI;

interface ImageSegmentationProvider
{
    /**
     * Find instances of one short noun phrase concept.
     * @return list<array{label:string,x1:int,y1:int,x2:int,y2:int,w:int,h:int,c:?float}>
     */
    public function segment(string $imageBytes, string $mime, string $concept, int $width, int $height, ?string $demoScenario = null): array;

    public function mode(): string;
}
