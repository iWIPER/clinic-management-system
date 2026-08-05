# Convites de Cadastro (Patient Invitations) — BRD v1.0 (fechado)

> **Status:** documento definitivo, encerrado — pronto para servir de base à implementação por fases. Nada deste módulo está implementado ainda.
> **Módulo proposto:** `app/Models/PatientInvite.php`, `app/Models/PatientInviteActivityLog.php`, `app/Services/PatientInviteService.php`, `app/Http/Controllers/PatientInviteController.php` (autenticado), `app/Http/Controllers/Public/PatientInvitePublicController.php` (público), `resources/js/Pages/PatientInvites/PublicWizard.vue` (público), `resources/js/Components/Patient/InviteStatusBadge.vue` (painel de acompanhamento).
> **Histórico de revisões:** rascunho inicial → rodada 1 (estados renomeados/reduzidos, detecção de paciente duplicado, progresso, painel compacto, timeline reaproveitada, roadmap por fases) → rodada 2 (Fase 6 mantida independente, autosave como requisito obrigatório com impactos documentados, tela de conclusão acolhedora, badge enriquecido com percentual e tipo de fluxo, esclarecimento sobre reuso do `AnamnesisInstance.progress`, auditoria de infraestrutura) → **rodada 3, final** (regra de unicidade de convite ativo por paciente+tipo, com cancelamento automático do anterior; comportamento explícito de convite cancelado, com a mesma tela acolhedora já usada para expirado). A seção 2 resume o que mudou em cada rodada.

---

## 1. Objetivo

Permitir que a recepção capture o mínimo possível de dados (nome, sobrenome, celular) e envie um link para o próprio paciente completar o cadastro — e, opcionalmente, a anamnese — no celular dele, sem precisar de um funcionário digitando tudo presencialmente.

---

## 2. Decisões fechadas

Resumo rastreável de cada ponto pedido nas três rodadas de revisão, para conferência rápida:

**Rodada 1:**

| # | Pedido | Decisão final | Onde no documento |
|---|---|---|---|
| 1 | Manter separação `patients.status` × ciclo de vida do convite | Mantido — nenhuma mudança | §3.1 |
| 2 | Telefone já pertence a paciente existente | Não bloqueia. Sistema detecta e oferece "enviar convite de atualização cadastral" (recomendado) ou "criar outro paciente mesmo assim" | §7, §17 |
| 3 | Nome/Sobrenome separados no modal | Confirmado — dois campos, igual ao cadastro interno | §7 |
| 4 | LGPD | Fica só no roadmap (Fase 7), nenhum módulo agora | §19 |
| 5 | WhatsApp | Confirmado: e-mail automático + botão `wa.me`. Sem API oficial nesta fase | §12 |
| 6 | Novo convite | Sempre cria uma linha nova em `patient_invites`; nunca reaproveita token. Histórico completo preservado | §5, §5.1 |
| 7 | Expiração configurável + tela de expirado amigável | Campo `expires_at` (Nunca/7/15/30 dias); copy exata da tela de expirado | §8.3, §16 |
| 8 | Indicador compacto junto ao nome, com painel sem navegação | `InviteStatusBadge.vue`, reaproveitando o padrão de popover já usado em `NavbarDropdown.vue` | §9 |
| 9 | Indicador de progresso (checklist e/ou %) | Coluna `progress` (0–100) em `patient_invites` | §6 |
| 10 | Estados do convite revisados | 8 estados definidos, nomeados em PT-BR; "não respondeu" virou flag, não estado | §5 |
| 11 | Timeline completa e auditável | Reaproveita `PatientHubService::timeline()` + `TimelineTab.vue` | §10, §18 |
| 12 | QR Code sem dependência nova | Confirmado — `bacon/bacon-qr-code` já instalado | §13 |
| 13 | UX detalhada (recepção e paciente) | Seções dedicadas | §7, §8 |
| 14 | Roadmap final por fases | 7 fases definidas | §19 |
| 15 | Auditoria final de reuso | Feita — achados relevantes em §18 | §18 |

**Rodada 2 (final):**

| # | Pedido | Decisão final | Onde no documento |
|---|---|---|---|
| 1 | Fase 6 (Atualização Cadastral) deve continuar independente no roadmap, mesmo reaproveitando a infraestrutura da Fase 1 | Mantida como fase própria — a distinção é de produto (Fluxo A vs. Fluxo B), não de engenharia. Isso introduziu a necessidade real de uma coluna nova (`kind`) para o sistema saber diferenciar os dois fluxos depois de criados | §3.4, §4, §19 |
| 2 | Autosave obrigatório, sem perda de dados, retomada exata | Confirmado como requisito, não sugestão. Impactos arquiteturais documentados explicitamente (validação teve que ficar mais permissiva no rascunho; limite honesto do que "sem perda" consegue garantir) | §8.1 |
| 3 | Tela de conclusão acolhedora, com agendamento vinculado quando existir | Copy definida; lookup dinâmico do próximo agendamento via `Patient::appointments()` já existente — sem coluna nova | §8.2 |
| 4 | Badge enriquecido (percentual + tipo de fluxo) | Badge passa a interpolar `progress` e usar uma cor própria (azul) quando `kind = atualizacao` | §9 |
| 5 | Manter a decisão de reaproveitar `PatientHubService::timeline()`/`TimelineTab.vue` | Confirmado, sem mudanças | §10 |
| 6 | Esclarecer se `AnamnesisInstance.progress` pode ser reaproveitado para o progresso do convite | Reuso parcial, explicado com precisão: mesmo *padrão* arquitetural, fórmula diferente (etapas heterogêneas vs. lista homogênea de perguntas); mas o valor de `AnamnesisInstance.progress` é lido ao vivo como a fatia "Anamnese" do progresso do convite, sem duplicar a lógica de medir progresso de anamnese | §6 |
| 7 | Última auditoria de reaproveitamento (QR, token, expiração, autosave, auditoria, timeline, notificações, progresso) | Feita — um achado novo relevante: já existe um comando+schedule idêntico ao que este módulo precisa para expirar convites automaticamente (`documents:expire-signature-tokens`) | §12, §18 |

**Rodada 3 (final):**

| # | Pedido | Decisão final | Onde no documento |
|---|---|---|---|
| 1 | No máximo um convite ativo por (paciente, tipo); novo convite cancela automaticamente o anterior; regra explícita e mecanismo justificado | Documentado como regra de domínio de três camadas — service (cancela-então-cria, dentro de transação com lock) + constraint real no banco (índice único parcial, `patient_id`+`kind`, só sobre status não-terminal) — nenhuma camada sozinha bastava | §5.2 |
| 2 | Convite cancelado: link e QR param de funcionar imediatamente, tela amigável (não link genérico/404) | Mesma tela já usada para expirado (§8.3), com o texto específico pedido; explicitamente confirmado que o QR Code não precisa de nenhuma lógica própria de invalidação — ele resolve para o mesmo token, então já está coberto pela checagem de status | §8.3 |
| 3 | Auditoria prévia sobre conflito com histórico, cenário legítimo de dois ativos, e se auto-cancelar é a melhor decisão | Feita — achado relevante: existe um precedente direto e já em produção (`InviteService::createOrUpdate()`, convites de equipe) para "só um convite ativo por destinatário", mas ele resolve isso **reaproveitando a mesma linha** (UPDATE in place) — inaplicável aqui, porque este módulo já decidiu explicitamente preservar histórico como linhas separadas (rodada 1, item 6). A auditoria também identificou uma distinção que precisava ficar explícita: "Reenviar" não cria linha nova (não aciona o auto-cancelamento); só "Gerar novo convite" cria | §5.2, §18 |

