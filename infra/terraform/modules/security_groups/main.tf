resource "aws_security_group" "alb" {
  name        = "${var.project}-${var.environment}-alb-sg"
  description = "ALB publico - entrada HTTP/HTTPS da internet"
  vpc_id      = var.vpc_id

  ingress {
    description = "HTTP"
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  ingress {
    description = "HTTPS"
    from_port   = 443
    to_port     = 443
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  # Restrito a porta do container: o ALB nunca precisa falar com mais nada
  # alem dos targets do target group (na 8080). Nao referencia a SG do ECS
  # diretamente (cidr_blocks) para evitar dependencia ciclica entre as duas
  # security groups dentro deste mesmo modulo.
  egress {
    description = "Para os targets ECS (container port)"
    from_port   = var.container_port
    to_port     = var.container_port
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Name = "${var.project}-${var.environment}-alb-sg"
  }
}

resource "aws_security_group" "ecs" {
  name        = "${var.project}-${var.environment}-ecs-sg"
  description = "Tasks ECS (web/worker/scheduler/migrate) - so recebe do ALB, egress restrito a HTTPS/DNS/Postgres"
  vpc_id      = var.vpc_id

  ingress {
    description     = "Trafego do ALB"
    from_port       = var.container_port
    to_port         = var.container_port
    protocol        = "tcp"
    security_groups = [aws_security_group.alb.id]
  }

  # Egress restrito ao que a aplicacao realmente usa: HTTPS (Stripe, Google
  # Drive, SES, ECR, Secrets Manager, CloudWatch Logs, S3 - tudo via API/443),
  # DNS, e Postgres so dentro da VPC (RDS). Nao referencia a SG do RDS
  # diretamente para evitar dependencia ciclica entre security groups deste
  # mesmo modulo - o CIDR da VPC ja restringe isso ao essencial sem esse risco.
  egress {
    description = "HTTPS (Stripe, Google Drive, SES, ECR, Secrets Manager, S3, CloudWatch)"
    from_port   = 443
    to_port     = 443
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  egress {
    description = "DNS (TCP)"
    from_port   = 53
    to_port     = 53
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  egress {
    description = "DNS (UDP)"
    from_port   = 53
    to_port     = 53
    protocol    = "udp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  egress {
    description = "Postgres (RDS, dentro da VPC)"
    from_port   = 5432
    to_port     = 5432
    protocol    = "tcp"
    cidr_blocks = [var.vpc_cidr]
  }

  tags = {
    Name = "${var.project}-${var.environment}-ecs-sg"
  }
}

resource "aws_security_group" "rds" {
  name        = "${var.project}-${var.environment}-rds-sg"
  description = "RDS Postgres - so aceita conexao das tasks ECS"
  vpc_id      = var.vpc_id

  ingress {
    description     = "Postgres das tasks ECS"
    from_port       = 5432
    to_port         = 5432
    protocol        = "tcp"
    security_groups = [aws_security_group.ecs.id]
  }

  # Sem egress de proposito: uma instancia RDS nunca inicia conexao de saida,
  # entao nao ha necessidade de nenhuma regra aqui (deny-all e o correto).

  tags = {
    Name = "${var.project}-${var.environment}-rds-sg"
  }
}
