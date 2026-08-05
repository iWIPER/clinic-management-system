<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Contextos de observações clínicas
    |--------------------------------------------------------------------------
    |
    | Mapeia um contexto do sistema (o módulo/tela onde a observação deveria
    | aparecer automaticamente) para os slugs de PatientTag que a qualificam.
    | Consumido por PatientNoteService::forContext().
    |
    | Nenhum módulo chama forContext() ainda — este mapeamento só prepara a
    | arquitetura para quando Financeiro, Tratamentos, Atendimento e Orçamento
    | tiverem um ponto pronto para exibir observações contextuais.
    |
    */
    'contexts' => [
        'financeiro'  => ['financeiro'],
        'atendimento' => ['ansiedade', 'medicamentos'],
        'orcamento'   => ['convenio'],
        'tratamentos' => ['implante'],
    ],
];
