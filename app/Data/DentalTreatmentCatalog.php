<?php

namespace App\Data;

class DentalTreatmentCatalog
{
    public static function categories(): array
    {
        return [
            'Avaliação' => ['cor' => '#3b82f6', 'ordem' => 1],
            'Dentística' => ['cor' => '#10b981', 'ordem' => 2],
            'Endodontia' => ['cor' => '#8b5cf6', 'ordem' => 3],
            'Cirurgia' => ['cor' => '#ef4444', 'ordem' => 4],
            'Periodontia' => ['cor' => '#f59e0b', 'ordem' => 5],
            'Implantodontia' => ['cor' => '#06b6d4', 'ordem' => 6],
            'Prótese' => ['cor' => '#6366f1', 'ordem' => 7],
            'Ortodontia' => ['cor' => '#ec4899', 'ordem' => 8],
            'Radiologia' => ['cor' => '#64748b', 'ordem' => 9],
            'Estética' => ['cor' => '#f472b6', 'ordem' => 10],
        ];
    }

    public static function items(): array
    {
        return [
            // ── Avaliação ──────────────────────────────────────────────────
            ['slug' => 'avaliacao-consulta-inicial', 'categoria' => 'Avaliação', 'tipo' => 'procedimento', 'nome' => 'Consulta Inicial', 'especialidade' => 'Odontologia Geral', 'duracao' => 40, 'preco' => 150, 'ordem' => 1, 'descricao' => 'Consulta de avaliação clínica com anamnese, exame intraoral, registro fotográfico inicial e elaboração de plano de tratamento.'],
            ['slug' => 'avaliacao-consulta-retorno', 'categoria' => 'Avaliação', 'tipo' => 'procedimento', 'nome' => 'Consulta de Retorno', 'especialidade' => 'Odontologia Geral', 'duracao' => 20, 'preco' => 80, 'ordem' => 2, 'descricao' => 'Retorno clínico para acompanhamento de tratamento, revisão de evolução ou pós-operatório.'],
            ['slug' => 'avaliacao-ortodontica', 'categoria' => 'Avaliação', 'tipo' => 'procedimento', 'nome' => 'Avaliação Ortodôntica', 'especialidade' => 'Ortodontia', 'duracao' => 45, 'preco' => 200, 'ordem' => 3, 'descricao' => 'Avaliação ortodôntica com análise de oclusão, relação maxilomandibular e indicação de tratamento ortodôntico.'],
            ['slug' => 'avaliacao-implantodontia', 'categoria' => 'Avaliação', 'tipo' => 'procedimento', 'nome' => 'Avaliação Implantodontia', 'especialidade' => 'Implantodontia', 'duracao' => 50, 'preco' => 250, 'ordem' => 4, 'descricao' => 'Avaliação para reabilitação com implantes, incluindo análise óssea, espaço protético e planejamento cirúrgico.'],
            ['slug' => 'avaliacao-estetica', 'categoria' => 'Avaliação', 'tipo' => 'procedimento', 'nome' => 'Avaliação Estética', 'especialidade' => 'Estética Dental', 'duracao' => 40, 'preco' => 180, 'ordem' => 5, 'descricao' => 'Avaliação estética do sorriso com análise de proporções, cor, forma dentária e harmonização facial.'],

            // ── Dentística ─────────────────────────────────────────────────
            ['slug' => 'dentistica-profilaxia-basica', 'categoria' => 'Dentística', 'tipo' => 'procedimento', 'nome' => 'Profilaxia Básica', 'especialidade' => 'Odontologia Geral', 'duracao' => 30, 'preco' => 150, 'ordem' => 1, 'descricao' => 'Remoção de placa bacteriana e polimento coronário para manutenção preventiva da saúde bucal.'],
            ['slug' => 'dentistica-profilaxia-completa', 'categoria' => 'Dentística', 'tipo' => 'procedimento', 'nome' => 'Profilaxia Completa', 'especialidade' => 'Odontologia Geral', 'duracao' => 60, 'preco' => 300, 'ordem' => 2, 'descricao' => 'Procedimento preventivo destinado à remoção de biofilme bacteriano, placa, cálculo supragengival e manchas extrínsecas, contribuindo para manutenção da saúde periodontal e prevenção de doenças bucais.'],
            ['slug' => 'dentistica-aplicacao-fluor', 'categoria' => 'Dentística', 'tipo' => 'procedimento', 'nome' => 'Aplicação de Flúor', 'especialidade' => 'Odontologia Geral', 'duracao' => 15, 'preco' => 80, 'ordem' => 3, 'descricao' => 'Aplicação tópica de flúor para remineralização do esmalte e prevenção de cáries.'],
            ['slug' => 'dentistica-selante', 'categoria' => 'Dentística', 'tipo' => 'procedimento', 'nome' => 'Selante Dental', 'especialidade' => 'Odontologia Geral', 'duracao' => 20, 'preco' => 120, 'ordem' => 4, 'descricao' => 'Selamento de fissuras e sulcos em molares permanentes para prevenção de cáries.'],

            ['slug' => 'dentistica-grupo-resina', 'categoria' => 'Dentística', 'tipo' => 'grupo', 'nome' => 'Restauração em Resina', 'especialidade' => 'Odontologia Geral', 'duracao' => 0, 'preco' => 0, 'ordem' => 10, 'descricao' => 'Restaurações diretas em resina composta conforme número de faces envolvidas.'],
            ['slug' => 'dentistica-resina-1-face', 'categoria' => 'Dentística', 'tipo' => 'variacao', 'parent' => 'dentistica-grupo-resina', 'nome' => 'Resina 1 Face', 'especialidade' => 'Odontologia Geral', 'duracao' => 30, 'preco' => 180, 'ordem' => 11, 'descricao' => 'Restauração em resina composta de uma face com isolamento absoluto e escultura anatômica.'],
            ['slug' => 'dentistica-resina-2-faces', 'categoria' => 'Dentística', 'tipo' => 'variacao', 'parent' => 'dentistica-grupo-resina', 'nome' => 'Resina 2 Faces', 'especialidade' => 'Odontologia Geral', 'duracao' => 40, 'preco' => 250, 'ordem' => 12, 'descricao' => 'Restauração em resina composta de duas faces com matrizção e acabamento oclusal.'],
            ['slug' => 'dentistica-resina-3-faces', 'categoria' => 'Dentística', 'tipo' => 'variacao', 'parent' => 'dentistica-grupo-resina', 'nome' => 'Resina 3 Faces', 'especialidade' => 'Odontologia Geral', 'duracao' => 50, 'preco' => 320, 'ordem' => 13, 'descricao' => 'Restauração em resina composta de três faces com reconstrução de paredes e contato interproximal.'],
            ['slug' => 'dentistica-resina-4-faces', 'categoria' => 'Dentística', 'tipo' => 'variacao', 'parent' => 'dentistica-grupo-resina', 'nome' => 'Resina 4 Faces', 'especialidade' => 'Odontologia Geral', 'duracao' => 60, 'preco' => 400, 'ordem' => 14, 'descricao' => 'Restauração extensa em resina composta envolvendo quatro faces do elemento dentário.'],
            ['slug' => 'dentistica-resina-estetica-anterior', 'categoria' => 'Dentística', 'tipo' => 'variacao', 'parent' => 'dentistica-grupo-resina', 'nome' => 'Resina Estética Anterior', 'especialidade' => 'Estética Dental', 'duracao' => 60, 'preco' => 450, 'ordem' => 15, 'descricao' => 'Restauração estética em dentes anteriores com stratificação de cor e anatomia labial.'],

            // ── Endodontia ─────────────────────────────────────────────────
            ['slug' => 'endo-grupo-canal', 'categoria' => 'Endodontia', 'tipo' => 'grupo', 'nome' => 'Tratamento de Canal', 'especialidade' => 'Endodontia', 'duracao' => 0, 'preco' => 0, 'ordem' => 1, 'descricao' => 'Tratamentos endodônticos conforme grupo dentário e complexidade radicular.'],
            ['slug' => 'endo-canal-incisivo', 'categoria' => 'Endodontia', 'tipo' => 'variacao', 'parent' => 'endo-grupo-canal', 'nome' => 'Canal — Incisivo', 'especialidade' => 'Endodontia', 'duracao' => 60, 'preco' => 550, 'ordem' => 2, 'descricao' => 'Tratamento endodôntico em dente incisivo com remoção da polpa, desinfecção e obturação do canal.'],
            ['slug' => 'endo-canal-premolar', 'categoria' => 'Endodontia', 'tipo' => 'variacao', 'parent' => 'endo-grupo-canal', 'nome' => 'Canal — Pré-Molar', 'especialidade' => 'Endodontia', 'duracao' => 75, 'preco' => 700, 'ordem' => 3, 'descricao' => 'Tratamento endodôntico em pré-molar com preparo biomecânico e obturação do sistema de canais.'],
            ['slug' => 'endo-canal-molar', 'categoria' => 'Endodontia', 'tipo' => 'variacao', 'parent' => 'endo-grupo-canal', 'nome' => 'Canal — Molar', 'especialidade' => 'Endodontia', 'duracao' => 90, 'preco' => 950, 'ordem' => 4, 'descricao' => 'Tratamento endodôntico em molar com múltiplos canais, desinfecção e selamento tridimensional.'],
            ['slug' => 'endo-canal-retratamento', 'categoria' => 'Endodontia', 'tipo' => 'variacao', 'parent' => 'endo-grupo-canal', 'nome' => 'Retratamento Endodôntico', 'especialidade' => 'Endodontia', 'duracao' => 120, 'preco' => 1200, 'ordem' => 5, 'descricao' => 'Retratamento endodôntico com remoção de material obturador prévio, desinfecção e reobturação.'],

            // ── Cirurgia ───────────────────────────────────────────────────
            ['slug' => 'cirurgia-extracao-simples', 'categoria' => 'Cirurgia', 'tipo' => 'procedimento', 'nome' => 'Extração Simples', 'especialidade' => 'Cirurgia Bucomaxilofacial', 'duracao' => 30, 'preco' => 200, 'ordem' => 1, 'descricao' => 'Exodontia simples de dente indicado clinicamente, sem necessidade de retalho ou osteotomia.'],
            ['slug' => 'cirurgia-extracao-complexa', 'categoria' => 'Cirurgia', 'tipo' => 'procedimento', 'nome' => 'Extração Complexa', 'especialidade' => 'Cirurgia Bucomaxilofacial', 'duracao' => 60, 'preco' => 450, 'ordem' => 2, 'descricao' => 'Exodontia de complexidade moderada com necessidade de técnicas cirúrgicas auxiliares.'],
            ['slug' => 'cirurgia-siso-incluso', 'categoria' => 'Cirurgia', 'tipo' => 'procedimento', 'nome' => 'Siso Incluso', 'especialidade' => 'Cirurgia Bucomaxilofacial', 'duracao' => 90, 'preco' => 800, 'ordem' => 3, 'descricao' => 'Remoção cirúrgica de terceiro molar totalmente incluso com osteotomia e sutura.'],
            ['slug' => 'cirurgia-siso-semi-incluso', 'categoria' => 'Cirurgia', 'tipo' => 'procedimento', 'nome' => 'Siso Semi-Incluso', 'especialidade' => 'Cirurgia Bucomaxilofacial', 'duracao' => 75, 'preco' => 650, 'ordem' => 4, 'descricao' => 'Remoção cirúrgica de terceiro molar parcialmente erupcionado ou semi-incluso.'],
            ['slug' => 'cirurgia-frenectomia', 'categoria' => 'Cirurgia', 'tipo' => 'procedimento', 'nome' => 'Frenectomia', 'especialidade' => 'Cirurgia Bucomaxilofacial', 'duracao' => 45, 'preco' => 350, 'ordem' => 5, 'descricao' => 'Correção cirúrgica de freio labial ou lingual com indicacão ortodôntica ou fonética.'],

            // ── Periodontia ────────────────────────────────────────────────
            ['slug' => 'perio-raspagem-supra', 'categoria' => 'Periodontia', 'tipo' => 'procedimento', 'nome' => 'Raspagem Supragengival', 'especialidade' => 'Periodontia', 'duracao' => 45, 'preco' => 220, 'ordem' => 1, 'descricao' => 'Remoção de cálculo e biofilme supragengival para controle de gengivite.'],
            ['slug' => 'perio-raspagem-sub', 'categoria' => 'Periodontia', 'tipo' => 'procedimento', 'nome' => 'Raspagem Subgengival', 'especialidade' => 'Periodontia', 'duracao' => 60, 'preco' => 350, 'ordem' => 2, 'descricao' => 'Raspagem e alisamento radicular subgengival para tratamento de periodontite.'],
            ['slug' => 'perio-gengivoplastia', 'categoria' => 'Periodontia', 'tipo' => 'procedimento', 'nome' => 'Gengivoplastia', 'especialidade' => 'Periodontia', 'duracao' => 60, 'preco' => 500, 'ordem' => 3, 'descricao' => 'Procedimento cirúrgico periodontal para remodelação do contorno gengival.'],
            ['slug' => 'perio-tratamento-periodontal', 'categoria' => 'Periodontia', 'tipo' => 'procedimento', 'nome' => 'Tratamento Periodontal', 'especialidade' => 'Periodontia', 'duracao' => 90, 'preco' => 600, 'ordem' => 4, 'descricao' => 'Tratamento periodontal completo com sessões de raspagem, instrução de higiene e controle.'],

            // ── Implantodontia ─────────────────────────────────────────────
            ['slug' => 'implante-unitario', 'categoria' => 'Implantodontia', 'tipo' => 'procedimento', 'nome' => 'Implante Unitário', 'especialidade' => 'Implantodontia', 'duracao' => 90, 'preco' => 2800, 'ordem' => 1, 'descricao' => 'Instalação de implante osteointegrado unitário com planejamento cirúrgico guiado.'],
            ['slug' => 'implante-coroa', 'categoria' => 'Implantodontia', 'tipo' => 'procedimento', 'nome' => 'Implante + Coroa', 'especialidade' => 'Implantodontia', 'duracao' => 120, 'preco' => 4500, 'ordem' => 2, 'descricao' => 'Reabilitação unitária com implante e coroa protética sobre implante.'],
            ['slug' => 'implante-multiplo', 'categoria' => 'Implantodontia', 'tipo' => 'procedimento', 'nome' => 'Implante Múltiplo', 'especialidade' => 'Implantodontia', 'duracao' => 150, 'preco' => 7500, 'ordem' => 3, 'descricao' => 'Instalação de múltiplos implantes para reabilitação parcial ou protocolo.'],
            ['slug' => 'implante-enxerto-osseo', 'categoria' => 'Implantodontia', 'tipo' => 'procedimento', 'nome' => 'Enxerto Ósseo', 'especialidade' => 'Implantodontia', 'duracao' => 90, 'preco' => 1800, 'ordem' => 4, 'descricao' => 'Enxerto ósseo para aumento de volume ósseo pré ou co-implante.'],

            // ── Prótese ────────────────────────────────────────────────────
            ['slug' => 'protese-coroa-metaloceramica', 'categoria' => 'Prótese', 'tipo' => 'procedimento', 'nome' => 'Coroa Metalocerâmica', 'especialidade' => 'Prótese Dentária', 'duracao' => 60, 'preco' => 1200, 'ordem' => 1, 'descricao' => 'Confecção e instalação de coroa metalocerâmica para reabilitação posterior.'],
            ['slug' => 'protese-coroa-porcelana', 'categoria' => 'Prótese', 'tipo' => 'procedimento', 'nome' => 'Coroa Porcelana', 'especialidade' => 'Prótese Dentária', 'duracao' => 60, 'preco' => 1800, 'ordem' => 2, 'descricao' => 'Coroa em porcelana pura ou dissilicato para alta estética.'],
            ['slug' => 'protese-faceta-resina', 'categoria' => 'Prótese', 'tipo' => 'procedimento', 'nome' => 'Faceta Resina', 'especialidade' => 'Estética Dental', 'duracao' => 60, 'preco' => 600, 'ordem' => 3, 'descricao' => 'Faceta direta em resina composta para harmonização estética do sorriso.'],
            ['slug' => 'protese-faceta-porcelana', 'categoria' => 'Prótese', 'tipo' => 'procedimento', 'nome' => 'Faceta Porcelana', 'especialidade' => 'Estética Dental', 'duracao' => 90, 'preco' => 1500, 'ordem' => 4, 'descricao' => 'Faceta em porcelana laminada para correção estética de dentes anteriores.'],
            ['slug' => 'protese-parcial', 'categoria' => 'Prótese', 'tipo' => 'procedimento', 'nome' => 'Prótese Parcial', 'especialidade' => 'Prótese Dentária', 'duracao' => 90, 'preco' => 2200, 'ordem' => 5, 'descricao' => 'Prótese parcial removível para reposição de elementos dentários ausentes.'],
            ['slug' => 'protese-total', 'categoria' => 'Prótese', 'tipo' => 'procedimento', 'nome' => 'Prótese Total', 'especialidade' => 'Prótese Dentária', 'duracao' => 120, 'preco' => 2800, 'ordem' => 6, 'descricao' => 'Prótese total removível para reabilitação de arcada edêntula.'],

            // ── Ortodontia ─────────────────────────────────────────────────
            ['slug' => 'orto-documentacao', 'categoria' => 'Ortodontia', 'tipo' => 'procedimento', 'nome' => 'Documentação Ortodôntica', 'especialidade' => 'Ortodontia', 'duracao' => 45, 'preco' => 350, 'ordem' => 1, 'descricao' => 'Registro ortodôntico completo com fotos, modelos, radiografias e plano de tratamento.'],
            ['slug' => 'orto-instalacao-metalico', 'categoria' => 'Ortodontia', 'tipo' => 'procedimento', 'nome' => 'Instalação Aparelho Metálico', 'especialidade' => 'Ortodontia', 'duracao' => 90, 'preco' => 2200, 'ordem' => 2, 'descricao' => 'Instalação de aparelho ortodôntico fixo metálico com colagem de brackets e arqueamento inicial.'],
            ['slug' => 'orto-instalacao-estetico', 'categoria' => 'Ortodontia', 'tipo' => 'procedimento', 'nome' => 'Instalação Aparelho Estético', 'especialidade' => 'Ortodontia', 'duracao' => 90, 'preco' => 2800, 'ordem' => 3, 'descricao' => 'Instalação de aparelho ortodôntico estético em cerâmica ou safira.'],
            ['slug' => 'orto-manutencao-mensal', 'categoria' => 'Ortodontia', 'tipo' => 'procedimento', 'nome' => 'Manutenção Mensal', 'especialidade' => 'Ortodontia', 'duracao' => 30, 'preco' => 200, 'ordem' => 4, 'descricao' => 'Manutenção ortodôntica mensal com troca de elásticos, ajuste de arcos e controle de progresso.'],
            ['slug' => 'orto-remocao-aparelho', 'categoria' => 'Ortodontia', 'tipo' => 'procedimento', 'nome' => 'Remoção de Aparelho', 'especialidade' => 'Ortodontia', 'duracao' => 60, 'preco' => 450, 'ordem' => 5, 'descricao' => 'Remoção de aparelho fixo, limpeza de resíduos adesivos e instalação de contenção.'],

            // ── Radiologia ─────────────────────────────────────────────────
            ['slug' => 'radio-periapical', 'categoria' => 'Radiologia', 'tipo' => 'procedimento', 'nome' => 'Radiografia Periapical', 'especialidade' => 'Radiologia Odontológica', 'duracao' => 10, 'preco' => 50, 'ordem' => 1, 'descricao' => 'Radiografia periapical para avaliação de dente, raiz e estruturas periapicais.'],
            ['slug' => 'radio-panoramica', 'categoria' => 'Radiologia', 'tipo' => 'procedimento', 'nome' => 'Radiografia Panorâmica', 'especialidade' => 'Radiologia Odontológica', 'duracao' => 15, 'preco' => 120, 'ordem' => 2, 'descricao' => 'Exame radiográfico panorâmico para avaliação global das arcadas dentárias e estruturas ósseas.'],
            ['slug' => 'radio-tomografia', 'categoria' => 'Radiologia', 'tipo' => 'procedimento', 'nome' => 'Tomografia Cone Beam', 'especialidade' => 'Radiologia Odontológica', 'duracao' => 20, 'preco' => 450, 'ordem' => 3, 'descricao' => 'Tomografia computadorizada de feixe cônico para planejamento implantológico e endodôntico.'],

            // ── Estética ───────────────────────────────────────────────────
            ['slug' => 'estetica-clareamento-caseiro', 'categoria' => 'Estética', 'tipo' => 'procedimento', 'nome' => 'Clareamento Caseiro', 'especialidade' => 'Estética Dental', 'duracao' => 40, 'preco' => 800, 'ordem' => 1, 'descricao' => 'Clareamento dental caseiro supervisionado com moldeiras personalizadas e gel clareador.'],
            ['slug' => 'estetica-clareamento-consultorio', 'categoria' => 'Estética', 'tipo' => 'procedimento', 'nome' => 'Clareamento Consultório', 'especialidade' => 'Estética Dental', 'duracao' => 90, 'preco' => 1200, 'ordem' => 2, 'descricao' => 'Clareamento dental em consultório com gel de alta concentração e proteção gengival.'],
            ['slug' => 'estetica-clareamento-combinado', 'categoria' => 'Estética', 'tipo' => 'procedimento', 'nome' => 'Clareamento Combinado', 'especialidade' => 'Estética Dental', 'duracao' => 120, 'preco' => 1800, 'ordem' => 3, 'descricao' => 'Protocolo combinado de clareamento em consultório e manutenção caseira supervisionada.'],
        ];
    }
}