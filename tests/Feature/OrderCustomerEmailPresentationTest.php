<?php

namespace Tests\Feature;

use App\Models\OrderStatus;
use App\Models\OrderStatusItem;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Notifications\OrderPhaseUpdated;
use App\Notifications\OrderStatusUpdated;
use App\Support\OrderEmailPresentation;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class OrderCustomerEmailPresentationTest extends TestCase
{
    public function test_every_order_status_has_logo_photo_short_description_currency_and_plain_text(): void
    {
        $path = public_path('images/mail-review-test-'.Str::uuid().'.png');
        $image = imagecreatetruecolor(320, 200);
        imagepng($image, $path);
        imagedestroy($image);
        try {
            $product = (new Product)->forceFill(['name' => 'Filtro de prueba', 'description' => '<p>Filtración de aceite para maquinaria industrial.</p>', 'image_path' => 'images/'.basename($path)]);
            $item = (new SaleOrderItem)->forceFill(['description' => 'Filtro de prueba', 'part_number' => 'TEST-100', 'quantity' => 1.5, 'total_price' => 25]);
            $item->setRelation('product', $product);
            $order = (new SaleOrder)->forceFill(['reference' => 'PRUEBA-NO-REAL', 'client_name' => 'Cliente de prueba', 'currency' => 'USD', 'total_amount' => 25]);
            $order->setRelation('items', collect([$item]));
            foreach (['cotizacion', 'solicitado_proveedor', 'en_transito_aduanas', 'en_inventario_local', 'en_ruta', 'entregado', 'cancelado', 'generated', 'pending_purchase', 'confirmed', 'desconocido'] as $status) {
                $order->status = $status;
                $mail = (new OrderStatusUpdated($order))->toMail($order);
                $html = view($mail->view[0], $mail->viewData)->render();
                $this->assertStringContainsString('cid:', $html);
                $this->assertStringContainsString('Quantum Soluciones', $html);
                $this->assertStringContainsString('Filtración de aceite', $html);
                $this->assertStringContainsString('US$25.00', $html);
                $this->assertStringContainsString('1.5', $html);
                if ($status === 'cotizacion') {
                    $this->assertSame('Cotización', $mail->viewData['statusLabel']);
                }
                $this->assertStringNotContainsString('C$25.00', $html);
                $this->assertStringContainsString('Ver mi pedido', view($mail->view[1], $mail->viewData)->render());
                $email = (new Email)->from('test@example.test')->to('test-recipient@example.test')->html($html);
                foreach ($mail->callbacks as $callback) {
                    $callback($email);
                }
                $this->assertCount(2, $email->getAttachments());
                $this->assertTrue(collect($email->getAttachments())->every(fn ($part) => $part->getDisposition() === 'inline'));
            }
            $status = (new OrderStatus)->setRelation('saleOrder', $order);
            $line = (new OrderStatusItem)->forceFill(['part_name' => 'Filtro de prueba', 'part_number' => 'TEST-100', 'quantity_ordered' => 2]);
            $line->setRelation('product', $product);
            $mail = (new OrderPhaseUpdated($status, $line, 'Aduana', 'En aduana', 'Actualización de la partida.'))->toMail($order);
            $html = view($mail->view[0], $mail->viewData)->render();
            $this->assertStringContainsString('únicamente al producto indicado', $html);
            $this->assertStringContainsString('Filtración de aceite', $html);
            $this->assertStringNotContainsString('Total del pedido', $html);
        } finally {
            @unlink($path);
        }
    }

    public function test_missing_and_untrusted_images_fail_safely_and_summaries_are_short(): void
    {
        $this->assertNull(OrderEmailPresentation::productImage('../../.env'));
        $this->assertNull(OrderEmailPresentation::productImage('https://untrusted.invalid/photo.png'));
        $this->assertNull(OrderEmailPresentation::productImage('non-existent.png'));
        $this->assertLessThanOrEqual(153, mb_strlen(OrderEmailPresentation::summary(str_repeat('Descripción ', 200), 'Título')));
        $this->assertSame('C$', OrderEmailPresentation::currency('NIO'));
        $this->assertSame('US$', OrderEmailPresentation::currency('USD'));
    }

    public function test_storage_images_resolve_without_a_public_storage_symlink(): void
    {
        $relative = 'mail-review-'.Str::uuid().'.png';
        $path = storage_path('app/public/'.$relative);
        $image = imagecreatetruecolor(32, 32);
        imagepng($image, $path);
        imagedestroy($image);
        try {
            $this->assertNotNull(OrderEmailPresentation::productImage($relative));
            $this->assertNotNull(OrderEmailPresentation::productImage('storage/'.$relative));
        } finally {
            @unlink($path);
        }
    }
}
