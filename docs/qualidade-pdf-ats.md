# Qualidade e apresentação do PDF ATS

Validação em 05/11/2026, na branch `feat/migrate-python-bot-to-laravel`.

## Resultado

O PDF enviado como referência tinha três páginas, títulos de projetos repetidos,
um link GitHub duplicado, seção de experiência vazia e uma última página com
apenas o idioma. A renderização da análise correspondente com o novo layout ficou
em uma página. A comparação utilizou os dados salvos, sem alterar o banco ou o
PDF original. Nenhum documento pessoal foi incluído nos testes versionados.

A apresentação agora utiliza uma coluna, texto selecionável, corpo de 10 pontos,
metadados de 9 pontos, margens explícitas e fontes DejaVu Sans incorporadas com
mapeamento Unicode. Não usa tabelas, imagens ou ícones para representar conteúdo.
Os títulos aparecem uma vez por seção; seções sem dados não são impressas.

Entradas curtas permanecem juntas. Entradas extensas podem atravessar páginas,
sem truncamento ou redução automática da fonte. Títulos de seção e de entradas
são protegidos contra separação do conteúdo seguinte. Não há limite artificial
de uma ou duas páginas: a extensão acompanha os fatos selecionados pelo usuário.

## Responsabilidades

- `ResumeLinks`: remove destinos repetidos; links informados pelo usuário
  prevalecem sobre variantes de capitalização produzidas pelo modelo.
- `ResumeDocumentPresenter`: prepara os dados apenas para exibição, sem persistir
  alterações e sem chamar IA. Consolida contatos, elimina entradas idênticas,
  separa formação de cursos e traduz termos de apresentação como `native`,
  `Current` e `years`. Níveis não padronizados, como C1, permanecem intactos.
- `resources/views/exports/ats.blade.php`: controla tipografia, espaçamento e
  paginação. Não contém regras de negócio ou interpretação semântica de fatos.
- `ResumeDocumentService`: continua responsável pelo arquivo, storage e nome
  único. O contrato com o frontend e o download autenticado permanecem iguais.
- `ResumePrompt` (`resume-v2`) e descrições do schema: orientam idioma consistente,
  tradução das descrições do GitHub, concisão e preservação de nomes e fatos.
  Datas não podem ser transportadas de formação para projetos; datas ausentes
  permanecem nulas. A data atual deixou de ser injetada no prompt de geração.

A deduplicação é conservadora. Cursos de instituições diferentes e projetos
com descrições distintas são preservados. Uma certificação só é suprimida quando
todos os seus fatos exibidos já constam em uma qualificação. Habilidades e idiomas
vazios não geram seções. Não se imprime nota ATS nem mensagens como “Sem experiência”.

Os rótulos de apresentação estão disponíveis em português e inglês, conforme
`RESUME_OUTPUT_LANGUAGE`; a configuração padrão permanece `pt-BR`.

## Dados e PDFs já existentes

O renderizador não traduz automaticamente textos livres já revisados ou salvos.
A prévia do currículo antigo mantém, por isso, seu headline e descrições existentes,
mesmo quando estão em inglês. A tradução dessas descrições é responsabilidade
da geração com o novo prompt ou da revisão pelo usuário.

Para aplicar o layout a um currículo já salvo, revise e finalize novamente para
gerar outro PDF. Para aplicar também as novas instruções de idioma, gere uma nova
análise e revise o resultado. O download continua entregando o arquivo armazenado;
não dispara IA nem regeneração implícita. Não houve atualização em massa de dados.

## Validação executada

| Caso | Resultado |
|---|---|
| Análise correspondente ao PDF enviado | 3 páginas no original → 1 página no novo layout |
| Estudante, cinco projetos, nenhuma experiência | 1 página, sem seção vazia e sem perder projetos |
| Profissional com seis experiências e dois projetos | 2 páginas, com todos os itens |
| Perfil com seis experiências, 24 projetos e 12 cursos adicionais | 6 páginas, sem cortar conteúdo |
| Um projeto com 100 linhas de descrição e URL extensa | 5 páginas; todas as linhas presentes uma vez e texto dentro das margens |
| GitHub em inglês com modelo local `qwen3:4b` | Descrições em português, nomes preservados, sem experiência inventada e sem inferir datas de projetos |

- Suíte determinística do backend: **266 testes aprovados, 9.354 assertions**;
  três testes de integração real ficam desabilitados por padrão.
- Regressão opt-in de idioma e datas com Ollama real: **1 teste aprovado,
  15 assertions**, em aproximadamente 38 segundos. Uma verificação anterior do
  prompt final levou aproximadamente 55 segundos e também gerou um PDF revisado.
- Após o último ajuste de compatibilidade de metadados, os sete testes de
  apresentação e normalização passaram novamente, com 8.584 assertions.
- Pint: **167 arquivos aprovados**; `git diff --check` aprovado.
- `pdftotext -layout`: conteúdo, acentos, títulos únicos, quantidade de páginas,
  ausência de títulos órfãos nos cenários e preservação de todas as entradas.
- `pdftotext -bbox`: coordenadas das palavras em uma entrada extensa para detectar
  texto cortado ou fora das margens. Essa verificação explica a quantidade de assertions.
- `pdffonts`: fontes normal e negrito incorporadas, com mapeamento Unicode.
- `pdftoppm`: inspeção visual das amostras, incluindo continuação e última página.
- Backend reconstruído e atualizado no Docker local; serviços saudáveis e
  acesso via proxy preservado. Os PDFs existentes não foram sobrescritos.

As fontes sintéticas ficam em `tests/Support/ResumePdfProfiles.php` e
`tests/Fixtures/ats_language_sources.json`. Os testes não contêm dados pessoais.

## Reprodução

No ambiente de testes PHP 8.4 com Poppler, execute:

```bash
vendor/bin/pest tests/Feature/Resume/ResumePdfPresentationTest.php
vendor/bin/pest tests/Unit/Services/Resume/ResumeDocumentPresenterTest.php
vendor/bin/pest tests/Unit/Services/Resume/ResumeLinksTest.php
vendor/bin/pint --test
```

Para o teste real, use o contêiner descartável descrito no
[guia de operação](migracao-llm-laravel.md#testes-reproduzíveis), com
`RUN_OLLAMA_INTEGRATION=1`, selecionando
`tests/Feature/Resume/LiveOllamaLanguageTest.php`. Somente o Ollama local é chamado.
Não foram realizadas chamadas pagas.

## Limites da validação

A inspeção visual e a extração textual verificam legibilidade e estrutura; não
constituem certificação de todos os ATS comerciais. A geração por IA continua
sujeita à revisão de fatos. O teste de idioma é uma regressão dirigida ao caso
encontrado, não um detector universal de idioma ou de alucinação.

As regras de quebra de página respeitam o suporte do renderizador:
[compatibilidade CSS do Dompdf](https://github.com/dompdf/dompdf/wiki/CSSCompatibility).
