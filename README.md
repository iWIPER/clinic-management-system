# Gestão de Clínicas — SaaS MVP (Odontologia + Multi-especialidade)

> **Do agendamento ao recebimento, a clínica inteira numa plataforma só — sem a burocracia de 7 sistemas separados.**

Sistema multi-tenant de gestão para clínicas de saúde, com foco inicial em **odontologia**, construído em Laravel 11 + Inertia + Vue 3 + Tailwind.

**Conformidade**: Português do Brasil + LGPD.

## Status do Projeto

- **Fase atual**: Fundação (Fase 0) + início do Núcleo (Fase 1)
- Estrutura criada com base no **Prompt de Build completo** (ver `docs/BRIEFING.md`).

## Stack

- Backend: Laravel 11 (PHP 8.3)
- Frontend: Inertia.js + Vue 3 (Composition API) + Vite
- CSS: Tailwind CSS
- Banco: PostgreSQL 16 (RLS + isolamento por tenant)
- Auth: Laravel Breeze (Inertia)
- RBAC: spatie/laravel-permission (com teams)
- Billing: Laravel Cashier (Stripe)
- Fotos clínicas: Google Drive do cliente (OAuth + drive.file scope)
- Armazenamento geral: S3 / local

## Como rodar (desenvolvimento local)

### Opção Recomendada no Windows (fácil)

1. **Instale Laragon** (recomendado no Brasil):
   - https://laragon.org/
   - Inclui PHP 8.3, Composer, Node, MySQL/Postgres, Nginx.

2. Ou use **Laravel Herd** (oficial):
   - https://herd.laravel.com/windows

3. Ou instale manualmente (elevated PowerShell):
   ```powershell
   # Como Administrador
   choco install php --version=8.3.13 -y
   choco install composer -y
   # Adicione C:\php ao PATH se necessário
   ```

### Depois de ter PHP + Composer

```bash
cd C:\Users\drehs\dev\gestao-clinicas

# Instalar dependências PHP
composer install

# Instalar dependências JS
npm install

# Copiar .env
cp .env.example .env

# Gerar chave
php artisan key:generate

# Configurar banco (veja .env)
# Rode as migrations depois de configurar o Postgres

php artisan migrate

# Seed inicial (planos + tratamentos odontológicos)
php artisan db:seed

# Dev servers (dois terminais)
php artisan serve
npm run dev
```

### Banco de dados (PostgreSQL)

- Recomendado: Docker Compose (ver `docker-compose.yml` exemplo) ou Postgres via Laragon/WSL.
- O projeto usa **RLS (Row Level Security)** + Global Scopes.

## Estrutura do MVP (conforme briefing)

### Fundação (Fase 0 — prioridade)

- [x] Multi-tenancy (`clinic_id` + Global Scope + RLS)
- [ ] RBAC (owner/admin/professional/staff + overrides)
- [ ] Entitlements / Planos (Start Grátis → Premium)
- [ ] Onboarding + Auth + Convites

### Núcleo (Fase 1)

1. Pacientes (CRUD + import CSV + drive_folder_id)
2. Agenda (Agendamentos)
3. Consultas (fluxo check-in → em atendimento → finalizado)
4. Procedimentos + Tratamentos (catálogo + execução + baixa estoque)
5. Prontuário (SOAP + galeria de fotos no Google Drive)
6. Estoque básico
7. Financeiro básico (orçamentos, transações, precificação)

**Fluxo-ouro** que deve funcionar no final do MVP:
Cadastro Paciente → Agendamento → Check-in → Consulta + Prontuário + Fotos + Execução → Baixa no Estoque → Orçamento/Pagamento

## Google Drive Integration (Fotos Clínicas)

- OAuth2 por clínica (scope: `drive.file`)
- Refresh token criptografado em `clinic_storage_connections`
- `patient_photos` armazena apenas IDs + metadados (bytes ficam no Drive do dentista)
- Responsabilidade de retenção = da clínica (LGPD + CFO)

## Fora do MVP (roadmap explícito)

Ver seção 9 do briefing: WhatsApp + IA, Marketplace, Comissões, etc.

## Comandos úteis

```bash
php artisan migrate:fresh --seed
php artisan test
```

## Contribuição / Próximos Passos

Siga a ordem do `docs/BRIEFING.md` seção 11.

---

**Próximo passo do agente**: Continuar scaffolding da Fundação + primeiras migrations e models do multi-tenancy.
