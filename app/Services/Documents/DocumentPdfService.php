<?php

namespace App\Services\Documents;

use App\Jobs\ArchiveDocumentToDriveJob;
use App\Models\Document;
use App\Models\DocumentActivityLog;
use App\Services\ClinicLogoService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class DocumentPdfService
{
    public function __construct(private DocumentPlaceholderResolver $resolver) {}

    public function generate(Document $document, ?int $userId = null, $request = null): string
    {
        $document->load(['patient', 'professional', 'clinic', 'signatures', 'template']);

        $dompdf = $this->render($document);

        $filename = 'documents/document-' . $document->id . '.pdf';
        // Sem parâmetro de visibilidade: o bucket usa Object Ownership
        // "BucketOwnerEnforced" (confirmado via AWS read-only), que desabilita
        // ACLs por completo — qualquer PutObject com ACL falha. Privacidade já
        // vem do Block Public Access + política do bucket, não de ACL por objeto.
        Storage::disk('s3')->put($filename, $dompdf->output());

        $document->update([
            'pdf_path'     => $filename,
            'content_hash' => hash('sha256', $document->rendered_html),
        ]);

        DocumentActivityLog::create([
            'clinic_id'   => $document->clinic_id,
            'document_id' => $document->id,
            'patient_id'  => $document->patient_id,
            'document_template_id' => $document->template_id,
            'action'      => 'pdf_generated',
            'user_id'     => $userId,
            'ip_address'  => $request?->ip(),
            'user_agent'  => $request?->userAgent(),
        ]);

        // Fase B5: arquivamento no Drive é best-effort por design (nunca
        // bloqueia a emissão do documento — ver DocumentDriveArchiver) e não
        // precisa acontecer antes desta resposta voltar; movido pra job pra
        // não somar download do S3 + upload multipart ao tempo desta request.
        ArchiveDocumentToDriveJob::dispatch($document->id);

        return $filename;
    }

    /**
     * Gera os bytes de uma cópia protegida por senha (aberta somente com a
     * senha) do mesmo conteúdo já emitido, para o fluxo de "Compartilhar
     * documento" — nunca sobrescreve o pdf_path original (esse continua sem
     * senha, usado na visualização interna já autenticada). Retorna os bytes
     * em vez de gravar direto no S3 — Fase B5: quem chama (o job de
     * compartilhamento) reaproveita os mesmos bytes tanto pro upload quanto
     * pro anexo do e-mail, sem precisar rebaixar do S3 depois.
     */
    public function generateProtectedCopyBytes(Document $document, string $password): string
    {
        $document->loadMissing(['patient', 'professional', 'clinic', 'signatures', 'template']);

        $dompdf = $this->render($document);
        $canvas = $dompdf->getCanvas();

        if (method_exists($canvas, 'get_cpdf')) {
            // Sem senha de "owner" separada — só precisamos exigir a senha
            // para abrir o arquivo (o owner_pass vazio faz o Cpdf gerar uma
            // aleatória internamente quando não informada, o que já é seguro
            // o bastante aqui pois não expomos permissões diferenciadas).
            $canvas->get_cpdf()->setEncryption($password, '', ['print']);
        }

        return $dompdf->output();
    }

    private function render(Document $document): Dompdf
    {
        if (! $document->validation_token) {
            $document->update(['validation_token' => bin2hex(random_bytes(32))]);
            $document->refresh();
        }

        $verifyUrl = route('documents.validate', ['token' => $document->validation_token]);
        $qrDataUri = $this->buildQrDataUri($verifyUrl);
        $logoDataUri = $document->clinic ? ClinicLogoService::dataUri($document->clinic) : null;

        $contentHtml = $this->resolver->resolveSignatureBlocksForPdf($document->rendered_html, $document->signatures);

        $html = View::make('pdf.document', [
            'document'    => $document,
            'clinic'      => $document->clinic,
            'patient'     => $document->patient,
            'logoDataUri' => $logoDataUri,
            'qrDataUri'   => $qrDataUri,
            'contentHtml' => $contentHtml,
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $canvas->page_text(500, 818, 'Página {PAGE_NUM} de {PAGE_COUNT}', null, 7, [0.6, 0.6, 0.6]);

        return $dompdf;
    }

    private function buildQrDataUri(string $url): ?string
    {
        if (! class_exists(\BaconQrCode\Writer::class)) {
            return null;
        }

        try {
            $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                new \BaconQrCode\Renderer\RendererStyle\RendererStyle(120),
                new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
            );
            $writer = new \BaconQrCode\Writer($renderer);
            $svg = $writer->writeString($url);

            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        } catch (\Throwable) {
            return null;
        }
    }
}
