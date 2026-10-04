<?php

namespace App\Company\Application\Profile;

use App\Company\Application\Query\Companies;
use App\Company\Domain\Error\InvalidCompanyProfile;
use App\Company\Domain\Repository\CompanyRepository;
use App\Ledger\Application\Query\LedgerCatalog;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Fiscal\FiscalResponsibility;
use App\Shared\Domain\Fiscal\IdentificationType;
use App\Shared\Domain\Fiscal\VatRegime;
use Symfony\Component\Uid\Uuid;

final class UpdateCompanyProfileHandler implements CommandHandler
{
    public function __construct(
        private readonly CompanyRepository $companies,
        private readonly LedgerCatalog $ledger,
        private readonly AuditTrail $audit,
        private readonly Companies $views,
    ) {
    }

    public function __invoke(UpdateCompanyProfile $command): void
    {
        $company = $this->companies->get($command->companyId);
        /** @var list<array{field: string, message: string}> $violations */
        $violations = [];

        $type = IdentificationType::tryFrom($command->identificationType);
        if (null === $type) {
            $violations[] = ['field' => 'identification_type', 'message' => 'Choose a document type.'];
        }
        $number = $command->identificationNumber;
        if (null !== $type && $type->hasCheckDigit()) {
            $number = preg_replace('/\D/', '', $number) ?? '';
            if ('' === $number || \strlen($number) > 15) {
                $violations[] = ['field' => 'identification_number', 'message' => 'A NIT has only digits.'];
            }
        }
        if ([] === $violations && $this->companies->identificationTaken($number, $company->id())) {
            $violations[] = ['field' => 'identification_number', 'message' => 'A company with this identification is already registered.'];
        }

        $charge = $this->defaultTax($command->companyId, $command->defaultChargeTaxId, 'charge', 'default_charge_tax_id', 'Choose an active charge tax (IVA, impoconsumo).', $violations);
        $withholding = $this->defaultTax($command->companyId, $command->defaultWithholdingTaxId, 'withholding', 'default_withholding_tax_id', 'Choose an active withholding tax (retención).', $violations);
        if ([] !== $violations) {
            throw new InvalidCompanyProfile($violations);
        }
        \assert(null !== $type);

        $before = $this->snapshot($command->companyId);
        $company->updateProfile(
            $command->legalName,
            $command->tradeName,
            $type,
            $number,
            $command->checkDigit,
            $command->address,
            $command->city,
            $command->phone,
            $command->email,
            VatRegime::from($command->vatRegime),
            array_map(FiscalResponsibility::from(...), $command->fiscalResponsibilities),
            $charge,
            $withholding,
        );
        $this->audit->record($command->companyId, $command->userId, 'company.updated', 'company', $company->id(), ['from' => $before, 'to' => $this->snapshot($command->companyId)]);
    }

    /**
     * @param list<array{field: string, message: string}> $violations
     */
    private function defaultTax(Uuid $companyId, ?string $id, string $class, string $field, string $message, array &$violations): ?Uuid
    {
        if (null === $id || '' === $id) {
            return null;
        }
        try {
            $tax = $this->ledger->tax($companyId, Uuid::fromString($id));
        } catch (NotFound) {
            $violations[] = ['field' => $field, 'message' => $message];

            return null;
        }
        if ($tax->taxClass !== $class || !$tax->active) {
            $violations[] = ['field' => $field, 'message' => $message];

            return null;
        }

        return Uuid::fromString($id);
    }

    /** @return array<string, mixed> */
    private function snapshot(Uuid $companyId): array
    {
        return (array) $this->views->view($companyId);
    }
}
