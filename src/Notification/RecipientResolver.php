<?php

namespace App\Notification;

use App\Entity\Member;

final class RecipientResolver
{
    public function resolveEmail(Member $member): ?string
    {
        $candidates = [
            $member->getCommunicationAddress1(),
            $member->getCommunicationAddress2(),
        ];

        foreach ($candidates as $candidate) {
            $candidate = is_string($candidate) ? trim($candidate) : '';
            if ($candidate === '') {
                continue;
            }
            if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }

        return null;
    }
}
