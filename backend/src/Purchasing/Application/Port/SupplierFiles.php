<?php

namespace App\Purchasing\Application\Port;

use Symfony\Component\Uid\Uuid;

/**
 * The supplier's PDF or XML attached to a purchase invoice (§4.10, §6): Shared Attachments (owner type
 * purchase_invoice) whose bytes live under UPLOADS_DIR by id. Every method is scoped to one invoice of one company.
 */
interface SupplierFiles
{
    /** Stores a file the caller already checked (PDF or XML, size) and returns the attachment's id. */
    public function store(Uuid $companyId, Uuid $invoiceId, Uuid $userId, string $originalName, string $contentType, string $sourcePath): Uuid;

    /** @throws \App\Purchasing\Domain\Error\SupplierFileNotFound */
    public function remove(Uuid $companyId, Uuid $invoiceId, Uuid $attachmentId): void;

    /** Forgets every file of the invoice (a draft deleted). */
    public function removeAll(Uuid $companyId, Uuid $invoiceId): void;

    /** @throws \App\Purchasing\Domain\Error\SupplierFileNotFound when the attachment or its bytes are gone */
    public function find(Uuid $companyId, Uuid $invoiceId, Uuid $attachmentId): StoredFile;

    /** @return list<SupplierFileView> in the order they were attached */
    public function list(Uuid $companyId, Uuid $invoiceId): array;
}
