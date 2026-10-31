<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('mensajes', 'storefront_contact_messages');
        Schema::rename('solicitudes_servicio', 'storefront_service_requests');
        Schema::rename('pedidos_web', 'storefront_orders');
        Schema::rename('pedidos_web_items', 'storefront_order_items');
        Schema::rename('blog_tecnico', 'tech_blog_posts');
        Schema::rename('catalogos', 'catalogs');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('storefront_contact_messages', 'mensajes');
        Schema::rename('storefront_service_requests', 'solicitudes_servicio');
        Schema::rename('storefront_orders', 'pedidos_web');
        Schema::rename('storefront_order_items', 'pedidos_web_items');
        Schema::rename('tech_blog_posts', 'blog_tecnico');
        Schema::rename('catalogs', 'catalogos');
    }
};
