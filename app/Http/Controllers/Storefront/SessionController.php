<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionController extends Controller
{
    public function index()
    {
        $customer = Auth::guard('customer')->user();
        $currentSessionId = $request->session()->getId();

        $sessions = collect($customer->active_sessions ?? [])->map(function ($sessionId) use ($currentSessionId) {
            return [
                'id' => $sessionId,
                'is_current' => $sessionId === $currentSessionId,
                'device' => 'Unknown Device', // In real app, store device info
                'ip' => 'Unknown IP', // In real app, store IP
                'last_active' => now()->toISOString(), // In real app, store timestamp
            ];
        })->values();

        return view('storefront.account.sessions', compact('sessions', 'currentSessionId'));
    }

    public function destroy(Request $request, string $sessionId): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();
        $currentSessionId = $request->session()->getId();

        // Prevent revoking current session from this page (use logout instead)
        if ($sessionId === $currentSessionId) {
            return back()->with('error', 'Không thể thu hồi phiên hiện tại. Hãy sử dụng nút Đăng Xuất.');
        }

        $sessions = $customer->active_sessions ?? [];
        $sessions = array_values(array_filter($sessions, fn ($id) => $id !== $sessionId));
        $customer->active_sessions = $sessions;
        $customer->saveQuietly();

        // In a real implementation, also delete the session from the database
        // \DB::table("sessions")->where("id", $sessionId)->delete();

        return back()->with('success', 'Đã thu hồi phiên đăng nhập.');
    }

    public function destroyAll(Request $request): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();
        $currentSessionId = $request->session()->getId();

        // Keep only current session
        $customer->active_sessions = [$currentSessionId];
        $customer->saveQuietly();

        // In a real implementation, delete all other sessions from database
        // \DB::table("sessions")->where("id", "!=", $currentSessionId)->delete();

        return back()->with('success', 'Đã thu hồi tất cả phiên đăng nhập khác.');
    }
}
