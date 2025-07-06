# Khắc phục lỗi Appwrite Function

## Lỗi hiện tại: Function Timeout và Failed

### Nguyên nhân có thể:

1. **Function chưa được deploy đúng cách**
2. **Code function có lỗi syntax**
3. **Timeout do function chạy quá lâu**
4. **Thiếu dependencies**
5. **Lỗi cấu hình**

## Các bước khắc phục:

### 1. Kiểm tra Function trong Appwrite Console

1. Vào Appwrite Console > Functions
2. Chọn function `686a1e4a0010de76b3ea`
3. Kiểm tra:
   - **Status**: Phải là "Active"
   - **Runtime**: PHP 8.0
   - **Deployment**: Phải có deployment active

### 2. Deploy lại Function Code

1. Vào function > Settings > Source Code
2. Copy code từ `functions/process-location/index.php`
3. Deploy lại function

### 3. Test với Function đơn giản

```bash
# Test với payload đơn giản
php artisan appwrite:test-simple

# Test với payload tùy chỉnh
php artisan appwrite:test-simple --payload='{"test":true,"message":"hello"}'
```

### 4. Kiểm tra Logs

1. Vào function > Executions
2. Xem execution history
3. Kiểm tra logs và errors

### 5. Tạo Function mới để test

Nếu function hiện tại vẫn lỗi, tạo function mới:

1. Tạo function mới trong Appwrite Console
2. Sử dụng code đơn giản từ `functions/simple-test/index.php`
3. Test với function mới

## Code Function đơn giản để test:

```php
<?php

use Utopia\App;
use Utopia\CLI\Console;

App::init(function (array $utopia, array $request, array $response, array $args) {
    Console::log('Function started');
    
    $payload = $request['payload'] ?? '{}';
    $data = json_decode($payload, true) ?: [];
    
    Console::log('Received: ' . $payload);
    
    return [
        'success' => true,
        'message' => 'Test successful',
        'data' => $data,
        'timestamp' => time()
    ];
    
}, ['utopia', 'request', 'response', 'args']);

App::shutdown(function (array $utopia, array $request, array $response, array $args) {
    Console::log('Function completed');
}, ['utopia', 'request', 'response', 'args']);
```

## Test từng bước:

### Bước 1: Test kết nối cơ bản
```bash
php artisan appwrite:test-simple
```

### Bước 2: Test với payload nhỏ
```bash
php artisan appwrite:test-simple --payload='{"test":true}'
```

### Bước 3: Test với payload location
```bash
php artisan appwrite:test-simple --payload='{"driver_id":"test","location":{"latitude":10.762622,"longitude":106.660172}}'
```

### Bước 4: Test function phức tạp
```bash
php artisan appwrite:test-function --type=location
```

## Các lỗi thường gặp:

### 1. Timeout Error
```
Error Number: 28. Error Msg: Operation timed out after 20000 milliseconds
```

**Giải pháp:**
- Giảm thời gian xử lý trong function
- Tối ưu code
- Tăng timeout trong Appwrite Console

### 2. Function Not Found
```
Function not found or not accessible
```

**Giải pháp:**
- Kiểm tra Function ID
- Kiểm tra API Key permissions
- Kiểm tra function status

### 3. Invalid Payload
```
Invalid JSON payload
```

**Giải pháp:**
- Validate JSON format
- Kiểm tra encoding
- Sử dụng payload đơn giản

### 4. Runtime Error
```
PHP Fatal error
```

**Giải pháp:**
- Kiểm tra PHP syntax
- Kiểm tra dependencies
- Sử dụng try-catch

## Monitoring và Debug:

### 1. Appwrite Console Logs
- Functions > Executions > View Logs
- Kiểm tra execution history
- Xem error details

### 2. Laravel Logs
```bash
tail -f storage/logs/laravel.log
```

### 3. Test Commands
```bash
# Test tất cả services
php artisan appwrite:test

# Test function đơn giản
php artisan appwrite:test-simple

# Test function phức tạp
php artisan appwrite:test-function
```

## Best Practices:

1. **Start Simple**: Bắt đầu với function đơn giản
2. **Test Incrementally**: Test từng bước một
3. **Monitor Logs**: Luôn kiểm tra logs
4. **Handle Errors**: Xử lý lỗi gracefully
5. **Validate Input**: Validate dữ liệu đầu vào
6. **Use Timeouts**: Đặt timeout hợp lý

## Support:

- Appwrite Documentation: https://appwrite.io/docs
- Function Logs: Appwrite Console > Functions > Executions
- Community: https://appwrite.io/discord
- GitHub Issues: https://github.com/appwrite/appwrite/issues 