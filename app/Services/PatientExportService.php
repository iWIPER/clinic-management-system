<?php

namespace App\Services;

use App\Models\Patient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Única fonte das colunas de exportação de pacientes — CSV (streamCsv) e
 * Excel (App\Exports\PatientsExport) consomem exatamente a mesma definição
 * de columns(), então os dois formatos nunca divergem entre si nem exigem
 * reescrita quando uma coluna muda.
 */
class PatientExportService
{
    /**
     * @return array<string, callable(Patient): string>
     */
    public function columns(): array
    {
        return [
            'ID' => fn (Patient $p) => (string) $p->id,
            'Nome completo' => fn (Patient $p) => $p->nome_completo,
            'Documento' => fn (Patient $p) => $p->documentInfo()['number'] ?? 'Sem documento',
            'Tipo do documento' => fn (Patient $p) => $p->documentInfo()['type'] ?? 'Sem documento',
            'Data de nascimento' => fn (Patient $p) => $p->nascimento?->format('d/m/Y') ?? 'Não informado',
            'Idade' => fn (Patient $p) => $p->idade !== null ? "{$p->idade} anos" : 'Não informado',
            'Sexo' => fn (Patient $p) => $p->sexo ?? 'Não informado',
            'Telefone' => fn (Patient $p) => $p->telefone ?? 'Não informado',
            'E-mail' => fn (Patient $p) => $p->email ?? 'Não informado',
            'Endereço' => fn (Patient $p) => $this->formatAddress($p),
            'Bairro' => fn (Patient $p) => $p->bairro ?? 'Não informado',
            'Cidade' => fn (Patient $p) => $p->cidade ?? 'Não informado',
            'Estado' => fn (Patient $p) => $p->estado ?? 'Não informado',
            'CEP' => fn (Patient $p) => $p->cep ?? 'Não informado',
            'Responsável' => fn (Patient $p) => $p->responsavel_legal_nome ?? 'Não informado',
            'Documento do responsável' => fn (Patient $p) => $p->guardianDocumentInfo()['number'] ?? 'Sem documento',
            'Contato de emergência' => fn (Patient $p) => $this->formatEmergencyContact($p),
            'Convênio' => fn (Patient $p) => $p->convenio?->nome ?? 'Particular',
            'Número da carteirinha' => fn (Patient $p) => $p->convenio_numero_carteirinha ?? 'Não informado',
            'Categorias' => fn (Patient $p) => $p->markers->isNotEmpty() ? $p->markers->pluck('name')->join(', ') : 'Sem categorias',
            'Observações' => fn (Patient $p) => $this->formatNotes($p),
            'Origem' => fn (Patient $p) => $p->origem ? ucfirst($p->origem) : 'Não informado',
            'Data de cadastro' => fn (Patient $p) => $p->created_at?->format('d/m/Y H:i') ?? 'Não informado',
            'Última atualização' => fn (Patient $p) => $p->updated_at?->format('d/m/Y H:i') ?? 'Não informado',
            'Última consulta' => fn (Patient $p) => $this->formatLastConsultation($p),
        ];
    }

    public function headings(): array
    {
        return array_keys($this->columns());
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function rowsFor(Collection $patients): array
    {
        $columns = $this->columns();

        return $patients->map(fn (Patient $p) => collect($columns)
            ->map(fn (callable $resolve) => $resolve($p))
            ->values()
            ->all()
        )->all();
    }

    public function streamCsv(Collection $patients, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($patients) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

            fputcsv($handle, $this->headings());

            foreach ($this->rowsFor($patients) as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function formatAddress(Patient $p): string
    {
        if (! $p->logradouro) {
            return 'Não informado';
        }

        $address = $p->logradouro . ($p->numero ? ", {$p->numero}" : '');

        return $p->complemento ? "{$address} - {$p->complemento}" : $address;
    }

    private function formatEmergencyContact(Patient $p): string
    {
        $parts = array_filter([$p->contato_emergencia_nome, $p->contato_emergencia_telefone]);

        return $parts ? implode(' - ', $parts) : 'Não informado';
    }

    private function formatNotes(Patient $p): string
    {
        $titles = $p->notes->pluck('title')->filter();

        return $titles->isNotEmpty() ? $titles->join('; ') : 'Sem observações';
    }

    /**
     * consultations_max_check_in_at vem de withMax('consultations',
     * 'check_in_at') — um agregado cru do banco, sem o cast "datetime" que
     * Consultation::$casts define (esse cast só se aplica a atributos de um
     * model Consultation de verdade, não a uma coluna agregada no Patient).
     */
    private function formatLastConsultation(Patient $p): string
    {
        $lastCheckIn = $p->consultations_max_check_in_at ?? null;

        return $lastCheckIn ? Carbon::parse($lastCheckIn)->format('d/m/Y H:i') : 'Sem consultas registradas';
    }
}
