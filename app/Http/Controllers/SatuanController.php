<?php

namespace App\Http\Controllers;

use App\Models\Satuan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SatuanController extends Controller
{
    public function index()
    {
        $satuan = Satuan::all();

        return response()->json([
            'success' => true,
            'message' => 'Data satuan berhasil diambil',
            'data' => $satuan
        ]);
    }

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

    public function show($id)
    {
        $satuan = Satuan::withTrashed()->find($id);

        if (!$satuan) {
            return response()->json([
                'success' => false,
                'message' => 'Satuan tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Satuan ditemukan',
            'data' => $satuan
        ]);
    }

    public function update(Request $request, $id)
    {
        $satuan = Satuan::withTrashed()->find($id);

        if (!$satuan) {
            return response()->json([
                'success' => false,
                'message' => 'Satuan tidak ditemukan'
            ], 404);
        }

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

        $satuan->update([
            'name' => $request->name
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Satuan berhasil diperbarui',
            'data' => $satuan
        ]);
    }

    public function destroy($id)
    {
        $satuan = Satuan::find($id);

        if (!$satuan) {
            return response()->json([
                'success' => false,
                'message' => 'Satuan tidak ditemukan'
            ], 404);
        }

        $satuan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Satuan berhasil dihapus'
        ]);
    }

    public function trashed()
    {
        $trashed = Satuan::onlyTrashed()->get();

        return response()->json([
            'success' => true,
            'message' => 'Data satuan yang dihapus berhasil diambil',
            'data' => $trashed
        ]);
    }

    public function restore($id)
    {
        $satuan = Satuan::onlyTrashed()->find($id);

        if (!$satuan) {
            return response()->json([
                'success' => false,
                'message' => 'Satuan tidak ditemukan di data terhapus'
            ], 404);
        }

        $satuan->restore();

        return response()->json([
            'success' => true,
            'message' => 'Satuan berhasil dikembalikan',
            'data' => $satuan
        ]);
    }
}
