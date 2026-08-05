<?php

namespace App\Exceptions;

use Exception;

class DriveStructureMissingException extends Exception
{
    public function __construct(
        public readonly ?string $level = null,
        public readonly ?string $oldFolderId = null,
    ) {
        parent::__construct('A estrutura de armazenamento do Google Drive não está mais disponível.');
    }
}