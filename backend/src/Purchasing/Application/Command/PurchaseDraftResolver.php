<?php

namespace App\Purchasing\Application\Command;

use App\Catalog\Application\Query\ProductCatalog;
use App\Ledger\Application\Query\LedgerCatalog;
use App\Party\Application\Query\TerceroDirectory;
use App\Purchasing\Domain\Error\DuplicateSupplierInvoiceNumber;
use App\Purchasing\Domain\Model\PurchaseInvoice;
use App\Purchasing\Domain\Model\PurchaseLineDraft;
use App\Purchasing\Domain\Model\PurchasePaymentDraft;
use App\Purchasing\Domain\Repository\PurchaseInvoiceRepository;
use App\Shared\Domain\Error\InvalidValues;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Model\PaymentKind;
use App\Shared\Domain\Model\TaxSnapshot;
use App\Shared\Domain\Money\Money;
use App\Shared\Domain\Money\Quantity;
use App\Shared\Domain\Money\Rate;
use App\Shared\Domain\Money\UnitPrice;
use App\Shared\Domain\Totals\TaxCalculation;
use Symfony\Component\Uid\Uuid;

/**
 * Checks a draft's references against the other contexts and copies what the document keeps (§4.6, §4.10): the
 * supplier's name, each tax as it is now (with its purchase account), each payment method. Every problem is reported
 * at once, by field (`tercero_id`, `lines[0].account_id`, `payments[1].due_date`…).
 *
 * A tax, product or payment method must be active to be newly chosen; one the draft already had stays allowed after it
 * was deactivated. A line by account takes only a postable, active account usable on purchases (§9 Q14).
 */
final class PurchaseDraftResolver
{
    /** @var list<array{field: string, message: string}> */
    private array $violations = [];

    public function __construct(
        private readonly TerceroDirectory $terceros,
        private readonly ProductCatalog $products,
        private readonly LedgerCatalog $ledger,
        private readonly PurchaseInvoiceRepository $invoices,
    ) {
    }

    /**
     * @throws InvalidValues|DuplicateSupplierInvoiceNumber
     */
    public function resolve(Uuid $companyId, PurchaseInvoiceContents $contents, ?PurchaseInvoice $current = null): ResolvedDraft
    {
        $this->violations = [];
        $kept = self::keptReferences($current);

        $terceroName = '';
        try {
            $tercero = $this->terceros->get($companyId, $contents->terceroId);
            $terceroName = $tercero->displayName;
            if (!$tercero->active && !($current?->terceroId()->equals($contents->terceroId) ?? false)) {
                $this->violate('tercero_id', 'This tercero is inactive.');
            }
        } catch (NotFound) {
            $this->violate('tercero_id', 'This tercero does not exist.');
        }

        if (null !== $contents->dueDate && $contents->dueDate < $contents->issueDate) {
            $this->violate('due_date', 'The due date cannot be before the invoice date.');
        }

        $lines = [];
        foreach ($contents->lines as $i => $line) {
            $lines[] = $this->line($companyId, "lines[$i]", $line, $kept);
        }
        $payments = [];
        foreach ($contents->payments as $i => $payment) {
            $payments[] = $this->payment($companyId, "payments[$i]", $payment, $contents, $kept);
        }

        if ([] !== $this->violations) {
            throw new InvalidValues($this->violations);
        }

        $number = null === $contents->supplierInvoiceNumber ? '' : trim($contents->supplierInvoiceNumber);
        if ('' !== $number && $this->invoices->supplierNumberTaken($companyId, $contents->terceroId, $number, $current?->id())) {
            throw new DuplicateSupplierInvoiceNumber();
        }
        $notes = null === $contents->notes ? '' : trim($contents->notes);

        return new ResolvedDraft(
            $contents->terceroId,
            $terceroName,
            '' === $number ? null : $number,
            $contents->issueDate,
            $contents->dueDate,
            '' === $notes ? null : $notes,
            array_values(array_filter($lines)),
            array_values(array_filter($payments)),
        );
    }

