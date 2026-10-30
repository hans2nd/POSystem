<?php

namespace App\Http\Controllers;

use App\Services\MasterExportImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MasterExportImportController extends Controller
{
    public function __construct(
        protected MasterExportImportService $service
    ) {}

    /**
     * Download Excel template for master data import
     */
    public function template(string $type): StreamedResponse
    {
        return $this->service->downloadTemplate($type);
    }

    /**
     * Export all master data to Excel
     */
    public function export(string $type): StreamedResponse
    {
        return $this->service->exportData($type);
    }

    /**
     * Import master data from uploaded Excel/CSV file
     */
    public function import(Request $request, string $type): JsonResponse
    {
        try {
            if (!$request->hasFile('file') || !$request->file('file')->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pilih file Excel atau CSV yang valid untuk diunggah.',
                ], 422);
            }

            $file = $request->file('file');
            $ext = strtolower($file->getClientOriginalExtension());

            if (!in_array($ext, ['xlsx', 'xls', 'csv', 'txt'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Format file harus berupa Excel (.xlsx, .xls) atau CSV (.csv).',
                ], 422);
            }

            if ($file->getSize() > 10 * 1024 * 1024) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ukuran file maksimal 10MB.',
                ], 422);
            }

            $updateExisting = $request->boolean('update_existing', true);
            $result = $this->service->importData($type, $file, $updateExisting);

            return response()->json($result, $result['success'] ? 200 : 422);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Master Import Error [{$type}]: " . $e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memproses file: ' . $e->getMessage(),
            ], 422);
        }
    }
}
