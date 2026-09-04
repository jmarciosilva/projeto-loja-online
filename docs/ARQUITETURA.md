# 🏗️ Arquitetura — Loja Online

> ⚠️ **Este documento descreve o desenho-alvo, não o código atual.**
>
> Em 2026-09-04 nada da camada de aplicação existe no repositório: não há
> `app/`, `routes/`, models, services nem policies. Os exemplos de código
> abaixo são especificação de como o projeto deve ser escrito, e servem de
> referência ao implementar cada fase — não são trechos extraídos do código.
>
> O que **existe e está validado** é a infraestrutura Docker
> ([`docker/VERIFICACAO.md`](../docker/VERIFICACAO.md)). Para o estado real de
> cada fase, veja o [ROADMAP](../ROADMAP.md).

## Diagrama de Arquitetura (Alto Nível)

```
┌─────────────────────────────────────────────────────────────┐
│                      CLIENTE (Browser)                      │
│                                                              │
│  Frontend: Blade + Livewire 4 + TailwindCSS + AlpineJS 3   │
└────────────────────┬────────────────────────────────────────┘
                     │ HTTP/HTTPS
┌─────────────────────────────────────────────────────────────┐
│                         NGINX (Reverse Proxy)               │
└────────────────────┬────────────────────────────────────────┘
                     │
┌─────────────────────────────────────────────────────────────┐
│                      PHP-FPM (8.3)                          │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │           LARAVEL 12 APPLICATION                     │   │
│  │                                                       │   │
│  │  ┌──────────────┐  ┌────────────┐  ┌─────────────┐ │   │
│  │  │  Controllers │  │  Services  │  │  Policies   │ │   │
│  │  └──────────────┘  └────────────┘  └─────────────┘ │   │
│  │                                                       │   │
│  │  ┌──────────────┐  ┌────────────┐  ┌─────────────┐ │   │
│  │  │    Models    │  │ Middleware │  │  Providers  │ │   │
│  │  └──────────────┘  └────────────┘  └─────────────┘ │   │
│  │                                                       │   │
│  │  ┌──────────────┐  ┌────────────┐  ┌─────────────┐ │   │
│  │  │   Livewire   │  │  Commands  │  │    Enums    │ │   │
│  │  │  Components  │  └────────────┘  └─────────────┘ │   │
│  │  └──────────────┘                                    │   │
│  │                                                       │   │
│  │         ↓ ORM (Eloquent)                             │   │
│  │                                                       │   │
│  └─────────────────────────────────────────────────────┘   │
│                         │                                   │
│                    ┌────┴─────┐                             │
│                    │           │                             │
└───────────────────┼───────────┼─────────────────────────────┘
                    │           │
        ┌───────────┴─┐     ┌────┴────────────┐
        │             │     │                 │
   ┌────▼─────┐   ┌──▼──┐  │  ┌────────────┐ │
   │  MySQL   │   │Redis│  │  │  File      │ │
   │  (BD)    │   │(Cache)  │  │  Storage   │ │
   └──────────┘   └──────┘  │  └────────────┘ │
                            │                 │
                            └─────────────────┘
                            
┌──────────────────────────────────────────────────────────────┐
│                   MOBILE CLIENT (App)                        │
│           REST API via Laravel Sanctum (Tokens)             │
└──────────────────────────────────────────────────────────────┘
```

---

## 🏛️ Camadas da Aplicação

### Camada 1: HTTP Request / Middleware

```
HTTP Request
    ↓
Web Server (Nginx)
    ↓
Middleware Stack:
  • EncryptCookies
  • VerifyCsrfToken
  • Authenticate (optional)
  • CheckRole/Permission (custom)
    ↓
Router (routes/web.php ou routes/api.php)
```

### Camada 2: Controller

```
Controller (Http/Controllers)
    ↓
Input Validation (Form Request)
    ↓
Delegate to Service Layer
    ↓
Return Response (View ou JSON)
```

### Camada 3: Service Layer

```
Service (App/Services)
    ↓
Business Logic
    ↓
Repository (or direct Model)
    ↓
Database Operations
```

