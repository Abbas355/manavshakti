<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Http\Traits\PaymentTrait;
use Illuminate\Http\Request;
use Anand\LaravelPaytmWallet\Facades\PaytmWallet;

class PaytmController extends Controller
{
    use PaymentTrait;

    public function pay()
    {
        try {
            // Getting payment info from session
            $job_payment_type = session('job_payment_type') ?? 'package_job';
            
            if ($job_payment_type == 'per_job') {
                $price = session('job_total_amount') ?? '100';
            } else {
                $plan = session('plan');
                $price = $plan->price;
            }

            // Amount conversion
            $amount = currencyConversion($price, 'INR', 1);
            $converted_amount = usdAmount($price);

            // Storing payment info in session
            session(['order_payment' => [
                'payment_provider' => 'paytm',
                'amount' => $amount,
                'currency_symbol' => '₹',
                'usd_amount' => $converted_amount,
            ]]);

            $user = auth()->user();
            $order_id = uniqid('paytm_');

            $payment = PaytmWallet::with('receive');
            $payment->prepare([
                'order' => $order_id,
                'user' => $user->id,
                'mobile_number' => $user->contact_number ?? '9999999999',
                'email' => $user->email,
                'amount' => $amount,
                'callback_url' => route('paytm.success'),
            ]);

            return $payment->receive();
        } catch (\Throwable $th) {
            session()->flash('error', $th->getMessage());
            return back();
        }
    }

    public function success(Request $request)
    {
        $transaction = PaytmWallet::with('receive');
        $response = $transaction->response();
        
        if ($transaction->isSuccessful()) {
            session(['transaction_id' => $response['TXNID'] ?? null]);
            $this->orderPlacing();
        } elseif ($transaction->isFailed()) {
            session()->flash('error', __('payment_was_failed'));
            return back();
        } elseif ($transaction->isOpen()) {
            session()->flash('error', __('payment_is_pending'));
            return back();
        } else {
            session()->flash('error', __('payment_was_failed'));
            return back();
        }
    }
}