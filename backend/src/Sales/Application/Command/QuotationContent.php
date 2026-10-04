<?php

namespace App\Sales\Application\Command;

use App\Party\Application\Query\TerceroDirectory;
use App\Party\Application\Query\TerceroView;
use App\Sales\Domain\Error\InvalidQuotation;
use App\Sales\Domain\Model\Quotation;
use App\Shared\Domain\Error\NotFound;
use Symfony\Component\Uid\Uuid;

/**
 * Turns what a person sent into a draft quotation's content: the client's name copied, the contact and responsable
 * checked, the lines resolved with the same rules as an invoice's (SalesInvoiceContent). Every problem is reported
 * at once, by field path.
 */
final class QuotationContent
{
    /** @var list<array{field: string, message: string}> */
    private array $violations = [];

    public function __construct(
        private readonly TerceroDirectory $terceros,
        private readonly SalesInvoiceContent $lines,
    ) {
    }

    /** A new draft quotation (not stored yet) holding the data. */
    public function create(Uuid $companyId, Uuid $userId, QuotationData $data, \DateTimeImmutable $now): Quotation
    {
        $name = '';
        try {
            $name = $this->terceros->get($companyId, $data->terceroId)->displayName;
        } catch (NotFound) {
            // Reported by write() as a violation on tercero_id.
        }
        $quotation = new Quotation($companyId, $data->terceroId, $name, $data->issueDate, $data->expiryDate ?? $data->issueDate->modify(\sprintf('+%d days', Quotation::VALIDITY_DAYS)), $userId, $now);
        $this->write($quotation, $data);

        return $quotation;
    }

    public function write(Quotation $quotation, QuotationData $data): void
    {
        $this->violations = [];
        $companyId = $quotation->companyId();
        $client = $this->tercero($companyId, $data->terceroId, 'tercero_id');
        if (null !== $client && !$client->active && !$data->terceroId->equals($quotation->terceroId())) {
            $this->violate('tercero_id', 'This client is inactive.');
        }
        if (null !== $data->contactId && null === $this->terceros->contactName($companyId, $data->terceroId, $data->contactId)) {
            $this->violate('contact_id', 'Choose a contact of this client.');
        }
        if (null !== $data->responsibleId) {
            $responsible = $this->tercero($companyId, $data->responsibleId, 'responsible_id');
            if (null !== $responsible && !\in_array('empleado', $responsible->roles, true)) {
                $this->violate('responsible_id', 'The responsible is a tercero with the role empleado.');
            }
        }
        if (null !== $data->expiryDate && $data->expiryDate->format('Y-m-d') < $data->issueDate->format('Y-m-d')) {
            $this->violate('expiry_date', 'The offer cannot expire before the quotation date.');
        }
        [$lines, $lineViolations] = $this->lines->resolveLines($companyId, $quotation->lines(), $data->lines, $data->issueDate);
        array_push($this->violations, ...$lineViolations);
        if ([] !== $this->violations) {
            throw new InvalidQuotation($this->violations);
        }

        $quotation->revise($data->terceroId, null === $client ? $quotation->terceroName() : $client->displayName, $data->contactId, $data->responsibleId, $data->issueDate, $data->expiryDate, $data->header, $data->terms, $data->notes);
        $quotation->replaceLines($lines);
    }

    private function tercero(Uuid $companyId, Uuid $id, string $field): ?TerceroView
    {
        try {
            return $this->terceros->get($companyId, $id);
        } catch (NotFound) {
            $this->violate($field, 'This tercero does not exist.');

            return null;
        }
    }

    private function violate(string $field, string $message): void
    {
        $this->violations[] = ['field' => $field, 'message' => $message];
    }
}
