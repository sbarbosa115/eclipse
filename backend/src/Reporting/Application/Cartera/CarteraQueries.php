<?php

namespace App\Reporting\Application\Cartera;

use Symfony\Component\Uid\Uuid;

/**
 * Cartera de clientes and de proveedores (§4.13): the open receivables / payables as they stood at the end of a day.
 * The balance "as of" is rebuilt from the documents' own dates (an invoice counts from its issue date, a receipt or
 * payment from its date until it is voided), so any past date answers what the books showed then.
 */
interface CarteraQueries
{
    /**
     * @param string|null $search part of the tercero's name or identification number, matched literally
     *
     * @return list<CarteraRow> by total owed (largest first), then name
     */
    public function summary(Uuid $companyId, CarteraSide $side, \DateTimeImmutable $asOf, ?string $search, ?int $limit = null, int $offset = 0): array;

    public function totals(Uuid $companyId, CarteraSide $side, \DateTimeImmutable $asOf, ?string $search = null): CarteraTotals;

    /**
     * @return list<CarteraDocument> the soonest due first; one tercero's, or everybody's
     */
    public function documents(Uuid $companyId, CarteraSide $side, \DateTimeImmutable $asOf, ?Uuid $terceroId = null, ?string $search = null): array;
}
