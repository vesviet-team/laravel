<?php

namespace App\Mail;

use App\Models\AbandonedCart;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AbandonedCartReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public AbandonedCart $abandonedCart
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Giỏ hàng của bạn đang chờ — Hoàn tất đặt hàng tại '.config('app.name', 'Sober Furniture'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.abandoned-cart-reminder',
        );
    }
}
