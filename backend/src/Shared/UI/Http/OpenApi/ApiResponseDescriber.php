<?php

namespace App\Shared\UI\Http\OpenApi;

use App\Shared\UI\Http\ApiResponse;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareInterface;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareTrait;
use Nelmio\ApiDocBundle\Model\Model;
use Nelmio\ApiDocBundle\OpenApiPhp\Util;
use Nelmio\ApiDocBundle\RouteDescriber\RouteDescriberInterface;
use Nelmio\ApiDocBundle\RouteDescriber\RouteDescriberTrait;
use OpenApi\Annotations as OA;
use OpenApi\Generator;
use Symfony\Component\Routing\Route;
use Symfony\Component\TypeInfo\Type;

/**
 * Turns #[ApiResponse] into the operation's OpenAPI response, with the Output DTO registered as a schema.
 *
 * Dev and test only (config/services.yaml): it needs NelmioApiDocBundle, which production does not install.
 */
final class ApiResponseDescriber implements RouteDescriberInterface, ModelRegistryAwareInterface
{
    use ModelRegistryAwareTrait;
    use RouteDescriberTrait;

    public function describe(OA\OpenApi $api, Route $route, \ReflectionMethod $reflectionMethod): void
    {
        $attributes = $reflectionMethod->getAttributes(ApiResponse::class);
        if ([] === $attributes) {
            return;
        }

        // Several for one status: a oneOf.
        $byStatus = [];
        foreach ($attributes as $attribute) {
            $response = $attribute->newInstance();
            $byStatus[$response->status][] = $response;
        }
        foreach ($this->getOperations($api, $route) as $operation) {
            foreach ($byStatus as $responses) {
                if (1 === \count($responses)) {
                    $this->describeResponse($operation, $responses[0]);
                } else {
                    $this->describeAlternatives($operation, $responses);
                }
            }
        }
    }

    private function describeResponse(OA\Operation $operation, ApiResponse $response): void
    {
        $ref = $this->modelRegistry->register(new Model(Type::object($response->output)));

        /** @var OA\Response $openApiResponse */
        $openApiResponse = Util::getIndexedCollectionItem($operation, OA\Response::class, $response->status);
        if (Generator::UNDEFINED === $openApiResponse->description) {
            $openApiResponse->description = 201 === $response->status ? 'Created.' : 'OK.';
        }
        /** @var OA\MediaType $json */
        $json = Util::getIndexedCollectionItem($openApiResponse, OA\MediaType::class, 'application/json');
        /** @var OA\Schema $schema */
        $schema = Util::getChild($json, OA\Schema::class);

        // {key: …}: the DTOs sit under one property of an object.
        if (null !== $response->key) {
            $schema->type = 'object';
            $schema->required = [$response->key];
            /** @var OA\Property $schema */
            $schema = Util::getCollectionItem($schema, OA\Property::class, ['property' => $response->key]);
        }

        if ($response->page) {
            $schema->type = 'object';
            $schema->required = ['items', 'total', 'page', 'per_page'];
            self::arrayOf(Util::getCollectionItem($schema, OA\Property::class, ['property' => 'items']), $ref);
            foreach (['total', 'page', 'per_page'] as $name) {
                Util::getCollectionItem($schema, OA\Property::class, ['property' => $name, 'type' => 'integer']);
            }
        } elseif ($response->list) {
            self::arrayOf($schema, $ref);
        } else {
            $schema->ref = $ref;
        }
    }

    /**
     * @param non-empty-list<ApiResponse> $responses plain DTOs of one status
     */
    private function describeAlternatives(OA\Operation $operation, array $responses): void
    {
        /** @var OA\Response $openApiResponse */
        $openApiResponse = Util::getIndexedCollectionItem($operation, OA\Response::class, $responses[0]->status);
        if (Generator::UNDEFINED === $openApiResponse->description) {
            $openApiResponse->description = 'One of these.';
        }
        /** @var OA\MediaType $json */
        $json = Util::getIndexedCollectionItem($openApiResponse, OA\MediaType::class, 'application/json');
        /** @var OA\Schema $schema */
        $schema = Util::getChild($json, OA\Schema::class);
        $schema->oneOf = array_map(
            fn (ApiResponse $response) => new OA\Schema(['ref' => $this->modelRegistry->register(new Model(Type::object($response->output))), '_context' => $schema->_context]),
            $responses,
        );
    }

    private static function arrayOf(OA\Schema $schema, string $ref): void
    {
        $schema->type = 'array';
        Util::getChild($schema, OA\Items::class, ['ref' => $ref]);
    }
}
