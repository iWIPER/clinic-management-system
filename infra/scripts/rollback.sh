#!/usr/bin/env bash
# Rollback do service web e/ou worker para a revisao anterior do task definition.
#
# Uso:
#   infra/scripts/rollback.sh web       # so o service web
#   infra/scripts/rollback.sh worker    # so o service worker
#   infra/scripts/rollback.sh           # os dois
#
# Nao mexe no scheduler nem roda migration - se o rollback for por causa de
# uma migration quebrada, trate o banco manualmente antes (nao ha rollback
# automatico de schema).

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TF_DIR="${SCRIPT_DIR}/../terraform/environments/production"

TARGET="${1:-both}"

cd "${TF_DIR}"
ECS_CLUSTER=$(terraform output -raw ecs_cluster_name)
WEB_SERVICE=$(terraform output -raw ecs_web_service_name)
WORKER_SERVICE=$(terraform output -raw ecs_worker_service_name)
cd - >/dev/null

rollback_service() {
  local service="$1"
  local family="$2"

  local current_revision
  current_revision=$(aws ecs describe-services --cluster "${ECS_CLUSTER}" --services "${service}" \
    --query 'services[0].taskDefinition' --output text | grep -oE '[0-9]+$')

  local previous_revision=$((current_revision - 1))

  if [ "${previous_revision}" -lt 1 ]; then
    echo "!! ${service}: nao ha revisao anterior (atual = ${current_revision})."
    return 1
  fi

  local previous_task_def="${family}:${previous_revision}"
  echo "==> ${service}: revisao atual ${current_revision} -> voltando para ${previous_revision}"

  aws ecs update-service --cluster "${ECS_CLUSTER}" --service "${service}" \
    --task-definition "${previous_task_def}" --force-new-deployment --output text >/dev/null

  echo "    aguardando estabilizar..."
  aws ecs wait services-stable --cluster "${ECS_CLUSTER}" --services "${service}"
  echo "    ${service} revertido para ${previous_task_def}"
}

case "${TARGET}" in
  web)
    rollback_service "${WEB_SERVICE}" "wildental-production-web"
    ;;
  worker)
    rollback_service "${WORKER_SERVICE}" "wildental-production-worker"
    ;;
  both)
    rollback_service "${WEB_SERVICE}" "wildental-production-web"
    rollback_service "${WORKER_SERVICE}" "wildental-production-worker"
    ;;
  *)
    echo "Uso: $0 [web|worker|both]"
    exit 1
    ;;
esac
