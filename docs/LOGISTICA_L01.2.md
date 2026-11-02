# L01.2 — Perfis logísticos e preparação de cotação

## Escopo e estado

A L01.2 persiste dados logísticos dos produtos e prepara uma solicitação interna
normalizada para uma futura cotação. Não chama Frenet ou Melhor Envio, não
confirma conectividade e não realiza pedidos, reservas, contratação ou etiquetas.
Sua migration ainda precisa ser aplicada explicitamente em cada ambiente.

## Perfil do produto

A tabela `product_shipping_profiles` tem uma relação 1:1 com `products`, por uma
chave estrangeira única. Excluir definitivamente um produto remove seu perfil
pela chave estrangeira; o soft delete do catálogo preserva o perfil. Produtos
anteriores sem linha de perfil continuam sem perfil e aparecem como pendentes.
Ausência de dados nunca significa automaticamente “não exige envio”.

`requires_shipping` registra a escolha explícita do administrador. Quando for
`true`, o produto pode guardar `weight_kg`, `height_cm`, `width_cm` e
`length_cm`. Valores aceitos: peso maior que zero até 1.000 kg, com três casas
decimais; dimensões maiores que zero até 300 cm, com duas casas. Não há pesos ou
dimensões presumidos. O produto pode ser salvo incompleto, mas não está apto à
preparação de cotação.

As colunas `package_weight_kg`, `package_height_cm`, `package_width_cm` e
`package_length_cm` guardam as medidas reais de uma embalagem para uma unidade.
Elas são distintas das medidas do produto. Não se calcula a embalagem somando
comprimento, largura ou altura dos produtos.

O formulário preserva o perfil quando uma atualização não inclui os campos de
envio. Campos omitidos preservam valores; valores explicitamente vazios limpam
a medida correspondente. Para novo perfil, é necessário selecionar de modo
explícito se há envio físico.

## Prontidão

O indicador administrativo tem estados pendente, não exige envio, medidas do
produto pendentes, medidas do pacote pendentes ou pronto para preparar uma
cotação MVP. “Pronto” significa apenas que os dados locais atendem à política
implementada. Não afirma que um provedor está conectado ou que existe preço de
frete.

## Preparação e unidades

`ShippingQuotePreparationService` recebe IDs e quantidades, carrega produtos
publicados e perfis no servidor e obtém preço efetivo persistido. Não confia em
medidas nem valores monetários enviados pelo navegador. O request interno
normaliza peso para gramas inteiras, dimensões para milímetros (arredondando
frações para cima para não subdimensionar) e valores para centavos inteiros.
CEPs são normalizados para oito dígitos. O provedor ativo e o CEP de origem vêm
das configurações L01.1.

A política `SingleUnitShippingPackagePreparer` suporta somente um produto
físico e uma unidade, usando uma embalagem explicitamente medida. Produtos
explicitamente configurados como sem envio são ignorados. Se não houver item
físico, o resultado é `not_required`. Perfil ausente, medidas incompletas,
provedor desativado, origem ausente e combinações ainda não suportadas retornam
estados indisponíveis com códigos de motivo. Mais de uma unidade física ou mais
de um produto físico ainda não pode ser empacotado com segurança.

## L01.3 e evolução

A próxima etapa deverá definir como o carrinho futuro representa linhas e
quantidades e como converte erros de preparação em mensagens ao usuário. Antes
de habilitar cotações reais, será necessário suportar embalagens e múltiplos
volumes, revisar limites por serviço e capacidades por provedor, implementar
adaptadores autenticados Frenet/Melhor Envio, timeouts e tratamento de falhas,
e escrever testes de contrato sem credenciais reais. Checkout e pagamentos
continuam etapas separadas; quando existirem, o servidor deverá recalcular o
frete e o total antes de criar pagamento.

## Migration

`2026_11_06_120000_create_product_shipping_profiles_table` cria somente a tabela
aditiva de perfis. Ela não preenche registros para produtos existentes e não
altera dados comerciais. Foi aplicada no banco local em 2026-11-02 após backup;
outros ambientes devem aplicar a migration explicitamente.
