<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ManifestationTypeCode extends Constraint
{
    public string $message = 'コードマスタ({{ type }}) に識別子 "{{ identifier }}" が存在しません。';
    public string $codeType = '';
    public string $flagParam = '';

    public function __construct(
        string $codeType = '',
        string $flagParam = '',
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct([], $groups, $payload);
        $this->codeType = $codeType;
        $this->flagParam = $flagParam;
        if ($message !== null) {
            $this->message = $message;
        }
    }
}
