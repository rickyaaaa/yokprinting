<?php

namespace App\Services\Reports;

final readonly class GeneratedReportFile
{
    public function __construct(
        public string $contents,
        public string $filename,
        public string $contentType,
    ) {}
}
