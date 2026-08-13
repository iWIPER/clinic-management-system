output "task_definition_arn" {
  value = aws_ecs_task_definition.migrate.arn
}

output "task_definition_family" {
  value = aws_ecs_task_definition.migrate.family
}

output "log_group_name" {
  value = aws_cloudwatch_log_group.migrate.name
}
