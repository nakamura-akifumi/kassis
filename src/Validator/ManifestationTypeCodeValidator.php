<?php

namespace App\Validator;

use App\Repository\CodeRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ManifestationTypeCodeValidator extends ConstraintValidator
{
    public function __construct(
        private CodeRepository $codeRepository,
        private ParameterBagInterface $params,
    ) {
    }

    /**
     * @param mixed $value
     */
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof ManifestationTypeCode) {
            return;
        }

        $input = is_string($value) ? trim($value) : null;
        if ($input === null || $input === '') {
            return;
        }

        if ($constraint->flagParam !== '') {
            if (!$this->params->has($constraint->flagParam)) {
                return;
            }
            $enabled = (bool) $this->params->get($constraint->flagParam);
            if (!$enabled) {
                return;
            }
        }

        $codeType = $constraint->codeType;
        if ($codeType === '') {
            return;
        }

        $code = $this->codeRepository->findOneBy([
            'type' => $codeType,
            'identifier' => $input,
        ]);

        if ($code === null) {
            $code = $this->codeRepository->findOneBy([
                'type' => $codeType,
                'displayname' => $input,
            ]);
            if ($code !== null) {
                $object = $this->context->getObject();
                $property = $this->context->getPropertyName();
                if (is_object($object) && is_string($property) && $property !== '') {
                    $setter = 'set' . ucfirst($property);
                    if (is_callable([$object, $setter])) {
                        $object->$setter($code->getIdentifier());
                    }
                }
                return;
            }
        }

        if ($code === null) {
            $this->context
                ->buildViolation($constraint->message)
                ->setParameter('{{ type }}', $codeType)
                ->setParameter('{{ identifier }}', $input)
                ->addViolation();
        }
    }
}
