# E04 — Identidade visual e diagnóstico da jornada

## Identidade e configuração

O CMS permanece como fonte dos dados visuais: nome e slogan em `site.name` e `site.slogan`; logo e favicon por referências a `Media.id`; cores em `theme.primary_color`, `theme.secondary_color` e `theme.accent_color`; páginas, menus e banners em seus módulos existentes. Os defaults de nome, slogan e tema são aplicados na leitura e não são gravados automaticamente.

O nome e slogan iniciais da marca são **UAU NetShopping** e **Variedade que surpreende!**. A tela `/admin/configuracoes` permite editar ambos. A identidade de imagem permanece configurável em `/admin/configuracoes/identidade-visual`, e as cores em `/admin/configuracoes/tema`.

## Logotipo e favicon

O arquivo oficial fornecido, `Logo UAU NetShopping Vibrante.png`, é PNG RGB de 1536 × 1024 pixels (aprox. 1,3 MB). O original foi preservado sem alteração e não é necessário em runtime. `public/images/uau-netshopping-logo.webp` é a versão WebP completa, derivada com margem de segurança e redimensionada proporcionalmente para 1200 × 741; é usada como imagem Open Graph de fallback. `public/images/uau-netshopping-logo-header.webp` é a versão WebP sem o slogan incorporado, 1200 × 642, usada como fallback no cabeçalho e rodapé, onde o slogan configurável é texto HTML. O CMS continua tendo precedência quando uma logo está configurada.

O favicon não foi gerado. O CMS aceita favicon PNG, mas a arte completa não oferece uma versão pequena legível sem extrair o símbolo. É necessário preparar e revisar uma variante aprovada antes de configurá-la.

## Home, conteúdo e tema

A home usa banners ativos, categorias públicas e produtos em destaque reais. Categorias já listadas no menu principal não são repetidas no menu de categorias do cabeçalho. Estado sem conteúdo é apresentado sem ofertas, descontos, produtos ou contatos inventados. O rodapé só apresenta links já publicados no menu `footer` e contatos cadastrados.

O tema usa defaults azul-marinho `#092B57`, cinza `#475569` e laranja `#FF6900`; o fundo claro usa `#F5F7FA`. Laranja não é usado como cor de texto em fundo branco, pois o contraste é insuficiente para texto normal. No banco local já havia cores personalizadas (`#3F5B4B`, `#737567`, `#C47B58`). Elas foram preservadas; para usar a nova paleta nesse ambiente, o proprietário pode salvá-la em `/admin/configuracoes/tema`.

No banco local, o banner de roupas demonstrativo original foi preservado, mas está inativo; três banners comerciais foram cadastrados manualmente e estão ativos. Essa alteração de dados local não faz parte do commit. O logotipo CMS, favicon, nome/slogan e cores também dependem de configuração administrativa explícita em cada ambiente.

## SEO e acessibilidade

A home tem descrição, metadados Open Graph e URL canônica; as páginas públicas usam título escapado e URL canônica. Páginas CMS publicadas continuam fornecendo seus títulos e meta descriptions existentes. `public/robots.txt` existe e permite rastreamento; não foi encontrada implementação de sitemap.

O cabeçalho mantém menu CMS, categorias, link real para catálogo e o acesso administrativo já corrigido. Não foram adicionados busca, conta de cliente ou carrinho, pois não existem rotas funcionais para essas ações. Foram preservados controles mobile, rótulos acessíveis, estados de foco, dimensões intrínsecas e carregamento lazy nas imagens dos cards.

### E04.1 — Banners e retorno ao topo

Na home, banners elegíveis continuam vindo de `BannerService::activeForPosition(BannerPosition::Hero)`, com `is_active` e ordem do CMS. O schema atual não possui datas de publicação, título ou descrição pública: `name` é identificador administrativo, e `alt_text` é o texto alternativo mostrado ao leitor de tela. O carrossel usa somente imagem, texto alternativo e destino já configurados. Um banner é estático; dois ou mais recebem setas, indicadores e pausa/retomada. O avanço automático usa cinco segundos, pausa ao receber foco ou hover e é desativado para `prefers-reduced-motion`. Nenhuma migration ou configuração de banner foi criada.

O botão flutuante “Voltar ao topo” pertence somente ao layout público. Aparece depois de 400 px, permanece acessível até o fim da página e usa rolagem suave respeitando movimento reduzido. Seu posicionamento respeita as áreas seguras móveis e reserva espaço após o conteúdo do rodapé.

### E04.2 — Imagens de banner e preparação para novos ambientes

