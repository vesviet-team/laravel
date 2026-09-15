<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class LimitConcurrentSessions
{
    /**
     * Maximum number of concurrent sessions allowed per customer.
     */
    protected int $maxSessions = 5;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip session tracking for logout route
        if ($request->is('*/account/logout') && $request->isMethod('post')) {
            return $next($request);
        }

        if (! Auth::guard('customer')->check()) {
            return $next($request);
        }

        $customer = Auth::guard('customer')->user();
        $sessionId = Session::getId();

        // Store the current session ID for this customer
        $this->trackSession($customer, $sessionId);

        // Check if we've exceeded the maximum concurrent sessions
        if ($this->exceedsMaxSessions($customer)) {
            // Remove the oldest session (first in, first out)
            $this->revokeOldestSession($customer);
        }

        return $next($request);
    }

    /**
     * Track the current session for the customer.
     */
    protected function trackSession($customer, string $sessionId): void
    {
        $sessions = $customer->active_sessions ?? [];

        // Add current session if not already tracked
        if (! in_array($sessionId, $sessions)) {
            $sessions[] = $sessionId;
            $customer->active_sessions = $sessions;
            $customer->saveQuietly();
        }
    }

    /**
     * Check if the customer has exceeded the maximum concurrent sessions.
     */
    protected function exceedsMaxSessions($customer): bool
    {
        $sessions = $customer->active_sessions ?? [];

        return count($sessions) > $this->maxSessions;
    }

    /**
     * Revoke the oldest session for the customer.
     */
    protected function revokeOldestSession($customer): void
    {
        $sessions = $customer->active_sessions ?? [];

        if (empty($sessions)) {
            return;
        }

        $oldestSessionId = array_shift($sessions);
        $customer->active_sessions = $sessions;
        $customer->saveQuietly();

        // In a real implementation, you would also delete the session from the database
        // \DB::table('sessions')->where('id', $oldestSessionId)->delete();
    }
}
