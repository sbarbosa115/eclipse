<?php

namespace App\Access\Infrastructure\Mail;

use App\Access\Application\Port\AccessMailer;
use App\Shared\Infrastructure\Mail\QueuedMailer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Address;

/**
 * The invitation and password-reset e-mails (templates/emails/access/), in Spanish, queued on the worker. The link
 * puts the token after `#`: the browser never sends that part to a server, so it stays out of every access log.
 */
final class TwigAccessMailer implements AccessMailer
{
    private const ROLES = ['owner' => 'Administrador', 'billing' => 'Facturación', 'accountant' => 'Contador'];

    public function __construct(
        private readonly QueuedMailer $mailer,
        #[Autowire('%app.url%')]
        private readonly string $appUrl,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $from,
    ) {
    }

    public function invitation(string $to, string $companyName, string $inviterName, string $role, #[\SensitiveParameter] string $token, \DateTimeImmutable $expiresAt): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->from, 'Mustang'))
            ->to($to)
            ->subject(\sprintf('%s te invita a Mustang', $companyName))
            ->htmlTemplate('emails/access/invitation.html.twig')
            ->textTemplate('emails/access/invitation.txt.twig')
            ->context([
                'company_name' => $companyName,
                'inviter_name' => $inviterName,
                'role' => self::ROLES[$role] ?? $role,
                'link' => $this->link('invitacion', $token),
                'expires_on' => $expiresAt->setTimezone(new \DateTimeZone('America/Bogota'))->format('d/m/Y'),
            ]);

        $this->mailer->send($email, 'invitation');
    }

    public function passwordReset(string $to, string $name, #[\SensitiveParameter] string $token): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->from, 'Mustang'))
            ->to($to)
            ->subject('Restablece tu contraseña de Mustang')
            ->htmlTemplate('emails/access/password_reset.html.twig')
            ->textTemplate('emails/access/password_reset.txt.twig')
            ->context([
                'name' => $name,
                'link' => $this->link('restablecer-contrasena', $token),
            ]);

        $this->mailer->send($email, 'password_reset');
    }

    private function link(string $page, string $token): string
    {
        return rtrim($this->appUrl, '/').'/'.$page.'#'.$token;
    }
}
