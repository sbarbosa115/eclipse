<?php

namespace App\Purchasing\Application\Port;

/** A stored file: where its bytes are and what they are. */
final readonly class StoredFile
{
    public function __construct(
        public string $path,
        public string $contentType,
        public string $fileName,
    ) {
    }
}
