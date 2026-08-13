variable "project" {
  type = string
}

variable "environment" {
  type = string
}

variable "secret_arns" {
  type = list(string)
}

variable "s3_bucket_arn" {
  type = string
}

variable "domain_name" {
  type = string
}

variable "ses_identity_arn" {
  type = string
}
