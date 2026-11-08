<?php

namespace App\Tests\Functional\Observations;

/**
 * The retenciones the observations tests combine on one line (PRD §4.2, OBS-2/OBS-3): the seed has Retefuente and
 * ReteIVA; a ReteICA (0,966 %, Bogotá's for services) is created by the owner, without accounts of its own, so it posts
 * to its kind's concept (135518 on sales, 2368 on purchases).
 *
 * @mixin \App\Tests\Support\ApiTestCase
 */
trait WithholdingTaxes
{
    private ?string $reteIca = null;

    protected function reteIca(): string
    {
        if (null !== $this->reteIca) {
            return $this->reteIca;
        }
        $tax = $this->sendJson('POST', '/api/v1/taxes', ['name' => 'ReteICA 0,966 %', 'tax_class' => 'withholding', 'kind' => 'reteica', 'calculation' => 'percentage', 'rate' => '0.9660']);
        self::assertResponseStatusCodeSame(201, 'The ReteICA is created: '.json_encode($tax));

        return $this->reteIca = (string) $tax['id'];
    }

    /** The seeded "Ninguno" retención (kind none): a product's default may be it, and the editor sends it as is. */
    protected function noWithholding(): string
    {
        foreach ($this->getJson('/api/v1/taxes?class=withholding&all=1')['items'] as $tax) {
            if ('none' === $tax['kind']) {
                return (string) $tax['id'];
            }
        }
        self::fail('No seeded "Ninguno" retención.');
    }

    /**
     * @return list<array{tax_id: string}>
     */
    protected static function withholdings(string ...$taxIds): array
    {
        return array_map(static fn (string $id) => ['tax_id' => $id], array_values($taxIds));
    }
}
