<?php

namespace App\Company\Infrastructure\Storage;

use App\Company\Application\Port\CompanyLogos;
use App\Company\Application\Port\LogoFile;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Model\Attachment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

/** Logos as Attachments (owner "company") with their bytes under UPLOADS_DIR/<company>/<attachment id>. */
final class FilesystemCompanyLogos implements CompanyLogos
{
    public const OWNER_TYPE = 'company_logo';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Clock $clock,
        #[Autowire('%env(UPLOADS_DIR)%')]
        private readonly string $uploadsDir,
    ) {
    }

    public function store(Uuid $companyId, Uuid $userId, string $originalName, string $contentType, string $sourcePath): Uuid
    {
        $size = filesize($sourcePath);
        $attachment = new Attachment($companyId, self::OWNER_TYPE, $companyId, self::safeName($originalName), $contentType, false === $size ? 0 : $size, $userId, $this->clock->now());
        $target = $this->pathOf($attachment);
        if (!is_dir(\dirname($target)) && !mkdir(\dirname($target), 0o770, true) && !is_dir(\dirname($target))) {
            throw new \RuntimeException('The uploads directory is not writable.');
        }
        if (!copy($sourcePath, $target)) {
            throw new \RuntimeException('The logo could not be stored.');
        }
        $this->em->persist($attachment);

        return $attachment->id();
    }

    public function remove(Uuid $companyId, Uuid $logoId): void
    {
        $attachment = $this->em->find(Attachment::class, $logoId);
        if (!$attachment instanceof Attachment || !$attachment->companyId()->equals($companyId)) {
            return;
        }
        $path = $this->pathOf($attachment);
        if (is_file($path)) {
            unlink($path);
        }
        $this->em->remove($attachment);
    }

    public function find(Uuid $companyId, Uuid $logoId): ?LogoFile
    {
        $attachment = $this->em->find(Attachment::class, $logoId);
        if (!$attachment instanceof Attachment || !$attachment->companyId()->equals($companyId)) {
            return null;
        }
        $path = $this->pathOf($attachment);

        return is_file($path) ? new LogoFile($path, $attachment->contentType(), $attachment->fileName()) : null;
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

        return mb_substr('' === $name ? 'logo' : $name, 0, 255);
    }
}
