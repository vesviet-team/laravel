<?php

namespace App\Mail;

use App\Models\AbandonedCart;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AbandonedCartIncentiveMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public AbandonedCart $abandonedCart,
        public string $couponCode,
        public int $discountPercent = 5
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🎁 Ưu đãi độc quyền dành riêng cho bạn: Giảm '.$this->discountPercent.'% đơn hàng',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.abandoned-cart-incentive',
        );
    }
}
