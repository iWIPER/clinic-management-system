# Arquitetura de Largura Horizontal — Padrão Oficial

> **Status:** consolidado, parte da Project Bible.
> **Escopo:** todas as telas Vue/Inertia sob `resources/js`. Não cobre Blade/PDF (`resources/views/pdf/*`, páginas públicas de validação) — esse é um pipeline de renderização separado, fora do domínio deste documento.
> **Fonte de verdade do código:** `resources/js/Layouts/AppLayout.vue`. Se este documento e o código divergirem, o código está errado — corrija o código para bater com o documento, não o contrário.

## 1. O problema que este documento resolve

Antes deste padrão, cada tela decidia sua própria largura com um `<div class="max-w-*">` ad-hoc. O resultado, levantado em auditoria: 6+ valores de `max-w-*` diferentes e sem critério, várias telas com `max-w-*` **sem** `mx-auto` (conteúdo estreito mas colado à esquerda — o bug visual que motivou esta arquitetura), e nenhuma forma de saber, ao criar uma tela nova, qual largura usar.

Este documento define a regra única. Ela existe para nunca mais precisar ser redescoberta por auditoria.

## 2. Layouts existentes e responsabilidade de cada um

| Layout | Usa `content-width`? | Responsabilidade | Quem usa |
|---|---|---|---|
| **`AppLayout.vue`** | Sim — é o dono do mecanismo | Chrome da aplicação clínica: navbar fixa, região de scroll (`scroll-region`), toasts, barra de progresso de navegação. Único lugar que decide largura de página. | ~98% das telas autenticadas do sistema clínico |
| **`AdminLayout.vue`** | Herda de `AppLayout` (envolve `<AppLayout><slot/></AppLayout>`) | Chrome adicional do backoffice (título "Backoffice", nav secundária de admin) por cima do chrome do `AppLayout`. **Não é um sistema de largura próprio** — é uma camada fina sobre o `AppLayout`. | Telas `Pages/Admin/**` |
| **`AffiliateLayout.vue`** | **Não.** Tem seu próprio `<main class="max-w-5xl mx-auto ...">` hardcoded, independente. | Chrome minimalista do portal do afiliado (navbar simples, sem scroll-region, sem sticky). É uma **exceção deliberada e documentada** — ver seção 9. | Só `Pages/Referrals/Index.vue`, quando `isAffiliate === true` |

Não existe — e não deve ser criado — nenhum quarto layout sem que este documento seja atualizado primeiro.

## 3. O mecanismo `content-width`

Vive inteiramente em `AppLayout.vue`:

```js
const props = defineProps({
    contentWidth: {
        type: String,
        default: 'full',
        validator: (v) => ['sm', 'md', 'lg', 'full'].includes(v),
    },
})

const CONTENT_WIDTH_CLASSES = {
    sm: 'max-w-lg',    // 512px
    md: 'max-w-2xl',   // 672px
    lg: 'max-w-4xl',   // 896px
    full: 'max-w-7xl',  // 1280px — padrão implícito
}
```

`<main>` aplica a classe correspondente + `mx-auto` automaticamente. A página nunca declara `max-w-*` nem `mx-auto` própria para controlar sua largura total — só passa o token.

**Os três tokens não são arbitrários.** `sm`/`md`/`lg` nasceram dos três valores que já existiam organicamente no código antes da padronização (formulários simples, formulários com formulário 2-colunas, formulários densos). Um novo token só deve ser criado quando um cluster real de telas precisar de um quarto valor — nunca para uma tela isolada (ver seção 8, exemplo incorreto).

## 4. Quando usar `content-width`

Use `sm`, `md` ou `lg` quando a tela é **conteúdo de largura naturalmente estreita**:

- Formulário de criar/editar **uma** entidade (`Treatments/Create`, `Patients/Edit`, etc.).
- Tela de configuração de escopo único (`ClinicSettings/Edit`).
- Qualquer tela cujo conteúdo principal seja uma coluna vertical única (título + card + formulário), sem grade de dados, sem múltiplos painéis lado a lado.

Regra prática: se ao esticar a janela para um monitor ultrawide o conteúdo ficaria "perdido" numa faixa fina no meio de uma página vazia — é candidato a `sm/md/lg`. Se o conteúdo naturalmente usa o espaço extra (mais colunas de tabela visíveis, mais dias de calendário, mais cards por linha) — não é.

## 5. Quando **não** usar `content-width`

Deixe em `full` (ou seja, não passe a prop) quando a tela for:

