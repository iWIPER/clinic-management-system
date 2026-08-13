output "service_name" {
  value = aws_ecs_service.web.name
}

output "task_definition_family" {
  value = aws_ecs_task_definition.web.family
}

output "log_group_name" {
  value = aws_cloudwatch_log_group.web.name
}
