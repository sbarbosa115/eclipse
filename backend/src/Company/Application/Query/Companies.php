<?php

namespace App\Company\Application\Query;

use App\Company\Domain\Model\Company;
use App\Company\Domain\Repository\CompanyRepository;
use App\Shared\Domain\Fiscal\FiscalResponsibility;
use Symfony\Component\Uid\Uuid;

final class Companies
{
    public function __construct(private readonly CompanyRepository $companies)
    {
    }

    public function view(Uuid $companyId): CompanyView
    {
        return self::toView($this->companies->get($companyId));
    }

    private static function toView(Company $c): CompanyView
    {
        return new CompanyView(
            $c->id()->toRfc4122(),
            $c->legalName(),
            $c->tradeName(),
            $c->identificationType()->value,
            $c->identificationNumber(),
            $c->checkDigit(),
            $c->address(),
            $c->city(),
            $c->phone(),
            $c->email(),
            $c->logoId()?->toRfc4122(),
            $c->vatRegime()->value,
            array_map(static fn (FiscalResponsibility $r) => $r->value, $c->fiscalResponsibilities()),
            $c->defaultChargeTaxId()?->toRfc4122(),
            $c->defaultWithholdingTaxId()?->toRfc4122(),
            $c->resolutionWarningNumbers(),
            $c->resolutionWarningDays(),
            $c->manualInvoicingConfirmedAt()?->format(\DATE_ATOM),
        );
    }
}