- **Tabela/lista** (`Patients/Index`, `Treatments/Index`, `Team/Index`, ...) — mais largura = mais colunas visíveis sem scroll horizontal.
- **Dashboard/grid de cards** (`Dashboard.vue`, `Finance/Index`) — layout em grade se beneficia do espaço.
- **Calendário/agenda/kanban** (`Appointments/Index`) — sempre vai precisar de mais espaço que qualquer token oferece; essas telas escapam do frame do `AppLayout` via margem negativa (`-mx-4 sm:-mx-6 lg:-mx-8`) para ficar full-bleed, não com um token estreito.
- **Visão de duas colunas / detalhe** (`Patients/Show`, `Prontuario/Show`, `Anamneses/Edit`) — sidebar + conteúdo é parte do design; forçar um token estreito quebraria a proporção das duas colunas.
- **Editor lado a lado** (`Documents/Templates/Editor.vue`, editor + preview) — precisa de mais que os 896px do maior token; usa sua própria largura (`max-w-[1400px]`) por ser um caso genuinamente único, não por preguiça de usar o padrão.
- **Odontograma, ferramentas visuais** — precisam da largura disponível para o desenho/gráfico.
- **Qualquer tela fullscreen** que não usa `AppLayout` (`Appointments/Fullscreen.vue`) — `content-width` não existe nesse contexto, é irrelevante.

## 6. Quem pode controlar largura própria — e quem nunca pode

### Pode (e deve, dentro do seu próprio escopo)

| Categoria | Exemplo | Por quê |
|---|---|---|
| Modais/diálogos | `Modal.vue` (`maxWidth` prop), `DriveHealthReportModal.vue` | Largura de um diálogo é decisão do próprio diálogo — eixo completamente diferente de "largura da página por trás dele". |
| Sidebars de layout de duas colunas | `AnamnesisSidebarNav.vue` (`w-40` no `<aside>` pai) | Faz parte do design de duas colunas daquela tela específica, não é "largura de conteúdo principal". |
| Colunas/células de tabela | `TreatmentsTable.vue` (`max-w-[220px]` numa `<td>`) | Constrangimento de truncamento de texto local à coluna. |
| Preview com fidelidade visual | `DocumentLivePreview.vue` (`max-w-[560px]`, simula papel A4) | A largura representa o **documento sendo editado**, não a página web. |
| Imagens/ícones/badges | qualquer `max-w-[140px]` em logo, `w-[18px]` em ícone | Tamanho intrínseco do elemento, não layout de página. |

### Nunca pode

| Categoria | Exemplo | Regra |
|---|---|---|
| Componentes de campo de formulário | `AddressFields.vue`, `InputError.vue` | **Nunca** declaram `max-w-*`/`width` próprios. Herdam 100% do container pai. Um campo de formulário que impõe sua própria largura quebra qualquer form que o reutilize em contexto diferente. |
| Painéis/cards de conteúdo genérico | `PatientEvolutionCard.vue`, `SectionCard.vue`, qualquer `*Tab.vue` do hub do paciente | Sempre `w-full` implícito (bloco). A largura é decidida pela página que os contém, nunca pelo componente. |
| Qualquer novo layout ou página | — | Proibido recriar `<div class="max-w-* mx-auto">` para controlar a largura *total* da página. Se a tela usa `AppLayout`, a largura total só se decide via `content-width`. |

## 7. Exemplos corretos

```vue
<!-- Treatments/Create.vue — formulário simples, uma entidade -->
<AppLayout content-width="sm">
    <h1 class="text-2xl font-semibold mb-6">Novo Procedimento</h1>
    <form @submit.prevent="submit" class="bg-white p-8 rounded-2xl border space-y-4">
        ...
    </form>
</AppLayout>
```

```vue
<!-- Patients/Create.vue — formulário mais denso (grade 2 colunas) -->
<AppLayout content-width="lg">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Cadastrar Paciente</h1>
        <Link :href="route('patients.index')">← Voltar</Link>
    </div>
    <form @submit.prevent="submit" class="bg-white p-8 rounded-2xl border space-y-6">...</form>
</AppLayout>
```

```vue
<!-- Patients/Index.vue — tabela, permanece full (nenhuma prop passada) -->
<AppLayout>
    <table>...</table>
</AppLayout>
```

## 8. Exemplos incorretos

```vue
<!-- ERRADO: recriando um wrapper de largura dentro da página -->
<AppLayout>
    <div class="max-w-2xl">          <!-- proibido: use content-width="md" -->
        <h1>Novo Agendamento</h1>
        <form>...</form>
    </div>
</AppLayout>
```

```vue
<!-- ERRADO: max-w sem mx-auto — o bug original que motivou este padrão -->
<AppLayout>
    <form class="max-w-lg bg-white ...">...</form>  <!-- fica colado à esquerda -->
</AppLayout>
```

```vue
<!-- ERRADO: inventar um token novo para uma única tela -->
<AppLayout content-width="xl-custom">   <!-- não existe; se "existir" foi
adicionado ao array de validação para UMA tela só — não faça isso. Um token
novo exige um cluster real de telas com a mesma necessidade (ver seção 3). -->
```

```vue
<!-- ERRADO: componente de campo de formulário com largura própria -->
<!-- dentro de AddressFields.vue -->
<div class="max-w-md">    <!-- proibido: quebra qualquer form mais largo que o use -->
    <input ... />
</div>
```

## 9. Exceção documentada: `AffiliateLayout`

