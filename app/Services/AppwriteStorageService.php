<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Appwrite\Services\Storage;
use Appwrite\ID;

class AppwriteStorageService
{
    private $storage;
    private $bucketId;

    public function __construct()
    {
        $this->storage = app(Storage::class);
        $this->bucketId = config('appwrite.storage_bucket_id');
    }

    /**
     * Upload file lên Appwrite Storage
     */
    public function uploadFile($file, $path = null)
    {
        try {
            $fileName = $path ?: $file->getClientOriginalName();
            $fileId = ID::unique();

            $result = $this->storage->createFile(
                $this->bucketId,
                $fileId,
                $file->getPathname()
            );

            Log::info("File uploaded to Appwrite Storage: {$fileName}");
            return $result;

        } catch (\Exception $e) {
            Log::error("Failed to upload file to Appwrite Storage: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Upload file từ URL
     */
    public function uploadFileFromUrl($url, $fileName = null)
    {
        try {
            $fileId = ID::unique();
            $fileName = $fileName ?: basename($url);

            $result = $this->storage->createFile(
                $this->bucketId,
                $fileId,
                $url
            );

            Log::info("File uploaded from URL to Appwrite Storage: {$fileName}");
            return $result;

        } catch (\Exception $e) {
            Log::error("Failed to upload file from URL to Appwrite Storage: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Lấy URL download file
     */
    public function getFileUrl($fileId)
    {
        try {
            $result = $this->storage->getFileView(
                $this->bucketId,
                $fileId
            );

            return $result;

        } catch (\Exception $e) {
            Log::error("Failed to get file URL from Appwrite Storage: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Lấy thông tin file
     */
    public function getFile($fileId)
    {
        try {
            $result = $this->storage->getFile(
                $this->bucketId,
                $fileId
            );

            return $result;

        } catch (\Exception $e) {
            Log::error("Failed to get file from Appwrite Storage: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Xóa file
     */
    public function deleteFile($fileId)
    {
        try {
            $this->storage->deleteFile(
                $this->bucketId,
                $fileId
            );

            Log::info("File deleted from Appwrite Storage: {$fileId}");
            return true;

        } catch (\Exception $e) {
            Log::error("Failed to delete file from Appwrite Storage: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Lấy danh sách file
     */
    public function listFiles($queries = [])
    {
        try {
            $result = $this->storage->listFiles(
                $this->bucketId,
                $queries
            );

            return $result;

        } catch (\Exception $e) {
            Log::error("Failed to list files from Appwrite Storage: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Cập nhật file
     */
    public function updateFile($fileId, $file)
    {
        try {
            $result = $this->storage->updateFile(
                $this->bucketId,
                $fileId,
                $file->getPathname()
            );

            Log::info("File updated in Appwrite Storage: {$fileId}");
            return $result;

        } catch (\Exception $e) {
            Log::error("Failed to update file in Appwrite Storage: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Upload ảnh profile driver
     */
    public function uploadDriverProfileImage($file, $driverId)
    {
        try {
            $fileName = "drivers/{$driverId}/profile." . $file->getClientOriginalExtension();
            return $this->uploadFile($file, $fileName);

        } catch (\Exception $e) {
            Log::error("Failed to upload driver profile image: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Upload ảnh đơn hàng
     */
    public function uploadOrderImage($file, $orderId)
    {
        try {
            $fileName = "orders/{$orderId}/" . time() . "." . $file->getClientOriginalExtension();
            return $this->uploadFile($file, $fileName);

        } catch (\Exception $e) {
            Log::error("Failed to upload order image: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Upload ảnh người dùng
     */
    public function uploadUserImage($file, $userId)
    {
        try {
            $fileName = "users/{$userId}/profile." . $file->getClientOriginalExtension();
            return $this->uploadFile($file, $fileName);

        } catch (\Exception $e) {
            Log::error("Failed to upload user image: " . $e->getMessage());
            return null;
        }
    }
} 