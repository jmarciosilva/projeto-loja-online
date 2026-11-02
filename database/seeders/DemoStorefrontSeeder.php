<?php

namespace Database\Seeders;

use App\Enums\BannerPosition;
use App\Enums\MenuItemType;
use App\Enums\PageStatus;
use App\Models\Banner;
use App\Models\Media;
use App\Models\Menu;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Services\BannerService;
use App\Services\MediaService;
use App\Services\MenuService;
use App\Services\PageService;
use App\Services\SiteSettingService;
use App\Services\ThemeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Conteúdo demonstrativo da vitrine, limitado ao desenvolvimento local.
 *
 * A execução é explícita (`db:seed --class=DemoStorefrontSeeder`). Registros
 * existentes são preservados; o seeder não cria produtos nem pedidos fictícios.
 */
class DemoStorefrontSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        DB::transaction(function (): void {
            $pages = $this->seedPages(app(PageService::class));
            $this->seedTheme(app(SiteSettingService::class), app(ThemeService::class));
            $this->seedHeroBanner(app(BannerService::class));
            $this->seedMainMenu($pages, app(MenuService::class));
        });
    }

    /**
     * @return array<string, Page>
     */
    private function seedPages(PageService $pages): array
    {
        $definitions = [
            'sobre-a-loja' => [
                'title' => 'Sobre a loja',
                'content' => <<<'MARKDOWN'
## Uma curadoria para o cotidiano

Esta página faz parte do conteúdo de demonstração da loja. Use o painel para trocar o texto, publicar novas páginas e ajustar os links da navegação.

Estamos preparando uma experiência de compra simples, com produtos escolhidos para acompanhar diferentes momentos do dia.
MARKDOWN,
                'meta_title' => 'Sobre a loja',
                'meta_description' => 'Conheça a proposta e a curadoria da loja.',
            ],
            'entrega-e-devolucoes' => [
                'title' => 'Entrega e devoluções',
                'content' => <<<'MARKDOWN'
## Informações de entrega

Este conteúdo é demonstrativo. Antes de abrir a loja ao público, informe aqui as regiões atendidas, os prazos, os valores de frete e as condições de troca e devolução da operação.
MARKDOWN,
                'meta_title' => 'Entrega e devoluções',
                'meta_description' => 'Informações demonstrativas sobre entrega, trocas e devoluções.',
            ],
            'contato' => [
                'title' => 'Contato',
                'content' => <<<'MARKDOWN'
## Fale com a loja

Este conteúdo é demonstrativo. Substitua pelos canais oficiais de atendimento e pelo horário de funcionamento antes de publicar a loja.
MARKDOWN,
                'meta_title' => 'Contato',
                'meta_description' => 'Entre em contato com a equipe da loja.',
            ],
        ];

        $seededPages = [];

        foreach ($definitions as $slug => $attributes) {
            // Inclui páginas removidas logicamente: não as restaura nem reutiliza
            // o slug que continua reservado pelo schema.
            if (Page::withTrashed()->where('slug', $slug)->exists()) {
                continue;
            }

            $seededPages[$slug] = $pages->create([
                ...$attributes,
                'slug' => $slug,
                'status' => PageStatus::Published,
            ]);
        }

        return $seededPages;
    }

    private function seedHeroMedia(): ?Media
    {
        $source = __DIR__.'/assets/home-hero.png';
        $contents = file_get_contents($source);

        if ($contents === false) {
            throw new RuntimeException('Não foi possível ler a imagem demonstrativa da vitrine.');
        }

        $image = getimagesizefromstring($contents);

        if ($image === false || $image['mime'] !== 'image/png') {
            throw new RuntimeException('A imagem demonstrativa da vitrine precisa ser um PNG válido.');
        }

        $path = 'media/demo/home-hero.png';
        $disk = Storage::disk(MediaService::DISK);
        $media = Media::query()->where([
            'disk' => MediaService::DISK,
            'path' => $path,
        ])->first();

        if ($media !== null) {
            return $disk->exists($path) ? $media : null;
        }

        // Um caminho ocupado sem registro não é assumido como arquivo de demonstração.
        if ($disk->exists($path)) {
            return null;
        }

        if ($disk->put($path, $contents) === false) {
            throw new RuntimeException('Não foi possível armazenar a imagem demonstrativa da vitrine.');
        }

        $media = new Media;
        $media->fill([
            'original_name' => 'vitrine-demonstrativa.png',
            'disk' => MediaService::DISK,
            'path' => $path,
            'mime_type' => $image['mime'],
            'size' => strlen($contents),
            'width' => $image[0],
            'height' => $image[1],
        ]);
        $media->save();

        return $media;
    }

    private function seedTheme(SiteSettingService $settings, ThemeService $theme): void
    {
        $keys = $theme->keys();

        $defaults = [
            'primary' => '#3F5B4B',
            'secondary' => '#737567',
            'accent' => '#C47B58',
        ];

        foreach ($keys as $color => $key) {
            if (! SiteSetting::query()->where('key', $key)->exists()) {
                $settings->set($key, 'string', $defaults[$color]);
            }
        }
    }

    private function seedHeroBanner(BannerService $banners): void
    {
        // Não competir com banners existentes nem substituir conteúdo comercial.
        if (Banner::query()->where('position', BannerPosition::Hero)->exists()) {
            return;
        }

        $media = $this->seedHeroMedia();

        if ($media === null) {
            return;
        }

        $attributes = [
            'name' => 'Vitrine demonstrativa — coleção essencial',
            'media_id' => $media->getKey(),
            'position' => BannerPosition::Hero,
            'link_url' => '/paginas/sobre-a-loja',
            'alt_text' => 'Roupas em tons naturais, bolsa de couro, vaso e embalagem sobre uma bancada clara.',
            'is_active' => true,
        ];

        $banners->create($attributes);
    }

    /**
     * @param  array<string, Page>  $pages
     */
    private function seedMainMenu(array $pages, MenuService $menus): void
    {
        $menu = Menu::query()->where('code', 'main')->first();

        if ($menu !== null) {
            return;
        }

        $menu = $menus->createMenu([
            'name' => 'Navegação principal',
            'code' => 'main',
            'is_active' => true,
        ]);

        $items = [
            [
                'label' => 'Início',
                'type' => MenuItemType::Url,
                'url' => '/',
                'page_id' => null,
            ],
        ];

        foreach ([
            'sobre-a-loja' => 'Sobre a loja',
            'entrega-e-devolucoes' => 'Entrega e devoluções',
            'contato' => 'Contato',
        ] as $slug => $label) {
            if (! isset($pages[$slug])) {
                continue;
            }

            $items[] = [
                'label' => $label,
                'type' => MenuItemType::Page,
                'page_id' => $pages[$slug]->getKey(),
                'url' => null,
            ];
        }

        foreach ($items as $attributes) {
            $attributes['is_active'] = true;
            $menus->createItem($menu, $attributes);
        }
    }
}
