<?php

namespace App\Livewire\Inventario;

use App\Enums\PieceRotationType;
use App\Enums\ProductType;
use App\Enums\StockMovementType;
use App\Enums\TechnicalCategory;
use App\Models\Product;
use App\Models\StockMove;
use App\Models\StockMoveAttachment;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class StockMovement extends Component
{
    use WithFileUploads;

    public $product_id = '';

    public $product_sku = '';

    public $type = 'in';

    public $movement_date;

    public $quantity = 1;

    public $cost_in = 0;

    public $cost_out = 0;

    public $reference = '';

    public array $attachments = [];

    public $filter_product_id = '';

    public $new_product_name = '';

    public $new_product_description = '';

    public $new_product_brand = '';

    public $new_product_part_number = '';

    public $new_product_part_number_alt_1 = '';

    public $new_product_part_number_alt_2 = '';

    public $new_product_type = 'pieza';

    public $new_product_piece_rotation_type = 'alta_rotacion';

    public $new_product_technical_category = 'mecanico';

    public $new_product_sales_price = 0;

    public $new_product_image_url = '';

    public $resolvedExistingProductId = null;

    public function mount(): void
    {
        $this->movement_date = now()->toDateString();
    }

    public function updatedProductId($value): void
    {
        $product = $value ? Product::find($value) : null;
        $this->resolvedExistingProductId = $product?->id;
        $this->product_sku = $product?->sku ?? '';
    }

    public function updatedProductSku($value): void
    {
        $sku = strtoupper(trim((string) $value));
        $this->product_sku = $sku;
        $product = $sku ? Product::where('sku', $sku)->first() : null;
        $this->resolvedExistingProductId = $product?->id;
        $this->product_id = $product?->id ?? '';
    }

    public function updatedAttachments(): void
    {
        $this->validateOnly('attachments', [
            'attachments' => ['required', 'array', 'min:1', 'max:10'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf,xlsx,xls,csv,doc,docx', 'max:10240'],
        ]);
    }

    public function processMovement(InventoryService $inventoryService): void
    {
        abort_unless(auth()->user()?->can('warehouse.inventory.view'), 403);

        $this->validate([
            'product_id' => ['nullable', 'exists:products,id'],
            'product_sku' => ['nullable', 'string', 'max:100'],
            'type' => ['required', 'in:in,out'],
            'movement_date' => ['required', 'date'],
            'quantity' => ['required', 'integer', 'min:1'],
            'cost_in' => ['nullable', 'required_if:type,in', 'numeric', 'min:0'],
            'cost_out' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:255'],
            'attachments' => ['required', 'array', 'min:1', 'max:10'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf,xlsx,xls,csv,doc,docx', 'max:10240'],
            'new_product_name' => ['nullable', 'string', 'max:255'],
            'new_product_description' => ['nullable', 'string'],
            'new_product_brand' => ['nullable', 'string', 'max:255'],
            'new_product_part_number' => ['nullable', 'string', 'max:180'],
            'new_product_part_number_alt_1' => ['nullable', 'string', 'max:180'],
            'new_product_part_number_alt_2' => ['nullable', 'string', 'max:180'],
            'new_product_type' => ['nullable', Rule::in(ProductType::values())],
            'new_product_piece_rotation_type' => ['nullable', 'required_if:new_product_type,pieza', Rule::in(PieceRotationType::values())],
            'new_product_technical_category' => ['nullable', 'required_if:new_product_type,pieza', Rule::in(TechnicalCategory::values())],
            'new_product_sales_price' => ['nullable', 'numeric', 'min:0'],
            'new_product_image_url' => ['nullable', 'string', function ($attribute, $value, $fail) {
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

        try {
            $movementType = StockMovementType::from($this->type);
            $requiredPermission = $movementType === StockMovementType::In
                ? 'warehouse.kardex.entry'
                : 'warehouse.kardex.output';

            abort_unless(auth()->user()?->can($requiredPermission), 403);

            $product = $this->resolveProduct($movementType);
            $storedAttachments = collect($this->attachments)
                ->map(fn ($file) => [
                    'path' => $file->store('inventory-movements', 'private'),
                    'name' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            $attachmentPath = $storedAttachments->pluck('path')
                ->first(fn ($path) => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true))
                ?: $storedAttachments->first()['path'];

            if ($movementType === StockMovementType::In) {
                $this->syncProductImageFromEntryAttachment($product, $attachmentPath);
            }

            $move = $inventoryService->recordMovement(
                productId: $product->id,
                type: $movementType,
                quantity: (int) $this->quantity,
                movementDate: $this->movement_date,
                costIn: $this->type === 'in' ? (float) $this->cost_in : null,
                costOut: $this->type === 'out' ? (float) $this->cost_out : null,
                reference: $this->reference ?: null,
                attachmentPath: $attachmentPath
            );

            $storedAttachments->each(fn (array $attachment) => StockMoveAttachment::create([
                'stock_move_id' => $move->id,
                'file_path' => $attachment['path'],
                'file_name' => $attachment['name'],
                'mime_type' => $attachment['mime'],
                'file_size' => $attachment['size'],
                'uploaded_by' => auth()->id(),
            ]));

            session()->flash('message', "{$movementType->label()} registrada. Referencia: {$move->reference}");
            $this->reset([
                'product_id',
                'product_sku',
                'quantity',
                'cost_in',
                'cost_out',
                'reference',
                'type',
                'attachments',
                'new_product_name',
                'new_product_description',
                'new_product_brand',
                'new_product_part_number',
                'new_product_part_number_alt_1',
                'new_product_part_number_alt_2',
                'new_product_sales_price',
                'new_product_image_url',
                'resolvedExistingProductId',
            ]);
            $this->type = 'in';
            $this->quantity = 1;
            $this->movement_date = now()->toDateString();
            $this->new_product_type = 'pieza';
            $this->new_product_piece_rotation_type = 'alta_rotacion';
            $this->new_product_technical_category = 'mecanico';
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', \App\Support\UserMessage::error($e, 'No se pudo registrar el movimiento. Revisa los datos e intenta de nuevo.'));
        }
    }

    private function resolveProduct(StockMovementType $movementType): Product
    {
        $product = null;

        if ($this->product_id) {
            $product = Product::find($this->product_id);
        }

        if (! $product && $this->product_sku) {
            $product = Product::where('sku', strtoupper(trim($this->product_sku)))->first();
        }

        if ($product) {
            return $product;
        }

        if ($movementType === StockMovementType::Out) {
            throw new \InvalidArgumentException('Para registrar una salida debes seleccionar un producto existente.');
        }

        if (! trim($this->new_product_name)) {
            throw new \InvalidArgumentException('Para crear un producto desde Kardex debes indicar el nombre del producto.');
        }

        return Product::create([
            'name' => trim($this->new_product_name),
            'description' => $this->new_product_description ?: null,
            'brand' => $this->new_product_brand ? strtoupper(trim($this->new_product_brand)) : null,
            'part_number' => $this->new_product_part_number ?: null,
            'part_number_alt_1' => $this->new_product_part_number_alt_1 ?: null,
            'part_number_alt_2' => $this->new_product_part_number_alt_2 ?: null,
            'type' => $this->new_product_type ?: ProductType::Piece->value,
            'piece_rotation_type' => $this->new_product_type === ProductType::Piece->value ? $this->new_product_piece_rotation_type : null,
            'technical_category' => $this->new_product_type === ProductType::Piece->value ? $this->new_product_technical_category : null,
            'sales_price' => $this->new_product_sales_price ?: 0,
            'cost' => 0,
            'total_cost' => 0,
            'stock_on_hand' => 0,
            'active' => true,
        ]);
    }

    public function render()
    {
        $selectedProduct = $this->resolvedExistingProductId
            ? Product::find($this->resolvedExistingProductId)
            : null;

        $products = Product::where('active', true)
            ->where('type', '!=', 'servicio')
            ->orderBy('name')
            ->get();

        $recentMoves = StockMove::with(['product', 'attachments'])
            ->when($this->filter_product_id, fn ($q) => $q->where('product_id', $this->filter_product_id))
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return view('livewire.inventario.stock-movement', [
            'products' => $products,
            'recentMoves' => $recentMoves,
            'movementTypes' => StockMovementType::cases(),
            'productTypes' => ProductType::cases(),
            'pieceRotationTypes' => PieceRotationType::cases(),
            'technicalCategories' => TechnicalCategory::cases(),
            'selectedProduct' => $selectedProduct,
        ]);
    }

    /**
     * The image attached to a Kardex entry is the received product photo.
     * Keep the movement evidence intact and copy it to the public product
     * location used by the catalog/storefront.
     */
    private function syncProductImageFromEntryAttachment(Product $product, ?string $attachmentPath): void
    {
        if (! $attachmentPath) {
            return;
        }

        $extension = pathinfo($attachmentPath, PATHINFO_EXTENSION) ?: 'jpg';
        $filename = 'prod_'.$product->id.'_kardex_'.now()->format('YmdHis').'.'.$extension;
        $productImagePath = 'storefront/products/'.$filename;

        $source = Storage::disk('private')->readStream($attachmentPath);

        if (! is_resource($source)) {
            throw new \RuntimeException('No se encontro la evidencia privada del movimiento.');
        }

        try {
            $stored = Storage::disk('public')->writeStream($productImagePath, $source);
        } finally {
            fclose($source);
        }

        if (! $stored || ! Storage::disk('public')->exists($productImagePath)) {
            throw new \RuntimeException('No se pudo preparar la imagen del producto para el catálogo.');
        }

        $product->update(['image_path' => $productImagePath]);
    }
}
