<?php

namespace App\Services\Documents;

use App\Jobs\GenerateAndSendDocumentShareJob;
use App\Mail\DocumentShareMail;
use App\Models\AnamnesisInstance;
use App\Models\Document;
use App\Models\DocumentShare;
use App\Models\DocumentShareLog;
use App\Models\Patient;
use App\Services\Anamnesis\AnamnesisPdfService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentShareService
{
    private const TOKEN_TTL_DAYS = 7;
    private const ATTACHMENT_MAX_BYTES = 5 * 1024 * 1024; // 5 MB — acima disso, só o link.
    private const PASSWORD_LENGTH = 10;

    public function __construct(
        private DocumentPdfService $documentPdfService,
        private AnamnesisPdfService $anamnesisPdfService,
    ) {}

    /**
     * Fase B5: só monta a linha (senha já gerada e criptografada em repouso
     * via cast 'encrypted') e despacha o job — a geração do PDF protegido
     * (~5s+ de render/criptografia, medido localmente) + upload S3 + e-mail
     * não bloqueiam mais a resposta HTTP. O job (generateAndSend() abaixo)
     * é quem faz esse trabalho pesado.
     */
    public function share(
        Document|AnamnesisInstance $shareable,
        Patient $patient,
        string $recipientEmail,
        ?string $recipientName,
        int $creatorUserId,
        ?string $ip = null,
        ?string $userAgent = null,
    ): DocumentShare {
        $password = $this->generatePassword();
        $token = DocumentShare::generateToken();
        $storagePath = 'document-shares/' . $token . '.pdf';

        $share = DocumentShare::create([
            'clinic_id'          => $patient->clinic_id,
            'patient_id'         => $patient->id,
            'shareable_type'     => $shareable::class,
            'shareable_id'       => $shareable->id,
            'token'              => $token,
            'recipient_email'    => $recipientEmail,
            'recipient_name'     => $recipientName,
            'friendly_filename'  => $this->friendlyFilename($shareable, $patient),
            'storage_path'       => $storagePath,
            'password_encrypted' => $password, // cast 'encrypted' cuida da criptografia ao salvar
            'status'             => DocumentShare::STATUS_PENDING,
            'generation_status'  => DocumentShare::GENERATION_PROCESSING,
            'expires_at'         => now()->addDays(self::TOKEN_TTL_DAYS),
            'created_by_id'      => $creatorUserId,
        ]);

        $this->log($share, 'created', $creatorUserId, $ip, $userAgent);

        GenerateAndSendDocumentShareJob::dispatch($share->id);

        return $share;
    }

    /**
     * Chamado pelo GenerateAndSendDocumentShareJob (nunca direto pelo
     * request). Idempotente: se já foi enviado, não refaz nada — protege
     * contra o job rodar duas vezes (retry após timeout, redispatch manual).
     * Nunca recebe a senha por parâmetro/construtor de job — sempre relida
     * do próprio $share (cast 'encrypted', mesmo mecanismo que
     * PasswordDeliveryService já usa pra revelar a senha ao destinatário),
     * então o payload serializado do job nunca carrega a senha em texto
     * puro pela tabela `jobs`.
     */
    public function generateAndSend(DocumentShare $share): void
    {
        if ($share->generation_status === DocumentShare::GENERATION_SENT) {
            return;
        }

        // Fase B5: como a geração agora é assíncrona, existe uma janela
        // entre share() e o job rodar em que a clínica pode revogar o
        // compartilhamento antes do e-mail sair — nunca gerar/enviar depois
        // de revogado.
        if ($share->isRevoked()) {
            return;
        }

        $shareable = $share->shareable;
        if (! $shareable) {
            throw new \RuntimeException("DocumentShare #{$share->id}: shareable não encontrado (documento/anamnese excluído?)");
        }

        $password = $share->password_encrypted; // decriptado automaticamente pelo cast ao ler

        $pdfBytes = $this->generateProtectedCopyBytes($shareable, $password);
        Storage::disk('s3')->put($share->storage_path, $pdfBytes);

        $revealUrl = route('documents.shared.password.show', $share->token);
        $attachment = strlen($pdfBytes) <= self::ATTACHMENT_MAX_BYTES ? $pdfBytes : null;

        // Já estamos dentro de um job (fora do ciclo de request) — envio
        // direto aqui, sem enfileirar de novo.
        Mail::to($share->recipient_email)->send(new DocumentShareMail($share, $revealUrl, $attachment));

        $share->update(['sent_at' => now(), 'generation_status' => DocumentShare::GENERATION_SENT]);
        $this->log($share, 'sent_email', $share->created_by_id, null, null, ['attached' => $attachment !== null]);
    }

    /**
     * Revoga o link de compartilhamento — bloqueia toda visualização/revelação
     * de senha NOVA a partir de agora (ver DocumentSharePasswordController,
     * que checa isUsable() em toda rota pública).
     *
     * Deliberadamente NÃO apaga o objeto em document-shares/{token}.pdf no S3:
     * revogar não "desenvia" um e-mail já entregue nem apaga uma cópia que o
     * destinatário já tenha baixado antes da revogação — isso é inerente a
     * qualquer compartilhamento de arquivo, não uma limitação corrigível
     * apagando o objeto. Manter o objeto também preserva o rastro de
     * auditoria (o que foi de fato enviado) caso seja necessário revisar o
     * incidente depois. Se um requisito futuro de "apagar de vez" surgir,
     * deve ser uma ação explícita e documentada separada desta.
     */
    public function revoke(DocumentShare $share, int $userId): void
    {
        $share->update(['revoked_at' => now(), 'status' => DocumentShare::STATUS_REVOKED]);
        $this->log($share, 'revoked', $userId);
    }

    public function log(DocumentShare $share, string $action, ?int $userId = null, ?string $ip = null, ?string $userAgent = null, array $metadata = []): void
    {
        DocumentShareLog::create([
            'clinic_id'          => $share->clinic_id,
            'document_share_id' => $share->id,
            'action'             => $action,
            'user_id'            => $userId,
            'ip_address'         => $ip,
            'user_agent'         => $userAgent,
            'metadata'           => $metadata,
        ]);
    }

    private function generateProtectedCopyBytes(Document|AnamnesisInstance $shareable, string $password): string
    {
        return match (true) {
            $shareable instanceof Document => $this->documentPdfService->generateProtectedCopyBytes($shareable, $password),
            $shareable instanceof AnamnesisInstance => $this->anamnesisPdfService->generateProtectedCopyBytes($shareable, $password),
        };
    }

    private function friendlyFilename(Document|AnamnesisInstance $shareable, Patient $patient): string
    {
        $label = $shareable instanceof Document ? $shareable->template_name : $shareable->displayName();
        $patientName = trim($patient->nome . ' ' . $patient->sobrenome);

        return $this->slug($label) . '-' . $this->slug($patientName) . '.pdf';
    }

    private function slug(string $value): string
    {
        $slug = Str::of($value)->ascii()->replaceMatches('/[^A-Za-z0-9]+/', '-')->trim('-')->value();

        return $slug !== '' ? $slug : 'documento';
    }

    private function generatePassword(): string
    {
        // Sem 0/O, 1/I/l (ambíguos); nunca derivada de dado do paciente.
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $password = '';
        for ($i = 0; $i < self::PASSWORD_LENGTH; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }

}
