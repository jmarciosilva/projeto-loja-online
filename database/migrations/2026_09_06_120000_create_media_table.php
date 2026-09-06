<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Executa as migrations.
     */
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            // `disk` e `path` juntos localizam o arquivo. Guardar o disco por
            // registro é o que permite trocar de backend no futuro sem
            // reescrever o histórico: a leitura usa sempre `$media->disk`.
            $table->string('disk', 32);
            $table->string('path');
            // Nome enviado pelo administrador. É metadado de reconhecimento na
            // grid — nunca compõe caminho, URL ou nome de download.
            $table->string('original_name');
            $table->string('mime_type', 128);
            // A primeira versão limita o upload a 5 MB, então INT UNSIGNED
            // (até ~4 GB) cobre a faixa contratada com folga; BIGINT reservaria
            // o dobro para valores que o próprio contrato torna inalcançáveis.
            $table->unsignedInteger('size');
            // Dimensões do arquivo armazenado, não do enviado. São NOT NULL
            // porque a biblioteca só aceita imagens raster: uma coluna
            // nullable que nunca recebe null só geraria consumidores
            // defensivos sem motivo.
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->timestamps();

            // O nome físico é gerado com componente aleatório e verificado antes
            // de gravar, mas só o banco fecha a corrida entre gerar e inserir —
            // mesma proteção final que `pages.slug` já usa. A chave é composta
            // porque é o par (disco, caminho) que identifica o arquivo.
            $table->unique(['disk', 'path']);
        });
    }

    /**
     * Reverte as migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