### Camada 4: Model & Repository

```
Model (Eloquent)
    ↓
Scopes, Relations, Mutators
    ↓
Database Query (MySQL)
```

### Camada 5: Database

```
MySQL Tables
    ↓
Cache Layer (Redis)
    ↓
Persisted Data
```

---

## 📊 Fluxo de Uma Requisição (Exemplo: Adicionar Produto ao Carrinho)

```
1. Browser (POST /carrinho/adicionar)
   ↓
2. Livewire Component (CartManager.php)
   - Emite evento
   ↓
3. Middleware Stack
   - CSRF token verificado
   - User autenticado
   ↓
4. Controller (CartController@add)
   - Request validation
   - Chama CartService
   ↓
5. Service (CartService)
   - Validação de estoque
   - Lógica de negócio
   - Chama Cart model
   ↓
6. Model (Cart, CartItem)
   - Relacionamentos
   - Mutators
   - Save/Update no DB
   ↓
7. Database (MySQL)
   - INSERT/UPDATE cart_items
   - Commit transaction
   ↓
8. Response (JSON ou redirect)
   - Retorna sucesso
   - Livewire atualiza UI
   ↓
9. Browser
   - Toast de sucesso
   - CartSummary atualizado
```

---

## 🔗 Relacionamentos entre Models (ER Simplificado)

```
User (Cliente/Admin)
  ├─ hasMany Orders
  ├─ hasMany Cart
  ├─ hasMany ReviewProducts (futuro)
  └─ hasMany Addresses

Role (via Spatie)
  ├─ hasMany Users
  └─ belongsToMany Permissions

Permission (via Spatie)
  └─ belongsToMany Roles

Category
  ├─ hasMany Products
  └─ hasMany MenuItems (futuro)

Product
  ├─ belongsTo Category
  ├─ belongsTo User (created_by)
  ├─ hasMany ProductImage
  ├─ hasMany CartItem
  ├─ hasMany OrderItem
  └─ hasMany Review (futuro)

ProductImage
  └─ belongsTo Product

Cart
  ├─ belongsTo User
  └─ hasMany CartItem

CartItem
  ├─ belongsTo Cart
  └─ belongsTo Product

Order
  ├─ belongsTo User
  ├─ hasMany OrderItem
  ├─ hasMany OrderPayment
  └─ hasMany OrderTracking

OrderItem
  ├─ belongsTo Order
  └─ belongsTo Product

OrderPayment
  └─ belongsTo Order

OrderTracking
  └─ belongsTo Order

SiteSetting (Configurações)
  └─ key => value (JSON)

Page (Conteúdo estático)

Banner

Menu
  ├─ hasMany MenuItem
  └─ belongsToMany MenuItems

Media (Uploads)

CustomerAddress
  └─ belongsTo User
```

---

## 🎯 Padrões de Código

### 1. Service Layer

**Objetivo:** Abstrair lógica de negócio dos controllers.

**Exemplo: CartService**

```php
namespace App\Services;

class CartService {
    public function addItem(User $user, int $productId, int $quantity): CartItem {
        // Validar estoque
        $product = Product::findOrFail($productId);
        if ($product->stock < $quantity) {
            throw new OutOfStockException();
        }
        
        // Buscar ou criar carrinho
        $cart = $user->cart()->firstOrCreate(['user_id' => $user->id]);
        
        // Adicionar/atualizar item
        $cartItem = $cart->items()->updateOrCreate(
            ['product_id' => $productId],
            ['quantity' => $quantity]
        );
        
        return $cartItem;
    }
    
    public function getTotal(User $user): float {
        return $user->cart()->cartItems()->sum('subtotal');
    }
}
```

**Uso em Controller:**

```php
class CartController {
    public function __construct(protected CartService $cartService) {}
    
    public function add(Request $request) {
        $this->cartService->addItem(
            auth()->user(),
            $request->product_id,
            $request->quantity
        );
        
        return back()->with('success', 'Produto adicionado!');
    }
}
```

### 2. Repository Pattern (Opcional)

Para queries complexas:

