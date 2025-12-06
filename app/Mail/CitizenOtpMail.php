<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\EmailTemplate;

class CitizenOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $otpCode;
    public string $phoneNumber;
    private ?EmailTemplate $template;

    /**
     * Create a new message instance.
     */
    public function __construct(string $otpCode, string $phoneNumber)
    {
        $this->otpCode = $otpCode;
        $this->phoneNumber = $phoneNumber;
        $this->template = EmailTemplate::getByCode('CITIZEN_OTP');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = 'Kod OTP untuk Portal Khidmat Rakyat';
        
        if ($this->template) {
            $rendered = $this->template->render([
                'otp_code' => $this->otpCode,
                'phone_number' => $this->phoneNumber,
            ]);
            $subject = $rendered['subject'];
        }

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: $this->getHtmlBody(),
            text: 'emails.citizen-otp-text',
            with: [
                'otpCode' => $this->otpCode,
                'phoneNumber' => $this->phoneNumber,
            ],
        );
    }

    /**
     * Get HTML body from database template.
     */
    private function getHtmlBody(): string
    {
        if ($this->template) {
            $rendered = $this->template->render([
                'otp_code' => $this->otpCode,
                'phone_number' => $this->phoneNumber,
            ]);
            return $rendered['body_html'];
        }

        // Fallback to default template
        return view('emails.citizen-otp', [
            'otpCode' => $this->otpCode,
            'phoneNumber' => $this->phoneNumber,
        ])->render();
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
