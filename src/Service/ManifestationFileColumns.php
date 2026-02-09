<?php

namespace App\Service;

use Symfony\Contracts\Translation\TranslatorInterface;

final class ManifestationFileColumns
{
    public const REQUIRED_EXPORT_KEYS = ['id', 'title', 'identifier'];

    public const COLUMNS = [
        'id' => [
            'labelKey' => 'Model.Manifestation.fields.Id',
            'getter' => 'getId',
            'importKey' => 'id',
            'headerAliases' => ['ID', 'id'],
        ],
        'title' => [
            'labelKey' => 'Model.Manifestation.fields.Title',
            'getter' => 'getTitle',
            'importKey' => 'title',
        ],
        'titleTranscription' => [
            'labelKey' => 'Model.Manifestation.fields.Title_Transcription',
            'getter' => 'getTitleTranscription',
            'importKey' => 'title_transcription',
        ],
        'identifier' => [
            'labelKey' => 'Model.Manifestation.fields.Identifier',
            'getter' => 'getIdentifier',
            'importKey' => 'identifier',
        ],
        'externalIdentifier1' => [
            'labelKey' => 'Model.Manifestation.fields.External_identifier1',
            'getter' => 'getExternalIdentifier1',
            'importKey' => 'external_identifier1',
        ],
        'externalIdentifier2' => [
            'labelKey' => 'Model.Manifestation.fields.External_identifier2',
            'getter' => 'getExternalIdentifier2',
            'importKey' => 'external_identifier2',
        ],
        'externalIdentifier3' => [
            'labelKey' => 'Model.Manifestation.fields.External_identifier3',
            'getter' => 'getExternalIdentifier3',
            'importKey' => 'external_identifier3',
        ],
        'description' => [
            'labelKey' => 'Model.Manifestation.fields.Description',
            'getter' => 'getDescription',
            'importKey' => 'description',
        ],
        'buyer' => [
            'labelKey' => 'Model.Manifestation.fields.Buyer',
            'getter' => 'getBuyer',
            'importKey' => 'buyer',
        ],
        'buyerIdentifier' => [
            'labelKey' => 'Model.Manifestation.fields.Buyer_identifier',
            'getter' => 'getBuyerIdentifier',
            'importKey' => 'buyer_identifier',
        ],
        'purchaseDate' => [
            'labelKey' => 'Model.Manifestation.fields.Purchase_date',
            'getter' => 'getPurchaseDate',
            'importKey' => 'purchase_date',
        ],
        'recordSource' => [
            'labelKey' => 'Model.Manifestation.fields.RecordSource',
            'getter' => 'getRecordSource',
            'importKey' => 'record_source',
        ],
        'type1' => [
            'labelKey' => 'Model.Manifestation.fields.Type1',
            'getter' => 'getType1',
            'importKey' => 'type1',
        ],
        'type2' => [
            'labelKey' => 'Model.Manifestation.fields.Type2',
            'getter' => 'getType2',
            'importKey' => 'type2',
        ],
        'type3' => [
            'labelKey' => 'Model.Manifestation.fields.Type3',
            'getter' => 'getType3',
            'importKey' => 'type3',
        ],
        'type4' => [
            'labelKey' => 'Model.Manifestation.fields.Type4',
            'getter' => 'getType4',
            'importKey' => 'type4',
        ],
        'class1' => [
            'labelKey' => 'Model.Manifestation.fields.Class1',
            'getter' => 'getClass1',
            'importKey' => 'class1',
        ],
        'class2' => [
            'labelKey' => 'Model.Manifestation.fields.Class2',
            'getter' => 'getClass2',
            'importKey' => 'class2',
        ],
        'extinfo' => [
            'labelKey' => 'Model.Manifestation.fields.Extinfo',
            'getter' => 'getExtinfo',
            'importKey' => 'extinfo',
            'exportForm' => false,
        ],
        'location1' => [
            'labelKey' => 'Model.Manifestation.fields.Location1',
            'getter' => 'getLocation1',
            'importKey' => 'location1',
        ],
        'location2' => [
            'labelKey' => 'Model.Manifestation.fields.Location2',
            'getter' => 'getLocation2',
            'importKey' => 'location2',
        ],
        'location3' => [
            'labelKey' => 'Model.Manifestation.fields.Location3',
            'getter' => 'getLocation3',
            'importKey' => 'location3',
        ],
        'contributor1' => [
            'labelKey' => 'Model.Manifestation.fields.Contributor1',
            'getter' => 'getContributor1',
            'importKey' => 'contributor1',
        ],
        'contributor2' => [
            'labelKey' => 'Model.Manifestation.fields.Contributor2',
            'getter' => 'getContributor2',
            'importKey' => 'contributor2',
        ],
        'status1' => [
            'labelKey' => 'Model.Manifestation.fields.Status1',
            'getter' => 'getStatus1',
            'importKey' => 'status1',
        ],
        'status2' => [
            'labelKey' => 'Model.Manifestation.fields.Status2',
            'getter' => 'getStatus2',
            'importKey' => 'status2',
        ],
        'loanRestriction' => [
            'labelKey' => 'Model.Manifestation.fields.LoanRestriction',
            'getter' => 'getLoanRestriction',
            'importKey' => 'loan_restriction',
            'headerAliases' => ['loan_restriction'],
        ],
        'releaseDateString' => [
            'labelKey' => 'Model.Manifestation.fields.ReleaseDateString',
            'getter' => 'getReleaseDateString',
            'importKey' => 'release_date_string',
        ],
        'price' => [
            'labelKey' => 'Model.Manifestation.fields.Price',
            'getter' => 'getPrice',
            'importKey' => 'price',
        ],
        'priceCurrency' => [
            'labelKey' => 'Model.Manifestation.fields.PriceCurrency',
            'getter' => 'getPriceCurrency',
            'importKey' => 'price_currency',
            'headerAliases' => ['Currency'],
        ],
        'createdAt' => [
            'labelKey' => 'Model.Manifestation.fields.CreatedAt',
            'getter' => 'getCreatedAt',
            'importKey' => 'created_at',
        ],
        'updatedAt' => [
            'labelKey' => 'Model.Manifestation.fields.UpdatedAt',
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
            if (($definition['exportForm'] ?? true) === false) {
                continue;
            }
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
