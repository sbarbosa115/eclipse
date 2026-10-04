<?php

namespace App\Sales\Application\Port;

/** Puts the e-mail with the invoice's PDF on the queue, to the client's billing address. */
interface SalesInvoiceMailer
{
    public function send(SalesInvoiceEmail $email): void;
}
