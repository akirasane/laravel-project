<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InputSanitization
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get security configuration
        $config = config('security.validation');

        // Check maximum input variables
        if (count($request->all()) > $config['max_input_vars']) {
            abort(413, 'Too many input variables');
        }

        // Sanitize input data
        if ($config['sanitize_html']) {
            $this->sanitizeInput($request);
        }

        // Validate file uploads
        $this->validateFileUploads($request, $config);

        return $next($request);
    }

    /**
     * Sanitize input data to prevent XSS attacks.
     */
    private function sanitizeInput(Request $request): void
    {
        $input = $request->all();
        $sanitized = $this->sanitizeArray($input);
        $request->replace($sanitized);
    }

    /**
     * Recursively sanitize array data.
     */
    private function sanitizeArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                // Remove potentially dangerous HTML tags and attributes
                $data[$key] = strip_tags($value, '<p><br><strong><em><ul><ol><li>');
                
                // Remove JavaScript event handlers
                $data[$key] = preg_replace('/on\w+="[^"]*"/i', '', $data[$key]);
                
                // Remove script tags
                $data[$key] = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $data[$key]);
                
                // Trim whitespace
                $data[$key] = trim($data[$key]);
            }
        }

        return $data;
    }

    /**
     * Validate file uploads for security.
     */
    private function validateFileUploads(Request $request, array $config): void
    {
        foreach ($request->allFiles() as $file) {
            if (is_array($file)) {
                foreach ($file as $uploadedFile) {
                    $this->validateSingleFile($uploadedFile, $config);
                }
            } else {
                $this->validateSingleFile($file, $config);
            }
        }
    }

    /**
     * Validate a single uploaded file.
     */
    private function validateSingleFile($file, array $config): void
    {
        if (!$file || !$file->isValid()) {
            return;
        }

        // Check file size
        if ($file->getSize() > ($config['max_file_size'] * 1024)) {
            abort(413, 'File size exceeds maximum allowed size');
        }

        // Check file extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $config['allowed_file_types'])) {
            abort(415, 'File type not allowed');
        }

        // Check MIME type
        $mimeType = $file->getMimeType();
        $allowedMimeTypes = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls' => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        ];

        if (isset($allowedMimeTypes[$extension])) {
            if (!in_array($mimeType, $allowedMimeTypes[$extension])) {
                abort(415, 'File MIME type does not match extension');
            }
        }
    }
}