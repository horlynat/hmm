<?php

namespace App\Service;

use App\Entity\User;

class ProfileCompletionService
{
    public function calculateCompletionPercentage(User $user): int
    {
        $totalFields = 5;
        $filledFields = 0;

        if ($user->getFullName()) $filledFields++;
        if ($user->getEmail()) $filledFields++;
        if ($user->getPhone()) $filledFields++;
        if ($user->getProfileImage()) $filledFields++;
        if ($user->getLastLoginAt()) $filledFields++;

        return (int) (($filledFields / $totalFields) * 100);
    }
}