# PADRÃO OFICIAL PARA IMPORTAÇÃO DE ANAMNESES (ClinicFlow)

## Estrutura

Cada pergunta DEVE seguir exatamente este formato.

    QUESTION:
    Queixa principal?

    CATEGORY:
    QUEIXA PRINCIPAL

    TYPE:
    TEXT

    ALERT:
    NONE

    SHOW_ON_PATIENT_CARD:
    true

------------------------------------------------------------------------

### Campos permitidos

MODEL: - ADULTA - ADULTA_RESUMIDA - INFANTIL - INFANTIL_RESUMIDA - HOF -
ORTODONTIA

CATEGORY: - QUEIXA PRINCIPAL - GERAL - DOENÇAS SISTÊMICAS - HISTÓRICO -
HÁBITOS - EXAME CLÍNICO - GESTAÇÃO - ODONTOLÓGICO - COVID - ORTODONTIA -
ESTÉTICA - PEDIATRIA

TYPE: - TEXT - YES_NO - YES_NO_TEXT - YES_NO_UNKNOWN -
YES_NO_UNKNOWN_TEXT

ALERT: - NONE ou - Hipertenso - Diabético - Toma - Alérgico - Grávida -
Problema renal - Problema respiratório - Alteração cardíaca - Alteração
sanguínea - Disfunção hepática - Câncer - Doença transmissível -
Alérgico ao anestésico - Antecedente de endocardite bacteriana -
Alteração óssea - Risco de hemorragia - Outro texto

SHOW_ON_PATIENT_CARD: true false

------------------------------------------------------------------------

EXEMPLOS

MODEL: ADULTA

QUESTION: Queixa principal?

CATEGORY: QUEIXA PRINCIPAL

TYPE: TEXT

ALERT: NONE

SHOW_ON_PATIENT_CARD: true

------------------------------------------------------------------------

MODEL: ADULTA

QUESTION: Está usando medicação?

CATEGORY: DOENÇAS SISTÊMICAS

TYPE: YES_NO_UNKNOWN_TEXT

ALERT: Toma

SHOW_ON_PATIENT_CARD: true

------------------------------------------------------------------------

MODEL: ADULTA

QUESTION: Possui diabetes?

CATEGORY: DOENÇAS SISTÊMICAS

TYPE: YES_NO_UNKNOWN_TEXT

ALERT: Diabético

SHOW_ON_PATIENT_CARD: true

------------------------------------------------------------------------

MODEL: ADULTA

QUESTION: Quando foi sua última consulta ao dentista?

CATEGORY: HISTÓRICO

TYPE: TEXT

ALERT: NONE

SHOW_ON_PATIENT_CARD: true

=====================================================

OBSERVAÇÕES

• Nunca escrever "Sem alerta". Use ALERT: NONE.

• Nunca escrever "Pergunta Sim/Não". Use TYPE.

• Nunca usar traços (-) antes das perguntas.

• Nunca misturar observações com perguntas.

• Cada pergunta é um bloco independente.

• Todos os modelos reutilizam perguntas do banco principal.

• O sistema deve interpretar SOMENTE estes campos: MODEL QUESTION
CATEGORY TYPE ALERT SHOW_ON_PATIENT_CARD
