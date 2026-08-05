<?php

namespace App\Services;

use App\Models\Clinic;
use Illuminate\Support\Facades\Storage;

class ClinicLogoService
{
    public const DEFAULT_LOGOS = [
        ['filename' => 'toothflow.png',      'label' => 'Flow'],
        ['filename' => 'toothgeometric.png', 'label' => 'Geometric'],
        ['filename' => 'toothleaf.png',      'label' => 'Leaf'],
        ['filename' => 'toothminimal.png',   'label' => 'Minimal'],
        ['filename' => 'toothpremium.png',   'label' => 'Premium'],
        ['filename' => 'toothshield.png',    'label' => 'Shield'],
    ];

    private const FALLBACK_FILE = 'cliniflow-default.png';

    /**
     * Lista de logos padrão com URL pública — para o modal de seleção.
     */
    public static function defaultLogos(): array
    {
        return array_map(fn ($logo) => [
            'filename' => $logo['filename'],
            'label'    => $logo['label'],
            'url'      => asset('images/brand/' . $logo['filename']),
        ], self::DEFAULT_LOGOS);
    }

    /**
     * URL pública do logo — sempre retorna string válida, nunca vazia.
     * Prioridade: custom → default escolhido → fallback do sistema.
     */
    public static function url(Clinic $clinic): string
    {
        if ($clinic->logo_type === 'custom'
            && $clinic->logo_path
            && Storage::disk('public')->exists($clinic->logo_path)
        ) {
            // asset('storage/...') equivale a Storage::disk('public')->url()
            // mas sem depender de método não tipado do contrato Filesystem
            $v = $clinic->updated_at?->timestamp ?? time();
            return asset('storage/' . $clinic->logo_path) . '?v=' . $v;
        }

        return asset('images/brand/' . self::resolveDefaultFile($clinic));
    }

    /**
     * Data URI base64 para PDFs (DomPDF não carrega URLs remotas com confiança).
     */
    public static function dataUri(Clinic $clinic): string
    {
        if ($clinic->logo_type === 'custom'
            && $clinic->logo_path
            && Storage::disk('public')->exists($clinic->logo_path)
        ) {
            $contents  = Storage::disk('public')->get($clinic->logo_path);
            $localPath = storage_path('app/public/' . $clinic->logo_path);
            $mime      = file_exists($localPath) ? (mime_content_type($localPath) ?: 'image/png') : 'image/png';
            return 'data:' . $mime . ';base64,' . base64_encode($contents);
        }

        $file = self::resolveDefaultFile($clinic);
        $path = public_path('images/brand/' . $file);

        if (file_exists($path)) {
            return 'data:image/png;base64,' . base64_encode(file_get_contents($path));
        }

        return '';
    }

    private static function resolveDefaultFile(Clinic $clinic): string
    {
        $validFiles = array_column(self::DEFAULT_LOGOS, 'filename');

        if ($clinic->default_logo && in_array($clinic->default_logo, $validFiles)) {
            return $clinic->default_logo;
        }

        return self::FALLBACK_FILE;
    }
}
