<?php

namespace App\Http\Controllers\User\Settings;

use App\Helpers\Crud;
use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    // =========================================================
    // INDEX
    // =========================================================
    public function index(Request $request)
    {
        $companies = Crud::getByUser(
            modelClass:    Company::class,
            relations:     ['user'],
            search:        $request->search,
            searchFields:  ['name', 'status'],
            userColumn:    'user_id',
            companyColumn: null, // Company tidak punya company_id
        );

        return view('user.settings.company.index', compact('companies'));
    }

    // =========================================================
    // CREATE
    // =========================================================
    public function create()
    {
        return view('user.settings.company.create');
    }

    // =========================================================
    // STORE
    // =========================================================
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'   => 'required|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);

        Crud::create(
            modelClass:  Company::class,
            data:        $validated,
            autoUserId:  true, // otomatis set user_id dari Auth
        );

        return redirect()
            ->route('company.index')
            ->with('success', 'Company berhasil dibuat.');
    }

    // =========================================================
    // EDIT
    // =========================================================
    public function edit(string $id)
    {
        $company = Crud::getById(
            modelClass: Company::class,
            id:         $id,
            relations:  ['user'],
            userScoped: true, // hanya bisa edit milik sendiri
        );

        return view('user.settings.company.edit', compact('company'));
    }

    // =========================================================
    // UPDATE
    // =========================================================
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'name'   => 'required|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);

        Crud::update(
            modelClass: Company::class,
            id:         $id,
            data:       $validated,
            userScoped: true, // hanya bisa update milik sendiri
        );

        return redirect()
            ->route('company.index')
            ->with('success', 'Company berhasil diperbarui.');
    }

    // =========================================================
    // DESTROY
    // =========================================================
    public function destroy(string $id)
    {
        Crud::delete(
            modelClass: Company::class,
            id:         $id,
            userScoped: true, // hanya bisa hapus milik sendiri
        );

        return redirect()
            ->route('company.index')
            ->with('success', 'Company berhasil dihapus.');
    }
}

/*
|--------------------------------------------------------------------------
| Routes — tambahkan di routes/web.php
|--------------------------------------------------------------------------
|
| Route::middleware(['auth'])->prefix('user/settings')->name('user.settings.')->group(function () {
|     Route::resource('company', \App\Http\Controllers\User\Settings\CompanyController::class);
| });
|
*/