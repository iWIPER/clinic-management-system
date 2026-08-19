<?php

namespace App\Policies;

use App\Models\DocumentCategory;
use App\Models\User;
use App\Policies\Concerns\AllowsGlobalOrOwnClinic;

class DocumentCategoryPolicy
{
    use AllowsGlobalOrOwnClinic;

    public function manage(User $user, DocumentCategory $category): bool
    {
        return $this->globalOrSameClinic($category->clinic_id);
    }
}
