<?php

namespace App\Purchasing\Application\Port;

/** Puts the e-mail with the order's PDF on the queue, to the supplier's address. */
interface PurchaseOrderMailer
{
    public function send(PurchaseOrderEmail $email): void;
}
