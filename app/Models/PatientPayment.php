<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PatientPayment extends Model
{
    use BelongsToClinic;

    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_PARCIAL  = 'parcial';
    public const STATUS_PAGO     = 'pago';
    public const STATUS_CANCELADO = 'cancelado';

    public const STATUSES = [
        self::STATUS_PENDENTE  => 'Pendente',
        self::STATUS_PARCIAL   => 'Parcial',
        self::STATUS_PAGO      => 'Recebido',
        self::STATUS_CANCELADO => 'Cancelado',
    ];

    /**
     * Fonte de verdade das formas de pagamento aceitas — validação em
     * PatientPaymentController::receive() e exposta ao front via prop
     * (mesmo padrão de PatientMarkerService::MAX_MARKERS_PER_PATIENT).
     */
    /**
     * Teto de parcelas ao criar um plano de pagamento (2 anos em cadência
     * mensal) — só um limite de sanidade contra entrada absurda, mesmo
     * padrão de PatientMarkerService::MAX_MARKERS_PER_PATIENT.
     */
    public const MAX_INSTALLMENTS = 24;

    public const METHODS = [
        'dinheiro'      => 'Dinheiro',
        'pix'           => 'PIX',
        'debito'        => 'Débito',
        'credito'       => 'Crédito',
        'transferencia' => 'Transferência',
        'cheque'        => 'Cheque',
        'convenio'      => 'Convênio',
        'outro'         => 'Outro',
    ];

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'patient_treatment_id',
        'installment_number',
        'installment_total',
        'amount',
        'amount_paid',
        'discount',
        'interest',
        'payment_method',
        'status',
        'due_date',
        'paid_at',
        'notes',
        'transaction_id',
        'created_by_id',
        'updated_by_id',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'discount'    => 'decimal:2',
        'interest'    => 'decimal:2',
        'due_date'    => 'date',
        'paid_at'     => 'datetime',
    ];

    public function treatment()
    {
        return $this->belongsTo(PatientTreatment::class, 'patient_treatment_id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    /**
     * Valor efetivamente devido desta parcela, já considerando desconto/juros
     * (ajustados via "Editar", só antes de qualquer recebimento — ver
     * PatientPaymentController::update()). `amount` continua sendo o valor
     * original cobrado, usado como está em "Total cobrado" nos cards.
     */
    public function effectiveTotal(): float
    {
        return max(0, (float) $this->amount - (float) $this->discount + (float) $this->interest);
    }

    /**
     * Saldo devedor desta parcela — nunca negativo (um recebimento não pode
     * ultrapassar o valor efetivo, mas o clamp protege contra dados legados).
     */
    public function remaining(): float
    {
        return max(0, $this->effectiveTotal() - (float) $this->amount_paid);
    }

    /**
     * Centraliza a transição pendente/parcial → parcial/pago — nem o
     * controller nem o frontend decidem esse status, só chamam isto.
     * Mantém a Transaction vinculada (caso installment_total = 1, único caso
     * existente até a Fase 3) sincronizada: ela só tem pendente/pago/cancelado
     * (sem noção de "parcial"), então só é marcada "pago" quando esta parcela
     * quita integralmente.
     */
    public function registerPayment(float $amountReceived, string $method, ?string $notes = null): void
    {
        $this->amount_paid = round((float) $this->amount_paid + $amountReceived, 2);
        $this->payment_method = $method;
        if ($notes !== null) {
            $this->notes = $notes;
        }

        $this->status = $this->amount_paid >= $this->effectiveTotal()
            ? self::STATUS_PAGO
            : self::STATUS_PARCIAL;

        if ($this->status === self::STATUS_PAGO && ! $this->paid_at) {
            $this->paid_at = now();
        }

        $this->save();

        if ($this->status === self::STATUS_PAGO && $this->transaction_id) {
            $this->transaction()->update(['status' => 'pago', 'pago_em' => $this->paid_at]);
        }
    }

    /**
     * Só chamável enquanto amount_paid = 0 (ver guarda no controller) — nunca
     * apaga dinheiro já recebido sem um fluxo de estorno, fora de escopo
     * desta entrega. Cancela também a Transaction vinculada, já que nada foi
     * de fato recebido para ela representar.
     */
    public function cancel(): void
    {
        $this->status = self::STATUS_CANCELADO;
        $this->save();

        if ($this->transaction_id) {
            $this->transaction()->update(['status' => 'cancelado']);
        }
    }

    /**
     * "Em atraso" nunca é persistido — é sempre recalculado a partir de
     * due_date, igual ao resto do app (nenhum status financeiro aqui é
     * mantido por job/cron). Usado tanto na exibição quanto nos filtros.
     */
    public function isOverdue(): bool
    {
        return in_array($this->status, [self::STATUS_PENDENTE, self::STATUS_PARCIAL], true)
            && $this->due_date !== null
            && $this->due_date->isPast();
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