---

## 3. Arquitetura geral

### 3.1 — Status do convite ≠ status clínico do paciente

`patients.status` (`ativo|inativo|falecido`) **não ganha nenhum valor novo**. O ciclo de vida do convite vive inteiramente em `patient_invites.status` (§5). Motivo: `PatientStatusService::recalculate()` (e as variantes `recalculateBatch`/`recalculateAll`/`recalculateForClinic`) só sabem excluir `'falecido'` da recalculação automática — um 4º valor de status clínico seria sobrescrito silenciosamente pela primeira consulta lançada. Os badges de convite descrevem o convite, não o paciente; os dois conceitos são independentes por definição (um paciente já `ativo` pode ter um convite de atualização cadastral em aberto, por exemplo).

### 3.2 — Como uma rota pública resolve o tenant sem sessão

`ClinicScope` é um no-op quando `!Auth::check()`. O precedente já resolvido no projeto (`DocumentPublicSignatureController`) busca o registro direto pelo token (`where('token', $token)->first()`) e usa `$model->clinic_id` daí em diante — nunca a sessão. `PatientInvitePublicController` segue exatamente esse padrão. Consultas a `Convenio` no wizard público são filtradas manualmente por `clinic_id` (o `scopeActive()` sozinho não basta fora de contexto autenticado).

### 3.3 — Token: unguessable, único globalmente

Mesmo padrão de `AnamnesisInstance::validation_token` (`bin2hex(random_bytes(32))`), único no banco, sem dependência de sequência.

### 3.4 — Paciente é criado (ou reaproveitado) imediatamente, nunca só ao final do wizard

O badge/painel junto ao nome (§9) e o card de acompanhamento pressupõem que o `Patient` já existe desde que a recepção clica em "Gerar convite" — seja um paciente novo, seja um já existente que teve o telefone reconhecido (§7). Isso também é o que torna o wizard simples de implementar: ele literalmente edita um `Patient` que já existe, reaproveitando a mesma validação de `PatientController::patientValidationRules()`.

### 3.4.1 — Cadastro novo e Atualização cadastral são o mesmo mecanismo técnico, dois fluxos de produto

Tecnicamente, os dois casos (`patient_id` apontando para um `Patient` recém-criado vs. um já existente) usam exatamente a mesma tabela, o mesmo wizard público e o mesmo controller. Mas são duas experiências diferentes do ponto de vista de quem usa o sistema — a recepção pensa em "mandar um paciente novo se cadastrar" e "pedir para um paciente que já existe atualizar os dados dele" como duas ações distintas, com textos, expectativas e pontos de entrada na UI diferentes (§7, §19 Fase 6).

Isso tem uma consequência real no schema: sem guardar explicitamente qual dos dois fluxos deu origem a um convite, não há como o sistema saber depois — `patient_id` sozinho não distingue "paciente que já existia antes deste convite" de "paciente criado por este convite", porque nas duas situações, no momento da consulta, o paciente simplesmente existe. Por isso `patient_invites` ganha uma coluna `kind` (§4), gravada uma única vez na criação do convite, refletindo a intenção do fluxo escolhido pela recepção — não uma inferência calculada depois.

### 3.5 — Duas camadas de dado: colunas denormalizadas + log append-only

Mesmo padrão já usado em `AnamnesisInstance` (guarda `started_at`/`completed_at`/`progress` direto nas próprias colunas) somado a `AnamnesisActivityLog`/`DocumentActivityLog` (log completo, append-only, por entidade). `patient_invites` guarda um pequeno conjunto de colunas denormalizadas para renderizar o painel (§9) sem agregar um log a cada carregamento; `patient_invite_activity_logs` é a fonte de verdade completa da timeline (§10) e resolve a idempotência dos lembretes automáticos (§12).

### 3.6 — QR Code: nenhuma dependência nova

`bacon/bacon-qr-code` já está no `composer.json`, só sem nenhum wrapper de serviço ainda. Não é necessário adicionar `endroid/qr-code` nem `simplesoftwareio/simple-qrcode`. **Decisão fechada.**

### 3.7 — WhatsApp no MVP = link `wa.me`, sem API oficial

Confirmado. `wa.me/55{telefone}?text={mensagem}`, mesmo padrão já usado em `PatientHubService::birthdayInfo()`. A migração futura para Meta Cloud API / Twilio / Evolution / outra é uma decisão separada, de produto e orçamento, fora deste documento. **Decisão fechada.**

---

## 4. Modelo de dados

### `patient_invites`

| Coluna | Tipo | Observação |
|---|---|---|
| `id` | bigint | |
| `clinic_id` | FK `clinics` | |
| `patient_id` | FK `patients` | não-nulo — paciente novo ou já existente (§3.4, §7) |
| `kind` | string | `cadastro` ou `atualizacao` — grava a intenção do fluxo escolhido pela recepção (§3.4.1), usado no badge (§9) e no roadmap (Fase 6, §19). Nunca inferido depois, sempre gravado na criação |
| `token` | string, unique | `bin2hex(random_bytes(32))` |
| `status` | string | 8 valores — ver §5 |
| `channel` | string | `whatsapp, email, link_only` |
| `allow_insurance` | boolean, default false | libera a etapa de Convênio no wizard |
| `allow_anamnesis` | boolean, default false | dispara a etapa de Anamnese (§11) |
| `anamnesis_template_id` | FK `anamnesis_templates`, nullable | só relevante se `allow_anamnesis` |
| `expires_at` | timestamp, nullable | `null` = "Nunca expira" |
| `progress` | tinyint, default 0 | 0–100, mesmo padrão de `AnamnesisInstance.progress` (§6) |
| `current_step` | string, nullable | para retomar o wizard exatamente onde parou |
| `opened_at` | timestamp, nullable | primeiro acesso ao link |
| `started_at` | timestamp, nullable | primeiro campo salvo |
| `completed_at` | timestamp, nullable | cadastro (etapas 1–4) finalizado |
| `anamnesis_completed_at` | timestamp, nullable | só se `allow_anamnesis` |
| `not_responded_flagged_at` | timestamp, nullable | marcado pela automação de 72h (§12) — **não é um status**, é uma flag informativa que não bloqueia o convite |
| `cancelled_at` / `cancelled_by` | timestamp / FK `users`, nullable | |
| `created_by` | FK `users` | |
| `created_at` / `updated_at` | timestamp | |

### Constraint de unicidade

`patient_invites` recebe uma coluna gerada (`active_key`) + índice único sobre ela (mecanismo MySQL — corrigido durante a implementação da Fase 1, ver nota em §5.2), garantindo em nível de banco a regra do §5.2 — no máximo uma linha não-terminal por `(patient_id, kind)`. Detalhes completos, incluindo por que isso sozinho não basta e o que a camada de serviço adiciona por cima, em §5.2.

### `patient_invite_activity_logs` (append-only, sem `updated_at`)

Réplica direta da forma já usada em `AnamnesisActivityLog`/`DocumentActivityLog`: `id`, `clinic_id`, `patient_invite_id`, `action`, `metadata` (json), `actor_type` (`staff` ou `patient` — ao contrário dos outros logs do projeto, nem todo evento tem um `user_id`, porque o próprio paciente gera eventos anônimos), `user_id` (nullable, só quando `actor_type = staff`), `created_at`. A lista completa de `action`s está em §10.

### Alteração aditiva necessária

