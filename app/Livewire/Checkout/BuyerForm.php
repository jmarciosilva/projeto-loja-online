<?php

namespace App\Livewire\Checkout;

use App\Enums\BrazilianState;
use App\Services\CartService;
use App\Services\Checkout\CheckoutSession;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Identificação do comprador e endereço de entrega.
 *
 * Não cria pedido, não reserva estoque e não calcula frete. Os dados validados
 * ficam apenas na sessão do checkout, até a etapa de pedido.
 */
class BuyerForm extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $postalCode = '';

    public string $street = '';

    public string $number = '';

    public string $complement = '';

    public string $district = '';

    public string $city = '';

    public string $state = '';

    public string $reference = '';

    public bool $saved = false;

    public function mount(CheckoutSession $session): void
    {
        $buyer = $session->buyer();

        if ($buyer === null) {
            return;
        }

        foreach ($buyer as $field => $value) {
            if (property_exists($this, $field)) {
                $this->{$field} = $value;
            }
        }

        $this->saved = true;
    }

    public function save(CheckoutSession $session, CartService $cart): void
    {
        if (! $cart->readyForCheckout()) {
            session()->flash('cart_error', 'Seu carrinho mudou. Revise os itens antes de continuar.');
            $this->redirectRoute('cart.index');

            return;
        }

        $this->normalize();
        $this->validate();

        $session->putBuyer([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'postalCode' => $this->postalCode,
            'street' => $this->street,
            'number' => $this->number,
            'complement' => $this->complement,
            'district' => $this->district,
            'city' => $this->city,
            'state' => $this->state,
            'reference' => $this->reference,
        ]);

        $this->saved = true;
    }

    public function render(): View
    {
        return view('livewire.checkout.buyer-form', [
            'states' => BrazilianState::cases(),
        ]);
    }

    /**
     * Normaliza a entrada antes da validação: somente dígitos em telefone e CEP,
     * e-mail em minúsculas e espaços reduzidos. A validação continua no servidor.
     */
    private function normalize(): void
    {
        $this->name = preg_replace('/\s+/u', ' ', trim($this->name)) ?? '';
        $this->email = strtolower(trim($this->email));
        $this->phone = preg_replace('/\D/', '', $this->phone) ?? '';
        $this->postalCode = preg_replace('/\D/', '', $this->postalCode) ?? '';
        $this->state = strtoupper(trim($this->state));

        foreach (['street', 'number', 'complement', 'district', 'city', 'reference'] as $field) {
            $this->{$field} = trim($this->{$field});
        }
    }

    /** @return array<string, array<int, mixed>|string> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', 'regex:/^[\pL\s\.\'-]+$/u', function (string $attribute, mixed $value, \Closure $fail): void {
                if (count(array_filter(explode(' ', (string) $value))) < 2) {
                    $fail('Informe nome e sobrenome.');
                }
            }],
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'phone' => ['required', 'string', 'regex:/^(1[1-9]|[2-9][0-9])9\d{8}$/'],
            'postalCode' => ['required', 'string', 'regex:/^(?!0{8})\d{8}$/'],
            'street' => ['required', 'string', 'max:150'],
            'number' => ['required', 'string', 'max:20', 'regex:/^[\pL\d\s\/\-\.]+$/u'],
            'complement' => ['nullable', 'string', 'max:100'],
            'district' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', Rule::enum(BrazilianState::class)],
            'reference' => ['nullable', 'string', 'max:150'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'required' => 'Este campo é obrigatório.',
            'email' => 'Informe um e-mail válido.',
            'email.max' => 'O e-mail é muito longo.',
            'phone.regex' => 'Informe um celular válido com DDD, com 11 dígitos (ex.: 11 91234-5678).',
            'postalCode.regex' => 'Informe um CEP válido, com 8 dígitos (ex.: 01310-100).',
            'number.regex' => 'Use apenas letras, números e os sinais / - . (ex.: 123 ou S/N).',
            'name.regex' => 'O nome deve conter apenas letras.',
            'state.enum' => 'Selecione uma UF válida.',
            'state.required' => 'Selecione a UF.',
            'max' => 'Este campo passou do tamanho permitido.',
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'name' => 'nome completo',
            'email' => 'e-mail',
            'phone' => 'celular',
            'postalCode' => 'CEP',
            'street' => 'logradouro',
            'number' => 'número',
            'complement' => 'complemento',
            'district' => 'bairro',
            'city' => 'cidade',
            'state' => 'UF',
            'reference' => 'referência',
        ];
    }
}
