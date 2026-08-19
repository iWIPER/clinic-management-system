<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\DocumentShare;
use App\Rules\ValidCpf;
use App\Services\Documents\DocumentShareService;
use App\Services\Documents\PasswordDeliveryService;
use App\Services\Documents\PatientIdentityMatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Fluxo público (sem login) de "Ver senha" de um documento compartilhado —
 * mesma família dos outros fluxos públicos por token do projeto
 * (Public\DocumentPublicSignatureController, AnamnesisValidationController).
 *
 * Identidade (nome parcial + CPF) não é autenticação forte — é um filtro
 * de sanidade complementar ao token em si (já não-adivinhável, ver
 * DocumentShare::generateToken()). Ver PatientIdentityMatcher para a regra
 * de comparação de nome.
 */
class DocumentSharePasswordController extends Controller
{
    public function __construct(
        private DocumentShareService $shareService,
        private PasswordDeliveryService $deliveryService,
    ) {}

    public function show(Request $request, string $token): View
    {
        $share = $this->findShare($token);

        if (! $share) {
            return view('document-share-password', ['valid' => false, 'reason' => 'not_found']);
        }

        if (! $share->isUsable()) {
            return view('document-share-password', ['valid' => false, 'reason' => $share->isRevoked() ? 'revoked' : 'expired']);
        }

        if ($share->isIdentityLocked()) {
            return view('document-share-password', ['valid' => false, 'reason' => 'locked']);
        }

        $verified = (bool) $request->session()->get("document_share_verified.{$share->token}");

        if (! $verified) {
            return view('document-share-password', [
                'valid'    => true,
                'verified' => false,
                'token'    => $token,
            ]);
        }

        return view('document-share-password', [
            'valid'    => true,
            'verified' => true,
            'token'    => $token,
            'title'    => $share->displayTitle(),
            'filename' => $share->friendly_filename,
            'password' => $share->password_encrypted,
            'clinicName' => $share->patient->clinic?->displayName() ?? 'a clínica',
        ]);
    }

    public function verify(Request $request, string $token)
    {
        $share = $this->findShare($token);

        if (! $share || ! $share->isUsable()) {
            return back()->withErrors(['identity' => 'Link inválido ou expirado.']);
        }

        if ($share->isIdentityLocked()) {
            return back()->withErrors(['identity' => 'Muitas tentativas. Tente novamente mais tarde.']);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:160',
            'cpf'  => ['required', 'string', new ValidCpf()],
        ]);

        $patient = $share->patient;
        $cpfDigits = preg_replace('/\D/', '', $validated['cpf']);
        $registeredCpfDigits = preg_replace('/\D/', '', (string) $patient->cpf);

        $nameMatches = PatientIdentityMatcher::namesMatch($patient->nome_completo, $validated['name']);
        $cpfMatches = $registeredCpfDigits !== '' && hash_equals($registeredCpfDigits, $cpfDigits);

        if (! $nameMatches || ! $cpfMatches) {
            $share->increment('identity_attempts');

            if ($share->identity_attempts >= DocumentShare::MAX_IDENTITY_ATTEMPTS) {
                $share->update(['identity_locked_until' => now()->addMinutes(DocumentShare::IDENTITY_LOCK_MINUTES)]);
            }

            // Nunca gravamos o CPF/nome tentado — só o resultado.
            $this->shareService->log($share, 'identity_failed', null, $request->ip(), $request->userAgent(), [
                'attempts' => $share->identity_attempts,
            ]);

            return back()->withErrors(['identity' => 'Nome ou CPF não conferem com o cadastro.']);
        }

        $share->update(['identity_attempts' => 0, 'identity_locked_until' => null]);

        if (! $share->password_revealed_at) {
            $share->update(['password_revealed_at' => now(), 'status' => DocumentShare::STATUS_VIEWED]);
        }

        $request->session()->put("document_share_verified.{$share->token}", true);
        $this->shareService->log($share, 'password_revealed', null, $request->ip(), $request->userAgent());

        return redirect()->route('documents.shared.password.show', $token);
    }

    public function viewDocument(Request $request, string $token)
    {
        $share = $this->findShare($token);
        abort_unless($share && $share->isUsable(), 404);
        abort_unless($request->session()->get("document_share_verified.{$share->token}"), 403);

        $shareable = $share->shareable;
        abort_unless($shareable && $shareable->pdf_path, 404);

        $this->shareService->log($share, 'document_viewed', null, $request->ip(), $request->userAgent());

        // Visualização dentro do Wildental usa o PDF original (sem senha) —
        // a senha só protege o arquivo que sai por e-mail/download externo
        // (ver DocumentPdfService::generateProtectedCopyBytes). O usuário já
        // provou identidade nesta mesma sessão, então não precisa digitar a
        // senha de novo para ver online.
        return Storage::disk('s3')->response($shareable->pdf_path);
    }

    public function sendPassword(Request $request, string $token)
    {
        $share = $this->findShare($token);
        abort_unless($share && $share->isUsable(), 404);
        abort_unless($request->session()->get("document_share_verified.{$share->token}"), 403);

        $validated = $request->validate([
            'channel' => 'required|in:email,whatsapp,sms',
        ]);

        $result = $this->deliveryService->send($share, $validated['channel'], $share->password_encrypted);

        $this->shareService->log(
            $share,
            'password_sent_' . $validated['channel'],
            null,
            $request->ip(),
            $request->userAgent(),
            ['status' => $result['status']]
        );

        return response()->json($result);
    }

    private function findShare(string $token): ?DocumentShare
    {
        return DocumentShare::where('token', $token)->first();
    }
}
