<?php

namespace App\Company\Application\Numbering;

/**
 * A number handed to a document at emission: its prefix, its place in the series and how it is printed ("RC-12").
 */
final readonly class DocumentNumber
{
    public function __construct(
        public string $prefix,
        public int $sequence,
    ) {
    }

    public function formatted(): string
    {
        return '' === $this->prefix ? (string) $this->sequence : $this->prefix.'-'.$this->sequence;
    }
}
