<?php

namespace App\Company\Application\Numbering;

use Symfony\Component\Uid\Uuid;

/**
 * A sales invoice's two numbers (§4.8): the resolution's (authorised, printed) and the internal consecutive.
 */
final readonly class AuthorisedNumber
{
    public function __construct(
        public Uuid $resolutionId,
        public DocumentNumber $authorised,
        public DocumentNumber $internal,
    ) {
    }
}
