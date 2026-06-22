# Instruções de Instalação - Gestão de Clínicas

## Pré-requisitos

Você precisa ter:

- PHP 8.3+
- Composer
- Node.js 20+ + npm
- PostgreSQL 16 (ou use o docker-compose.yml)
- Git

## Passo a passo rápido (Windows recomendado)

1. **Instale ferramentas** (escolha uma):
   - **Melhor opção**: Laragon (https://laragon.org/)
   - Ou Laravel Herd
   - Ou rode como admin:
     ```powershell
     choco install php --version=8.3.13 -y
     choco install composer -y
     ```

2. No terminal:

```powershell
cd C:\Users\drehs\dev\gestao-clinicas

composer install
npm install

cp .env.example .env
php artisan key:generate
```

3. Banco de dados

```bash
# Opção Docker
docker compose up -d

# Ou configure manualmente no .env (DB_*)
```

4. Migrations + seeds

```bash
php artisan migrate
php artisan db:seed
```

5. Rode

```bash
# Terminal 1
php artisan serve

# Terminal 2
npm run dev
```

Acesse http://localhost:8000

## Próximos passos após rodar

- Registrar um usuário (será o owner)
- Criar a primeira clínica no onboarding (a implementar)
- Testar o fluxo-ouro

## Pacotes que ainda precisam ser instalados (após composer install)

```bash
composer require spatie/laravel-permission
composer require laravel/cashier
composer require google/apiclient
composer require maatwebsite/excel
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Laravel\Cashier\CashierServiceProvider"
```

Edite `app/Providers/AppServiceProvider.php` se necessário.

## Configuração do Google Drive (Fase 1)

Criar credenciais OAuth no Google Cloud Console com escopo `https://www.googleapis.com/auth/drive.file`

## Dicas

- Para RLS no Postgres (defesa em profundidade), veja migrations e um arquivo extra em `database/migrations/..._enable_rls.sql` (será adicionado).
- Use `php artisan make:controller` normalmente.

Boa sorte com o projeto!
