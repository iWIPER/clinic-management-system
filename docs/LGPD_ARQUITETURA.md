# LGPD — Base Arquitetural (Fase C4)

> Documento técnico, não jurídico. Mapeia a arquitetura real do sistema
> contra os requisitos de privacidade/segurança que a LGPD (Lei 13.709/2018)
> impõe a um produto que trata dado pessoal e dado de saúde. Não declara
> base legal de nenhum tratamento, não classifica juridicamente nenhum
> fornecedor como controlador/operador, e não substitui análise jurídica
> antes de qualquer comunicação pública (política de privacidade, aviso de
> cookies, etc.). Onde o código não permite determinar algo com certeza,
> está marcado explicitamente como "não determinado".

Escrito em 2026-08-18, durante a Fase C4 (Policies e autorização
centralizada) — ver também [[project_gestao_clinicas]] na memória do
projeto.

## 1. Por que este documento existe agora

C4 não implementa nenhum direito do titular (acesso, correção, portabilidade,
anonimização, eliminação) nem o Registro de Operações de Tratamento
completo — isso fica para uma fase futura dedicada. O que esta fase garante
é que a arquitetura de autorização (Policies) não *impede* essas
funcionalidades de serem construídas depois, e que existe um mapeamento
honesto do estado atual pra servir de ponto de partida.

## 2. Dados pessoais e de saúde identificados

Levantado a partir dos models e regras de validação reais, não de suposição.

| Categoria | Onde vive | Observação |
|---|---|---|
| Identificação (nome, CPF, RG, passaporte, nascimento, sexo) | `patients` | Inclui responsável legal quando aplicável (`responsavel_legal_*`) |
| Contato (telefone, e-mail, endereço) | `patients` | |
| Dado de saúde (anamnese, odontograma, evolução clínica, prontuário, exames) | `patient_anamneses`, `patient_odontograms`, `clinical_evolutions`, `clinical_records`, `documents` | Categoria de dado sensível pela LGPD (art. 5º, II) |
| Fotos clínicas | `patient_photos` (metadados no banco; arquivo no Google Drive da clínica) | |
| Documentos assinados (anamnese, evolução, documentos clínicos) | `anamnesis_signatures`, `clinical_evolution_signatures`, `document_signatures` | Assinatura desenhada em canvas (`LocalSignatureProvider`/`LocalDocumentSignatureProvider`/`LocalEvolutionSignatureProvider`) |
| Financeiro do paciente (CPF, telefone, e-mail enviados a financeiras) | `FinancingProposalController`/`FinancingSimulationController` | Ver seção 4 — sai da infraestrutura própria |
| Dado de conta de usuário (equipe da clínica) | `users` (nome, e-mail, CPF, CRO, telefone) | Não é titular-paciente, mas ainda é dado pessoal |

## 3. Pontos de coleta

- Formulários de cadastro/edição de paciente (`PatientController`).
- Convite público de paciente (`PatientInvitePublicController`) — o próprio
  titular preenche dados antes de existir conta.
- Anamnese, odontograma, evolução clínica, prontuário — preenchidos pela
  equipe da clínica durante o atendimento.
- Upload de fotos/documentos (Google Drive da clínica, S3 privado).
- Simulação/proposta de financiamento — CPF/nome/telefone/e-mail
  digitados no fluxo de orçamento, enviados à financeira escolhida.
- Compartilhamento de documento (`DocumentShareController`) — nome/CPF do
  destinatário digitados no fluxo público de verificação de identidade.

## 4. Armazenamento

| Onde | O quê | Região |
|---|---|---|
| PostgreSQL (RDS, `sa-east-1`) | Todos os dados estruturados (pacientes, anamneses, evoluções, financeiro, usuários) | Brasil (São Paulo) |
| Google Drive (por clínica, via OAuth `drive.file`) | Fotos e documentos originais do paciente | Não determinado — depende de onde o Google aloca o Drive da conta Google de cada clínica; não há garantia de residência no Brasil |
| AWS S3 privado (`sa-east-1`) | PDFs de documentos/anamnese/prontuário gerados pelo sistema, avatares | Brasil (São Paulo) |
| AWS Secrets Manager (`sa-east-1`) | Credenciais de integração (não dado de paciente) | Brasil (São Paulo) |

## 5. Integrações reais com terceiros (auditadas no código, não presumidas)

