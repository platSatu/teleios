<?php

namespace App\Services\Chat;

use App\Models\Company;
use App\Models\CompanyToUser;
use App\Models\WaMessageSchedule;
use Illuminate\Support\Carbon;

/**
 * Resolves {{tag}} placeholders inside a WaMessageAutoReply::reply_message
 * against this company's own live data — called by App\Jobs\
 * SendAutoReplyMessage right before sending, so e.g. {{jadwal_aktif}}
 * always reflects whatever's actually active at that moment, not a
 * snapshot from whenever the rule was last saved.
 *
 * Deliberately a small, curated set of tags rather than a generic
 * templating engine bolted onto user input — every tag here maps to
 * data that's unambiguously scoped to "this company" already via
 * existing relationships. Notably absent: anything payment/invoice
 * related — there's no data model yet linking an arbitrary WhatsApp
 * contact to a specific payment/invoice record, so a {{pembayaran}} tag
 * would have to fabricate something. Add a tag here once that data
 * exists, following the same pattern as the others.
 */
class AutoReplyTagResolver
{
    /**
     * Tag => human description, shown as a clickable legend on the Auto
     * Reply form (resources/views/chat/message-auto-replies/_form.blade.php)
     * so a company knows what's available without reading this class.
     *
     * @return array<string, string>
     */
    public static function availableTags(): array
    {
        return [
            '{{jadwal_aktif}}' => 'Daftar Pesan Terjadwal yang sedang aktif milik company ini',
            '{{daftar_user}}' => 'Daftar user/karyawan yang terdaftar di company ini',
            '{{nama_perusahaan}}' => 'Nama company pengirim pesan',
            '{{tanggal_hari_ini}}' => 'Tanggal hari ini',
        ];
    }

    public function resolve(?string $message, Company $company): string
    {
        $message = (string) $message;

        // Fast path — most rules are plain static text with no tags at
        // all, so skip every lookup below entirely for those.
        if (! str_contains($message, '{{')) {
            return $message;
        }

        foreach (array_keys(static::availableTags()) as $tag) {
            if (str_contains($message, $tag)) {
                $message = str_replace($tag, $this->resolveTag($tag, $company), $message);
            }
        }

        return $message;
    }

    private function resolveTag(string $tag, Company $company): string
    {
        return match ($tag) {
            '{{jadwal_aktif}}' => $this->activeSchedulesList($company),
            '{{daftar_user}}' => $this->companyUsersList($company),
            '{{nama_perusahaan}}' => $company->name,
            '{{tanggal_hari_ini}}' => Carbon::now()->translatedFormat('d M Y'),
            default => '',
        };
    }

    /**
     * Up to 10 of this company's currently-active WaMessageSchedule rows
     * (any type — once/recurring/drip), formatted as a numbered list.
     * Capped rather than exhaustive: this is going into a WhatsApp
     * message, not a report.
     */
    private function activeSchedulesList(Company $company): string
    {
        $schedules = WaMessageSchedule::where('company_id', $company->id)
            ->where('status', 'active')
            ->orderBy('date_start')
            ->limit(10)
            ->get();

        if ($schedules->isEmpty()) {
            return 'Belum ada jadwal yang tersedia saat ini.';
        }

        return $schedules->values()->map(function (WaMessageSchedule $schedule, int $i) {
            $range = $schedule->date_start->equalTo($schedule->date_end)
                ? $schedule->date_start->translatedFormat('d M Y')
                : $schedule->date_start->translatedFormat('d M Y').' - '.$schedule->date_end->translatedFormat('d M Y');

            $time = Carbon::parse($schedule->schedule_time)->format('H:i');

            return ($i + 1).". {$schedule->title} ({$range}, jam {$time})";
        })->implode("\n");
    }

    /**
     * Every distinct user belonging to this company (owner + members),
     * name only — no email/phone, since this text is going straight into
     * a WhatsApp reply that could be seen by anyone messaging the bot.
     */
    private function companyUsersList(Company $company): string
    {
        $names = CompanyToUser::where('company_id', $company->id)
            ->with('user:id,name')
            ->get()
            ->pluck('user.name')
            ->filter()
            ->unique()
            ->values();

        if ($names->isEmpty()) {
            return 'Belum ada user yang terdaftar.';
        }

        return $names->map(fn (string $name, int $i) => ($i + 1).". {$name}")->implode("\n");
    }
}
