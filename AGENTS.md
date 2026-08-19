# AGENTS.md

Regras para qualquer agente de IA (Claude Code, Codex ou outro) trabalhando
neste repositório. Complementa, sem duplicar, `CLAUDE.md` (checklist
específica de validação pós-alteração de UI/backend) e a documentação
técnica em `docs/`.

## Arquitetura

- **Stack:** Laravel 11 (PHP 8.3) + Inertia.js + Vue 3 (Composition API) +
  Tailwind CSS + PostgreSQL 16. Testes rodam em SQLite in-memory (Pest).
- **Multi-tenant:** isolamento por `clinic_id`. `App\Scopes\ClinicScope`
  (global scope, fail-closed — sem `current_clinic_id` na sessão, nenhum
  registro é retornado) + trait `BelongsToClinic` nos models. Middleware
  `EnsureCurrentClinic` valida a clínica ativa e a membresia em
  `clinic_user` a cada requisição.
- **Autorização:** Laravel Policies em `app/Policies/`. Dois padrões de
  semântica de `clinic_id` nulo, em `app/Policies/Concerns/`:
  `AuthorizesClinicOwnership` (nulo nunca casa — recurso pertence sempre a
  uma clínica) e `AllowsGlobalOrOwnClinic` (nulo sempre casa — recurso
  global/sistema, ex. categorias/templates padrão).
- **Camadas:** Controllers (`app/Http/Controllers`) finos, regra de negócio
  não-trivial vive em `app/Services/`. Validação cross-tenant em
  `exists:`/`Rule::exists()` **sempre** escopada por `clinic_id` (essas
  regras ignoram global scopes por padrão — ver exemplos existentes em
  qualquer controller antes de escrever uma nova).
- **Convites/tokens públicos:** entropia forte (`Str::random(32)`),
  validade máxima e revogação já são tratadas como regra de negócio
  aplicada no backend, nunca só no frontend.

Não invente novos padrões arquiteturais. Se uma tela ou fluxo já resolve algo
de um jeito, siga o mesmo jeito — não introduza uma segunda forma de fazer a
mesma coisa.

## Segurança

- Preservar isolamento entre tenants em toda alteração — nunca remover ou
  enfraquecer `ClinicScope`, `BelongsToClinic` ou o escopo por
  `clinic_id` de uma query existente.
- Nunca ignorar ou contornar autorização (Policies) existente.
- Nunca expor secrets, tokens ou credenciais — nem em código, nem em
  commits, nem em respostas/relatórios. `.env` nunca é commitado.
- Nunca colocar credenciais/segredos no frontend.
- Validar sempre no backend, mesmo quando já há validação client-side.
- Considerar LGPD ao lidar com dado pessoal/sensível (ver
  `docs/LGPD_ARQUITETURA.md`).
- Não remover proteções de segurança "para facilitar" o desenvolvimento.
- Não desabilitar ou pular testes de segurança para fazer a suíte passar.

## Banco de dados

- Nunca rodar comando destrutivo (`migrate:fresh`, `migrate:reset`,
  `db:wipe`, `DELETE`/`TRUNCATE` em massa) sem autorização explícita do
  usuário, mesmo em ambiente local.
- Alterações estruturais seguem a estratégia de migrations já usada no
  projeto (uma migration por mudança, nomeada com data/descrição).
- Não alterar dados de benchmark (`database/seeders/BenchmarkSeeder.php`)
  sem necessidade real — e nunca reexecutar esse seeder automaticamente.
- Diferenciar claramente dado de desenvolvimento, dado de benchmark
  (sintético, gerado localmente) e dado de produção. Nunca tratar um como
  se fosse outro.

## Desenvolvimento

- Entender a implementação existente antes de modificar.
- Procurar reutilização antes de criar duplicação (helper, composable,
  Service já existente).
- Preservar padrões já estabelecidos, mesmo que não sejam os que você
  escolheria do zero.
- Evitar refatorações não relacionadas à tarefa pedida.
- Não corrigir "coisas extras" fora do escopo — registrar como
  recomendação em vez de mexer.
- Não adicionar dependências novas sem justificar por que a alternativa
  nativa/já instalada não resolve.

## Testes

- Toda alteração relevante deve vir com teste novo ou atualizado quando
  fizer sentido, e os testes relacionados devem ser executados antes de
  reportar a tarefa concluída.
- Alteração de UI/Agenda/formulário segue a checklist específica já
  definida em `CLAUDE.md` (inclui build, teste visual autenticado e
  screenshots — não repetida aqui).
- Reportar o resultado real dos testes/build — nunca declarar sucesso sem
  ter rodado.

## Git

Fluxo: Issue → branch → implementação → testes → PR (CI validando) →
revisão/QA → merge.

Alterações relevantes usam branch própria — nunca direto na `master`. Merge
só ocorre depois que o CI passa e a revisão/QA está concluída.

Convenção de branch:

```
feature/<descricao>
fix/<descricao>
security/<descricao>
refactor/<descricao>
chore/<descricao>
```

Commits claros, relacionados à alteração que descrevem (sem misturar
mudanças não relacionadas no mesmo commit).

Exceção histórica: a consolidação inicial deste repositório — organizar um
volume de trabalho já existente em um conjunto coerente de commits — foi
feita deliberadamente direto na `master`, sem branch nem PR, para viabilizar
essa reorganização pontual. Não é o padrão para trabalho futuro: a partir
daqui, toda alteração relevante segue o fluxo descrito acima.

## Pull Requests

Todo PR relevante deve:

- referenciar a Issue relacionada;
- explicar o problema;
- explicar a solução;
- informar os testes realizados;
- informar impactos;
- informar limitações ou follow-ups conhecidos.

Quando aplicável, usar `Closes #<numero>` para fechar a Issue no merge.

PR não é depósito de código — é o mecanismo de revisão e integração, não de
deploy (ver seção seguinte).

## Deploy

PR + merge na `master` **não** dispara deploy automaticamente. Deploy é uma
etapa posterior, controlada separadamente (infraestrutura em `infra/` —
Terraform + AWS). Nenhum agente deve executar deploy, alterar
infraestrutura AWS ou mexer em produção sem autorização explícita e direta
do usuário para aquela ação específica.

## Agentes de IA

O agente **não deve**:

- reescrever grandes partes do sistema sem necessidade real;
- criar abstrações genéricas sem um uso concreto já existente;
- criar componentes duplicados quando um já resolve;
- adicionar bibliotecas para resolver problemas que código simples resolve;
- gerar documentação excessiva ou redundante com o que já existe;
- adicionar animação, skeleton, lazy loading ou loaders por padrão, sem
  necessidade real perceptível pelo usuário (ver `docs/` se houver
  guideline de motion/UI mais específica);
- transformar uma alteração pequena em uma arquitetura nova.

O agente **deve preferir**: código simples, explícito, testável,
consistente com o que já existe no projeto, e fácil de manter por outro
desenvolvedor humano.
