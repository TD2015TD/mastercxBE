<?php

namespace App\Services;

use App\Models\EmailMessage;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\EmailConfiguration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImapEmailService
{
    protected $connection;
    protected $mailbox;
    protected $config;

    /**
     * Initialize IMAP connection with optional configuration.
     *
     * @param EmailConfiguration|null $config
     */
    public function __construct(?EmailConfiguration $config = null)
    {
        $this->config = $config;
        $this->connect();
    }

    /**
     * Build an instance with no live IMAP connection. RawEmailParserService
     * does the actual parsing for piped mail (this server's ext-imap build
     * has no local-mbox-file driver, only remote IMAP/POP), but
     * ProcessIncomingEmail::processSingleEmail() still type-hints
     * ImapEmailService for its optional markAsRead() call — this satisfies
     * that without a real connection. markAsRead() is only ever invoked when
     * $emailData['email_number'] is set, which piped mail never has.
     */
    public static function detached(): self
    {
        /** @var self $instance */
        $instance = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $instance->connection = null;

        return $instance;
    }

    /**
     * Connect to IMAP server using database configuration or fallback to env.
     */
    protected function connect(): void
    {
        // Try to get configuration from database first
        if (!$this->config) {
            $this->config = EmailConfiguration::getDefaultImap();
        }

        // Use database config if available, otherwise fallback to env config
        if ($this->config) {
            $host = $this->config->imap_host;
            $port = $this->config->imap_port ?? 993;
            $encryption = $this->config->imap_encryption ?? 'ssl';
            $username = $this->config->imap_username;
            $password = $this->config->imap_password;
            $folder = $this->config->imap_folder ?? 'INBOX';

            Log::info("Using database IMAP configuration: {$this->config->name}");
        } else {
            // Fallback to environment configuration
            $host = config('mail.imap.host');
            $port = config('mail.imap.port', 993);
            $encryption = config('mail.imap.encryption', 'ssl');
            $username = config('mail.imap.username');
            $password = config('mail.imap.password');
            $folder = config('mail.imap.folder', 'INBOX');

            Log::info("Using environment IMAP configuration");
        }

        if (!$host || !$username || !$password) {
            throw new \Exception('IMAP configuration is not set properly. Please configure email settings in the database or .env file.');
        }

        // Build mailbox string
        $this->mailbox = sprintf(
            '{%s:%d/imap/%s}%s',
            $host,
            $port,
            $encryption,
            $folder
        );

        // Connect to IMAP
        $this->connection = @imap_open($this->mailbox, $username, $password);

        if (!$this->connection) {
            $error = imap_last_error();
            throw new \Exception("Failed to connect to IMAP server: {$error}");
        }

        Log::info('IMAP connection established successfully');
    }

    /**
     * Fetch new unread emails.
     */
    public function fetchUnreadEmails(): array
    {
        if (!$this->connection) {
            throw new \Exception('IMAP connection not established');
        }

        // Search for unread emails
        $emails = imap_search($this->connection, 'UNSEEN');

        if (!$emails) {
            Log::info('No unread emails found');
            return [];
        }

        $messages = [];

        foreach ($emails as $emailNumber) {
            try {
                $message = $this->parseEmail($emailNumber);
                if ($message) {
                    $messages[] = $message;
                }
            } catch (\Exception $e) {
                Log::error("Error parsing email #{$emailNumber}: " . $e->getMessage());
            }
        }

        return $messages;
    }

    /**
     * Parse individual email message.
     */
    protected function parseEmail(int $emailNumber): ?array
    {
        // Get email headers
        $header = imap_headerinfo($this->connection, $emailNumber);
        $structure = imap_fetchstructure($this->connection, $emailNumber);

        if (!$header) {
            Log::warning("Could not fetch header for email #{$emailNumber}");
            return null;
        }

        // Extract message ID for deduplication
        $messageId = $header->message_id ?? uniqid('email_', true);

        // Check if message already exists (deduplication)
        if (EmailMessage::where('message_id', $messageId)->exists()) {
            Log::info("Email with message_id {$messageId} already exists, skipping");
            return null;
        }

        // Extract email addresses
        $from = $this->extractEmail($header->from[0] ?? null);
        $to = $this->extractEmail($header->to[0] ?? null);
        $replyTo = isset($header->reply_to[0]) ? $this->extractEmail($header->reply_to[0]) : null;

        // Extract CC and BCC
        $cc = isset($header->cc) ? $this->extractMultipleEmails($header->cc) : null;
        $bcc = isset($header->bcc) ? $this->extractMultipleEmails($header->bcc) : null;

        // Get email body
        $body = $this->getBody($emailNumber, $structure);

        // Extract headers
        $headers = $this->extractHeaders($emailNumber);

        // Extract attachments (inline images carry a content_id)
        $attachments = $this->extractAttachments($emailNumber, $structure);

        // Replace cid: references in HTML body with stored public URLs so images
        // show correctly in the browser iframe and ticket details view.
        // Resolution order: (1) content_id exact/local-part, (2) img alt text → filename, (3) positional.
        if (!empty($body['html']) && !empty($attachments)) {
            $cidMap = [];
            $imageAtts = [];
            foreach ($attachments as $att) {
                if (!empty($att['content_id'])) {
                    $cid = $att['content_id'];
                    $cidMap[$cid] = $att['url'];
                    $localPart = strstr($cid, '@', true) ?: false;
                    if ($localPart && $localPart !== $cid) {
                        $cidMap[$localPart] = $att['url'];
                    }
                }
                // Index by filename too (some clients use filename as CID)
                if (!empty($att['filename'])) {
                    $cidMap[$att['filename']] = $att['url'];
                }
                // Collect image attachments for positional fallback
                if (str_starts_with(strtolower($att['mime_type'] ?? ''), 'image/')) {
                    $imageAtts[] = $att;
                }
            }

            if (!empty($cidMap) || !empty($imageAtts)) {
                $imgFallbackIdx = 0;
                $body['html'] = preg_replace_callback(
                    '/<img([^>]*?)src=(["\'])cid:([^"\'>\s]+)\2([^>]*?)>/i',
                    function ($m) use ($cidMap, $attachments, $imageAtts, &$imgFallbackIdx) {
                        $before = $m[1];
                        $quote  = $m[2];
                        $cid    = $m[3];
                        $after  = $m[4];

                        // 1. CID / filename map
                        $url = $cidMap[$cid]
                            ?? $cidMap[strstr($cid, '@', true) ?: '']
                            ?? null;
                        if ($url) return "<img{$before}src={$quote}{$url}{$quote}{$after}>";

                        // 2. Alt-text → filename (Gmail sets alt="filename.ext")
                        $allAttrs = $before . $after;
                        if (preg_match('/alt=["\']([^"\']+)["\']/i', $allAttrs, $altM)) {
                            foreach ($attachments as $att) {
                                if (($att['filename'] ?? '') === $altM[1]) {
                                    return "<img{$before}src={$quote}{$att['url']}{$quote}{$after}>";
                                }
                            }
                        }

                        // 3. Positional fallback
                        if (isset($imageAtts[$imgFallbackIdx])) {
                            $url = $imageAtts[$imgFallbackIdx++]['url'];
                            return "<img{$before}src={$quote}{$url}{$quote}{$after}>";
                        }

                        return $m[0]; // leave unresolved; frontend will handle
                    },
                    $body['html']
                );
            }
        }

        // Get thread information.
        // imap_headerinfo() does not expose References; fall back to the raw-parsed
        // headers (which now correctly handle folded multi-line values).
        $inReplyTo  = $header->in_reply_to ?? $headers['In-Reply-To'] ?? null;
        $references = $header->references ?? $headers['References'] ?? null;
        $threadId = EmailMessage::generateThreadId($messageId, $inReplyTo, $references);

        return [
            'message_id' => $messageId,
            'subject' => isset($header->subject) ? imap_utf8($header->subject) : 'No Subject',
            'from_email' => $from['email'],
            'from_name' => $from['name'],
            'to_email' => $to['email'],
            'cc' => $cc,
            'bcc' => $bcc,
            'reply_to' => $replyTo ? $replyTo['email'] : null,
            'body_text' => $body['text'],
            'body_html' => $body['html'],
            'received_at' => isset($header->date) ? date('Y-m-d H:i:s', strtotime($header->date)) : now(),
            'headers' => $headers,
            'attachments' => $attachments,
            'conversation_thread_id' => $threadId,
            'in_reply_to' => $inReplyTo,
            'references' => $references,
            'email_number' => $emailNumber,
        ];
    }

    /**
     * Extract email address and name from header object.
     */
    protected function extractEmail($headerObj): array
    {
        if (!$headerObj) {
            return ['email' => '', 'name' => ''];
        }

        $email = $headerObj->mailbox . '@' . $headerObj->host;
        $name = isset($headerObj->personal) ? imap_utf8($headerObj->personal) : '';

        return ['email' => $email, 'name' => $name];
    }

    /**
     * Extract multiple email addresses.
     */
    protected function extractMultipleEmails(array $headerObjs): string
    {
        $emails = [];
        foreach ($headerObjs as $obj) {
            $extracted = $this->extractEmail($obj);
            $emails[] = $extracted['email'];
        }
        return implode(', ', $emails);
    }

    /**
     * Get email body (both plain text and HTML).
     */
    protected function getBody(int $emailNumber, $structure): array
    {
        $body = ['text' => '', 'html' => ''];
        $this->extractBodyParts($emailNumber, $structure, '0', $body);
        return $body;
    }

    /**
     * Recursively walk the MIME tree to find text/plain and text/html parts.
     * Handles nested multipart (multipart/alternative inside multipart/mixed, etc.)
     * as sent by Gmail and other modern mail clients.
     */
    protected function extractBodyParts(int $emailNumber, $part, string $sectionPrefix, array &$body): void
    {
        $mimeType = $this->getMimeType($part);

        if (strpos($mimeType, 'MULTIPART') === 0) {
            // Recurse into sub-parts
            if (!empty($part->parts)) {
                foreach ($part->parts as $i => $subPart) {
                    $section = $sectionPrefix === '0'
                        ? (string) ($i + 1)
                        : $sectionPrefix . '.' . ($i + 1);
                    $this->extractBodyParts($emailNumber, $subPart, $section, $body);
                }
            }
            return;
        }

        // Skip attachments
        if ($this->isAttachment($part)) {
            return;
        }

        // Fetch and decode the leaf part
        $fetchSection = $sectionPrefix === '0' ? '1' : $sectionPrefix;
        $content = imap_fetchbody($this->connection, $emailNumber, $fetchSection, FT_PEEK);

        if ($part->encoding == 3) {       // Base64
            $content = base64_decode($content);
        } elseif ($part->encoding == 4) { // Quoted-printable
            $content = quoted_printable_decode($content);
        }

        // Apply charset if available
        $charset = null;
        foreach (($part->parameters ?? []) as $param) {
            if (strtolower($param->attribute) === 'charset') {
                $charset = $param->value;
                break;
            }
        }
        if ($charset && strtolower($charset) !== 'utf-8') {
            $converted = @iconv($charset, 'UTF-8//TRANSLIT//IGNORE', $content);
            if ($converted !== false) $content = $converted;
        }

        if ($mimeType === 'TEXT/PLAIN' && $body['text'] === '') {
            $body['text'] = $content;
        } elseif ($mimeType === 'TEXT/HTML' && $body['html'] === '') {
            $body['html'] = $content;
        }
    }

    /**
     * Extract all headers from email.
     */
    protected function extractHeaders(int $emailNumber): array
    {
        $rawHeaders = imap_fetchheader($this->connection, $emailNumber);
        // Unfold RFC 2822 continuation lines (lines starting with whitespace
        // are continuations of the previous header line).
        $unfolded = preg_replace('/\r?\n[ \t]+/', ' ', $rawHeaders);
        $headers = [];

        foreach (explode("\n", $unfolded) as $line) {
            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }

        return $headers;
    }

    /**
     * Extract attachments from email.
     */
    protected function extractAttachments(int $emailNumber, $structure): array
    {
        $attachments = [];
        $this->collectAttachmentParts($emailNumber, $structure, '0', $attachments);
        return $attachments;
    }

    /**
     * Recursively walk the MIME tree to collect all attachment parts.
     * Mirrors extractBodyParts so nested multipart/mixed structures
     * (e.g. Gmail replies with inline images + file attachments) are
     * fully traversed and no attachments are missed.
     */
    protected function collectAttachmentParts(int $emailNumber, $part, string $sectionPrefix, array &$attachments): void
    {
        $mimeType = $this->getMimeType($part);

        if (strpos($mimeType, 'MULTIPART') === 0) {
            if (!empty($part->parts)) {
                foreach ($part->parts as $i => $subPart) {
                    $section = $sectionPrefix === '0'
                        ? (string) ($i + 1)
                        : $sectionPrefix . '.' . ($i + 1);
                    $this->collectAttachmentParts($emailNumber, $subPart, $section, $attachments);
                }
            }
            return;
        }

        if (!$this->isAttachment($part)) {
            return;
        }

        $filename = $this->getAttachmentFilename($part);
        if (!$filename) {
            return;
        }

        $contentId = null;
        if (!empty($part->id)) {
            $contentId = trim($part->id, '<> ');
        }

        $fetchSection   = $sectionPrefix === '0' ? '1' : $sectionPrefix;
        $attachmentData = imap_fetchbody($this->connection, $emailNumber, $fetchSection, FT_PEEK);

        if ($part->encoding == 3) {
            $attachmentData = base64_decode($attachmentData);
        } elseif ($part->encoding == 4) {
            $attachmentData = quoted_printable_decode($attachmentData);
        }

        ['path' => $storedPath, 'url' => $storedUrl] = $this->storeAttachment($filename, $attachmentData);

        $attachments[] = [
            'filename'   => $filename,
            'path'       => $storedPath,
            'url'        => $storedUrl,
            'size'       => strlen($attachmentData),
            'mime_type'  => $mimeType,
            'content_id' => $contentId,
        ];
    }

    /**
     * Check if part is an attachment.
     */
    protected function isAttachment($part): bool
    {
        if (!isset($part->disposition)) {
            return false;
        }
        $disp = strtolower($part->disposition);
        if ($disp === 'attachment') {
            return true;
        }
        // Only treat inline parts as attachments when they carry a filename
        // (i.e. they are real files, not just body text or HTML rendered inline).
        if ($disp === 'inline') {
            return $this->getAttachmentFilename($part) !== null;
        }
        return false;
    }

    /**
     * Get attachment filename.
     */
    protected function getAttachmentFilename($part): ?string
    {
        if (!isset($part->dparameters)) {
            return null;
        }

        foreach ($part->dparameters as $param) {
            if (strtolower($param->attribute) == 'filename') {
                return imap_utf8($param->value);
            }
        }

        if (isset($part->parameters)) {
            foreach ($part->parameters as $param) {
                if (strtolower($param->attribute) == 'name') {
                    return imap_utf8($param->value);
                }
            }
        }

        return null;
    }

    /**
     * Get MIME type from part.
     */
    protected function getMimeType($part): string
    {
        $primaryMime = ['TEXT', 'MULTIPART', 'MESSAGE', 'APPLICATION', 'AUDIO', 'IMAGE', 'VIDEO', 'OTHER'];

        if (isset($part->type)) {
            $type = $primaryMime[(int)$part->type];
        } else {
            $type = 'TEXT';
        }

        if (isset($part->subtype)) {
            return $type . '/' . $part->subtype;
        }

        return $type;
    }

    /**
     * Store attachment to disk.
     */
    protected function storeAttachment(string $filename, string $data): array
    {
        $directory = 'email_attachments/' . date('Y/m/d');
        $uniqueFilename = time() . '_' . $filename;
        $path = $directory . '/' . $uniqueFilename;

        Storage::disk('public')->put($path, $data);

        return [
            'path' => $path,
            'url'  => rtrim(config('app.frontend_url', config('app.url')), '/') . '/storage/' . $path,
        ];
    }

    /**
     * Mark email as read.
     */
    public function markAsRead(int $emailNumber): void
    {
        imap_setflag_full($this->connection, (string)$emailNumber, '\\Seen');
    }

    /**
     * Close IMAP connection.
     */
    public function disconnect(): void
    {
        if ($this->connection) {
            try {
                imap_close($this->connection);
                Log::info('IMAP connection closed');
            } catch (\Throwable $e) {
                // Connection may already be closed (e.g. by email:idle's own connection)
            }
            $this->connection = null;
        }
    }

    /**
     * Destructor to ensure connection is closed.
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
