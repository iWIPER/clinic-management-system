output "route53_name_servers" {
  description = "Aponte os nameservers do wildental.com.br no Registro.br para estes 4 valores."
  value       = module.route53.name_servers
}

output "alb_dns_name" {
  value = module.alb.dns_name
}

output "ecr_repository_url" {
  value = module.ecr.repository_url
}

output "ecs_cluster_name" {
  value = module.ecs_cluster.cluster_name
}

output "ecs_web_service_name" {
  value = module.ecs_web.service_name
}

output "ecs_worker_service_name" {
  value = module.ecs_worker.service_name
}

output "ecs_migrate_task_definition_arn" {
  value = module.ecs_migrate.task_definition_arn
}

output "rds_endpoint" {
  value = module.rds.address
}

output "s3_bucket_name" {
  value = module.s3.bucket_name
}

output "stripe_secret_arn" {
  value = module.secrets.stripe_secret_arn
}

output "google_secret_arn" {
  value = module.secrets.google_secret_arn
}

output "ses_events_topic_arn" {
  value = module.ses.events_topic_arn
}

output "cloudwatch_alerts_topic_arn" {
  value = module.cloudwatch.alerts_topic_arn
}