`ORIGEM_OPTIONS` (`resources/js/lib/patientFormOptions.js`) ganha `{ value: 'convite', label: 'Convite de cadastro' }`, com o ajuste equivalente no `in:` de `PatientController::patientValidationRules()`. Sem isso, todo paciente criado por convite ficaria com `origem = 'manual'`, o que atrapalha qualquer relatório futuro de origem de paciente.

### Campos reaproveitados sem alteração

`Convenio` (`id`, `nome`, `ativo`) e os campos de convênio já existentes em `Patient` (`convenio_id`, `convenio_numero_carteirinha`, `convenio_titular`, `convenio_titular_cpf`, `convenio_titular_parentesco`, `tipo_atendimento`) — a etapa de Convênio do wizard é o mesmo bloco que já existe em `Create.vue`/`Edit.vue`.

---

## 5. Estados do convite

8 estados, nomeados em PT-BR (o valor do enum no banco é o próprio slug em português, mesma convenção já usada em `status`/`canal_lembrete` no restante do projeto):

| Estado (`status`) | Como entra | Terminal? |
|---|---|---|
| `gerado` | recepção confirma o modal | não |
| `enviado` | e-mail efetivamente disparado (Mailable), **ou** o botão de WhatsApp foi clicado (aproximação — ver nota abaixo). Pulado se o canal for "apenas gerar link" | não |
| `visualizado` | primeiro `GET` público no link | não |
| `em_preenchimento` | primeiro campo salvo (autosave) | não |
| `aguardando_conclusao` | etapas de cadastro (1–4) finalizadas, mas `allow_anamnesis = true` e a anamnese ainda não foi concluída. **Pulado inteiramente se `allow_anamnesis = false`** — nesse caso o wizard vai direto para `concluido` | não |
| `concluido` | cadastro (e anamnese, se aplicável) finalizado | **sim** |
| `expirado` | job de automação detecta `now() > expires_at` sem conclusão | **sim** |
| `cancelado` | recepção clica "Cancelar" | **sim** |

**Nota sobre `enviado` no canal WhatsApp:** como o envio via `wa.me` é um link de clique manual (§3.7), o sistema não tem confirmação real de entrega — só sabe que a recepção clicou para gerar a mensagem. Tratar isso como "enviado" é uma aproximação aceita conscientemente; uma confirmação real de entrega só existe com uma API oficial (fora de escopo, §19 Fase 7+).

**"Não respondeu" não é um status.** A automação de 72h (§12) grava `not_responded_flagged_at` e loga o evento — o convite continua no status real em que estava (`enviado`/`visualizado`, tipicamente). Isso resolve uma inconsistência do rascunho anterior, em que "não respondeu" tentava ser ao mesmo tempo uma flag e um estado — mantê-lo fora da máquina de estados principal é mais simples e evita a pergunta "dá pra sair desse estado depois?".

Toda escrita pública (autosave, finalizar, anamnese) valida `status not in (concluido, expirado, cancelado)` a cada request, não só na primeira abertura — a recepção pode cancelar um convite com o paciente ainda com a aba aberta.

### 5.1 — Um paciente pode ter vários convites; nunca reaproveitar token

"Gerar novo convite" **sempre** cria uma linha nova em `patient_invites` — nunca reseta ou reaproveita a linha/token anterior. O histórico completo fica visível (e é exatamente isso que popula a timeline, §10):

```
Convite 1 → expirado
Convite 2 → cancelado
Convite 3 → concluido   (o vigente)
```

O painel junto ao nome (§9) sempre reflete o convite mais recente (`ORDER BY created_at DESC LIMIT 1` por paciente), mas a timeline mostra todos.

### 5.2 — Regra de unicidade: no máximo um convite ativo por paciente + tipo

**Regra de domínio:** para cada combinação de `patient_id` e `kind` (`cadastro` ou `atualizacao`), pode existir no máximo **um** convite em estado não-terminal (`gerado`, `enviado`, `visualizado`, `em_preenchimento`, `aguardando_conclusao`) simultaneamente. Não há limite para convites em estado terminal (`concluido`, `expirado`, `cancelado`) — o histórico completo (§5.1) continua sem nenhuma restrição.

Sempre que um novo convite é criado para uma combinação que já tem um ativo, o convite anterior é **cancelado automaticamente** (`status → cancelado`, com um `action` de log específico — `auto_cancelled_by_new_invite`, não o mesmo `cancelled` de um cancelamento manual, para a timeline deixar claro que não foi a recepção que cancelou) antes do novo ser criado.

**Auditoria feita antes de fechar esta regra** (as três perguntas pedidas):

1. **Conflita com o histórico?** Não. A restrição de unicidade só olha para o subconjunto de status não-terminais; convites já `concluido`/`expirado`/`cancelado` nunca contam para o limite e nunca são tocados. O exemplo do §5.1 (Convite 1 expirado → Convite 2 cancelado → Convite 3 concluído) continua válido exatamente como está.

2. **Existe algum cenário legítimo com dois convites ativos simultâneos, mesmo tipo, mesmo paciente?** Não encontrei nenhum. O caso mais próximo de parecer legítimo — "o paciente perdeu o link antigo, preciso mandar outro sem esperar o antigo expirar" — já é exatamente o que "Gerar novo convite" resolve (cancela o velho, cria o novo); não é um argumento a favor de permitir dois ativos, é o próprio caso de uso que a regra atende. Importante: isso só vale para o **mesmo** `kind`. Um paciente pode perfeitamente ter um convite de `cadastro` ativo e um de `atualizacao` ativo ao mesmo tempo — são combinações diferentes, a regra não impede isso.

3. **Cancelar automaticamente é a melhor decisão, ou deveria rejeitar e exigir um passo manual antes ("já existe um convite ativo, cancele primeiro")?** Aqui a auditoria encontrou um precedente direto no próprio projeto que mudou a resposta: `InviteService::createOrUpdate()` (convites de equipe, `app/Services/InviteService.php`) já resolve exatamente este problema — "só um convite ativo por destinatário" — e o faz reaproveitando a mesma linha (`$existing->update($payload)`, com um `UNIQUE(clinic_id, email)` real na tabela `invites`, `database/migrations/2025_01_01_000006_create_invites_table.php`). **Esse padrão não é aplicável aqui**, porque a rodada 1 deste documento já decidiu explicitamente que convites de paciente preservam histórico como linhas separadas e nunca reaproveitam um token (§5.1) — uma decisão de auditoria (LGPD/rastreabilidade de quem preencheu o quê, quando) mais rígida do que a de um convite interno de equipe. Então a mecânica tem que ser diferente (nova linha + cancelar a anterior, não UPDATE in place) mesmo a regra de domínio sendo conceitualmente a mesma ("só um ativo por chave") que o projeto já usa em outro lugar.

   Dado que a mecânica precisa ser "nova linha + cancela a anterior", rejeitar com um erro e exigir cancelamento manual antes seria fricção redundante **quando a ação que originou a criação já é, ela mesma, um pedido explícito de substituição** — é exatamente o caso do botão "Gerar novo convite" (§9): quem clica nele já está pedindo "recomeça". Auto-cancelar ali é o comportamento esperado pelo próprio rótulo do botão, sem necessidade de confirmação extra.

   **Achado que exigiu um ajuste de UX, não só de mecanismo:** essa mesma colisão pode acontecer pela **outra** porta de entrada — o modal genérico "Enviar cadastro ao paciente" (§7), quando a checagem de telefone duplicado (§7) encontra um paciente que já tem um convite ativo daquele tipo, criado por outro atendente ou esquecido pelo mesmo. Ali, diferente do botão "Gerar novo", a intenção de substituir não está implícita na ação — auto-cancelar silenciosamente o convite de um colega sem avisar seria uma perda de dado surpreendente, não um comportamento esperado. Por isso o modal (§7) precisa mostrar um aviso explícito nesse caso específico ("Este paciente já tem um convite de {tipo} ativo, enviado em {data}. Gerar um novo vai cancelar o anterior. Continuar?") antes de prosseguir — o mesmo mecanismo de auto-cancelamento por trás, mas com confirmação na porta de entrada onde a intenção não é óbvia.

