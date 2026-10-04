<?php

namespace App\Shared\UI\Http\Spa;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The React app's HTML shell for every page URL (/c/{id}, /register, /maps…): the router in the browser decides what
 * to show. API paths and real files never reach it — /api/ is excluded here, and Apache/nginx serve files first.
 */
final class SpaController extends AbstractController
{
    #[Route('/{path}', name: 'spa', requirements: ['path' => '(?!api/|api$|_).*'], methods: ['GET'], priority: -100)]
    public function __invoke(): Response
    {
        return $this->render('spa.html.twig');
    }
}
