<?php

namespace App\Console\Commands;

use App\Services\ImapEmailService;
use App\Services\RawEmailParserService;
use Illuminate\Support\Facades\Log;

/**
 * Ingest a single raw RFC822 email handed off by a local MTA pipe
 * (e.g. Postfix /etc/aliases: "tickets: |php artisan email:pipe")
 * and feed it through the exact same ticket-creation pipeline used by
 * the IMAP-polling path in ProcessIncomingEmail — no IMAP/POP access
 * needed on the receiving end at all.
 */
class PipeIncomingEmail extends ProcessIncomingEmail
{
    protected $signature = 'email:pipe';

    protected $description = 'Process one raw email from stdin (MTA pipe delivery) and create/update a ticket';

    public function handle()
    {
        $raw = stream_get_contents(STDIN);

        if (!$raw) {
            $this->error('No message received on stdin');
            return 1;
        }

        try {
            $emailData = RawEmailParserService::parse($raw);

            $this->processSingleEmail($emailData, ImapEmailService::detached(), null);

            $this->info('Processed piped email from: ' . $emailData['from_email']);
            return 0;
        } catch (\Throwable $e) {
            Log::error('[email:pipe] Failed to process piped email: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error('Failed: ' . $e->getMessage());
            return 1;
        }
    }
}
