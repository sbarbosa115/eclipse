<?php

namespace App\Reporting\Application\Exogena;

use Symfony\Component\Uid\Uuid;

/**
 * The exógena figures of the documents of a year (observations PRD §4.7 d): the lines and their retenciones of the
 * invoices that count (emitted, partially paid or paid, by issue date; drafts and voided ones never), and the terceros
 * as the formats identify them.
 */
interface ExogenaQueries
{
    /**
     * The year's figures of a format built from the invoices (every format but 1008 and 1009, which are the cartera at
     * 31/12): one entry per tercero, or per tercero and account in 1001 and 1007, by tercero name then account code.
     *
     * @return list<ExogenaAmounts>
     */
    public function amounts(Uuid $companyId, ExogenaFormat $format, int $year): array;

    /**
     * @param list<string> $terceroIds RFC 4122
     *
     * @return array<string, ExogenaTercero> by id; another company's terceros are not found
     */
    public function terceros(Uuid $companyId, array $terceroIds): array;
}
