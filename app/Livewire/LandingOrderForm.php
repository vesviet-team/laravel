<?php

namespace App\Livewire;

use App\Actions\ProcessLandingOrderAction;
use App\Exceptions\CommerceException;
use App\Models\LandingPage;
use Livewire\Attributes\Throttle;
use Livewire\Attributes\Validate;
use Livewire\Component;

class LandingOrderForm extends Component
{
    public LandingPage $landingPage;

    // Form state
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|string|regex:/^(0[3-9][0-9]{8}|84[3-9][0-9]{8})$/')]
    public string $phone = '';

    #[Validate('required|string|max:500')]
    public string $address = '';

    #[Validate('nullable|string|max:500')]
    public string $note = '';

    #[Validate('nullable|string|max:100')]
    public string $selectedComboId = '';

    // Honeypot — must stay empty
    public string $website = '';

    // Result state
    public bool $isSubmitting = false;

    public ?array $successData = null;

    public string $errorMsg = '';

    public function mount(LandingPage $landingPage): void
    {
        $this->landingPage = $landingPage;

        // Pre-select first combo if available
        $combos = $landingPage->comboRules();
        if (! empty($combos)) {
            $this->selectedComboId = $combos[0]['id'] ?? '';
        }
    }

    /**
     * P0-04: Rate limit to 5 submissions per 60s per IP.
     * Prevents bot spam on landing pages (honeypot alone is insufficient).
     */
    #[Throttle(5, 60)]
    public function submitOrder(ProcessLandingOrderAction $action): void
    {
        // Honeypot check — bots fill this field
        if (! empty($this->website)) {
            $this->errorMsg = 'Có lỗi xảy ra, vui lòng thử lại.';

            return;
        }

        $this->validate();

        $this->isSubmitting = true;
        $this->errorMsg = '';

        try {
            $order = $action->execute($this->landingPage, [
                'name' => $this->name,
                'phone' => $this->phone,
                'address' => $this->address,
                'note' => $this->note,
                'selectedComboId' => $this->selectedComboId,
            ]);

            $combo = collect($this->landingPage->comboRules())
                ->firstWhere('id', $this->selectedComboId);

            $this->successData = [
                'order_reference' => $order->order_number,
                'payment_method' => 'cod',
                'estimated_delivery' => '2-3 ngày làm việc',
                'total_amount' => $order->total_amount,
                'combo_name' => $combo['name'] ?? null,
            ];

            // Fire tracking events to the browser for pixel integration
            $this->dispatch('order-placed', [
                'value' => $order->total_amount,
                'currency' => 'VND',
                'facebook_pixel_id' => $this->landingPage->facebook_pixel_id,
                'tiktok_pixel_id' => $this->landingPage->tiktok_pixel_id,
            ]);

            // Reset form fields
            $this->name = '';
            $this->phone = '';
            $this->address = '';
            $this->note = '';

        } catch (CommerceException|\RuntimeException $e) {
            $this->errorMsg = $e->getMessage();
        } catch (\Throwable $e) {
            $this->errorMsg = 'Lỗi hệ thống, vui lòng thử lại hoặc liên hệ hỗ trợ.';
            report($e);
        } finally {
            $this->isSubmitting = false;
        }
    }

    public function render()
    {
        return view('livewire.landing-order-form');
    }
}
