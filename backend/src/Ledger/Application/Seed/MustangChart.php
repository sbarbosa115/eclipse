<?php

namespace App\Ledger\Application\Seed;

use App\Shared\Domain\Accounting\PostingConcept;

/**
 * What Mustang adds to the official catálogo (PRD §5, Appendix A.10): the company's own sub-accounts and auxiliares
 * under their official parents (Art. 6 allows them), and the account each concept posts to by default.
 *
 * Where the PUC leaves a range free (2205, 2367, 2368, 2408, 2495, 4175, 6205 have no standard subcuentas), Mustang
 * opens the first one so that the concept has a postable account.
 */
final class MustangChart
{
    /** code => [name, parent code], parents first. */
    public const ACCOUNTS = [
        '11050501' => ['CAJA GENERAL', '110505'],
        '11100501' => ['BANCOS MONEDA NACIONAL', '111005'],
        '13050501' => ['CLIENTES NACIONALES', '130505'],
        '13051001' => ['CLIENTES DEL EXTERIOR', '130510'],
        '220505' => ['PROVEEDORES NACIONALES', '2205'],
        '22050501' => ['PROVEEDORES NACIONALES', '220505'],
        '236701' => ['RETEIVA PRACTICADA', '2367'],
        '236801' => ['RETEICA PRACTICADA', '2368'],
        '240805' => ['IVA GENERADO', '2408'],
        '240810' => ['IVA DESCONTABLE', '2408'],
        '249505' => ['IMPUESTO NACIONAL AL CONSUMO', '2495'],
        '417501' => ['DEVOLUCIONES, REBAJAS Y DESCUENTOS EN VENTAS', '4175'],
        '620501' => ['COMPRAS DE MERCANCIAS', '6205'],
    ];

    /**
     * The §5 defaults. Ingreso starts at goods (4135, §9 Q1); the accountant moves it to 4155 or another 41xx.
     *
     * @return array<string, string> concept => account code
     */
    public static function defaultRules(): array
    {
        return [
            PostingConcept::Revenue->value => '413595',
            PostingConcept::SalesDiscount->value => '417501',
            PostingConcept::VatGenerated->value => '240805',
            PostingConcept::VatDeductible->value => '240810',
            PostingConcept::ConsumptionTax->value => '249505',
            PostingConcept::WithholdingSuffered->value => '135515',
            PostingConcept::VatWithholdingSuffered->value => '135517',
            PostingConcept::IcaWithholdingSuffered->value => '135518',
            PostingConcept::Receivables->value => '13050501',
            PostingConcept::Payables->value => '22050501',
            // Each retención posts to its own subcuenta by concept (the tax's account); this is the fallback.
            PostingConcept::WithholdingPracticed->value => '236570',
            PostingConcept::VatWithholdingPracticed->value => '236701',
            PostingConcept::IcaWithholdingPracticed->value => '236801',
            PostingConcept::DefaultExpense->value => '519595',
            PostingConcept::MerchandisePurchases->value => '620501',
        ];
    }
}
