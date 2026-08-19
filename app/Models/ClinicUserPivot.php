<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot customizado de clinic_user — só existe pra dar cast correto a
 * working_days (JSON) e agenda_visible_to_team (boolean). Escrita continua
 * pelo caminho já estabelecido no projeto: $user->clinics()->updateExistingPivot(...)
 * (ver GoogleDriveService/TeamController), não $pivot->save().
 */
class ClinicUserPivot extends Pivot
{
    protected $table = 'clinic_user';

    public $incrementing = true;

    protected $casts = [
        'agenda_visible_to_team' => 'boolean',
        'working_days' => 'array',
    ];

    // Chaves na ordem ISO-8601 (1=segunda..7=domingo), usadas tanto pro
    // JSON salvo quanto pra resolver o dia da semana de uma data.
    public const DAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public const DEFAULT_WORKING_DAYS = [
        'mon' => true, 'tue' => true, 'wed' => true, 'thu' => true,
        'fri' => true, 'sat' => true, 'sun' => true,
    ];

    // Valor sugerido pra pré-preencher o formulário de configurações quando
    // o profissional nunca mexeu no horário — só isso, não é um valor que
    // passa a restringir ninguém sozinho (ver workingHoursConfigured()
    // abaixo: nulo = sem restrição, mesmo espírito de working_days nulo =
    // todos os dias ligados).
    public const DEFAULT_WORKING_HOURS = ['start' => '09:00', 'end' => '18:00'];

    /**
     * working_days nulo (nunca configurado) = todos os dias ligados —
     * preserva o comportamento de antes dessa feature existir. Chaves
     * ausentes dentro de um working_days parcial também default pra true.
     */
    public function workingDaysResolved(): array
    {
        return array_merge(self::DEFAULT_WORKING_DAYS, $this->working_days ?? []);
    }

    // ISO-8601 (1=segunda..7=domingo) -> chave de DAY_KEYS. Extraído aqui
    // pra ser reaproveitado por effectiveWorksOnDate().
    public static function dayKeyFor(\DateTimeInterface $date): string
    {
        return self::DAY_KEYS[((int) $date->format('N')) - 1];
    }

    /**
     * Pro FORMULÁRIO de configurações — sempre retorna algo pra mostrar nos
     * campos Início/Fim, mesmo sem configuração prévia (cai no sugerido
     * DEFAULT_WORKING_HOURS). NÃO usar isto pra decidir se um agendamento
     * deve ser bloqueado — ver workingHoursConfigured() pra isso.
     *
     * @return array{start: string, end: string} formato "HH:MM"
     */
    public function workingHoursResolved(): array
    {
        return [
            'start' => $this->working_start ?: self::DEFAULT_WORKING_HOURS['start'],
            'end'   => $this->working_end   ?: self::DEFAULT_WORKING_HOURS['end'],
        ];
    }

    /**
     * null = profissional nunca configurou horário -> sem restrição
     * nenhuma, mesmo espírito de working_days nulo = todos os dias
     * ligados. Só retorna um intervalo quando os dois campos foram
     * explicitamente preenchidos (a validação em AgendaSettingsController
     * já garante que os dois vêm juntos, nunca só um).
     *
     * @return array{start: string, end: string}|null
     */
    public function workingHoursConfigured(): ?array
    {
        if (! $this->working_start || ! $this->working_end) {
            return null;
        }

        return ['start' => $this->working_start, 'end' => $this->working_end];
    }

    /**
     * Aplica workingDaysResolved() e, por cima, a regra administrativa da
     * clínica quando ela for obrigatória (ver Clinic::businessHoursEnforced).
     * A clínica só pode FECHAR um dia que o profissional teria aberto —
     * nunca abre um dia que ele mesmo fechou.
     */
    public function effectiveWorkingDayEnabled(?Clinic $clinic, string $dayKey): bool
    {
        $own = $this->workingDaysResolved()[$dayKey];

        if (! $clinic?->businessHoursEnforced()) {
            return $own;
        }

        $clinicDay = $clinic->businessHoursFor($dayKey);

        if (! $clinicDay || $clinicDay['enabled']) {
            return $own;
        }

        // Clínica fechada esse dia + regra obrigatória vence o próprio
        // profissional — o valor dele continua salvo intacto em
        // clinic_user, só a LEITURA efetiva ignora enquanto a regra durar.
        return false;
    }

    public function effectiveWorksOnDate(?Clinic $clinic, \DateTimeInterface $date): bool
    {
        return $this->effectiveWorkingDayEnabled($clinic, self::dayKeyFor($date));
    }

    /**
     * A janela da clínica é um TETO, não uma obrigação de atender: quando o
     * profissional já é mais restrito que a clínica, o dele prevalece igual
     * (ex.: clínica 09–18, profissional 10–16 -> efetivo 10–16). Só quando
     * ele tentaria ir além da janela da clínica é que o efetivo encolhe
     * (ex.: profissional 08–20 -> efetivo 09–18). Sem enforcement, ou sem
     * regra da clínica pra esse dia, comportamento idêntico a
     * workingHoursConfigured().
     *
     * @return array{start: string, end: string}|null
     */
    public function effectiveWorkingHours(?Clinic $clinic, string $dayKey): ?array
    {
        $own = $this->workingHoursConfigured();

        if (! $clinic?->businessHoursEnforced()) {
            return $own;
        }

        $clinicDay = $clinic->businessHoursFor($dayKey);

        if (! $clinicDay || ! $clinicDay['enabled']) {
            return $own;
        }

        $clinicWindow = ['start' => $clinicDay['start'], 'end' => $clinicDay['end']];

        // Profissional nunca configurou horário próprio -> a janela da
        // clínica passa a ser a referência (mesmo espírito de "sem dado
        // próprio, usa o mais autoritativo disponível").
        if ($own === null) {
            return $clinicWindow;
        }

        return [
            'start' => max($own['start'], $clinicWindow['start']),
            'end'   => min($own['end'], $clinicWindow['end']),
        ];
    }

    public function effectiveIsWithinWorkingHours(?Clinic $clinic, \DateTimeInterface $start, \DateTimeInterface $end): bool
    {
        $hours = $this->effectiveWorkingHours($clinic, self::dayKeyFor($start));
        if ($hours === null) {
            return true;
        }

        return $start->format('H:i') >= $hours['start'] && $end->format('H:i') <= $hours['end'];
    }
}
