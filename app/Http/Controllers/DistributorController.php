<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Distributor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DistributorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $distributors = Distributor::with('products')->get();
            
            return response()->json([
                'success' => true,
                'message' => 'Data distributor berhasil diambil',
                'data' => $distributors
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data distributor',
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
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $distributor = Distributor::create([
                'name' => $request->name,
                'phone' => $request->phone,
                'address' => $request->address
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Distributor berhasil dibuat',
                'data' => $distributor
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat distributor',
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
            $distributor = Distributor::with('products')->find($id);

            if (!$distributor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Distributor tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data distributor berhasil diambil',
                'data' => $distributor
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data distributor',
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
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $distributor = Distributor::find($id);

            if (!$distributor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Distributor tidak ditemukan'
                ], 404);
            }

            $distributor->update([
                'name' => $request->name,
                'phone' => $request->phone,
                'address' => $request->address
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Distributor berhasil diperbarui',
                'data' => $distributor
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui distributor',
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
            $distributor = Distributor::find($id);

            if (!$distributor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Distributor tidak ditemukan'
                ], 404);
            }

            // Check if distributor has products
            if ($distributor->products()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat menghapus distributor yang masih memiliki produk'
                ], 400);
            }

            $distributor->delete();

            return response()->json([
                'success' => true,
                'message' => 'Distributor berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus distributor',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}