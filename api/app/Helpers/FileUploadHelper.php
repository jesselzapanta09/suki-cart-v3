<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;

class FileUploadHelper
{
    /**
     * Store an uploaded image locally under public/uploads/{folder}/.
     *
     * @param  string        $folder  Sub-folder name, e.g. 'profile', 'banners'
     * @param  UploadedFile  $file    The uploaded file instance
     * @return string                 Relative path stored in DB, e.g. 'uploads/profile/profile_xxx.jpg'
     */
    public static function storeImage(string $folder, UploadedFile $file): string
    {
        $uploadPath = public_path('storage/uploads/' . $folder);

        if (!file_exists($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $filename = uniqid($folder . '_') . '.' . $file->getClientOriginalExtension();
        $file->move($uploadPath, $filename);

        return 'storage/uploads/' . $folder . '/' . $filename;
    }

    /**
     * Delete a stored file given its relative path.
     *
     * @param  string|null  $relativePath  e.g. 'uploads/profile/profile_xxx.jpg'
     */
    public static function delete(?string $relativePath): void
    {
        if (!$relativePath) return;

        $fullPath = public_path($relativePath);

        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    /**
     * Replace an existing file: delete the old one and store the new one.
     *
     * @param  string        $folder
     * @param  UploadedFile  $file
     * @param  string|null   $existingPath  Path of the file to delete before storing the new one
     * @return string                       New relative path
     */
    public static function replace(string $folder, UploadedFile $file, ?string $existingPath = null): string
    {
        self::delete($existingPath);
        return self::storeImage($folder, $file);
    }

    /**
     * Return the public URL for a stored file, or null if no path given.
     */
    public static function url(?string $relativePath): ?string
    {
        if (!$relativePath) return null;
        return url($relativePath);
    }
}
