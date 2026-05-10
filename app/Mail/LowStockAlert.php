<?php

namespace App\Mail;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LowStockAlert extends Mailable
{
    use Queueable, SerializesModels;

    public $product;
    public $currentStock;

    /**
     * Create a new message instance.
     */
    public function __construct(Product $product, $currentStock)
    {
        $this->product = $product;
        $this->currentStock = $currentStock;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('⚠️ CRITICAL: Low Stock Alert - ' . $this->product->name)
                    ->view('emails.low-stock-alert');
    }
}
