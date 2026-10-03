<?php

namespace App\Company\Application\Port;

use Symfony\Component\Uid\Uuid;

/**
 * Where the company's logo lives: a Shared Attachment whose bytes are stored under UPLOADS_DIR by id.
 */
interface CompanyLogos
{
    /** Stores the file (already checked as a PNG or JPEG by the caller) and returns the attachment's id. */
    public function store(Uuid $companyId, Uuid $userId, string $originalName, string $contentType, string $sourcePath): Uuid;

    /** Forgets the attachment and deletes its file. */
    public function remove(Uuid $companyId, Uuid $logoId): void;

    /** The logo's stored file, or null when the attachment or its file is gone. */
    public function find(Uuid $companyId, Uuid $logoId): ?LogoFile;
}
