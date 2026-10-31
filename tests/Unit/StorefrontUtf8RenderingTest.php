<?php

namespace Tests\Unit;

use App\Enums\TechnicalCategory;
use App\Services\Storefront\ProductService;
use ReflectionMethod;
use Tests\TestCase;

class StorefrontUtf8RenderingTest extends TestCase
{
    public function test_storefront_category_labels_use_utf8_text_instead_of_html_entities(): void
    {
        $method = new ReflectionMethod(ProductService::class, 'categoryLabel');
        $label = $method->invoke(app(ProductService::class), TechnicalCategory::Mechanical->value);

        $this->assertSame('Mecánico', $label);
        $this->assertStringNotContainsString('&', $label);
        $this->assertSame('Mecánico', TechnicalCategory::Mechanical->label());
    }

    public function test_multibyte_initials_do_not_split_utf8_characters(): void
    {
        $this->assertSame('ÑU', mb_strtoupper(mb_substr('Ñuñez', 0, 2, 'UTF-8'), 'UTF-8'));
    }
}
