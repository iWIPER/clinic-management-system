<?php

namespace Database\Seeders;

use App\Models\Treatment;
use Illuminate\Database\Seeder;

class TreatmentLibrarySeeder extends Seeder
{
    public function run(): void
    {
        $treatments = [
            // Odontologia básica (semente)
            ['nome' => 'Consulta Odontológica', 'especialidade' => 'Clínica Geral', 'duracao_padrao' => 30, 'preco_base' => 120],
            ['nome' => 'Profilaxia / Limpeza', 'especialidade' => 'Clínica Geral', 'duracao_padrao' => 45, 'preco_base' => 180],
            ['nome' => 'Restauração de Resina', 'especialidade' => 'Clínica Geral', 'duracao_padrao' => 40, 'preco_base' => 250],
            ['nome' => 'Tratamento de Canal (Incisivo)', 'especialidade' => 'Endodontia', 'duracao_padrao' => 60, 'preco_base' => 650],
            ['nome' => 'Extração Simples', 'especialidade' => 'Cirurgia', 'duracao_padrao' => 25, 'preco_base' => 180],
            ['nome' => 'Extração de Siso', 'especialidade' => 'Cirurgia', 'duracao_padrao' => 45, 'preco_base' => 450],
            ['nome' => 'Aplicação de Flúor', 'especialidade' => 'Clínica Geral', 'duracao_padrao' => 15, 'preco_base' => 80],
            ['nome' => 'Clareamento Dental', 'especialidade' => 'Estética', 'duracao_padrao' => 90, 'preco_base' => 1200],
            ['nome' => 'Implante Dentário (por dente)', 'especialidade' => 'Implantodontia', 'duracao_padrao' => 60, 'preco_base' => 2800],
            ['nome' => 'Aparelho Ortodôntico - Instalação', 'especialidade' => 'Ortodontia', 'duracao_padrao' => 60, 'preco_base' => 2200],
        ];

        foreach ($treatments as $t) {
            Treatment::updateOrCreate(
                ['nome' => $t['nome']],
                array_merge($t, ['ativo' => true])
            );
        }
    }
}
