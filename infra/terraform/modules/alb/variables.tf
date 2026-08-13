variable "project" {
  type = string
}

variable "environment" {
  type = string
}

variable "vpc_id" {
  type = string
}

variable "public_subnet_ids" {
  type = list(string)
}

variable "alb_sg_id" {
  type = string
}

variable "certificate_arn" {
  type = string
}

variable "container_port" {
  type    = number
  default = 8080
}

variable "apex_domain" {
  type = string
}

variable "www_domain" {
  type = string
}
