# Infraestrutura de Produção — Wildental

Infraestrutura AWS (Terraform + ECS Fargate) para colocar `https://www.wildental.com.br` no ar. Este documento é o guia operacional — para o raciocínio por trás de cada decisão, ver o histórico do projeto.

## Arquitetura

```
Internet
   │
   ▼
Route 53 (wildental.com.br)  ──►  ACM (TLS)
   │
   ▼
ALB (subnets públicas, 2 AZs)
   │  wildental.com.br  → 301 → www.wildental.com.br
   │  www.wildental.com.br → target group
   ▼
ECS Fargate (subnets privadas, 2 AZs)
   ├── service "web"        (Fargate padrão, autoscaling CPU, 1–2 tasks)
   ├── service "worker"     (Fargate Spot, queue:work, 1 task)
   ├── EventBridge Scheduler → RunTask "scheduler" (schedule:run, a cada minuto)
   └── task one-off "migrate" (php artisan migrate --force, disparado manualmente)
   │
   ├──► RDS PostgreSQL 16 (subnet privada, sem IP público)
   ├──► S3 (storage privado: PDFs, assinaturas, avatares, logos)
   ├──► Secrets Manager (APP_KEY, DB, Stripe, Google Drive)
   ├──► SES (e-mail transacional, domínio verificado)
   └──► CloudWatch Logs + alarmes (SNS)

Google Drive / Stripe / SES ──► via NAT Gateway (egress das subnets privadas)
```

**Por que essas escolhas** (resumo — auditoria completa do código real precedeu o desenho):

- **Postgres 16 privado**: confirmado em `docker-compose.yml` e `config/database.php`, não MySQL.
- **Worker separado**: existem 3 Jobs reais (`UploadEvolutionPhotoJob`, `ProcessFinancialWebhookJob`, `SubmitFinancingProposalJob`) usando `QUEUE_CONNECTION=database`.
- **Scheduler via EventBridge, não container 24/7**: só 3 tarefas/dia (`routes/console.php`) — mais barato disparar uma task curta por minuto do que manter um container ocioso o tempo todo.
- **S3 para storage local**: `Storage::disk('public')` grava PDFs/assinaturas/avatares/logo — isso quebra em Fargate (disco efêmero). Migrado via `FILESYSTEM_DISK=s3` + `league/flysystem-aws-s3-v3` (adicionado ao `composer.json`).
- **Google Drive não precisa de disco**: é o storage principal de arquivos do paciente, mas via API (tokens no banco) — só precisa de egress de internet (NAT).
- **Migration como task one-off**: nunca roda no boot dos containers web (evita corrida entre múltiplas tasks migrando ao mesmo tempo em um deploy com autoscaling).
- **`www` como domínio canônico**, apex redireciona 301 — decisão registrada no histórico do projeto.
- **1 NAT Gateway (não 2)**: maior custo fixo da conta depois do ALB; trade-off de custo vs. HA documentado, fácil de dobrar depois.

## Árvore de arquivos

```
gestao-clinicas/
├── Dockerfile                  # build multi-stage: node (assets) → composer (vendor) → php-fpm+nginx
├── .dockerignore
├── docker/
│   ├── entrypoint.sh            # dispatch web|worker|scheduler|migrate
│   ├── nginx.conf
│   ├── php-fpm.conf
│   ├── php.ini
│   └── supervisord.conf
└── infra/
    ├── README.md                # este arquivo
    ├── scripts/
    │   ├── deploy.sh             # build+push+migrate+update services
    │   └── rollback.sh           # volta web/worker pra revisao anterior
    └── terraform/
        ├── bootstrap/            # cria o bucket S3 de state (1x)
        ├── modules/               # 17 modulos reutilizaveis
        └── environments/
            ├── production/
            └── staging/          # placeholder, nao provisionado ainda
```

## Passo a passo do primeiro deploy

### 0. Pré-requisitos

- AWS CLI v2 configurado (`aws configure`) com uma conta/role com permissão para criar os recursos abaixo.
- Terraform >= 1.10.
- Docker, `jq`.
- Acesso ao painel do Registro.br para o domínio `wildental.com.br` (para o passo de nameservers).

### 1. Bootstrap do state remoto (uma vez só)

```bash
cd infra/terraform/bootstrap
terraform init
terraform apply
```

Isso cria o bucket `wildental-terraform-state` (S3, versionado, criptografado, privado).

