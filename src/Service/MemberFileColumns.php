<?php

namespace App\Service;

use Symfony\Contracts\Translation\TranslatorInterface;

final class MemberFileColumns
{
    public const REQUIRED_EXPORT_KEYS = ['id', 'identifier', 'fullName'];

    public const COLUMNS = [
        'id' => [
            'labelKey' => 'Model.Member.fields.Id',
            'getter' => 'getId',
            'importKey' => 'id',
            'headerAliases' => ['ID', 'id'],
        ],
        'identifier' => [
            'labelKey' => 'Model.Member.fields.Identifier',
            'getter' => 'getIdentifier',
            'importKey' => 'identifier',
        ],
        'fullName' => [
            'labelKey' => 'Model.Member.fields.FullName',
            'getter' => 'getFullName',
            'importKey' => 'full_name',
        ],
        'fullNameTranscription' => [
            'labelKey' => 'Model.Member.fields.FullNameTranscription',
            'getter' => 'getFullNameTranscription',
            'importKey' => 'full_name_transcription',
            'headerAliases' => ['full_name_yomi', 'フルネームヨミ'],
        ],
        'group1' => [
            'labelKey' => 'Model.Member.fields.Group1',
            'getter' => 'getGroup1',
            'importKey' => 'group1',
        ],
        'group2' => [
            'labelKey' => 'Model.Member.fields.Group2',
            'getter' => 'getGroup2',
            'importKey' => 'group2',
        ],
        'communicationAddress1' => [
            'labelKey' => 'Model.Member.fields.CommunicationAddress1',
            'getter' => 'getCommunicationAddress1',
            'importKey' => 'communication_address1',
        ],
        'communicationAddress2' => [
            'labelKey' => 'Model.Member.fields.CommunicationAddress2',
            'getter' => 'getCommunicationAddress2',
            'importKey' => 'communication_address2',
        ],
        'role' => [
            'labelKey' => 'Model.Member.fields.Role',
            'getter' => 'getRole',
            'importKey' => 'role',
        ],
        'status' => [
            'labelKey' => 'Model.Member.fields.Status',
            'getter' => 'getStatusLabel',
            'importKey' => 'status',
        ],
        'note' => [
            'labelKey' => 'Model.Member.fields.Note',
            'getter' => 'getNote',
            'importKey' => 'note',
        ],
        'expiryDate' => [
            'labelKey' => 'Model.Member.fields.ExpiryDate',
            'getter' => 'getExpiryDate',
            'importKey' => 'expiry_date',
        ],
        'createdAt' => [
            'labelKey' => 'Model.Member.fields.CreatedAt',
            'getter' => 'getCreatedAt',
            'importKey' => 'created_at',
        ],
        'updatedAt' => [
            'labelKey' => 'Model.Member.fields.UpdatedAt',
            'getter' => 'getUpdatedAt',
            'importKey' => 'updated_at',
        ],
    ];

    /**
     * @return array<string, array{label: string, getter: string}>
     */
    public static function getExportColumns(TranslatorInterface $translator): array
    {
        $columns = [];
        foreach (self::COLUMNS as $key => $definition) {
            if (!isset($definition['getter'])) {
                continue;
            }
            $columns[$key] = [
                'label' => $translator->trans($definition['labelKey']),
                'getter' => $definition['getter'],
            ];
        }
        return $columns;
    }

    /**
     * @return array<int, array{key: string, labelKey: string}>
     */
    public static function getExportFormFields(): array
    {
        $fields = [];
        foreach (self::COLUMNS as $key => $definition) {
            $fields[] = [
                'key' => $key,
                'labelKey' => $definition['labelKey'],
            ];
        }
        return $fields;
    }

    /**
     * @return string[]
     */
    public static function getImportKeyList(): array
    {
        $keys = [];
        foreach (self::COLUMNS as $definition) {
            $keys[] = $definition['importKey'];
        }
        return $keys;
    }

    /**
     * @return array<string, string>
     */
    public static function getImportHeaderLabelMap(TranslatorInterface $translator): array
    {
        $map = [];
        foreach (self::COLUMNS as $definition) {
            $label = $translator->trans($definition['labelKey']);
            $map[$label] = $definition['importKey'];
            foreach (($definition['headerAliases'] ?? []) as $alias) {
                $map[$alias] = $definition['importKey'];
            }
        }
        return $map;
    }
}
