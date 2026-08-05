<?php

namespace App\DTO\Financial;

use App\Enums\Financial\FinancingProposalStatus;

readonly class FinancingProposalResult
{
    public function __construct(
        public string $externalId,
        public FinancingProposalStatus $status,
        public ?string $signatureUrl = null,
        public ?string $message = null,
        public array $raw = [],
    ) {}

    public function toArray(): array
    {
        return [
            'external_id'    => $this->externalId,
            'status'         => $this->status->value,
            'signature_url'  => $this->signatureUrl,
            'message'        => $this->message,
        ];
    }
}