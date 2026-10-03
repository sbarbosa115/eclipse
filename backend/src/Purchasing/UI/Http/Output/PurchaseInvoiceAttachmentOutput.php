<?php

namespace App\Purchasing\UI\Http\Output;

use App\Purchasing\Application\Port\SupplierFileView;

/** The supplier's PDF or XML. */
final readonly class PurchaseInvoiceAttachmentOutput
{
    public function __construct(
        public string $id,
        public string $fileName,
        public string $contentType,
        public int $size,
        public string $uploadedAt,
    ) {
    }

    public static function of(SupplierFileView $v): self
    {
        return new self($v->id, $v->fileName, $v->contentType, $v->size, $v->uploadedAt);
    }
}
