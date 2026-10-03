<?php

namespace App\Access\Domain\Model;

/**
 * What a user may do in their company (§8): the owner everything, the billing user the commercial documents, the
 * accountant the books (and only reads the documents).
 */
enum Role: string
{
    case Owner = 'owner';
    case Billing = 'billing';
    case Accountant = 'accountant';

    /** The Symfony role the firewall grants (security.yaml's role_hierarchy). */
    public function securityRole(): string
    {
        return 'ROLE_'.strtoupper($this->value);
    }
}
