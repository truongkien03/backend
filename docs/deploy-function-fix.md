# Hướng dẫn Deploy Function để khắc phục Timeout

## 🔍 **Vấn đề hiện tại:**
- Function timeout sau 20 giây
- Error: "Operation timed out after 20000 milliseconds"
- Status: failed

## 🛠️ **Giải pháp: Deploy lại Function**

### **Bước 1: Vào Appwrite Console**
1. Mở [Appwrite Console](https://cloud.appwrite.io)
2. Chọn project của bạn
3. Vào **Functions** > **686a1e4a0010de76b3ea**

### **Bước 2: Deploy Function Code mới**
1. Tab **Settings** > **Source Code**
2. Xóa code cũ
3. Copy code từ `functions/optimized-simple/index.php`:

```php
<?php

use Utopia\App;
use Utopia\CLI\Console;

App::init(function (array $utopia, array $request, array $response, array $args) {
    // Optimized simple function - minimal processing
    Console::log('Function started');
    
    $payload = $request['payload'] ?? '{}';
    $data = json_decode($payload, true) ?: [];
    
    // Simple response without complex processing
    $result = [
        'success' => true,
        'message' => 'Function executed successfully',
        'received_data' => $data,
        'timestamp' => time(),
        'function_id' => '686a1e4a0010de76b3ea'
    ];
    
    Console::log('Function completed');
    return $result;
    
}, ['utopia', 'request', 'response', 'args']);

App::shutdown(function (array $utopia, array $request, array $response, array $args) {
    Console::log('Function shutdown');
}, ['utopia', 'request', 'response', 'args']);
```

4. Click **Deploy**

### **Bước 3: Kiểm tra Deployment**
1. Tab **Deployments**
2. Đảm bảo deployment mới có status **Active**
3. Ghi nhớ **Deployment ID** mới

### **Bước 4: Test Function**
```bash
# Test với Laravel command
php artisan appwrite:test-permissions

# Test với payload đơn giản
php artisan appwrite:test-simple --payload='{"test":true}'
```

## 📋 **Code Function tối ưu:**

### **Phiên bản 1: Đơn giản nhất**
```php
<?php
use Utopia\App;
use Utopia\CLI\Console;

App::init(function (array $utopia, array $request, array $response, array $args) {
    return ['success' => true, 'message' => 'Hello World'];
}, ['utopia', 'request', 'response', 'args']);
```

### **Phiên bản 2: Xử lý payload**
```php
<?php
use Utopia\App;
use Utopia\CLI\Console;

App::init(function (array $utopia, array $request, array $response, array $args) {
    $payload = $request['payload'] ?? '{}';
    $data = json_decode($payload, true) ?: [];
    
    return [
        'success' => true,
        'data' => $data,
        'timestamp' => time()
    ];
}, ['utopia', 'request', 'response', 'args']);
```

### **Phiên bản 3: Xử lý location (sau khi fix)**
```php
<?php
use Utopia\App;
use Utopia\CLI\Console;

App::init(function (array $utopia, array $request, array $response, array $args) {
    $payload = $request['payload'] ?? '{}';
    $data = json_decode($payload, true) ?: [];
    
    // Validate required fields
    if (empty($data['driver_id']) || empty($data['location'])) {
        return [
            'success' => false,
            'error' => 'Missing required fields'
        ];
    }
    
    // Process location data
    $location = $data['location'];
    $processed = [
        'driver_id' => $data['driver_id'],
        'latitude' => $location['latitude'] ?? 0,
        'longitude' => $location['longitude'] ?? 0,
        'speed' => $location['speed'] ?? 0,
        'timestamp' => time()
    ];
    
    return [
        'success' => true,
        'data' => $processed
    ];
}, ['utopia', 'request', 'response', 'args']);
```

## 🔧 **Cấu hình Function:**

### **Settings cần kiểm tra:**
1. **Runtime**: PHP 8.0 ✅
2. **Timeout**: 20 seconds (default)
3. **Memory**: 128 MB (default)
4. **Permissions**: Public execution

### **Environment Variables (nếu cần):**
```env
APPWRITE_PROJECT_ID=your_project_id
APPWRITE_API_KEY=your_api_key
```

## 📊 **Monitoring:**

### **Kiểm tra Logs:**
1. Functions > Executions
2. Click vào execution để xem logs
3. Kiểm tra status và duration

### **Expected Results:**
- **Status**: completed
- **Duration**: < 5 seconds
- **Response**: JSON với success: true

## 🚨 **Troubleshooting:**

### **Nếu vẫn timeout:**
1. Giảm complexity của function
2. Loại bỏ external API calls
3. Sử dụng code đơn giản nhất

### **Nếu lỗi 403:**
1. Kiểm tra API Key permissions
2. Đảm bảo function có public execution
3. Kiểm tra project settings

### **Nếu lỗi 500:**
1. Kiểm tra PHP syntax
2. Xem function logs
3. Sử dụng try-catch

## ✅ **Test Commands:**

```bash
# Test cấu hình
php artisan appwrite:test-permissions --check-config

# Test function đơn giản
php artisan appwrite:test-simple

# Test function phức tạp
php artisan appwrite:test-function --type=location

# Test trực tiếp
php artisan appwrite:test-direct
```

## 📞 **Support:**

- Appwrite Documentation: https://appwrite.io/docs
- Function Logs: Appwrite Console > Functions > Executions
- Community: https://appwrite.io/discord 