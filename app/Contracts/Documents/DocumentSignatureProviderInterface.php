<?php

namespace App\Contracts\Documents;

use App\Models\Document;
use App\Models\DocumentSignature;

interface DocumentSignatureProviderInterface
{
    public function sign(Document $document, string $signerRole, array $data, ?string $ip): DocumentSignature;

    public function driver(): string;
}
