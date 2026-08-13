# Bootstrap: cria o bucket S3 que guarda o state remoto do Terraform.
# Aplicado uma única vez, com state local (é o próprio bucket de state
# que ainda não existe). Depois disso, environments/production/backend.tf
# aponta para este bucket.
#
# Uso:
#   cd infra/terraform/bootstrap
#   terraform init
#   terraform apply

terraform {
  required_version = ">= 1.10.0"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }
}

variable "aws_region" {
  type    = string
  default = "sa-east-1"
}

variable "project" {
  type    = string
  default = "wildental"
}

provider "aws" {
  region  = var.aws_region
  profile = "wildental-terraform"
}

resource "aws_s3_bucket" "terraform_state" {
  bucket = "${var.project}-terraform-state"

  lifecycle {
    prevent_destroy = true
  }

  tags = {
    Project   = var.project
    ManagedBy = "terraform"
    Purpose   = "terraform-remote-state"
  }
}

resource "aws_s3_bucket_versioning" "terraform_state" {
  bucket = aws_s3_bucket.terraform_state.id

  versioning_configuration {
    status = "Enabled"
  }
}

resource "aws_s3_bucket_server_side_encryption_configuration" "terraform_state" {
  bucket = aws_s3_bucket.terraform_state.id

  rule {
    apply_server_side_encryption_by_default {
      sse_algorithm = "AES256"
    }
    bucket_key_enabled = true
  }
}

resource "aws_s3_bucket_public_access_block" "terraform_state" {
  bucket = aws_s3_bucket.terraform_state.id

  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

output "state_bucket_name" {
  value = aws_s3_bucket.terraform_state.bucket
}
