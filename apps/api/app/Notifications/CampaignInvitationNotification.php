<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// FSD §10.2/§10.5 — email undangan (dan pengingat, lewat flag $isReminder).
// Sengaja BUKAN ShouldQueue: dikirim sinkron di dalam SendCampaignInvitationsJob
// yang sudah queued, supaya sukses/gagal per penerima bisa langsung dicatat
// ke delivery_attempts (kalau notifikasi ini sendiri yang di-queue, job
// pemanggil tidak akan tahu apakah pengirimannya berhasil).
//
// Catatan: link mengarah ke halaman "mulai isi survei" yang baru dibangun
// di Fase 4 — belum ada di frontend saat ini, tapi format link sudah final.
class CampaignInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $questionnaireTitle,
        private readonly string $rawToken,
        private readonly bool $isReminder = false,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $link = rtrim(config('services.spa.url'), '/')."/survei/mulai?token={$this->rawToken}";
        $subject = $this->isReminder
            ? "Pengingat: Survei \"{$this->questionnaireTitle}\" Menunggu Anda"
            : "Undangan Mengisi Survei: {$this->questionnaireTitle}";

        return (new MailMessage)
            ->subject($subject)
            ->line($this->isReminder
                ? "Kami masih menunggu tanggapan Anda untuk survei \"{$this->questionnaireTitle}\"."
                : "Anda diundang untuk mengisi survei \"{$this->questionnaireTitle}\".")
            ->action('Isi Survei', $link)
            ->line('Tautan ini bersifat pribadi, mohon tidak dibagikan ke orang lain.');
    }
}
