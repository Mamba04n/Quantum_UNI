<?php

namespace App\Services\Storefront;

use App\Domain\Clientes\Services\CustomerService;
use App\Models\ActivityLog;
use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentSequenceService;
use App\Support\StorefrontUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuoteService
{
    public function __construct(private readonly CartService $cart) {}

    public function create(array $customer): array
    {
        $items = $this->cart->items();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Agrega al menos un producto antes de solicitar la cotización.']);
        }

        return DB::transaction(function () use ($customer, $items) {
            $accountCreated = false;
            $tempPassword = null;
            $userAlreadyExisted = false;

            $email = mb_strtolower(trim($customer['email']));

            if (auth()->check()) {
                $user = auth()->user();
            } else {
                $user = User::whereRaw('LOWER(TRIM(email)) = ?', [$email])->first();

                if (! $user) {
                    $accountCreated = true;
                    $tempPassword = filled($customer['password'] ?? null)
                        ? (string) $customer['password']
                        : Str::password(10, numbers: true, symbols: false);

                    $user = User::create([
                        'name' => $customer['name'],
                        'email' => $email,
                        'phone' => $customer['phone'],
                        'password' => Hash::make($tempPassword),
                        'is_active' => true,
                        'email_verified_at' => now(),
                    ]);

                    $role = Role::where('slug', User::ROLE_CLIENTE_TIENDA)->first();
                    if ($role) {
                        $user->syncRoles([$role->id]);
                    }

                    try {
                        app(CustomerService::class)->createOrUpdateFromUser($user, 'storefront_quote');
                    } catch (\Throwable) {
                        // Resiliente ante variaciones de validación no críticas
                    }
                } else {
                    $userAlreadyExisted = true;
                }
            }

            // Reservar referencia oficial de cotización ERP (SC-XXXXX)
            $reference = app(DocumentSequenceService::class)->formattedNext(
                'customer_request',
                'SC-',
                5,
                1
            );

            $firstItem = $items->first();
            $quoteToName = filled($customer['company'] ?? null) ? $customer['company'] : $customer['name'];
            $contactName = filled($customer['contact_name'] ?? null) ? $customer['contact_name'] : $customer['name'];
            $subtotal = $this->cart->subtotal();

            // Crear Solicitud de Cotización en el ERP con estado 'recibida' (entra directo a Licitación Proveedor)
            $quotation = QuotationRequest::create([
                'reference' => $reference,
                'submission_token' => (string) Str::uuid(),
                'date' => now()->toDateString(),
                'quote_to_name' => $quoteToName,
                'quote_to_email' => $customer['email'],
                'quote_to_ruc' => trim($customer['ruc'] ?? '') ?: null,
                'customer_contact_name' => $contactName,
                'requested_by' => $user?->name ?: $customer['name'],
                'department' => 'ventas',
                'source_channel' => 'web',
                'machinery' => $customer['machinery'] ?? '',
                'machine_brand' => $customer['machine_brand'] ?? null,
                'machine_model' => $customer['machine_model'] ?? null,
                'equipment_serial' => $customer['equipment_serial'] ?? null,
                'part_code' => $firstItem?->part_number ?: $firstItem?->sku ?: 'N/D',
                'request_quantity' => $items->sum('cart_quantity'),
                'request_urgency' => $customer['urgency'] ?? 'media',
                'subtotal' => $subtotal,
                'total_amount' => $subtotal,
                'status' => QuotationRequest::STATUS_RECEIVED, // 'recibida' -> Licitación Proveedor
                'notes' => $customer['notes'] ?? null,
                'user_id' => $user?->id,
            ]);

            foreach ($items as $product) {
                QuotationRequestItem::create([
                    'quotation_request_id' => $quotation->id,
                    'product_id' => $product->id,
                    'part_name' => $product->name,
                    'part_number' => $product->part_number ?: $product->sku ?: 'N/D',
                    'description' => $product->name,
                    'quantity' => $product->cart_quantity,
                    'unit' => $product->unit ?: 'PZA',
                    'notes' => null,
                    'unit_price' => $product->sales_price ?? 0,
                    'line_total' => $product->cart_line_total ?? 0,
                    'internal_request_ref' => $reference,
                    'brand' => $product->brand ?: null,
                    'model' => null,
                    'serial_number' => null,
                    'machine_brand' => $customer['machine_brand'] ?? null,
                    'machine_model' => $customer['machine_model'] ?? null,
                    'image_path' => $product->image_path ?: null,
                    'status' => 'pending_review',
                ]);
            }

            // Conservar registro en storefront_orders como trazabilidad web
            $phoneDest = preg_replace('/\D+/', '', (string) config('services.storefront.whatsapp_owner', ''));

            $storefrontOrderId = DB::table('storefront_orders')->insertGetId([
                'codigo' => $reference,
                'guest_token' => hash('sha256', request()->ip().'|'.request()->userAgent()),
                'ip' => request()->ip(),
                'user_agent' => Str::limit((string) request()->userAgent(), 255, ''),
                'canal' => 'storefront_livewire',
                'estado' => 'cotizacion_erp',
                'nombre_cliente' => $customer['name'],
                'email_cliente' => $customer['email'],
                'telefono_cliente' => $customer['phone'],
                'empresa_cliente' => $customer['company'] ?? null,
                'notas_cliente' => $customer['notes'] ?? null,
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'moneda' => 'USD',
                'whatsapp_destino' => $phoneDest ?: null,
                'creado_en' => now(),
                'actualizado_en' => now(),
            ]);

            foreach ($items as $product) {
                DB::table('storefront_order_items')->insert([
                    'pedido_id' => $storefrontOrderId,
                    'product_id' => $product->id,
                    'nombre' => $product->name,
                    'sku' => $product->sku,
                    'cantidad' => $product->cart_quantity,
                    'precio_unitario' => $product->sales_price,
                    'subtotal' => $product->cart_line_total,
                    'imagen_url' => StorefrontUrl::productImage($product->image_path),
                    'detalle_url' => StorefrontUrl::product($product),
                    'creado_en' => now(),
                ]);
            }

            ActivityLog::log('ventas', 'customer_request_created', "Solicitud de cotización {$reference} registrada desde la Tienda Web por {$customer['name']}.", [
                'quotation_request_id' => $quotation->id,
                'source_channel' => 'web',
                'items_count' => $items->count(),
                'user_id' => $user?->id,
            ]);

            try {
                $recipient = config('mail.contact_recipient') ?: config('mail.from.address');
                if ($recipient) {
                    \Illuminate\Support\Facades\Notification::route('mail', $recipient)
                        ->notify(new \App\Notifications\WebQuotationRequestNotification($quotation));
                }
            } catch (\Throwable $e) {
                report($e);
            }

            $this->cart->clear();

            return [
                'id' => $quotation->id,
                'code' => $reference,
                'order_id' => $storefrontOrderId,
                'name' => $customer['name'],
                'email' => $customer['email'],
                'phone' => $customer['phone'],
                'account_created' => $accountCreated,
                'temp_password' => $tempPassword,
                'user_already_existed' => $userAlreadyExisted,
                'is_logged_in' => auth()->check(),
            ];
        });
    }
}
