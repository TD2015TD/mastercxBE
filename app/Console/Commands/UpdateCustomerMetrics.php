<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;

class UpdateCustomerMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customers:update-metrics
                            {--customer= : Specific customer ID to update}
                            {--all : Update all customers}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update customer ticket metrics (total_tickets, resolved_tickets, open_tickets_count)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('customer')) {
            // Update specific customer
            $customerId = $this->option('customer');
            $customer = Customer::find($customerId);
            
            if (!$customer) {
                $this->error("Customer with ID {$customerId} not found.");
                return 1;
            }
            
            $customer->updateMetrics();
            $this->info("Updated metrics for customer: {$customer->name} (ID: {$customer->id})");
            $this->info("  Total Tickets: {$customer->total_tickets}");
            $this->info("  Resolved Tickets: {$customer->resolved_tickets}");
            $this->info("  Open Tickets: {$customer->open_tickets_count}");
            
        } elseif ($this->option('all') || $this->confirm('Update metrics for all customers?', true)) {
            // Update all customers
            $customers = Customer::all();
            $total = $customers->count();
            
            $this->info("Updating metrics for {$total} customers...");
            $bar = $this->output->createProgressBar($total);
            $bar->start();
            
            foreach ($customers as $customer) {
                $customer->updateMetrics();
                $bar->advance();
            }
            
            $bar->finish();
            $this->newLine();
            $this->info('All customer metrics updated successfully!');
            
        } else {
            $this->info('Update cancelled.');
            return 0;
        }

        return 0;
    }
}
