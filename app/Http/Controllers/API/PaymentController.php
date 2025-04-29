<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    // Constants
    public const METHODS = ['Tunai', 'Transfer', 'QRIS'];
    public const STATUSES = ['pending', 'success', 'failed'];

    /**
     * Mendapatkan pembayaran untuk pemilik (semua pembayaran untuk properti mereka)
     */
    public function ownerIndex(Request $request)
    {
        $ownerId = auth()->id();
        
        $payments = Payment::with(['booking.property.rooms', 'booking.user'])
            ->whereHas('booking.property', function($query) use ($ownerId) {
                $query->where('user_id', $ownerId);
            })
            ->paginate(10);

        return response()->json([
            'message' => 'Pembayaran berhasil diambil',
            'data' => $payments
        ]);
    }

    /**
     * Mendapatkan pembayaran untuk pengguna (pembayaran mereka sendiri)
     */
    public function userIndex(Request $request)
    {
        $userId = auth()->id();
        
        $payments = Payment::with(['booking.property.rooms'])
            ->whereHas('booking', function($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->paginate(10);

        return response()->json([
            'message' => 'Pembayaran berhasil diambil',
            'data' => $payments
        ]);
    }

    /**
     * Membuat pembayaran untuk sebuah booking (hanya untuk pengguna)
     */
    public function userStore(Request $request, Booking $booking)
    {
        if ($booking->user_id !== auth()->id()) {
            return response()->json(['message' => 'Tidak sah'], 403);
        }

        $request->validate([
            'method' => 'required|in:' . implode(',', self::METHODS),
            'transfer_proof' => 'required_if:method,Transfer,QRIS|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        if ($booking->status !== 'pending') {
            return response()->json([
                'message' => 'Status booking tidak dapat dibayar'
            ], 400);
        }

        if ($booking->payments()->exists()) {
            return response()->json([
                'message' => 'Pembayaran sudah ada untuk booking ini'
            ], 400);
        }

        try {
            $payment = new Payment();
            $payment->booking_id = $booking->id;
            $payment->amount = $booking->total_price;
            $payment->method = $request->method;
            
            if (in_array($request->method, ['Transfer', 'QRIS'])) {
                $path = $request->file('transfer_proof')->store('payment_proofs', 'public');
                $payment->transfer_proof = $path;
            }

            $payment->save();

            return response()->json([
                'message' => 'Pembayaran berhasil dibuat',
                'data' => $payment
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Pembayaran gagal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mendapatkan detail pembayaran untuk pengguna
     */
    public function userShow(Booking $booking, Payment $payment)
    {
        if ($booking->user_id !== auth()->id() || $payment->booking_id !== $booking->id) {
            return response()->json(['message' => 'Tidak sah'], 403);
        }

        return response()->json([
            'message' => 'Detail pembayaran berhasil diambil',
            'data' => $payment->load(['booking.property.rooms'])
        ]);
    }

    /**
     * Mendapatkan detail pembayaran untuk pemilik
     */
    public function ownerShow(Payment $payment)
    {
        if ($payment->booking->property->user_id !== auth()->id()) {
            return response()->json(['message' => 'Tidak sah'], 403);
        }

        return response()->json([
            'message' => 'Detail pembayaran berhasil diambil',
            'data' => $payment->load(['booking.property.rooms', 'booking.user'])
        ]);
    }

    /**
     * Memperbarui status pembayaran (hanya untuk pemilik)
     */
    public function ownerUpdateStatus(Request $request, Payment $payment)
    {
        if ($payment->booking->property->user_id !== auth()->id()) {
            return response()->json(['message' => 'Tidak sah'], 403);
        }

        $request->validate([
            'status' => 'required|in:' . implode(',', self::STATUSES)
        ]);

        $payment->status = $request->status;
        
        if ($request->status === 'success') {
            $payment->paid_at = now();
            $payment->booking->update(['status' => 'confirmed']);
        }

        $payment->save();

        return response()->json([
            'message' => 'Status pembayaran berhasil diperbarui',
            'data' => $payment
        ]);
    }
}
