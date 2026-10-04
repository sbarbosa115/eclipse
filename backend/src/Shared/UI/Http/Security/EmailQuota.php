<?php

namespace App\Shared\UI\Http\Security;

use App\Shared\UI\Http\ApiException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * How many e-mails a company may have the app send in an hour (config/packages/rate_limiter.yaml): documents to
 * terceros (send, emit-and-send, "Guardar y enviar") and invitations. Anyone can sign up, and a tercero's e-mail is
 * whatever the user types, so without a bound the app would be a relay for mail under its own sender. Asked before
 * anything is changed: a refusal (429 `too_many_emails`) emits nothing and sends nothing.
 */
final class EmailQuota
{
    public function __construct(
        #[Autowire(service: 'limiter.document_email')]
        private readonly RateLimiterFactoryInterface $documents,
        #[Autowire(service: 'limiter.invitation_email')]
        private readonly RateLimiterFactoryInterface $invitations,
    ) {
    }

    /** One document e-mail (a factura, cotización, recibo de caja or de pago) to a tercero. */
    public function spendOnDocument(SignedInUser $user): void
    {
        self::spend($this->documents, $user);
    }

    /** One invitation e-mail (a new invitation or a resent one). */
    public function spendOnInvitation(SignedInUser $user): void
    {
        self::spend($this->invitations, $user);
    }

    private static function spend(RateLimiterFactoryInterface $limiter, SignedInUser $user): void
    {
        if (!$limiter->create($user->companyId()->toRfc4122())->consume()->isAccepted()) {
            throw ApiException::tooManyRequests('too_many_emails', 'This company has sent too many e-mails in the last hour. Try again later.');
        }
    }
}
