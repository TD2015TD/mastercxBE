<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Ticket;
use App\Models\Customer;
use App\Models\Agent;
use Carbon\Carbon;

class TicketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = Customer::all();
        $agents = Agent::all();

        if ($customers->isEmpty() || $agents->isEmpty()) {
            $this->command->warn('Please seed customers and agents first!');
            return;
        }

        $tickets = [
            [
                'ticket_number' => 'TKT' . date('Ymd') . '001',
                'customer_id' => $customers[0]->id,
                'assigned_to' => $agents[0]->id,
                'created_by' => $agents[0]->id,
                'priority' => 'P1',
                'department' => 'Customs Operations',
                'category' => 'Import Clearance',
                'sub_category' => 'Urgent Clearance',
                'subject' => 'Urgent: Medical Equipment Clearance Required',
                'description' => 'We have medical equipment stuck at customs that needs immediate clearance. This is time-sensitive equipment needed for hospital operations.',
                'urgency' => 'Critical',
                'impact' => 'High',
                'status' => 'In Progress',
                'channel' => 'phone',
                'tags' => json_encode(['urgent', 'medical', 'import']),
                'created_at' => Carbon::now()->subHours(2),
            ],
            [
                'ticket_number' => 'TKT' . date('Ymd') . '002',
                'customer_id' => $customers[1]->id,
                'assigned_to' => $agents[1]->id,
                'created_by' => $agents[1]->id,
                'priority' => 'P2',
                'department' => 'Tax Policy',
                'category' => 'Tax Inquiry',
                'sub_category' => 'SST Calculation',
                'subject' => 'SST Calculation Query for Import Goods',
                'description' => 'Need clarification on SST calculation for imported electronics. The valuation seems incorrect.',
                'urgency' => 'High',
                'impact' => 'Medium',
                'status' => 'Pending',
                'channel' => 'email',
                'tags' => json_encode(['tax', 'sst', 'inquiry']),
                'created_at' => Carbon::now()->subHours(5),
            ],
            [
                'ticket_number' => 'TKT' . date('Ymd') . '003',
                'customer_id' => $customers[2]->id,
                'assigned_to' => $agents[2]->id,
                'created_by' => $agents[2]->id,
                'priority' => 'P3',
                'department' => 'Trade Compliance',
                'category' => 'Documentation',
                'sub_category' => 'Certificate Request',
                'subject' => 'Certificate of Origin Request',
                'description' => 'Requesting Certificate of Origin for export shipment to Thailand. Shipment details attached.',
                'urgency' => 'Medium',
                'impact' => 'Medium',
                'status' => 'Open',
                'channel' => 'web',
                'tags' => json_encode(['documentation', 'export', 'certificate']),
                'created_at' => Carbon::now()->subHours(3),
            ],
            [
                'ticket_number' => 'TKT' . date('Ymd') . '004',
                'customer_id' => $customers[3]->id,
                'assigned_to' => $agents[0]->id,
                'created_by' => $agents[0]->id,
                'priority' => 'P1',
                'department' => 'Enforcement',
                'category' => 'Investigation',
                'sub_category' => 'Fraud Alert',
                'subject' => 'Suspicious Import Declaration - Possible Fraud',
                'description' => 'We have identified a suspicious import declaration with undervalued goods. Immediate investigation required.',
                'urgency' => 'Critical',
                'impact' => 'Critical',
                'status' => 'In Progress',
                'channel' => 'walk-in',
                'tags' => json_encode(['urgent', 'fraud', 'investigation']),
                'created_at' => Carbon::now()->subHour(),
            ],
            [
                'ticket_number' => 'TKT' . date('Ymd') . '005',
                'customer_id' => $customers[4]->id,
                'assigned_to' => $agents[1]->id,
                'created_by' => $agents[1]->id,
                'priority' => 'P4',
                'department' => 'Information Services',
                'category' => 'General Inquiry',
                'sub_category' => 'Process Information',
                'subject' => 'Information on Import Procedures',
                'description' => 'First-time importer seeking information on general import procedures and requirements.',
                'urgency' => 'Low',
                'impact' => 'Low',
                'status' => 'Open',
                'channel' => 'chat',
                'tags' => json_encode(['inquiry', 'first-timer', 'general']),
                'created_at' => Carbon::now()->subDays(1),
            ],
            [
                'ticket_number' => 'TKT' . date('Ymd') . '006',
                'customer_id' => $customers[5]->id,
                'assigned_to' => $agents[2]->id,
                'created_by' => $agents[2]->id,
                'priority' => 'P2',
                'department' => 'Customs Operations',
                'category' => 'Cargo Release',
                'sub_category' => 'Delayed Release',
                'subject' => 'Cargo Held - Documentation Issue',
                'description' => 'Our cargo has been held due to missing documentation. Please advise on required documents for release.',
                'urgency' => 'High',
                'impact' => 'High',
                'status' => 'In Progress',
                'resolution_notes' => 'List of required documents sent to customer. Awaiting submission.',
                'channel' => 'email',
                'tags' => json_encode(['cargo', 'documentation', 'delayed']),
                'created_at' => Carbon::now()->subHours(6),
            ],
            [
                'ticket_number' => 'TKT' . date('Ymd') . '007',
                'customer_id' => $customers[6]->id,
                'assigned_to' => $agents[0]->id,
                'created_by' => $agents[0]->id,
                'priority' => 'P3',
                'department' => 'Trade Compliance',
                'category' => 'License Application',
                'sub_category' => 'Import License',
                'subject' => 'Import License Application for Controlled Items',
                'description' => 'Need to apply for import license for controlled pharmaceutical products. Request guidance on application process.',
                'urgency' => 'Medium',
                'impact' => 'Medium',
                'status' => 'Resolved',
                'resolution_notes' => 'Application process explained and forms provided. Customer successfully submitted application.',
                'channel' => 'phone',
                'customer_rating' => 5,
                'customer_feedback' => 'Very helpful and clear explanation. Thank you!',
                'tags' => json_encode(['license', 'controlled-items', 'application']),
                'created_at' => Carbon::now()->subDays(2),
            ],
            [
                'ticket_number' => 'TKT' . date('Ymd') . '008',
                'customer_id' => $customers[7]->id,
                'assigned_to' => $agents[1]->id,
                'created_by' => $agents[1]->id,
                'priority' => 'P2',
                'department' => 'Tax Policy',
                'category' => 'Duty Refund',
                'sub_category' => 'Refund Claim',
                'subject' => 'Customs Duty Refund Claim',
                'description' => 'Overpaid customs duty on last shipment. Submitting refund claim with supporting documents.',
                'urgency' => 'Medium',
                'impact' => 'Medium',
                'status' => 'Pending',
                'channel' => 'web',
                'tags' => json_encode(['refund', 'duty', 'claim']),
                'created_at' => Carbon::now()->subDays(3),
            ],
            [
                'ticket_number' => 'TKT' . date('Ymd') . '009',
                'customer_id' => $customers[8]->id,
                'assigned_to' => $agents[2]->id,
                'created_by' => $agents[2]->id,
                'priority' => 'P1',
                'department' => 'Customs Operations',
                'category' => 'Inspection',
                'sub_category' => 'Physical Inspection',
                'subject' => 'Container Selected for Physical Inspection',
                'description' => 'Our container has been selected for physical inspection. Need to schedule inspection time.',
                'urgency' => 'High',
                'impact' => 'High',
                'status' => 'Resolved',
                'resolution_notes' => 'Inspection scheduled and completed. No issues found. Cargo released.',
                'channel' => 'phone',
                'customer_rating' => 4,
                'tags' => json_encode(['inspection', 'urgent', 'container']),
                'created_at' => Carbon::now()->subDays(1),
            ],
            [
                'ticket_number' => 'TKT' . date('Ymd') . '010',
                'customer_id' => $customers[9]->id,
                'assigned_to' => $agents[0]->id,
                'created_by' => $agents[0]->id,
                'priority' => 'P3',
                'department' => 'Information Services',
                'category' => 'Tariff Classification',
                'sub_category' => 'HS Code Query',
                'subject' => 'HS Code Classification for New Product',
                'description' => 'Need help with HS code classification for a new product we are importing. Product specifications attached.',
                'urgency' => 'Medium',
                'impact' => 'Low',
                'status' => 'Closed',
                'resolution_notes' => 'HS code provided: 8471.30.00. Customer confirmed and satisfied.',
                'channel' => 'email',
                'customer_rating' => 5,
                'customer_feedback' => 'Quick and accurate response. Excellent service!',
                'tags' => json_encode(['classification', 'hs-code', 'product']),
                'created_at' => Carbon::now()->subDays(4),
            ],
        ];

        foreach ($tickets as $ticketData) {
            $ticket = Ticket::create($ticketData);
            
            // Calculate SLA for each ticket
            $ticket->calculateSla();
            
            // Update SLA remaining
            $ticket->updateSlaRemaining();
            
            // Record first response for some tickets
            if (in_array($ticket->status, ['In Progress', 'Pending', 'Resolved', 'Closed'])) {
                $ticket->recordFirstResponse();
            }
            
            // Set resolution time for resolved/closed tickets
            if (in_array($ticket->status, ['Resolved', 'Closed'])) {
                $ticket->resolution_time_minutes = rand(30, 300);
            }
            
            $ticket->save();
        }

        // Update customer metrics
        foreach ($customers as $customer) {
            $customer->updateMetrics();
        }
    }
}
