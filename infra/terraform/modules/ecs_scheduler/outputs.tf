output "task_definition_family" {
  value = aws_ecs_task_definition.scheduler.family
}

output "log_group_name" {
  value = aws_cloudwatch_log_group.scheduler.name
}
