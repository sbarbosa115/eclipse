<?php

namespace App\Sales\Domain\Model;

use App\Shared\Domain\Model\OpenItem;
use Doctrine\ORM\Mapping as ORM;

/** What a client owes on one crédito line of a sales invoice (cartera de clientes). */
#[ORM\Entity]
#[ORM\Table(name: 'receivable')]
#[ORM\Index(name: 'receivable_open', columns: ['company_id', 'tercero_id', 'voided', 'due_date'])]
#[ORM\Index(name: 'receivable_invoice', columns: ['company_id', 'invoice_id'])]
class Receivable extends OpenItem
{
}
