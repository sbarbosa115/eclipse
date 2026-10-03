<?php

namespace App\Purchasing\Application\Posting;

use App\Ledger\Application\Posting\EntryDraft;
use App\Ledger\Application\Posting\EntryLine;
use App\Ledger\Application\Posting\Side;
use App\Purchasing\Domain\Model\PurchaseInvoice;
use App\Shared\Domain\Accounting\PostingConcept;
use App\Shared\Domain\Model\PaymentKind;
use App\Shared\Domain\Model\TaxSnapshot;
use App\Shared\Domain\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * The journal entry of an emitted factura de compra / gasto (PRD Appendix A.3), built from the line amounts, which add
 * up to the totals to the cent, so it balances by construction:
 *
 *     Dr each line's subtotal (net of its discount) + its impoconsumo   the chosen account, else the product's expense
 *                                                                        account, else compra_mercancias (producto) or
 *                                                                        gasto_por_defecto (servicio)
 *     Dr IVA descontable                                                 the tax's purchase account, else iva_descontable
 *     Cr each retención practicada                                       the tax's purchase account, else its kind's concept
 *     Cr each crédito payment                                            proveedores (the supplier's own account if it has one)
 *     Cr each contado payment                                            the payment method's account
 *
 * Debits and retenciones going to the same place are posted together; payments one by one (one per payable).
 */
final class PurchaseInvoiceEntry
{
    public const SOURCE_TYPE = 'purchase_invoice';

    /**
     * @param array<string, ProductAccounting> $products by product id (RFC 4122), for the lines by product
     */
    public static function draft(PurchaseInvoice $invoice, array $products, Uuid $userId): EntryDraft
    {
        $supplier = $invoice->terceroId();
        /** @var array<string, array{EntryLine, Money}> $costs */
        $costs = [];
        /** @var array<string, array{EntryLine, Money}> $vat */
        $vat = [];
        /** @var array<string, array{EntryLine, Money}> $withholdings */
        $withholdings = [];

        foreach ($invoice->lines() as $line) {
            $charge = $line->chargeTax();
            $isConsumption = 'impoconsumo' === $charge->kind();
            $cost = $isConsumption ? $line->subtotalAmount()->plus($line->taxAmount()) : $line->subtotalAmount();
            self::add($costs, self::costTarget($line->accountId(), $line->productId(), $products), Side::Debit, $cost, null);

            if (!$isConsumption && $line->taxAmount()->isPositive()) {
                self::add($vat, self::taxTarget($charge, PostingConcept::VatDeductible), Side::Debit, $line->taxAmount(), null);
            }
            if ($line->withholdingAmount()->isPositive()) {
                $withholding = $line->withholdingTax();
                self::add($withholdings, self::taxTarget($withholding, self::withholdingConcept($withholding->kind())), Side::Credit, $line->withholdingAmount(), $supplier);
            }
        }

        $lines = array_map(static fn (array $g) => $g[0], [...array_values($costs), ...array_values($vat), ...array_values($withholdings)]);
        foreach ($invoice->payments() as $payment) {
            $lines[] = PaymentKind::Credit === $payment->kind()
                ? EntryLine::toConcept(Side::Credit, $payment->amount(), PostingConcept::Payables, $supplier, $payment->methodName())
                : EntryLine::toAccount(Side::Credit, $payment->amount(), $payment->accountId() ?? throw new \LogicException('A contado payment has its account.'), null, $payment->methodName());
        }

        $number = $invoice->number() ?? throw new \LogicException('Only an emitted invoice is posted.');

        return new EntryDraft(
            $invoice->companyId(),
            $invoice->issueDate(),
            self::SOURCE_TYPE,
            $invoice->id(),
            $number,
            \sprintf('Factura de compra %s · %s (%s)', $number, $invoice->terceroName(), $invoice->supplierInvoiceNumber() ?? ''),
            $userId,
            $lines,
        );
    }

    /**
     * @param array<string, ProductAccounting> $products
     *
     * @return array{?PostingConcept, ?Uuid}
     */
    private static function costTarget(?Uuid $accountId, ?Uuid $productId, array $products): array
    {
        if (null !== $accountId) {
            return [null, $accountId];
        }
        $product = null === $productId ? null : ($products[$productId->toRfc4122()] ?? throw new \LogicException('The product of a line is known when posting.'));
        if (null !== $product?->expenseAccountId) {
            return [null, $product->expenseAccountId];
        }

        return [true === $product?->isGoods ? PostingConcept::MerchandisePurchases : PostingConcept::DefaultExpense, null];
    }

    /**
     * @return array{?PostingConcept, ?Uuid}
     */
    private static function taxTarget(TaxSnapshot $tax, PostingConcept $concept): array
    {
        return null === $tax->accountId() ? [$concept, null] : [null, $tax->accountId()];
    }

    private static function withholdingConcept(string $kind): PostingConcept
    {
        return match ($kind) {
            'reteiva' => PostingConcept::VatWithholdingPracticed,
            'reteica' => PostingConcept::IcaWithholdingPracticed,
            default => PostingConcept::WithholdingPracticed,
        };
    }

    /**
     * @param array<string, array{EntryLine, Money}> $groups
     * @param array{?PostingConcept, ?Uuid}          $target
     */
    private static function add(array &$groups, array $target, Side $side, Money $amount, ?Uuid $terceroId): void
    {
        [$concept, $accountId] = $target;
        $key = null !== $accountId ? 'a:'.$accountId->toRfc4122() : 'c:'.$concept?->value;
        $total = isset($groups[$key]) ? $groups[$key][1]->plus($amount) : $amount;
        $line = null !== $accountId
            ? EntryLine::toAccount($side, $total, $accountId, $terceroId)
            : EntryLine::toConcept($side, $total, $concept ?? throw new \LogicException('A target is a concept or an account.'), $terceroId);
        $groups[$key] = [$line, $total];
    }
}
