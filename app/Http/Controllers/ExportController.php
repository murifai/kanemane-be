<?php

namespace App\Http\Controllers;

use App\Models\Export;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * Download export file using token
     */
    public function download(string $token)
    {
        $export = Export::where('token', $token)->firstOrFail();

        // Check if expired
        if ($export->isExpired()) {
            // Delete expired file
            if (Storage::exists($export->filepath)) {
                Storage::delete($export->filepath);
            }
            $export->delete();
            
            return response()->json([
                'error' => 'Link sudah kadaluarsa. Silakan buat export baru.'
            ], 410);
        }

        // Check if file exists
        if (!Storage::exists($export->filepath)) {
            return response()->json([
                'error' => 'File tidak ditemukan.'
            ], 404);
        }

        // Mark as downloaded
        $export->markAsDownloaded();

        // Stream file download
        return Storage::download($export->filepath, $export->filename);
    }
}
