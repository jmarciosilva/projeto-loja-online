# E05.3.1 — Polimento final da vitrine

## Objetivo

Refinar quatro áreas públicas da UAU NetShopping sem alterar catálogo, banners,
configurações ou regras comerciais persistidas: hero mobile, preenchimento dos
produtos em destaque, consistência dos cards e rodapé institucional.

## Alterações

- O hero continua usando uma única instância Alpine e os banners elegíveis do
  `BannerService`, na ordem do CMS. Em telas menores que `lg`, a imagem mantém
  sua proporção em um bloco próprio e o título, texto e CTA aparecem em painel
  claro separado. As setas ficam posicionadas sobre a área da imagem. Em desktop,
  o texto retorna à composição sobre a fotografia com gradiente. Banner único
  não recebe controles; o fallback sem banners permanece disponível.
- A seleção inicial da home agora limita o resultado a cinco produtos: primeiro
  entram os destaques explícitos, ordenados pela regra existente (`sort_order`,
  depois ID); os espaços restantes são preenchidos pelos produtos públicos mais
  recentes, com desempate por ID decrescente. IDs já escolhidos são excluídos,
  e a seleção não grava nem altera flags. O título permanece “Produtos em
  destaque” quando há destaque explícito; caso contrário, usa “Novidades no
  catálogo”.
- Os cards compartilhados por home, catálogo e categorias reservam a mesma área
  de imagem 4:3, centralizam as imagens sem distorcê-las, limitam títulos a duas
  linhas e reservam espaço consistente para condição, preço e disponibilidade.
  Imagens permanecem lazy-loaded e dimensões declaradas continuam reduzindo CLS.
- O rodapé usa o azul institucional configurável do tema, apresenta o logotipo
  sobre fundo branco, slogan e texto institucional neutro. A seção Institucional
  só é exibida quando o menu público de rodapé contém destinos; Atendimento só é
  exibido para contato configurado. Não são adicionados canais ou promessas.

## Fontes de dados

- Banners: `BannerService::activeForPosition()` e mídia existente.
- Destaques e complementos: `ProductService`, com o escopo `publiclyVisible()`.
- Marca e contatos: `StorefrontBrandingService` e identidade visual do CMS.
- Links institucionais: árvore pública de rodapé do `MenuService`.
- Cores: tokens runtime do tema, sem atualização de configurações persistidas.

## Testes

Foram adicionados testes para seleção de destaques com complementos, limite,
ordem determinística, produtos arquivados/não publicados, estados de cards,
estrutura responsiva do hero, controles de banner e navegação/contato do rodapé.
Nesta execução, a suíte Laravel terminou com 930 testes aprovados, nenhum falho
e 8 ignorados. Os ignorados incluem verificações de concorrência que exigem
MySQL de testes isolado. Os testes JavaScript (`npm run test:js`) passaram: 4
testes. Pint passou em 198 arquivos; o build Vite passou; `git diff --check`
passou. O comando `npm test` não existe no `package.json`; foi usado o script
existente `test:js`.

## Validação visual

O mockup aprovado foi inspecionado antes das alterações. Não havia navegador
automatizado disponível nesta execução; portanto, a disposição visual final não
foi certificada por captura. Recomenda-se revisar `/`, `/produtos`,
`/categorias/livros-usados` e `/produtos/o-hobbit-demo` em zoom 100%, nos tamanhos
360×800, 390×844, 412×915, 768×1024, 1024×768 e 1440×900.

## Limites

Não foram executadas migrations, seeders, alterações do banco, staging, commits
ou push. Funcionalidades de carrinho, checkout, pagamento, favoritos e avaliações
continuam fora de escopo.
