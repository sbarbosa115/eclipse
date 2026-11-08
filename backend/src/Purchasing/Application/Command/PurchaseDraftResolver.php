<?php

namespace App\Purchasing\Application\Command;

use App\Catalog\Application\Query\ProductCatalog;
use App\Ledger\Application\Query\LedgerCatalog;
use App\Party\Application\Query\TerceroDirectory;
use App\Purchasing\Domain\Error\DuplicateSupplierInvoiceNumber;
use App\Purchasing\Domain\Model\PurchaseInvoice;
use App\Purchasing\Domain\Model\PurchaseInvoicePayment;
use App\Purchasing\Domain\Model\PurchaseLineDraft;
use App\Purchasing\Domain\Model\PurchasePaymentDraft;
use App\Purchasing\Domain\Repository\PurchaseInvoiceRepository;
use App\Shared\Application\Document\WithholdingChoice;
use App\Shared\Domain\Error\InvalidValues;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Model\CommercialLine;
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
        $kept = self::keptReferences($current?->lines() ?? [], $current?->payments() ?? []);

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

        $lines = $this->lines($companyId, $contents->lines, $kept, $contents->issueDate);
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
            $lines,
            array_values(array_filter($payments)),
        );
    }

    /**
     * Resolves the lines of a purchase document (a factura de compra's or an orden de compra's) on their own: what the
     * document already has keeps its inactive products and taxes (their snapshots), a new choice must be active and in
     * force on the date. Every problem is reported at once, by field (`lines[0].withholdings`…).
     *
     * @param list<PurchaseLineContents> $lines
     * @param list<CommercialLine>       $current the document's lines as they are now
     *
     * @return array{list<PurchaseLineDraft>, list<array{field: string, message: string}>} the drafts and the violations
     */
    public function resolveLines(Uuid $companyId, array $lines, array $current, \DateTimeImmutable $issueDate): array
    {
        $this->violations = [];
        $drafts = $this->lines($companyId, $lines, self::keptReferences($current, []), $issueDate);

        return [$drafts, $this->violations];
    }

    /**
     * @param list<PurchaseLineContents>      $lines
     * @param array<string, true|TaxSnapshot> $kept
     *
     * @return list<PurchaseLineDraft>
     */
    private function lines(Uuid $companyId, array $lines, array $kept, \DateTimeImmutable $issueDate): array
    {
        $drafts = [];
        foreach ($lines as $i => $line) {
            $draft = $this->line($companyId, "lines[$i]", $line, $kept, $issueDate);
            if (null !== $draft) {
                $drafts[] = $draft;
            }
        }

        return $drafts;
    }

    /**
     * @param array<string, true|TaxSnapshot> $kept
     */
    private function line(Uuid $companyId, string $at, PurchaseLineContents $line, array $kept, \DateTimeImmutable $issueDate): ?PurchaseLineDraft
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

        $charge = $this->tax($companyId, "$at.charge_tax_id", $line->chargeTaxId, 'charge', $kept, $issueDate);
        $withholdings = $this->withholdings($companyId, $at, $line, $kept, $issueDate);
        if (!$ok || null === $charge || null === $withholdings) {
            return null;
        }

        return new PurchaseLineDraft($line->productId, $line->accountId, trim($line->description), Quantity::of($line->quantity), UnitPrice::of($line->unitPrice), Rate::of('' === $line->discount ? '0' : $line->discount), $charge, $withholdings);
    }

    /**
     * The line's retenciones (observations PRD §4.2): each one as a tax of its own, at most one per kind. Null when one
     * of them was refused (the violation is recorded).
     *
     * @param array<string, true|TaxSnapshot> $kept
     *
     * @return list<TaxSnapshot>|null
     */
    private function withholdings(Uuid $companyId, string $at, PurchaseLineContents $line, array $kept, \DateTimeImmutable $issueDate): ?array
    {
        $withholdings = [];
        $ok = true;
        foreach (WithholdingChoice::fields($line->withholdingTaxIds, $line->withholdingTaxId, $at, brackets: true) as [$taxId, $field]) {
            $tax = $this->tax($companyId, $field, $taxId, 'withholding', $kept, $issueDate);
            if (null === $tax) {
                $ok = false;
            } elseif (!$tax->isNone()) {
                $withholdings[] = $tax;
            }
        }
        if (WithholdingChoice::repeatsAKind($withholdings)) {
            $this->violate("$at.withholdings", WithholdingChoice::ONE_PER_KIND);
            $ok = false;
        }

        return $ok ? $withholdings : null;
    }

    /**
     * A tax newly chosen must be active and in force on the invoice's date (F5); one the draft already had is kept, with
     * its snapshot once it was deactivated.
     *
     * @param array<string, true|TaxSnapshot> $kept
     */
    private function tax(Uuid $companyId, string $field, ?Uuid $taxId, string $class, array $kept, \DateTimeImmutable $issueDate): ?TaxSnapshot
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
        if (!isset($kept['tax:'.$tax->id]) && !$tax->isValidOn($issueDate)) {
            $this->violate($field, 'This tax is not in force on the document\'s date.');

            return null;
        }
        if ('none' === $tax->kind) {
            return TaxSnapshot::none();
        }
        $snapshot = $kept['tax:'.$tax->id] ?? null;
        if (!$tax->active && $snapshot instanceof TaxSnapshot) {
            return $snapshot;
        }

        return new TaxSnapshot($taxId, $tax->name, $tax->kind, TaxCalculation::from($tax->calculation), $tax->rate, null === $tax->purchaseAccountId ? null : Uuid::fromString($tax->purchaseAccountId));
    }

    /**
     * @param array<string, true|TaxSnapshot> $kept
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
     * What the draft already uses, which stays allowed after it was deactivated (each tax with its snapshot).
     *
     * @param list<CommercialLine>         $lines
     * @param list<PurchaseInvoicePayment> $payments
     *
     * @return array<string, true|TaxSnapshot>
     */
    private static function keptReferences(array $lines, array $payments): array
    {
        $kept = [];
        foreach ($lines as $line) {
            if (null !== $line->productId()) {
                $kept['product:'.$line->productId()->toRfc4122()] = true;
            }
            foreach ([$line->chargeTax(), ...$line->withholdingTaxes()] as $tax) {
                if (null !== $tax->taxId()) {
                    $kept['tax:'.$tax->taxId()->toRfc4122()] = $tax;
                }
            }
        }
        foreach ($payments as $payment) {
            $kept['method:'.$payment->paymentMethodId()->toRfc4122()] = true;
        }

        return $kept;
    }
}
