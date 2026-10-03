<?php

namespace App\Company\Domain\Model;

/**
 * The internal numbering series a company keeps (§4.1 numeración interna), plus the internal consecutive of every
 * sales invoice (§4.8) and the journal's entry numbers.
 */
enum SeriesKind: string
{
    case Quotation = 'quotation';
    case CashReceipt = 'cash_receipt';
    case PurchaseInvoice = 'purchase_invoice';
    case SupplierPayment = 'supplier_payment';
    case SalesInvoiceInternal = 'sales_invoice_internal';
    case JournalEntry = 'journal_entry';

    public function defaultPrefix(): string
    {
        return match ($this) {
            self::Quotation => 'C',
            self::CashReceipt => 'RC',
            self::PurchaseInvoice => 'FC',
            self::SupplierPayment => 'RP',
            self::SalesInvoiceInternal => 'FVI',
            self::JournalEntry => 'CD',
        };
    }

    /** The journal numbers its own entries: only the document series are the owner's to move. */
    public function isEditable(): bool
    {
        return self::JournalEntry !== $this;
    }
}
