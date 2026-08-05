# Marcadores do Paciente — Roadmap Técnico

> **Status:** documento de arquitetura futura. A parte implementada (marcadores administrativos manuais) reaproveita `PatientTag` — não existe mais `PatientMarker` como model/tabela separada (ver seção 0, decisão revista após auditoria). Vive em `app/Models/PatientTag.php` (coluna `is_patient_marker`), `app/Services/PatientMarkerService.php`, `app/Http/Controllers/PatientMarkerController.php`, `resources/js/Components/Patient/PatientMarkerManager.vue`. Este documento cobre o que **não** foi implementado agora.

## 0. Decisão revista: PatientTag reaproveitado, não um model novo

A primeira versão desta feature criou `PatientMarker` como model/tabela própria, quase idêntico a `PatientTag`, por medo de misturar categorias de observação com marcadores num dropdown. Numa auditoria posterior concluímos que esse risco é barato de conter (só existem dois pontos de leitura do vocabulário — `PatientNoteService::availableTags()` e `PatientMarkerService::availableMarkers()` — cada um filtrando por `is_patient_marker`) e que manter duas tabelas quase idênticas era duplicação de verdade, sem nenhuma regra de negócio distinta para justificá-la. `PatientMarker` foi removido; `patient_tags` ganhou a coluna `is_patient_marker` e um segundo pivot (`patient_marker_assignments`, `Patient` ↔ `PatientTag`) além do já existente (`patient_note_tag`, `PatientNote` ↔ `PatientTag`).

## 1. Três conceitos, um vocabulário compartilhado onde faz sentido

| Conceito | O que é | Fonte do dado | Onde vive |
|---|---|---|---|
| Observações | histórico, notas, lembretes, alertas | manual, por nota | `PatientNote` + `PatientTag` (`is_patient_marker=false`) |
| Marcadores | classificação administrativa do paciente | manual, por paciente | `PatientTag` (`is_patient_marker=true`) + pivot `patient_marker_assignments` |
| Badges automáticas | classificação derivada de outro módulo | computada, nunca digitada | não persistida (ver item 2) |

Observações e Marcadores compartilham a tabela-vocabulário (mesma forma: nome, slug, cor, clinic-scoped) mas nunca o mesmo registro — `is_patient_marker` os separa, e cada um tem seu próprio pivot (nota vs. paciente). Badges automáticas não usam essa tabela de jeito nenhum — são computadas, não cadastradas.

**Trade-off aceito:** como o slug é único por clínica na mesma tabela, uma clínica não pode ter uma categoria de observação e um marcador com o nome idêntico. Não é um problema hoje (os conjuntos de nomes não se sobrepõem) mas fica registrado.

## 2. Badges automáticas (não implementado)

Convênio, Ortodontia, Implante, Prótese, Cirurgia — e também "Paciente Particular" (`convenio_id IS NULL`) — **não devem virar marcador (`PatientTag.is_patient_marker`)**. São fatos que já existem em outro lugar do sistema; armazená-los de novo como marcador manual cria a mesma duplicação que motivou tirar "Falecido"/"Inativo" da lista de marcadores nesta própria tarefa (ver `app/Http/Controllers/PatientController.php`, prop `patient` — `status` já resolve isso hoje via `PatientMarkerManager.vue::statusBadge`).

**Desenho proposto:** um `PatientBadgeResolver` (service, sem tabela própria) com um método por badge, cada um perguntando ao módulo dono do dado:

```php
class PatientBadgeResolver
{
    public function resolve(Patient $patient): array
    {
        $badges = [];

        if ($patient->convenio_id) {
            $badges[] = ['label' => 'Convênio', 'color' => '#14b8a6'];
        }

        // Implante/Prótese/Ortodontia/Cirurgia: existe PatientTreatment
        // concluído/ativo cujo Treatment pertença à categoria correspondente.
        // Depende de como Treatment classifica categoria — não confirmado
        // nesta auditoria, verificar antes de implementar.

        return $badges;
    }
}
```

Resultado computado a cada carregamento da ficha (ou cacheado por poucos minutos se o custo da query justificar) — nunca gravado em `patient_tags` nem em nenhuma tabela nova. `PatientMarkerManager.vue` já está pronto para receber uma terceira lista (`automaticBadges`) exibida no mesmo padrão visual das outras duas, sem mudança estrutural quando isso for implementado.

## 3. Por que não foi feito agora

Pedido explícito: badges automáticas são arquitetura preparada, não implementação. Além disso a regra de "Implante/Prótese/Ortodontia/Cirurgia" depende de como o módulo de Tratamentos categoriza cada `Treatment` — não foi auditado nesta tarefa, então qualquer implementação agora seria um chute. Quando for priorizado, auditar `Treatment`/`PatientTreatment` primeiro.
