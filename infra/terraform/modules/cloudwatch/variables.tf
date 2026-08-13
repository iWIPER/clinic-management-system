variable "project" {
  type = string
}

variable "environment" {
  type = string
}

variable "alb_arn_suffix" {
  type = string
}

variable "target_group_arn_suffix" {
  type = string
}

variable "rds_identifier" {
  type = string
}

variable "rds_free_storage_threshold_bytes" {
  type    = number
  default = 2147483648 # 2 GiB
}
