<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/settings')]
final class SettingsController extends AbstractController
{
    #[Route('', name: 'app_settings_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('settings/index.html.twig');
    }
}