    /**
     * @param array<string, true> $kept
     */
    private function line(Uuid $companyId, string $at, PurchaseLineContents $line, array $kept): ?PurchaseLineDraft
    {
        $ok = true;
        if ((null === $line->productId) === (null === $line->accountId)) {
            $this->violate("$at.account_id", 'Choose a product or an expense account, not both.');
            $ok = false;
        } elseif (null !== $line->productId) {
            try {
                $product = $this->products->get($companyId, $line->productId);
                if (!$product->active && !isset($kept['product:'.$line->productId->toRfc4122()])) {
                    $this->violate("$at.product_id", 'This product is inactive.');
                    $ok = false;
                }
            } catch (NotFound) {
                $this->violate("$at.product_id", 'This product does not exist.');
                $ok = false;
            }
        } elseif (null !== $line->accountId) {
            try {
                $account = $this->ledger->account($companyId, $line->accountId);
                $usable = $account->postable && $account->active && $account->usableOnPurchases;
            } catch (NotFound) {
                $usable = false;
            }
            if (!$usable) {
                $this->violate("$at.account_id", 'Choose an active expense or cost account that purchases may use.');
                $ok = false;
            }
        }

        $charge = $this->tax($companyId, "$at.charge_tax_id", $line->chargeTaxId, 'charge', $kept);
        $withholding = $this->tax($companyId, "$at.withholding_tax_id", $line->withholdingTaxId, 'withholding', $kept);
        if (!$ok || null === $charge || null === $withholding) {
            return null;
        }

        return new PurchaseLineDraft($line->productId, $line->accountId, trim($line->description), Quantity::of($line->quantity), UnitPrice::of($line->unitPrice), Rate::of('' === $line->discount ? '0' : $line->discount), $charge, $withholding);
    }

    /**
     * @param array<string, true> $kept
     */
    private function tax(Uuid $companyId, string $field, ?Uuid $taxId, string $class, array $kept): ?TaxSnapshot
    {
        if (null === $taxId) {
            return TaxSnapshot::none();
        }
        try {
            $tax = $this->ledger->tax($companyId, $taxId);
        } catch (NotFound) {
            $this->violate($field, 'Choose an active tax of this class.');

            return null;
        }
        if ($tax->taxClass !== $class || (!$tax->active && !isset($kept['tax:'.$tax->id]))) {
            $this->violate($field, 'Choose an active tax of this class.');

            return null;
        }
        if ('none' === $tax->kind) {
            return TaxSnapshot::none();
        }

        return new TaxSnapshot($taxId, $tax->name, $tax->kind, TaxCalculation::from($tax->calculation), $tax->rate, null === $tax->purchaseAccountId ? null : Uuid::fromString($tax->purchaseAccountId));
    }

    /**
     * @param array<string, true> $kept
     */
    private function payment(Uuid $companyId, string $at, PurchasePaymentContents $payment, PurchaseInvoiceContents $contents, array $kept): ?PurchasePaymentDraft
    {
        try {
            $method = $this->ledger->paymentMethod($companyId, $payment->paymentMethodId);
        } catch (NotFound) {
            $this->violate("$at.payment_method_id", 'Choose an active payment method.');

            return null;
        }
        if (!$method->active && !isset($kept['method:'.$method->id])) {
            $this->violate("$at.payment_method_id", 'Choose an active payment method.');

            return null;
        }
        $kind = PaymentKind::from($method->kind);
        if (PaymentKind::Cash === $kind) {
            if (null === $method->accountId) {
                $this->violate("$at.payment_method_id", 'This payment method has no account to pay from.');

                return null;
            }

            return new PurchasePaymentDraft($payment->paymentMethodId, $method->name, $kind, Uuid::fromString($method->accountId), Money::of($payment->amount), null);
        }

        $due = $payment->dueDate ?? $contents->dueDate;
        if (null === $due) {
            $this->violate("$at.due_date", 'A credit payment needs its due date.');

            return null;
        }
        if ($due < $contents->issueDate) {
            $this->violate("$at.due_date", 'The due date cannot be before the invoice date.');

            return null;
        }

        return new PurchasePaymentDraft($payment->paymentMethodId, $method->name, $kind, null, Money::of($payment->amount), $due);
    }

    private function violate(string $field, string $message): void
    {
        $this->violations[] = ['field' => $field, 'message' => $message];
    }

    /**
     * What the draft already uses, which stays allowed after it was deactivated.
     *
     * @return array<string, true>
     */
    private static function keptReferences(?PurchaseInvoice $current): array
    {
        $kept = [];
        foreach ($current?->lines() ?? [] as $line) {
            if (null !== $line->productId()) {
                $kept['product:'.$line->productId()->toRfc4122()] = true;
            }
            foreach ([$line->chargeTax()->taxId(), $line->withholdingTax()->taxId()] as $taxId) {
                if (null !== $taxId) {
                    $kept['tax:'.$taxId->toRfc4122()] = true;
                }
            }
        }
        foreach ($current?->payments() ?? [] as $payment) {
            $kept['method:'.$payment->paymentMethodId()->toRfc4122()] = true;
        }

        return $kept;
    }
}
