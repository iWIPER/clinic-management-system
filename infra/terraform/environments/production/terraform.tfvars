# Valores nao-sensiveis do ambiente de producao. Nada de senha, chave ou
# secret aqui - isso vive no Secrets Manager (ver modulo secrets).

project     = "wildental"
environment = "production"
aws_region  = "sa-east-1"

apex_domain = "wildental.com.br"
www_domain  = "www.wildental.com.br"

mail_from_address = "naoresponda@wildental.com.br"
mail_from_name    = "WiLDental"

# Atualizado pelo infra/scripts/deploy.sh a cada deploy - nao editar manualmente
# no dia a dia.
image_tag = "initial"

# --- Custo inicial (piloto, sem clientes reais ainda) ---
rds_instance_class      = "db.t4g.micro"
rds_multi_az            = false
rds_deletion_protection = true

web_cpu                 = 512
web_memory              = 1024
web_desired_count       = 1
web_autoscaling_enabled = true
web_min_capacity        = 1
web_max_capacity        = 2

worker_cpu              = 256
worker_memory           = 512
worker_desired_count    = 1
worker_use_fargate_spot = true

log_retention_days = 14

# --- Custo ---
budget_monthly_limit_usd = 150
budget_alert_email       = "wilk.p.matias@gmail.com"
