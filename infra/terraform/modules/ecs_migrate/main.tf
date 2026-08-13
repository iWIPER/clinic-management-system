# Task definition one-off para `php artisan migrate --force`. Sem service -
# disparada manualmente pelo infra/scripts/deploy.sh via `aws ecs run-task`,
# nunca automaticamente no boot de cada container web.

resource "aws_cloudwatch_log_group" "migrate" {
  name              = "/ecs/${var.project}-${var.environment}/migrate"
  retention_in_days = var.log_retention_days
}

resource "aws_ecs_task_definition" "migrate" {
  family                   = "${var.project}-${var.environment}-migrate"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = var.cpu
  memory                   = var.memory
  execution_role_arn       = var.execution_role_arn
  task_role_arn            = var.task_role_arn

  container_definitions = jsonencode([
    {
      name      = "migrate"
      image     = "${var.image_repository_url}:${var.image_tag}"
      essential = true
      command   = ["migrate"]

      environment = [
        for k, v in var.environment_variables : { name = k, value = v }
      ]

      secrets = [
        for s in var.secrets : { name = s.name, valueFrom = s.valueFrom }
      ]

      logConfiguration = {
        logDriver = "awslogs"
        options = {
          "awslogs-group"         = aws_cloudwatch_log_group.migrate.name
          "awslogs-region"        = var.aws_region
          "awslogs-stream-prefix" = "migrate"
        }
      }
    }
  ])

  tags = {
    Name = "${var.project}-${var.environment}-migrate-task"
  }

  lifecycle {
    ignore_changes = [container_definitions]
  }
}
