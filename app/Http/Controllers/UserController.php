<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index(Request $request)
    {
        try {
            if (!$request->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak memiliki akses untuk melihat data user'
                ], 403);
            }

            $users = User::with('role')
                ->whereHas('role', function($q) {
                    $q->whereIn('name', ['admin', 'kasir']);
                })
                ->orderBy('created_at', 'desc')
                ->get();

            $transformedUsers = $users->map(function($user) {
                return [
                    'id' => $user->id,
                    'employee_code' => 'US' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => strtolower($user->role->name),
                    'created_at' => $user->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Data pegawai berhasil diambil',
                'data' => $transformedUsers
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data pegawai',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak memiliki akses untuk membuat pegawai'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:admin,kasir',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $role = Role::where('name', $request->role)->first();
            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role tidak ditemukan'
                ], 400);
            }

            $user = User::create([
                'name' => $request->name,
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role_id' => $role->id,
            ]);

            $user->load('role');

            $transformedUser = [
                'id' => $user->id,
                'employee_code' => 'US' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => strtolower($user->role->name),
                'created_at' => $user->created_at,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Pegawai berhasil ditambahkan',
                'data' => $transformedUser
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat pegawai',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $currentUser = request()->user();

            if (!$currentUser->isAdmin() && $currentUser->id != $id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak memiliki akses untuk melihat data user ini'
                ], 403);
            }

            $user = User::with('role')->find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pegawai tidak ditemukan'
                ], 404);
            }

            $transformedUser = [
                'id' => $user->id,
                'employee_code' => 'US' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => strtolower($user->role->name),
                'created_at' => $user->created_at,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Data pegawai berhasil diambil',
                'data' => $transformedUser
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data pegawai',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $currentUser = $request->user();
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pegawai tidak ditemukan'
                ], 404);
            }

            if (!$currentUser->isAdmin() && $currentUser->id != $id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak memiliki akses untuk mengubah data user ini'
                ], 403);
            }

            $rules = [
                'name' => 'sometimes|required|string|max:255',
                'username' => 'sometimes|required|string|max:255|unique:users,username,' . $id,
                'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $id,
                'password' => 'sometimes|nullable|string|min:8|confirmed',
            ];

            if ($currentUser->isAdmin()) {
                $rules['role'] = 'sometimes|required|in:admin,kasir';
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = [];

            if ($request->has('name')) {
                $updateData['name'] = $request->name;
            }

            if ($request->has('username')) {
                $updateData['username'] = $request->username;
            }

            if ($request->has('email')) {
                $updateData['email'] = $request->email;
            }

            if ($request->has('password') && $request->password) {
                $updateData['password'] = Hash::make($request->password);
            }

            if ($request->has('role') && $currentUser->isAdmin()) {
                $role = Role::where('name', $request->role)->first();
                if ($role) {
                    $updateData['role_id'] = $role->id;
                }
            }

            $user->update($updateData);
            $user->load('role');

            $transformedUser = [
                'id' => $user->id,
                'employee_code' => 'US' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => strtolower($user->role->name),
                'created_at' => $user->created_at,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Pegawai berhasil diperbarui',
                'data' => $transformedUser
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui pegawai',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $currentUser = request()->user();

            if (!$currentUser->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak memiliki akses untuk menghapus pegawai'
                ], 403);
            }

            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pegawai tidak ditemukan'
                ], 404);
            }

            if ($currentUser->id == $id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat menghapus akun sendiri'
                ], 400);
            }

            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'Pegawai berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus pegawai',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function profile(Request $request)
    {
        try {
            $user = $request->user();
            $user->load('role');

            return response()->json([
                'success' => true,
                'message' => 'Profile berhasil diambil',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
                'current_password' => 'required_with:password|string',
                'password' => 'sometimes|nullable|string|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($request->has('password') && $request->password) {
                if (!Hash::check($request->current_password, $user->password)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Password saat ini tidak valid'
                    ], 400);
                }
            }

            $updateData = [];

            if ($request->has('name')) {
                $updateData['name'] = $request->name;
            }

            if ($request->has('email')) {
                $updateData['email'] = $request->email;
            }

            if ($request->has('password') && $request->password) {
                $updateData['password'] = Hash::make($request->password);
            }

            $user->update($updateData);
            $user->load('role');

            return response()->json([
                'success' => true,
                'message' => 'Profile berhasil diperbarui',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
