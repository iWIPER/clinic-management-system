# Observações do Paciente — Roadmap Técnico

> **Status:** documento de arquitetura futura. Nada aqui está implementado — é o desenho de referência para quando essas evoluções forem priorizadas, para que sejam feitas seguindo um padrão único em vez de decididas ad-hoc no momento da implementação.
> **Módulo:** `PatientNote` (`app/Models/PatientNote.php`, `app/Services/PatientNoteService.php`, `app/Http/Controllers/PatientNoteController.php`, `resources/js/Components/Patient/PatientNotesTab.vue` e `PatientAlertChips.vue`).

## 1. Contexto já implementado nesta evolução

- Sistema de prioridade (`priority`: `critico`/`atencao`/`informativo`) para notas com `is_alert = true` — ver `PatientNote::PRIORITIES`.
- Observações contextuais: `PatientNoteService::forContext(Patient, string $context, ?int $viewerId)`, com o mapeamento categoria (tag) → contexto em `config/patient_notes.php`. **Nenhum controller consome este método ainda** — é arquitetura pronta para o item 2 abaixo.
- `alertNotes()` e `forContext()` agora ordenam fixadas primeiro, igual `listForPatient()` (via `PatientNoteService::visibleTo()`, ponto único de filtro de privacidade).

## 2. Próximo passo natural: integrações reais

Quando os módulos abaixo tiverem uma tela/controller pronto para receber a informação, a integração é uma chamada de uma linha, sem mudança de arquitetura:

| Módulo consumidor | Contexto (`config/patient_notes.php`) | Chamada |
|---|---|---|
| Financeiro do paciente | `financeiro` | `$noteService->forContext($patient, 'financeiro', $doctor?->id)` |
| Início de atendimento / anamnese | `atendimento` | idem, contexto `atendimento` |
| Geração de orçamento | `orcamento` | idem, contexto `orcamento` |
| Aba Tratamentos | `tratamentos` | idem, contexto `tratamentos` |

Ao integrar o primeiro consumidor real, vale extrair um componente Vue compacto de "observações contextuais" (ex.: `PatientContextNotes.vue`) reaproveitando o mesmo estilo de card já usado em `PatientNotesTab.vue`. Não foi criado agora porque, sem um caso de uso real para validar a API de props, seria um componente especulativo — risco de precisar ser refeito no primeiro uso de verdade.

Os novos contextos (além dos 4 já mapeados) são só uma entrada a mais no array `contexts` do config — não exigem migration nem mudança no service.

## 3. Validade / expiração automática (não implementado)

**Problema que resolve:** observações como "paciente em antibiótico até dia X" perdem relevância depois de uma data e hoje continuam aparecendo para sempre.

**Desenho proposto:**
- Migration: `expires_at` (timestamp nullable) em `patient_notes`.
- Filtro de leitura em `PatientNoteService::visibleTo()` (o mesmo ponto único já usado para privacidade, então todos os métodos do service — `listForPatient`, `alertNotes`, `forContext` — ganham o comportamento de graça):
  ```php
  $query->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
  ```
- **Sem job/cron.** Expiração é um filtro de leitura (`WHERE`), não uma exclusão física — a nota continua no banco (histórico preservado, ver item 4) e simplesmente para de aparecer. Isso evita introduzir infraestrutura de agendamento só para isso.
- UI: campo opcional "válida até" no formulário (`PatientNotesTab.vue`); indicador visual discreto quando a expiração está próxima (ex.: "expira em 3 dias").

## 4. Histórico / auditoria (não implementado)

**Problema que resolve:** hoje `update()` sobrescreve os campos sem deixar rastro do valor anterior, e `destroy()` é hard delete sem log de quem excluiu.

**Desenho proposto — réplica exata do padrão já existente em `PatientTreatmentAuditLog`** (`app/Models/PatientTreatmentAuditLog.php` + `PatientTreatmentController::logAudit()`, linha 306):

```php
class PatientNoteAuditLog extends Model
{
    use BelongsToClinic;
    public $timestamps = false;

    protected $fillable = ['clinic_id', 'patient_note_id', 'user_id', 'action', 'metadata', 'created_at'];
    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];

    public const ACTIONS = [
        'created' => 'Criação',
        'updated' => 'Edição',
        'deleted' => 'Exclusão',
        'pinned' => 'Fixada',
        'unpinned' => 'Desfixada',
        'alert_set' => 'Marcada como alerta',
        'alert_cleared' => 'Alerta removido',
    ];

    public function patientNote() { return $this->belongsTo(PatientNote::class); }
    public function user() { return $this->belongsTo(User::class); }
}
```

Tabela append-only (sem `updated_at`, mesma forma da referência). `PatientNoteController` ganharia um `logAudit()` privado (idêntico em formato ao de `PatientTreatmentController`), chamado após `store`/`update`/`destroy`. Isso também resolveria o hard-delete sem rastro: antes de `$note->delete()`, registrar `action: 'deleted'` com um snapshot dos campos em `metadata`.

## 5. Por que não foi feito agora

Os itens 3 e 4 exigem migration + (no caso do histórico) um model e uma tabela novos — mudança estrutural real, não só visual. O pedido que originou esta evolução foi explícito em não implementar essas duas partes agora, só preparar o desenho. Quando forem priorizadas, este documento é o ponto de partida — não precisa de nova auditoria do zero.
