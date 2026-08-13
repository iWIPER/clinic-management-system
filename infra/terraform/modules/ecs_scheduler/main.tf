# Nao roda como Service 24/7 - o Laravel scheduler so tem 3 tarefas/dia
# (2 diarias + 1 a cada 15min), entao um EventBridge Scheduler dispara uma
# ECS RunTask curta (`schedule:run`) a cada minuto, que e o padrao recomendado
# pra cron em Fargate sem manter container ocioso o tempo todo.

resource "aws_cloudwatch_log_group" "scheduler" {
  name              = "/ecs/${var.project}-${var.environment}/scheduler"
  retention_in_days = var.log_retention_days
}

resource "aws_ecs_task_definition" "scheduler" {
  family                   = "${var.project}-${var.environment}-scheduler"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = var.cpu
  memory                   = var.memory
  execution_role_arn       = var.execution_role_arn
  task_role_arn            = var.task_role_arn

  container_definitions = jsonencode([
    {
      name      = "scheduler"
      image     = "${var.image_repository_url}:${var.image_tag}"
      essential = true
      command   = ["scheduler"]

      environment = [
        for k, v in var.environment_variables : { name = k, value = v }
      ]

      secrets = [
        for s in var.secrets : { name = s.name, valueFrom = s.valueFrom }
      ]

      logConfiguration = {
        logDriver = "awslogs"
        options = {
          "awslogs-group"         = aws_cloudwatch_log_group.scheduler.name
          "awslogs-region"        = var.aws_region
          "awslogs-stream-prefix" = "scheduler"
        }
      }
    }
  ])

  tags = {
    Name = "${var.project}-${var.environment}-scheduler-task"
  }

  lifecycle {
    ignore_changes = [container_definitions]
  }
}

data "aws_caller_identity" "current" {}

data "aws_iam_policy_document" "scheduler_assume" {
  statement {
    actions = ["sts:AssumeRole"]
    principals {
      type        = "Service"
      identifiers = ["scheduler.amazonaws.com"]
    }

    # Protecao contra confused deputy: so um Schedule desta mesma conta
    # pode assumir este role via o principal de servico scheduler.amazonaws.com.
    condition {
      test     = "StringEquals"
      variable = "aws:SourceAccount"
      values   = [data.aws_caller_identity.current.account_id]
    }
  }
}

resource "aws_iam_role" "scheduler" {
  name               = "${var.project}-${var.environment}-eventbridge-scheduler"
  assume_role_policy = data.aws_iam_policy_document.scheduler_assume.json
}

data "aws_iam_policy_document" "scheduler_run_task" {
  statement {
    sid       = "RunSchedulerTask"
    actions   = ["ecs:RunTask"]
    resources = ["${replace(aws_ecs_task_definition.scheduler.arn, "/:\\d+$/", "")}:*"]

    condition {
      test     = "ArnEquals"
      variable = "ecs:cluster"
      values   = [var.cluster_arn]
    }
  }

  statement {
    sid       = "PassTaskRoles"
    actions   = ["iam:PassRole"]
    resources = [var.execution_role_arn, var.task_role_arn]

    condition {
      test     = "StringLike"
      variable = "iam:PassedToService"
      values   = ["ecs-tasks.amazonaws.com"]
    }
  }
}

resource "aws_iam_role_policy" "scheduler_run_task" {
  name   = "${var.project}-${var.environment}-scheduler-run-task"
  role   = aws_iam_role.scheduler.id
  policy = data.aws_iam_policy_document.scheduler_run_task.json
}

resource "aws_scheduler_schedule" "run_every_minute" {
  name                         = "${var.project}-${var.environment}-schedule-run"
  schedule_expression          = "rate(1 minute)"
  schedule_expression_timezone = "America/Sao_Paulo"

  flexible_time_window {
    mode = "OFF"
  }

  target {
    arn      = var.cluster_arn
    role_arn = aws_iam_role.scheduler.arn

    ecs_parameters {
      task_definition_arn = aws_ecs_task_definition.scheduler.arn
      launch_type         = "FARGATE"
      platform_version    = "LATEST"

      network_configuration {
        subnets          = var.private_subnet_ids
        security_groups  = [var.ecs_sg_id]
        assign_public_ip = false
      }
    }

    retry_policy {
      maximum_retry_attempts = 0
    }
  }
}
