<?php

namespace App\Policies;

use App\Models\DocumentTemplate;
use App\Models\User;
use App\Policies\Concerns\AllowsGlobalOrOwnClinic;

class DocumentTemplatePolicy
{
    use AllowsGlobalOrOwnClinic;

    public function manage(User $user, DocumentTemplate $template): bool
    {
        return $this->globalOrSameClinic($template->clinic_id);
    }
}
