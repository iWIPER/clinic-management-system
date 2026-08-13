variable "project" {
  type = string
}

variable "environment" {
  type = string
}

variable "aws_region" {
  type = string
}

variable "execution_role_arn" {
  type = string
}

variable "task_role_arn" {
  type = string
}

variable "image_repository_url" {
  type = string
}

variable "image_tag" {
  type    = string
  default = "initial"
}

variable "cpu" {
  type    = number
  default = 512
}

variable "memory" {
  type    = number
  default = 1024
}

variable "log_retention_days" {
  type    = number
  default = 14
}

variable "environment_variables" {
  type = map(string)
}

variable "secrets" {
  type = list(object({
    name      = string
    valueFrom = string
  }))
}
