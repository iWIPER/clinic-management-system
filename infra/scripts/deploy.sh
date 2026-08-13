#!/usr/bin/env bash
# Deploy do Wildental para producao (ECS Fargate).
#
# O que faz, em ordem:
#   1. build da imagem Docker e push pro ECR (tag = git sha curto + timestamp)
#   2. registra uma nova revisao dos task definitions (web/worker/scheduler/migrate)
#      apontando pra imagem nova, preservando o resto da definicao atual
#   3. roda a migration como ECS one-off task e ESPERA o resultado - se falhar,
#      para aqui e nao atualiza nenhum service
#   4. atualiza os services web/worker pra nova revisao e espera estabilizar
#   5. atualiza o alvo do EventBridge Scheduler pra nova revisao do scheduler
#
# Pre-requisitos: aws cli v2, docker, jq, credenciais AWS configuradas
# (aws configure ou variaveis de ambiente) com permissao nos recursos abaixo.
#
# Uso:
#   infra/scripts/deploy.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
TF_DIR="${SCRIPT_DIR}/../terraform/environments/production"

echo "==> Lendo outputs do Terraform..."
cd "${TF_DIR}"
AWS_REGION=$(terraform output -raw ecr_repository_url | cut -d. -f4)
ECR_REPO_URL=$(terraform output -raw ecr_repository_url)
ECS_CLUSTER=$(terraform output -raw ecs_cluster_name)
WEB_SERVICE=$(terraform output -raw ecs_web_service_name)
WORKER_SERVICE=$(terraform output -raw ecs_worker_service_name)
cd "${REPO_ROOT}"

FAMILY_PREFIX="wildental-production"
IMAGE_TAG="$(git -C "${REPO_ROOT}" rev-parse --short HEAD 2>/dev/null || echo nogit)-$(date +%Y%m%d%H%M%S)"
IMAGE_URI="${ECR_REPO_URL}:${IMAGE_TAG}"

echo "==> Build da imagem: ${IMAGE_URI}"
docker build -t "${IMAGE_URI}" "${REPO_ROOT}"

echo "==> Login no ECR (${AWS_REGION})"
aws ecr get-login-password --region "${AWS_REGION}" \
  | docker login --username AWS --password-stdin "${ECR_REPO_URL%%/*}"

echo "==> Push da imagem"
docker push "${IMAGE_URI}"

register_new_revision() {
  local family="$1"
  local current_def
  current_def=$(aws ecs describe-task-definition --task-definition "${family}" --query 'taskDefinition' --output json)

  local new_def
  new_def=$(echo "${current_def}" | jq --arg IMAGE "${IMAGE_URI}" '
    .containerDefinitions[0].image = $IMAGE
    | del(.taskDefinitionArn, .revision, .status, .requiresAttributes, .compatibilities, .registeredAt, .registeredBy)
  ')

  aws ecs register-task-definition --cli-input-json "${new_def}" --query 'taskDefinition.taskDefinitionArn' --output text
}

echo "==> Registrando nova revisao: web"
WEB_TASK_DEF_ARN=$(register_new_revision "${FAMILY_PREFIX}-web")

echo "==> Registrando nova revisao: worker"
WORKER_TASK_DEF_ARN=$(register_new_revision "${FAMILY_PREFIX}-worker")

echo "==> Registrando nova revisao: scheduler"
SCHEDULER_TASK_DEF_ARN=$(register_new_revision "${FAMILY_PREFIX}-scheduler")

echo "==> Registrando nova revisao: migrate"
MIGRATE_TASK_DEF_ARN=$(register_new_revision "${FAMILY_PREFIX}-migrate")

echo "==> Rodando migration (php artisan migrate --force) e aguardando..."
NETWORK_CONFIG=$(aws ecs describe-services --cluster "${ECS_CLUSTER}" --services "${WEB_SERVICE}" \
  --query 'services[0].networkConfiguration' --output json)

RUN_TASK_OUT=$(aws ecs run-task \
  --cluster "${ECS_CLUSTER}" \
  --task-definition "${MIGRATE_TASK_DEF_ARN}" \
  --launch-type FARGATE \
  --network-configuration "${NETWORK_CONFIG}" \
  --output json)

MIGRATE_TASK_ARN=$(echo "${RUN_TASK_OUT}" | jq -r '.tasks[0].taskArn')
echo "    task: ${MIGRATE_TASK_ARN}"

aws ecs wait tasks-stopped --cluster "${ECS_CLUSTER}" --tasks "${MIGRATE_TASK_ARN}"

EXIT_CODE=$(aws ecs describe-tasks --cluster "${ECS_CLUSTER}" --tasks "${MIGRATE_TASK_ARN}" \
  --query 'tasks[0].containers[0].exitCode' --output text)

if [ "${EXIT_CODE}" != "0" ]; then
  echo "!! Migration falhou (exit code ${EXIT_CODE}). Abortando deploy - services NAO foram atualizados."
  echo "   Verifique os logs em CloudWatch: /ecs/${FAMILY_PREFIX}/migrate"
  exit 1
fi

echo "==> Migration OK. Atualizando services..."

aws ecs update-service --cluster "${ECS_CLUSTER}" --service "${WEB_SERVICE}" \
  --task-definition "${WEB_TASK_DEF_ARN}" --force-new-deployment --output text >/dev/null

aws ecs update-service --cluster "${ECS_CLUSTER}" --service "${WORKER_SERVICE}" \
  --task-definition "${WORKER_TASK_DEF_ARN}" --force-new-deployment --output text >/dev/null

echo "==> Atualizando alvo do EventBridge Scheduler..."
SCHEDULE_NAME="${FAMILY_PREFIX}-schedule-run"
CURRENT_SCHEDULE=$(aws scheduler get-schedule --name "${SCHEDULE_NAME}" --output json)
UPDATED_SCHEDULE=$(echo "${CURRENT_SCHEDULE}" | jq --arg ARN "${SCHEDULER_TASK_DEF_ARN}" '
  .Target.EcsParameters.TaskDefinitionArn = $ARN
  | {Name, GroupName, ScheduleExpression, ScheduleExpressionTimezone, FlexibleTimeWindow, Target, State}
')
aws scheduler update-schedule --name "${SCHEDULE_NAME}" --cli-input-json "${UPDATED_SCHEDULE}" --output text >/dev/null

echo "==> Aguardando o service web estabilizar..."
aws ecs wait services-stable --cluster "${ECS_CLUSTER}" --services "${WEB_SERVICE}"

echo "==> Deploy concluido: ${IMAGE_URI}"
echo "    Para rollback: infra/scripts/rollback.sh"
