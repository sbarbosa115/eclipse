<?php

namespace App\Shared\UI\Http\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The browser's own locks, on every answer: no type sniffing, never framed by another site, no full URL in the
 * Referer, HSTS over HTTPS. The app's page (HTML) also gets a Content-Security-Policy that runs only the app's own
 * built scripts and styles (templates/spa.html.twig has nothing inline), so an injected tag would not run. Files the
 * API serves (PDFs, logos, attachments) keep their own headers and get no CSP, which would get in the way of the
 * browser's PDF viewer. A header a response already set is kept.
 */
#[AsEventListener(event: KernelEvents::RESPONSE, priority: -10)]
final class SecurityHeaders
{
    public const POLICY = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'";

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $headers = $event->getResponse()->headers;
        $defaults = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ];
        if ($event->getRequest()->isSecure()) {
            $defaults['Strict-Transport-Security'] = 'max-age=31536000';
        }
        if (str_starts_with((string) $headers->get('Content-Type', 'text/html'), 'text/html')) {
            $defaults['Content-Security-Policy'] = self::POLICY;
        }
        foreach ($defaults as $name => $value) {
            if (!$headers->has($name)) {
                $headers->set($name, $value);
            }
        }
    }
}