### 2. Primeiro apply — cria a hosted zone (e só ela, se preferir ir com calma)

```bash
cd infra/terraform/environments/production
terraform init
terraform plan
terraform apply
```

**Atenção**: o `aws_acm_certificate_validation` desse apply fica **esperando a validação DNS**, que só acontece depois que os nameservers do domínio apontarem para o Route 53 (passo 3). Se preferir não deixar o terminal preso, rode primeiro só a zone:

```bash
terraform apply -target=module.route53
terraform output route53_name_servers
```

### 3. Apontar os nameservers no Registro.br (manual, fora do Terraform)

No painel do Registro.br, troque os nameservers de `wildental.com.br` para os 4 valores retornados por `terraform output route53_name_servers`. A propagação pode levar de minutos a algumas horas.

**Nada quebra durante a espera**: o domínio hoje não tem A/CNAME configurado (nenhum tráfego em produção usando esse DNS), então não há corte de serviço.

### 4. Terminar o apply

Depois que os nameservers propagarem (dá pra checar com `dig NS wildental.com.br`), rode `terraform apply` de novo (ou continue o que ficou em background) — o certificado ACM valida e o resto da infra (ALB, ECS, RDS etc.) sobe.

### 5. Preencher os secrets reais (Stripe e Google Drive)

O Terraform cria os secrets com valor `CHANGE_ME` de propósito — preencha com os valores reais:

```bash
aws secretsmanager put-secret-value \
  --secret-id "$(cd infra/terraform/environments/production && terraform output -raw stripe_secret_arn)" \
  --secret-string '{"STRIPE_KEY":"pk_live_...","STRIPE_SECRET":"sk_live_...","STRIPE_WEBHOOK_SECRET":"whsec_..."}'

aws secretsmanager put-secret-value \
  --secret-id "$(cd infra/terraform/environments/production && terraform output -raw google_secret_arn)" \
  --secret-string '{"GOOGLE_DRIVE_CLIENT_ID":"...","GOOGLE_DRIVE_CLIENT_SECRET":"..."}'
```

O Terraform tem `ignore_changes` nesses secrets — rodar `terraform apply` de novo não vai sobrescrever os valores reais de volta pra `CHANGE_ME`.

### 5.1. Registrar o redirect URI no Google Cloud Console (manual)

`config/services.php` lê `GOOGLE_DRIVE_REDIRECT_URI` sem valor padrão — o Terraform já injeta `https://www.wildental.com.br/auth/google/callback` como variável de ambiente, mas esse endereço também precisa estar cadastrado em **Google Cloud Console → APIs & Services → Credentials → seu OAuth Client → Authorized redirect URIs**, senão o Google recusa o OAuth com `redirect_uri_mismatch`.

### 6. Sair do sandbox do SES (manual, console AWS)

Depois que o domínio verificar no SES (`aws sesv2 get-email-identity --email-identity wildental.com.br` deve mostrar `VerifiedForSendingStatus: true`), solicite produção em **SES → Account dashboard → Request production access** no console. Sem isso, só é possível enviar para endereços verificados manualmente.

### 7. Primeiro deploy real da aplicação

```bash
infra/scripts/deploy.sh
```

Isso builda a imagem, faz push pro ECR, roda a migration (espera o resultado) e só então atualiza os services `web`/`worker` e o alvo do scheduler.

### 8. Verificação

```bash
# health check via DNS do ALB, sem depender do dominio
curl -I http://$(cd infra/terraform/environments/production && terraform output -raw alb_dns_name)/up

# depois que o DNS propagar:
curl -I https://www.wildental.com.br/up
```

## Deploys seguintes

```bash
infra/scripts/deploy.sh
```

Terraform **não** é reaplicado a cada deploy de código — ele define a forma da infra (CPU, memória, variáveis, secrets), e o `deploy.sh` cuida do ciclo de vida da imagem/release (os task definitions têm `lifecycle { ignore_changes = [container_definitions] }` justamente para isso). Rode `terraform apply` de novo só quando mudar algo estrutural (tamanho de instância, nova variável de ambiente, etc.).

## Rollback

```bash
infra/scripts/rollback.sh          # web + worker
infra/scripts/rollback.sh web      # so o service web
```

Volta o service pra revisão anterior do task definition e espera estabilizar. **Não desfaz migrations** — se o problema foi uma migration quebrada, trate o schema manualmente antes (não há rollback automático de banco).

