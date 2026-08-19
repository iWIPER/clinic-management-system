<?php

namespace App\Services;

use App\Models\Clinic;
use Google_Service_Exception;

/**
 * Fase C1.2.3 — extraída de GoogleDriveService::callDrive() pra ser
 * reutilizável também por GoogleDriveStructureService sem duplicar a
 * lógica de retry nem criar uma dependência circular entre as duas
 * classes (GoogleDriveService precisa de GoogleDriveStructureService pra
 * delegação de wrappers; GoogleDriveStructureService precisaria do
 * callDrive que antes só existia em GoogleDriveService — daí o ciclo).
 *
 * Sem estado de "clínica atual": cada chamada recebe a Clinic
 * explicitamente, no mesmo padrão já usado por GoogleDriveAuthService
 * (C1.2.2). Depende só de GoogleDriveAuthService (pra renovar o token),
 * nunca de GoogleDriveService ou GoogleDriveStructureService.
 */
class GoogleDriveCallExecutor
{
    public function __construct(private GoogleDriveAuthService $authService)
    {
    }

    /**
     * Run a live Drive API call, transparently refreshing the access token
     * and retrying once if the call fails due to an expired/revoked token.
     */
    public function call(Clinic $clinic, callable $fn)
    {
        try {
            return $fn();
        } catch (Google_Service_Exception $e) {
            if (!$this->isAuthError($e)) {
                throw $e;
            }

            $this->authService->forceRefreshAccessToken($clinic);

            return $fn();
        }
    }

    public function isAuthError(Google_Service_Exception $e): bool
    {
        if ($e->getCode() === 401) {
            return true;
        }

        foreach ($e->getErrors() as $error) {
            if (($error['reason'] ?? null) === 'authError') {
                return true;
            }
        }

        return false;
    }
}
