<#
.SYNOPSIS
Busca uma sessao temporaria (via MFA) da WiLDentalTerraformDeployRole e grava
como credenciais estaticas de curta duracao no profile local "wildental-terraform".

.DESCRIPTION
O provider AWS do Terraform (hashicorp/aws-sdk-go-base) nao sabe pedir codigo
MFA interativamente - so a AWS CLI sabe fazer isso (via --token-code). Por isso
o assume-role com MFA precisa acontecer AQUI, uma vez, e o Terraform so recebe
credenciais de sessao ja prontas (AccessKeyId/SecretAccessKey/SessionToken),
validas por ate 1h (limite da role, MaxSessionDuration=3600).

Nenhum desses valores e impresso no terminal nem gravado em nenhum arquivo do
repositorio - so em ~/.aws/credentials, local.

.PARAMETER MfaCode
Codigo de 6 digitos gerado pelo seu app autenticador (Google Authenticator,
Authy, 1Password etc.) no momento da execucao.

.PARAMETER DurationSeconds
Duracao da sessao temporaria em segundos. Default 3600 (1h), que e o
MaxSessionDuration configurado na role - nao adianta pedir mais que isso.

.EXAMPLE
.\infra\scripts\aws-terraform-session.ps1 -MfaCode 123456
#>

[CmdletBinding()]
param(
    [Parameter(Mandatory = $true, Position = 0)]
    [string]$MfaCode,

    [Parameter(Mandatory = $false)]
    [ValidateRange(900, 3600)]
    [int]$DurationSeconds = 3600
)

$ErrorActionPreference = "Stop"

# Validado manualmente (em vez de via atributo) para compatibilidade com
# Windows PowerShell 5.1, que nao suporta ValidatePattern(ErrorMessage=...).
if ($MfaCode -notmatch '^\d{6}$') {
    Write-Host "!!  O codigo MFA deve ter exatamente 6 digitos numericos (recebido: '$MfaCode')." -ForegroundColor Red
    exit 1
}

$RoleArn           = "arn:aws:iam::429601541646:role/WiLDentalTerraformDeployRole"
$MfaSerial         = "arn:aws:iam::429601541646:mfa/WiLDentalTerraformBootstrap-mfa"
$BootstrapProfile  = "wildental-bootstrap"
$TargetProfile     = "wildental-terraform"
$Region            = "sa-east-1"
$SessionNamePrefix = "wildental-terraform"

function Write-Step($msg) { Write-Host "==> $msg" -ForegroundColor Cyan }
function Write-Ok($msg) { Write-Host "    $msg" -ForegroundColor Green }
function Write-Err($msg) { Write-Host "!!  $msg" -ForegroundColor Red }

# --- checagens antes de chamar a AWS ---

$awsCmd = Get-Command aws -ErrorAction SilentlyContinue
if (-not $awsCmd) {
    Write-Err "AWS CLI nao encontrada no PATH. Instale o AWS CLI v2 nativo do Windows (nao precisa de WSL)."
    exit 1
}

try {
    aws configure get aws_access_key_id --profile $BootstrapProfile *> $null
    if ($LASTEXITCODE -ne 0) {
        Write-Err "Profile '$BootstrapProfile' nao encontrado ou sem access key configurada em ~/.aws/credentials."
        exit 1
    }
} catch {
    Write-Err "Nao foi possivel ler o profile '$BootstrapProfile': $($_.Exception.Message)"
    exit 1
}

$sessionName = "$SessionNamePrefix-$(Get-Date -Format 'yyyyMMddHHmmss')"

Write-Step "Assumindo $RoleArn com MFA (sessao de $DurationSeconds s)..."

# Usa Start-Process com stdout/stderr redirecionados para arquivos temporarios.
# Evita a combinacao "2>&1 + `$ErrorActionPreference=Stop`" do PowerShell, que
# em comandos nativos pode mascarar a mensagem de erro real - forma mais
# confiavel de capturar as duas saidas sem perder informacao nem vazar nada
# fora do controle do script.
$stdoutFile = New-TemporaryFile
$stderrFile = New-TemporaryFile

