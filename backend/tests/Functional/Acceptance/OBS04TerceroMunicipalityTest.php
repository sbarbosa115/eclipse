<?php

namespace App\Tests\Functional\Acceptance;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\SignsUp;
use Symfony\Component\Uid\Uuid;

/**
 * OBS-4: saving a Colombian tercero without municipio is refused on `municipality_code`, a foreign one needs its
 * ciudad, and the terceros migrated without one are counted by `?missing_municipality=1` until they are saved again.
 */
final class OBS04TerceroMunicipalityTest extends ApiTestCase
{
    use SignsUp;

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function tercero(array $override = []): array
    {
        return $override + ['person_type' => 'empresa', 'identification_type' => 'nit', 'identification_number' => '890903938', 'business_name' => 'Cliente S.A.S.', 'roles' => ['cliente']];
    }

    public function testTheMunicipalityRulesAndTheMigrationPath(): void
    {
        $this->signUp();

        $body = $this->sendJson('POST', '/api/v1/terceros', $this->tercero());
        self::assertResponseStatusCodeSame(422, 'A Colombian tercero without municipio is refused.');
        self::assertSame('municipality_code', $body['violations'][0]['field']);

        $body = $this->sendJson('POST', '/api/v1/terceros', $this->tercero(['country_code' => 'PA']));
        self::assertResponseStatusCodeSame(422, 'A foreign tercero needs its ciudad.');
        self::assertSame('city', $body['violations'][0]['field']);

        $this->sendJson('POST', '/api/v1/terceros', $this->tercero(['country_code' => 'PA', 'city' => 'Panamá', 'identification_number' => '800197268']));
        self::assertResponseStatusCodeSame(201);
        $old = $this->sendJson('POST', '/api/v1/terceros', $this->tercero(['municipality_code' => '08001']));
        self::assertResponseStatusCodeSame(201);

        static::getContainer()->get('doctrine.orm.entity_manager')->getConnection()->update('tercero', ['municipality_code' => null], ['id' => Uuid::fromString($old['id'])->toBinary()]);

        self::assertSame(1, $this->getJson('/api/v1/terceros?missing_municipality=1')['total'], 'The migrated one is counted; the foreign one is not.');

        $this->sendJson('PUT', '/api/v1/terceros/'.$old['id'], $this->tercero());
        self::assertResponseStatusCodeSame(422, 'Its next save requires the municipio.');
        $saved = $this->sendJson('PUT', '/api/v1/terceros/'.$old['id'], $this->tercero(['municipality_code' => '08001']));
        self::assertSame('BARRANQUILLA', $saved['municipality_name']);
        self::assertSame(0, $this->getJson('/api/v1/terceros?missing_municipality=1')['total']);
    }
}
