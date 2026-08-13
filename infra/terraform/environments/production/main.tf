module "network" {
  source = "../../modules/network"

  project     = var.project
  environment = var.environment
}

module "security_groups" {
  source = "../../modules/security_groups"

  project     = var.project
  environment = var.environment
  vpc_id      = module.network.vpc_id
  vpc_cidr    = module.network.vpc_cidr
}

module "ecr" {
  source = "../../modules/ecr"

  project     = var.project
  environment = var.environment
}

resource "random_password" "db" {
  length  = 32
  special = false
}

module "rds" {
  source = "../../modules/rds"

  project             = var.project
  environment         = var.environment
  private_subnet_ids  = module.network.private_subnet_ids
  rds_sg_id           = module.security_groups.rds_sg_id
  master_password     = random_password.db.result
  instance_class      = var.rds_instance_class
  multi_az            = var.rds_multi_az
  deletion_protection = var.rds_deletion_protection
}

module "s3" {
  source = "../../modules/s3"

  project              = var.project
  environment          = var.environment
  cors_allowed_origins = ["https://${var.www_domain}"]
}

module "secrets" {
  source = "../../modules/secrets"

  project     = var.project
  environment = var.environment
  db_host     = module.rds.address
  db_port     = module.rds.port
  db_name     = module.rds.db_name
  db_username = module.rds.username
  db_password = random_password.db.result
}

module "ses" {
  source = "../../modules/ses"

  project     = var.project
  environment = var.environment
  domain_name = var.apex_domain
}

module "iam" {
  source = "../../modules/iam"

  project          = var.project
  environment      = var.environment
  domain_name      = var.apex_domain
  s3_bucket_arn    = module.s3.bucket_arn
  ses_identity_arn = module.ses.identity_arn
  secret_arns = [
    module.secrets.app_secret_arn,
    module.secrets.database_secret_arn,
    module.secrets.stripe_secret_arn,
    module.secrets.google_secret_arn,
  ]
}

module "route53" {
  source = "../../modules/route53"

  project                = var.project
  environment            = var.environment
  domain_name            = var.apex_domain
  ses_verification_token = module.ses.verification_token
  ses_dkim_tokens        = module.ses.dkim_tokens
}

module "acm" {
  source = "../../modules/acm"

  project                   = var.project
  environment               = var.environment
  domain_name               = var.www_domain
  subject_alternative_names = [var.apex_domain]
  zone_id                   = module.route53.zone_id
}

module "alb" {
  source = "../../modules/alb"

  project           = var.project
  environment       = var.environment
  vpc_id            = module.network.vpc_id
  public_subnet_ids = module.network.public_subnet_ids
  alb_sg_id         = module.security_groups.alb_sg_id
  certificate_arn   = module.acm.certificate_arn
  apex_domain       = var.apex_domain
  www_domain        = var.www_domain
}

# Registros de DNS que dependem do ALB ja existir - por isso ficam aqui,
# fora do modulo route53 (que so cria a zone + registros independentes de ALB).
resource "aws_route53_record" "apex_alias" {
  zone_id = module.route53.zone_id
  name    = var.apex_domain
  type    = "A"

  alias {
    name                   = module.alb.dns_name
    zone_id                = module.alb.zone_id
    evaluate_target_health = true
  }
}

resource "aws_route53_record" "www_alias" {
  zone_id = module.route53.zone_id
  name    = var.www_domain
  type    = "A"

  alias {
    name                   = module.alb.dns_name
    zone_id                = module.alb.zone_id
    evaluate_target_health = true
  }
}

module "ecs_cluster" {
  source = "../../modules/ecs_cluster"

  project     = var.project
  environment = var.environment
}

locals {
  app_environment_variables = {
    APP_NAME                    = "WiLDental"
    APP_ENV                     = "production"
    APP_DEBUG                   = "false"
    APP_URL                     = "https://${var.www_domain}"
    APP_TIMEZONE                = "America/Sao_Paulo"
    APP_LOCALE                  = "pt_BR"
    LOG_CHANNEL                 = "stack"
    LOG_STACK                   = "stderr"
    LOG_LEVEL                   = "warning"
    SESSION_DRIVER              = "database"
    SESSION_SECURE_COOKIE       = "true"
    CACHE_STORE                 = "database"
    QUEUE_CONNECTION            = "database"
    BROADCAST_CONNECTION        = "log"
    FILESYSTEM_DISK             = "s3"
    AWS_DEFAULT_REGION          = var.aws_region
    AWS_BUCKET                  = module.s3.bucket_name
    AWS_USE_PATH_STYLE_ENDPOINT = "false"
    MAIL_MAILER                 = "ses"
    MAIL_FROM_ADDRESS           = var.mail_from_address
    MAIL_FROM_NAME              = var.mail_from_name
    # config/services.php le isso via env() sem default - sem essa var o
    # OAuth do Google Drive quebra (redirect fica null).
    GOOGLE_DRIVE_REDIRECT_URI = "https://${var.www_domain}/auth/google/callback"
  }

  app_secrets = [
    { name = "APP_KEY", valueFrom = "${module.secrets.app_secret_arn}:APP_KEY::" },
    { name = "DB_HOST", valueFrom = "${module.secrets.database_secret_arn}:DB_HOST::" },
    { name = "DB_PORT", valueFrom = "${module.secrets.database_secret_arn}:DB_PORT::" },
    { name = "DB_DATABASE", valueFrom = "${module.secrets.database_secret_arn}:DB_DATABASE::" },
    { name = "DB_USERNAME", valueFrom = "${module.secrets.database_secret_arn}:DB_USERNAME::" },
    { name = "DB_PASSWORD", valueFrom = "${module.secrets.database_secret_arn}:DB_PASSWORD::" },
    { name = "STRIPE_KEY", valueFrom = "${module.secrets.stripe_secret_arn}:STRIPE_KEY::" },
    { name = "STRIPE_SECRET", valueFrom = "${module.secrets.stripe_secret_arn}:STRIPE_SECRET::" },
    { name = "STRIPE_WEBHOOK_SECRET", valueFrom = "${module.secrets.stripe_secret_arn}:STRIPE_WEBHOOK_SECRET::" },
    { name = "GOOGLE_DRIVE_CLIENT_ID", valueFrom = "${module.secrets.google_secret_arn}:GOOGLE_DRIVE_CLIENT_ID::" },
    { name = "GOOGLE_DRIVE_CLIENT_SECRET", valueFrom = "${module.secrets.google_secret_arn}:GOOGLE_DRIVE_CLIENT_SECRET::" },
  ]
}