try {
    $argList = @(
        "sts", "assume-role",
        "--profile", $BootstrapProfile,
        "--role-arn", $RoleArn,
        "--role-session-name", $sessionName,
        "--serial-number", $MfaSerial,
        "--token-code", $MfaCode,
        "--duration-seconds", $DurationSeconds,
        "--output", "json"
    )
    $proc = Start-Process -FilePath "aws" -ArgumentList $argList -NoNewWindow -Wait -PassThru `
        -RedirectStandardOutput $stdoutFile.FullName -RedirectStandardError $stderrFile.FullName

    $rawJson = Get-Content $stdoutFile.FullName -Raw
    $errorText = Get-Content $stderrFile.FullName -Raw

    if ($proc.ExitCode -ne 0 -or [string]::IsNullOrWhiteSpace($rawJson)) {
        if ($errorText -match "MultiFactorAuthentication failed|AccessDenied.*[Mm]ulti[Ff]actor|InvalidToken") {
            Write-Err "Codigo MFA invalido, expirado ou ja usado. Gere um NOVO codigo no seu app autenticador (aguarde o proximo) e tente de novo."
        } elseif ($errorText -match "ExpiredToken|InvalidClientTokenId|SignatureDoesNotMatch|UnrecognizedClientException") {
            Write-Err "As credenciais do profile '$BootstrapProfile' parecem invalidas ou expiradas. Confira ~/.aws/credentials."
        } elseif ($errorText -match "AccessDenied") {
            Write-Err "Acesso negado ao assumir a role. Confira se o usuario do profile '$BootstrapProfile' ainda tem a policy de AssumeRole e se o MFA foi ativado corretamente pra esse usuario."
        } elseif ([string]::IsNullOrWhiteSpace($errorText)) {
            Write-Err "aws sts assume-role falhou (codigo de saida $($proc.ExitCode)), sem mensagem de erro capturada."
        } else {
            Write-Err "Falha ao assumir a role:`n$errorText"
        }
        exit 1
    }
} finally {
    Remove-Item $stdoutFile.FullName, $stderrFile.FullName -ErrorAction SilentlyContinue
}

# --- parse do JSON (sem nunca imprimir o conteudo bruto) ---

try {
    $creds = $rawJson | ConvertFrom-Json
} catch {
    Write-Err "Nao foi possivel interpretar a resposta da AWS (formato inesperado)."
    exit 1
}

$accessKeyId     = $creds.Credentials.AccessKeyId
$secretAccessKey = $creds.Credentials.SecretAccessKey
$sessionToken    = $creds.Credentials.SessionToken
$expiration      = $creds.Credentials.Expiration

if (-not $accessKeyId -or -not $secretAccessKey -or -not $sessionToken) {
    Write-Err "Resposta da AWS nao trouxe credenciais completas. Nada foi gravado."
    exit 1
}

# --- grava as credenciais temporarias no profile local (nunca no terminal) ---

Write-Step "Gravando sessao temporaria no profile [$TargetProfile]..."

aws configure set aws_access_key_id $accessKeyId --profile $TargetProfile
aws configure set aws_secret_access_key $secretAccessKey --profile $TargetProfile
aws configure set aws_session_token $sessionToken --profile $TargetProfile
aws configure set region $Region --profile $TargetProfile

# Limpa as variaveis sensiveis da sessao do PowerShell assim que possivel.
Remove-Variable secretAccessKey, sessionToken, rawJson, creds -ErrorAction SilentlyContinue
[System.GC]::Collect()

Write-Ok "Sessao gravada. Expira em: $expiration"
Write-Ok "Access Key ID (nao e segredo): $accessKeyId"

# --- validacao automatica: confirma que a identidade assumida e a esperada ---

Write-Step "Validando identidade com o profile [$TargetProfile]..."
$valOutFile = New-TemporaryFile
$valErrFile = New-TemporaryFile
try {
    $valProc = Start-Process -FilePath "aws" -ArgumentList @("sts", "get-caller-identity", "--profile", $TargetProfile, "--output", "json") `
        -NoNewWindow -Wait -PassThru -RedirectStandardOutput $valOutFile.FullName -RedirectStandardError $valErrFile.FullName
    $identity = Get-Content $valOutFile.FullName -Raw
    $identityErr = Get-Content $valErrFile.FullName -Raw
    if ($valProc.ExitCode -ne 0 -or [string]::IsNullOrWhiteSpace($identity)) {
        Write-Err "A sessao foi gravada mas a validacao falhou: $identityErr"
        exit 1
    }
} finally {
    Remove-Item $valOutFile.FullName, $valErrFile.FullName -ErrorAction SilentlyContinue
}
$identityObj = $identity | ConvertFrom-Json
if ($identityObj.Arn -notmatch "assumed-role/WiLDentalTerraformDeployRole/") {
    Write-Err "Identidade validada, mas NAO e a WiLDentalTerraformDeployRole esperada: $($identityObj.Arn)"
    exit 1
}

Write-Ok "Identidade confirmada: $($identityObj.Arn)"
Write-Host ""
Write-Host "Pronto. Use:" -ForegroundColor Cyan
Write-Host "  `$env:AWS_PROFILE = `"$TargetProfile`"; terraform plan"
