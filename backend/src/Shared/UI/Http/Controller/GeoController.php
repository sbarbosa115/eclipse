<?php

namespace App\Shared\UI\Http\Controller;

use App\Shared\Application\Geo\Municipalities;
use App\Shared\UI\Http\ApiResponse;
use App\Shared\UI\Http\Output\DepartmentOutput;
use App\Shared\UI\Http\Output\MunicipalityOutput;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** DANE's DIVIPOLA for the municipality pickers (observations PRD §4.3). Every signed-in role reads it. */
#[Route('/api/v1/geo')]
final class GeoController extends AbstractController
{
    public function __construct(private readonly Municipalities $municipalities)
    {
    }

    #[Route('/departments', methods: ['GET'])]
    #[ApiResponse(DepartmentOutput::class, key: 'items', list: true)]
    public function departments(): JsonResponse
    {
        return $this->json(['items' => array_map(DepartmentOutput::of(...), $this->municipalities->departments())]);
    }

    /** ?q= part of the name or the start of the code (literal); ?department= a 2-digit code; at most 50. */
    #[Route('/municipalities', methods: ['GET'])]
    #[ApiResponse(MunicipalityOutput::class, key: 'items', list: true)]
    public function municipalities(Request $request): JsonResponse
    {
        $department = $request->query->getString('department');

        return $this->json(['items' => array_map(MunicipalityOutput::of(...), $this->municipalities->search($request->query->getString('q'), '' === $department ? null : $department))]);
    }
}
