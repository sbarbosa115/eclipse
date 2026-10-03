<?php

namespace App\Company\Application\Profile;

use Symfony\Component\Uid\Uuid;

/** The owner confirms the company holds the DIAN permission to invoice manually (§4.1). */
final readonly class ConfirmManualInvoicing
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $userId,
    ) {
    }
}
