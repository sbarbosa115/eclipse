<?php

namespace App\Tests\Unit\Purchasing;

use App\Ledger\Application\Posting\EntryDraft;
use App\Ledger\Application\Posting\EntryLine;
use App\Ledger\Application\Posting\Side;
use App\Purchasing\Application\Posting\ProductAccounting;
use App\Purchasing\Application\Posting\PurchaseInvoiceEntry;
use App\Purchasing\Domain\Model\PurchaseInvoice;
use App\Purchasing\Domain\Model\PurchaseLineDraft;
use App\Purchasing\Domain\Model\PurchasePaymentDraft;
use App\Shared\Domain\Accounting\PostingConcept as C;
use App\Shared\Domain\Model\PaymentKind;
use App\Shared\Domain\Model\TaxSnapshot;
use App\Shared\Domain\Money\Money;
use App\Shared\Domain\Money\Quantity;
use App\Shared\Domain\Money\Rate;
use App\Shared\Domain\Money\UnitPrice;
use App\Shared\Domain\Totals\TaxCalculation;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * PRD Appendix A.3: what an emitted factura de compra / gasto posts. Débitos: each line's subtotal net of its discount
 * to its account (the one chosen, else the product's, else 6205 compras de mercancías for a producto and gasto por
 * defecto for a servicio), impoconsumo added to it (a cost on purchases), IVA descontable to the tax's purchase
 * account or the concept. Créditos: each retención practicada to its account or its kind's concept, 2205 proveedores
 * (the supplier) per crédito payment and the method's account per contado payment.
 */
final class PurchaseInvoicePostingTest extends TestCase
{
    private Uuid $company;
    private Uuid $supplier;
    private Uuid $user;
    /** @var array<string, string> account id → name, to read the entry */
    private array $names = [];

    protected function setUp(): void
    {
        $this->company = Uuid::v7();
        $this->supplier = Uuid::v7();
        $this->user = Uuid::v7();
    }

    private function account(string $name): Uuid
    {
        $id = Uuid::v7();
        $this->names[$id->toRfc4122()] = $name;

        return $id;
    }

    private static function tax(string $kind, string $rate, ?Uuid $account = null, TaxCalculation $calculation = TaxCalculation::Percentage): TaxSnapshot
    {
        return new TaxSnapshot(Uuid::v7(), $kind.' '.$rate, $kind, $calculation, $rate, $account);
    }

    private static function line(?Uuid $product, ?Uuid $account, string $quantity, string $price, string $discount = '0', ?TaxSnapshot $charge = null, ?TaxSnapshot $withholding = null): PurchaseLineDraft
    {
        return new PurchaseLineDraft($product, $account, 'Línea', Quantity::of($quantity), UnitPrice::of($price), Rate::of($discount), $charge ?? TaxSnapshot::none(), $withholding ?? TaxSnapshot::none());
    }

    private function credit(string $amount): PurchasePaymentDraft
    {
        return new PurchasePaymentDraft(Uuid::v7(), 'Crédito', PaymentKind::Credit, null, Money::of($amount), new \DateTimeImmutable('2026-10-31'));
    }

    private function cash(string $amount, Uuid $account): PurchasePaymentDraft
    {
        return new PurchasePaymentDraft(Uuid::v7(), 'Efectivo', PaymentKind::Cash, $account, Money::of($amount), null);
    }

    /**
     * @param list<PurchaseLineDraft>    $lines
     * @param list<PurchasePaymentDraft> $payments
     * @param array<string, ProductAccounting> $products
     */
    private function post(array $lines, array $payments, array $products = []): EntryDraft
    {
        $invoice = new PurchaseInvoice($this->company, $this->supplier, 'Servicios Andinos S.A.S.', 'FAC-881', new \DateTimeImmutable('2026-10-01'), $this->user, new \DateTimeImmutable('2026-10-01'));
        $invoice->revise($this->supplier, 'Servicios Andinos S.A.S.', 'FAC-881', new \DateTimeImmutable('2026-10-01'), null, null, $lines, $payments);
        $invoice->emit('FC', 3, $this->user, new \DateTimeImmutable('2026-10-02'), new \DateTimeImmutable('2026-10-02'));

        return PurchaseInvoiceEntry::draft($invoice, $products, $this->user);
    }

