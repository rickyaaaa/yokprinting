<?php

namespace App\Services\Invoices;

final readonly class GeneratedInvoicePdf
{
    public function __construct(
        public string $contents,
        public string $filename,
    ) {}
}
