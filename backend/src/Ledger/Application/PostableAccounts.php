<?php

namespace App\Ledger\Application;

use App\Ledger\Domain\Error\AccountNotFound;
use App\Ledger\Domain\Error\InvalidPaymentMethod;
use App\Ledger\Domain\Error\InvalidTaxDefinition;
use App\Ledger\Application\Query\LedgerCatalog;
use Symfony\Component\Uid\Uuid;

/**
 * Checks that an account a form names is one of the company's chart that entries may post to (a subcuenta or an
 * auxiliar, active). Another company's account does not exist for this one.
 */
final class PostableAccounts
{
    public function __construct(private readonly LedgerCatalog $catalog)
    {
    }

    /**
     * @throws InvalidTaxDefinition on $field
     */
    public function forTax(Uuid $companyId, ?string $accountId, string $field): ?Uuid
    {
        return $this->check($companyId, $accountId, static fn (string $message) => new InvalidTaxDefinition($field, $message));
    }

    /**
     * @throws InvalidPaymentMethod on account_id
     */
    public function forPaymentMethod(Uuid $companyId, ?string $accountId): ?Uuid
    {
        return $this->check($companyId, $accountId, static fn (string $message) => new InvalidPaymentMethod('account_id', $message));
    }

    /**
     * @param callable(string): \Throwable $refuse
     */
    private function check(Uuid $companyId, ?string $accountId, callable $refuse): ?Uuid
    {
        if (null === $accountId || '' === $accountId) {
            return null;
        }
        if (!Uuid::isValid($accountId)) {
            throw $refuse('This account does not exist.');
        }
        $id = Uuid::fromString($accountId);
        try {
            $account = $this->catalog->account($companyId, $id);
        } catch (AccountNotFound) {
            throw $refuse('This account does not exist.');
        }
        if (!$account->postable) {
            throw $refuse('Choose a sub-account or an auxiliary account that is active.');
        }

        return $id;
    }
}
