<?php

namespace App\Tests\Unit\Sales;

use App\Sales\Domain\Model\InvoiceLineDraft;
use App\Sales\Domain\Model\InvoicePaymentDraft;
use App\Sales\Domain\Model\SalesInvoice;
use App\Shared\Domain\Model\PaymentKind;
use App\Shared\Domain\Model\TaxSnapshot;
use App\Shared\Domain\Money\Money;
use App\Shared\Domain\Money\Quantity;
use App\Shared\Domain\Money\Rate;
use App\Shared\Domain\Money\UnitPrice;
use App\Shared\Domain\Totals\TaxCalculation;
use Symfony\Component\Uid\Uuid;

/** Sales invoices built in memory, the way the draft handlers build them, for the domain's unit tests. */
trait BuildsInvoices
{
    private static ?Uuid $company = null;
    private static ?Uuid $client = null;
    private static ?Uuid $user = null;

    private static function company(): Uuid
    {
        return self::$company ??= Uuid::v7();
    }

    private static function client(): Uuid
    {
        return self::$client ??= Uuid::v7();
    }

    private static function user(): Uuid
    {
        return self::$user ??= Uuid::v7();
    }

    private static function draft(string $date = '2026-10-01'): SalesInvoice
    {
        return new SalesInvoice(self::company(), self::client(), 'Cliente Uno S.A.S.', new \DateTimeImmutable($date), self::user(), new \DateTimeImmutable('2026-10-01 09:00'));
    }

    private static function iva(string $rate = '19.0000', ?Uuid $account = null): TaxSnapshot
    {
        return new TaxSnapshot(Uuid::v7(), "IVA $rate %", 'iva', TaxCalculation::Percentage, $rate, $account);
    }

    private static function withholding(string $kind, string $rate, ?Uuid $account = null): TaxSnapshot
    {
        return new TaxSnapshot(Uuid::v7(), "$kind $rate %", $kind, TaxCalculation::Percentage, $rate, $account);
    }

    private static function line(string $quantity, string $price, string $discount = '0', ?TaxSnapshot $charge = null, ?TaxSnapshot $withholding = null, ?Uuid $product = null): InvoiceLineDraft
    {
        return new InvoiceLineDraft($product ?? Uuid::v7(), 'Servicio de consultoría', Quantity::of($quantity), UnitPrice::of($price), Rate::of($discount), $charge ?? TaxSnapshot::none(), $withholding ?? TaxSnapshot::none());
    }

    private static function cash(string $amount, ?Uuid $account = null): InvoicePaymentDraft
    {
        return new InvoicePaymentDraft(Uuid::v7(), 'Efectivo', PaymentKind::Cash, $account ?? Uuid::v7(), Money::of($amount), null);
    }

    private static function credit(string $amount, string $due = '2026-10-31'): InvoicePaymentDraft
    {
        return new InvoicePaymentDraft(Uuid::v7(), 'Crédito', PaymentKind::Credit, null, Money::of($amount), new \DateTimeImmutable($due));
    }

    /**
     * Emits as the handler does once the resolution gave its numbers.
     *
     * @return list<\App\Sales\Domain\Model\Receivable>
     */
    private static function emit(SalesInvoice $invoice, string $today = '2026-10-03', int $number = 1): array
    {
        return $invoice->emit(Uuid::v7(), 'FE', $number, $number, new \DateTimeImmutable($today), self::user(), new \DateTimeImmutable("$today 10:00"));
    }
}
