<?php

use App\Enums\ShippingConnectionStatus;
use App\Enums\ShippingProviderCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_provider_configs', function (Blueprint $table) {
            $table->id();
            $table->enum('provider', ShippingProviderCode::values())->unique();
            $table->string('environment', 24);
            $table->char('origin_postal_code', 8);
            $table->json('enabled_service_codes')->nullable();
            // O cast encrypted:array precisa de texto, pois o valor cifrado
            // possui tamanho variável e nunca é consultado por conteúdo.
            $table->text('credentials')->nullable();
            $table->enum('connection_status', array_column(ShippingConnectionStatus::cases(), 'value'))
                ->default(ShippingConnectionStatus::NotTested->value);
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->timestamps();

            $table->index(['provider', 'connection_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_provider_configs');
    }
};
