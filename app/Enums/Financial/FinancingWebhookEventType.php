<?php

namespace App\Enums\Financial;

enum FinancingWebhookEventType: string
{
    case ProposalCreated   = 'proposta_criada';
    case UnderReview       = 'em_analise';
    case Approved          = 'aprovada';
    case Rejected          = 'recusada';
    case AwaitingSignature = 'aguardando_assinatura';
    case Signed            = 'assinada';
    case Paid              = 'pagamento_realizado';
    case Cancelled         = 'cancelada';
    case Settled           = 'liquidada';
    case Unknown           = 'desconhecido';
}