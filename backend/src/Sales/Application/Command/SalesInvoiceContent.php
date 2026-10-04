<?php

namespace App\Sales\Application\Command;

use App\Catalog\Application\Query\ProductCatalog;
use App\Ledger\Application\Query\LedgerCatalog;
use App\Ledger\Application\Query\TaxView;
use App\Party\Application\Query\TerceroDirectory;
use App\Party\Application\Query\TerceroView;
use App\Sales\Domain\Error\InvalidInvoice;
use App\Sales\Domain\Model\InvoiceLineDraft;
use App\Sales\Domain\Model\InvoicePaymentDraft;
use App\Sales\Domain\Model\SalesInvoice;
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
 * Turns what a person sent into a draft's content, checking every reference against the catalogs and copying what
 * the document keeps (the client's name, each tax as a TaxSnapshot, each method's name, kind and account). A new choice
 * must be active; what the draft already had is kept even if it was deactivated since. Every problem is reported at
 * once, by field path.
 */
final class SalesInvoiceContent
{
    /** @var list<array{field: string, message: string}> */
    private array $violations = [];

    public function __construct(
        private readonly TerceroDirectory $terceros,
        private readonly ProductCatalog $products,
        private readonly LedgerCatalog $ledger,
    ) {
    }

    /**
     * Writes the data onto the draft (a new one or one being edited).
     */
    public function write(SalesInvoice $invoice, SalesInvoiceData $data): void
    {
        $this->violations = [];
        $name = $this->client($invoice, $data);
        $this->contactAndSeller($invoice->companyId(), $data);
        $lines = $this->lines($invoice->companyId(), $invoice->lines(), $data->lines, $data->issueDate);
        $payments = $this->payments($invoice, $data->payments);

        $invoice->revise($data->terceroId, $name ?? $invoice->terceroName(), $data->contactId, $data->sellerId, $data->issueDate, $data->notes);
        try {
            // The rows' own rules (amounts, due dates) are reported with the rest, in the same answer.
            $invoice->replacePayments($payments);
        } catch (InvalidInvoice $e) {
            array_push($this->violations, ...$e->violations());
        }
        if ([] !== $this->violations) {
            throw new InvalidInvoice($this->violations);
        }
        $invoice->replaceLines($lines);
    }

    /** The client's name to copy; a client newly chosen must be active. */
    public function clientName(Uuid $companyId, Uuid $terceroId): ?string
    {
        $this->violations = [];
        $view = $this->tercero($companyId, $terceroId, 'tercero_id');
        if (null !== $view && !$view->active) {
            $this->violate('tercero_id', 'This client is inactive.');
        }
        if ([] !== $this->violations) {
            throw new InvalidInvoice($this->violations);
        }

        return $view?->displayName;
    }

    private function client(SalesInvoice $invoice, SalesInvoiceData $data): ?string
    {
        $view = $this->tercero($invoice->companyId(), $data->terceroId, 'tercero_id');
        if (null !== $view && !$view->active && !$data->terceroId->equals($invoice->terceroId())) {
            $this->violate('tercero_id', 'This client is inactive.');
        }

        return $view?->displayName;
    }

    private function contactAndSeller(Uuid $companyId, SalesInvoiceData $data): void
    {
        if (null !== $data->contactId && null === $this->terceros->contactName($companyId, $data->terceroId, $data->contactId)) {
            $this->violate('contact_id', 'Choose a contact of this client.');
        }
        if (null !== $data->sellerId) {
            $seller = $this->tercero($companyId, $data->sellerId, 'seller_id');
            if (null !== $seller && !\in_array('empleado', $seller->roles, true)) {
                $this->violate('seller_id', 'The seller is a tercero with the role empleado.');
            }
        }
    }

    private function tercero(Uuid $companyId, Uuid $id, string $field): ?TerceroView
    {
        try {
            return $this->terceros->get($companyId, $id);
        } catch (NotFound) {
            $this->violate($field, 'This tercero does not exist.');

            return null;
        }
    }

    /**
     * The lines of a draft (a quotation's or an invoice's) resolved against the catalogs: what the draft already has
     * keeps its inactive products and taxes, a new choice must be active. Every problem is reported at once.
     *
     * @param list<CommercialLine>       $current the draft's lines as they are now
     * @param list<SalesInvoiceLineData> $lines
     *
     * @return array{list<InvoiceLineDraft>, list<array{field: string, message: string}>} the drafts and the violations
     */
    public function resolveLines(Uuid $companyId, array $current, array $lines, \DateTimeImmutable $issueDate): array
    {
        $this->violations = [];
        $drafts = $this->lines($companyId, $current, $lines, $issueDate);

        return [$drafts, $this->violations];
    }