As imagens aprovadas e otimizadas para banners estão versionadas em `public/images/banners/` como arquivos-fonte para upload manual. Cada arquivo tem 2000 × 750 pixels e formato WebP. Os banners ativos da instalação local referenciam linhas `media` e arquivos em `storage/app/public/media`; esses registros e arquivos de armazenamento local não são transportados pelo Git. Não existe seeder automático para banners.

Em uma instalação nova, acesse **Administração → Mídia** e envie `uau-livros.webp`, `uau-las-e-linhas.webp` e `uau-eva-artesanato.webp` a partir de `public/images/banners/`. Depois, em **Administração → Banners**, crie um banner para cada mídia, usando posição `Hero`, ordem desejada, status ativo, texto alternativo descritivo e link vazio até existir um destino comercial apropriado. Isso cria as referências necessárias pela interface existente; não é preciso executar um seeder. O sistema não ativa automaticamente essas imagens apenas por estarem no repositório.

### E04.3 e E04.4 — Controles e padronização visual

Os controles do carrossel usam tokens de tema, setas e pausa acessíveis, indicadores com estado ativo, controles com foco visível e área de toque mínima. Em mobile, a área da imagem mantém a proporção 16:6 das fotografias, sem corte ou distorção. O botão Voltar ao topo conserva o posicionamento com safe-area.

As cores base do tema são `#092B57` (primária), `#475569` (secundária), `#FF6900` (destaque) e `#F5F7FA` (fundo). O layout administrativo usa os tokens base institucionais; a vitrine continua usando as cores persistidas pelo CMS. Neste banco local estão salvas `#3F5B4B`, `#737567` e `#C47B58`. Para aplicar a paleta oficial neste ambiente, acesse `/admin/configuracoes/tema` e salve Cor primária `#092B57`, Cor secundária `#475569` e Cor de destaque `#FF6900`. Nenhuma atualização automática dessas configurações acompanha o commit.

## Diagnóstico da jornada de compra

| Módulo | Estado | Evidência no código | Pendência |
|---|---|---|---|
| Catálogo e detalhes | Implementado | Rotas `/produtos`, `/produtos/{slug}`, `/categorias/{slug}`, `CatalogController`, `ProductService` | A busca não está implementada; disponibilidade representa publicação, não quantidade de estoque |
| Carrinho | Não implementado | Não há rotas, models ou serviços de carrinho | Criar carrinho e validação de quantidade/estoque |
| Clientes e endereços | Não implementado | Nenhum módulo de conta/endereço além da autenticação administrativa | Criar identidade de cliente e endereços |
| Pedidos e reserva de estoque | Não implementado | Não há models/migrations de pedido ou estoque | Definir consistência, reserva e histórico |
| Checkout | Não implementado | Não há fluxo de identificação, entrega e confirmação | Depende de cliente, carrinho, pedido e frete |
| Mercado Pago | Não implementado | Não existem adaptadores, webhooks ou preferência de pagamento | Integrar depois da criação de pedido e total autoritativo |
| Configuração logística | Implementado parcialmente | L01.1: provedores, credenciais cifradas, seleção e CEP administrativo | Ainda sem chamada real ao provedor |
| Preparação de cotação | Implementado parcialmente | L01.2: perfil por produto, DTOs e `ShippingQuotePreparationService` | Política atual suporta apenas uma unidade/pacote simples; cotação real depende dos adaptadores Frenet/Melhor Envio |
| Etiqueta e rastreamento | Não implementado | Não há modelos ou operações de envio | Depende de contratação real de frete e pedido |

## Sequência recomendada

1. Revisar catálogo, detalhes, atributos e modelo de estoque.
2. Implementar carrinho persistente para visitantes e clientes.
3. Implementar cadastro de clientes e endereços.
4. Implementar pedidos, itens, reserva e liberação de estoque.
5. Implementar checkout e total calculado no servidor, integrando cotação disponível.
6. Integrar Mercado Pago com idempotência e webhooks autenticados.
7. Implementar cotações reais, depois compra de frete/etiquetas.
8. Implementar pós-venda, estados do pedido e rastreamento.

Esses itens são diagnóstico e planejamento; não foram implementados na E04.

## Limites da validação desta entrega

Os testes automatizados cobrem renderização e regressões. Não havia navegador ou ferramenta visual disponível nesta sessão, portanto não foram inspecionados os viewports 360, 390, 768, 1024 e 1440 px. A conferência manual de layout e configuração CMS permanece necessária antes da publicação. Nenhuma migration, seeder ou alteração de dados comerciais foi executada.
