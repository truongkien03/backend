# Proximity Service Guide

## Tổng quan

Proximity Service là một hệ thống tự động theo dõi vị trí của driver từ Firebase Realtime Database, so sánh với tọa độ đặt hàng trong cơ sở dữ liệu và gửi thông báo FCM cho driver khi có đơn hàng gần.

## Các thành phần chính

### 1. LocationProximityService
- **File**: `app/Services/LocationProximityService.php`
- **Chức năng**: Xử lý logic so sánh tọa độ và gửi thông báo
- **Tính năng**:
  - Tìm đơn hàng gần driver trong bán kính cho trước
  - Tính khoảng cách sử dụng Haversine formula
  - Gửi thông báo FCM cho driver
  - Hỗ trợ cấu hình bán kính gần

### 2. ProcessProximityCheck Job
- **File**: `app/Jobs/ProcessProximityCheck.php`
- **Chức năng**: Xử lý proximity check một cách bất đồng bộ
- **Tính năng**:
  - Chạy trong queue để tránh block main thread
  - Xử lý lỗi và retry tự động
  - Logging chi tiết

### 3. OrderProximityAlert Notification
- **File**: `app/Notifications/OrderProximityAlert.php`
- **Chức năng**: Gửi thông báo FCM cho driver
- **Tính năng**:
  - Thông báo push notification
  - Broadcast event cho realtime updates
  - Dữ liệu chi tiết về đơn hàng

### 4. ProximityController
- **File**: `app/Http/Controllers/Api/ProximityController.php`
- **Chức năng**: API endpoints để test và quản lý proximity
- **Endpoints**:
  - `GET /api/proximity/nearby-orders` - Tìm đơn hàng gần
  - `GET /api/proximity/driver/{id}/test` - Test proximity cho driver
  - `POST /api/proximity/simulate-location` - Simulate location update
  - `GET /api/proximity/stats` - Thống kê proximity

## Cách sử dụng

### 1. Khởi động Proximity Worker

#### Sử dụng Firebase Realtime (Khuyến nghị)
```bash
# Khởi động worker với Firebase realtime listener
php artisan proximity:worker --firebase --radius=2.0

# Hoặc với cấu hình tùy chỉnh
php artisan proximity:worker --firebase --radius=3.0 --interval=60
```

#### Sử dụng Periodic Check
```bash
# Khởi động worker với periodic check (mỗi 30 giây)
php artisan proximity:worker --radius=2.0 --interval=30

# Hoặc với cấu hình tùy chỉnh
php artisan proximity:worker --radius=1.5 --interval=60
```

### 2. Test chức năng

#### Test với tọa độ cụ thể
```bash
# Test với tọa độ Hồ Chí Minh
php artisan test:proximity --lat=10.8231 --lon=106.6297 --radius=2.0

# Test với tọa độ khác
php artisan test:proximity --lat=21.0285 --lon=105.8542 --radius=1.5
```

#### Test với driver cụ thể
```bash
# Test với driver ID
php artisan test:proximity --driver-id=DRIVER_001

# List tất cả đơn hàng pending
php artisan test:proximity --list-orders
```

### 3. API Testing

#### Tìm đơn hàng gần
```bash
curl -X GET "http://your-domain/api/proximity/nearby-orders?latitude=10.8231&longitude=106.6297&radius=2.0"
```

#### Test proximity cho driver
```bash
curl -X GET "http://your-domain/api/proximity/driver/DRIVER_001/test"
```

#### Simulate location update
```bash
curl -X POST "http://your-domain/api/proximity/simulate-location" \
  -H "Content-Type: application/json" \
  -d '{
    "driver_id": "DRIVER_001",
    "latitude": 10.8231,
    "longitude": 106.6297,
    "is_online": true
  }'
```

#### Lấy thống kê
```bash
curl -X GET "http://your-domain/api/proximity/stats"
```

## Cấu hình

