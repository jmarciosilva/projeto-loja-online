# E05.3.2 — Refinamento comercial da vitrine

## Diagnóstico e arquitetura reutilizada

A etapa reaproveita os componentes públicos já existentes. O rodapé consulta o menu `footer` pelo `MenuService::publicTree()`, que elimina itens inativos e páginas em rascunho. A seção de exploração usa categorias públicas fornecidas pelo serviço existente. Nome, slogan e contatos continuam vindo das configurações públicas do CMS. O cadastro de produtos já usa a biblioteca de mídia, seleção de imagem principal e galeria de até seis mídias; não foi criado outro fluxo de upload.

A página de detalhes já possuía galeria, zoom, preço efetivo, condição, descrição sanitizada e disponibilidade do estoque. O ajuste reorganiza essas informações e utiliza texto alternativo baseado no nome do produto, sem expor nomes de arquivo da biblioteca.

## Rodapé institucional

O rodapé agora combina a identidade da loja, links institucionais publicados pelo menu do CMS, navegação para catálogo/categorias públicas e atendimento apenas quando existem dados configurados. Telefone recebe `tel:` somente quando pode ser normalizado com segurança; e-mail usa `mailto:`. Endereço permanece texto. Não são mostrados canais sociais ou contatos que o CMS não fornece.

A grade adapta o número de colunas à presença de navegação e contato. O conteúdo institucional curto existente continua sendo usado; nenhum histórico, garantia ou promessa comercial foi inventado.

## Fotografias e placeholders

O formulário administrativo mantém o fluxo atual da biblioteca de mídia, pré-visualização, escolha da principal, ordenação e remoção da galeria. Foi acrescentada uma orientação não obrigatória para livros usados: fotografar capa frontal, contracapa, lombada e marcas de uso. A recomendação não é aplicada a outras categorias.

No storefront, imagens mantêm proporção sem distorção. A galeria do produto conserva o fallback atual “Imagem em breve”. Textos alternativos identificam o produto e a posição da imagem, em vez de expor o nome interno do arquivo. Nenhuma imagem ou registro de produto foi substituído.

## Detalhes do produto

A página organiza categoria, nome, referência, condição, disponibilidade, conservação, preço efetivo e descrição em grupos visuais. Dados vazios continuam ocultos. O contato só aparece quando há e-mail ou telefone válido configurado, sem antecipar carrinho, pagamento, frete ou qualquer ação comercial ainda inexistente. Os estados de estoque continuam derivados do serviço/modelo existente.

## Seleção dos produtos em destaque

`ProductService::homepageSelection()` mantém primeiro os produtos destacados manualmente, na ordenação administrativa existente. As vagas restantes são preenchidas por produtos públicos com controle de estoque habilitado e saldo disponível positivo, ordenados por criação mais recente e ID decrescente. Se ainda houver vagas, usa os produtos públicos recentes restantes, inclusive esgotados ou sem estoque informado. IDs já selecionados são excluídos em cada consulta; nenhuma flag é alterada.

O limite padrão continua em cinco. A seleção carrega categoria, mídia, galeria e estoque antecipadamente. Se a tabela de estoque não existir em um contexto legado, a consulta de disponíveis retorna vazia e o fallback público continua operando.

## Validações e limitações

Os testes de feature cobrem seleção manual e automática, desempate determinístico, produtos esgotados e sem estoque informado, ausência de duplicação, galeria/fallback, conteúdo alternativo, publicação de páginas do rodapé e contatos configurados. A suíte completa, Pint, testes JavaScript e build devem ser registrados no relatório da execução.

Não havia navegador ou ferramenta de captura disponível nesta execução. Portanto, a validação visual automatizada não foi declarada. Conferir manualmente a home, catálogo e detalhe de produto em 360×800, 390×844, 412×915, 768×1024, 1024×768 e 1440×900, especialmente distribuição do rodapé, imagens e CTA de contato.

Nenhuma migration ou seeder foi executado; o banco principal não foi alterado. Não houve staging, commit ou push.