## Migrations

- Nunca rodam no boot dos containers web (evita concorrência entre múltiplas tasks).
- Rodam como ECS task one-off (`wildental-production-migrate`), disparada pelo `deploy.sh`, que espera o exit code antes de atualizar qualquer service.
- Pra rodar uma migration avulsa sem fazer deploy completo:

```bash
aws ecs run-task \
  --cluster "$(cd infra/terraform/environments/production && terraform output -raw ecs_cluster_name)" \
  --task-definition wildental-production-migrate \
  --launch-type FARGATE \
  --network-configuration "$(aws ecs describe-services --cluster <cluster> --services <web-service> --query 'services[0].networkConfiguration' --output json)"
```

## Logs e monitoramento

- CloudWatch Logs: `/ecs/wildental-production/{web,worker,scheduler,migrate}` (retenção configurável, padrão 14 dias).
- Alarmes (tópico SNS `wildental-production-alerts`): 5xx do ALB, hosts unhealthy, storage baixo no RDS. **Assine seu e-mail**:

```bash
aws sns subscribe \
  --topic-arn "$(cd infra/terraform/environments/production && terraform output -raw cloudwatch_alerts_topic_arn)" \
  --protocol email --notification-endpoint seu-email@wildental.com.br
```

- Bounce/complaint do SES: tópico `wildental-production-ses-events` — mesma lógica de assinatura:

```bash
aws sns subscribe \
  --topic-arn "$(cd infra/terraform/environments/production && terraform output -raw ses_events_topic_arn)" \
  --protocol email --notification-endpoint seu-email@wildental.com.br
```

## Checklist de produção

- [ ] Nameservers do Registro.br apontando para o Route 53
- [ ] Certificado ACM validado (`ISSUED`)
- [ ] Secrets Stripe e Google Drive preenchidos com valores reais (não `CHANGE_ME`)
- [ ] SES fora do sandbox (produção liberada)
- [ ] SNS de alarmes e de SES com e-mail assinado e confirmado
- [ ] `deploy.sh` executado com sucesso (task de migration com exit code 0)
- [ ] `https://www.wildental.com.br/up` respondendo 200
- [ ] `deletion_protection = true` no RDS (já é o padrão em `terraform.tfvars`)
- [ ] Backups automáticos do RDS ativos (7 dias, padrão já configurado)
- [ ] Testar login/upload de arquivo/checkout Stripe em produção antes de anunciar o piloto

## Estimativa de custo mensal (sa-east-1)

| Item | Aprox. |
|---|---|
| NAT Gateway | ~US$ 43 + dados |
| ALB | ~US$ 23 + LCU |
| Fargate web (0.5 vCPU/1GB, 24/7) | ~US$ 18 |
| Fargate worker (Spot, 0.25 vCPU/0.5GB) | ~US$ 4 |
| Fargate scheduler (RunTask/min) | ~US$ 2 |
| RDS db.t4g.micro + 20GB gp3 | ~US$ 16 |
| S3 + Secrets Manager + ECR + Route 53 + SES | ~US$ 5 |
| CloudWatch Logs | ~US$ 2–5 |
| **Total** | **~US$ 110–150/mês** |

Maior alavanca de custo: NAT + ALB (fixos). Não dá pra remover o NAT enquanto Stripe/Google Drive/SES exigirem egress real de internet.

## Escalar depois

- **HA de banco**: `rds_multi_az = true` em `terraform.tfvars`.
- **Mais capacidade web**: subir `web_max_capacity`.
- **Staging**: ver `infra/terraform/environments/staging/README.md` — módulos já são reutilizáveis, sem duplicar código.
- **NAT redundante**: hoje 1 único NAT Gateway; para HA, duplicar em `modules/network` (1 por AZ) quando o tráfego justificar.

## O que depende de você (fora do Terraform)

1. Apontar nameservers no Registro.br (passo 3 acima).
2. Preencher secrets do Stripe e Google Drive (passo 5).
3. Cadastrar o redirect URI no Google Cloud Console (passo 5.1).
4. Solicitar saída do sandbox do SES (passo 6).
5. Assinar os tópicos SNS de alarme (seção Logs e monitoramento).
6. Credenciais AWS configuradas localmente/CI para rodar `terraform apply` e `deploy.sh`.
