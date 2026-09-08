<?php

namespace Tests\Feature;

use App\Enums\MenuItemType;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Services\MenuService;
use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * Provas que só o MySQL real sustenta — F2.6-A.
 *
 * Duas coisas moram aqui, e as duas pelo mesmo motivo: nenhuma delas é
 * verificável no SQLite em memória da suíte padrão. A **concorrência** exige
 * processos simultâneos com transações e locks de verdade; a **atomicidade da
 * exclusão** exige uma escrita real desfeita por um rollback real.
 *
 * Na atomicidade, o primeiro `DELETE` da árvore executa de fato, dentro da
 * transação aberta por `deleteMenu()`, e quem o desfaz é o rollback do InnoDB —
 * não uma limpeza do teste. A falha é injetada pelo listener de
 * `QueryExecuted`, no instante em que esse `DELETE` retorna com sucesso.
 * Um trigger `BEFORE DELETE` com `SIGNAL` seria o caminho mais direto, mas o
 * servidor roda com `log_bin = 1` e `log_bin_trust_function_creators = 0` e o
 * usuário da aplicação não tem `SUPER`: `CREATE TRIGGER` falha com o erro 1419.
 * Nenhuma configuração de infraestrutura foi alterada para contornar isso.
 *
 * Na concorrência não há simulação: cada worker é um **processo próprio**, com
 * a sua conexão ao MySQL, chamando o `MenuService` de verdade. O que se prova é
 * que duas criações simultâneas no mesmo grupo de irmãos nunca terminam com o
 * mesmo `sort_order`.
 *
 * O caso crítico é o **grupo vazio**. O alvo natural do bloqueio não existe
 * ainda — não há irmão nenhum para travar —, e travar o intervalo vazio recai
 * sobre um gap lock, que é compatível com outro gap lock igual: as duas
 * transações passariam pela leitura e calculariam a mesma ordem, ou colidiriam
 * em deadlock na inserção. A âncora em `menus` resolve isso bloqueando uma
 * linha que sempre existe.
 *
 * Estes testes **não** usam `RefreshDatabase`: a transação que ele mantém
 * aberta esconderia as fixtures dos outros processos, que só enxergam o que já
 * foi commitado.
 */
class MenuConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    /**
     * Repetições por cenário.
     *
     * Uma corrida perdida uma vez é ruído; um contrato que se sustenta em
     * dezenas de tentativas é evidência. O número é alto o bastante para o
     * falso positivo ficar improvável e baixo o bastante para a suíte continuar
     * utilizável.
     */
    private const ITERATIONS = 20;

    /**
     * Processos concorrentes por rodada.
     */
    private const WORKERS = 2;

    /**
     * Mensagem da falha injetada na prova de atomicidade.
     */
    private const FAILURE_MESSAGE = 'forced rollback probe';

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Estas provas exigem o MySQL canônico do projeto.');
        }

        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Estas provas exigem a extensão pcntl para os processos concorrentes.');
        }
    }

    public function test_two_processes_creating_the_first_root_item_never_share_the_order(): void
    {
        // Cenário A — grupo raiz vazio: o caso em que não existe irmão para
        // bloquear.
        $this->assertOrdersAreUniquePerRound(
            fn (Menu $menu): ?MenuItem => null,
        );
    }

    public function test_two_processes_creating_a_root_item_in_a_populated_group_never_share_the_order(): void
    {
        // Cenário B — grupo raiz já com 1 e 2 gravados.
        $this->assertOrdersAreUniquePerRound(
            function (Menu $menu): ?MenuItem {
                $this->service()->createItem($menu, $this->payload('Existente 1'));
                $this->service()->createItem($menu, $this->payload('Existente 2'));

                return null;
            },
            expectedExisting: 2,
        );
    }

    public function test_two_processes_creating_the_first_child_of_a_parent_never_share_the_order(): void
    {
        // Cenário C — grupo de filhos vazio, sob um pai que existe.
        $this->assertOrdersAreUniquePerRound(
            fn (Menu $menu): ?MenuItem => $this->service()->createItem($menu, $this->payload('Pai')),
        );
    }

    public function test_two_processes_creating_a_child_in_a_populated_group_never_share_the_order(): void
    {
        // Cenário D — grupo de filhos já com 1 e 2 gravados.
        $this->assertOrdersAreUniquePerRound(
            function (Menu $menu): ?MenuItem {
                $parent = $this->service()->createItem($menu, $this->payload('Pai'));
                $this->service()->createItem($menu, $this->payload('Filho 1', $parent->id));
                $this->service()->createItem($menu, $this->payload('Filho 2', $parent->id));

                return $parent;
            },
            expectedExisting: 2,
        );
    }

    // --- Atomicidade da exclusão --------------------------------------------

    public function test_a_failure_after_a_partial_delete_rolls_the_whole_tree_back(): void
    {
        // A árvore é uma cadeia, para que a remoção tenha camadas distintas:
        //
        // A
        // └── B
        //     └── C
        //
        // A ordem contratada é C, depois B, depois A. A falha é injetada
        // **depois** que a remoção de C já executou dentro da transação — é
        // essa escrita parcial que o rollback precisa desfazer.
        //
        // O que é real aqui: a exclusão de C acontece de verdade, no MySQL,
        // dentro da transação aberta por `deleteMenu()`; e o rollback é o
        // rollback do InnoDB, não uma limpeza do teste. Esta classe não usa
        // `RefreshDatabase`, então a transação da operação é a de nível
        // superior — não um savepoint aninhado em outra.
        //
        // O que é simulado: apenas a origem do erro — o motivo está no
        // cabeçalho da classe.
        $menu = $this->service()->createMenu(['name' => 'Rollback', 'code' => 'rollback']);
        $a = $this->service()->createItem($menu, $this->payload('A'));
        $b = $this->service()->createItem($menu, $this->payload('B', $a->id));
        $c = $this->service()->createItem($menu, $this->payload('C', $b->id));

        $deleted = [];

        // O listener é desarmado por uma variável própria, e **não** removido do
        // dispatcher: `forget(QueryExecuted::class)` apagaria todos os ouvintes
        // do evento, inclusive os que não pertencem a este teste. Desarmado, ele
        // continua registrado até o fim do processo do PHPUnit, mas inerte.
        $armed = true;

        DB::listen(function (QueryExecuted $query) use (&$deleted, &$armed): void {
            if (! $armed) {
                return;
            }

            // O evento só é emitido para consultas que executaram com sucesso:
            // o que chega aqui já é uma escrita concluída dentro da transação.
            if (! str_starts_with(mb_strtolower($query->sql), 'delete from') || ! str_contains($query->sql, 'menu_items')) {
                return;
            }

            preg_match_all('/\d+/', $query->sql, $matches);
            $deleted[] = array_map('intval', $matches[0]);

            throw new RuntimeException(self::FAILURE_MESSAGE);
        });

        try {
            $this->service()->deleteMenu($menu);

            $this->fail('A exclusão deveria ter falhado depois da primeira camada.');
        } catch (RuntimeException $exception) {
            $this->assertSame(self::FAILURE_MESSAGE, $exception->getMessage());
        } finally {
            // Desarmado aconteça o que acontecer — inclusive se uma asserção
            // acima falhar —, para não derrubar os testes seguintes.
            $armed = false;
        }

        // 1. Uma camada **foi** removida antes da falha: a folha C, primeira da
        //    ordem contratada.
        $this->assertSame(
            [[$c->id]],
            $deleted,
            'A folha C deveria ter sido excluída antes de a operação falhar.',
        );

        // 2. E o rollback desfez essa remoção junto com todo o resto.
        $this->assertDatabaseHas('menus', ['id' => $menu->id]);

        foreach ([$a, $b, $c] as $item) {
            $this->assertDatabaseHas('menu_items', [
                'id' => $item->id,
                'menu_id' => $menu->id,
                'parent_id' => $item->parent_id,
            ]);
        }

        // 3. Nada sobrou e nada faltou: o estado é exatamente o anterior.
        $this->assertSame(1, Menu::query()->count());
        $this->assertSame(3, MenuItem::query()->where('menu_id', $menu->id)->count());
        $this->assertSame(3, MenuItem::query()->count());
    }

    public function test_the_injected_failure_leaves_no_residue(): void
    {
        // Guarda de higiene: o listener do teste acima continua registrado, mas
        // desarmado. Se o desarme falhasse, ele derrubaria a primeira exclusão
        // de qualquer teste seguinte, com um erro difícil de rastrear até lá —
        // então aqui uma exclusão completa precisa concluir normalmente.
        $menu = $this->service()->createMenu(['name' => 'Higiene', 'code' => 'higiene']);
        $item = $this->service()->createItem($menu, $this->payload('Único'));

        $this->service()->deleteItem($item);
        $this->service()->deleteMenu($menu);

        $this->assertSame(0, Menu::query()->count());
        $this->assertSame(0, MenuItem::query()->count());
    }

    /**
     * Roda o cenário N vezes e exige ordem distinta em cada rodada.
     *
     * O `$prepare` monta o estado inicial e devolve o pai sob o qual os workers
     * vão criar — `null` para o grupo raiz.
     *
     * @param  Closure(Menu): ?MenuItem  $prepare
     */
    private function assertOrdersAreUniquePerRound(Closure $prepare, int $expectedExisting = 0): void
    {
        $duplicates = [];
        $incomplete = [];

        for ($round = 1; $round <= self::ITERATIONS; $round++) {
            // A limpeza passa pelo próprio serviço: um `DELETE` plano em
            // `menu_items` esbarraria no RESTRICT da hierarquia, que é
            // exatamente a barreira que esta subfase existe para respeitar.
            Menu::query()->get()->each(fn (Menu $menu): mixed => $this->service()->deleteMenu($menu));

            $menu = $this->service()->createMenu(['name' => 'Concorrência', 'code' => 'concorrencia']);
            $parent = $prepare($menu);
            $parentId = $parent?->getKey();

            $this->runConcurrently(function () use ($menu, $parentId, $round): void {
                $this->service()->createItem($menu, $this->payload("Round {$round}", $parentId));
            });

            $orders = MenuItem::query()
                ->where('menu_id', $menu->id)
                ->when($parentId === null, fn ($query) => $query->whereNull('parent_id'))
                ->when($parentId !== null, fn ($query) => $query->where('parent_id', $parentId))
                ->orderBy('sort_order')
                ->pluck('sort_order')
                ->all();

            if (count($orders) !== $expectedExisting + self::WORKERS) {
                $incomplete[$round] = $orders;

                continue;
            }

            // A sequência tem de ser exatamente 1..N: sem repetição e sem
            // buraco, que é o que uma ordem calculada duas vezes produziria.
            if ($orders !== range(1, $expectedExisting + self::WORKERS)) {
                $duplicates[$round] = $orders;
            }
        }

        $this->assertSame(
            [],
            $duplicates,
            'Criações simultâneas produziram uma sequência inválida: '.json_encode($duplicates),
        );

        $this->assertSame(
            [],
            $incomplete,
            'Alguma criação simultânea não concluiu: '.json_encode($incomplete),
        );
    }

    /**
     * Executa o trabalho em processos paralelos de verdade.
     *
     * A conexão do processo pai é fechada **antes** do fork: um filho que
     * herdasse o socket aberto e o descartasse enviaria o encerramento pela
     * mesma conexão que o pai ainda usa. Cada filho abre a sua.
     *
     * O filho termina com `SIGKILL` para não executar o encerramento do PHPUnit
     * herdado — o resultado que interessa já está commitado no banco.
     *
     * @param  Closure(): void  $work
     */
    private function runConcurrently(Closure $work): void
    {
        DB::disconnect();

        // Todos os filhos partem do mesmo instante, para que a janela de corrida
        // seja a maior possível.
        $startAt = microtime(true) + 0.15;

        $pids = [];

        for ($worker = 0; $worker < self::WORKERS; $worker++) {
            $pid = pcntl_fork();

            $this->assertNotSame(-1, $pid, 'Não foi possível criar o processo concorrente.');

            if ($pid === 0) {
                try {
                    DB::reconnect();

                    while (microtime(true) < $startAt) {
                        // espera ativa: um sleep de 150 ms dispersaria a largada
                    }

                    $work();
                } catch (Throwable) {
                    // A falha aparece como linha ausente na verificação do pai.
                }

                posix_kill(getmypid(), SIGKILL);
            }

            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $label, ?int $parentId = null): array
    {
        return [
            'label' => $label,
            'type' => MenuItemType::Url,
            'url' => '/'.md5($label.$parentId),
            'parent_id' => $parentId,
        ];
    }

    private function service(): MenuService
    {
        return app(MenuService::class);
    }
}