```php
interface ProductRepositoryInterface {
    public function getByCategory(Category $category, array $filters = []);
}

class ProductRepository implements ProductRepositoryInterface {
    public function getByCategory(Category $category, array $filters = []) {
        $query = $category->products();
        
        if (isset($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }
        
        if (isset($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }
        
        return $query->paginate();
    }
}
```

### 3. Policies (Autorização)

**Exemplo: ProductPolicy**

```php
namespace App\Policies;

class ProductPolicy {
    public function update(User $user, Product $product): bool {
        return $user->id === $product->created_by || $user->isAdmin();
    }
    
    public function delete(User $user, Product $product): bool {
        return $user->isAdmin();
    }
}
```

**Uso em Controller:**

```php
class ProductController {
    public function update(Product $product, UpdateRequest $request) {
        $this->authorize('update', $product); // Verifica policy
        
        $product->update($request->validated());
        return back();
    }
}
```

### 4. Form Requests (Validação)

```php
namespace App\Http\Requests;

class CreateProductRequest extends FormRequest {
    public function rules(): array {
        return [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
        ];
    }
    
    public function messages(): array {
        return [
            'name.required' => 'Nome do produto é obrigatório',
        ];
    }
}
```

### 5. Traits para Código Compartilhado

```php
trait HasSlug {
    protected static function bootHasSlug() {
        static::creating(function ($model) {
            $model->slug = Str::slug($model->name);
        });
    }
}

class Product extends Model {
    use HasSlug;
}
```

### 6. Enums para Valores Fixos

```php
namespace App\Enums;

enum OrderStatus: string {
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case PROCESSING = 'processing';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
    
    public function label(): string {
        return match($this) {
            self::PENDING => 'Pendente',
            self::CONFIRMED => 'Confirmado',
            // ...
        };
    }
}
```

**Uso:**

```php
$order->status = OrderStatus::PENDING;
echo $order->status->label(); // "Pendente"
```

### 7. Livewire Components

```php
namespace App\Livewire;

use Livewire\Component;

class CartManager extends Component {
    public $cartItems = [];
    
    #[Validate('required|numeric|min:1')]
    public $quantity = 1;
    
    public function updateQuantity($itemId, $newQuantity) {
        // Lógica aqui
        $this->dispatch('cartUpdated');
    }
    
    public function render() {
        return view('livewire.cart-manager', [
            'total' => $this->calculateTotal(),
        ]);
    }
}
```

---

## 🗂️ Estrutura de Pastas Detalhada