`AffiliateLayout.vue` **não** usa `content-width` e isso é intencional, não uma lacuna esquecida. O portal do afiliado é um produto conceitualmente diferente (chrome mais simples, sem os recursos clínicos), não "mais uma tela da clínica". Ele **não deve** ser usado como modelo para nenhuma tela nova do sistema clínico, e nenhuma tela nova do sistema clínico deve replicar o padrão dele (`<main class="max-w-* mx-auto">` hardcoded).

Se, no futuro, o portal do afiliado crescer a ponto de precisar de mais de uma largura de tela, a extensão correta é dar a ele os **mesmos tokens** (`sm/md/lg/full`) — não inventar uma segunda régua de valores.

## 10. Débito técnico conhecido (não bloqueia o padrão, mas está registrado)

- **Redundância cosmética:** `Treatments/Create.vue`, `Treatments/Edit.vue`, `Inventory/Create.vue`, `Patients/Create.vue`, `Patients/Edit.vue` têm `max-w-*` no próprio `<form>` além do `content-width` no `AppLayout` (mesmo valor, duplicado). Zero impacto visual; limpar quando essas telas forem tocadas por outro motivo.
- **`AdminLayout` não declara/repassa `contentWidth` explicitamente.** Funciona hoje por fallthrough automático de atributos do Vue (o root do template de `AdminLayout` é o próprio `<AppLayout>`), não testado formalmente. Antes de qualquer tela `Admin/*` precisar de `content-width`, formalizar o repasse (`defineProps` + `v-bind`).
- **Nomenclatura `sm/md/lg` colide conceitualmente com os breakpoints responsivos do Tailwind** (`sm:`, `md:`, `lg:`), apesar de serem eixos diferentes. Considerar renomear (`compact/standard/wide` ou similar) antes de expandir o uso para muitas telas novas — quanto mais telas dependerem do nome atual, mais caro fica renomear depois.

## 11. Regras para futuras telas

Ao criar uma tela nova sob `AppLayout`:

1. Pergunte: "se a janela for um monitor ultrawide, esse conteúdo deveria esticar ou ficar num bloco centralizado?" — decide `full` vs. `sm/md/lg`.
2. Se for `sm/md/lg`: **não** crie nenhum wrapper de largura na página. Só passe `content-width="..."` no `<AppLayout>`.
3. Se nenhum dos três tokens existentes couber bem, **não invente um valor solto**. Primeiro verifique se outras 2+ telas têm a mesma necessidade (cluster real). Se sim, proponha um token novo alterando `CONTENT_WIDTH_CLASSES` em `AppLayout.vue` e este documento juntos, na mesma mudança. Se for uma tela genuinamente única (editor lado a lado, ferramenta visual), ela pode ter sua própria largura customizada — mas isso é exceção, não a regra, e deve ficar óbvio pelo comentário no código por quê.
4. Componentes que a tela reutiliza (campos de formulário, cards, tabs) nunca devem ganhar `max-w-*`/`width` própria para "ajudar" a centralizar — a responsabilidade é sempre do `AppLayout`.

## 12. Regras para futuros desenvolvedores

- Revisão de PR: qualquer `<div class="max-w-*">` ou `<div class="... mx-auto">` novo dentro de uma página que usa `AppLayout` é sinal de alerta — pergunte "por que isso não é `content-width`?" antes de aprovar.
- Se a resposta for "porque é um modal/sidebar/tabela/preview visual" — legítimo, siga a seção 6.
- Se a resposta for "porque a tela precisa ser mais estreita" — errado, deveria ser `content-width`.
- Nunca copie o padrão do `AffiliateLayout.vue` para uma tela nova do sistema clínico.

## 13. Regras para ferramentas de desenvolvimento automatizado

Antes de escrever ou editar qualquer página Vue sob `resources/js/Pages` que usa `AppLayout`:

1. **Não** adicione `max-w-*`, `mx-auto`, ou `container` para controlar a largura total da página. Use a prop `content-width` do `AppLayout` (`sm|md|lg|full`, default `full`).
2. **Não** crie um novo layout Vue sem antes ler este documento inteiro e confirmar que nenhum dos três layouts existentes serve.
3. Se a tela que você está criando/editando se parece com um dos exemplos da seção 5 ("quando não usar") — não toque na largura, deixe `full`.
4. Se for necessário um token de largura que não existe (`sm/md/lg`), pare e pergunte ao usuário antes de inventar um — não adicione um valor ad-hoc "só para essa tela".
5. Antes de declarar qualquer trabalho de largura de página "concluído", verifique se a página renderiza dentro de `AppLayout` (ou `AdminLayout`, que herda dele) e não dentro de `AffiliateLayout` — os dois têm mecanismos diferentes.
6. Se encontrar uma tela com `max-w-*` sem `mx-auto`, ou um wrapper de largura redundante com o `content-width` já aplicado — isso é o bug/débito técnico que este documento existe para prevenir. Sinalize antes de "corrigir" silenciosamente, a menos que a tarefa seja explicitamente essa limpeza.
7. Este documento é a fonte de verdade sobre *intenção*. O arquivo `AppLayout.vue` é a fonte de verdade sobre *implementação atual*. Se divergirem, avise o usuário — não assuma qual dos dois está desatualizado.
