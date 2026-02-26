<?php

namespace App\Form;

use App\Service\ManifestationStatusResolver;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Contracts\Translation\TranslatorInterface;

class ManifestationFileImportFormType extends AbstractType
{
    public function __construct(
        private ParameterBagInterface $params,
        private ManifestationStatusResolver $statusResolver,
        private TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('uploadFile', FileType::class, [
            'label' => 'インポートファイル（.xlsx / .csv）',
            'mapped' => false,
            'required' => true,
            'constraints' => [
                new File([
                    'maxSize' => '50M',
                    'mimeTypes' => [
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'text/csv',
                        'text/plain',
                        'application/csv',
                        'application/vnd.ms-excel',
                    ],
                    'mimeTypesMessage' => 'Excel（.xlsx）またはCSV（.csv）ファイルをアップロードしてください',
                ]),
            ],
            'attr' => [
                'class' => 'form-control form-control-sm',
                'accept' => '.xlsx,.csv',
            ],
            'help' => '「/file/export」で出力できる列構成（ID/タイトル/著者/出版社/出版年/ISBN）のファイルをアップロードしてください。',
        ]);

        if ($this->isStatusSelectable()) {
            $builder->add('defaultStatus', ChoiceType::class, [
                'label' => '新規レコードのステータス',
                'mapped' => false,
                'required' => false,
                'placeholder' => 'ファイルの値を優先',
                'choices' => $this->buildStatusChoices(),
                'attr' => [
                    'class' => 'form-select form-select-sm',
                ],
                'help' => 'ステータス列が空の場合のみ、新規作成分に適用されます。',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }

    private function isStatusSelectable(): bool
    {
        if (!$this->params->has('app.manifestation.import_status_selectable')) {
            return false;
        }

        return (bool) $this->params->get('app.manifestation.import_status_selectable');
    }

    /**
     * @return array<string, string>
     */
    private function buildStatusChoices(): array
    {
        $choices = [];
        $configured = $this->params->has('app.manifestation.import_status_choices')
            ? (array) $this->params->get('app.manifestation.import_status_choices')
            : [];

        foreach ($configured as $status) {
            if (!is_string($status) || trim($status) === '') {
                continue;
            }
            $normalized = $this->statusResolver->normalize($status);
            if ($normalized === null) {
                continue;
            }
            $labelKey = 'Model.Manifestation.values.Status.' . $normalized;
            $translated = $this->translator->trans($labelKey);
            $choices[$translated !== $labelKey ? $translated : $normalized] = $normalized;
        }

        if ($choices !== []) {
            return $choices;
        }

        $fallback = ['New', 'Ordered', 'Available'];
        foreach ($fallback as $status) {
            $labelKey = 'Model.Manifestation.values.Status.' . $status;
            $translated = $this->translator->trans($labelKey);
            $choices[$translated !== $labelKey ? $translated : $status] = $status;
        }

        return $choices;
    }
}