| Serviço | Onde é usado | Tipo de dado que pode chegar até ele | Finalidade aparente | Dado pessoal? | Dado sensível? | País/região | Transferência internacional aparente? |
|---|---|---|---|---|---|---|---|
| **Google Drive** (OAuth `drive.file` + `userinfo.email`, por clínica) | `GoogleDriveAuthService`/`GoogleDriveStructureService`/`GoogleDriveService` | Fotos e documentos do paciente (dado de saúde) | Storage de arquivos do paciente, delegado à conta Google da própria clínica | Sim | Sim | Não determinado — requer validação/documentação | Provável — infraestrutura Google é global; não determinado se há garantia contratual de região |
| **Stripe** (billing) | `CheckoutController`, `StripeWebhookController`, `config/services.php: stripe` | Dado da clínica-cliente (nome, e-mail, cartão) — não dado de paciente | Cobrança de assinatura SaaS | Sim (da clínica, não do paciente) | Não | EUA (padrão Stripe) — não determinado se há configuração regional específica | Sim, presumível (arquitetura padrão Stripe) — requer validação/documentação |
| **AWS SES** | `MAIL_MAILER=ses` (infra de produção) | E-mail transacional (convites, notificações) — pode incluir nome do destinatário | Envio de e-mail transacional | Sim | Não | `sa-east-1` (São Paulo), conforme Terraform | Não determinado — depende de rota interna da AWS, não visível no código |
| **Google OAuth (login/identidade)** | `GoogleDriveAuthService::fetchEmailFromToken` | E-mail da conta Google conectada | Confirmar identidade da conta Drive conectada | Sim | Não | Não determinado | Provável, mesma ressalva do Drive |
| **Financiamento (BancoBV, DentalCred, DrCash, Konsiga)** | `app/Services/Financial/Gateways/*`, `FinancingProposalController`, `FinancingSimulationController` | CPF, nome, telefone, e-mail do paciente (validação explícita no controller) | Simulação/proposta de crédito pro tratamento odontológico | Sim | Não (financeiro, não saúde) | Não determinado — prováveis prestadores brasileiros, não confirmado no código | Não determinado |
| **Google Analytics** (`gtag`) | `resources/views/app.blade.php`, condicional a `GA_MEASUREMENT_ID` configurado | Dado de navegação/uso (não visto explicitamente enviando PII, mas gtag pode capturar IP/user-agent por padrão do Google) | Analytics de uso do produto | Possível (IP, comportamento) | Não | EUA (padrão Google Analytics) | Sim, presumível — requer validação/documentação |
| **Meta Pixel** (`fbq`) | `resources/views/app.blade.php`, condicional a `META_PIXEL_ID` configurado | Dado de navegação/uso | Marketing/remarketing | Possível | Não | EUA (padrão Meta) | Sim, presumível — requer validação/documentação |
| **Mailgun / Postmark** | Configurados em `config/services.php`, mas `MAIL_MAILER=ses` em produção (ver infra) | — | Não usados como driver ativo — configuração vestigial do skeleton Laravel | N/A | N/A | N/A | N/A |

**Nenhuma integração de IA foi encontrada no código.**

## 6. Compartilhamento — quem recebe dado do paciente, hoje

- **A clínica em si** (equipe autorizada, via RBAC — ver Fase C4).
- **Google** (arquivo bruto, via Drive da clínica — a clínica é dona da
  conta Google, não o Wildental).
- **Financeira escolhida pelo paciente/clínica**, só quando uma simulação ou
  proposta de financiamento é explicitamente submetida — não é automático.
- **Destinatário de um compartilhamento de documento** (`DocumentShareController`
  + `DocumentSharePasswordController`), só quando a clínica explicitamente
  compartilha, com verificação de identidade (nome+CPF) e expiração.

Não há integração de IA, não há venda/compartilhamento de dado com
terceiros fora do necessário pra operar a própria funcionalidade que o
usuário acionou.

## 7. Base estrutural para direitos do titular — o que a C4 preparou

Não implementado agora (fica pra fase futura de LGPD), mas a arquitetura de
Policies criada nesta fase não impede:

- **Acesso**: `PatientPolicy::view` já centraliza "quem pode ver os dados
  deste paciente" — uma futura rota de exportação usaria a mesma Policy.
- **Correção**: `PatientPolicy::update` já centraliza "quem pode alterar".
- **Eliminação/anonimização**: `PatientPolicy::delete` já existe como
  ability nomeada — hoje só verifica tenant, mas é o ponto de extensão
  natural pra regras futuras (ex.: exigir confirmação adicional, período de
  retenção mínimo por obrigação legal de prontuário odontológico).
- **Portabilidade/exportação**: nenhuma Policy foi desenhada de forma
  restritiva o suficiente pra impedir um futuro `ExportController` de
  reutilizar `PatientPolicy::view` como base de autorização.
- **Rastreabilidade**: os logs de atividade já existentes (`DriveActivityLog`,
  `AnamnesisActivityLog`, `DocumentActivityLog`, `AccessLog`) continuam
  funcionando e não foram alterados — são a base natural pra um futuro
  registro de solicitações LGPD.

## 8. Privacy by design — princípios já presentes, documentados aqui

- **Isolamento por clínica**: `ClinicScope` + `BelongsToClinic` (mecanismo
  de dados) e as Policies da C4 (mecanismo de autorização) — camadas
  separadas e complementares, ver auditoria da C4.
- **Mínimo privilégio**: RBAC por clínica (`owner`/`admin`/`professional`/`staff`),
  agora centralizado via `ClinicPolicy::manageTeam`.
- **Finalidade definida por integração**: cada serviço terceiro tem uma
  função única e local (Drive = arquivo, Stripe = cobrança, financeiras =
  crédito) — nenhuma integração "genérica" que espalha dado sem propósito
  claro.
- **Gap conhecido, não introduzido por esta fase**: `ClinicScope` é inerte
  em contexto de console/job (`app()->runningInConsole()`); mitigado onde
  identificado via checagem explícita (agora via Policy).

## 9. Pontos que exigem documentação futura (não determinados pelo código)

- Região de armazenamento real do Google Drive de cada clínica.
- Se a AWS SES processa e-mails fora de `sa-east-1` em algum ponto da rota.
- Se há cláusula contratual de transferência internacional com Google/Stripe/Meta
  (Standard Contractual Clauses ou equivalente).
- Confirmação formal de quais das 4 financeiras (BancoBV, DentalCred, DrCash,
  Konsiga) estão realmente conectadas com credenciais reais em produção hoje
  vs. em modo mock/sandbox.

## 10. Pontos que exigem validação jurídica (fora do escopo técnico desta fase)

- Base legal aplicável a cada tratamento listado na seção 5 (não inventada
  aqui — decisão do time jurídico/DPO).
- Classificação formal de cada fornecedor como controlador/operador/
  suboperador.
- Necessidade e conteúdo de Acordo de Tratamento de Dados (DPA) com Google,
  Stripe, financeiras e (se ativado) Google Analytics/Meta Pixel.
- Prazo de retenção de prontuário odontológico conforme legislação
  profissional (CFO) x LGPD.
