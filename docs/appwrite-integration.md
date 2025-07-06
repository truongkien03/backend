# Hướng dẫn tích hợp Appwrite vào dự án Laravel

## Tổng quan

Appwrite là một Backend-as-a-Service (BaaS) mạnh mẽ cung cấp các dịch vụ như:
- **Database**: NoSQL database với realtime capabilities
- **Storage**: File storage với CDN
- **Functions**: Serverless functions
- **Realtime**: WebSocket connections
- **Authentication**: User management
- **Messaging**: Push notifications

## Cài đặt

### 1. Cài đặt Appwrite SDK

```bash
composer require appwrite/appwrite
```

### 2. Cấu hình môi trường

Thêm các biến môi trường vào file `.env`:

```env
# Appwrite Configuration
APPWRITE_PROJECT_ID=your_project_id_here
APPWRITE_ENDPOINT=https://cloud.appwrite.io/v1
APPWRITE_API_KEY=your_api_key_here
APPWRITE_DATABASE_ID=your_database_id_here
APPWRITE_STORAGE_BUCKET_ID=your_storage_bucket_id_here

# Collections
APPWRITE_COLLECTION_USERS=users_collection_id
APPWRITE_COLLECTION_DRIVERS=drivers_collection_id
APPWRITE_COLLECTION_ORDERS=orders_collection_id
APPWRITE_COLLECTION_LOCATIONS=locations_collection_id
APPWRITE_COLLECTION_NOTIFICATIONS=notifications_collection_id

# Functions
APPWRITE_FUNCTION_PROCESS_LOCATION=process_location_function_id
APPWRITE_FUNCTION_SEND_NOTIFICATION=send_notification_function_id

# Realtime
APPWRITE_REALTIME_ENABLED=true
```

## Cấu trúc dự án

### Services

1. **AppwriteRealtimeService** (`app/Services/AppwriteRealtimeService.php`)
   - Quản lý realtime location updates
   - Thay thế FirebaseRealtimeService
   - Xử lý online drivers tracking

2. **AppwriteStorageService** (`app/Services/AppwriteStorageService.php`)
   - Upload/download files
   - Quản lý images cho drivers, users, orders
   - Thay thế FirebaseStorageService

3. **AppwriteFunctionsService** (`app/Services/AppwriteFunctionsService.php`)
   - Gọi cloud functions
   - Xử lý notifications
   - Business logic processing

### Controllers

- **AppwriteController** (`app/Http/Controllers/Api/AppwriteController.php`)
  - API endpoints để test Appwrite
  - CRUD operations cho locations, files, functions

### Commands

- **TestAppwriteCommand** (`app/Console/Commands/TestAppwriteCommand.php`)
  - Test các chức năng Appwrite
  - Debug và troubleshooting

## API Endpoints

### Realtime & Location

```bash
# Test kết nối
GET /api/appwrite/test-connection

# Lưu location mới
POST /api/appwrite/location/save
{
    "driver_id": "driver_001",
    "latitude": 10.762622,
    "longitude": 106.660172,
    "speed": 25.5,
    "bearing": 90,
    "accuracy": 10,
    "isOnline": true,
    "status": "active"
}

# Lấy location của driver
GET /api/appwrite/location/driver?driver_id=driver_001

# Lấy tất cả driver online
GET /api/appwrite/drivers/online
```

### Storage

```bash
# Upload file
POST /api/appwrite/file/upload
Content-Type: multipart/form-data
{
    "file": [file],
    "path": "optional/path"
}
```

### Functions

```bash
# Gọi cloud function
POST /api/appwrite/function/execute
{
    "function_id": "function_id_here",
    "data": {
        "key": "value"
    }
}

# Gửi notification
POST /api/appwrite/notification/send
{
    "user_id": "user_001",
    "message": "Hello from Appwrite!",
    "type": "info"
}
```

### Info

```bash
# Lấy thông tin Appwrite
GET /api/appwrite/info
```

## Sử dụng trong code

### Realtime Service

```php
use App\Services\AppwriteRealtimeService;

$realtimeService = new AppwriteRealtimeService();

// Lưu location
$locationData = [
    'latitude' => 10.762622,
    'longitude' => 106.660172,
    'speed' => 25.5,
    'bearing' => 90,
    'accuracy' => 10,
    'isOnline' => true,
    'status' => 'active',
    'timestamp' => time() * 1000
];

$documentId = $realtimeService->saveLocation('driver_001', $locationData);

// Lấy location
$location = $realtimeService->getDriverLocation('driver_001');

// Lấy online drivers
$onlineDrivers = $realtimeService->getAllOnlineDrivers();
```

### Storage Service

```php
use App\Services\AppwriteStorageService;

$storageService = new AppwriteStorageService();

// Upload file
$result = $storageService->uploadFile($request->file('image'));

// Upload từ URL
$result = $storageService->uploadFileFromUrl('https://example.com/image.jpg');

// Lấy file URL
$fileUrl = $storageService->getFileUrl($fileId);
```

