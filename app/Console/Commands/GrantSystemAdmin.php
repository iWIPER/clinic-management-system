<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SystemAdminService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Bootstrap do primeiro System Admin — e para promover qualquer outro
 * diretamente pelo terminal (ex.: acesso perdido, ambiente novo).
 *
 * O primeiro System Admin planejado é suportewildental@gmail.com — não
 * criamos essa conta automaticamente (não inventar usuário fictício): rode
 * este comando com esse e-mail assim que a conta existir de verdade:
 *
 *   php artisan admin:grant-system-admin suportewildental@gmail.com
 *
 * Depois do primeiro admin promovido, use a própria área "System Admins"
 * do backoffice (Admin > System Admins) — este comando continua existindo
 * só como via de emergência fora do navegador.
 */
class GrantSystemAdmin extends Command
{
    protected $signature = 'admin:grant-system-admin {email}';

    protected $description = 'Concede o privilégio de System Admin a um usuário existente, pelo e-mail';

    public function handle(SystemAdminService $service): int
    {
        $email = $this->argument('email');
        $user  = User::where('email', $email)->first();

        if (! $user) {
            $this->error("Nenhum usuário encontrado com o e-mail {$email}. A conta precisa existir antes de ser promovida — este comando não cria contas.");

            return self::FAILURE;
        }

        try {
            $service->grant($user, null);
        } catch (ValidationException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("{$user->name} ({$user->email}) agora é System Admin.");

        return self::SUCCESS;
    }
}
