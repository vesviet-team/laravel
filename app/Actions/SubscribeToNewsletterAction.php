<?php

namespace App\Actions;

use App\Models\NewsletterSubscriber;
use Illuminate\Database\UniqueConstraintViolationException;

class SubscribeToNewsletterAction
{
    /**
     * Subscribe an email address to the newsletter.
     *
     * Returns true on successful new subscription.
     * Returns false if email already exists (silently — no error exposed to user).
     */
    public function handle(string $email, ?string $ipAddress = null): bool
    {
        try {
            NewsletterSubscriber::create([
                'email' => strtolower(trim($email)),
                'ip_address' => $ipAddress,
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            // Email already subscribed — treat as success silently (no enumeration).
            return false;
        }
    }
}
