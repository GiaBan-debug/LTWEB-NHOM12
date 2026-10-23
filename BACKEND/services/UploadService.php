<?php
// BACKEND/services/UploadService.php

class UploadService {
    private string $baseDir;
    private array  $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private int    $maxSize      = 5 * 1024 * 1024; // 5 MB

    public function __construct() {
        // Thu muc uploads nam o goc project
        $this->baseDir = dirname(__DIR__, 2) . '/uploads/';
    }

    /**
     * Upload mot file anh vao thu muc con chi dinh
     * @param  array  $file   Phan tu tu $_FILES
     * @param  string $folder 'products' | 'banners' | 'posts'
     * @return string|false   Duong dan tuong doi de luu DB, hoac false neu loi
     */
    public function uploadImage(array $file, string $folder = 'products'): string|false {
        if ($file['error'] !== UPLOAD_ERR_OK) return false;
        if ($file['size'] > $this->maxSize)    return false;

        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, $this->allowedTypes, true)) return false;

        $ext      = $this->mimeToExt($mime);
        $filename = uniqid('img_', true) . '.' . $ext;
        $dir      = $this->baseDir . $folder . '/';

        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $dest = $dir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) return false;

        // Tra ve duong dan tuong doi (luu vao DB)
        return "uploads/{$folder}/{$filename}";
    }

    public function delete(string $relativePath): bool {
        $full = dirname(__DIR__, 2) . '/' . ltrim($relativePath, '/');
        if (file_exists($full)) return unlink($full);
        return false;
    }

    private function mimeToExt(string $mime): string {
        return match($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            default      => 'jpg',
        };
    }
}