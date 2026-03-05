<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogFileUpload
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $files = $request->file('files') ?? [];

        if (empty($files)) {
            Log::warning('[FileUpload] No files in request. $_FILES='.json_encode($_FILES));
        }

        foreach ($files as $index => $file) {
            $error = $file->getError();
            $errorMessage = match ($error) {
                UPLOAD_ERR_OK => 'OK',
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize ('.ini_get('upload_max_filesize').')',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE form directive',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'Upload stopped by PHP extension',
                default => "Unknown error code: {$error}",
            };

            Log::info("[FileUpload] File {$index}: name={$file->getClientOriginalName()}, error={$error} ({$errorMessage})");
        }

        Log::info('[FileUpload] PHP limits: upload_max_filesize='.ini_get('upload_max_filesize').', post_max_size='.ini_get('post_max_size'));

        $response = $next($request);

        if ($response->getStatusCode() !== 200) {
            Log::warning("[FileUpload] Upload failed with status {$response->getStatusCode()}: {$response->getContent()}");
        }

        return $response;
    }
}
