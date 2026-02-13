<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DueReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var array<string,mixed>
     */
    public array $payload;

    /**
     * @param array<string,mixed> $payload
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function build(): self
    {
        $label = (string)($this->payload['label'] ?? 'Pengingat');
        $loanCode = (string)($this->payload['loan_code'] ?? '');

        $subject = "NOTOBUKU • {$label} Jatuh Tempo";
        if ($loanCode !== '') $subject .= " ({$loanCode})";

        return $this
            ->subject($subject)
            ->view('emails.transaksi.reminder_jatuh_tempo')
            ->with(['payload' => $this->payload]);
    }
}
