<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SendRefillReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pharmacy:send-refill-reminders';
    protected $description = 'Scan orders for chronic medications and send refill reminders';

    public function handle()
    {
        $this->info('Scanning for chronic medication refills...');

        $chronicItems = \App\Models\OrderProduct::with(['order.user', 'product'])
            ->whereHas('product', function($q) {
                $q->where('is_chronic', true)->whereNotNull('refill_interval_days');
            })
            ->whereHas('order', function($q) {
                $q->where('status', 'completed');
            })
            ->get();

        $count = 0;
        foreach($chronicItems as $item) {
            if (!$item->order || !$item->order->user || !$item->product) continue;

            $daysSinceOrder = $item->order->created_at->diffInDays(now());
            $refillDays = (int)$item->product->refill_interval_days;
            
            // Remind 3 days before it runs out (e.g. if 30 days supply, remind on day 27)
            if ($daysSinceOrder === ($refillDays - 3)) {
                event(new \App\Events\RefillReminderEvent($item->order->user, $item->product));
                $this->info("Sent reminder to {$item->order->user->name} for {$item->product->name}");
                $count++;
            }
        }

        $this->info("Scan complete. Sent {$count} reminders.");
    }
}
