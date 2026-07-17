<?php

namespace App\Services;

use App\Models\EmailMessage;
use Illuminate\Support\Facades\Storage;
use ZBateson\MailMimeParser\MailMimeParser;
use ZBateson\MailMimeParser\Message\IMessagePart;

/**
 * Parses a raw RFC822 email string into the same array shape produced by
 * ImapEmailService::parseEmail(), so it can be fed into the exact same
 * ticket-creation pipeline (ProcessIncomingEmail::processSingleEmail()).
 *
 * Used by email:pipe for mail handed off by a local MTA, where no IMAP/POP
 * connection to a mailbox exists at all — the message arrives as raw bytes
 * on stdin instead.
 *
 * (ext-imap's local-mbox-file trick was tried first but this server's imap
 * build has the local mailbox driver disabled, so parsing is done here with
 * a pure-PHP MIME parser instead.)
 */
class RawEmailParserService
{
    public static function parse(string $rawMessage): array
    {
        $message = (new MailMimeParser())->parse($rawMessage, false);

        $from = self::firstAddress($message->getHeader('From'));
        $to = self::firstAddress($message->getHeader('To'));
        $replyTo = self::firstAddress($message->getHeader('Reply-To'));

        $messageId = $message->getMessageId() ?: uniqid('email_', true);

        $bodyText = $message->getTextContent() ?? '';
        $bodyHtml = $message->getHtmlContent() ?? '';

        $attachments = self::extractAttachments($message);

        if ($bodyHtml && !empty($attachments)) {
            $bodyHtml = self::resolveCidReferences($bodyHtml, $attachments);
        }

        $inReplyTo = $message->getHeaderValue('In-Reply-To');
        $references = $message->getHeaderValue('References');
        $threadId = EmailMessage::generateThreadId($messageId, $inReplyTo, $references);

        $rawHeaders = [];
        foreach ($message->getAllHeaders() as $header) {
            $rawHeaders[$header->getName()] = $header->getRawValue();
        }

        $dateHeader = $message->getHeaderValue('Date');

        return [
            'message_id' => $messageId,
            'subject' => $message->getSubject() ?: 'No Subject',
            'from_email' => $from['email'],
            'from_name' => $from['name'],
            'to_email' => $to['email'],
            'cc' => self::allAddressesAsString($message->getHeader('Cc')),
            'bcc' => self::allAddressesAsString($message->getHeader('Bcc')),
            'reply_to' => $replyTo['email'] ?: null,
            'body_text' => $bodyText,
            'body_html' => $bodyHtml,
            'received_at' => $dateHeader ? date('Y-m-d H:i:s', strtotime($dateHeader)) : now(),
            'headers' => $rawHeaders,
            'attachments' => $attachments,
            'conversation_thread_id' => $threadId,
            'in_reply_to' => $inReplyTo,
            'references' => $references,
        ];
    }

    private static function firstAddress($header): array
    {
        if (!$header || !method_exists($header, 'getAddresses')) {
            return ['email' => '', 'name' => ''];
        }

        $addresses = $header->getAddresses();
        if (empty($addresses)) {
            return ['email' => '', 'name' => ''];
        }

        return [
            'email' => $addresses[0]->getEmail() ?: '',
            'name' => $addresses[0]->getName() ?: '',
        ];
    }

    private static function allAddressesAsString($header): ?string
    {
        if (!$header || !method_exists($header, 'getAddresses')) {
            return null;
        }

        $emails = array_map(fn ($a) => $a->getEmail(), $header->getAddresses());
        $emails = array_filter($emails);

        return empty($emails) ? null : implode(', ', $emails);
    }

    private static function extractAttachments($message): array
    {
        $attachments = [];

        foreach ($message->getAllAttachmentParts() as $part) {
            /** @var IMessagePart $part */
            $filename = $part->getFilename();
            if (!$filename) {
                continue;
            }

            $stream = $part->getBinaryContentStream();
            $data = $stream ? $stream->getContents() : '';

            $directory = 'email_attachments/' . date('Y/m/d');
            $uniqueFilename = time() . '_' . $filename;
            $path = $directory . '/' . $uniqueFilename;

            Storage::disk('public')->put($path, $data);

            $attachments[] = [
                'filename' => $filename,
                'path' => $path,
                'url' => rtrim(config('app.frontend_url', config('app.url')), '/') . '/storage/' . $path,
                'size' => strlen($data),
                'mime_type' => $part->getContentType(),
                'content_id' => $part->getContentId(),
            ];
        }

        return $attachments;
    }

    /**
     * Mirrors ImapEmailService's cid: resolution so inline images render the
     * same way regardless of which ingestion path produced the ticket.
     */
    private static function resolveCidReferences(string $html, array $attachments): string
    {
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
            if (!empty($att['filename'])) {
                $cidMap[$att['filename']] = $att['url'];
            }
            if (str_starts_with(strtolower($att['mime_type'] ?? ''), 'image/')) {
                $imageAtts[] = $att;
            }
        }

        if (empty($cidMap) && empty($imageAtts)) {
            return $html;
        }

        $imgFallbackIdx = 0;
        return preg_replace_callback(
            '/<img([^>]*?)src=(["\'])cid:([^"\'>\s]+)\2([^>]*?)>/i',
            function ($m) use ($cidMap, $attachments, $imageAtts, &$imgFallbackIdx) {
                $before = $m[1];
                $quote = $m[2];
                $cid = $m[3];
                $after = $m[4];

                $url = $cidMap[$cid]
                    ?? $cidMap[strstr($cid, '@', true) ?: '']
                    ?? null;
                if ($url) return "<img{$before}src={$quote}{$url}{$quote}{$after}>";

                $allAttrs = $before . $after;
                if (preg_match('/alt=["\']([^"\']+)["\']/i', $allAttrs, $altM)) {
                    foreach ($attachments as $att) {
                        if (($att['filename'] ?? '') === $altM[1]) {
                            return "<img{$before}src={$quote}{$att['url']}{$quote}{$after}>";
                        }
                    }
                }

                if (isset($imageAtts[$imgFallbackIdx])) {
                    $url = $imageAtts[$imgFallbackIdx++]['url'];
                    return "<img{$before}src={$quote}{$url}{$quote}{$after}>";
                }

                return $m[0];
            },
            $html
        );
    }
}
