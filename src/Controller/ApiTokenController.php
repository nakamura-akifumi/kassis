<?php

namespace App\Controller;

use App\Entity\ApiToken;
use App\Repository\ApiTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/settings/api-tokens')]
final class ApiTokenController extends AbstractController
{
    #[Route('', name: 'app_settings_api_tokens', methods: ['GET', 'POST'])]
    public function index(Request $request, ApiTokenRepository $apiTokenRepository, EntityManagerInterface $entityManager): Response
    {
        $createdToken = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('api_token_create', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', '不正なリクエストです。');
                return $this->redirectToRoute('app_settings_api_tokens');
            }

            $name = trim((string) $request->request->get('name'));
            if ($name === '') {
                $this->addFlash('danger', 'トークン名を入力してください。');
                return $this->redirectToRoute('app_settings_api_tokens');
            }

            $rawToken = bin2hex(random_bytes(32));
            $expiresAtInput = trim((string) $request->request->get('expires_at'));
            $expiresAt = null;
            if ($expiresAtInput !== '') {
                $expiresAt = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $expiresAtInput);
                if ($expiresAt === false) {
                    $this->addFlash('danger', '有効期限の形式が不正です。');
                    return $this->redirectToRoute('app_settings_api_tokens');
                }
            } else {
                $defaultDays = $this->getParameter('app.api_token.default_expiry_days');
                if (is_numeric($defaultDays) && (int) $defaultDays > 0) {
                    $expiresAt = (new \DateTimeImmutable())->modify('+' . (int) $defaultDays . ' days');
                }
            }
            $token = new ApiToken();
            $token->setName($name);
            $token->setTokenHash(hash('sha256', $rawToken));
            $token->setCreatedAt(new \DateTimeImmutable());
            $token->setExpiresAt($expiresAt);
            $token->setEnabled(true);

            $entityManager->persist($token);
            $entityManager->flush();

            $createdToken = $rawToken;
            $this->addFlash('success', 'トークンを作成しました。必ず控えてください。');
        }

        $tokens = $apiTokenRepository->findBy([], ['id' => 'DESC']);

        return $this->render('settings/api_tokens.html.twig', [
            'tokens' => $tokens,
            'createdToken' => $createdToken,
        ]);
    }

    #[Route('/{id<\\d+>}/toggle', name: 'app_settings_api_tokens_toggle', methods: ['POST'])]
    public function toggle(ApiToken $apiToken, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('api_token_toggle_' . $apiToken->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', '不正なリクエストです。');
            return $this->redirectToRoute('app_settings_api_tokens');
        }

        $apiToken->setEnabled(!$apiToken->isEnabled());
        $entityManager->flush();

        $this->addFlash('success', 'トークンの状態を更新しました。');
        return $this->redirectToRoute('app_settings_api_tokens');
    }

    #[Route('/{id<\\d+>}/delete', name: 'app_settings_api_tokens_delete', methods: ['POST'])]
    public function delete(ApiToken $apiToken, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('api_token_delete_' . $apiToken->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', '不正なリクエストです。');
            return $this->redirectToRoute('app_settings_api_tokens');
        }

        $entityManager->remove($apiToken);
        $entityManager->flush();

        $this->addFlash('success', 'トークンを削除しました。');
        return $this->redirectToRoute('app_settings_api_tokens');
    }
}
