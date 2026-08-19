<?php

namespace App\Policies;

use App\Models\AnamnesisQuestion;
use App\Models\User;
use App\Policies\Concerns\AllowsGlobalOrOwnClinic;

class AnamnesisQuestionPolicy
{
    use AllowsGlobalOrOwnClinic;

    public function manage(User $user, AnamnesisQuestion $question): bool
    {
        return $this->globalOrSameClinic($question->clinic_id);
    }
}
