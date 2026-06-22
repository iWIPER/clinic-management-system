# Status de Implementação — Sistema de Gestão para Clínicas (MVP)

> **Seguindo rigorosamente o Prompt de Build original** (ordem da Seção 11).

---

## Fase 0 — Fundação (PRIORIDADE MÁXIMA)

- [x] Projeto Laravel 11 + Inertia + Vue 3 + Tailwind
- [x] Multi-tenancy (clinic_id + Global Scope + BelongsToClinic trait)
- [x] RBAC com spatie/laravel-permission + teams (owner, admin, professional, staff + overrides)
- [x] Entitlements / limites por plano (Start Grátis → Premium)
- [x] Onboarding completo (escolha de papel → criar clínica → convidar equipe)
- [x] Autenticação básica (Breeze stubs + redirecionamentos)
- [x] Migrações de fundação (clinics, clinic_user, plans, invites, etc.)

**Status**: Concluído

---

## Fase 1 — Núcleo (ordem exata do prompt)

### 1. Pacientes
- [x] CRUD completo
- [x] Busca por nome/CPF/telefone
- [x] Ficha do paciente com histórico
- [x] Formulário completo (todos os campos do prompt)

**Status**: Concluído

### 2. Agenda (Agendamentos)
- [x] View Lista + Calendário
- [x] Criar agendamento vinculado a paciente + tratamento
- [x] Duração automática vinda do tratamento
- [x] Filtros por profissional / data / status

**Status**: Concluído

### 3. Consultas
- [x] Fluxo: Check-in → Aguardando → Em atendimento → Finalizado
- [x] Tela de atendimento com anotações
- [x] Integração com agendamento (botão Check-in)
- [x] Prontuário básico (SOAP)

**Status**: Concluído

### 4. Procedimentos
- [x] Catálogo de tratamentos (CRUD)
- [x] Biblioteca semente de tratamentos odontológicos
- [x] Registro de execução dentro da consulta
- [x] Criação de ProcedureExecution

**Status**: Concluído

### 5. Estoque básico
- [x] Cadastro de itens de estoque
- [x] Entrada manual de estoque
- [x] Baixa automática ao registrar execução de procedimento (via treatment_materials)
- [x] Alerta visual de estoque baixo

**Status**: Concluído

### 6. Financeiro básico
- [x] Lançamentos de receita/despesa
- [x] Orçamento (rascunho/aprovado)
- [x] Criação automática de transação ao executar procedimento
- [x] Calculadora de precificação (Hora Técnica + Hora Clínica)
- [x] Visão geral (receita × despesa)

**Status**: Concluído

### 7. Fotos no Drive (Google Drive do cliente)
- [x] OAuth 2.0 com escopo `drive.file`
- [x] Armazenamento criptografado do refresh_token (`clinic_storage_connections`)
- [x] Criação automática de pasta por paciente (`drive_folder_id`)
- [x] Upload de foto → salva apenas metadados + `drive_file_id`
- [x] Galeria de fotos no paciente (thumbnails + link para Drive)
- [x] Tratamento de conexão (botão conectar / aviso quando não conectado)

**Status**: Concluído

---

## Resumo Geral

| Módulo              | Status     | Ordem no Prompt |
|---------------------|------------|-----------------|
| Fundação            | ✅ Concluído | Fase 0         |
| Pacientes           | ✅ Concluído | 1º             |
| Agenda              | ✅ Concluído | 2º             |
| Consultas           | ✅ Concluído | 3º             |
| Procedimentos       | ✅ Concluído | 4º             |
| Estoque             | ✅ Concluído | 5º             |
| Financeiro          | ✅ Concluído | 6º             |
| Fotos no Drive      | ✅ Concluído | 7º (último)    |

---

## Próximos Passos (sugestão)

- [ ] Validar o **fluxo-ouro completo** (Seção 6) ponta a ponta
- [ ] Adicionar testes básicos
- [ ] Melhorar tratamento de erros do Google Drive (token revogado, cota, etc.)
- [ ] Adicionar importação CSV de pacientes
- [ ] Fase 2 (itens fora do MVP)

---

**Última atualização**: 22/06/2026

Este arquivo é atualizado automaticamente conforme o agente avança.