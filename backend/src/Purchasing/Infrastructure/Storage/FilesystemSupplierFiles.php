<?php

namespace App\Purchasing\Infrastructure\Storage;

use App\Purchasing\Application\Port\StoredFile;
use App\Purchasing\Application\Port\SupplierFiles;
use App\Purchasing\Application\Port\SupplierFileView;
use App\Purchasing\Domain\Error\SupplierFileNotFound;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Model\Attachment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

/** The supplier's files as Attachments (owner purchase_invoice) with their bytes under UPLOADS_DIR/<company>/<id>. */
final class FilesystemSupplierFiles implements SupplierFiles
{
    public const OWNER_TYPE = 'purchase_invoice';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Clock $clock,
        #[Autowire('%env(UPLOADS_DIR)%')]
        private readonly string $uploadsDir,
    ) {
    }

    public function store(Uuid $companyId, Uuid $invoiceId, Uuid $userId, string $originalName, string $contentType, string $sourcePath): Uuid
    {
        $size = filesize($sourcePath);
        $attachment = new Attachment($companyId, self::OWNER_TYPE, $invoiceId, self::safeName($originalName), $contentType, false === $size ? 0 : $size, $userId, $this->clock->now());
        $target = $this->pathOf($attachment);
        if (!is_dir(\dirname($target)) && !mkdir(\dirname($target), 0o770, true) && !is_dir(\dirname($target))) {
            throw new \RuntimeException('The uploads directory is not writable.');
        }
        if (!copy($sourcePath, $target)) {
            throw new \RuntimeException('The file could not be stored.');
        }
        $this->em->persist($attachment);

        return $attachment->id();
    }

    public function remove(Uuid $companyId, Uuid $invoiceId, Uuid $attachmentId): void
    {
        $this->forget($this->attachment($companyId, $invoiceId, $attachmentId));
    }

    public function removeAll(Uuid $companyId, Uuid $invoiceId): void
    {
        foreach ($this->attachments($companyId, $invoiceId) as $attachment) {
            $this->forget($attachment);
        }
    }

    public function find(Uuid $companyId, Uuid $invoiceId, Uuid $attachmentId): StoredFile
    {
        $attachment = $this->attachment($companyId, $invoiceId, $attachmentId);
        $path = $this->pathOf($attachment);

        return is_file($path) ? new StoredFile($path, $attachment->contentType(), $attachment->fileName()) : throw new SupplierFileNotFound();
    }

    public function list(Uuid $companyId, Uuid $invoiceId): array
    {
        return array_map(static fn (Attachment $a) => new SupplierFileView($a->id()->toRfc4122(), $a->fileName(), $a->contentType(), $a->size(), $a->uploadedAt()->format(\DATE_ATOM)), $this->attachments($companyId, $invoiceId));
    }

    /** @return list<Attachment> */
    private function attachments(Uuid $companyId, Uuid $invoiceId): array
    {
        return array_values($this->em->getRepository(Attachment::class)->findBy(['companyId' => $companyId, 'ownerType' => self::OWNER_TYPE, 'ownerId' => $invoiceId], ['uploadedAt' => 'ASC', 'id' => 'ASC']));
    }

    private function attachment(Uuid $companyId, Uuid $invoiceId, Uuid $attachmentId): Attachment
    {
        return $this->em->getRepository(Attachment::class)->findOneBy(['companyId' => $companyId, 'ownerType' => self::OWNER_TYPE, 'ownerId' => $invoiceId, 'id' => $attachmentId]) ?? throw new SupplierFileNotFound();
    }

    private function forget(Attachment $attachment): void
    {
        $path = $this->pathOf($attachment);
        if (is_file($path)) {
            unlink($path);
        }
        $this->em->remove($attachment);
    }

    private function pathOf(Attachment $attachment): string
    {
        return rtrim($this->uploadsDir, '/').'/'.$attachment->storageKey();
    }

    /** What the person called the file is only shown back to them: no path, no control characters. */
    private static function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name) ?? '';

        return mb_substr('' === $name ? 'factura' : $name, 0, 255);
    }
}
