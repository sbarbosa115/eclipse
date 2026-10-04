<?php

namespace App\Sales\Application\Command;

use App\Company\Application\Numbering\Numbering;
use App\Ledger\Application\Posting\JournalPoster;
use App\Ledger\Application\Query\LedgerCatalog;
use App\Ledger\Application\Query\PaymentMethodView;
use App\Party\Application\Query\TerceroDirectory;
use App\Party\Application\Query\TerceroView;
use App\Sales\Application\Collection\InvoiceCollections;
use App\Sales\Application\Posting\CashReceiptPosting;
use App\Sales\Application\SalesCalendar;
use App\Sales\Domain\Error\InvalidReceipt;
use App\Sales\Domain\Error\PeriodLocked;
use App\Sales\Domain\Error\TerceroHasNoEmail;
use App\Sales\Domain\Event\CashReceiptEmailRequested;
use App\Sales\Domain\Model\CashReceipt;
use App\Sales\Domain\Model\ReceivableAllocation;
use App\Sales\Domain\Repository\CashReceiptRepository;
use App\Sales\Domain\Repository\ReceivableLocks;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * A recibo de caja is emitted when it is saved (§4.9), in one transaction: a known client, an active contado method
 * with its account (*Dónde ingresa el dinero*), an open period (AC-9), the receivables locked (two receipts for one
 * receivable run one after the other: ReceivableLocks), the receipt's own rules (CashReceipt::issue), the RC number,
 * each allocation applied to its invoice (InvoiceCollections: balance and status), and the A.2 entry. Any refusal rolls
 * everything back, the number included.
 */
final class ReceiveCashHandler implements CommandHandler
{
    public function __construct(
        private readonly CashReceiptRepository $receipts,
        private readonly ReceivableLocks $locks,
        private readonly InvoiceCollections $collections,
        private readonly TerceroDirectory $terceros,
        private readonly LedgerCatalog $catalog,
        private readonly Numbering $numbering,
        private readonly JournalPoster $poster,
        private readonly EventBus $events,
        private readonly SalesCalendar $calendar,
    ) {
    }

    public function __invoke(ReceiveCash $command): Uuid
    {
        $companyId = $command->companyId;
        [$client, $method] = $this->clientAndMethod($command);
        if (!$this->poster->isOpen($companyId, $command->receiptDate)) {
            throw new PeriodLocked($command->receiptDate);
        }
        if ($command->send && null === $client->email) {
            throw new TerceroHasNoEmail();
        }

        $locked = $this->locks->lockForCollection($companyId, array_map(static fn (CashReceiptAllocationData $a) => $a->receivableId, $command->allocations));
        $allocations = [];
        $unknown = [];
        foreach ($command->allocations as $i => $allocation) {
            $receivable = $locked[$allocation->receivableId->toRfc4122()] ?? null;
            if (null === $receivable) {
                $unknown[] = ['field' => "allocations.$i.receivable_id", 'message' => 'Choose an open invoice of this client, once.'];
                continue;
            }
            $allocations[] = new ReceivableAllocation($receivable, Money::of($allocation->amount));
        }
        if ([] !== $unknown) {
            throw new InvalidReceipt($unknown);
        }

        $number = $this->numbering->cashReceipt($companyId);
        $accountId = $method->accountId ?? throw new \LogicException('Checked above: a contado method has its account.');
        $receipt = CashReceipt::issue(
            $companyId, $number->prefix, $number->sequence, $command->terceroId, $client->displayName, $command->receiptDate,
            $command->paymentMethodId, $method->name, Uuid::fromString($accountId), Money::of($command->amount), self::notes($command->notes),
            $allocations, $this->calendar->today(), $command->userId, $this->calendar->now(),
        );
        $this->receipts->add($receipt);
        foreach ($receipt->allocations() as $allocation) {
            $this->collections->apply($companyId, $allocation->openItemId(), $allocation->amount());
        }
        $receipt->recordPosting($this->poster->post(CashReceiptPosting::entryFor($receipt, $command->userId)));

        if ($command->send) {
            $this->events->publish(new CashReceiptEmailRequested($companyId->toRfc4122(), $receipt->id()->toRfc4122()));
        }

        return $receipt->id();
    }

    /**
     * The client and the method, both reported at once when they are wrong.
     *
     * @return array{TerceroView, PaymentMethodView}
     */
    private function clientAndMethod(ReceiveCash $command): array
    {
        $violations = [];
        $client = $method = null;
        try {
            $client = $this->terceros->get($command->companyId, $command->terceroId);
        } catch (NotFound) {
            $violations[] = ['field' => 'tercero_id', 'message' => 'Choose the client.'];
        }
        try {
            $method = $this->catalog->paymentMethod($command->companyId, $command->paymentMethodId);
            if ('cash' !== $method->kind || !$method->active || null === $method->accountId) {
                $violations[] = ['field' => 'payment_method_id', 'message' => 'Choose an active contado payment method.'];
            }
        } catch (NotFound) {
            $violations[] = ['field' => 'payment_method_id', 'message' => 'Choose an active contado payment method.'];
        }
        if ([] !== $violations || null === $client || null === $method) {
            throw new InvalidReceipt([] === $violations ? [['field' => 'tercero_id', 'message' => 'Choose the client.']] : $violations);
        }

        return [$client, $method];
    }

    private static function notes(?string $notes): ?string
    {
        $notes = null === $notes ? null : trim($notes);

        return '' === $notes ? null : $notes;
    }
}
