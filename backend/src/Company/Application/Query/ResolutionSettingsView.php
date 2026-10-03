<?php

namespace App\Company\Application\Query;

final readonly class ResolutionSettingsView
{
    public function __construct(
        public ?ResolutionView $resolution,
        public ResolutionStatusView $status,
        public ?string $manualInvoicingConfirmedAt,
    ) {
    }
}
