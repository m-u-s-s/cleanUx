<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-subscriptions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        app(\App\Services\Subscription\SubscriptionScheduler::class)
            ->generateUpcomingBookings();

        $this->info('Subscriptions processed.');
    }
}
