<?php

namespace App\Shared\Domain\Accounting;

/**
 * An accounting concept a document posts to (§5, principle 2: posting rules are data). Each company maps every
 * concept to one PUC account in its posting rules (Ledger), which the accountant may change; documents name the
 * concept, never the account. A later stage adds concepts here (salario, cesantías, inventario…).
 */
enum PostingConcept: string
{
    case Revenue = 'ingreso';
    case SalesDiscount = 'descuento_ventas';
    case VatGenerated = 'iva_generado';
    case VatDeductible = 'iva_descontable';
    case ConsumptionTax = 'impoconsumo';
    case WithholdingSuffered = 'retefuente_sufrida';
    case VatWithholdingSuffered = 'reteiva_sufrida';
    case IcaWithholdingSuffered = 'reteica_sufrida';
    case Receivables = 'clientes';
    case Payables = 'proveedores';
    case WithholdingPracticed = 'retefuente_practicada';
    case VatWithholdingPracticed = 'reteiva_practicada';
    case IcaWithholdingPracticed = 'reteica_practicada';
    case DefaultExpense = 'gasto_por_defecto';
    case MerchandisePurchases = 'compra_mercancias';
}
