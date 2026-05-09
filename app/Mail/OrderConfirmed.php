<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    public $order;

    /**
     * Create a new message instance.
     */
    public function __construct(Order $order)
    {
        $this->order = $order->load(['orderProducts.product', 'user']);
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Order Confirmation - ' . $this->order->receipt_number)
                    ->view('emails.order-confirmed');
    }
}
