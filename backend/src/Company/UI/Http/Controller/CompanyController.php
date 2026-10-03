<?php

namespace App\Company\UI\Http\Controller;

use App\Company\Application\Profile\RemoveLogo;
use App\Company\Application\Profile\ReplaceLogo;
use App\Company\Application\Profile\UpdateCompanyProfile;
use App\Company\Application\Query\Companies;
use App\Company\Application\Query\LogoReader;
use App\Company\Domain\Error\LogoRejected;
use App\Company\Domain\Error\LogoTooLarge;
use App\Company\UI\Http\Input\CompanyInput;
use App\Company\UI\Http\Output\CompanyOutput;
use App\Shared\Application\Command\CommandBus;
use App\Shared\UI\Http\ApiResponse;
use App\Shared\UI\Http\InputMapper;
use App\Shared\UI\Http\Security\SignedInUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The company's profile (§4.1): every role reads it, the owner changes it. Each change is written to the audit log.
 */
#[Route('/api/v1/company')]
final class CompanyController extends AbstractController
{
    use EditsCompany;

    private const LOGO_MAX_BYTES = 2 * 1024 * 1024;
    private const LOGO_TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg'];

    public function __construct(
        private readonly InputMapper $inputs,
        private readonly CommandBus $commands,
        private readonly Companies $companies,
        private readonly LogoReader $logos,
    ) {
    }

    /**
     * The company's razón social, NIT, address, régimen, responsabilidades fiscales, default taxes and logo.
     */
    #[Route('', methods: ['GET'])]
    #[ApiResponse(CompanyOutput::class)]
    public function show(#[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->json(CompanyOutput::of($this->companies->view($user->companyId())));
    }

    /**
     * Changes the profile (owner). The check digit is computed for a NIT when empty. The default taxes must be active
     * taxes of their class (cargo / retención); the NIT cannot be another company's.
     */
    #[Route('', methods: ['PUT'])]
    #[ApiResponse(CompanyOutput::class)]
    public function update(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->requireOwner($user);
        $input = $this->inputs->map($this->inputs->json($request), CompanyInput::class);
        $this->commands->dispatch(new UpdateCompanyProfile(
            $user->companyId(), $user->userId(), $input->legalName, $input->tradeName, $input->identificationType, $input->identificationNumber, $input->checkDigit, $input->address, $input->city, $input->phone, $input->email, $input->vatRegime,
            [] === $input->fiscalResponsibilities ? ['R-99-PN'] : $input->fiscalResponsibilities,
            $input->defaultChargeTaxId, $input->defaultWithholdingTaxId,
        ));

        return $this->json(CompanyOutput::of($this->companies->view($user->companyId())));
    }

    /**
     * Uploads the logo (owner), multipart field `file`: PNG or JPEG judged by its content, 2 MB at most. Replaces the
     * previous one.
     */
    #[Route('/logo', methods: ['POST'])]
    #[ApiResponse(CompanyOutput::class)]
    public function uploadLogo(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->requireOwner($user);
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new LogoRejected();
        }
        if (\UPLOAD_ERR_INI_SIZE === $file->getError() || \UPLOAD_ERR_FORM_SIZE === $file->getError()) {
            throw new LogoTooLarge();
        }
        if (!$file->isValid()) {
            throw new LogoRejected();
        }
        if ($file->getSize() > self::LOGO_MAX_BYTES) {
            throw new LogoTooLarge();
        }
        $type = $this->contentTypeOf($file->getPathname());
        $this->commands->dispatch(new ReplaceLogo($user->companyId(), $user->userId(), $file->getPathname(), $file->getClientOriginalName(), $type));

        return $this->json(CompanyOutput::of($this->companies->view($user->companyId())));
    }

    /**
     * The logo's image (every role). 404 `logo_not_found` without one.
     */
    #[Route('/logo', methods: ['GET'])]
    public function logo(#[CurrentUser] SignedInUser $user): Response
    {
        $logo = $this->logos->logo($user->companyId());
        $response = new BinaryFileResponse($logo->path, 200, ['Content-Type' => $logo->contentType, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=0, must-revalidate']);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, 'logo.'.(self::LOGO_TYPES[$logo->contentType] ?? 'bin'));

        return $response;
    }

    /**
     * Removes the logo (owner): 204.
     */
    #[Route('/logo', methods: ['DELETE'])]
    public function removeLogo(#[CurrentUser] SignedInUser $user): Response
    {
        $this->requireOwner($user);
        $this->commands->dispatch(new RemoveLogo($user->companyId(), $user->userId()));

        return new Response(null, 204);
    }

    /** PNG or JPEG by the bytes, never by the name or the type the browser claims. */
    private function contentTypeOf(string $path): string
    {
        $type = (new \finfo(\FILEINFO_MIME_TYPE))->file($path);
        if (false === $type || !isset(self::LOGO_TYPES[$type]) || false === @getimagesize($path)) {
            throw new LogoRejected();
        }

        return $type;
    }
}
