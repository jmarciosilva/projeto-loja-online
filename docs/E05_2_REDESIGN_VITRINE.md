# E05.2 — Redesign da vitrine

## Objetivo

Reorganizar a experiência pública para facilitar a descoberta de produtos em desktop e mobile, mantendo os dados e as regras existentes de publicação, preço, promoção, categoria, mídia e estoque.

## Referência de experiência

A leitura da página pública do Enjoei serviu apenas como referência de princípios de UX: agrupamento de produtos em seções de descoberta, categorias fáceis de localizar e leitura rápida de imagem e preço. A identidade visual, conteúdo, imagens e implementação da UAU NetShopping permanecem próprios.

## Implementação

- O cabeçalho apresenta busca GET funcional para o catálogo e mantém a navegação CMS e categorias públicas. Visitantes recebem o acesso administrativo; usuários autenticados sem permissão de catálogo não veem esse acesso.
- A home dá prioridade ao carrossel existente, às categorias disponíveis e a produtos reais. Quando não há produtos destacados, usa produtos públicos recentes sem alterar o marcador de destaque. Seções adicionais aparecem apenas para categorias com produtos e evitam repetir os itens da seleção principal.
- Os cards usam uma proporção uniforme, imagem sem distorção, preço em destaque, condição resumida e disponibilidade textual.
- Catálogo e categorias oferecem busca, categoria, condição, intervalo de preço, disponibilidade e ordenação. Os parâmetros GET são validados e preservados na paginação.
- A busca consulta nome, descrição e SKU exclusivamente em produtos e categorias publicamente visíveis.
- Ordenação por preço usa o preço promocional somente quando a promoção está ativa no momento da consulta.
- A página de detalhes mantém a galeria e o zoom existentes, com breadcrumb, condição de conservação e preço em hierarquia visual mais clara.

## Regras preservadas

As consultas continuam partindo do escopo `publiclyVisible()`. Produtos arquivados, inativos ou em categorias inativas não entram no catálogo. Disponibilidade continua derivada do saldo persistido; produto sem estoque configurado não é anunciado como disponível. Nenhuma ação de compra foi adicionada.

Nenhuma migration, seeder, alteração de banco, saldo ou produto foi executada nesta etapa.

## Validação

Os testes de feature cobrem busca por nome/descrição/SKU, exclusão de produto arquivado, filtros por condição e disponibilidade, ordenação pelo preço efetivo e estrutura responsiva renderizada. Testes Laravel e JS, Pint, build Vite e `git diff --check` devem ser executados antes da revisão.

O ambiente desta execução não disponibiliza navegador automatizado; portanto, as viewports não foram aprovadas visualmente por automação. URLs locais para inspeção manual:

- `/`
- `/produtos`
- `/categorias/livros-usados`
- `/categorias/livros-de-fantasia`
- `/produtos/o-hobbit`

Inspecionar em 360, 390, 412, 768, 1024 e 1440 px. O catálogo demonstrativo pode ter sido alterado depois da criação desta documentação; confirme os slugs disponíveis no ambiente local.

## Limites conhecidos

- A busca usa `LIKE` do banco atual e é apropriada ao catálogo inicial; busca tolerante a erros, relevância textual avançada e indexação especializada podem ser avaliadas quando o catálogo crescer.
- Não há conta de cliente, carrinho, checkout ou compra; o cabeçalho não apresenta ações que simulem esses recursos.
- Imagens demonstrativas existentes são mantidas e não foram substituídas por capas externas.
