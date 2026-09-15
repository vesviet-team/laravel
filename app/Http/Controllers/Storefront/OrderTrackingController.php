<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderTrackingController extends Controller
{
    public function index(Request $request)
    {
        $order = null;
        $order_number = $request->query('order_number');
        $contact_info = $request->query('contact_info');

        if ($order_number && $contact_info) {
            $order = Order::where('order_number', $order_number)
                ->where(function ($query) use ($contact_info) {
                    $query->where('email', $contact_info)
                        ->orWhere('phone', $contact_info);
                })
                ->with('items')
                ->first();

            if (! $order) {
                // Return an error to avoid enumeration attacks but provide feedback
                return view('storefront.tracking.index', compact('order', 'order_number'))->with('error', 'Không tìm thấy đơn hàng với thông tin đã cung cấp.');
            }
        }

        return view('storefront.tracking.index', compact('order', 'order_number'));
    }

    public function track(Request $request)
    {
        $request->validate([
            'order_number' => 'required|string',
            'contact_info' => 'required|string',
        ]);

        return redirect()->route('track-order.index', [
            'order_number' => $request->order_number,
            'contact_info' => $request->contact_info,
        ]);
    }
}
