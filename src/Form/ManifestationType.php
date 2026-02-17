<?php

namespace App\Form;

use App\Entity\Manifestation;
use App\Repository\CodeRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class ManifestationType extends AbstractType
{
    public function __construct(
        private CodeRepository $codeRepository,
        private ParameterBagInterface $params,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $useType1Code = $this->isCodeEnabled('app.manifestation.type1.use_code');
        $useType2Code = $this->isCodeEnabled('app.manifestation.type2.use_code');
        $useType3Code = $this->isCodeEnabled('app.manifestation.type3.use_code');
        $useType4Code = $this->isCodeEnabled('app.manifestation.type4.use_code');
        $useLocation1Code = $this->isCodeEnabled('app.manifestation.location1.use_code');
        $useLocation2Code = $this->isCodeEnabled('app.manifestation.location2.use_code');

        $builder
            ->add('title', TextareaType::class, [
                'constraints' => [
                    new NotBlank([
                        'message' => 'タイトルは必須です',
                    ]),
                ],
                'attr' => [
                    'rows' => 1,
                ],
                'required' => true,
            ])
            ->add('title_transcription', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'rows' => 1,
                ],
            ])
            ->add('identifier', TextType::class, [
                'constraints' => [
                    new NotBlank([
                        'message' => '識別子は必須です',
                    ]),
                ],
                'required' => true,
            ])
            ->add('external_identifier1', null, [
                'required' => false,
            ])
            ->add('external_identifier2', null, [
                'required' => false,
            ])
            ->add('external_identifier3', null, [
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'rows' => 1,
                ],
            ])
            ->add('buyer', null, [
                'required' => false,
            ])
            ->add('buyer_identifier', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'rows' => 1,
                ],
            ])
            ->add('purchase_date', null, [
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('record_source', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'rows' => 1,
                ],
            ])
            ->add('release_date_string', TextType::class, [
                'required' => false,
            ])
            ->add('type1', $useType1Code ? ChoiceType::class : null, [
                'required' => false,
                'choices' => $useType1Code ? $this->getCodeChoices('manifestation_type1') : null,
                'placeholder' => $useType1Code ? '選択してください' : null,
            ])
            ->add('type2', $useType2Code ? ChoiceType::class : null, [
                'required' => false,
                'choices' => $useType2Code ? $this->getCodeChoices('manifestation_type2') : null,
                'placeholder' => $useType2Code ? '選択してください' : null,
            ])
            ->add('type3', $useType3Code ? ChoiceType::class : null, [
                'required' => false,
                'choices' => $useType3Code ? $this->getCodeChoices('manifestation_type3') : null,
                'placeholder' => $useType3Code ? '選択してください' : null,
            ])
            ->add('type4', $useType4Code ? ChoiceType::class : null, [
                'required' => false,
                'choices' => $useType4Code ? $this->getCodeChoices('manifestation_type4') : null,
                'placeholder' => $useType4Code ? '選択してください' : null,
            ])
            ->add('class1', null, [
                'required' => false,
            ])
            ->add('class2', null, [
                'required' => false,
            ])
            ->add('location1', $useLocation1Code ? ChoiceType::class : null, [
                'required' => false,
                'choices' => $useLocation1Code ? $this->getCodeChoices('manifestation_location1') : null,
                'placeholder' => $useLocation1Code ? '選択してください' : null,
            ])
            ->add('location2', $useLocation2Code ? ChoiceType::class : null, [
                'required' => false,
                'choices' => $useLocation2Code ? $this->getCodeChoices('manifestation_location2') : null,
                'placeholder' => $useLocation2Code ? '選択してください' : null,
            ])
            ->add('location3', null, [
                'required' => false,
            ])
            ->add('contributor1', null, [
                'required' => false,
            ])
            ->add('contributor2', null, [
                'required' => false,
            ])
            ->add('loan_restriction', ChoiceType::class, [
                'required' => false,
                'choices' => $options['loan_restriction_choices'],
                'placeholder' => '選択してください',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Manifestation::class,
            'validation_groups' => ['Default'],
            'loan_restriction_choices' => [],
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function getCodeChoices(string $type): array
    {
        $choices = [];
        $codes = $this->codeRepository->findBy(['type' => $type], ['display_order' => 'ASC', 'identifier' => 'ASC']);
        foreach ($codes as $code) {
            $label = $code->getDisplayname();
            if ($label === null || trim($label) === '') {
                $label = $code->getIdentifier();
            }
            $choices[$label] = $code->getIdentifier();
        }

        return $choices;
    }

    private function isCodeEnabled(string $param): bool
    {
        return $this->params->has($param) && (bool) $this->params->get($param);
    }
}
