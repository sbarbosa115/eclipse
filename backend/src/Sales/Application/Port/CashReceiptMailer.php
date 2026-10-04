<?php

namespace App\Sales\Application\Port;

/** Puts the e-mail with the receipt's PDF on the queue, to the client's billing address. */
interface CashReceiptMailer
{
    public function send(CashReceiptEmail $email): void;
}
