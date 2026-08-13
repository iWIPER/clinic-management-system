# Instruções permanentes do projeto

## Validação obrigatória após alterações de UI/Agenda/formulários

Depois de QUALQUER alteração que afete frontend, UI, Agenda, formulário ou
fluxo de usuário, é obrigatório antes de considerar a tarefa concluída:

1. Testes de backend relevantes (Tinker/PHPUnit conforme aplicável).
2. Testes da lógica isolada, quando fizer sentido (ex.: composables puros).
3. Build do frontend (`npm run build`).
4. **Teste visual real com navegador autenticado** — preferencialmente
   Playwright. Isso inclui: subir o ambiente local (`php artisan serve` +
   build ou `npm run dev`), autenticar com um usuário de teste real,
   navegar até a tela afetada, executar os fluxos reais (clicar, preencher
   formulários, submeter), e observar o resultado — não só o HTTP status.
5. Screenshots das telas/estados relevantes, inspecionados de fato (não só
   capturados).
6. Corrigir qualquer regressão encontrada e repetir o teste visual depois
   da correção.

**Nunca declarar uma alteração de UI concluída apenas porque `npm run
build` passou.** Build verifica sintaxe/compilação, não comportamento —
já houve caso real neste projeto de uma regra de negócio corretamente
implementada em um componente mas nunca conectada a outro (formulário de
criação de agendamento), passando no build e em testes isolados, mas
quebrada no fluxo real do usuário.

## Após alterações de backend

- Rodar lint (`php -l` nos arquivos alterados, no mínimo).
- Rodar os testes relevantes (`php artisan test --filter=...`).
- Sempre que possível, testar o fluxo real via requisição HTTP autêntica
  (não apenas chamar o método isoladamente via Tinker/reflection) —
  principalmente para regras de autorização/validação que dependem de
  sessão, CSRF ou contexto de tenant/clínica.
