<?php

namespace App\Console\Commands;

use App\Models\EmailConfiguration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * IMAP IDLE daemon — instant email delivery without polling.
 *
 * Tracks the EXISTS count from SELECT so it only fires email:process when the
 * count genuinely increases (fixes false positives from servers that echo EXISTS
 * on IDLE entry). Also processes any already-UNSEEN messages on startup.
 */
class EmailIdleListen extends Command
{
    protected $signature   = 'email:idle';
    protected $description = 'Listen for new emails via IMAP IDLE (instant, no polling)';

    private const IDLE_TIMEOUT_SECS = 1740; // Re-IDLE every 29 min per RFC 2177
    private const TAG_PREFIX        = 'CRM';

    private $sock        = null;
    private int $seq     = 0;
    private int $exists  = 0; // message count at last SELECT/EXISTS update

    public function handle(): void
    {
        $this->info('Starting IMAP IDLE listener…');

        while (true) {
            try {
                $this->runSession();
            } catch (\Throwable $e) {
                Log::error('[EmailIdle] Session error — restarting in 3s', ['error' => $e->getMessage()]);
                $this->error('[EmailIdle] ' . $e->getMessage() . ' — reconnecting in 3s');
                $this->closeSocket();
                sleep(3);
            }
        }
    }

    private function runSession(): void
    {
        [$host, $port, $user, $pass, $folder] = $this->getCredentials();

        $this->connect($host, $port);
        $this->login($user, $pass);
        $this->exists = $this->selectFolder($folder);

        $this->info("Connected — watching {$folder} on {$host} ({$this->exists} messages)");

        // Process any UNSEEN emails that arrived before we connected
        $this->callProcessOnce();

        while (true) {
            $this->sendIdle();
            $newExists = $this->waitForExistsOrTimeout();
            $this->sendDone();

            if ($newExists !== null && $newExists > $this->exists) {
                $this->exists = $newExists;
                $this->info("[EmailIdle] EXISTS {$newExists} — new mail, triggering processing");
                Log::info('[EmailIdle] New message detected', ['exists' => $newExists]);
                $this->callProcessOnce();
            } elseif ($newExists !== null) {
                // EXISTS didn't increase (server sync echo) — update count, skip processing
                $this->exists = $newExists;
            }
            // null = timeout → just re-IDLE, no new mail
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // IMAP protocol
    // ──────────────────────────────────────────────────────────────────────────

    private function connect(string $host, int $port): void
    {
        $this->closeSocket();

        $ctx = stream_context_create([
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);

        $this->sock = stream_socket_client(
            "ssl://{$host}:{$port}", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $ctx
        );

        if (!$this->sock) {
            throw new \RuntimeException("IMAP connect failed [{$errno}]: {$errstr}");
        }

        stream_set_blocking($this->sock, true);
        $this->readLine(); // server greeting
    }

    private function login(string $user, string $pass): void
    {
        $u = '"' . addslashes($user) . '"';
        $p = '"' . addslashes($pass) . '"';
        $this->sendCmd("LOGIN {$u} {$p}", expectOk: true);
    }

    /** Returns the EXISTS count reported by SELECT. */
    private function selectFolder(string $folder): int
    {
        $tag     = $this->tag();
        $exists  = 0;

        fwrite($this->sock, "{$tag} SELECT \"{$folder}\"\r\n");

        while ($line = $this->readLine()) {
            if (preg_match('/^\* (\d+) EXISTS/i', $line, $m)) {
                $exists = (int) $m[1];
            }
            if (str_starts_with($line, $tag)) {
                if (!preg_match("/^{$tag} OK/i", $line)) {
                    throw new \RuntimeException("SELECT failed: {$line}");
                }
                break;
            }
        }

        return $exists;
    }

    private function sendIdle(): void
    {
        $tag = $this->tag();
        fwrite($this->sock, "{$tag} IDLE\r\n");

        // Skip unsolicited server lines (e.g. * EXISTS echoed after email:process ran)
        // and wait for the '+' continuation that confirms IDLE was accepted.
        while (true) {
            $line = $this->readLine();
            if (empty($line)) {
                throw new \RuntimeException("IDLE not accepted: empty/closed response");
            }
            if (str_starts_with($line, '+')) {
                break; // server accepted IDLE
            }
            if (str_starts_with($line, '* ')) {
                continue; // unsolicited EXISTS/FLAGS/RECENT — skip
            }
            throw new \RuntimeException("IDLE not accepted: {$line}");
        }

        stream_set_timeout($this->sock, self::IDLE_TIMEOUT_SECS);
    }

    /**
     * Waits for EXISTS or timeout.
     * Returns the new EXISTS count if EXISTS was received, or null on timeout.
     */
    private function waitForExistsOrTimeout(): ?int
    {
        $newExists = null;

        while (true) {
            $line = fgets($this->sock);
            $meta = stream_get_meta_data($this->sock);

            if ($meta['timed_out'] || $line === false) {
                break; // 29-min re-IDLE
            }

            $line = rtrim($line);
            if (empty($line)) continue;

            if (preg_match('/^\* (\d+) EXISTS/i', $line, $m)) {
                $newExists = (int) $m[1];
                // Drain any further unsolicited updates before DONE
                stream_set_timeout($this->sock, 1);
                while ($extra = fgets($this->sock)) {
                    $meta2 = stream_get_meta_data($this->sock);
                    if ($meta2['timed_out'] || $extra === false) break;
                    // Catch higher EXISTS in the drain window
                    if (preg_match('/^\* (\d+) EXISTS/i', $extra, $m2)) {
                        $newExists = max($newExists, (int) $m2[1]);
                    }
                }
                break;
            }
        }

        return $newExists;
    }

    private function sendDone(): void
    {
        fwrite($this->sock, "DONE\r\n");
        while ($line = $this->readLine()) {
            if (preg_match('/^' . self::TAG_PREFIX . '\d+ (OK|BAD|NO)/i', $line)) break;
        }
        stream_set_timeout($this->sock, 86400);
    }

    private function sendCmd(string $cmd, bool $expectOk = false): string
    {
        $tag  = $this->tag();
        $last = '';
        fwrite($this->sock, "{$tag} {$cmd}\r\n");
        while ($line = $this->readLine()) {
            $last = $line;
            if (str_starts_with($line, $tag)) {
                if ($expectOk && !preg_match("/^{$tag} OK/i", $line)) {
                    throw new \RuntimeException("Command failed: {$line}");
                }
                break;
            }
        }
        return $last;
    }

    private function readLine(): string
    {
        $line = fgets($this->sock);
        return $line !== false ? rtrim($line) : '';
    }

    private function tag(): string
    {
        return self::TAG_PREFIX . (++$this->seq);
    }

    private function closeSocket(): void
    {
        if ($this->sock) {
            @fclose($this->sock);
            $this->sock = null;
        }
        $this->seq   = 0;
        $this->exists = 0;
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function callProcessOnce(): void
    {
        // email:process --once manages its own lock; daemon must NOT hold a lock here
        // or email:process will always see "already running" and skip.
        try {
            $this->call('email:process', ['--once' => true]);
        } catch (\Throwable $e) {
            Log::error('[EmailIdle] email:process failed', ['error' => $e->getMessage()]);
        }
    }

    private function getCredentials(): array
    {
        $config = EmailConfiguration::getDefaultImap();
        if ($config) {
            return [
                $config->imap_host,
                (int) $config->imap_port,
                $config->imap_username,
                $config->imap_password,
                $config->imap_folder ?? 'INBOX',
            ];
        }

        return [
            env('IMAP_HOST', 'localhost'),
            (int) env('IMAP_PORT', 993),
            env('IMAP_USERNAME'),
            env('IMAP_PASSWORD'),
            env('IMAP_FOLDER', 'INBOX'),
        ];
    }
}
