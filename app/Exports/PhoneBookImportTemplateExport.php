<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * "Download Template" inside the Buku Telepon import modal — two
 * sheets: a fill-in-the-blanks Template (App\Exports\
 * PhoneBookImportTemplateSheet) and a Referensi sheet
 * (App\Exports\PhoneBookImportReferenceSheet) listing every
 * Kelompok/branch name this specific company can currently import
 * against — see the individual sheet classes for why they're split
 * instead of sharing one sheet like before. Same pattern as
 * App\Exports\CompanyUserImportTemplateExport. See
 * App\Exports\CategoryPhoneBookImportTemplateExport for the
 * single-sheet version of this template still used by the "Kelompok"
 * import (out of scope for this split — that one doesn't have the
 * phantom-row/MAX_ROWS interaction this one does).
 */
class PhoneBookImportTemplateExport implements WithMultipleSheets
{
    /**
     * @param  Collection<int,string>  $categoryNames
     * @param  Collection<int,string>  $branchNames
     */
    public function __construct(
        private Collection $categoryNames,
        private Collection $branchNames,
    ) {
    }

    public function sheets(): array
    {
        return [
            new PhoneBookImportTemplateSheet($this->categoryNames->first()),
            new PhoneBookImportReferenceSheet($this->categoryNames, $this->branchNames),
        ];
    }
}
