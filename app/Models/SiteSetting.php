<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use JsonException;
use LogicException;

class SiteSetting extends Model
{
    /**
     * Indica que um novo valor validado foi atribuído desde a última persistência.
     * O marcador diferencia uma atribuição explícita de uma alteração detectada no banco.
     */
    private bool $valueWasProvided = false;

    /**
     * Os valores são persistidos como texto com tipo explícito: string, integer,
     * boolean, json ou null. Valores estruturados são arrays codificados em JSON.
     *
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'type',
        'value',
    ];

    /**
     * Garante que a atribuição em massa defina o tipo antes de codificar o valor.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function fill(array $attributes)
    {
        if (array_key_exists('type', $attributes) && array_key_exists('value', $attributes)) {
            $value = $attributes['value'];
            unset($attributes['value']);

            parent::fill($attributes);
            $this->value = $value;

            return $this;
        }

        return parent::fill($attributes);
    }

    /**
     * Uma configuração persistida só pode mudar de tipo com um novo valor atribuído
     * e validado. Um valor pode ser serializado com a mesma representação já armazenada.
     *
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = [])
    {
        try {
            if ($this->exists && $this->isDirty('type') && ! $this->valueWasProvided) {
                throw new LogicException('Changing a site setting type requires a new compatible value.');
            }

            return parent::save($options);
        } finally {
            $this->valueWasProvided = false;
        }
    }

    /**
     * @return Attribute<string, string>
     */
    protected function type(): Attribute
    {
        return Attribute::make(
            set: function (string $type): string {
                if (! in_array($type, ['string', 'integer', 'boolean', 'json', 'null'], true)) {
                    throw new InvalidArgumentException("Unsupported site setting type [{$type}].");
                }

                return $type;
            },
        );
    }

    /**
     * @return Attribute<string|int|bool|array<mixed>|null, string|null>
     */
    protected function value(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value, array $attributes): string|int|bool|array|null => match ($attributes['type']) {
                'string' => $value,
                'integer' => (int) $value,
                'boolean' => (bool) $value,
                'json' => json_decode($value, true, 512, JSON_THROW_ON_ERROR),
                'null' => null,
            },
            set: function (mixed $value, array $attributes): ?string {
                try {
                    $encodedValue = $this->encodeValue($value, $attributes['type'] ?? null);
                } catch (InvalidArgumentException $exception) {
                    $this->valueWasProvided = false;

                    throw $exception;
                }

                $this->valueWasProvided = true;

                return $encodedValue;
            },
        );
    }

    private function encodeValue(mixed $value, ?string $type): ?string
    {
        return match ($type) {
            'string' => $this->encodeString($value),
            'integer' => $this->encodeInteger($value),
            'boolean' => $this->encodeBoolean($value),
            'json' => $this->encodeJson($value),
            'null' => $this->encodeNull($value),
            default => throw new InvalidArgumentException('A site setting type must be set before its value.'),
        };
    }

    private function encodeString(mixed $value): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException('A string site setting must have a string value.');
        }

        return $value;
    }

    private function encodeInteger(mixed $value): string
    {
        if (! is_int($value)) {
            throw new InvalidArgumentException('An integer site setting must have an integer value.');
        }

        return (string) $value;
    }

    private function encodeBoolean(mixed $value): string
    {
        if (! is_bool($value)) {
            throw new InvalidArgumentException('A boolean site setting must have a boolean value.');
        }

        return $value ? '1' : '0';
    }

    private function encodeJson(mixed $value): string
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException('A JSON site setting must have an array value.');
        }

        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('The JSON site setting value cannot be encoded.', previous: $exception);
        }
    }

    private function encodeNull(mixed $value): null
    {
        if ($value !== null) {
            throw new InvalidArgumentException('A null site setting must have a null value.');
        }

        return null;
    }
}
