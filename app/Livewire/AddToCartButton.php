<?php

namespace App\Livewire;

use App\Models\Product;
use App\Services\CartService;
use Livewire\Component;

class AddToCartButton extends Component
{
    public Product $product;

    public int $quantity = 1;

    public ?int $variantId = null;

    public function mount(Product $product): void
    {
        $this->product = $product;
        if ($this->product->variants->count() > 0) {
            $this->variantId = $this->product->variants->first()->id;
        }
    }

    public function increment(): void
    {
        $this->quantity++;
    }

    public function decrement(): void
    {
        if ($this->quantity > 1) {
            $this->quantity--;
        }
    }

    public function addToCart(CartService $cartService): void
    {
        if ($this->product->stock <= 0) {
            $this->dispatch('toast', message: 'Sản phẩm đã hết hàng.', type: 'error');

            return;
        }

        // Delegate all cart logic to CartService — single source of truth for cart state.
        $cartService->add($this->product->id, $this->variantId, $this->quantity);

        // U1: Toast notification feedback
        $this->dispatch('toast', message: 'Đã thêm vào giỏ hàng!', type: 'success');
        $this->dispatch('cart-updated');
        $this->dispatch('open-cart');
    }

    public function render()
    {
        return view('livewire.add-to-cart-button');
    }
}