    /**
     * @return list<array{string, string, string, bool}> side, where (concept or account name), amount, carries the supplier
     */
    private function movements(EntryDraft $draft): array
    {
        return array_map(fn (EntryLine $l) => [
            $l->side->value,
            null !== $l->accountId ? $this->names[$l->accountId->toRfc4122()] ?? '?' : ($l->concept?->value ?? '?'),
            $l->amount->toString(),
            $l->terceroId?->equals($this->supplier) ?? false,
        ], $draft->lines);
    }

    private static function assertBalanced(EntryDraft $draft): void
    {
        $debit = $credit = Money::zero();
        foreach ($draft->lines as $line) {
            Side::Debit === $line->side ? $debit = $debit->plus($line->amount) : $credit = $credit->plus($line->amount);
        }
        self::assertSame($debit->toString(), $credit->toString(), 'Σ débitos = Σ créditos (§5 invariant 1).');
    }

    public function testAnExpenseAccountLineWithIvaAndReteFuenteOnCreditPostsAsA3(): void
    {
        $services = $this->account('513595 Otros servicios');
        $draft = $this->post(
            [self::line(null, $services, '1', '1000000', charge: self::tax('iva', '19.0000'), withholding: self::tax('retefuente', '4.0000'))],
            [$this->credit('1150000.00')],
        );

        self::assertSame([
            ['debit', '513595 Otros servicios', '1000000.00', false],
            ['debit', C::VatDeductible->value, '190000.00', false],
            ['credit', C::WithholdingPracticed->value, '40000.00', true],
            ['credit', C::Payables->value, '1150000.00', true],
        ], $this->movements($draft), 'Dr gasto, Dr IVA descontable, Cr 2365, Cr 2205 (acceptance criterion 5).');
        self::assertBalanced($draft);
        self::assertSame('purchase_invoice', $draft->sourceType);
        self::assertSame('FC-3', $draft->sourceNumber);
        self::assertSame('2026-10-01', $draft->date->format('Y-m-d'), 'Posted on the invoice\'s date.');
    }

    public function testTheDiscountIsNettedOnTheLineAndTheTaxesUseTheirOwnPurchaseAccounts(): void
    {
        $rent = $this->account('512010 Arrendamientos');
        $ivaAccount = $this->account('240810 IVA descontable');
        $reteAccount = $this->account('236530 Arrendamientos');
        $draft = $this->post(
            [self::line(null, $rent, '2', '500000', '10', self::tax('iva', '19.0000', $ivaAccount), self::tax('retefuente', '3.5000', $reteAccount))],
            [$this->credit('1039500.00')],
        );

        self::assertSame([
            ['debit', '512010 Arrendamientos', '900000.00', false],
            ['debit', '240810 IVA descontable', '171000.00', false],
            ['credit', '236530 Arrendamientos', '31500.00', true],
            ['credit', C::Payables->value, '1039500.00', true],
        ], $this->movements($draft), 'No discount account on purchases: the supplier\'s invoice already shows it (A.3).');
        self::assertBalanced($draft);
    }