**Mecanismo de garantia — três camadas, cada uma cobrindo o que a outra não cobre sozinha:**

| Camada | O que garante | Por que não basta sozinha |
|---|---|---|
| **Regra de domínio** (documentada aqui) | Define o que "ativo" significa e a política de substituição | É só documentação — nada impede um bug de violá-la sem um mecanismo por trás |
| **Serviço** (`PatientInviteService::create()`): dentro de uma transação, busca o convite ativo existente para `(patient_id, kind)` com `lockForUpdate()`, cancela-o (com o log específico acima), só então insere o novo | Dá a experiência correta — cancelamento gracioso, com log específico, sem erro cru — e evita a corrida mais comum (duas requisições da mesma tela) | Sozinho, sob concorrência real (duas requisições simultâneas, cada uma lendo "nenhum ativo" antes de qualquer uma commitar), ainda pode deixar dois ativos coexistindo por uma fração de segundo, dependendo de timing e isolamento de transação |
| **Constraint no banco** — coluna gerada + índice único no MySQL (ver correção abaixo) sobre `(patient_id, kind)`, ativa só para linhas com `status` não-terminal | É a garantia definitiva, inclusive sob concorrência — o banco rejeita fisicamente uma segunda linha ativa, não importa a corrida | Sozinha, dá um erro de constraint cru para quem perder a corrida — sem o serviço por cima, a experiência seria ruim (um 500 genérico em vez do cancelamento gracioso esperado) |

**Correção feita durante a implementação da Fase 1:** a auditoria original (§18) confirmou o mecanismo checando `.env.example` e o default de `config/database.php`, ambos `pgsql` — mas o `.env` real do projeto usa `DB_CONNECTION=mysql` (MySQL 8.4), uma discrepância que só apareceu ao rodar a migration de verdade (erro de sintaxe do MySQL na cláusula `WHERE` de um `CREATE UNIQUE INDEX`, que só existe no Postgres). Interrompido, reportado e resolvido antes de prosseguir, conforme acordado para qualquer achado arquitetural relevante durante a implementação. MySQL não tem índice único parcial, mas tem o equivalente padrão para "único condicional": uma coluna gerada que só recebe valor quando a condição é satisfeita, com um índice único comum sobre ela — linhas onde a condição não vale recebem `NULL`, e um índice único do MySQL nunca trata dois `NULL`s como colisão entre si:

```sql
`active_key` VARCHAR(255) GENERATED ALWAYS AS (
    CASE WHEN status NOT IN ('concluido','expirado','cancelado')
         THEN CONCAT(patient_id, ':', kind)
         ELSE NULL END
) STORED,
UNIQUE INDEX patient_invites_active_unique (active_key)
```

Mesma garantia, mesmo lugar na tabela de três camadas acima — só a sintaxe mudou para a que o motor de banco realmente em uso suporta. Nenhuma outra decisão deste documento dependia de Postgres especificamente; foi um detalhe de mecanismo, não da regra.

Não existe um precedente de "unicidade condicional" neste projeto ainda (o `UNIQUE(clinic_id, email)` de `invites` é incondicional) — é a primeira vez que esse recurso é usado aqui, mas é um padrão MySQL bem estabelecido, não uma solução exótica, e é consistente com o fato de o projeto já confiar em constraints reais de banco para invariantes de domínio (o próprio `invites` é a prova disso).

---

## 6. Progresso do preenchimento

Dois formatos, os dois pedidos, um dado só por trás:

- **Percentual (`patient_invites.progress`, 0–100):** persistido, recalculado a cada autosave.
- **Checklist por etapa (computado on-the-fly, sem coluna própria):** cada etapa é "✔ completa" com um critério objetivo e determinístico:
  - Dados pessoais: `nome`, `sobrenome` e `telefone` preenchidos (mínimo já garantido na criação do convite).
  - Endereço: `cep` e `logradouro` preenchidos.
  - Convênio: só entra no checklist se `allow_insurance = true`; completa quando `convenio_id` preenchido (ou `tipo_atendimento` diferente de `convenio`, indicando que o paciente optou por não usar convênio).
  - Anamnese: só entra no checklist se `allow_anamnesis = true`; completa quando `anamnesis_completed_at` não é nulo.

O checklist é o que aparece no painel compacto (§9); o percentual é o que aparece no badge (§9) e, por exemplo, numa eventual listagem com muitos convites em aberto ao mesmo tempo.

### 6.1 — Reuso de `AnamnesisInstance.progress`: parcial, e por quê

A pergunta era se o mecanismo de progresso já usado em `AnamnesisInstance` pode ser reaproveitado total ou parcialmente. Resposta precisa:

**A fórmula não pode ser reaproveitada diretamente.** `AnamnesisInstance.progress` mede a proporção de perguntas respondidas dentro de um conjunto homogêneo e fixo (todas as `AnamnesisTemplateQuestion` do template) — é essencialmente uma contagem `respondidas / total`. O progresso do convite, ao contrário, soma etapas heterogêneas de natureza completamente diferente (dados cadastrais, endereço, convênio, anamnese) — não existe uma "pergunta" única a contar, existe um conjunto de critérios distintos por etapa (§6, checklist acima). Aplicar a mesma fórmula de contagem exigiria forçar cadastro/endereço/convênio a virarem pseudo-"perguntas", o que é mais complexo do que só ter uma fórmula de pesos por etapa.

**O que É reaproveitado, e sem duplicar nada:** o *padrão* arquitetural — uma coluna inteira 0–100 persistida na própria entidade, recalculada a cada escrita relevante, em vez de agregada por query a cada renderização — é o mesmo já validado em produção por `AnamnesisInstance`. E, de forma mais direta ainda: quando o wizard chega na etapa de Anamnese e uma `AnamnesisInstance` já existe para aquele convite, a fatia "Anamnese" do progresso do convite **lê o valor de `AnamnesisInstance.progress` diretamente**, em vez de o módulo de convites reinventar sua própria forma de medir quanto da anamnese já foi respondido. Ou seja: zero lógica duplicada para medir progresso de anamnese — essa conta é feita uma única vez, no lugar que já sabe fazê-la.

---

## 7. Fluxo da recepção

Botão **"Enviar cadastro ao paciente"**, ao lado do botão "Novo Paciente" já existente em `Patients/Index.vue`.

Modal:

1. **Nome** e **Sobrenome** — dois campos separados, exatamente como em `Create.vue` (nada de split automático).
2. **Celular*** — ao perder o foco (blur) ou após um pequeno debounce, o sistema consulta se esse telefone já pertence a um paciente da clínica (`GET /patient-invites/check-phone?telefone=...`, endpoint novo, autenticado). Se encontrar:
   - Mostra um aviso inline: *"Encontramos um cadastro existente para {nome do paciente} com este telefone."*
   - Duas opções, a primeira em destaque/recomendada:
     - **"Enviar convite de atualização cadastral"** (recomendado) — o convite é criado apontando `patient_id` para o paciente já existente; o wizard abre pré-preenchido com os dados que já existem, e o paciente só precisa completar/corrigir o que falta.
     - **"Criar outro paciente mesmo assim"** — segue o fluxo normal, cria um `Patient` novo. Útil no caso legítimo de duas pessoas diferentes compartilhando um telefone (ex.: responsável e paciente menor de idade).
   - Se não encontrar nenhum paciente com esse telefone, segue o fluxo normal sem nenhum aviso.