module "ecs_web" {
  source = "../../modules/ecs_web"

  project               = var.project
  environment           = var.environment
  aws_region            = var.aws_region
  cluster_id            = module.ecs_cluster.cluster_id
  cluster_name          = module.ecs_cluster.cluster_name
  execution_role_arn    = module.iam.execution_role_arn
  task_role_arn         = module.iam.task_role_arn
  image_repository_url  = module.ecr.repository_url
  image_tag             = var.image_tag
  cpu                   = var.web_cpu
  memory                = var.web_memory
  private_subnet_ids    = module.network.private_subnet_ids
  ecs_sg_id             = module.security_groups.ecs_sg_id
  target_group_arn      = module.alb.target_group_arn
  desired_count         = var.web_desired_count
  autoscaling_enabled   = var.web_autoscaling_enabled
  min_capacity          = var.web_min_capacity
  max_capacity          = var.web_max_capacity
  log_retention_days    = var.log_retention_days
  environment_variables = local.app_environment_variables
  secrets               = local.app_secrets
}

module "ecs_worker" {
  source = "../../modules/ecs_worker"

  project               = var.project
  environment           = var.environment
  aws_region            = var.aws_region
  cluster_id            = module.ecs_cluster.cluster_id
  cluster_name          = module.ecs_cluster.cluster_name
  execution_role_arn    = module.iam.execution_role_arn
  task_role_arn         = module.iam.task_role_arn
  image_repository_url  = module.ecr.repository_url
  image_tag             = var.image_tag
  cpu                   = var.worker_cpu
  memory                = var.worker_memory
  private_subnet_ids    = module.network.private_subnet_ids
  ecs_sg_id             = module.security_groups.ecs_sg_id
  desired_count         = var.worker_desired_count
  use_fargate_spot      = var.worker_use_fargate_spot
  log_retention_days    = var.log_retention_days
  environment_variables = local.app_environment_variables
  secrets               = local.app_secrets
}

module "ecs_scheduler" {
  source = "../../modules/ecs_scheduler"

  project               = var.project
  environment           = var.environment
  aws_region            = var.aws_region
  cluster_arn           = module.ecs_cluster.cluster_arn
  execution_role_arn    = module.iam.execution_role_arn
  task_role_arn         = module.iam.task_role_arn
  image_repository_url  = module.ecr.repository_url
  image_tag             = var.image_tag
  private_subnet_ids    = module.network.private_subnet_ids
  ecs_sg_id             = module.security_groups.ecs_sg_id
  log_retention_days    = var.log_retention_days
  environment_variables = local.app_environment_variables
  secrets               = local.app_secrets
}

module "ecs_migrate" {
  source = "../../modules/ecs_migrate"

  project               = var.project
  environment           = var.environment
  aws_region            = var.aws_region
  execution_role_arn    = module.iam.execution_role_arn
  task_role_arn         = module.iam.task_role_arn
  image_repository_url  = module.ecr.repository_url
  image_tag             = var.image_tag
  log_retention_days    = var.log_retention_days
  environment_variables = local.app_environment_variables
  secrets               = local.app_secrets
}

# Alerta simples de custo - so notifica por e-mail, nao bloqueia gasto nem
# cria infraestrutura de billing adicional.
resource "aws_budgets_budget" "monthly_cost" {
  name         = "${var.project}-${var.environment}-monthly"
  budget_type  = "COST"
  limit_amount = tostring(var.budget_monthly_limit_usd)
  limit_unit   = "USD"
  time_unit    = "MONTHLY"

  notification {
    comparison_operator        = "GREATER_THAN"
    threshold                  = 80
    threshold_type             = "PERCENTAGE"
    notification_type          = "ACTUAL"
    subscriber_email_addresses = [var.budget_alert_email]
  }
}

module "cloudwatch" {
  source = "../../modules/cloudwatch"

  project                 = var.project
  environment             = var.environment
  alb_arn_suffix          = module.alb.arn_suffix
  target_group_arn_suffix = module.alb.target_group_arn_suffix
  rds_identifier          = module.rds.identifier
}
