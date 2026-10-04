<?php

namespace App\Party\UI\Http\Output;

use App\Ledger\Application\Query\AccountView;
use App\Party\Domain\Model\Contact;
use App\Party\Domain\Model\Tercero;
use App\Shared\Domain\Fiscal\FiscalResponsibility;

/**
 * A whole tercero (§4.2: datos básicos, facturación y envío, responsabilidad fiscal, contactos, cuentas contables).
 * Also what a Ley 1581 export carries.
 */
final readonly class TerceroOutput
{
    /**
     * @param list<string>        $roles                  cliente, proveedor, empleado, otro
     * @param list<string>        $fiscalResponsibilities O-13, O-15, O-23, O-47, R-99-PN
     * @param list<PhoneOutput>   $phones
     * @param list<ContactOutput> $contacts
     */
    public function __construct(
        public string $id,
        public string $displayName,
        /** persona or empresa */
        public string $personType,
        /** cc, nit, ce, pasaporte, … */
        public string $identificationType,
        public string $identificationNumber,
        public ?string $checkDigit,
        public string $branchCode,
        public ?string $firstNames,
        public ?string $lastNames,
        public ?string $businessName,
        public ?string $tradeName,
        public ?string $city,
        public ?string $address,
        public array $phones,
        public ?string $billingContactName,
        public ?string $email,
        public ?string $mobile,
        public ?string $postalCode,
        /** responsable, no_responsable or simple */
        public ?string $vatRegime,
        public bool $billingContactIsPayer,
        public array $fiscalResponsibilities,
        public array $roles,
        public ?AccountRefOutput $receivableAccount,
        public ?AccountRefOutput $payableAccount,
        public array $contacts,
        public bool $active,
        /** ISO 8601 date-time when the personal data was erased on request (Ley 1581), else null */
        public ?string $erasedAt,
        public string $createdAt,
    ) {
    }

    public static function of(Tercero $t, ?AccountView $receivable = null, ?AccountView $payable = null): self
    {
        return new self(
            $t->id()->toRfc4122(),
            $t->displayName(),
            $t->personType()->value,
            $t->identificationType()->value,
            $t->identificationNumber(),
            $t->checkDigit(),
            $t->branchCode(),
            $t->firstNames(),
            $t->lastNames(),
            $t->businessName(),
            $t->tradeName(),
            $t->city(),
            $t->address(),
            array_map(static fn (array $p) => new PhoneOutput($p['indicative'], $p['number'], $p['extension'] ?? null), $t->phones()),
            $t->billingContactName(),
            $t->email(),
            $t->mobile(),
            $t->postalCode(),
            $t->vatRegime()?->value,
            $t->billingContactIsPayer(),
            array_map(static fn (FiscalResponsibility $r) => $r->value, $t->fiscalResponsibilities()),
            TerceroSummaryOutput::rolesOf($t),
            null === $receivable ? null : new AccountRefOutput($receivable->id, $receivable->code, $receivable->name),
            null === $payable ? null : new AccountRefOutput($payable->id, $payable->code, $payable->name),
            array_map(static fn (Contact $c) => new ContactOutput($c->id()->toRfc4122(), $c->name(), $c->email(), $c->phone()), $t->contacts()),
            $t->isActive(),
            $t->erasedAt()?->format(\DATE_ATOM),
            $t->createdAt()->format(\DATE_ATOM),
        );
    }
}