3. **Email** (opcional).
4. Checkbox **"Permitir preencher convênio"** → seta `allow_insurance`.
5. Checkbox **"Solicitar anamnese após cadastro"** → seta `allow_anamnesis`; se marcado, aparece o seletor de **modelo de anamnese** (`AnamnesisTemplate::active()`/`forClinic()`, mesma fonte já usada no fluxo interno).
6. **Canal de envio**: WhatsApp / Email / Apenas gerar link.
7. **Expiração**: Nunca / 7 dias / 15 dias / 30 dias.

Antes de criar o convite, o sistema também verifica se já existe um convite **ativo** do mesmo `kind` para aquele paciente (§5.2). Se existir — cenário mais comum quando o paciente já foi encontrado pelo telefone (passo 2 acima) e a recepção escolhe "atualização cadastral", mas tecnicamente possível em qualquer combinação — o modal mostra um aviso explícito antes de prosseguir: *"Este paciente já tem um convite de {tipo} ativo, enviado em {data}. Gerar um novo vai cancelar o anterior. Continuar?"*. Só depois da confirmação o botão segue adiante. Essa confirmação existe só nesta porta de entrada — o botão "Gerar novo convite" do painel (§9) não precisa dela, porque ali a intenção de substituir já está implícita no próprio rótulo do botão (detalhado em §5.2).

Botão **"Gerar convite"** → cria (ou reaproveita) o `Patient`, cancela automaticamente qualquer convite ativo anterior do mesmo tipo (§5.2), cria o `PatientInvite` (`status = 'gerado'`), loga `invite_created`, gera QR code + link + mensagens, e devolve tudo para o modal exibir/copiar. Se o canal escolhido for e-mail, dispara o `Mailable` na hora e transiciona para `enviado` no sucesso do envio; se for WhatsApp, abre o `wa.me` com a mensagem pronta e loga `whatsapp_link_generated` (transição para `enviado` no clique, com a ressalva do §5); se for "apenas gerar link", fica em `gerado` até o paciente abrir.

**Acompanhamento** — cada patient tem, na tela do paciente, o painel compacto descrito em §9 com os botões Reenviar / Copiar link / Cancelar / Gerar novo, sem precisar sair da página.

---

## 8. Fluxo do paciente

`GET /p/{token}` (a URL pode carregar `?src=qr` quando vem do QR code, versus sem esse parâmetro quando é o link copiado — ver §10, evento "QR Code utilizado") — tela de boas-vindas ("Bem-vindo(a), {nome}. Isso leva ~5 minutos. [Começar]").

Na primeira abertura válida, marca `opened_at`, transiciona `→ visualizado`, loga o evento. Aberturas seguintes não repetem a transição nem o log (idempotente).

Wizard — uma única página Inertia com os passos controlados no client (não uma rota por etapa). As etapas reaproveitam os mesmos blocos/componentes já usados em `Create.vue`/`Edit.vue` (Identificação, `AddressFields.vue`, Responsável Legal condicional via o mesmo checkbox `possui_responsavel_legal`, Convênio condicional), dentro de um layout público em vez de `AppLayout`. Ao concluir a etapa final, se `allow_anamnesis = true` o paciente é levado direto para a anamnese (§11), sem precisar de outro link; senão, `status → concluido`.

### 8.1 — Autosave: requisito obrigatório, não conveniência

Este é um requisito duro, não uma sugestão de UX: nenhuma informação já digitada pode se perder, o paciente deve poder fechar o navegador a qualquer momento, e reabrir o mesmo link deve devolver o formulário exatamente como foi deixado. Isso tem impactos arquiteturais reais, documentados aqui em vez de ficarem implícitos:

- **A fonte de verdade é sempre o servidor, nunca o cliente.** Cada campo altera o `Patient` via `PATCH /p/{token}` (debounced — ~600ms de inatividade após a digitação, mesmo padrão de debounce já usado no projeto, só que via `setTimeout`/`clearTimeout` inline, sem um composable dedicado — é assim que já funciona a busca de `Patients/Index.vue`, e não há necessidade de criar uma abstração nova só para isso). Nada fica retido só em memória do componente entre uma etapa e outra — é isso que permite "fechar o navegador e continuar depois em qualquer aparelho", já que o estado nunca depende de `localStorage`.
- **`current_step` também é persistido a cada troca de etapa**, não só os campos — sem isso, reabrir o link devolveria os dados certos mas na etapa errada.
- **A validação do autosave precisa ser mais permissiva que a validação de conclusão.** `PatientController::patientValidationRules()` (usada pelo cadastro interno) exige `nome`/`sobrenome`, entre outros. No convite, esses dois já existem desde a criação (§7) — mas o restante dos campos, durante o preenchimento, está por definição incompleto a maior parte do tempo. O endpoint `PATCH /p/{token}` não pode usar o mesmo conjunto de regras "tudo obrigatório" do formulário interno; ele valida cada campo individualmente conforme chega (tipo/formato, não presença), e só a validação completa (equivalente a `patientValidationRules()`) roda em `POST /p/{token}/concluir`, bloqueando a finalização até os campos realmente obrigatórios estarem presentes.
- **Limite honesto do que "sem perda de dados" consegue garantir.** Uma página pública, sem armazenamento offline, não consegue prometer perda zero sob qualquer interrupção possível (bateria do celular acaba no meio de uma tecla, por exemplo) — isso exigiria uma aplicação com estado offline-first, fora do escopo deste módulo. O que é tecnicamente garantível e será implementado: (a) autosave por campo com debounce curto, e (b) uma tentativa best-effort de salvar imediatamente ao detectar `beforeunload`/`visibilitychange` (via `navigator.sendBeacon` ou fetch com `keepalive`), cobrindo o caso comum de "o paciente fechou a aba de propósito". A janela de perda realista, na pior hipótese, fica limitada a menos de um intervalo de debounce do único campo sendo digitado no instante exato da interrupção — não a "tudo que foi preenchido antes disso", que está seguro desde que a etapa anterior tenha sido salva.

### 8.2 — Tela de conclusão

Ao finalizar (`POST /p/{token}/concluir`), a resposta não é uma mensagem simples de sucesso — é uma tela dedicada, acolhedora:

> **Cadastro concluído com sucesso.**
> Obrigado por preencher seus dados. A clínica recebeu automaticamente todas as suas informações. Em breve entraremos em contato.

Se o paciente tiver algum agendamento futuro já marcado, a tela mostra isso também:

> **Seu cadastro foi concluído.**
> Nos vemos em: **{data} às {hora}**. Até breve.

Esse lookup **não depende de nenhuma coluna nova em `patient_invites`** — usa a relação `Patient::appointments()` que já existe, filtrando o próximo compromisso futuro com status ainda válido (`Appointment::where('patient_id', $patient->id)->where('start', '>=', now())->whereIn('status', ['scheduled', 'confirmed'])->orderBy('start')->first()`, usando os campos reais já existentes no model `Appointment`). Fazer o lookup dinamicamente no momento da conclusão, em vez de amarrar o convite a um agendamento específico no momento da criação, garante que a tela reflita a agenda real mesmo se uma consulta for marcada depois do convite já ter sido enviado.

### 8.3 — Convite expirado ou cancelado