### Functions Service

```php
use App\Services\AppwriteFunctionsService;

$functionsService = new AppwriteFunctionsService();

// Gọi function
$result = $functionsService->executeFunction('function_id', $data);

// Gửi notification
$result = $functionsService->sendUserNotification('user_001', 'Hello!', 'info');
```

## Testing

### Command Line

```bash
# Test tất cả services
php artisan appwrite:test

# Test riêng từng service
php artisan appwrite:test --service=realtime
php artisan appwrite:test --service=storage
php artisan appwrite:test --service=functions
```

### API Testing

```bash
# Test kết nối
curl -X GET http://localhost:8000/api/appwrite/test-connection

# Lưu location
curl -X POST http://localhost:8000/api/appwrite/location/save \
  -H "Content-Type: application/json" \
  -d '{
    "driver_id": "test_driver",
    "latitude": 10.762622,
    "longitude": 106.660172,
    "speed": 25.5,
    "isOnline": true
  }'
```

## Thiết lập Appwrite Console

### 1. Tạo Project

1. Đăng ký tại [appwrite.io](https://appwrite.io)
2. Tạo project mới
3. Lấy Project ID từ Settings

### 2. Tạo Database

1. Vào Databases > Create Database
2. Tạo database với tên "delivery-app"
3. Lấy Database ID

### 3. Tạo Collections

Tạo các collections sau:

#### Locations Collection
```json
{
  "driver_id": "string",
  "location_data": "object",
  "timestamp": "integer",
  "created_at": "string",
  "updated_at": "string"
}
```

#### Users Collection
```json
{
  "user_id": "string",
  "name": "string",
  "email": "string",
  "phone": "string",
  "avatar": "string",
  "created_at": "string"
}
```

#### Drivers Collection
```json
{
  "driver_id": "string",
  "name": "string",
  "phone": "string",
  "vehicle_info": "object",
  "is_online": "boolean",
  "current_location": "object",
  "created_at": "string"
}
```

### 4. Tạo Storage Bucket

1. Vào Storage > Create Bucket
2. Tạo bucket với tên "delivery-files"
3. Cấu hình permissions

### 5. Tạo Functions (Optional)

1. Vào Functions > Create Function
2. Tạo function để xử lý location updates
3. Tạo function để gửi notifications

### 6. Tạo API Key

1. Vào Settings > API Keys
2. Tạo API Key với permissions cần thiết
3. Copy API Key vào .env

## Migration từ Firebase

### Thay thế FirebaseRealtimeService

```php
// Thay vì sử dụng FirebaseRealtimeService
use App\Services\FirebaseRealtimeService;

// Sử dụng AppwriteRealtimeService
use App\Services\AppwriteRealtimeService;
```

### Thay thế FirebaseStorageService

```php
// Thay vì sử dụng FirebaseStorageService
use App\Services\FirebaseStorageService;

// Sử dụng AppwriteStorageService
use App\Services\AppwriteStorageService;
```

## Troubleshooting

### Lỗi kết nối

1. Kiểm tra Project ID và API Key
2. Đảm bảo API Key có đủ permissions
3. Kiểm tra network connectivity

### Lỗi Database

1. Kiểm tra Database ID và Collection IDs
2. Đảm bảo collection schema đúng
3. Kiểm tra permissions của collection

### Lỗi Storage

1. Kiểm tra Storage Bucket ID
2. Đảm bảo bucket permissions đúng
3. Kiểm tra file size limits

### Lỗi Functions

1. Kiểm tra Function ID
2. Đảm bảo function đã deploy
3. Kiểm tra function logs

## Best Practices

1. **Error Handling**: Luôn wrap Appwrite calls trong try-catch
2. **Logging**: Log tất cả operations để debug
3. **Validation**: Validate data trước khi gửi đến Appwrite
4. **Caching**: Cache kết quả khi cần thiết
5. **Rate Limiting**: Implement rate limiting cho API calls
6. **Security**: Sử dụng environment variables cho sensitive data

## Performance Tips

1. **Batch Operations**: Sử dụng batch operations khi có thể
2. **Pagination**: Implement pagination cho large datasets
3. **Indexing**: Tạo indexes cho frequently queried fields
4. **Caching**: Cache frequently accessed data
5. **Connection Pooling**: Reuse Appwrite client instances

## Monitoring

1. **Logs**: Monitor Appwrite logs
2. **Metrics**: Track API usage và performance
3. **Alerts**: Set up alerts cho errors
4. **Health Checks**: Implement health check endpoints

## Support

- [Appwrite Documentation](https://appwrite.io/docs)
- [Appwrite SDK Documentation](https://appwrite.io/docs/references/cloud/web)
- [Appwrite Community](https://appwrite.io/discord) 