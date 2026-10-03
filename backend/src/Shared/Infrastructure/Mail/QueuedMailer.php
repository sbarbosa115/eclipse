<?php

namespace App\Shared\Infrastructure\Mail;

use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Exception\ExceptionInterface as MessengerException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Mime\BodyRendererInterface;

/**
 * Sends an email through the `async` queue instead of while the request waits: the worker (a cron run every minute on
 * cPanel) delivers it, and retries it when the mail server is busy (config/packages/messenger.yaml).
 *
 * The body is rendered here, so the worker only hands finished MIME to the transport. A failure to queue is logged and
 * never undoes the change that triggered the email.
 */
final class QueuedMailer
{
    public const TRANSPORT = 'async';

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly BodyRendererInterface $renderer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function send(TemplatedEmail $email, string $key): void
    {
        try {
            $this->renderer->render($email);
            $this->bus->dispatch(new SendEmailMessage($email), [new TransportNamesStamp([self::TRANSPORT])]);
        } catch (MessengerException $e) {
            $this->logger->error('Could not queue the "{key}" email.', ['key' => $key, 'exception' => $e]);
        }
    }
}
