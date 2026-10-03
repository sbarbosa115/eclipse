<?php

namespace App\Shared\UI\Http;

/**
 * What an endpoint answers, in one line, for the OpenAPI schema and so for the TypeScript types the UI imports:
 *
 *     #[ApiResponse(ContractOutput::class)]                    one ContractOutput
 *     #[ApiResponse(ContractOutput::class, page: true)]        {items: ContractOutput[], total, page, per_page}
 *     #[ApiResponse(RequirementOutput::class, key: 'requirements', list: true)]   {requirements: RequirementOutput[]}
 *     #[ApiResponse(ContractOutput::class, status: 201)]       created
 *
 * Plain PHP: production reads nothing from it. OpenApi\ApiResponseDescriber (dev and test only, where
 * NelmioApiDocBundle is installed) turns it into the operation's response.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final readonly class ApiResponse
{
    /**
     * @param class-string $output the Output DTO
     * @param bool         $page   a page of a list endpoint (items, total, page, per_page)
     * @param bool         $list   a JSON array of them
     * @param string|null  $key    the object key they are wrapped in, if any ({"items": [...]})
     */
    public function __construct(
        public string $output,
        public bool $page = false,
        public bool $list = false,
        public ?string $key = null,
        public int $status = 200,
    ) {
    }
}
