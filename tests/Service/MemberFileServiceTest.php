<?php

namespace App\Tests\Service;

use App\Entity\Member;
use App\Service\MemberFileService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MemberFileServiceTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private MemberFileService $memberFileService;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->memberFileService = $container->get(MemberFileService::class);

        $this->initDatabase();
    }

    private function initDatabase(): void
    {
        $metaData = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metaData);
        $schemaTool->createSchema($metaData);
    }

    public function testImportMembersFromFile(): void
    {
        $filePath = self::getContainer()->getParameter('kernel.project_dir') . '/tests/resources/membertest1.xlsx';

        if (!file_exists($filePath)) {
            $this->markTestSkipped('テスト用のExcelファイルが見つかりません: ' . $filePath);
        }

        $uploadedFile = new UploadedFile(
            $filePath,
            'membertest1.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $repository = $this->entityManager->getRepository(Member::class);

        $result = $this->memberFileService->importMembersFromFile($uploadedFile);

        $this->assertSame(7, $result['success'], '7件のインポート成功を期待しています。');
        $this->assertSame(3, $result['errors'], '3件のインポート失敗を期待しています。');
        $this->assertNotEmpty($result['errorMessages']);
        $this->assertStringContainsString('不正な利用者グループ1', $result['errorMessages'][0]);

        $this->assertSame(6, count($repository->findAll()), '同一識別子の上書きを含めて6件を期待しています。');

        $member = $repository->findOneBy(['identifier' => 'X001']);
        $this->assertNotNull($member);
        $this->assertSame('貸巣健一', $member->getFullName());
        $this->assertSame('かしすけんいち', $member->getFullNameTranscription());
        $this->assertSame('standard', $member->getGroup1());
        $this->assertSame('ぐるーぷ２あ', $member->getGroup2());
        $this->assertSame('member', $member->getRole());
        $this->assertSame('inactive', $member->getStatus());
        $this->assertSame('要注意人物！出禁 上書き用', $member->getNote());
        $this->assertSame('2027-01-31', $member->getExpiryDate()?->format('Y-m-d'));
        $this->assertSame('090–0000-4321', $member->getCommunicationAddress1());
        $this->assertSame('kenichi@example.com', $member->getCommunicationAddress2());

        $member = $repository->findOneBy(['identifier' => 'V101']);
        $this->assertNotNull($member);
        $this->assertSame('貸巣四郎', $member->getFullName());
        $this->assertSame('vip', $member->getGroup1());

        $member = $repository->findOneBy(['identifier' => 'Z003']);
        $this->assertNotNull($member);
        $this->assertSame('貸巣五郎', $member->getFullName());
        $this->assertSame('guest', $member->getGroup1());

    }
}