    public function testAProductPostsToItsExpenseAccountElseToPurchasesOfGoodsOrTheDefaultExpense(): void
    {
        $withAccount = Uuid::v7();
        $goods = Uuid::v7();
        $service = Uuid::v7();
        $stationery = $this->account('519530 Útiles y papelería');
        $draft = $this->post(
            [
                self::line($withAccount, null, '1', '100000'),
                self::line($goods, null, '10', '20000'),
                self::line($service, null, '1', '50000'),
            ],
            [$this->credit('350000.00')],
            [
                $withAccount->toRfc4122() => new ProductAccounting(true, $stationery),
                $goods->toRfc4122() => new ProductAccounting(true, null),
                $service->toRfc4122() => new ProductAccounting(false, null),
            ],
        );

        self::assertSame([
            ['debit', '519530 Útiles y papelería', '100000.00', false],
            ['debit', C::MerchandisePurchases->value, '200000.00', false],
            ['debit', C::DefaultExpense->value, '50000.00', false],
            ['credit', C::Payables->value, '350000.00', true],
        ], $this->movements($draft), 'Producto → 6205 (sistema periódico), servicio → gasto por defecto, unless the product names its account.');
        self::assertBalanced($draft);
    }

    public function testImpoconsumoOnAPurchaseIsPartOfTheCost(): void
    {
        $meals = $this->account('519525 Elementos de aseo y cafetería');
        $draft = $this->post(
            [self::line(null, $meals, '1', '100000', charge: self::tax('impoconsumo', '8.0000'))],
            [$this->credit('108000.00')],
        );

        self::assertSame([
            ['debit', '519525 Elementos de aseo y cafetería', '108000.00', false],
            ['credit', C::Payables->value, '108000.00', true],
        ], $this->movements($draft), 'Impoconsumo is not descontable: it is added to the line\'s debit.');
        self::assertBalanced($draft);
    }

    public function testEachRetencionGoesToItsKindsConceptWhenTheTaxHasNoAccount(): void
    {
        $fees = $this->account('511025 Asesoría jurídica');
        $draft = $this->post(
            [
                self::line(null, $fees, '1', '1000000', withholding: self::tax('reteiva', '15.0000')),
                self::line(null, $fees, '1', '1000000', withholding: self::tax('reteica', '0.9660')),
            ],
            [$this->credit('1840340.00')],
        );

        self::assertSame([
            ['debit', '511025 Asesoría jurídica', '2000000.00', false],
            ['credit', C::VatWithholdingPracticed->value, '150000.00', true],
            ['credit', C::IcaWithholdingPracticed->value, '9660.00', true],
            ['credit', C::Payables->value, '1840340.00', true],
        ], $this->movements($draft), 'Lines on the same account are posted together; ReteIVA → 2367, ReteICA → 2368.');
        self::assertBalanced($draft);
    }

    public function testEachPaymentLineIsCreditedToItsAccountOrToTheSupplier(): void
    {
        $services = $this->account('513595 Otros servicios');
        $cashBox = $this->account('11050501 Caja general');
        $bank = $this->account('11100501 Bancos');
        $draft = $this->post(
            [self::line(null, $services, '1', '1000000', charge: self::tax('iva', '19.0000'))],
            [$this->cash('190000.00', $cashBox), $this->cash('500000.00', $bank), $this->credit('250000.00'), $this->credit('250000.00')],
        );

        self::assertSame([
            ['debit', '513595 Otros servicios', '1000000.00', false],
            ['debit', C::VatDeductible->value, '190000.00', false],
            ['credit', '11050501 Caja general', '190000.00', false],
            ['credit', '11100501 Bancos', '500000.00', false],
            ['credit', C::Payables->value, '250000.00', true],
            ['credit', C::Payables->value, '250000.00', true],
        ], $this->movements($draft), 'Cr the method\'s account per contado line; Cr proveedores (the supplier) per crédito line.');
        self::assertBalanced($draft);
    }

    public function testRoundingSharesStillBalanceToTheCent(): void
    {
        $a = $this->account('513595 Otros servicios');
        $b = $this->account('519595 Otros');
        $iva = self::tax('iva', '19.0000');
        $draft = $this->post(
            [self::line(null, $a, '1', '0.3333', charge: $iva), self::line(null, $b, '1', '0.3333', charge: $iva), self::line(null, $a, '1', '0.3333', charge: $iva)],
            [$this->credit('1.19')],
        );

        self::assertBalanced($draft);
    }
}
