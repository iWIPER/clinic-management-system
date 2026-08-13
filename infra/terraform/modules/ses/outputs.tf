output "verification_token" {
  value = aws_ses_domain_identity.this.verification_token
}

output "identity_arn" {
  value = aws_ses_domain_identity.this.arn
}

output "dkim_tokens" {
  value = aws_ses_domain_dkim.this.dkim_tokens
}

output "events_topic_arn" {
  value = aws_sns_topic.ses_events.arn
}
