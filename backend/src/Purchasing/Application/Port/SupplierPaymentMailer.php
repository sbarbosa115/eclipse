<?php

namespace App\Purchasing\Application\Port;

/** Puts the e-mail with the payment's PDF on the queue, to the supplier's address. */
interface SupplierPaymentMailer
{
    public function send(SupplierPaymentEmail $email): void;
}
