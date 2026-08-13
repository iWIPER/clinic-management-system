variable "project" {
  type = string
}

variable "environment" {
  type = string
}

variable "cors_allowed_origins" {
  type    = list(string)
  default = ["https://www.wildental.com.br"]
}
