<?php

namespace App\Exceptions;

use App\Models\Clinic;
use Exception;

/**
 * Thrown when the Google Drive refresh token is missing, corrupted, or
 * rejected by Google (invalid_grant) — recovery requires a new OAuth consent,
 * as opposed to a plain expired access token, which is refreshed silently.
 */
class GoogleDriveReauthRequiredException extends Exception
{
    public function __construct(
        public readonly ?Clinic $clinic = null,
    ) {
        parent::__construct('A conexão com o Google Drive expirou e precisa ser refeita.');
    }
}
