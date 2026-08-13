output "dns_name" {
  value = aws_lb.this.dns_name
}

output "zone_id" {
  value = aws_lb.this.zone_id
}

output "arn" {
  value = aws_lb.this.arn
}

output "arn_suffix" {
  value = aws_lb.this.arn_suffix
}

output "target_group_arn" {
  value = aws_lb_target_group.web.arn
}

output "target_group_arn_suffix" {
  value = aws_lb_target_group.web.arn_suffix
}

output "https_listener_arn" {
  value = aws_lb_listener.https.arn
}
