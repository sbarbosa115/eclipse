<?php

namespace App\Sales\Application\Posting;

use App\Ledger\Application\Posting\EntryDraft;
use App\Ledger\Application\Posting\EntryLine;
use App\Ledger\Application\Posting\Side;
use App\Sales\Domain\Model\SalesInvoice;
use App\Shared\Domain\Accounting\PostingConcept;
use App\Shared\Domain\Model\PaymentKind;
use App\Shared\Domain\Model\TaxSnapshot;
use App\Shared\Domain\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * The entry of an emitted factura de venta, PRD Appendix A.1:
 *
 *     Dr payment-method account (per contado line)     Cr revenue: the product's account, else the rule "ingreso" (gross)
 *     Dr 1305 Clientes, tercero (per crédito line)     Cr each impuesto cargo: its account, else IVA → iva_generado,
 *     Dr 4175 descuento_ventas (discounts)                                     impoconsumo → impoconsumo
 *     Dr each retención suffered: its account, else retefuente/reteiva/reteica → 135515/135517/135518 (§9 Q4)
 *
 * Built from the lines' shares of the totals (DocumentTotals) and grouped by account, so it balances by construction:
 * Σ débitos = Total neto + Retenciones + Descuentos = Σ créditos = Total bruto + Impuestos.
 */
final class SalesInvoicePosting
{
    public const SOURCE_TYPE = 'sales_invoice';

    /**
     * @param array<string, Uuid> $revenueAccounts the revenue account of each product that has one, by product id
     */
    public static function entryFor(SalesInvoice $invoice, array $revenueAccounts, Uuid $userId): EntryDraft
    {
        $number = $invoice->number() ?? throw new \LogicException('Only an emitted invoice is posted.');

        return new EntryDraft(
            $invoice->companyId(),
            $invoice->issueDate(),
            self::SOURCE_TYPE,
            $invoice->id(),
            $number,
            \sprintf('Factura de venta %s · %s', $number, $invoice->terceroName()),
            $userId,
            self::linesFor($invoice, $revenueAccounts),
        );
    }

    /**
     * @param array<string, Uuid> $revenueAccounts
     *
     * @return list<EntryLine>
     */
    public static function linesFor(SalesInvoice $invoice, array $revenueAccounts): array
    {
        $client = $invoice->terceroId();
        $debits = new Movements(Side::Debit);
        $credits = new Movements(Side::Credit);

        foreach ($invoice->payments() as $payment) {
            if (PaymentKind::Credit === $payment->kind()) {
                // One per crédito line, like the receivables, never merged.
                $debits->separate(PostingConcept::Receivables, $payment->amount(), $client);
            } else {
                $debits->add($payment->accountId() ?? throw new \LogicException('A contado payment has an account.'), $payment->amount());
            }
        }
        $debits->add(PostingConcept::SalesDiscount, $invoice->discountTotal());

        foreach ($invoice->lines() as $line) {
            $productId = $line->productId()?->toRfc4122();
            $credits->add(null !== $productId && isset($revenueAccounts[$productId]) ? $revenueAccounts[$productId] : PostingConcept::Revenue, $line->grossAmount());
        }
        foreach ($invoice->lines() as $line) {
            $debits->add(self::taxTarget($line->withholdingTax()), $line->withholdingAmount(), $client);
        }
        foreach ($invoice->lines() as $line) {
            $credits->add(self::taxTarget($line->chargeTax()), $line->taxAmount());
        }

        return [...$debits->lines(), ...$credits->lines()];
    }

    /** The tax's own account on sales, else the concept of its kind. */
    private static function taxTarget(TaxSnapshot $tax): Uuid|PostingConcept|null
    {
        return $tax->accountId() ?? match ($tax->kind()) {
            'iva' => PostingConcept::VatGenerated,
            'impoconsumo' => PostingConcept::ConsumptionTax,
            'retefuente' => PostingConcept::WithholdingSuffered,
            'reteiva' => PostingConcept::VatWithholdingSuffered,
            'reteica' => PostingConcept::IcaWithholdingSuffered,
            default => null,
        };
    }
}
