variable "project" {
  type    = string
  default = "wildental"
}

variable "environment" {
  type    = string
  default = "production"
}

variable "aws_region" {
  type    = string
  default = "sa-east-1"
}

variable "apex_domain" {
  type    = string
  default = "wildental.com.br"
}

variable "www_domain" {
  type    = string
  default = "www.wildental.com.br"
}

variable "mail_from_address" {
  type    = string
  default = "naoresponda@wildental.com.br"
}

variable "mail_from_name" {
  type    = string
  default = "WiLDental"
}

variable "image_tag" {
  description = "Tag da imagem no ECR a usar nos task definitions. Atualizada pelo infra/scripts/deploy.sh a cada deploy, nao pelo terraform apply do dia a dia."
  type        = string
  default     = "initial"
}

# --- RDS ---
variable "rds_instance_class" {
  type    = string
  default = "db.t4g.micro"
}

variable "rds_multi_az" {
  description = "Ativar quando o piloto virar produto com clientes reais e exigir HA de banco."
  type        = bool
  default     = false
}

variable "rds_deletion_protection" {
  type    = bool
  default = true
}

# --- ECS web ---
variable "web_cpu" {
  type    = number
  default = 512
}

variable "web_memory" {
  type    = number
  default = 1024
}

variable "web_desired_count" {
  type    = number
  default = 1
}

variable "web_autoscaling_enabled" {
  type    = bool
  default = true
}

variable "web_min_capacity" {
  type    = number
  default = 1
}

variable "web_max_capacity" {
  type    = number
  default = 2
}

# --- ECS worker ---
variable "worker_cpu" {
  type    = number
  default = 256
}

variable "worker_memory" {
  type    = number
  default = 512
}

variable "worker_desired_count" {
  type    = number
  default = 1
}

variable "worker_use_fargate_spot" {
  type    = bool
  default = true
}

# --- Observabilidade ---
variable "log_retention_days" {
  type    = number
  default = 14
}

# --- Custo ---
variable "budget_monthly_limit_usd" {
  description = "Limite mensal (USD) usado so para gerar alerta de custo, nao bloqueia gasto."
  type        = number
  default     = 150
}

variable "budget_alert_email" {
  description = "E-mail que recebe o alerta quando o gasto real passar de 80% do limite mensal."
  type        = string
  default     = "wilk.p.matias@gmail.com"
}
