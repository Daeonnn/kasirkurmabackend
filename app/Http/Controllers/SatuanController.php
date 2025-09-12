<?php

namespace App\Http\Controllers;

use App\Models\Satuan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SatuanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $satuan = Satuan::all();

            return response()->json([
                'success' => true,
                'message' => 'Data satuan berhasil diambil',
                'data' => $satuan
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data satuan',
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
            'name' => 'required|string|max:50|unique:satuan,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $satuan = Satuan::create([
                'name' => $request->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Satuan berhasil ditambahkan',
                'data' => $satuan
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan satuan',
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
            $satuan = Satuan::find($id);

            if (!$satuan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Satuan tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data satuan berhasil diambil',
                'data' => $satuan
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data satuan',
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
            'name' => 'required|string|max:50|unique:satuan,name,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $satuan = Satuan::find($id);

            if (!$satuan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Satuan tidak ditemukan'
                ], 404);
            }

            $satuan->update([
                'name' => $request->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Satuan berhasil diperbarui',
                'data' => $satuan
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui satuan',
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
            $satuan = Satuan::find($id);

            if (!$satuan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Satuan tidak ditemukan'
                ], 404);
            }

            // Cek apakah satuan masih digunakan di tabel products
            if ($satuan->products()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat dihapus karena satuan telah digunakan'
                ], 400);
            }

            // Hard delete
            $satuan->delete();

            return response()->json([
                'success' => true,
                'message' => 'Satuan berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus satuan',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
