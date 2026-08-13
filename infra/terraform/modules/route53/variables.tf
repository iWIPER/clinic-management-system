variable "project" {
  type = string
}

variable "environment" {
  type = string
}

variable "domain_name" {
  type = string
}

variable "ses_verification_token" {
  type = string
}

variable "ses_dkim_tokens" {
  type = list(string)
}

variable "dmarc_report_email" {
  type    = string
  default = "dmarc@wildental.com.br"
}
