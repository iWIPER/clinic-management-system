<?php

namespace App\Policies;

use App\Models\AnamnesisCategoryDefinition;
use App\Models\User;
use App\Policies\Concerns\AllowsGlobalOrOwnClinic;

class AnamnesisCategoryDefinitionPolicy
{
    use AllowsGlobalOrOwnClinic;

    public function manage(User $user, AnamnesisCategoryDefinition $category): bool
    {
        return $this->globalOrSameClinic($category->clinic_id);
    }
}
