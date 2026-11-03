# E05.3 — Nova vitrine marketplace

## Referência visual

O mockup local `Mockup da UAU NetShopping.png` foi aberto e analisado visualmente antes das alterações. Ele apresenta uma faixa institucional azul-marinho, cabeçalho comercial com busca, navegação secundária, hero horizontal com texto e CTA, faixa de benefícios, quatro categorias em cartões e cinco produtos em destaque. A versão mobile usa busca própria, hero compacto, categorias em quatro colunas e produtos em duas.

O mockup permaneceu intacto e não foi adicionado ao Git.

## Decisões de interface

- O cabeçalho agora tem faixa institucional com mensagens verificáveis sobre navegação e informações de produtos. Não anuncia entrega nacional, pagamento seguro, conta de cliente, carrinho ou favoritos.
- Desktop utiliza faixa, logo/slogan, busca central e navegação comercial. Em telas abaixo de `lg`, o cabeçalho usa menu recolhível, logo centralizado e busca em linha própria.
- O hero reutiliza banners ativos do CMS e seus links, imagens, textos alternativos e ordem. Como o modelo de banner não possui título e subtítulo, a composição usa o slogan configurável do CMS e uma chamada genérica para o catálogo; nenhum texto é gravado no banco.
- Sem banner elegível, a home mostra um hero neutro com o slogan e link real para o catálogo.
- A faixa de benefícios descreve funcionalidades existentes: consultar informações do produto, navegar por busca/categorias e descobrir produtos publicados. Ela aparece em tablet/desktop; o mobile preserva a composição compacta do mockup.
- Categorias principais e subcategorias são apresentadas como cartões lado a lado, com ícones SVG, fundos suaves e contagem agregada de produtos publicados. A raiz soma produtos diretos e de suas subcategorias. A consulta de contagem é agrupada, sem N+1.
- Home e catálogo podem mostrar cinco produtos por linha em telas largas; tablets usam quatro/três colunas e celulares duas. Os cards preservam imagens completas com `object-contain`, preço efetivo, condição e disponibilidade.
- A página individual mantém a galeria, zoom, dados comerciais, conservação e disponibilidade existentes.

## Diferenças justificadas em relação ao mockup

- Os ícones de conta, carrinho e favoritos não foram adicionados porque essas funções não existem.
- Promessas de entrega e pagamento seguro foram omitidas porque ainda não foram implementadas e validadas.
- O hero não recebe texto específico por banner porque o CMS atual só armazena imagem, posição, link, texto alternativo, ordem e estado. O slogan do CMS fornece a identidade, enquanto a chamada genérica leva ao catálogo.
- Categorias usam ícones vetoriais correspondentes a nomes conhecidos e um ícone neutro para futuras categorias, sem alterar a hierarquia persistida.

## Acessibilidade e desempenho

Busca tem labels visíveis para leitores de tela, botões têm nomes acessíveis, o menu mobile atualiza `aria-expanded`, fecha por Escape e devolve foco ao acionador ao fechar pelo botão. Os controles existentes do carrossel preservam pausa, autoplay, navegação e `prefers-reduced-motion`. Imagens mantêm dimensões, prioridade adequada e lazy loading fora do primeiro banner. Contagens de categorias são agregadas em uma consulta.

## Validação e limites

Os testes Laravel validam renderização, links, conteúdo, contagem pública das categorias e controles do carrossel; testes JavaScript e build verificam os componentes existentes. Eles não comprovam composição visual por viewport. A sessão não disponibiliza navegador automatizado para capturas; a comparação final nos tamanhos 360×800, 390×844, 412×915, 768×1024, 1024×768 e 1440×900 continua pendente de conferência manual.

Nenhuma migration, seed, gravação no banco, alteração de `.env`, estoque ou dado comercial faz parte desta etapa.
