<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class OpenApiController extends AbstractController
{
    #[Route('/openapi.yaml', name: 'api_openapi', methods: ['GET'])]
    public function openapi(): Response
    {
        $path = $this->getParameter('kernel.project_dir') . '/public/openapi.yaml';
        $content = file_get_contents($path);
        if ($content === false) {
            return new Response('OpenAPI not found', 404);
        }

        return new Response($content, 200, [
            'Content-Type' => 'application/yaml',
        ]);
    }
}
