<?php

namespace App\Tests\Support;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * A functional test of the JSON API. Each test runs in a transaction that is rolled back afterwards
 * (DAMA\DoctrineTestBundle), so tests never see each other's rows.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Rate limits are counted in a cache that outlives the test: every test starts with none used.
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    protected function save(object ...$entities): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        foreach ($entities as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        $em->clear();
    }

    /**
     * @return array<mixed>
     */
    protected function getJson(string $uri): array
    {
        $this->client->request('GET', $uri, server: ['HTTP_ACCEPT' => 'application/json']);

        return $this->body();
    }

    /**
     * @param array<mixed> $payload
     *
     * @return array<mixed>
     */
    protected function sendJson(string $method, string $uri, array $payload): array
    {
        $this->client->request($method, $uri, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: json_encode($payload, \JSON_THROW_ON_ERROR));

        return $this->body();
    }

    /**
     * @return array<mixed>
     */
    protected function body(): array
    {
        $content = (string) $this->client->getResponse()->getContent();

        return '' === $content ? [] : (array) json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
    }
}
