# Fase D — Limpeza / Redução de Dívida Técnica

Data: 2026-08-18
Escopo: dead code, componentes Vue órfãos, dependências não utilizadas,
duplicação de formatadores, test setup, interfaces sem consumidor.
Fora de escopo (não tocado): regras de negócio, autorização, multi-tenancy,
RLS/RBAC, System Admin, LGPD, billing, Google Drive, auth, fluxos clínicos.

## Removidos

### PHP
- `app/Models/MedicalRecord.php` — zero referências fora da relação
  `Consultation::medicalRecord()`; nenhum controller, rota, view ou teste
  usava o model. Evidência: grep completo no repo sem outras ocorrências.
- `Consultation::medicalRecord(): HasOne` — única consumidora do model acima.
- `ConsultationController::show()` — removida `'medicalRecord'` do array de
  eager load (`->load([...])`), consequência direta da remoção da relação.
- `PatientTreatment::scopeForTooth()` — zero chamadas a `->forTooth(` no
  repo inteiro.
- `ClinicUserPivot::worksOnDate()` e `ClinicUserPivot::isWithinWorkingHours()`
  — superadas por `effectiveWorksOnDate()`/`effectiveIsWithinWorkingHours()`,
  que não as chamam internamente (verificado). Métodos auxiliares
  `dayKeyFor()`/`workingHoursConfigured()` seguem em uso pelas variantes
  "effective" e foram mantidos.
- `routes/web.php` — rota duplicada
  `patients.drive.verify` (`/patients/{patient}/drive/verify`); mantida
  `patients.drive.health-check`, a única de fato referenciada em
  `Patients/Show.vue`.
- Dependência composer `laravel/sanctum` — `HasApiTokens` nunca foi
  efetivamente conectado: sem `config/sanctum.php` publicado, sem
  middleware registrado, e a tabela `personal_access_tokens` não existe
  nem no Postgres real nem no SQLite de teste (confirmado via
  `Schema::hasTable()` nos dois). `composer why laravel/sanctum` confirmou
  que só o pacote raiz o exigia (nenhuma dependência transitiva).

### Vue (componentes órfãos, zero imports estáticos ou dinâmicos)
- `resources/js/Components/PatientHub/AiInsightsCard.vue`
- `resources/js/Components/PatientHub/ClinicalAlertsBar.vue`
- `resources/js/Components/PatientHub/HubHeader.vue`
- `resources/js/Components/PatientHub/TimelineTab.vue`
- `resources/js/Components/Anamnesis/AnamnesisTemplateCategorySection.vue`
- `resources/js/Components/Anamnesis/QuestionBankModal.vue`

### JS
- `groupQuestionsByCategory()` em `useAnamnesisCategories.js` — zero
  chamadores internos ou externos.
- `CATEGORY_ORDER`, `GRID_FLOOR_HOUR`, `dayKeyForDate` deixaram de ser
  `export` (viraram privados ao módulo) — ainda usados internamente, mas
  sem nenhum consumidor externo real. `GRID_CEIL_HOUR` e `DAY_KEYS`
  permaneceram exportados (consumidores externos confirmados).

### Dependências npm
- `vue-cal` removida de `package.json` — sem nenhum import no código;
  `npm install` removeu o pacote e 2 dependências transitivas, lockfile
  sincronizado.

## Mantidos

- **Tabela `medical_records` no banco** — o model PHP foi removido, mas a
  migration/tabela em si foi deliberadamente preservada. Decisão de
  schema (dropar tabela com dados) está fora do escopo de limpeza de
  código morto desta fase; requer decisão própria em fase futura.
- **Duplicação de formatação de moeda** (~25 arquivos com formatação
  inline de valores monetários) e **duplicação de formatação de data**
  (~28 arquivos com formatação inline de datas) — identificadas e
  documentadas aqui, mas **não migradas** para um helper único. Migrar
  25+28 arquivos é uma refatoração de superfície ampla, incompatível com
  a instrução explícita desta fase de não iniciar um grande refactor.
  Fica registrado como candidato a uma fase própria e dedicada.
- `GRID_CEIL_HOUR`, `DAY_KEYS` (`useEffectiveSchedule.js`) — consumidores
  externos reais confirmados, mantidos exportados.

## Inconclusivos

Nenhum item adicional ficou em estado inconclusivo além do já registrado
em "Mantidos" acima. Todo item avaliado nesta fase teve evidência
suficiente (grep completo + build + testes) para classificação definitiva
como removido ou mantido.

## Resultado

1. **Arquivos removidos:** 7 (1 PHP model + 6 componentes Vue)
2. **Componentes removidos:** 6 (Vue, todos órfãos confirmados)
3. **Dependências removidas:** 2 (`vue-cal` via npm, `laravel/sanctum` via composer)
4. **Duplicações eliminadas:** 0 executadas — 2 padrões de duplicação
   documentados (formatação de moeda e de data) e conscientemente não
   migrados nesta fase (ver "Mantidos")
5. **Interfaces/helpers removidos:** `scopeForTooth()`, `worksOnDate()`,
   `isWithinWorkingHours()`, `medicalRecord()` (relation),
   `groupQuestionsByCategory()`, 1 rota duplicada, 3 exports JS
   convertidos para privados
6. **Itens inconclusivos:** nenhum
7. **Arquivos modificados:** `app/Models/Consultation.php`,
   `app/Http/Controllers/ConsultationController.php`,
   `app/Models/PatientTreatment.php`, `app/Models/ClinicUserPivot.php`,
   `app/Models/User.php`, `routes/web.php`,
   `resources/js/composables/useEffectiveSchedule.js`,
   `resources/js/composables/useAnamnesisCategories.js`,
   `package.json`, `package-lock.json`, `composer.json`, `composer.lock`
8. **Testes adicionados/alterados:** nenhum — nenhum código morto removido
   nesta fase tinha teste dedicado próprio
9. **Resultado `php artisan test`:** 620 passed (3179 assertions), 0
   failures — idêntico à baseline pré-Fase D
10. **Resultado `npm run build`:** sucesso, sem erros ou warnings novos
11. **Riscos/observações:**
    - Durante a remoção do `laravel/sanctum`, um `php artisan test` disparado
      em paralelo ao `composer remove` (ainda em andamento) produziu um
      falso-negativo severo (373 failed) por corrupção momentânea do
      autoload/cache de descoberta de pacotes. Descartado; refeito de forma
      sequencial e limpo (`bootstrap/cache/packages.php` e `services.php`
      removidos, `package:discover` + `config:clear` + `cache:clear`
      re-executados antes da medição final), resultando nos 620
      passed/3179 assertions confirmados acima. Nenhuma ação de código foi
      necessária — era puramente um artefato de concorrência de processos.
    - A tabela `medical_records` permanece no schema sem model associado;
      recomenda-se decisão explícita (drop ou reaproveitamento) em fase
      futura dedicada a schema.
