<?php

namespace App\Http\Controllers;

use App\Models\Jenis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class JenisController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $jenis = Jenis::all();

            return response()->json([
                'success' => true,
                'message' => 'Data jenis berhasil diambil',
                'data' => $jenis
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data jenis',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:jenis,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $jenis = Jenis::create([
                'name' => $request->name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Jenis berhasil dibuat',
                'data' => $jenis
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat jenis',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $jenis = Jenis::find($id);

            if (!$jenis) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jenis tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data jenis berhasil diambil',
                'data' => $jenis
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data jenis',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:jenis,name,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $jenis = Jenis::find($id);

            if (!$jenis) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jenis tidak ditemukan'
                ], 404);
            }

            $jenis->update([
                'name' => $request->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Jenis berhasil diperbarui',
                'data' => $jenis
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui jenis',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $jenis = Jenis::find($id);

            if (!$jenis) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jenis tidak ditemukan'
                ], 404);
            }

            // Cek apakah jenis masih digunakan di tabel products
            if ($jenis->products()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat dihapus karena jenis telah digunakan'
                ], 400);
            }

            // Hard delete
            $jenis->delete();

            return response()->json([
                'success' => true,
                'message' => 'Jenis berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus jenis',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
