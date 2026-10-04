<?php

namespace App\Purchasing\Application\Port;

final readonly class SupplierFileView
{
    public function __construct(
        public string $id,
        public string $fileName,
        public string $contentType,
        public int $size,
        /** ATOM */
        public string $uploadedAt,
    ) {
    }
}
