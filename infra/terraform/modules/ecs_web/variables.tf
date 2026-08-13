variable "project" {
  type = string
}

variable "environment" {
  type = string
}

variable "aws_region" {
  type = string
}

variable "cluster_id" {
  type = string
}

variable "cluster_name" {
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

variable "container_port" {
  type    = number
  default = 8080
}

variable "private_subnet_ids" {
  type = list(string)
}

variable "ecs_sg_id" {
  type = string
}

variable "target_group_arn" {
  type = string
}

variable "desired_count" {
  type    = number
  default = 1
}

variable "autoscaling_enabled" {
  type    = bool
  default = true
}

variable "min_capacity" {
  type    = number
  default = 1
}

variable "max_capacity" {
  type    = number
  default = 2
}

variable "target_cpu_percent" {
  type    = number
  default = 60
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
