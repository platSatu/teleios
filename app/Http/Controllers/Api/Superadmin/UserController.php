<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Daftar user lewat API -- KHUSUS superadmin (sebelumnya cukup token
 * Sanctum user mana pun, sehingga semua data user bisa diambil).
 */
class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->user_type === 'SUPERADMIN', 403, 'Khusus superadmin.');

        return response()->json([
            'data' => User::query()->select(['id', 'name', 'email', 'status', 'user_type', 'created_at'])->latest()->paginate(50),
        ]);
    }
}