Se `status in (expirado, cancelado)`, a rota **não devolve um link genérico inválido nem um 404** — mostra a mesma tela dedicada e acolhedora em ambos os casos, só com o texto adaptado ao motivo:

**Expirado:**
> **"Seu convite expirou. Entre em contato com a clínica para solicitar um novo link."**

**Cancelado:**
> **"Este convite foi cancelado pela clínica. Caso ainda deseje concluir seu cadastro, entre em contato com a clínica para solicitar um novo convite."**

O bloqueio é **imediato**, não eventual: toda escrita pública já valida `status not in (concluido, expirado, cancelado)` a cada request (§5), e o próprio `GET /p/{token}` aplica a mesma checagem antes de renderizar o wizard — no instante em que a recepção cancela (ou o job de expiração roda, §12.2), a próxima requisição do paciente, seja lá qual for, já cai nessa tela. Não é necessário nenhum mecanismo de invalidação separado para o QR Code: ele nada mais é do que o mesmo link com `?src=qr` (§10), então resolve para o mesmo token e passa pela mesma checagem — cobrir o link cobre o QR Code automaticamente, não são dois pontos de bloqueio a manter sincronizados.

---

## 9. Painel de acompanhamento (indicador junto ao nome)

Componente novo, `InviteStatusBadge.vue`, reaproveitando o **mesmo padrão de popover sem navegação** já usado em `NavbarDropdown.vue` (slots `trigger`/default com `{open, toggle}`/`{close}` — o mesmo mecanismo já usado para o seletor de DDI do telefone e o picker de cor de marcadores).

**Badge (trigger)** — a cor/rótulo combina `status` com `progress` (§6) e `kind` (§4). Quando `kind = atualizacao`, o convite usa uma variação azul dos rótulos não-terminais, para a recepção diferenciar de imediato "isto é um paciente novo se cadastrando" de "isto é um paciente já existente atualizando os dados dele" — sem precisar abrir o painel:

| `status` | `kind = cadastro` | `kind = atualizacao` |
|---|---|---|
| `gerado` / `enviado` | 🟠 Cadastro pendente *(mostra `(N%)` se `progress > 0`, ex.: "Cadastro pendente (40%)")* | 🔵 Atualização cadastral enviada |
| `visualizado` / `em_preenchimento` | 🟡 Em preenchimento (N%) | 🔵 Atualização em preenchimento (N%) |
| `aguardando_conclusao` | 🟡 Aguardando conclusão (anamnese) | 🔵 Aguardando conclusão (anamnese) |
| `concluido` | 🟢 Cadastro concluído | 🟢 Atualização concluída |
| `expirado` | 🔴 Expirado | 🔴 Expirado |
| `cancelado` | ⚪ Cancelado | ⚪ Cancelado |

Se `not_responded_flagged_at` estiver preenchido e o status ainda não for terminal, some um indicador extra no badge (ex.: um ponto de alerta) sem mudar a cor base — é uma flag, não um estado (§5).

**Conteúdo do painel (aberto sem sair da página):**
- Data de envio (`created_at`, ou `opened_at` se o canal foi "apenas gerar link")
- Última abertura (`opened_at`)
- Última atividade (evento mais recente em `patient_invite_activity_logs`)
- Checklist/percentual de preenchimento (§6)
- Botões: **Copiar link**, **Reenviar**, **Cancelar**, **Gerar novo convite** — mesmos endpoints do §15

---

## 10. Timeline completa de eventos

**Achado da auditoria (§18):** já existe uma infraestrutura de timeline pronta e em uso — `PatientHubService::timeline(Patient $patient): array` agrega eventos de várias fontes (financeiro, clínico, documentos, arquivos, consultas) num array uniforme `{occurred_at, title, detail, category, meta}`, consumido por `TimelineTab.vue` (filtro por categoria, ordenação cronológica, UI já pronta). **Decisão: não criar uma timeline nova.** Os eventos de `patient_invite_activity_logs` entram nesse mesmo agregador, com uma nova categoria (`convite`) e uma cor própria no mapa `categoryIcons` do `TimelineTab.vue`. Isso também significa que o histórico do convite aparece **junto** com o resto do histórico do paciente na aba já existente, em vez de precisar de uma tela separada.

Eventos (`patient_invite_activity_logs.action`) mapeados aos itens pedidos:

| Evento pedido | `action` | `actor_type` |
|---|---|---|
| Convite criado | `invite_created` | staff |
| Link copiado | `link_copied` | staff |
| Email enviado | `email_sent` | staff (disparado pelo sistema em nome da ação da recepção) |
| WhatsApp enviado | `whatsapp_link_generated` | staff |
| QR Code utilizado | `opened_via_qrcode` | patient — distinguido do link normal pelo `?src=qr` na URL (§8) |
| Primeiro acesso | `opened` | patient |
| Dados pessoais concluídos | `personal_data_completed` | patient |
| Convênio concluído | `insurance_step_completed` | patient |
| Anamnese iniciada | `anamnesis_started` | patient |
| Anamnese finalizada | `anamnesis_completed` | patient |
| Cadastro concluído | `completed` | patient |

Mais os eventos administrativos: `cancelled`, `regenerated`, `reminder_24h_sent`, `reminder_48h_sent`, `marked_not_responded` (§12).

---

## 11. Anamnese — maior risco técnico do documento

Hoje **não existe nenhum caminho de preenchimento de anamnese anônimo**. Tudo que existe (`AnamnesisService`, componentes de pergunta/resposta em Vue, rotas de `PatientAnamnesisController`) está sob o grupo de rotas autenticado. A única rota pública relacionada é `GET /anamneses/validar/{token}` — somente leitura, para verificar uma anamnese já assinada.

Antes de comprometer um plano de implementação, um spike curto deve responder: os componentes Vue de pergunta/resposta conseguem renderizar fora do `AppLayout`/contexto autenticado sem reescrita grande? O que `AnamnesisService::createInstance()` faz com `professional_id` quando não há profissional nenhum envolvido? Isso é tratado como sua própria fase de implementação (Fase 4 do roadmap, §19), depois de Convites + Preenchimento cadastral já estarem funcionando e validados — o schema (`allow_anamnesis`, `anamnesis_template_id`) já existe desde o início, então isso não bloqueia nada, só adia a escrita pública em si.

---

## 12. Automações

Duas automações agendadas, cada uma com um precedente direto e exato já existente em `routes/console.php` — nenhuma das duas introduz um padrão novo.

### 12.1 — Lembretes (24h/48h) e flag de "não respondeu" (72h)

Mesmo padrão de `Schedule::command(...)` já usado por `patients:update-auto-status`. Proposta: `php artisan patient-invites:process-reminders`, rodando de hora em hora (`->hourly()`) — busca convites não-terminais cujo `created_at`/`opened_at` cruzou 24h/48h/72h e ainda não têm o log correspondente (idempotência via `exists()` em `patient_invite_activity_logs`, sem precisar de colunas de controle extras).

- **24h sem resposta:** reenvia lembrete por e-mail (se `channel = email`) e loga `reminder_24h_sent`.
- **48h sem resposta:** reenvia de novo, loga `reminder_48h_sent`.
- **72h sem resposta:** grava `not_responded_flagged_at` e loga `marked_not_responded` — **não muda o `status`** (§5).

**Confirmado:** nesta fase, lembrete automático é só por e-mail (fila `database` + `Mailable`, já existentes, zero integração nova). WhatsApp automático fica para quando uma API oficial for escolhida — fora de escopo aqui.

### 12.2 — Expiração automática: precedente exato já existe, mesmo comando a ser copiado

