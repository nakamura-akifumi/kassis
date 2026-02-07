<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class AmazonImportFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('zipFile', FileType::class, [
                'label' => 'Amazon購入履歴ファイル (Your Orders.zip)',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => '50M',
                        'mimeTypes' => [
                            'application/zip',
                            'application/x-zip-compressed',
                            'multipart/x-zip',
                        ],
                        'mimeTypesMessage' => 'ZIPファイルをアップロードしてください',
                    ])
                ],
                'attr' => [
                    'class' => 'form-control form-control-sm',
                    'accept' => '.zip'
                ],
                'help' => 'Amazonからダウンロードしたご注文履歴ファイル（Your Orders.zip）をアップロードしてください'
            ])
            ->add('kindleFile', FileType::class, [
                'label' => 'Kindle購入履歴ファイル (kindle.json)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '50M',
                        'mimeTypes' => [
                            'application/json',
                            'text/json',
                            'text/plain',
                        ],
                        'mimeTypesMessage' => 'JSONファイルをアップロードしてください',
                    ])
                ],
                'attr' => [
                    'class' => 'form-control form-control-sm',
                    'accept' => '.json'
                ],
                'help' => 'Kindle bookshelf exporterのkindle.jsonをアップロードできます（任意）'
            ])
            ->add('onlyIsbnAsin', CheckboxType::class, [
                'label' => 'ASINがISBNとして妥当でない場合は取り込まない（ISBN-10/ISBN-13）',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                ],
                'help' => 'ONにすると、ASINからISBNに変換できない行はスキップされます。',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