```
app/
├── Models/                          # Eloquent Models
│   ├── User.php
│   ├── Product.php
│   ├── Category.php
│   ├── Order.php
│   ├── OrderItem.php
│   ├── Cart.php
│   ├── CartItem.php
│   ├── Page.php
│   ├── Banner.php
│   ├── Menu.php
│   ├── MenuItem.php
│   ├── Media.php
│   ├── SiteSetting.php
│   └── CustomerAddress.php
│
├── Http/
│   ├── Controllers/
│   │   ├── Controller.php           # Controller base
│   │   ├── HomeController.php       # Homepage
│   │   ├── ProductController.php    # Listagem/detalhe produto
│   │   ├── CartController.php       # Gerenciar carrinho
│   │   ├── OrderController.php      # Checkout/rastreamento
│   │   ├── AuthController.php       # Login/registro
│   │   ├── AccountController.php    # Dados do cliente
│   │   ├── PageController.php       # Páginas estáticas
│   │   │
│   │   ├── Admin/                   # Controllers do painel admin
│   │   │   ├── DashboardController.php
│   │   │   ├── ConfigurationController.php
│   │   │   ├── UserController.php
│   │   │   ├── CategoryController.php
│   │   │   ├── ProductController.php
│   │   │   ├── OrderController.php
│   │   │   ├── PageController.php
│   │   │   ├── BannerController.php
│   │   │   ├── MenuController.php
│   │   │   └── MediaController.php
│   │   │
│   │   └── Api/                     # API REST Controllers
│   │       ├── AuthController.php
│   │       ├── ProductController.php
│   │       ├── CartController.php
│   │       ├── OrderController.php
│   │       └── AddressController.php
│   │
│   ├── Requests/                    # Form Requests (Validação)
│   │   ├── CreateProductRequest.php
│   │   ├── CreateOrderRequest.php
│   │   ├── CreateUserRequest.php
│   │   └── ... (mais conforme necessário)
│   │
│   ├── Middleware/                  # Middlewares customizados
│   │   ├── CheckAdmin.php
│   │   ├── CheckRole.php
│   │   ├── CheckPermission.php
│   │   └── EnsureEmailIsVerified.php
│   │
│   └── Resources/                   # API Resources (JSON transform)
│       ├── ProductResource.php
│       ├── OrderResource.php
│       └── UserResource.php
│
├── Services/                        # Lógica de Negócio
│   ├── CartService.php
│   ├── OrderService.php
│   ├── PaymentService.php          # Interface para pagamentos
│   ├── ShippingService.php         # Interface para frete
│   └── ImageService.php            # Processamento de imagens
│
├── Policies/                        # Autorização por Modelo
│   ├── ProductPolicy.php
│   ├── OrderPolicy.php
│   ├── UserPolicy.php
│   └── PagePolicy.php
│
├── Livewire/                        # Componentes Livewire
│   ├── CartManager.php
│   ├── CartSummary.php
│   ├── CheckoutForm.php
│   ├── OrderTracking.php
│   ├── PaginatedProducts.php
│   ├── FilterProducts.php
│   ├── ProductGallery.php
│   └── ProductCard.php
│
├── Enums/                           # Enums para tipos fixos
│   ├── OrderStatus.php
│   ├── UserRole.php
│   ├── PaymentMethod.php
│   └── OrderStatusMessage.php
│
├── Events/                          # Event classes
│   ├── OrderCreated.php
│   ├── PaymentProcessed.php
│   └── StockLow.php
│
├── Listeners/                       # Event listeners
│   ├── SendOrderConfirmation.php
│   └── UpdateInventory.php
│
├── Jobs/                            # Queue jobs
│   ├── SendOrderNotificationJob.php
│   ├── ProcessRefundJob.php
│   └── UpdateOrderStatusJob.php
│
├── Exceptions/                      # Exceções customizadas
│   ├── OutOfStockException.php
│   ├── InvalidCouponException.php
│   └── PaymentException.php
│
├── Providers/                       # Service providers
│   ├── AppServiceProvider.php
│   ├── AuthServiceProvider.php
│   ├── EventServiceProvider.php
│   └── RouteServiceProvider.php
│
└── Traits/                          # Traits reutilizáveis
    ├── HasSlug.php
    ├── HasTimestamps.php
    └── CreatedByUser.php
```

---

## 📍 Rotas Estruturadas

### routes/web.php

```php
// Frontend público
Route::get('/', [HomeController::class, 'index'])->name('home');

Route::group(['prefix' => 'produtos'], function () {
    Route::get('/', [ProductController::class, 'index'])->name('products.index');
    Route::get('{slug}', [ProductController::class, 'show'])->name('products.show');
});

Route::group(['prefix' => 'categoria'], function () {
    Route::get('{slug}', [ProductController::class, 'category'])->name('products.category');
});

// Carrinho (sem autenticação, com sessão)
Route::group(['prefix' => 'carrinho'], function () {
    Route::get('/', [CartController::class, 'index'])->name('cart.index');
    Route::post('adicionar', [CartController::class, 'add'])->name('cart.add');
    Route::post('remover/{id}', [CartController::class, 'remove'])->name('cart.remove');
    Route::post('atualizar', [CartController::class, 'update'])->name('cart.update');
});

// Checkout (requer autenticação)
Route::middleware('auth')->group(function () {
    Route::get('checkout', [OrderController::class, 'checkout'])->name('checkout');
    Route::post('pedido', [OrderController::class, 'store'])->name('order.store');
    
    Route::group(['prefix' => 'minha-conta'], function () {
        Route::get('/', [AccountController::class, 'dashboard'])->name('account.dashboard');
        Route::get('pedidos', [AccountController::class, 'orders'])->name('account.orders');
        Route::get('enderecos', [AccountController::class, 'addresses'])->name('account.addresses');
    });
});

// Pedido público (sem login, apenas número + email)
Route::get('pedido/{number}', [OrderController::class, 'track'])->name('order.track');

// Páginas estáticas
Route::get('{page}', [PageController::class, 'show'])->name('page.show'); // catch-all

// Admin (com middleware CheckAdmin)
Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::resource('usuarios', UserController::class, ['as' => 'admin']);
    Route::resource('categorias', CategoryController::class, ['as' => 'admin']);
    Route::resource('produtos', ProductController::class, ['as' => 'admin']);
    Route::resource('pedidos', OrderController::class, ['as' => 'admin']);
    // ... mais rotas admin
});
```

