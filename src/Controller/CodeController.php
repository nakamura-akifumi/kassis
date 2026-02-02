<?php

namespace App\Controller;

use App\Entity\Code;
use App\Repository\CodeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/settings/code')]
class CodeController extends AbstractController
{
    #[Route('', name: 'app_settings_code', methods: ['GET'])]
    public function index(CodeRepository $codeRepository, \Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface $params): Response
    {
        $codes = $codeRepository->findBy([], ['type' => 'ASC', 'identifier' => 'ASC']);
        $gridData = array_map(static function (Code $code): array {
            return [
                'type' => $code->getType(),
                'identifier' => $code->getIdentifier(),
                'value' => $code->getValue(),
                'displayname' => $code->getDisplayname(),
                'updatedAt' => $code->getUpdatedAt()?->format('Y-m-d H:i'),
            ];
        }, $codes);

        $typeOptions = $params->has('app.code.types') ? $params->get('app.code.types') : [];

        return $this->render('settings/code.html.twig', [
            'codes' => $codes,
            'gridData' => $gridData,
            'typeOptions' => $typeOptions,
        ]);
    }

    #[Route('/create', name: 'app_settings_code_create', methods: ['POST'])]
    public function create(Request $request, CodeRepository $codeRepository, EntityManagerInterface $entityManager, \Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface $params): Response
    {
        $type = trim((string) $request->request->get('type'));
        $identifier = trim((string) $request->request->get('identifier'));
        $value = $request->request->get('value');
        $displayname = trim((string) $request->request->get('displayname'));

        if (!$this->isCsrfTokenValid('code_create', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', '不正なリクエストです。');
            return $this->redirectToRoute('app_settings_code');
        }

        if ($type === '' || $identifier === '') {
            $this->addFlash('danger', '種別と識別子は必須です。');
            return $this->redirectToRoute('app_settings_code');
        }
        $typeOptions = $params->has('app.code.types') ? $params->get('app.code.types') : [];
        if ($typeOptions !== [] && !in_array($type, $typeOptions, true)) {
            $this->addFlash('danger', '種別が不正です。');
            return $this->redirectToRoute('app_settings_code');
        }

        if ($value === null || $value === '') {
            $this->addFlash('danger', '値は必須です。');
            return $this->redirectToRoute('app_settings_code');
        }

        if ($codeRepository->findOneBy(['type' => $type, 'identifier' => $identifier]) !== null) {
            $this->addFlash('danger', '同じ種別と識別子のコードが既に存在します。');
            return $this->redirectToRoute('app_settings_code');
        }

        $code = new Code();
        $code->setType($type);
        $code->setIdentifier($identifier);
        $code->setValue((string) $value);
        $code->setDisplayname($displayname !== '' ? $displayname : null);

        $entityManager->persist($code);
        $entityManager->flush();

        $this->addFlash('success', 'コードを追加しました。');
        return $this->redirectToRoute('app_settings_code');
    }

    #[Route('/update', name: 'app_settings_code_update', methods: ['POST'])]
    public function update(Request $request, CodeRepository $codeRepository, EntityManagerInterface $entityManager, \Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface $params): Response
    {
        if (!$this->isCsrfTokenValid('code_update', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', '不正なリクエストです。');
            return $this->redirectToRoute('app_settings_code');
        }

        $type = trim((string) $request->request->get('type'));
        $identifier = trim((string) $request->request->get('identifier'));
        $value = $request->request->get('value');
        $displayname = trim((string) $request->request->get('displayname'));

        $code = $codeRepository->findOneBy(['type' => $type, 'identifier' => $identifier]);
        if ($code === null) {
            $this->addFlash('danger', '対象のコードが見つかりません。');
            return $this->redirectToRoute('app_settings_code');
        }

        if ($value === null || $value === '') {
            $this->addFlash('danger', '値は必須です。');
            return $this->redirectToRoute('app_settings_code');
        }

        $code->setValue((string) $value);
        $code->setDisplayname($displayname !== '' ? $displayname : null);

        $entityManager->flush();

        $this->addFlash('success', 'コードを更新しました。');
        return $this->redirectToRoute('app_settings_code');
    }

    #[Route('/delete', name: 'app_settings_code_delete', methods: ['POST'])]
    public function delete(Request $request, CodeRepository $codeRepository, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('code_delete', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', '不正なリクエストです。');
            return $this->redirectToRoute('app_settings_code');
        }

        $type = trim((string) $request->request->get('type'));
        $identifier = trim((string) $request->request->get('identifier'));

        $code = $codeRepository->findOneBy(['type' => $type, 'identifier' => $identifier]);
        if ($code === null) {
            $this->addFlash('danger', '対象のコードが見つかりません。');
            return $this->redirectToRoute('app_settings_code');
        }

        $entityManager->remove($code);
        $entityManager->flush();

        $this->addFlash('success', 'コードを削除しました。');
        return $this->redirectToRoute('app_settings_code');
    }
}
