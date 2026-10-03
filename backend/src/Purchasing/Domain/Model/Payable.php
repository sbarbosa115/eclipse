<?php

namespace App\Purchasing\Domain\Model;

use App\Shared\Domain\Model\OpenItem;
use Doctrine\ORM\Mapping as ORM;

/** What the company owes a supplier on one crédito line of a purchase invoice (cartera de proveedores). */
#[ORM\Entity]
#[ORM\Table(name: 'payable')]
#[ORM\Index(name: 'payable_open', columns: ['company_id', 'tercero_id', 'voided', 'due_date'])]
#[ORM\Index(name: 'payable_invoice', columns: ['company_id', 'invoice_id'])]
class Payable extends OpenItem
{
}
