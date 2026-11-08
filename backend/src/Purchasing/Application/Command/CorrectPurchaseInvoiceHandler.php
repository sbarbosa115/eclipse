<?php

namespace App\Purchasing\Application\Command;

use App\Purchasing\Application\Port\SupplierFiles;
use App\Purchasing\Domain\Repository\PurchaseInvoiceRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Calendar;
use Symfony\Component\Uid\Uuid;

/**
 * Corregir (observations PRD §4.6), one transaction: the void exactly as /void does it (locked, reversing entry dated
 * today, payables voided, number kept, reason "Corregida: …"), then the draft copy with the supplier's number the
 * void released, and copies of the supplier's files. Any refusal of the void (document_has_allocations,
 * document_not_emitted, document_voided, period_locked) rolls all of it back. The files are copied last, so only a
 * failure of the commit itself could leave copied bytes without their row.
 */
final class CorrectPurchaseInvoiceHandler implements CommandHandler
{
    public const REASON_PREFIX = 'Corregida: ';

    public function __construct(
        private readonly VoidPurchaseInvoiceHandler $void,
        private readonly PurchaseInvoiceRepository $invoices,
        private readonly SupplierFiles $files,
        private readonly Calendar $calendar,
    ) {
    }

    /** @return Uuid the new draft's id */
    public function __invoke(CorrectPurchaseInvoice $command): Uuid
    {
        ($this->void)(new VoidPurchaseInvoice($command->companyId, $command->invoiceId, $command->userId, self::REASON_PREFIX.trim($command->reason)));

        $voided = $this->invoices->get($command->companyId, $command->invoiceId);
        $this->invoices->releaseSupplierNumber($voided);
        $draft = $voided->correction($command->userId, $this->calendar->now());
        $this->invoices->add($draft);
        $this->files->copyAll($command->companyId, $voided->id(), $draft->id(), $command->userId);

        return $draft->id();
    }
}
