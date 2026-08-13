output "app_secret_arn" {
  value = aws_secretsmanager_secret.app.arn
}

output "database_secret_arn" {
  value = aws_secretsmanager_secret.database.arn
}

output "stripe_secret_arn" {
  value = aws_secretsmanager_secret.stripe.arn
}

output "google_secret_arn" {
  value = aws_secretsmanager_secret.google.arn
}
