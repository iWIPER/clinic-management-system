# Cria a hosted zone do zero. Nada disto entra em vigor ate os nameservers
# do dominio (hoje em auto.dns.br, Registro.br) serem apontados para os NS
# desta zone - passo manual, feito por voce, fora do Terraform. Por isso e
# seguro aplicar isto mesmo com o dominio ja em uso: nao ha corte de trafego
# ate a troca manual dos nameservers.

resource "aws_route53_zone" "this" {
  name = var.domain_name

  tags = {
    Name = "${var.project}-${var.environment}-zone"
  }
}

# --- SES: verificacao de dominio + Easy DKIM (3 CNAMEs) ---
resource "aws_route53_record" "ses_verification" {
  zone_id = aws_route53_zone.this.zone_id
  name    = "_amazonses.${var.domain_name}"
  type    = "TXT"
  ttl     = 600
  records = [var.ses_verification_token]
}

resource "aws_route53_record" "ses_dkim" {
  # Fixo em 3: o SES Easy DKIM sempre gera exatamente 3 tokens (comportamento
  # documentado e estavel da AWS). Usar length(var.ses_dkim_tokens) aqui
  # quebra o plan, pois esse valor so existe depois que aws_ses_domain_dkim
  # for criado - count precisa ser conhecido antes do apply.
  count   = 3
  zone_id = aws_route53_zone.this.zone_id
  name    = "${var.ses_dkim_tokens[count.index]}._domainkey.${var.domain_name}"
  type    = "CNAME"
  ttl     = 600
  records = ["${var.ses_dkim_tokens[count.index]}.dkim.amazonses.com"]
}

# --- SPF: autoriza o SES a enviar em nome do dominio ---
resource "aws_route53_record" "spf" {
  zone_id = aws_route53_zone.this.zone_id
  name    = var.domain_name
  type    = "TXT"
  ttl     = 600
  records = ["v=spf1 include:amazonses.com -all"]
}

# --- DMARC: modo monitor (p=none), nao rejeita nada, so reporta ---
resource "aws_route53_record" "dmarc" {
  zone_id = aws_route53_zone.this.zone_id
  name    = "_dmarc.${var.domain_name}"
  type    = "TXT"
  ttl     = 600
  records = ["v=DMARC1; p=none; rua=mailto:${var.dmarc_report_email}"]
}

# --- MX nulo: preserva o comportamento atual do dominio (nao recebe e-mail).
# So mudar isto se decidir passar a RECEBER e-mail em wildental.com.br.
resource "aws_route53_record" "mx_null" {
  zone_id = aws_route53_zone.this.zone_id
  name    = var.domain_name
  type    = "MX"
  ttl     = 600
  records = ["0 ."]
}
