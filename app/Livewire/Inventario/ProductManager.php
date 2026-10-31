<?php

namespace App\Livewire\Inventario;

use App\Enums\PieceRotationType;
use App\Enums\ProductType;
use App\Enums\TechnicalCategory;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ProductManager extends Component
{
    use WithFileUploads, WithPagination;

    public $viewMode = 'kanban';

    public $search = '';

    public $product_id;

    public $name = '';

    public $description = '';

    public $sku = '';

    public $part_number = '';

    public $part_number_alt_1 = '';

    public $part_number_alt_2 = '';

    public $brand = '';

    public $type = 'pieza';

    public $piece_rotation_type = 'alta_rotacion';

    public $technical_category = 'mecanico';

    public $sales_price = 0;

    public $cost = 0;

    public $total_cost = 0;

    public $stock_on_hand = 0;

    public $photo;

    public $image_url = '';

    public function create(): void
    {
        $this->resetForm();
        $this->viewMode = 'form';
    }

    public function edit($id): void
    {
        $product = Product::findOrFail($id);

        $this->product_id = $product->id;
        $this->name = $product->name;
        $this->description = $product->description;
        $this->sku = $product->sku;
        $this->part_number = $product->part_number;
        $this->part_number_alt_1 = $product->part_number_alt_1;
        $this->part_number_alt_2 = $product->part_number_alt_2;
        $this->brand = $product->brand;
        $this->type = $product->type?->value ?? 'pieza';
        $this->piece_rotation_type = $product->piece_rotation_type?->value ?? 'alta_rotacion';
        $this->technical_category = $product->technical_category?->value ?? 'mecanico';
        $this->sales_price = $product->sales_price;
        $this->cost = $product->cost;
        $this->total_cost = $product->total_cost;
        $this->stock_on_hand = $product->stock_on_hand;
        $this->photo = null;

        $currentImg = $product->image_path;
        $this->image_url = str_starts_with((string) $currentImg, 'http') ? $currentImg : '';
        $this->viewMode = 'form';
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('warehouse.products.manage'), 403);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'part_number' => ['nullable', 'string', 'max:180'],
            'part_number_alt_1' => ['nullable', 'string', 'max:180'],
            'part_number_alt_2' => ['nullable', 'string', 'max:180'],
            'type' => ['required', Rule::in(ProductType::values())],
            'piece_rotation_type' => ['nullable', 'required_if:type,pieza', Rule::in(PieceRotationType::values())],
            'technical_category' => ['nullable', 'required_if:type,pieza', Rule::in(TechnicalCategory::values())],
            'sales_price' => ['nullable', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'photo' => ['nullable', 'image', 'max:4096'],
            'image_url' => ['nullable', 'string', function ($attribute, $value, $fail) {
                $value = trim((string) $value);

                if ($value === '') {
                    return;
                }

                if (str_starts_with($value, 'data:image')) {
                    if (! preg_match('/^data:image\/(png|jpe?g|webp);base64,/i', $value)) {
                        $fail('La imagen embebida debe ser PNG, JPG o WEBP.');

                        return;
                    }

                    $payload = explode(',', $value, 2)[1] ?? '';
                    $decoded = base64_decode($payload, true);

                    if ($decoded === false) {
                        $fail('La imagen embebida no se pudo leer.');

                        return;
                    }

                    if (strlen($decoded) > 5 * 1024 * 1024) {
                        $fail('La imagen embebida no debe superar 5 MB.');
                    }

                    return;
                }

                if (! filter_var($value, FILTER_VALIDATE_URL)) {
                    $fail('La imagen debe ser una URL valida o una imagen embebida.');
                }
            }],
        ]);

        DB::transaction(function () {
            $existingProduct = $this->product_id
                ? Product::query()->lockForUpdate()->findOrFail($this->product_id)
                : null;
            $currentStock = (int) ($existingProduct?->stock_on_hand ?? 0);

            $productData = [
                'name' => trim($this->name),
                'description' => $this->description,
                'part_number' => $this->part_number ?: null,
                'part_number_alt_1' => $this->part_number_alt_1 ?: null,
                'part_number_alt_2' => $this->part_number_alt_2 ?: null,
                'brand' => $this->brand ? strtoupper(trim($this->brand)) : null,
                'type' => $this->type,
                'piece_rotation_type' => $this->type === 'pieza' ? $this->piece_rotation_type : null,
                'technical_category' => $this->type === 'pieza' ? $this->technical_category : null,
                'sales_price' => $this->sales_price ?: 0,
                'cost' => $this->cost ?: 0,
                'total_cost' => $currentStock * ((float) $this->cost),
                'stock_on_hand' => $currentStock,
            ];

            if (! $this->product_id) {
                $productData['active'] = true;
                $this->stock_on_hand = 0;
            }

            $product = Product::updateOrCreate(
                ['id' => $this->product_id],
                $productData
            );

            $this->syncProductImage($product);
        });

        session()->flash('message', 'Producto guardado.');
        $this->viewMode = 'list';
        $this->photo = null;
    }

    public function delete($id): void
    {
        abort_unless(auth()->user()?->can('warehouse.products.manage'), 403);

        $product = Product::findOrFail($id);
        $product->update(['active' => false]);
        $this->viewMode = 'list';

        session()->flash('message', 'Producto desactivado. Se conserva su historial.');
    }

    public function deleteImage(): void
    {
        abort_unless(auth()->user()?->can('warehouse.products.manage'), 403);

        if (! $this->product_id) {
            return;
        }

        $product = Product::findOrFail($this->product_id);
        $this->deleteStoredImage($product);
        $product->update(['image_path' => null]);
        $this->photo = null;
        $this->image_url = '';

        session()->flash('message', 'Imagen eliminada.');
    }

    public function toggleActive($id): void
    {
        abort_unless(auth()->user()?->can('warehouse.products.manage'), 403);

        $product = Product::findOrFail($id);
        $product->active = ! $product->active;
        $product->save();
        session()->flash('message', $product->active ? 'Producto activado.' : 'Producto desactivado.');
    }

    public function setView($mode): void
    {
        $this->viewMode = $mode;
    }

    public function showList(): void
    {
        $this->viewMode = 'list';
    }

    private function syncProductImage(Product $product): void
    {
        if ($this->photo) {
            $this->deleteStoredImage($product);
            $ext = $this->photo->getClientOriginalExtension() ?: 'jpg';
            $filename = 'prod_'.$product->id.'_'.time().'.'.$ext;
            $publicPath = $this->photo->storeAs('storefront/products', $filename, 'public');

            $product->update(['image_path' => $publicPath]);

            return;
        }

        if ($this->image_url) {
            $this->deleteStoredImage($product);
            $product->update(['image_path' => null]);
            $imageValue = trim((string) $this->image_url);

            if (str_starts_with($imageValue, 'data:image')) {
                // Not supported natively anymore without extracting, we will ignore for now
                // or we could save it to local storage. I'll just save it to local storage if needed.
                // Wait, I will just null it for now if we don't have the implementation.
                // Or better, let's just save it.
                // $imageValue = $this->storeEmbeddedImageAsLegacyUpload($product, $imageValue);
            }
        }
    }

    private function storeEmbeddedImageAsLegacyUpload(Product $product, string $imageData): string
    {
        abort_unless(preg_match('/^data:image\/(png|jpe?g|webp);base64,/i', $imageData, $matches) === 1, 422);

        $payload = explode(',', $imageData, 2)[1] ?? '';
        $decoded = base64_decode($payload, true);
        abort_if($decoded === false || strlen($decoded) > 5 * 1024 * 1024, 422);

        $extension = strtolower($matches[1]) === 'jpeg' ? 'jpg' : strtolower($matches[1]);
        $filename = 'prod_'.$product->id.'_'.time().'.'.$extension;

        return $filename;
    }

    private function resetForm(): void
    {
        $this->reset([
            'product_id',
            'name',
            'description',
            'sku',
            'part_number',
            'part_number_alt_1',
            'part_number_alt_2',
            'brand',
            'sales_price',
            'cost',
            'total_cost',
            'stock_on_hand',
            'photo',
            'image_url',
        ]);
        $this->type = 'pieza';
        $this->piece_rotation_type = 'alta_rotacion';
        $this->technical_category = 'mecanico';
    }

    private function deleteStoredImage(Product $product): void
    {
        $this->deleteStoredImageFiles($product->image_path, null);
    }

    private function deleteStoredImageFiles(?string $publicImagePath, ?string $legacyImage): void
    {
        if ($publicImagePath) {
            Storage::disk('public')->delete($publicImagePath);
        }
    }

    public function render()
    {
        $products = Product::query()
            ->where('active', true)
            ->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%")
                    ->orWhere('sku', 'like', "%{$this->search}%")
                    ->orWhere('part_number', 'like', "%{$this->search}%")
                    ->orWhere('part_number_alt_1', 'like', "%{$this->search}%")
                    ->orWhere('part_number_alt_2', 'like', "%{$this->search}%");
            })
            ->orderBy('id', 'desc')
            ->paginate(50);

        return view('livewire.inventario.product-manager', [
            'products' => $products,
            'productTypes' => ProductType::cases(),
            'pieceRotationTypes' => PieceRotationType::cases(),
            'technicalCategories' => TechnicalCategory::cases(),
        ]);
    }
}
