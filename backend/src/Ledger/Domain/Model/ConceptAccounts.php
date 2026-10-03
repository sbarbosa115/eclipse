<?php

namespace App\Ledger\Domain\Model;

use App\Shared\Domain\Accounting\PostingConcept;

/**
 * Where in the PUC each concept may post (§5 and Appendix A): a posting rule may point anywhere under these prefixes,
 * so the accountant can choose 4155 for services (§9 Q1), 2335 for service payables (Q5) or a ReteFuente subcuenta by
 * concept, but never revenue to an expense account.
 */
final class ConceptAccounts
{
    private const PREFIXES = [
        'ingreso' => ['41'],
        'descuento_ventas' => ['4175'],
        'iva_generado' => ['2408'],
        'iva_descontable' => ['2408'],
        'impoconsumo' => ['24'],
        'retefuente_sufrida' => ['1355'],
        'reteiva_sufrida' => ['1355'],
        'reteica_sufrida' => ['1355'],
        'clientes' => ['13'],
        'proveedores' => ['22', '23'],
        'retefuente_practicada' => ['2365'],
        'reteiva_practicada' => ['2367'],
        'reteica_practicada' => ['2368'],
        'gasto_por_defecto' => ['5', '6', '7'],
        // Sistema periódico now; stage 3 points it at 1435 Mercancías (sistema permanente).
        'compra_mercancias' => ['6', '14'],
    ];

    public static function allows(PostingConcept $concept, string $code): bool
    {
        foreach (self::prefixes($concept) as $prefix) {
            if (str_starts_with($code, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public static function prefixes(PostingConcept $concept): array
    {
        return self::PREFIXES[$concept->value];
    }
}
