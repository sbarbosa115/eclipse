<?php

namespace App\Purchasing\Application\Command;

use App\Company\Application\Numbering\Numbering;
use App\Ledger\Application\Posting\JournalPoster;
use App\Ledger\Application\Query\LedgerCatalog;
use App\Ledger\Application\Query\PaymentMethodView;
use App\Party\Application\Query\TerceroDirectory;
use App\Party\Application\Query\TerceroView;
use App\Purchasing\Application\ColombianCalendar;
use App\Purchasing\Application\Payables\PayableAllocations;
use App\Purchasing\Application\Posting\SupplierPaymentPosting;
use App\Purchasing\Domain\Error\InvalidPayment;
use App\Purchasing\Domain\Error\SupplierHasNoEmail;
use App\Purchasing\Domain\Event\SupplierPaymentEmailRequested;
use App\Purchasing\Domain\Model\PayableAllocation;
use App\Purchasing\Domain\Model\SupplierPayment;
use App\Purchasing\Domain\Repository\PayableLocks;
use App\Purchasing\Domain\Repository\SupplierPaymentRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * A recibo de pago is emitted when it is saved (§4.11), in one transaction: a known supplier, an active contado method
 * with its account (*De dónde sale el dinero*), the payables and their invoices locked (two payments for one payable
 * run one after the other: PayableLocks), the payment's own rules (SupplierPayment::issue), the RP number, each
 * allocation applied to its payable and invoice (PayableAllocations: balance and status), and the A.4 entry (which
 * refuses a date in a locked period, AC-9). Any refusal rolls everything back, the number included.
 */
final class PaySupplierHandler implements CommandHandler
{
    public function __construct(
        private readonly SupplierPaymentRepository $payments,
        private readonly PayableLocks $locks,
        private readonly PayableAllocations $payables,
        private readonly TerceroDirectory $terceros,
        private readonly LedgerCatalog $catalog,
        private readonly Numbering $numbering,
        private readonly JournalPoster $poster,
        private readonly EventBus $events,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(PaySupplier $command): Uuid
    {
        $companyId = $command->companyId;
        [$supplier, $method] = $this->supplierAndMethod($command);
        if ($command->send && null === $supplier->email) {
            throw new SupplierHasNoEmail();
        }

        $locked = $this->locks->lockForPayment($companyId, array_map(static fn (SupplierPaymentAllocationData $a) => $a->payableId, $command->allocations));
        $allocations = [];
        $unknown = [];
        foreach ($command->allocations as $i => $allocation) {
            $payable = $locked[$allocation->payableId->toRfc4122()] ?? null;
            if (null === $payable) {
                $unknown[] = ['field' => "allocations.$i.payable_id", 'message' => 'Choose an open invoice of this supplier, once.'];
                continue;
            }
            $allocations[] = new PayableAllocation($payable, Money::of($allocation->amount));
        }
        if ([] !== $unknown) {
            throw new InvalidPayment($unknown);
        }

        $number = $this->numbering->supplierPayment($companyId);
        $accountId = $method->accountId ?? throw new \LogicException('Checked above: a contado method has its account.');
        $payment = SupplierPayment::issue(
            $companyId, $number->prefix, $number->sequence, $command->terceroId, $supplier->displayName, $command->receiptDate,
            $command->paymentMethodId, $method->name, Uuid::fromString($accountId), Money::of($command->amount), self::notes($command->notes),
            $allocations, ColombianCalendar::today($this->clock), $command->userId, $this->clock->now(),
        );
        $this->payments->add($payment);
        foreach ($payment->allocations() as $allocation) {
            $this->payables->apply($companyId, $allocation->openItemId(), $allocation->amount());
        }
        $payment->recordPosting($this->poster->post(SupplierPaymentPosting::entryFor($payment, $command->userId)));

        if ($command->send) {
            $this->events->publish(new SupplierPaymentEmailRequested($companyId->toRfc4122(), $payment->id()->toRfc4122()));
        }

        return $payment->id();
    }

    /**
     * The supplier and the method, both reported at once when they are wrong.
     *
     * @return array{TerceroView, PaymentMethodView}
     */
    private function supplierAndMethod(PaySupplier $command): array
    {
        $violations = [];
        $supplier = $method = null;
        try {
            $supplier = $this->terceros->get($command->companyId, $command->terceroId);
        } catch (NotFound) {
            $violations[] = ['field' => 'tercero_id', 'message' => 'Choose the supplier.'];
        }
        try {
            $method = $this->catalog->paymentMethod($command->companyId, $command->paymentMethodId);
            if ('cash' !== $method->kind || !$method->active || null === $method->accountId) {
                $violations[] = ['field' => 'payment_method_id', 'message' => 'Choose an active contado payment method.'];
            }
        } catch (NotFound) {
            $violations[] = ['field' => 'payment_method_id', 'message' => 'Choose an active contado payment method.'];
        }
        if ([] !== $violations || null === $supplier || null === $method) {
            throw new InvalidPayment([] === $violations ? [['field' => 'tercero_id', 'message' => 'Choose the supplier.']] : $violations);
        }

        return [$supplier, $method];
    }

    private static function notes(?string $notes): ?string
    {
        $notes = null === $notes ? null : trim($notes);

        return '' === $notes ? null : $notes;
    }
}