    /**
     * @param list<CommercialLine>       $current
     * @param list<SalesInvoiceLineData> $lines
     *
     * @return list<InvoiceLineDraft>
     */
    private function lines(Uuid $companyId, array $current, array $lines, \DateTimeImmutable $issueDate): array
    {
        $kept = [];
        $keptProducts = [];
        foreach ($current as $line) {
            foreach ([$line->chargeTax(), $line->withholdingTax()] as $tax) {
                if (null !== $tax->taxId()) {
                    $kept[$tax->taxId()->toRfc4122()] = $tax;
                }
            }
            if (null !== $line->productId()) {
                $keptProducts[$line->productId()->toRfc4122()] = true;
            }
        }

        $drafts = [];
        foreach ($lines as $i => $line) {
            $at = "lines.$i";
            if (null === $line->productId) {
                $this->violate("$at.product_id", 'Choose a product or service.');
            } else {
                try {
                    $product = $this->products->get($companyId, $line->productId);
                    if (!$product->active && !isset($keptProducts[$product->id])) {
                        $this->violate("$at.product_id", 'This product is inactive.');
                    }
                } catch (NotFound) {
                    $this->violate("$at.product_id", 'This product does not exist.');
                }
            }
            if ('' === trim($line->description)) {
                $this->violate("$at.description", 'Write the description.');
            }
            $charge = $this->tax($companyId, $line->chargeTaxId, 'charge', "$at.charge_tax_id", $kept, $issueDate);
            $withholding = $this->tax($companyId, $line->withholdingTaxId, 'withholding', "$at.withholding_tax_id", $kept, $issueDate);
            $quantity = Quantity::of($line->quantity);
            if (!$quantity->toBigDecimal()->isPositive()) {
                $this->violate("$at.quantity", 'The quantity is greater than zero.');
            }
            $discount = Rate::of('' === trim($line->discount) ? '0' : $line->discount);
            if (null !== $charge && null !== $withholding) {
                $drafts[] = new InvoiceLineDraft($line->productId, $line->description, $quantity, UnitPrice::of($line->unitPrice), $discount, $charge, $withholding);
            }
        }

        return $drafts;
    }

    /**
     * A tax newly chosen must be active and in force on the document's date (F5); one the draft already had is kept.
     *
     * @param array<string, TaxSnapshot> $kept the taxes the draft already had, by id
     */
    private function tax(Uuid $companyId, ?Uuid $taxId, string $class, string $field, array $kept, \DateTimeImmutable $issueDate): ?TaxSnapshot
    {
        if (null === $taxId) {
            return TaxSnapshot::none();
        }
        try {
            $tax = $this->ledger->tax($companyId, $taxId);
        } catch (NotFound) {
            $this->violate($field, 'charge' === $class ? 'Choose an active charge tax (IVA, impoconsumo).' : 'Choose an active withholding tax (retención).');

            return null;
        }
        if ($tax->taxClass !== $class || (!$tax->active && !isset($kept[$tax->id]))) {
            $this->violate($field, 'charge' === $class ? 'Choose an active charge tax (IVA, impoconsumo).' : 'Choose an active withholding tax (retención).');

            return null;
        }
        if (!isset($kept[$tax->id]) && !$tax->isValidOn($issueDate)) {
            $this->violate($field, 'This tax is not in force on the document\'s date.');

            return null;
        }
        if (!$tax->active) {
            return $kept[$tax->id];
        }

        return self::snapshot($tax);
    }

    private static function snapshot(TaxView $tax): TaxSnapshot
    {
        if ('none' === $tax->kind) {
            return TaxSnapshot::none();
        }

        return new TaxSnapshot(Uuid::fromString($tax->id), $tax->name, $tax->kind, TaxCalculation::from($tax->calculation), $tax->rate, null === $tax->salesAccountId ? null : Uuid::fromString($tax->salesAccountId));
    }

    /**
     * @param list<SalesInvoicePaymentData> $payments
     *
     * @return list<InvoicePaymentDraft>
     */
    private function payments(SalesInvoice $invoice, array $payments): array
    {
        $kept = [];
        foreach ($invoice->payments() as $payment) {
            $kept[$payment->paymentMethodId()->toRfc4122()] = $payment;
        }

        $drafts = [];
        foreach ($payments as $i => $payment) {
            $field = "payments.$i.payment_method_id";
            try {
                $method = $this->ledger->paymentMethod($invoice->companyId(), $payment->paymentMethodId);
            } catch (NotFound) {
                $this->violate($field, 'Choose an active payment method.');
                continue;
            }
            if (!$method->active && !isset($kept[$method->id])) {
                $this->violate($field, 'Choose an active payment method.');
                continue;
            }
            $kind = PaymentKind::from($method->kind);
            $account = PaymentKind::Cash === $kind && null !== $method->accountId ? Uuid::fromString($method->accountId) : null;
            if (!$method->active) {
                // Deactivated since the draft chose it: keep what the draft copied.
                $account = $kept[$method->id]->accountId();
            }
            $drafts[] = new InvoicePaymentDraft($payment->paymentMethodId, $method->name, $kind, $account, Money::of($payment->amount), PaymentKind::Credit === $kind ? $payment->dueDate : null);
        }

        return $drafts;
    }

    private function violate(string $field, string $message): void
    {
        $this->violations[] = ['field' => $field, 'message' => $message];
    }
}
