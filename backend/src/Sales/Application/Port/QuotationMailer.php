<?php

namespace App\Sales\Application\Port;

/** Puts the e-mail with the quotation's PDF on the queue, to the client's billing address. */
interface QuotationMailer
{
    public function send(QuotationEmail $email): void;
}
