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

        // Extract attachments
        $attachments = $this->extractAttachments($emailNumber, $structure);

        // Get thread information
        $inReplyTo = $header->in_reply_to ?? null;
        $references = $header->references ?? null;
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
            'raw_message' => imap_body($this->connection, $emailNumber),
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
        $body = [
            'text' => '',
            'html' => '',
        ];

        if (!isset($structure->parts)) {
            // Simple email (not multipart)
            $bodyText = imap_body($this->connection, $emailNumber);

            if ($structure->encoding == 3) { // Base64
                $bodyText = base64_decode($bodyText);
            } elseif ($structure->encoding == 4) { // Quoted-printable
                $bodyText = quoted_printable_decode($bodyText);
            }

            $body['text'] = $bodyText;
            return $body;
        }

        // Multipart email
        foreach ($structure->parts as $partNum => $part) {
            $partNumber = $partNum + 1;

            // Get the body part
            $bodyPart = imap_fetchbody($this->connection, $emailNumber, $partNumber);

            // Decode based on encoding
            if ($part->encoding == 3) { // Base64
                $bodyPart = base64_decode($bodyPart);
            } elseif ($part->encoding == 4) { // Quoted-printable
                $bodyPart = quoted_printable_decode($bodyPart);
            }

            // Determine content type
            $mimeType = $this->getMimeType($part);

            if (strpos($mimeType, 'text/plain') !== false) {
                $body['text'] = $bodyPart;
            } elseif (strpos($mimeType, 'text/html') !== false) {
                $body['html'] = $bodyPart;
            }
        }

        return $body;
    }

    /**
     * Extract all headers from email.
     */
    protected function extractHeaders(int $emailNumber): array
    {
        $rawHeaders = imap_fetchheader($this->connection, $emailNumber);
        $headers = [];

        foreach (explode("\n", $rawHeaders) as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
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

        if (!isset($structure->parts)) {
            return $attachments;
        }

        foreach ($structure->parts as $partNum => $part) {
            $partNumber = $partNum + 1;

            // Check if this is an attachment
            if (!$this->isAttachment($part)) {
                continue;
            }

            $filename = $this->getAttachmentFilename($part);

            if (!$filename) {
                continue;
            }

            // Get attachment data
            $attachmentData = imap_fetchbody($this->connection, $emailNumber, $partNumber);

            // Decode based on encoding
            if ($part->encoding == 3) { // Base64
                $attachmentData = base64_decode($attachmentData);
            } elseif ($part->encoding == 4) { // Quoted-printable
                $attachmentData = quoted_printable_decode($attachmentData);
            }

            // Store attachment
            $storedPath = $this->storeAttachment($filename, $attachmentData);

            $attachments[] = [
                'filename' => $filename,
                'path' => $storedPath,
                'size' => strlen($attachmentData),
                'mime_type' => $this->getMimeType($part),
            ];
        }

        return $attachments;
    }

    /**
     * Check if part is an attachment.
     */
    protected function isAttachment($part): bool
    {
        return isset($part->disposition) &&
               (strtolower($part->disposition) == 'attachment' ||
                strtolower($part->disposition) == 'inline');
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
    protected function storeAttachment(string $filename, string $data): string
    {
        $directory = 'email_attachments/' . date('Y/m/d');
        $uniqueFilename = time() . '_' . $filename;
        $path = $directory . '/' . $uniqueFilename;

        Storage::put($path, $data);

        return $path;
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
            imap_close($this->connection);
            Log::info('IMAP connection closed');
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