Achado da auditoria final (§18): `app/Console/Commands/ExpireDocumentSignatureTokens.php` já faz, para tokens de assinatura de documento, exatamente o que `patient_invites` precisa fazer para convites — está registrado em `routes/console.php` como `Schedule::command('documents:expire-signature-tokens')->everyFifteenMinutes()`. A forma do comando:

```php
Document::query()
    ->whereNotNull('signature_token_expires_at')
    ->where('signature_token_expires_at', '<', now())
    ->whereNotIn('status', [/* estados terminais */])
    ->get();
// para cada um, $statusService->markExpired($document);
```

Proposta: `php artisan patient-invites:expire`, mesma cadência (`->everyFifteenMinutes()`), mesma forma:

```php
PatientInvite::query()
    ->whereNotNull('expires_at')
    ->where('expires_at', '<', now())
    ->whereNotIn('status', ['concluido', 'expirado', 'cancelado'])
    ->get();
// para cada um, transiciona status -> 'expirado' + loga 'expired' em patient_invite_activity_logs
```

Não é "inspirado em" — é uma cópia estrutural direta do comando que já existe e já roda em produção para um caso quase idêntico (token com expiração, resultado é uma transição de status). Nenhuma decisão de design nova é necessária aqui.

---

## 13. QR Code

`bacon/bacon-qr-code` já instalado, sem uso hoje. Precisa só de um wrapper fino (`PatientInviteQrCodeService` ou método em `PatientInviteService`) que gera PNG/SVG, servido por `GET /patient-invites/{invite}/qrcode`. **Nenhuma dependência nova.**

---

## 14. Permissões

Mesma política de escrita de paciente já em vigor — qualquer usuário autenticado da clínica com acesso a `PatientController::store` pode gerar um convite. Não há indicação de que isso deva ser mais restrito; se for necessário limitar por cargo, isso precisa ser dito explicitamente.

---

## 15. APIs propostas

**Autenticado (staff), clinic-scoped:**
```
GET    /patient-invites/check-phone            verifica telefone duplicado (§7), devolve paciente encontrado ou null
POST   /patient-invites                         cria Patient (ou reaproveita existente) + PatientInvite
GET    /patient-invites/{invite}/qrcode         streama a imagem do QR
POST   /patient-invites/{invite}/resend
POST   /patient-invites/{invite}/cancel
POST   /patient-invites/{invite}/regenerate     sempre cria uma NOVA linha (§5.1), nunca reaproveita
POST   /patient-invites/{invite}/log-link-copied  log do evento "Link copiado" (§10), chamado pelo botão de copiar
```

**Público (anônimo), token-scoped, com throttle — mesmo padrão de `documentos/assinar/{token}` (`throttle:20,1`):**
```
GET    /p/{token}                    tela de boas-vindas / wizard (Inertia)
PATCH  /p/{token}                    autosave de campos do paciente
POST   /p/{token}/concluir           finaliza o cadastro
PATCH  /p/{token}/anamnese           (Fase 4 da implementação, §11)
POST   /p/{token}/anamnese/concluir  (idem)
```

---

## 16. Segurança

- Token: 32 bytes aleatórios, único, nunca sequencial/enumerável.
- Throttle nas rotas públicas de escrita.
- Toda escrita pública valida `status` a cada request, não só no `GET` inicial.
- Tela de expirado/cancelado com mensagem amigável e acionável (§8), nunca um 404/500 cru.
- Nenhum dado de outro paciente/clínica é acessível via token — busca sempre por `token`, nunca por ID sequencial.

---

## 17. Casos de borda

- **Link aberto duas vezes / em duas abas:** idempotente; sem lock pessimista — último autosave vence.
- **Cadastro parcial/abandonado:** sem limpeza automática — o paciente fica visível com o badge "Em preenchimento" até expirar, que é o comportamento desejado.
- **Telefone já cadastrado:** resolvido — ver §7 (detecção + escolha entre atualização cadastral ou novo paciente).
- **Recepção cancela enquanto o paciente preenche:** próxima escrita pública é rejeitada (checagem de status a cada request).
- **Convênio com titular = paciente:** reaproveita o padrão `convenioTitularEhPaciente` (não persistido) já implementado em `Create.vue`/`Edit.vue`.

---

## 18. Auditoria técnica final — reuso de infraestrutura

Feita em duas rodadas, a segunda cobrindo especificamente QR Code, geração de token, expiração, autosave, auditoria, timeline, notificações e progresso, conforme pedido. Achados relevantes:

- **Timeline (§10):** `PatientHubService::timeline()` + `TimelineTab.vue` já existem e já fazem exatamente o que o Fase 6 (histórico) original pedia para outras categorias do paciente. Reaproveitado, não recriado — confirmado e mantido na rodada 2 sem mudanças.
- **Progresso (§6, §6.1):** `AnamnesisInstance.progress` é reaproveitado como *padrão* (coluna 0–100 persistida, recalculada a cada escrita relevante) e, de forma mais direta, como *valor* — a fatia "Anamnese" do progresso do convite lê `AnamnesisInstance.progress` ao vivo em vez de o convite recalcular isso por conta própria. A fórmula geral, porém, não é a mesma (§6.1 explica por quê).
- **Expiração (§12.2) — achado novo desta rodada:** `app/Console/Commands/ExpireDocumentSignatureTokens.php`, agendado via `Schedule::command('documents:expire-signature-tokens')->everyFifteenMinutes()`, já resolve exatamente este problema para tokens de assinatura de documento. `patient-invites:expire` é uma cópia estrutural direta desse comando, mesma cadência, mesma forma de query.
- **Geração de token:** `bin2hex(random_bytes(32))`, mesmo padrão já usado em `AnamnesisInstance::validation_token` e no fluxo de assinatura de documentos — nenhuma lib de geração de token/UUID adicional necessária.
- **Autosave (§8.1) — achado novo desta rodada:** não existe um composable de debounce compartilhado no projeto; o padrão já usado (busca de `Patients/Index.vue`, `DocumentLivePreview.vue`, `Referrals/Index.vue`) é um `setTimeout`/`clearTimeout` inline simples. O autosave do wizard segue esse mesmo padrão em vez de introduzir uma abstração nova — não há infraestrutura de autosave genérica pronta para reaproveitar além disso, então a lógica de debounce em si é nova (mas mínima e consistente com o resto do projeto).
- **Auditoria/log:** `AnamnesisActivityLog`/`DocumentActivityLog` são o precedente direto de forma para `patient_invite_activity_logs` (append-only, por entidade, `action` + `metadata`) — confirmado, sem mudanças desde a rodada 1.
- **Notificações:** reconfirmado — não existe uso do `Notification`/`Notifiable` do Laravel no projeto, só `Mailable` (`TeamInviteMail`, `DocumentSignatureRequestMail` como precedente direto de forma) e o link `wa.me` de clique manual. Nenhuma automação de envio de WhatsApp existe para reaproveitar.
- **Painel sem navegação (§9):** `NavbarDropdown.vue` já é o componente usado no projeto para "clique num elemento pequeno, abre um painel, sem sair da página" — reaproveitado diretamente para `InviteStatusBadge.vue`.
- **QR Code (§13):** `bacon/bacon-qr-code` já está no `composer.json`, sem uso hoje — confirmado, nenhuma lib nova necessária.
- **`ClinicSettingsController` verificado e descartado:** não guarda nada relacionado a convites/expiração hoje (só marca/slogan/logo) — não há infraestrutura de "configuração padrão da clínica" para reaproveitar aqui.
- **Detecção de telefone duplicado (§7):** não existe nenhum mecanismo de deduplicação por telefone hoje — é a única peça genuinamente nova de infraestrutura de busca introduzida por este módulo (as buscas existentes em `PatientController::index` são full-text `like`, não uma checagem exata de duplicidade).
- **Agendamento vinculado (§8.2):** `Patient::appointments()` (relação já existente) e os campos reais do model `Appointment` (`start`, `status` em `scheduled/confirmed/cancelled/no_show/completed`) são suficientes para o lookup dinâmico da tela de conclusão — nenhuma coluna nova em `patient_invites` foi necessária para isso.

