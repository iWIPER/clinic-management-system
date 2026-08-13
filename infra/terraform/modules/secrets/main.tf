# Gera e guarda os secrets da aplicacao. APP_KEY e a senha do banco sao
# gerados pelo Terraform. Stripe e Google Drive ficam com valor placeholder
# ("CHANGE_ME") e devem ser preenchidos manualmente apos o apply via:
#   aws secretsmanager put-secret-value --secret-id <arn> --secret-string '{...}'
# (ver infra/README.md) - nunca commitados em tfvars ou no Dockerfile.

resource "random_id" "app_key" {
  byte_length = 32
}

resource "aws_secretsmanager_secret" "app" {
  name                    = "${var.project}/${var.environment}/app"
  recovery_window_in_days = 7

  tags = {
    Name = "${var.project}-${var.environment}-app-secret"
  }
}

resource "aws_secretsmanager_secret_version" "app" {
  secret_id = aws_secretsmanager_secret.app.id
  secret_string = jsonencode({
    APP_KEY = "base64:${random_id.app_key.b64_std}"
  })
}

resource "aws_secretsmanager_secret" "database" {
  name                    = "${var.project}/${var.environment}/database"
  recovery_window_in_days = 7

  tags = {
    Name = "${var.project}-${var.environment}-database-secret"
  }
}

resource "aws_secretsmanager_secret_version" "database" {
  secret_id = aws_secretsmanager_secret.database.id
  secret_string = jsonencode({
    DB_CONNECTION = "pgsql"
    DB_HOST       = var.db_host
    DB_PORT       = tostring(var.db_port)
    DB_DATABASE   = var.db_name
    DB_USERNAME   = var.db_username
    DB_PASSWORD   = var.db_password
  })
}

resource "aws_secretsmanager_secret" "stripe" {
  name                    = "${var.project}/${var.environment}/stripe"
  recovery_window_in_days = 7

  tags = {
    Name = "${var.project}-${var.environment}-stripe-secret"
  }
}

resource "aws_secretsmanager_secret_version" "stripe" {
  secret_id = aws_secretsmanager_secret.stripe.id
  secret_string = jsonencode({
    STRIPE_KEY            = "CHANGE_ME"
    STRIPE_SECRET         = "CHANGE_ME"
    STRIPE_WEBHOOK_SECRET = "CHANGE_ME"
  })

  lifecycle {
    ignore_changes = [secret_string]
  }
}

resource "aws_secretsmanager_secret" "google" {
  name                    = "${var.project}/${var.environment}/google"
  recovery_window_in_days = 7

  tags = {
    Name = "${var.project}-${var.environment}-google-secret"
  }
}

resource "aws_secretsmanager_secret_version" "google" {
  secret_id = aws_secretsmanager_secret.google.id
  secret_string = jsonencode({
    GOOGLE_DRIVE_CLIENT_ID     = "CHANGE_ME"
    GOOGLE_DRIVE_CLIENT_SECRET = "CHANGE_ME"
  })

  lifecycle {
    ignore_changes = [secret_string]
  }
}
