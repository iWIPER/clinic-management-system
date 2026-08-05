<?php

namespace App\Enums\Financial;

enum FinancingProposalStatus: string
{
    case Draft           = 'rascunho';
    case Created         = 'criada';
    case UnderReview     = 'em_analise';
    case Approved        = 'aprovada';
    case Rejected        = 'recusada';
    case AwaitingSignature = 'aguardando_assinatura';
    case Signed          = 'assinada';
    case Paid            = 'pagamento_realizado';
    case Cancelled       = 'cancelada';
    case Settled         = 'liquidada';
}