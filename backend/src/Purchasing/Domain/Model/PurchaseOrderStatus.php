<?php

namespace App\Purchasing\Domain\Model;

/** An orden de compra's life (observations PRD §4.8): draft → emitted → converted | voided. */
enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Emitted = 'emitted';
    case Converted = 'converted';
    case Voided = 'voided';
}
