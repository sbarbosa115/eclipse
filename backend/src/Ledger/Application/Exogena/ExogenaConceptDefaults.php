<?php

namespace App\Ledger\Application\Exogena;

/**
 * The concept table a company starts with (observations PRD §4.7 d): the obvious PUC families of formats 1001 (pagos)
 * and 1007 (ingresos). The accountant edits it in Configuración › Exógena; Version20261108120000 seeded the same rows
 * for the companies that existed before it.
 */
final class ExogenaConceptDefaults
{
    /** format, account prefix, DIAN concept */
    public const ROWS = [
        ['1001', '5110', '5001'], // honorarios
        ['1001', '5135', '5003'], // servicios
        ['1001', '5120', '5004'], // arrendamientos
        ['1001', '6205', '5007'], // compras (costo de ventas)
        ['1001', '1435', '5007'], // compras (inventario de mercancías)
        ['1001', '5195', '5016'], // otros gastos diversos
        ['1007', '41', '4001'], // ingresos operacionales
        ['1007', '42', '4002'], // ingresos no operacionales
    ];
}
