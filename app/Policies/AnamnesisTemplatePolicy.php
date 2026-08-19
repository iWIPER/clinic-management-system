<?php

namespace App\Policies;

use App\Models\AnamnesisTemplate;
use App\Models\User;
use App\Policies\Concerns\AllowsGlobalOrOwnClinic;

class AnamnesisTemplatePolicy
{
    use AllowsGlobalOrOwnClinic;

    public function manage(User $user, AnamnesisTemplate $template): bool
    {
        return $this->globalOrSameClinic($template->clinic_id);
    }
}
