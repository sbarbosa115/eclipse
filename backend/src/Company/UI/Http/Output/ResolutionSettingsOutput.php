<?php

namespace App\Company\UI\Http\Output;

use App\Company\Application\Query\ResolutionSettingsView;

/** The Resolución tab: the resolution (null before it is set up), its status and the manual-mode confirmation. */
final readonly class ResolutionSettingsOutput
{
    public function __construct(
        public ?ResolutionOutput $resolution,
        public ResolutionStatusOutput $status,
        /** When the owner confirmed the DIAN permission to invoice manually, or null: modalidad manual is not selectable. */
        public ?string $manualInvoicingConfirmedAt,
    ) {
    }

    public static function of(ResolutionSettingsView $v): self
    {
        return new self(null === $v->resolution ? null : ResolutionOutput::of($v->resolution), ResolutionStatusOutput::of($v->status), $v->manualInvoicingConfirmedAt);
    }
}
