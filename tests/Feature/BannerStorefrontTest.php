<?php

namespace Tests\Feature;

use App\Enums\BannerPosition;
use App\Models\Banner;
use App\Models\Media;
use App\Services\BannerService;
use App\Services\MediaService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Banners na vitrine — F2.5-C.
 *
 * A home continua sendo a home comercial existente: a integração é uma só e
 * mínima, `hero → home`. `sidebar` e `footer` existem no domínio e no serviço,
 * mas não ganham interface pública nesta subfase.
 *
 * O assunto aqui é a apresentação: o que aparece, o que não aparece, em que
 * ordem, com qual URL, com qual texto alternativo e com qual escape. As
 * invariantes de domínio continuam cobertas por BannerTest e BannerServiceTest.
 */
class BannerStorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(MediaService::DISK);
    }

    // --- Ausência ---------------------------------------------------------

    public function test_without_banners_the_home_keeps_rendering(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        // A home comercial continua inteira: nada da F2.5-C substitui seções.
        $this->assertStringContainsString('Fase 1 concluída', $html);

        // Sem banners ativos nenhuma moldura vazia é emitida — nem a seção,
        // nem uma borda sem conteúdo dentro dela.
        $this->assertNull($this->heroSection($html));
        $this->assertDoesNotMatchRegularExpression('#<img[^>]*media/#', $html);
    }

    public function test_a_position_with_only_inactive_banners_emits_no_section(): void
    {
        $this->makeBanner(BannerPosition::Hero, ['is_active' => false]);

        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertNull($this->heroSection($html));
    }

    // --- Presença ---------------------------------------------------------

    public function test_an_active_hero_banner_is_rendered_on_the_home(): void
    {
        $banner = $this->makeBanner(BannerPosition::Hero);

        $hero = $this->heroSectionOfHome();

        $this->assertMatchesRegularExpression(
            '#<img[^>]*src="[^"]*'.preg_quote($banner->media->path, '#').'"#',
            $hero,
        );
    }

    public function test_the_hero_image_uses_the_url_derived_from_the_media(): void
    {
        $banner = $this->makeBanner(BannerPosition::Hero);

        // A URL vem da F2.7 e não do banner: ele referencia `Media.id` e nunca
        // guarda caminho nem URL.
        $this->assertStringContainsString(
            'src="'.e(app(MediaService::class)->url($banner->media)).'"',
            $this->heroSectionOfHome(),
        );
    }

    public function test_the_hero_image_carries_the_alt_text_of_the_banner(): void
    {
        $banner = $this->makeBanner(BannerPosition::Hero, [
            'alt_text' => 'Modelos usando a coleção de verão.',
        ]);

        $this->assertMatchesRegularExpression(
            '#<img[^>]*alt="'.preg_quote(e($banner->alt_text), '#').'"#',
            $this->heroSectionOfHome(),
        );
    }

    public function test_the_alt_text_is_never_the_media_original_name(): void
    {
        $media = Media::factory()->create(['original_name' => 'banner-verao-final-v3.jpg']);

        $this->makeBanner(BannerPosition::Hero, [
            'media_id' => $media->id,
            'alt_text' => 'Modelos usando a coleção de verão.',
        ]);

        $hero = $this->heroSectionOfHome();

        $this->assertStringContainsString('alt="Modelos usando a coleção de verão."', $hero);
        $this->assertStringNotContainsString('banner-verao-final-v3.jpg', $hero);
    }

    public function test_the_administrative_name_is_not_published(): void
    {
        // `name` identifica o banner no painel; não é título público.
        $this->makeBanner(BannerPosition::Hero, ['name' => 'ZZ interno campanha 042']);

        $this->assertStringNotContainsString(
            'ZZ interno campanha 042',
            (string) $this->get('/')->assertOk()->getContent(),
        );
    }

    // --- Filtro público ---------------------------------------------------

    public function test_an_inactive_hero_banner_is_not_rendered(): void
    {
        $inactive = $this->makeBanner(BannerPosition::Hero, ['is_active' => false]);
        $active = $this->makeBanner(BannerPosition::Hero);

        $hero = $this->heroSectionOfHome();

        $this->assertStringContainsString($active->media->path, $hero);
        $this->assertStringNotContainsString($inactive->media->path, $hero);
    }

    public function test_an_active_sidebar_banner_does_not_reach_the_hero(): void
    {
        $sidebar = $this->makeBanner(BannerPosition::Sidebar);
        $this->makeBanner(BannerPosition::Hero);

        $this->assertStringNotContainsString($sidebar->media->path, $this->heroSectionOfHome());
    }

    public function test_an_active_footer_banner_does_not_reach_the_hero(): void
    {
        $footer = $this->makeBanner(BannerPosition::Footer);
        $this->makeBanner(BannerPosition::Hero);

        $this->assertStringNotContainsString($footer->media->path, $this->heroSectionOfHome());
    }

    public function test_the_other_positions_have_no_public_surface_of_their_own(): void
    {
        // `sidebar` e `footer` não ganham interface pública nesta subfase:
        // inventá-la só para consumir os valores do enum produziria tela que
        // ninguém pediu.
        $sidebar = $this->makeBanner(BannerPosition::Sidebar);
        $footer = $this->makeBanner(BannerPosition::Footer);

        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString($sidebar->media->path, $html);
        $this->assertStringNotContainsString($footer->media->path, $html);
    }

    // --- Link -------------------------------------------------------------

    public function test_a_hero_banner_with_a_link_is_wrapped_in_an_anchor(): void
    {
        $banner = $this->makeBanner(BannerPosition::Hero, ['link_url' => '/categorias/promocoes']);

        $this->assertMatchesRegularExpression(
            '#<a[^>]*href="/categorias/promocoes"[^>]*>\s*<img[^>]*'
                .preg_quote($banner->media->path, '#').'#s',
            $this->heroSectionOfHome(),
        );
    }

    public function test_an_absolute_http_link_is_rendered_as_received(): void
    {
        $this->makeBanner(BannerPosition::Hero, ['link_url' => 'https://exemplo.com/campanha']);

        $this->assertStringContainsString(
            'href="https://exemplo.com/campanha"',
            $this->heroSectionOfHome(),
        );
    }

    public function test_a_hero_banner_without_a_link_is_not_wrapped_in_an_anchor(): void
    {
        $banner = $this->makeBanner(BannerPosition::Hero, ['link_url' => null]);

        $hero = $this->heroSectionOfHome();

        $this->assertStringContainsString($banner->media->path, $hero);
        // Nenhuma âncora inerte em volta da imagem: um alvo de clique que não
        // leva a lugar nenhum é pior do que nenhum alvo.
        $this->assertStringNotContainsString('<a ', $hero);
        $this->assertStringNotContainsString('<a>', $hero);
    }

    public function test_the_markup_adds_no_target_and_no_script(): void
    {
        $this->makeBanner(BannerPosition::Hero, ['link_url' => 'https://exemplo.com/campanha']);

        $hero = $this->heroSectionOfHome();

        $this->assertStringNotContainsString('target=', $hero);
        $this->assertStringNotContainsString('<script', $hero);
        $this->assertStringNotContainsString('onclick', $hero);
    }

    // --- Escape -----------------------------------------------------------

    public function test_the_alt_text_is_escaped_in_the_attribute(): void
    {
        $altText = 'Promoção "verão" <b>50%</b> & cia';

        $this->makeBanner(BannerPosition::Hero, ['alt_text' => $altText]);

        $hero = $this->heroSectionOfHome();

        $this->assertStringContainsString('alt="'.e($altText).'"', $hero);
        $this->assertStringNotContainsString('<b>50%</b>', $hero);
    }

    public function test_the_link_is_escaped_in_the_attribute(): void
    {
        $link = '/busca?cor=azul&tamanho=m';

        $this->makeBanner(BannerPosition::Hero, ['link_url' => $link]);

        $hero = $this->heroSectionOfHome();

        $this->assertStringContainsString('href="'.e($link).'"', $hero);
        $this->assertStringContainsString('&amp;tamanho=m', $hero);
    }

    // --- Ordem ------------------------------------------------------------

    public function test_the_hero_banners_follow_sort_order_and_then_id(): void
    {
        $media = Media::factory()->count(3)->create();

        $third = $this->fixOrder($media[0], 3);
        // Os dois seguintes empatam em `sort_order`: sem o desempate por `id`
        // a ordem entre eles dependeria do plano escolhido pelo banco.
        $firstTie = $this->fixOrder($media[1], 1);
        $secondTie = $this->fixOrder($media[2], 1);

        $hero = $this->heroSectionOfHome();

        $this->assertSame(
            [$firstTie->media_id, $secondTie->media_id, $third->media_id],
            $this->renderedMediaIds($hero),
        );
    }

    // --- Consulta ---------------------------------------------------------

    public function test_the_view_does_not_query_the_banners_itself(): void
    {
        // Uma consulta a `banners` por requisição: a da rota, pelo serviço. Mais
        // do que isso significaria query na Blade ou N+1 na mídia.
        $media = Media::factory()->create();

        foreach (range(1, 3) as $ignored) {
            $this->makeBanner(BannerPosition::Hero, ['media_id' => $media->id]);
        }

        $statements = [];
        DB::listen(function (QueryExecuted $query) use (&$statements): void {
            $statements[] = $query->sql;
        });

        $this->get('/')->assertOk();

        // O identificador é comparado sem as aspas do dialeto: a suíte roda
        // tanto no MySQL canônico quanto na configuração padrão do projeto, e
        // cada um cita a tabela à sua maneira.
        $bannerQueries = array_filter(
            $statements,
            fn (string $sql): bool => str_contains(mb_strtolower($sql), 'banners'),
        );

        $this->assertCount(1, $bannerQueries);
    }

    // --- Helpers ----------------------------------------------------------

    /**
     * Banner criado pelo `BannerService`, ativo por padrão.
     *
     * Passa pelo serviço de propósito: a vitrine mostra o que o domínio
     * aceitou, e criar a linha por fora poderia publicar um estado que o
     * contrato recusa.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function makeBanner(BannerPosition $position, array $overrides = []): Banner
    {
        $banner = app(BannerService::class)->create(array_merge([
            'name' => 'Campanha',
            'media_id' => Media::factory()->create()->id,
            'position' => $position,
            'alt_text' => 'Campanha de verão.',
            'is_active' => true,
        ], $overrides));

        return $banner->fresh();
    }

    /**
     * Banner ativo de `hero` com a ordem fixada diretamente na linha.
     *
     * A ordem precisa ser explícita para provar a regra de ordenação; deixá-la
     * a cargo do serviço tornaria o serviço a fixture do próprio teste.
     */
    private function fixOrder(Media $media, int $sortOrder): Banner
    {
        return Banner::factory()->create([
            'media_id' => $media->id,
            'position' => BannerPosition::Hero,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
    }

    /**
     * Conteúdo da seção de destaques da home, já exigido presente.
     */
    private function heroSectionOfHome(): string
    {
        $section = $this->heroSection((string) $this->get('/')->assertOk()->getContent());

        $this->assertNotNull($section, 'A seção de destaques não foi encontrada na home.');

        return (string) $section;
    }

    /**
     * Conteúdo da seção de destaques, ou `null` quando ela não foi emitida.
     *
     * A inspeção é da seção, e não da página inteira: o layout público tem
     * imagens e âncoras próprias — logo, link de marca, rodapé —, e uma busca
     * solta passaria por elas.
     */
    private function heroSection(string $html): ?string
    {
        $matched = preg_match(
            '#<section[^>]*aria-label="Destaques"[^>]*>(.*?)</section>#s',
            $html,
            $matches,
        );

        return $matched === 1 ? $matches[1] : null;
    }

    /**
     * Ids das mídias renderizadas, na ordem em que aparecem no HTML.
     *
     * A ordem sai das `src` emitidas, e não de espaços em branco ou indentação
     * do template.
     *
     * @return list<int>
     */
    private function renderedMediaIds(string $hero): array
    {
        preg_match_all('#<img[^>]*src="([^"]+)"#', $hero, $matches);

        return array_map(
            fn (string $src): int => Media::query()
                ->get()
                ->first(fn (Media $media): bool => str_contains($src, $media->path))
                ->id,
            $matches[1],
        );
    }
}
