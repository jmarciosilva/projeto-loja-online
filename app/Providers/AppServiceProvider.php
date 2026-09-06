<?php

namespace App\Providers;

use App\Models\Media;
use App\Services\MediaUsageRegistry;
use App\Services\ThemeService;
use App\Services\VisualIdentityService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Singleton de propósito: os consumidores se registram no bootstrap, e
        // o `MediaService` precisa consultar exatamente o mesmo estado. Com um
        // bind comum, cada resolução criaria um registro vazio e a proteção
        // contra exclusão de mídia em uso passaria a não bloquear nada.
        $this->app->singleton(MediaUsageRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerMediaConsumers();

        // As cores do tema e a identidade visual chegam ao layout público por um
        // composer específico, e não por View::share: o painel administrativo
        // usa outro layout e permanece neutro, por decisão arquitetural.
        View::composer('layouts.app', function (ViewInstance $view): void {
            $view->with('themeColors', $this->app->make(ThemeService::class)->colors());
            $view->with('visualIdentity', $this->app->make(VisualIdentityService::class)->forPublicLayout());
        });
    }

    /**
     * Declara quem consome a biblioteca de mídia.
     *
     * A direção é sempre consumidor → registro: a F2.7 não conhece logo nem
     * favicon, e é a F2.3-C que se apresenta aqui. Os rótulos são humanos e
     * estáveis porque chegam ao administrador na mensagem que explica por que a
     * exclusão foi bloqueada.
     *
     * O serviço é resolvido dentro do closure, e não no boot: a verificação só
     * acontece quando alguém tenta excluir uma mídia, e ler a configuração a
     * cada requisição seria trabalho jogado fora.
     */
    private function registerMediaConsumers(): void
    {
        $registry = $this->app->make(MediaUsageRegistry::class);

        $registry->register(
            'Logo do site',
            fn (Media $media): bool => $this->app->make(VisualIdentityService::class)->isLogo($media),
        );

        $registry->register(
            'Favicon do site',
            fn (Media $media): bool => $this->app->make(VisualIdentityService::class)->isFavicon($media),
        );
    }
}
