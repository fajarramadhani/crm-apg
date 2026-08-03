<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class PublicHistoryOtpMail extends Mailable
{
    public function __construct(public string $code, public int $expiresInMinutes) {}

    public function build(): self
    {
        return $this->subject('Kode akses riwayat pengajuan')
            ->text('mail.public-history-otp');
    }
}
