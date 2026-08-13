#!/usr/bin/env bash
# Busca uma sessao temporaria (via MFA) da WiLDentalTerraformDeployRole e
# grava como credenciais estaticas de curta duracao no profile local
# "wildental-terraform".
#
# Por que isso existe: o provider AWS do Terraform (hashicorp/aws-sdk-go-base)
# nao sabe pedir codigo MFA interativamente - so a AWS CLI sabe. Por isso o
# assume-role com MFA precisa acontecer AQUI (via `aws sts assume-role`, que
# ja lida com --token-code nativamente), e o Terraform so recebe credenciais
# de sessao ja prontas (AccessKeyId/SecretAccessKey/SessionToken), validas por
# ate 1h (limite da role, MaxSessionDuration=3600). Nenhum desses valores e
# gravado em nenhum arquivo do repositorio - so no ~/.aws/credentials local.
#
# Uso:
#   infra/scripts/aws-terraform-session.sh <codigo-mfa-de-6-digitos>
#
# Depois disso, use normalmente:
#   AWS_PROFILE=wildental-terraform terraform plan

set -euo pipefail

MFA_CODE="${1:?Uso: $0 <codigo-mfa-de-6-digitos>}"

ROLE_ARN="arn:aws:iam::429601541646:role/WiLDentalTerraformDeployRole"
MFA_SERIAL="arn:aws:iam::429601541646:mfa/WiLDentalTerraformBootstrap-mfa"
BOOTSTRAP_PROFILE="wildental-bootstrap"
TARGET_PROFILE="wildental-terraform"
REGION="sa-east-1"

echo "==> Assumindo ${ROLE_ARN} com MFA (sessao de 1h)..."

CREDS_JSON=$(aws sts assume-role \
  --profile "${BOOTSTRAP_PROFILE}" \
  --role-arn "${ROLE_ARN}" \
  --role-session-name "wildental-terraform-$(date +%Y%m%d%H%M%S)" \
  --serial-number "${MFA_SERIAL}" \
  --token-code "${MFA_CODE}" \
  --duration-seconds 3600 \
  --output json)

AKID=$(echo "${CREDS_JSON}" | grep -o '"AccessKeyId": *"[^"]*"' | sed 's/.*"\([A-Z0-9]*\)"$/\1/')
ASECRET=$(echo "${CREDS_JSON}" | grep -o '"SecretAccessKey": *"[^"]*"' | sed 's/"SecretAccessKey": *"\([^"]*\)"/\1/')
ATOKEN=$(echo "${CREDS_JSON}" | grep -o '"SessionToken": *"[^"]*"' | sed 's/"SessionToken": *"\([^"]*\)"/\1/')
EXPIRATION=$(echo "${CREDS_JSON}" | grep -o '"Expiration": *"[^"]*"' | sed 's/"Expiration": *"\([^"]*\)"/\1/')

aws configure set aws_access_key_id "${AKID}" --profile "${TARGET_PROFILE}"
aws configure set aws_secret_access_key "${ASECRET}" --profile "${TARGET_PROFILE}"
aws configure set aws_session_token "${ATOKEN}" --profile "${TARGET_PROFILE}"
aws configure set region "${REGION}" --profile "${TARGET_PROFILE}"

unset CREDS_JSON ASECRET ATOKEN AKID

echo "==> Sessao temporaria gravada no profile [${TARGET_PROFILE}]."
echo "    Expira em: ${EXPIRATION}"
echo "    Use: AWS_PROFILE=${TARGET_PROFILE} terraform plan"
