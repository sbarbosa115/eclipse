<?php

namespace App\Tests\Functional\Security;

use App\Tests\Support\ApiTestCase;

/**
 * Security audit 2026-10-04, finding 4: no response carried a Content-Security-Policy, a framing rule, a referrer
 * policy or HSTS. Every response now does (HSTS over HTTPS only), and the app's page a policy that runs only the
 * app's own scripts.
 */
final class SecurityHeadersTest extends ApiTestCase
{
    public function testThePageRunsOnlyTheAppsOwnScriptsAndIsNeverFramed(): void
    {
        $this->client->request('GET', '/facturas-venta');

        self::assertResponseIsSuccessful();
        $policy = (string) $this->client->getResponse()->headers->get('Content-Security-Policy');
        self::assertStringContainsString("default-src 'self'", $policy);
        self::assertStringContainsString("script-src 'self'", $policy);
        self::assertStringContainsString("object-src 'none'", $policy);
        self::assertStringContainsString("frame-ancestors 'none'", $policy);
        self::assertStringNotContainsString('unsafe-eval', $policy);
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function testApiAnswersCarryTheHeadersToo(): void
    {
        $this->client->request('GET', '/api/v1/me');

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
        self::assertResponseHeaderSame('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function testHstsOnlyOverHttps(): void
    {
        $this->client->request('GET', '/api/v1/me');
        self::assertResponseNotHasHeader('Strict-Transport-Security');

        $this->client->request('GET', 'https://localhost/api/v1/me');
        self::assertResponseHeaderSame('Strict-Transport-Security', 'max-age=31536000');
    }
}
