# Staging (ainda não provisionado)

Não existe ambiente de staging hoje — o WiLDental ainda é um piloto sem clientes reais, e o usuário optou por concentrar o esforço em produção primeiro (ver `infra/README.md` e o plano de infraestrutura original).

Quando fizer sentido criar staging, os módulos em `infra/terraform/modules/` já são reutilizáveis — basta:

1. Copiar `infra/terraform/environments/production/` para `infra/terraform/environments/staging/`.
2. Em `backend.tf`, trocar a `key` do state para `staging/terraform.tfstate` (mesmo bucket de state, prefixo diferente).
3. Em `terraform.tfvars`, trocar `environment = "staging"` e ajustar domínio (ex.: `staging.wildental.com.br`), tamanhos de instância (menores) e `rds_deletion_protection = false` (ambiente descartável).
4. Nenhum módulo precisa ser duplicado ou alterado — todos recebem `project`/`environment` como variáveis e nomeiam os recursos AWS de forma isolada por ambiente automaticamente.

Isso evita duas RDS, dois ALBs e dois clusters custando dinheiro sem necessidade enquanto não há uso real de staging.
