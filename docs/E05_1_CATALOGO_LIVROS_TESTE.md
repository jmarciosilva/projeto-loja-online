# E05.1 — Catálogo demonstrativo de livros usados

## Objetivo e escopo

Este catálogo serve exclusivamente para testar localmente categorias hierárquicas, disponibilidade e movimentações da E05. Os títulos e autores identificam obras reais, mas os registros de produto, preços, exemplares e quantidades são demonstrativos e não representam oferta ou estoque real da loja. Nenhum ISBN, edição, editora ou ano foi informado.

## Categorias

| Nome | Slug | Relação |
|---|---|---|
| Livros Usados | `livros-usados` | Principal |
| Livros de Informática | `livros-de-informatica` | Livros Usados |
| Livros de Fantasia | `livros-de-fantasia` | Livros Usados |
| Livros de Literatura | `livros-de-literatura` | Livros Usados |

A hierarquia atual permite uma categoria principal e um nível de subcategorias. A categoria principal lista produtos associados diretamente a ela e às subcategorias ativas.

## Catálogo e saldos de teste

Todos os produtos são publicados, condição `used`, sem promoção e com perfil de envio físico ainda incompleto. O serviço não inventa peso ou dimensões; por isso não estão aptos a uma cotação logística.

| Produto / autor | SKU | Preço demo | Conservação | Saldo inicial |
|---|---|---:|---|---:|
| Código Limpo — Robert C. Martin | `DEMO-LIV-INFO-001` | R$ 49,90 | Bom estado, leves marcas de manuseio. | 1 |
| O Programador Pragmático — Andrew Hunt e David Thomas | `DEMO-LIV-INFO-002` | R$ 59,90 | Muito bom estado, páginas preservadas. | 2 |
| Use a Cabeça! Java — Kathy Sierra e Bert Bates | `DEMO-LIV-INFO-003` | R$ 39,90 | Marcas de uso na capa, miolo preservado. | 0 |
| O Hobbit — J. R. R. Tolkien | `DEMO-LIV-FANT-001` | R$ 29,90 | Bom estado geral. | 1 |
| Harry Potter e a Pedra Filosofal — J. K. Rowling | `DEMO-LIV-FANT-002` | R$ 24,90 | Pequenas marcas de manuseio. | 3 |
| O Leão, a Feiticeira e o Guarda-Roupa — C. S. Lewis | `DEMO-LIV-FANT-003` | R$ 19,90 | Estado regular, capa com sinais de uso. | 0 |
| Dom Casmurro — Machado de Assis | `DEMO-LIV-LIT-001` | R$ 14,90 | Bom estado, páginas levemente amareladas. | 1 |
| 1984 — George Orwell | `DEMO-LIV-LIT-002` | R$ 27,90 | Muito bom estado. | 2 |
| A Metamorfose — Franz Kafka | `DEMO-LIV-LIT-003` | R$ 17,90 | Bom estado de conservação. | 1 |

Cada saldo é criado via `ProductService` e `StockService`, com motivo `Saldo inicial demonstrativo — E05.1`, usuário administrador local executor e referência `local_demo_catalog` + SKU. A repetição confirma os registros e saldos; se algum valor divergir, o comando falha sem reajustá-lo.

## Imagens

As imagens são placeholders tipográficos/geométricos próprios, gerados localmente pelo comando. Cada uma exibe o nome do título, `IMAGEM DEMONSTRATIVA` e o aviso de que não representa capa ou edição. Não foram baixadas capas nem usadas artes de terceiros. O comando as processa pelo serviço de mídia existente e armazena registros e arquivos no disco `public`, em caminhos opacos `media/AAAA/MM/...`; o texto alternativo público é o nome do produto.

## Hierarquia de categorias

A migration aditiva `2026_11_07_120200_add_parent_category_id_to_categories_table` adiciona `categories.parent_category_id`, índice de navegação e chave estrangeira autorreferente com exclusão física restrita. Categorias já existentes permanecem como principais porque o novo campo aceita `NULL`. A exclusão administrativa continua lógica.

## Execução local explícita

O comando `php artisan loja:catalogo-demo-livros` exige `APP_ENV=local`, driver MySQL e schema `loja_online`, além de exatamente um administrador com permissões de catálogo e estoque. Não é chamado por `DatabaseSeeder` nem executado automaticamente. Ele cria somente dados ausentes que passem nas verificações de compatibilidade. A categoria e o produto demonstrativos de camisetas são retirados da vitrine por soft delete apenas se corresponderem aos registros esperados, não tiverem saldos/movimentos e não houver tabelas de pedidos/carrinho; as mídias são preservadas.

Para reproduzir em um banco local vazio, aplique as migrations normais após configurar um ambiente local seguro, conceda permissões administrativas conforme o procedimento de bootstrap aprovado e execute explicitamente `php artisan loja:catalogo-demo-livros`. Não execute esse comando em produção ou homologação. No banco da tarefa, a migration E05.1 e o comando foram executados após backup, e o comando poderá validar a idempotência.

## Uso dos dados

Antes de uma publicação real, o administrador deve substituir títulos demonstrativos por exemplares efetivamente disponíveis, conferir edição e condição, cadastrar imagens próprias/licenciadas, informar preço comercial e realizar contagem física. As imagens placeholder não devem ser apresentadas como fotografias de produtos reais.
