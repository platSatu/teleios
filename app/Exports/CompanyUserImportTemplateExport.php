<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * The file behind "Download Template" in the Import modal on the Setting
 * Users tab (dashboard/user/profile). Two sheets: a fill-in-the-blanks
 * Template and a Referensi sheet listing exactly which role/category
 * values will pass validation — see the individual sheet classes.
 */
class CompanyUserImportTemplateExport implements WithMultipleSheets
{
    /**
     * @param  Collection<int,string>  $roleNames
     * @param  Collection<int,string>  $categoryNames
     */
    public function __construct(
        private Collection $roleNames,
        private Collection $categoryNames,
    ) {
    }

    public function sheets(): array
    {
        return [
            new CompanyUserImportTemplateSheet(),
            new CompanyUserImportReferenceSheet($this->roleNames, $this->categoryNames),
        ];
    }
}
