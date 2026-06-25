# Gestão de Clínicas — SaaS MVP (Odontologia + Multi-especialidade)

Sistema SaaS de gestão para clínicas de saúde, com foco inicial em odontologia e arquitetura multi-tenant.

O objetivo do projeto é centralizar pacientes, agenda, prontuário, procedimentos, estoque, financeiro e documentação clínica em uma única plataforma.

**Conformidade:** Português do Brasil + LGPD.

## Status do Projeto

**Status atual:**

* Fundação da plataforma implementada
* Estrutura multi-tenant configurada
* Base de autenticação e permissões em desenvolvimento
* Início da implementação dos módulos clínicos e operacionais

Para detalhes completos da arquitetura e planejamento, consulte `docs/BRIEFING.md`.

## Stack

* Backend: Laravel 11 (PHP 8.3)
* Frontend: Inertia.js + Vue 3 (Composition API) + Vite
* CSS: Tailwind CSS
* Banco de dados: PostgreSQL 16
* Autenticação: Laravel Breeze (Inertia)
* Controle de acesso: spatie/laravel-permission
* Billing: Laravel Cashier (Stripe)
* Armazenamento de fotos clínicas: Google Drive (OAuth2)
* Armazenamento geral: S3 ou local

## Como Executar Localmente

### Windows (Recomendado)

**Laragon**

https://laragon.org/

Inclui PHP, Composer, Node.js, MySQL/PostgreSQL e Nginx em uma única instalação.

**Alternativa oficial**

https://herd.laravel.com/windows

### Instalação das Dependências

```bash
cd C:\Users\drehs\dev\gestao-clinicas

composer install
npm install

cp .env.example .env

php artisan key:generate

php artisan migrate

php artisan db:seed

php artisan serve
npm run dev
```

## Banco de Dados

O projeto utiliza PostgreSQL com isolamento por tenant através de:

* Global Scopes
* Controle de acesso por clínica
* Estratégias de segregação de dados definidas na arquitetura da aplicação

## Estrutura do MVP

### Fundação

* [x] Multi-tenancy
* [ ] Controle de acesso por perfis e permissões
* [ ] Gestão de planos
* [ ] Onboarding
* [ ] Convites para equipe

### Módulos Principais

* Pacientes
* Agenda
* Consultas
* Procedimentos
* Tratamentos
* Prontuário clínico
* Estoque
* Financeiro

### Fluxo Principal

O fluxo mínimo esperado para o MVP é:

Paciente → Agendamento → Check-in → Atendimento → Prontuário → Procedimentos → Estoque → Pagamento

## Integração com Google Drive

As fotos clínicas são armazenadas diretamente no Google Drive da clínica.

Características:

* OAuth2 por clínica
* Tokens armazenados de forma segura
* Armazenamento apenas de IDs e metadados no banco
* Arquivos mantidos sob controle da própria clínica

## Roadmap

Itens planejados para versões futuras:

* Integração com WhatsApp
* Recursos assistidos por IA
* Marketplace
* Comissões
* Relatórios avançados
* Aplicativo mobile

## Comandos Úteis

```bash
php artisan migrate:fresh --seed

php artisan test
```

## Documentação

A documentação funcional, técnica e arquitetural está disponível em:

```text
docs/BRIEFING.md
```

## Licença

Projeto privado em desenvolvimento por Wilk Cosentino Pereira e Joseane Lelis.
