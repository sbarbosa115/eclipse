<?php

namespace App\Company\Application\Port;

/** A stored logo: where its bytes are and what they are. */
final readonly class LogoFile
{
    public function __construct(
        public string $path,
        public string $contentType,
        public string $fileName,
    ) {
    }
}
