# Hướng dẫn sử dụng Appwrite Function

## Function ID: `686a1e4a0010de76b3ea`

Function này được tạo để xử lý location updates từ driver trong hệ thống delivery.

## Thông tin Function

- **Function ID**: `686a1e4a0010de76b3ea`
- **Function Name**: `process-location`
- **Runtime**: PHP 8.0
- **Status**: Active
- **Domain**: `686a1e4b00227f3d98a4.nyc.appwrite.run`

## Cách sử dụng

### 1. Test bằng Command Line

```bash
# Test với Function ID mặc định
php artisan appwrite:test-function

# Test với Function ID cụ thể
php artisan appwrite:test-function 686a1e4a0010de76b3ea

# Test với payload tùy chỉnh
php artisan appwrite:test-function 686a1e4a0010de76b3ea --payload='{"driver_id":"test","location":{"latitude":10.762622,"longitude":106.660172}}'

# Test với type khác
php artisan appwrite:test-function --type=notification
```

### 2. Test bằng API

```bash
# Test với payload mặc định
curl -X POST http://localhost:8000/api/appwrite/function/execute \
  -H "Content-Type: application/json"

# Test với Function ID cụ thể
curl -X POST http://localhost:8000/api/appwrite/function/execute \
  -H "Content-Type: application/json" \
  -d '{
    "function_id": "686a1e4a0010de76b3ea",
    "type": "location"
  }'

# Test với payload tùy chỉnh
curl -X POST http://localhost:8000/api/appwrite/function/execute \
  -H "Content-Type: application/json" \
  -d '{
    "function_id": "686a1e4a0010de76b3ea",
    "data": {
      "driver_id": "driver_001",
      "location": {
        "latitude": 10.762622,
        "longitude": 106.660172,
        "speed": 25.5,
        "bearing": 90,
        "accuracy": 10,
        "isOnline": true,
        "status": "active"
      },
      "action": "location_update",
      "timestamp": 1640995200
    }
  }'
```

### 3. Sử dụng trong Code

```php
use App\Services\AppwriteFunctionsService;

$functionsService = new AppwriteFunctionsService();

// Gọi function với payload mặc định
$result = $functionsService->processLocation([
    'driver_id' => 'driver_001',
    'location' => [
        'latitude' => 10.762622,
        'longitude' => 106.660172,
        'speed' => 25.5,
        'bearing' => 90,
        'accuracy' => 10,
        'isOnline' => true,
        'status' => 'active'
    ],
    'action' => 'location_update',
    'timestamp' => time()
]);

// Gọi function trực tiếp
$result = $functionsService->executeFunction('686a1e4a0010de76b3ea', $data);
```

## Payload Format

### Input Payload

```json
{
  "driver_id": "string",
  "location": {
    "latitude": "number",
    "longitude": "number", 
    "speed": "number",
    "bearing": "number",
    "accuracy": "number",
    "isOnline": "boolean",
    "status": "string"
  },
  "action": "string",
  "timestamp": "number"
}
```

### Output Response

```json
{
  "success": true,
  "message": "Location processed successfully",
  "data": {
    "driver_id": "string",
    "location_data": {
      "latitude": "number",
      "longitude": "number",
      "speed": "number",
      "bearing": "number", 
      "accuracy": "number",
      "isOnline": "boolean",
      "status": "string",
      "timestamp": "number",
      "speed_kmh": "number",
      "speed_mph": "number",
      "geolocation": {
        "country": "string",
        "city": "string",
        "address": "string"
      }
    },
    "processed_at": "string",
    "action": "string",
    "metadata": {
      "function_id": "string",
      "function_name": "string",
      "processing_time": "number",
      "version": "string"
    }
  },
  "timestamp": "number",
  "function_execution_id": "string"
}
```

## Validation Rules

### Required Fields
- `driver_id`: ID của driver
- `location.latitude`: Vĩ độ (-90 đến 90)
- `location.longitude`: Kinh độ (-180 đến 180)

### Optional Fields
- `location.speed`: Tốc độ (m/s)
- `location.bearing`: Hướng (độ)
- `location.accuracy`: Độ chính xác (m)
- `location.isOnline`: Trạng thái online
- `location.status`: Trạng thái hoạt động
- `action`: Loại hành động
- `timestamp`: Thời gian

## Error Handling

### Common Errors

```json
{
  "success": false,
  "message": "Error message",
  "error": "Detailed error information"
}
```

### Error Types
- `400`: Bad Request (thiếu hoặc sai dữ liệu)
- `500`: Internal Server Error (lỗi server)

## Monitoring

### Logs
Function logs có thể xem trong Appwrite Console:
1. Vào Functions > `686a1e4a0010de76b3ea`
2. Tab "Executions" để xem execution history
3. Tab "Logs" để xem detailed logs

### Metrics
- Execution time
- Success/failure rate
- Memory usage
- CPU usage

## Deployment

### Update Function Code
1. Vào Appwrite Console > Functions
2. Chọn function `686a1e4a0010de76b3ea`
3. Tab "Settings" > "Source Code"
4. Cập nhật code và deploy

### Environment Variables
Function có thể sử dụng environment variables:
```php
$apiKey = getenv('APPWRITE_API_KEY');
$projectId = getenv('APPWRITE_PROJECT_ID');
```

## Best Practices

1. **Validate Input**: Luôn validate dữ liệu đầu vào
2. **Error Handling**: Xử lý lỗi một cách graceful
3. **Logging**: Log đầy đủ thông tin để debug
4. **Performance**: Tối ưu execution time
5. **Security**: Validate và sanitize input data

## Troubleshooting

### Function không execute
1. Kiểm tra Function ID có đúng không
2. Kiểm tra API Key có đủ permissions
3. Kiểm tra function có active không

### Payload validation failed
1. Kiểm tra format JSON
2. Kiểm tra required fields
3. Kiểm tra data types

### Performance issues
1. Kiểm tra execution logs
2. Monitor resource usage
3. Optimize code nếu cần

## Support

- Appwrite Documentation: https://appwrite.io/docs
- Function Logs: Appwrite Console > Functions > Executions
- Community: https://appwrite.io/discord 