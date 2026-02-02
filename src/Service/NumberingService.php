<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;

class NumberingService
{
    private const TYPE_NUMBERING = 'numbering';
    private const IDENTIFIER_MANIFESTATION = 'manifestation_identifier';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private string $manifestationIdentifierRule,
        private string $manifestationIdentifierFormat,
    ) {
    }

    public function generateIdentifier(?string $isbn, bool $withUnique = true): string
    {
        $rule = strtolower(trim($this->manifestationIdentifierRule));

        return match ($rule) {
            'uuidv7' => $this->generateUuidV7(),
            'numbering' => $this->generateNumbering(),
            'isbn+random(4)', 'random' => $this->generateIsbnRandom($isbn, $withUnique),
            default => $this->generateIsbnRandom($isbn, $withUnique),
        };
    }

    private function generateUuidV7(): string
    {
        $hex = Uuid::v7()->toHex();
        if (str_starts_with($hex, '0x')) {
            return substr($hex, 2);
        }
        return $hex;
    }

    private function generateNumbering(): string
    {
        $conn = $this->entityManager->getConnection();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $conn->beginTransaction();
        try {
            $row = $conn->fetchAssociative(
                'SELECT value FROM code WHERE type = ? AND identifier = ? FOR UPDATE',
                [self::TYPE_NUMBERING, self::IDENTIFIER_MANIFESTATION]
            );

            if ($row === false || $row === null) {
                $value = 1;
                $conn->insert('code', [
                    'type' => self::TYPE_NUMBERING,
                    'identifier' => self::IDENTIFIER_MANIFESTATION,
                    'value' => (string) $value,
                    'displayname' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $current = (int) $row['value'];
                $value = $current + 1;
                $conn->update('code', [
                    'value' => (string) $value,
                    'updated_at' => $now,
                ], [
                    'type' => self::TYPE_NUMBERING,
                    'identifier' => self::IDENTIFIER_MANIFESTATION,
                ]);
            }

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollBack();
            throw $e;
        }

        return sprintf($this->manifestationIdentifierFormat, $value);
    }

    private function generateIsbnRandom(?string $isbn, bool $withUnique = true): string
    {
        $base = null;

        if (!$this->isBlank($isbn)) {
            $base = preg_replace('/[^0-9Xx]/', '', (string) $isbn);
        }

        if ($withUnique) {
            if ($this->isBlank($base)) {
                return bin2hex(random_bytes(4));
            }
            $base = rtrim((string) $base, '-') . '-' . bin2hex(random_bytes(4));
        }

        return $base;
    }

    private function isBlank(?string $value): bool
    {
        return $value === null || trim($value) === '';
    }
}
