<?php

namespace App\Ledger\Application\Provisioning;

use App\Ledger\Application\Query\LedgerCatalog;
use App\Ledger\Domain\Model\PaymentMethod;
use App\Ledger\Domain\Model\Tax;
use App\Ledger\Domain\Model\TaxClass;
use App\Ledger\Domain\Model\TaxKind;
use App\Ledger\Domain\Repository\PaymentMethodRepository;
use App\Ledger\Domain\Repository\TaxRepository;
use App\Shared\Application\Company\CompanyProvisioner;
use App\Shared\Domain\Model\PaymentKind;
use App\Shared\Domain\Totals\TaxCalculation;
use Symfony\Component\Uid\Uuid;

/**
 * A new company's taxes (§4.4, §9 Q3) and payment methods (§4.5), pointing at the PUC accounts of §5 and A.10. The chart
 * is seeded just before (priority 100) by the "ledger" item; an account the chart does not have yet is null, and
 * posting then falls back to the kind's posting rule.
 */
final class CatalogProvisioner implements CompanyProvisioner
{
    public function __construct(
        private readonly TaxRepository $taxes,
        private readonly PaymentMethodRepository $methods,
        private readonly LedgerCatalog $catalog,
    ) {
    }

    public function provision(Uuid $companyId): void
    {
        $account = fn (?string $code): ?Uuid => null === $code ? null : $this->catalog->accountIdByCode($companyId, $code);
        $tax = function (string $name, TaxClass $class, TaxKind $kind, string $rate, ?string $sales, ?string $purchases, TaxCalculation $calculation = TaxCalculation::Percentage, bool $active = true) use ($companyId, $account): void {
            $tax = new Tax($companyId, $name, $class, $kind, $calculation, $rate, $account($sales), $account($purchases), standard: true);
            if (!$active) {
                $tax->deactivate();
            }
            $this->taxes->add($tax);
        };

        $tax('Ninguno', TaxClass::Charge, TaxKind::None, '0.0000', null, null);
        $tax('IVA 19 %', TaxClass::Charge, TaxKind::Vat, '19.0000', '240805', '240810');
        $tax('IVA 5 %', TaxClass::Charge, TaxKind::Vat, '5.0000', '240805', '240810');
        $tax('IVA 0 %', TaxClass::Charge, TaxKind::Vat, '0.0000', '240805', '240810');
        $tax('IVA por servicios 19 %', TaxClass::Charge, TaxKind::Vat, '19.0000', '240805', '240810');
        // Impoconsumo: no standard sub-account in the PUC for it, so null until the company creates one (§9 Q7).
        $tax('Impoconsumo 8 %', TaxClass::Charge, TaxKind::Consumption, '8.0000', '249505', null);
        // Its value per unit depends on what the company sells: seeded inactive at 0 until the accountant sets it.
        $tax('Impoconsumo por valor', TaxClass::Charge, TaxKind::Consumption, '0.0000', '249505', null, TaxCalculation::PerUnit, active: false);

        $tax('Ninguno', TaxClass::Withholding, TaxKind::None, '0.0000', null, null);
        // Retención sufrida (sales) in 135515; practicada (purchases) by concept (§5).
        $tax('ReteFuente servicios 4 %', TaxClass::Withholding, TaxKind::IncomeWithholding, '4.0000', '135515', '236525');
        $tax('ReteFuente compras 2,5 %', TaxClass::Withholding, TaxKind::IncomeWithholding, '2.5000', '135515', '236540');
        $tax('ReteFuente honorarios 10 %', TaxClass::Withholding, TaxKind::IncomeWithholding, '10.0000', '135515', '236515');
        $tax('ReteFuente honorarios 11 %', TaxClass::Withholding, TaxKind::IncomeWithholding, '11.0000', '135515', '236515');
        $tax('ReteIVA 15 %', TaxClass::Withholding, TaxKind::VatWithholding, '15.0000', '135517', '236701');

        $method = fn (string $name, PaymentKind $kind, ?string $code) => $this->methods->add(new PaymentMethod($companyId, $name, $kind, $account($code), standard: true));
        $method('Efectivo', PaymentKind::Cash, '11050501');
        $method('Tarjeta débito', PaymentKind::Cash, '11100501');
        $method('Tarjeta crédito', PaymentKind::Cash, '11100501');
        $method('Transferencia', PaymentKind::Cash, '11100501');
        $method('Crédito', PaymentKind::Credit, null);
    }

    public static function getPriority(): int
    {
        return 50;
    }
}
