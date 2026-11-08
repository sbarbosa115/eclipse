<?php

namespace App\Shared\Application\Document;

use App\Shared\Domain\Model\TaxSnapshot;
use Symfony\Component\Uid\Uuid;

/**
 * The retenciones chosen for a document line, as the resolvers of quotations, sales invoices, purchase invoices and
 * purchase orders read them (observations PRD §4.2, §6): the list `withholdings: [{tax_id}]`, or, when the list was not
 * sent, the deprecated single `withholding_tax_id`. At most one per kind (Q-G).
 */
final class WithholdingChoice
{
    public const ONE_PER_KIND = 'One retención per kind on each line.';

    /**
     * Each chosen tax with the field a problem with it is reported on: `<line>.withholdings.K.tax_id` (or
     * `<line>.withholdings[K].tax_id` where the document's paths use brackets), else `<line>.withholding_tax_id`.
     *
     * @param list<Uuid>|null $taxIds null: not sent, the deprecated single field decides
     * @param string          $line   the line's path: "lines.0" or "lines[0]"
     *
     * @return list<array{Uuid, string}>
     */
    public static function fields(?array $taxIds, ?Uuid $deprecated, string $line, bool $brackets = false): array
    {
        if (null === $taxIds) {
            return null === $deprecated ? [] : [[$deprecated, "$line.withholding_tax_id"]];
        }

        $fields = [];
        foreach ($taxIds as $k => $taxId) {
            $fields[] = [$taxId, $brackets ? "$line.withholdings[$k].tax_id" : "$line.withholdings.$k.tax_id"];
        }

        return $fields;
    }

    /**
     * Whether two of the retenciones are of the same kind (two Retefuentes, two ReteICAs…).
     *
     * @param list<TaxSnapshot> $withholdings
     */
    public static function repeatsAKind(array $withholdings): bool
    {
        $kinds = array_map(static fn (TaxSnapshot $t) => $t->kind(), $withholdings);

        return \count($kinds) !== \count(array_unique($kinds));
    }
}
