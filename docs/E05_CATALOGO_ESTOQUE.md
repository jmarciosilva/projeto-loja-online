# E05 — Catálogo comercial e controle de estoque

## Escopo

E05 adiciona condição comercial genérica ao produto, saldo físico de um único
estoque e histórico auditável. Carrinho, pedidos, reservas, checkout e venda
continuam fora desta etapa.

## Modelo de dados

- `products.condition` aceita `new`, `used` ou `NULL`; `NULL` preserva a
  condição desconhecida de produtos cadastrados antes da E05.
- `products.condition_notes` guarda até 500 caracteres para conservação de
  itens usados.
- `product_stocks` é uma relação 1:1. `quantity_on_hand` é a contagem física,
  `quantity_reserved` é a parcela comprometida e `minimum_quantity` é o limite
  interno. `available = quantity_on_hand - quantity_reserved`.
- Produto antigo sem linha em `product_stocks` tem disponibilidade não
  informada. Produtos novos recebem configuração explícita: controle desligado
  também é apresentado como disponibilidade não informada.
- `stock_movements` registra delta assinado, saldo anterior/posterior, tipo,
  justificativa, responsável e referência opcional. Não há origem de pedido
  nesta etapa.
- `product_shipping_profiles` permanece separado e não é alterado.

## Operações administrativas

O saldo inicial é registrado uma vez. Depois, somente `StockService` altera o
saldo: entrada, saída manual, ajuste positivo ou negativo. Cada escrita usa
transação, bloqueio `FOR UPDATE`, validação do saldo/reservas e registro do
histórico na mesma transação. A saída manual usa somente o saldo disponível;
ajustes negativos também não reduzem abaixo do reservado. Quantidades aceitas
são inteiras, de 0 a 1.000.000 no saldo inicial e positivas nas movimentações.

`can_manage_stock` controla saldo e movimentações; `can_view_stock_movements`
permite consulta do histórico. Ambas começam em `false` e nenhuma permissão é
concedida automaticamente. A consulta também exige permissão de catálogo.
Produtos continuam editáveis sem que o formulário comercial atualize o saldo.

## Vitrine

Com controle habilitado, saldo disponível positivo aparece como “Disponível”;
exatamente uma unidade aparece como “Última unidade”; zero aparece como
“Esgotado”. Ausência de configuração ou controle desligado aparece como
“Disponibilidade não informada”. Produtos esgotados continuam publicados. A
vitrine não mostra quantidade exata, salvo a mensagem da última unidade.

## Migrations e rollback

1. `2026_11_07_120000_add_product_condition_and_stock_permissions.php` adiciona
   condição e duas permissões restritivas.
2. `2026_11_07_120100_create_product_stocks_and_stock_movements_tables.php`
   cria saldo único e histórico com chaves estrangeiras, índices e constraints.

As migrations são aditivas e não preenchem saldo de produto existente. Rollback
remove as tabelas de estoque primeiro e, em seguida, os campos de condição e
permissão; os dados cadastrados nessas estruturas serão removidos no rollback.
Revisar e fazer backup antes de qualquer rollback operacional. Na validação
local de 2026-11-02, ambas foram aplicadas exclusivamente ao schema
`loja_online`; nenhum saldo foi criado automaticamente.

## Validação e evolução

Os testes padrão usam SQLite em memória. Esse banco valida regras e atomicidade
sequencial, mas não prova concorrência de `lockForUpdate`. A prova concorrente
foi aprovada em MySQL 8.4 isolado, com duas saídas concorrentes para uma unidade:
uma saída foi registrada, a outra foi recusada, e os saldos físico e reservado
terminaram em zero. A suíte padrão mantém as provas MySQL-only do menu e do SKU
ignoradas sob SQLite; essas provas não foram executadas nesta validação. A
prova de estoque exige schema de teste isolado terminado em `_test` e nunca usa
`loja_online`.
Na E08, reservas deverão bloquear o registro de estoque dentro da transação e
incrementar `quantity_reserved` sem reduzir o físico; confirmação/conclusão
reduzirá o físico e liberará a reserva atomicamente, com idempotência ligada ao
pedido. Nenhuma reserva fictícia foi criada na E05.
