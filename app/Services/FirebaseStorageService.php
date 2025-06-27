<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Kreait\Firebase\Factory;
use Storage;

class FirebaseStorageService
{
    protected $storage;
    protected $bucket;
    protected $defaultBucket;

    public function __construct()
    {
        $this->defaultBucket = config('firebase.projects.app.storage.default_bucket');
        
        $factory = (new Factory)
            ->withServiceAccount(config('firebase.projects.app.credentials.file'));
            
        $this->storage = $factory->createStorage();
        $this->bucket = $this->storage->getBucket($this->defaultBucket);
    }

    /**
     * Upload an image file to Firebase Storage
     *
     * @param UploadedFile $file
     * @return string The public URL of the uploaded file
     */
    public function uploadImage(UploadedFile $file)
    {
        try {
            // Create a unique filename
            $extension = $file->getClientOriginalExtension();
            $filename = 'UserAvatar/' . time() . '_' . Str::random(10) . '.' . $extension;
            
            // Get local temp path for file
            $localPath = $file->getPathname();
            
            // Create file options including content type
            $options = [
                'name' => $filename,
                'predefinedAcl' => 'publicRead',
                'metadata' => [
                    'contentType' => $file->getMimeType(),
                ]
            ];
            
            // Upload file
            $object = $this->bucket->upload(
                fopen($localPath, 'r'),
                $options
            );
            
            // Get public URL
            return $object->signedUrl(new \DateTime('2099-01-01'));
            
        } catch (\Exception $e) {
            \Log::error("Firebase upload error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Delete an image from Firebase Storage
     *
     * @param string $url The public URL of the file
     * @return bool Success status
     */
    public function deleteImage($url)
    {
        try {
            // Extract filename from URL
            $urlPath = parse_url($url, PHP_URL_PATH);
            $parts = explode('/', $urlPath);
            
            // Get filename after /o/ in the URL
            $filename = null;
            for ($i = 0; $i < count($parts); $i++) {
                if ($parts[$i] == 'o' && isset($parts[$i + 1])) {
                    $filename = urldecode($parts[$i + 1]);
                    break;
                }
            }
            
            if (!$filename) {
                throw new \Exception("Could not extract filename from URL");
            }
            
            // Delete the object
            $object = $this->bucket->object($filename);
            if ($object->exists()) {
                $object->delete();
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            \Log::error("Firebase delete error: " . $e->getMessage());
            return false;
        }
    }
} 