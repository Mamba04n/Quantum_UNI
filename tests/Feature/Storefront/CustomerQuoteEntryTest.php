<?php

namespace Tests\Feature\Storefront;

use App\Livewire\Storefront\Cart;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerQuoteEntryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_quote_entry_requires_login_and_keeps_the_cart(): void
    {
        $product = Product::factory()->create(['active' => true]);
        $this->withSession(['storefront.cart' => [$product->id => 2]])
            ->get(route('customer.quote'))->assertRedirect(route('login'))
            ->assertSessionHas('url.intended', route('customer.quote'))
            ->assertSessionHas('storefront.cart', [$product->id => 2]);
    }

    public function test_customer_login_returns_to_quote_entry_without_losing_items(): void
    {
        $role = Role::firstOrCreate(['slug' => User::ROLE_CLIENTE_TIENDA], ['name' => 'Cliente tienda']);
        $user = User::factory()->create(['password' => Hash::make('QuoteEntry-Testing-93!'), 'is_active' => true]);
        $user->roles()->syncWithoutDetaching([$role->id]);
        $product = Product::factory()->create(['active' => true]);
        $this->withSession(['url.intended' => route('customer.quote'), 'storefront.cart' => [$product->id => 2]])
            ->post(route('login'), ['email' => $user->email, 'password' => 'QuoteEntry-Testing-93!'])
            ->assertRedirect(route('customer.quote'))
            ->assertSessionHas('storefront.cart', [$product->id => 2]);
    }

    public function test_authenticated_cart_prefills_contact_and_quote_entry_keeps_verification(): void
    {
        $user = User::factory()->create(['phone' => '83262783', 'email_verified_at' => now()]);
        $this->actingAs($user)->get(route('customer.quote'))->assertRedirect(route('storefront.cart'));
        Livewire::actingAs($user)->test(Cart::class)->assertSet('name', $user->name)->assertSet('email', $user->email)->assertSet('phone', '83262783');
        $user->forceFill(['email_verified_at' => null])->save();
        $this->get(route('customer.quote'))->assertRedirect(route('verification.notice'));
    }

    public function test_registration_continuation_is_whitelisted_and_does_not_create_an_account(): void
    {
        $this->get(route('register', ['continue' => 'quote']))->assertOk()->assertSessionHas('url.intended', route('customer.quote'));
        $this->withSession(['url.intended' => route('customer.portal')])->get(route('register', ['continue' => 'https://example.invalid']))
            ->assertOk()->assertSessionHas('url.intended', route('customer.portal'));
    }
}
