resource "aws_ses_domain_identity" "this" {
  domain = var.domain_name
}

resource "aws_ses_domain_dkim" "this" {
  domain = aws_ses_domain_identity.this.domain
}

resource "aws_sns_topic" "ses_events" {
  name = "${var.project}-${var.environment}-ses-events"
}

# Notificacoes de bounce/complaint direto na identidade verificada -
# nao exige nenhuma mudanca no codigo Laravel (nao depende de configuration
# set referenciado pelo app), so assinar o topico SNS com um e-mail depois.
resource "aws_ses_identity_notification_topic" "bounce" {
  topic_arn                = aws_sns_topic.ses_events.arn
  notification_type        = "Bounce"
  identity                 = aws_ses_domain_identity.this.domain
  include_original_headers = true
}

resource "aws_ses_identity_notification_topic" "complaint" {
  topic_arn                = aws_sns_topic.ses_events.arn
  notification_type        = "Complaint"
  identity                 = aws_ses_domain_identity.this.domain
  include_original_headers = true
}
