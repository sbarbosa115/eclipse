<?php

namespace App\Tests\Unit\Purchasing;

use App\Purchasing\Domain\Error\DuplicateSupplierInvoiceNumber;
use App\Purchasing\UI\Http\DuplicateSupplierInvoiceNumberSubscriber;
use Doctrine\DBAL\Driver\AbstractException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * The supplier's number is checked before saving, and the database's unique key (company, supplier, live number:
 * `purchase_invoice_active_number`) settles two saves that pass the check at the same moment. Either way the person
 * reads the same 422 next to the field, never a 500.
 */
final class DuplicateSupplierInvoiceNumberSubscriberTest extends TestCase
{
    private function handle(\Throwable $e): ExceptionEvent
    {
        $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $e);
        (new DuplicateSupplierInvoiceNumberSubscriber(new IdentityTranslator()))->onException($event);

        return $event;
    }

    private static function uniqueViolation(string $key): UniqueConstraintViolationException
    {
        $driver = new class("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'x' for key '$key'", '23000', 1062) extends AbstractException {
        };

        return new UniqueConstraintViolationException($driver, null);
    }

    /** @return array<mixed> */
    private static function json(ExceptionEvent $event): array
    {
        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(422, $response->getStatusCode());

        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    public function testTheHandlersRefusalIsA422OnTheField(): void
    {
        $body = self::json($this->handle(new DuplicateSupplierInvoiceNumber()));

        self::assertSame('duplicate_supplier_invoice_number', $body['error']);
        self::assertSame(['supplier_invoice_number'], array_column($body['violations'], 'field'));
    }

    public function testTheDatabasesUniqueKeyReadsTheSame(): void
    {
        $body = self::json($this->handle(self::uniqueViolation('purchase_invoice_active_number')));

        self::assertSame('duplicate_supplier_invoice_number', $body['error'], 'A race on the live-number key is the same refusal.');
        self::assertSame(['supplier_invoice_number'], array_column($body['violations'], 'field'));
    }

    public function testAnotherUniqueKeyIsLeftAlone(): void
    {
        $event = $this->handle(self::uniqueViolation('journal_entry_number'));

        self::assertNull($event->getResponse(), 'Not this subscriber\'s to answer.');
    }
}
