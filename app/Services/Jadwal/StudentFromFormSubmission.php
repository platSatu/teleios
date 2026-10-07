<?php

namespace App\Services\Jadwal;

use App\Models\FormSubmission;
use App\Services\Company\CompanyContext;

/**
 * "+ Add Student" dari Form > Submission (7 Oktober 2026): mencari
 * submission milik company/branch yang sedang aktif, lalu menebak isian
 * awal form Tambah Student (nama, No. HP orang tua/murid) dari judul
 * pertanyaan form. Hasilnya hanya ISIAN AWAL -- admin tetap memeriksa &
 * melengkapi (Bidang, Pengajar, jadwal) sebelum menyimpan lewat alur
 * JadwalStudentController::store() yang sudah ada.
 */
class StudentFromFormSubmission
{
    private const PARENT_WORDS = '/orang\s*tua|ortu|wali|ayah|ibu|parent/i';

    private const STUDENT_WORDS = '/siswa|murid|student|anak|peserta/i';

    private const PHONE_WORDS = '/\b(hp|wa|whatsapp|telepon|telp|phone|handphone|ponsel)\b|nomor/i';

    private const NAME_WORDS = '/\bnama\b|\bname\b/i';

    /** Submission milik company (dan branch, kalau user dikunci ke satu branch). */
    public function find(CompanyContext $context, ?string $submissionId): ?FormSubmission
    {
        if (blank($submissionId)) {
            return null;
        }

        return FormSubmission::with(['answers.formContent', 'formHeader:id,name'])
            ->where('company_id', $context->company->id)
            ->when($context->isLockedToBranch(), fn ($q) => $q->where('branch_office_id', $context->branchOffice?->id))
            ->whereKey($submissionId)
            ->first();
    }

    /** @return array{name: ?string, parent_phone_number: ?string, student_phone_number: ?string, branch_office_id: ?string} */
    public function prefill(FormSubmission $submission): array
    {
        $name = $firstText = $parent = $student = $generic = null;

        $answers = $submission->answers->sortBy(fn ($a) => $a->formContent->position ?? PHP_INT_MAX);

        foreach ($answers as $answer) {
            $decoded = $answer->decodedValue();
            $value = trim(is_array($decoded) ? implode(', ', $decoded) : (string) $decoded);

            if ($value === '' || $answer->file_path) {
                continue;
            }

            $label = (string) ($answer->formContent->name ?? '');
            $isParent = (bool) preg_match(self::PARENT_WORDS, $label);
            $looksLikePhone = (bool) preg_match('/^\+?[\d\s.\-()]{8,20}$/', $value);

            if ($looksLikePhone || preg_match(self::PHONE_WORDS, $label)) {
                if ($isParent) {
                    $parent ??= $value;
                } elseif (preg_match(self::STUDENT_WORDS, $label)) {
                    $student ??= $value;
                } else {
                    $generic ??= $value;
                }

                continue;
            }

            if (! $isParent && preg_match(self::NAME_WORDS, $label)) {
                $name ??= $value;
            }

            $firstText ??= $value;
        }

        // Nomor tanpa keterangan jelas dipakai sebagai kontak orang tua
        // (kontak utama pengingat WA), atau nomor murid kalau sudah terisi.
        if ($generic !== null) {
            if ($parent === null) {
                $parent = $generic;
            } elseif ($student === null) {
                $student = $generic;
            }
        }

        $cut = fn (?string $v, int $max) => $v === null ? null : mb_substr($v, 0, $max);

        return [
            'name' => $cut($name ?? $firstText, 255),
            'parent_phone_number' => $cut($parent, 32),
            'student_phone_number' => $cut($student, 32),
            'branch_office_id' => $submission->branch_office_id,
        ];
    }
}
