<?php

namespace App\Services\Jadwal;

use App\Models\JadwalMessageTemplate;

/**
 * Renders a Jadwal WA message from a company's own custom wording if
 * they've set one (Jadwal\JadwalMessageTemplateController), falling back
 * to the built-in Indonesian default otherwise — the only place either
 * of those texts should live. See migration
 * 2026_08_07_160000_create_jadwal_message_templates_table for why this
 * is deliberately its own lightweight table instead of reusing
 * App\Models\WaMessageTemplate.
 *
 * v1 covers the highest-frequency, most-repeated messages first — the
 * H-1 reminders and the immediate WA-reply acknowledgements, since
 * those are the ones every guru/murid actually sees week after week.
 * One-off messages (jadwal berubah, guru sakit/pengganti, usulan waktu
 * custom) still use their own inline text for now; adding them here
 * later is just a matter of appending another DEFINITIONS entry and
 * swapping the hardcoded string at its call site for a render() call —
 * nothing structural has to change.
 */
class JadwalMessageTemplateService
{
    /**
     * @var array<string, array{label: string, placeholders: array<int, string>, default: string}>
     */
    public const DEFINITIONS = [
        'reminder_guru' => [
            'label' => 'Pengingat H-1 untuk Guru',
            'placeholders' => ['nama_guru', 'label_kelas', 'tanggal', 'jam'],
            'default' => 'Halo {{nama_guru}}, pengingat: besok Anda mengajar *{{label_kelas}}* pada {{tanggal}}, jam {{jam}}. Balas *OK* untuk konfirmasi.',
        ],
        'reminder_murid' => [
            'label' => 'Pengingat H-1 untuk Murid',
            'placeholders' => ['nama_murid', 'label_kelas', 'tanggal', 'jam'],
            'default' => 'Halo {{nama_murid}}, pengingat: besok ada kelas *{{label_kelas}}* pada {{tanggal}}, jam {{jam}}. Balas *YA* jika hadir, atau *IZIN* jika tidak bisa hadir.',
        ],
        'ack_murid_hadir' => [
            'label' => 'Balasan setelah Murid Konfirmasi Hadir',
            'placeholders' => ['nama_murid', 'label_kelas'],
            'default' => 'Terima kasih {{nama_murid}}, kehadiran Anda di kelas *{{label_kelas}}* sudah dikonfirmasi. Sampai jumpa!',
        ],
        'ack_murid_izin' => [
            'label' => 'Balasan setelah Murid Konfirmasi Izin',
            'placeholders' => ['nama_murid', 'label_kelas'],
            'default' => 'Baik {{nama_murid}}, sudah dicatat bahwa Anda izin/tidak hadir di kelas *{{label_kelas}}*.',
        ],
        'ack_guru_confirm' => [
            'label' => 'Balasan setelah Guru Konfirmasi Mengajar',
            'placeholders' => ['nama_guru', 'label_kelas'],
            'default' => 'Terima kasih {{nama_guru}}, jadwal mengajar Anda untuk *{{label_kelas}}* sudah dikonfirmasi.',
        ],
        'ack_guru_decline' => [
            'label' => 'Balasan setelah Guru Tidak Bisa Mengajar',
            'placeholders' => ['nama_guru', 'label_kelas'],
            'default' => 'Baik {{nama_guru}}, sudah dicatat bahwa Anda tidak bisa mengajar *{{label_kelas}}*. Kami akan tindak lanjuti.',
        ],
    ];

    /**
     * @param  array<string, string>  $vars
     */
    public function render(string $companyId, string $key, array $vars): string
    {
        $definition = self::DEFINITIONS[$key] ?? null;

        if (! $definition) {
            // Programmer error (unknown key), not a user-facing
            // situation — fail loud in local/staging rather than
            // silently sending a blank WA message.
            throw new \InvalidArgumentException("Unknown Jadwal message template key: {$key}");
        }

        $custom = JadwalMessageTemplate::where('company_id', $companyId)
            ->where('message_key', $key)
            ->value('body');

        $template = filled($custom) ? $custom : $definition['default'];

        $replacements = [];

        foreach ($vars as $name => $value) {
            $replacements['{{'.$name.'}}'] = (string) $value;
        }

        return strtr($template, $replacements);
    }

    /**
     * Current effective body for one company+key, for the settings
     * page's textarea — the CUSTOM override if one's saved, otherwise
     * the default text itself (so the textarea always shows something
     * real to start editing from, not a confusing blank box).
     */
    public function effectiveBody(string $companyId, string $key): string
    {
        $definition = self::DEFINITIONS[$key] ?? null;

        if (! $definition) {
            return '';
        }

        $custom = JadwalMessageTemplate::where('company_id', $companyId)
            ->where('message_key', $key)
            ->value('body');

        return filled($custom) ? $custom : $definition['default'];
    }

    /**
     * Whether $companyId has actually customized $key (vs just seeing
     * the default rendered into effectiveBody() above) — drives the
     * settings page's "Reset ke Default" button visibility.
     */
    public function isCustomized(string $companyId, string $key): bool
    {
        return JadwalMessageTemplate::where('company_id', $companyId)
            ->where('message_key', $key)
            ->whereNotNull('body')
            ->where('body', '!=', '')
            ->exists();
    }
}