### 1. Bán kính gần (Proximity Radius)
- **Mặc định**: 2.0 km
- **Cấu hình**: Trong `config/const.php` hoặc qua command line
- **Khuyến nghị**: 1.5 - 3.0 km tùy theo khu vực

### 2. FCM Configuration
- **File**: `config/firebase.php`
- **Topics**: `all_drivers` cho broadcast notifications
- **Individual tokens**: Cho thông báo trực tiếp

### 3. Queue Configuration
- **Driver**: `database` hoặc `redis`
- **Retry**: 3 lần với delay tăng dần
- **Timeout**: 60 giây

## Luồng hoạt động

### 1. Firebase Realtime Listener
```
Firebase Realtime Database
    ↓ (location update)
FirebaseRealtimeService
    ↓ (process location)
ProcessFirebaseLocationUpdate Job
    ↓ (dispatch)
ProcessProximityCheck Job
    ↓ (check proximity)
LocationProximityService
    ↓ (find nearby orders)
OrderProximityAlert Notification
    ↓ (send FCM)
Driver receives notification
```

### 2. Periodic Check
```
Cron/Worker
    ↓ (every 30 seconds)
StartProximityWorker
    ↓ (get online drivers)
ProcessProximityCheck Job
    ↓ (check proximity)
LocationProximityService
    ↓ (find nearby orders)
OrderProximityAlert Notification
    ↓ (send FCM)
Driver receives notification
```

## Monitoring và Logging

### 1. Log Files
- **Location updates**: `storage/logs/laravel.log`
- **Proximity checks**: `storage/logs/laravel.log`
- **FCM notifications**: `storage/logs/laravel.log`

### 2. Queue Monitoring
```bash
# Xem queue status
php artisan queue:work --verbose

# Xem failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

### 3. Database Monitoring
```sql
-- Xem số lượng đơn hàng pending
SELECT COUNT(*) FROM orders WHERE driver_id IS NULL AND status_code = 1;

-- Xem driver online
SELECT COUNT(*) FROM drivers WHERE is_online = 1;

-- Xem tracker records
SELECT COUNT(*) FROM trackers WHERE is_online = 1;
```

## Troubleshooting

### 1. Không nhận được thông báo
- Kiểm tra FCM token của driver
- Kiểm tra Firebase configuration
- Kiểm tra queue worker có đang chạy không

### 2. Không tìm thấy đơn hàng gần
- Kiểm tra tọa độ đơn hàng có đúng format không
- Kiểm tra bán kính proximity có phù hợp không
- Kiểm tra đơn hàng có status pending không

### 3. Performance issues
- Tăng interval cho periodic check
- Sử dụng Redis queue thay vì database
- Tối ưu database queries với indexes

### 4. Firebase connection issues
- Kiểm tra service account file
- Kiểm tra Firebase project configuration
- Kiểm tra network connectivity

## Best Practices

### 1. Performance
- Sử dụng Firebase realtime listener thay vì periodic check
- Cấu hình queue worker với multiple processes
- Sử dụng database indexes cho location queries

### 2. Reliability
- Implement retry logic cho failed notifications
- Sử dụng queue để tránh blocking
- Logging chi tiết cho debugging

### 3. Scalability
- Sử dụng Redis cho queue và cache
- Implement rate limiting cho API endpoints
- Monitor memory usage của worker processes

### 4. Security
- Validate tất cả input data
- Sử dụng authentication cho API endpoints
- Encrypt sensitive data

## Deployment

### 1. Production Setup
```bash
# Install dependencies
composer install --optimize-autoloader --no-dev

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start queue worker
php artisan queue:work --daemon

# Start proximity worker
php artisan proximity:worker --firebase --radius=2.0
```

### 2. Supervisor Configuration
```ini
[program:laravel-proximity-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/project/artisan proximity:worker --firebase
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/proximity-worker.log
```

### 3. Monitoring
- Sử dụng Laravel Horizon cho queue monitoring
- Implement health checks cho worker processes
- Set up alerts cho failed jobs và errors 