- **Unicidade de "ativo por chave" (§5.2) — achado desta rodada:** `InviteService::createOrUpdate()` (convites de equipe) já resolve o mesmo problema de domínio ("só um convite ativo por destinatário"), sustentado por um `UNIQUE(clinic_id, email)` real na tabela `invites`. Examinado em detalhe e **descartado como reaproveitável diretamente**: a mecânica de lá é reaproveitar a mesma linha (UPDATE in place), incompatível com a decisão já fechada de preservar histórico como linhas separadas para convites de paciente (§5.1). A regra de domínio é a mesma; a implementação não podia ser. Confirma, ainda assim, que o projeto já confia em constraints reais de banco para invariantes — suporte adicional para propor o índice único parcial em vez de depender só de lógica de serviço.
- **Driver de banco — correção registrada:** a auditoria original leu `DB_CONNECTION=pgsql` em `.env.example`/`config/database.php` (default) e assumiu Postgres. O `.env` real do projeto é `mysql` (8.4) — só descoberto ao rodar a migration de fato, na implementação da Fase 1. Mecanismo do §5.2 corrigido para o equivalente MySQL (coluna gerada + índice único); nenhuma outra decisão do documento dependia do driver específico. Lição para auditorias futuras: `.env.example`/defaults de config não substituem checar o `.env` real quando a decisão depende do motor de banco.

**Conclusão da auditoria:** não restou nenhum ponto de reaproveitamento pendente de verificação em nenhuma das três rodadas. Toda peça de infraestrutura relevante (QR, token, expiração, auditoria, timeline, progresso, notificações, unicidade de convite ativo) tem um precedente identificado e citado, reaproveitado onde a forma permitia e adaptado, com justificativa explícita, onde uma decisão já fechada deste documento (histórico completo, §5.1) tornava o reaproveitamento direto incompatível.

### 18.1 — Fase 4: dependência não commitada do hub de Anamnese (decisão registrada)

A Fase 4 (Anamnese) reaproveita o hub de Anamnese inteiro — `AnamnesisService`, `LocalSignatureProvider`, os models `AnamnesisInstance`/`AnamnesisTemplate`/`AnamnesisQuestion`/etc., e os componentes Vue `AnamnesisCategoryCard`/`AnamnesisQuestionField`/`AnamnesisSignatureModal`/`AnamnesisSignaturePad` — sem reescrita. Esse hub, porém, **nunca foi commitado** neste repositório (nenhuma linha de histórico em `git log --all`).

**Decisão explícita (auditoria pós-Fase 4):** não versionar o hub de Anamnese agora, mesmo tendo mapeado exatamente seu fechamento de dependência direta (9 models, 3 services, 2 enums, o provider de assinatura, 7 das 8 migrations originais). O motivo é um bloqueio concreto, não preguiça de organização: a migration fundacional do hub (`2026_06_26_400001_create_anamnesis_hub_tables.php`) cria as 7 tabelas de Anamnese **no mesmo arquivo** que cria `patient_tags`/`patient_notes`/`patient_note_tag` — tabelas de um módulo totalmente independente (Marcadores/Notas do Paciente). Separar isso exigiria reescrever uma migration de criação de tabela (mais delicado que os ajustes de coluna já feitos em §5.2/Commit 1), avaliado como desproporcional ao ganho de organização de histórico, e descartado deliberadamente.

**O que fica registrado para quando o hub for commitado de verdade:**
- `resources/js/Components/Anamnesis/AnamnesisCategoryCard.vue` recebeu uma prop `readonly` (default `false`), adicionada pela Fase 4 para reaproveitar o componente no wizard público sem duplicar a renderização de categoria/pergunta — esconde afordances de staff (toggle ON/OFF por pergunta, botão "Adicionar pergunta") que não fazem sentido para o paciente. **Não é uma funcionalidade nova do hub** — é uma adaptação de reuso, e precisa ser preservada (não sobrescrita por uma versão "limpa" de outro lugar) quando esse arquivo for commitado. O comentário no próprio componente já sinaliza isso.
- O fechamento de dependência direta mapeado nesta auditoria (listado acima) é o escopo mínimo que precisaria ser commitado — não o hub inteiro (que também inclui `PatientAnamnesisController`, os controllers de admin de templates/categorias/perguntas, `AnamnesisPdfService`, e a tela de assinatura do dentista, nenhum dos quais a Fase 4 usa).

---

## 19. Roadmap definitivo por fases

| Fase | Escopo | Depende de |
|---|---|---|
| **1 — Convites** | Modal da recepção (nome/sobrenome/celular/email, checkboxes, canal, expiração), detecção de telefone duplicado (§7), regra de convite ativo único por paciente+tipo com cancelamento automático (§5.2), criação de `Patient`+`PatientInvite`, geração de link/QR/mensagens, painel de acompanhamento (§9) | nada — só infraestrutura já existente |
| **2 — Preenchimento cadastral** | Tela pública de boas-vindas, wizard (Dados pessoais + Endereço + Responsável legal condicional), autosave, retomada, tela de expirado/cancelado | Fase 1 |
| **3 — Convênio** | Etapa de Convênio no mesmo wizard, condicionada a `allow_insurance` | Fase 2 (mesma infraestrutura técnica — é a mesma rota/wizard, só habilita mais uma etapa) |
| **4 — Anamnese** | Preenchimento de anamnese público pós-cadastro, condicionado a `allow_anamnesis` | Fase 2/3 concluídas e validadas em produção — maior risco técnico do documento (§11), spike recomendado antes de comprometer prazo |
| **5 — Automações** | Lembretes por e-mail em 24h/48h, flag de "não respondeu" em 72h | Fase 2 (precisa de convites reais em andamento para ter o que lembrar) |
| **6 — Atualização cadastral** | Jornada de produto dedicada ao Fluxo B (§3.4.1): ponto de entrada próprio na ficha de um paciente já existente (ex.: botão "Solicitar atualização cadastral"), mensagens/copy adaptadas ao contexto ("atualize seus dados" em vez de "complete seu cadastro"), e o badge azul distinto (§9) que já depende da coluna `kind` gravada desde a Fase 1 | Fase 1 (reaproveita toda a infraestrutura técnica — mesmo wizard, mesmo controller, mesma tabela — mas é entregue como sua própria fase porque representa uma experiência de produto diferente do Fluxo A, não uma tarefa técnica adicional) |
| **7 — LGPD e recursos futuros** | Aceite eletrônico dos termos, assinatura da LGPD, upload de foto do paciente, upload de documentos (RG/CPF/passaporte/convênio), geolocalização do aceite | Nenhuma decisão de arquitetura tomada aqui as bloqueia — o convite com token, o log append-only e o wizard modular por etapa já foram desenhados para acomodá-las sem retrabalho |

---

## 20. Fora de escopo nesta primeira leva de fases

Tudo que está na Fase 7 (§19) — nenhuma coluna, rota ou componente é proposto para eles neste documento, só a garantia de que a arquitetura das fases 1–6 não precisa ser refeita para acomodá-los depois.