### routes/api.php

```php
Route::group(['prefix' => 'v1'], function () {
    // Autenticação
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);
    
    // Pública
    Route::get('produtos', [ProductController::class, 'index']);
    Route::get('produtos/{id}', [ProductController::class, 'show']);
    Route::get('categorias', [CategoryController::class, 'index']);
    
    // Autenticada
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        
        Route::get('carrinho', [CartController::class, 'index']);
        Route::post('carrinho/adicionar', [CartController::class, 'add']);
        // ... mais rotas autenticadas
    });
});
```

---

## 🔄 Fluxo de Desenvolvimento

### Setup Inicial (Fase 1)
1. Docker + Laravel base ✅
2. Database migrations estruturadas
3. Seeders para roles/permissions

### Cada Nova Feature (Fases 2-8)
1. **Migration** → Criar/atualizar tabelas
2. **Model** → Relacionamentos e scopes
3. **Service** → Lógica de negócio
4. **Controller** → Endpoint/action
5. **View/Component** → UI
6. **Test** → Cobertura de testes
7. **Documentation** → Atualizar README/ROADMAP

---

## 💾 Database Transactions

Operações críticas usam transações:

```php
DB::transaction(function () {
    // Criar order
    $order = Order::create([...]);
    
    // Criar order items
    foreach ($cartItems as $item) {
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $item->product_id,
            'quantity' => $item->quantity,
        ]);
        
        // Diminuir estoque
        $product = Product::find($item->product_id);
        $product->stock -= $item->quantity;
        $product->save();
    }
    
    // Se algo falhar, TUDO reverte
});
```

---

## 🚀 Performance & Otimizações

### 1. N+1 Queries
```php
// ❌ Ruim: N+1
$orders = Order::all();
foreach ($orders as $order) {
    echo $order->user->name; // Query por item
}

// ✅ Bom: Eager loading
$orders = Order::with('user')->get();
foreach ($orders as $order) {
    echo $order->user->name; // Já carregado
}
```

### 2. Scopes
```php
// Model
class Product extends Model {
    public function scopeActive($query) {
        return $query->where('is_active', true);
    }
}

// Controller
$products = Product::active()->paginate();
```

### 3. Indexação de Banco
```php
// Migration
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->index('category_id');           // Index
    $table->fullText(['name', 'description']); // Full-text search
});
```

### 4. Cache
```php
// Salvar
Cache::remember('setting:logo', 3600, function () {
    return SiteSetting::where('key', 'logo')->value('value');
});

// Invalidar
Cache::forget('setting:logo');
```

---

## 🔐 Segurança

### CSRF Protection
Automático em Blade forms via `@csrf`

### XSS Prevention
Blade auto-escapa output: `{{ $variable }}`

### SQL Injection
Eloquent prepared statements: `where('name', $name)`

### Authorization
Policies: `$this->authorize('update', $product)`

### Rate Limiting
Middleware em API: `throttle:60,1` (60 requests/min)

---

## 📝 Convenções de Código

- **PSR-12** para espaçamento/nomenclatura
- **camelCase** para métodos e propriedades
- **snake_case** para variáveis de BD e env
- **PascalCase** para classes
- Métodos máximo 20 linhas (quebrar em serviços)
- Sem hardcoded strings (usar lang helpers)

---

**Última atualização:** 2026-09-04
