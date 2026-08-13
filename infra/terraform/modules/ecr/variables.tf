variable "project" {
  type = string
}

variable "environment" {
  type = string
}

variable "keep_last_n_images" {
  type    = number
  default = 15
}
