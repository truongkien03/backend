# Tài Liệu Chi Tiết: Hệ Thống Tính Khoảng Cách

## Mục lục
1. [Tổng quan](#tổng-quan)
2. [Khi User Tạo Đơn Hàng](#khi-user-tạo-đơn-hàng)
3. [Khi Tìm Tài Xế Gần Nhất](#khi-tìm-tài-xế-gần-nhất)
4. [Các Phương Pháp Tính Khoảng Cách](#các-phương-pháp-tính-khoảng-cách)
5. [Fallback Strategy](#fallback-strategy)
6. [Performance & Optimization](#performance--optimization)

---

## Tổng quan

Hệ thống có **2 loại tính khoảng cách chính**:

1. **Khoảng cách đơn hàng**: Từ điểm đón đến điểm giao hàng (dùng OSRM + fallback Haversine)
2. **Khoảng cách tài xế**: Từ vị trí tài xế đến điểm đón hàng (dùng MySQL spatial functions)

---

## Khi User Tạo Đơn Hàng

### 🎯 Mục đích
- Tính khoảng cách từ `from_address` đến `to_address`
- Tính phí giao hàng dựa trên khoảng cách
- Kiểm tra giới hạn khoảng cách (tối đa 100km)

### 📍 Code Implementation

```php
// File: app/Http/Controllers/Api/OrderController.php
public function createOrder(Request $request)
{
    // Parse địa chỉ từ JSON
    $origin = json_decode($request['from_address'], true);
    $destiny = json_decode($request['to_address'], true);
    
    // Tính khoảng cách bằng OSRM (có fallback)
    $distanceInKilometer = $this->getDistanceInKilometer(
        implode(',', array_intersect_key($origin, ['lat' => 0, 'lon' => 0])),
        implode(',', array_intersect_key($destiny, ['lat' => 0, 'lon' => 0]))
    );
    
    // Kiểm tra giới hạn
    if ($distanceInKilometer > 100) {
        return response()->json([
            'error' => true,
            'message' => ['to_address' => ['Hệ thống tạm thời không hỗ trợ đơn hàng xa hơn 100km']]
        ], 422);
    }
    
    // Tính phí giao hàng
    $request['distance'] = $distanceInKilometer;
    $request['shipping_cost'] = $this->calculateShippingFeeAmount($distanceInKilometer);
    
    // Tạo đơn hàng
    $order = Order::create($request->only([
        'user_id', 'from_address', 'to_address', 'items', 
        'shipping_cost', 'distance', 'user_note', 'receiver'
    ]));
    
    // Tự động tìm tài xế
    dispatch(new FindRandomDriverForOrder($order));
}
```

### 🔧 Phương thức `getDistanceInKilometer()`

```php
private function getDistanceInKilometer($fromAddress, $toAddress)
{
    // Convert input "lat,lon" to OSRM format "lon,lat"
    $fromCoords = explode(',', $fromAddress);
    $toCoords = explode(',', $toAddress);
    
    $fromOSRM = $fromCoords[1] . ',' . $fromCoords[0]; // lon,lat
    $toOSRM = $toCoords[1] . ',' . $toCoords[0];       // lon,lat
    
    try {
        // Gọi OSRM API để tính đường đi thực tế
        $baseUrl = config('osm.osrm.base_url', 'http://router.project-osrm.org');
        $timeout = config('osm.osrm.timeout', 10);
        
        $osrmUrl = "{$baseUrl}/route/v1/driving/{$fromOSRM};{$toOSRM}";
        
        $response = json_decode(Http::timeout($timeout)->get($osrmUrl, [
            'overview' => 'false',
            'steps' => 'false'
        ]), true);

        if (isset($response['code']) && $response['code'] === 'Ok' && !empty($response['routes'])) {
            $distanceInMeters = $response['routes'][0]['distance'];
            return $distanceInMeters / 1000; // Convert to kilometers
        }
        
        // Fallback nếu OSRM fail
        return $this->getDistanceInKilometerAsCrowFly($fromAddress, $toAddress);
        
    } catch (\Exception $e) {
        \Log::warning('OSRM API failed: ' . $e->getMessage());
        return $this->getDistanceInKilometerAsCrowFly($fromAddress, $toAddress);
    }
}
```

### 📊 Ví dụ Thực Tế

**Input:**
```json
{
    "from_address": {
        "lat": 10.8231,
        "lon": 106.6297,
        "desc": "123 Nguyễn Huệ, Quận 1, TP.HCM"
    },
    "to_address": {
        "lat": 10.7769,
        "lon": 106.7009,
        "desc": "456 Võ Văn Tần, Quận 3, TP.HCM"
    }
}
```

**Process:**
1. Convert to OSRM format: `106.6297,10.8231;106.7009,10.7769`
2. Call OSRM: `http://router.project-osrm.org/route/v1/driving/106.6297,10.8231;106.7009,10.7769`
3. Response: `{"routes":[{"distance":5200}]}` (5.2km)
4. Calculate shipping fee: 10,000đ + (5.2-1) × 5,000đ = 31,000đ

---

## Khi Tìm Tài Xế Gần Nhất

### 🎯 Mục đích
- Tìm tài xế có vị trí gần nhất với điểm đón hàng (`from_address`)
- Sắp xếp theo khoảng cách và rating
- Chỉ chọn tài xế có status = "free" và profile đã verified

### 📍 Code Implementation

```php
// File: app/Jobs/FindRandomDriverForOrder.php
private function randomDriver()
{
    $place = $this->order->from_address;
    $lat2 = $place['lat'];  // Latitude điểm đón hàng
    $lng2 = $place['lon'];  // Longitude điểm đón hàng

    $driver = Driver::has('profile')
        ->selectRaw(
            "*,
            6371 * acos(
                cos( radians($lat2) )
              * cos( radians( JSON_EXTRACT(current_location, '$.lat') ) )
              * cos( radians( JSON_EXTRACT(current_location, '$.lon') ) - radians($lng2) )
              + sin( radians($lat2) )
              * sin( radians( JSON_EXTRACT(current_location, '$.lat') ) )
            ) as distance"
        )
        ->where('status', config('const.driver.status.free'))
        ->whereNotIn('id', $this->order->except_drivers ?? [])
        ->orderBy('distance')      // Ưu tiên khoảng cách gần nhất
        ->orderBy('review_rate', 'desc') // Thứ hai là rating cao nhất
        ->first();

    return $driver;
}
```

### 🔧 MySQL Haversine Formula Explained

**Công thức Haversine trong MySQL:**
```sql
6371 * acos(
    cos( radians(lat2) )
  * cos( radians( JSON_EXTRACT(current_location, '$.lat') ) )
  * cos( radians( JSON_EXTRACT(current_location, '$.lon') ) - radians(lng2) )
  + sin( radians(lat2) )
  * sin( radians( JSON_EXTRACT(current_location, '$.lat') ) )
) as distance
```

**Giải thích:**
- `6371`: Bán kính Trái Đất tính bằng km
- `lat2`, `lng2`: Tọa độ điểm đón hàng
- `JSON_EXTRACT(current_location, '$.lat')`: Latitude hiện tại của tài xế
- `JSON_EXTRACT(current_location, '$.lon')`: Longitude hiện tại của tài xế
- Kết quả: Khoảng cách đường chim bay tính bằng km

### 📊 Ví dụ Thực Tế

**Tình huống:** Đơn hàng ở Quận 1, có 3 tài xế online:

| Tài xế | Vị trí hiện tại | Khoảng cách | Rating | Thứ tự |
|--------|----------------|-------------|---------|---------|
| Driver A | Quận 1 (1.2km) | 1.2km | 4.5⭐ | **1** (gần nhất) |
| Driver B | Quận 3 (3.5km) | 3.5km | 4.9⭐ | 2 |
| Driver C | Quận 2 (2.1km) | 2.1km | 4.2⭐ | 3 |

**Kết quả:** Driver A được chọn vì gần nhất (1.2km)

**SQL Generated:**
```sql
SELECT *, 
6371 * acos(
    cos(radians(10.8231)) 
  * cos(radians(JSON_EXTRACT(current_location, '$.lat'))) 
  * cos(radians(JSON_EXTRACT(current_location, '$.lon')) - radians(106.6297)) 
  + sin(radians(10.8231)) 
  * sin(radians(JSON_EXTRACT(current_location, '$.lat')))
) as distance
FROM drivers 
WHERE has_profile = 1 
  AND status = 'free'
ORDER BY distance ASC, review_rate DESC
LIMIT 1;
```

---

## Các Phương Pháp Tính Khoảng Cách

### 1. 🛣️ OSRM (Open Source Routing Machine)

**Đặc điểm:**
- Tính khoảng cách đường đi thực tế (theo đường xá)
- Có tính đến traffic, đường cấm, đường một chiều
- Chính xác hơn khoảng cách đường chim bay
- Sử dụng cho: Tính phí giao hàng, ước tính thời gian

**API Endpoint:**
```
GET http://router.project-osrm.org/route/v1/driving/{lon1},{lat1};{lon2},{lat2}
```

**Response:**
```json
{
    "code": "Ok",
    "routes": [
        {
            "distance": 5200,      // meters
            "duration": 900,       // seconds
            "geometry": "..."      // route polyline
        }
    ]
}
```

**Ưu điểm:**
- ✅ Chính xác với đường đi thực tế
- ✅ Miễn phí và open source
- ✅ Có thể tự host server riêng
- ✅ Hỗ trợ nhiều loại phương tiện

**Nhược điểm:**
- ❌ Phụ thuộc vào internet
- ❌ Có thể chậm hoặc không khả dụng
- ❌ Cần fallback strategy

### 2. 🎯 Haversine Formula (Đường chim bay)

**Đặc điểm:**
- Tính khoảng cách thẳng giữa 2 điểm trên mặt cầu
- Không tính đến địa hình, đường xá
- Nhanh và không phụ thuộc internet
- Sử dụng cho: Fallback, tìm tài xế gần nhất

**Công thức toán học:**
```
a = sin²(Δφ/2) + cos φ1 ⋅ cos φ2 ⋅ sin²(Δλ/2)
c = 2 ⋅ atan2( √a, √(1−a) )
d = R ⋅ c
```

Trong đó:
- φ = latitude (radian)
- λ = longitude (radian)  
- R = bán kính Trái Đất (6371 km)
- d = khoảng cách

**Code Implementation:**
```php
private function getDistanceInKilometerAsCrowFly($fromAddress, $toAddress)
{
    $fromAddress = explode(',', $fromAddress);
    $toAddress = explode(',', $toAddress);

    $latitude1 = $fromAddress[0];
    $longitude1 = $fromAddress[1];
    $latitude2 = $toAddress[0];
    $longitude2 = $toAddress[1];

    $theta = $longitude1 - $longitude2;
    $miles = (sin(deg2rad($latitude1)) * sin(deg2rad($latitude2))) + 
             (cos(deg2rad($latitude1)) * cos(deg2rad($latitude2)) * cos(deg2rad($theta)));
    $miles = acos($miles);
    $miles = rad2deg($miles);
    $miles = $miles * 60 * 1.1515;
    $kilometers = $miles * 1.609344;

    return $kilometers;
}
```

**Ưu điểm:**
- ✅ Nhanh và luôn khả dụng
- ✅ Không cần internet
- ✅ Tính toán đơn giản
- ✅ Phù hợp cho tìm kiếm gần đúng

**Nhược điểm:**
- ❌ Không chính xác với đường đi thực tế
- ❌ Không tính địa hình, sông, núi
- ❌ Sai số có thể lớn ở khoảng cách xa

### 3. 📊 So sánh kết quả

**Ví dụ: Từ Quận 1 đến Quận 3, TP.HCM**

| Phương pháp | Khoảng cách | Use case |
|-------------|-------------|----------|
| OSRM (thực tế) | 5.2 km | Tính phí, navigation |
| Haversine (chim bay) | 4.1 km | Tìm tài xế, search |
| Sai số | ~20-30% | Chấp nhận được |

---

## Fallback Strategy

### 🔄 Luồng Xử Lý Lỗi

```
1. Gọi OSRM API
   ↓
2. Check response success?
   ↓ NO
3. Log warning + Use Haversine
   ↓ YES  
4. Extract distance from OSRM
   ↓
5. Return accurate distance
```

### 🛡️ Error Handling

```php
try {
    // Primary: OSRM API call
    $response = Http::timeout(10)->get($osrmUrl);
    
    if ($response->successful() && $response['code'] === 'Ok') {
        return $response['routes'][0]['distance'] / 1000;
    }
    
    // Fallback 1: OSRM có response nhưng không thành công
    Log::warning("OSRM returned error: " . $response['code']);
    return $this->getDistanceInKilometerAsCrowFly($fromAddress, $toAddress);
    
} catch (\Exception $e) {
    // Fallback 2: Network error, timeout, etc.
    Log::warning("OSRM API failed: " . $e->getMessage());
    return $this->getDistanceInKilometerAsCrowFly($fromAddress, $toAddress);
}
```

### 📈 Monitoring & Alerting

**Metrics cần theo dõi:**
- OSRM success rate
- OSRM response time
- Fallback usage percentage
- Distance accuracy comparison

**Config để tuning:**
```php
// config/osm.php
return [
    'osrm' => [
        'base_url' => env('OSRM_BASE_URL', 'http://router.project-osrm.org'),
        'timeout' => env('OSRM_TIMEOUT', 10),
        'retry_attempts' => env('OSRM_RETRY', 2),
        'fallback_enabled' => env('OSRM_FALLBACK', true),
    ]
];
```

---

## Performance & Optimization

### 🚀 Tối Ưu Tìm Tài Xế

**1. Spatial Index trên current_location:**
```sql
-- Tạo virtual column để index GPS
ALTER TABLE drivers 
ADD COLUMN lat_generated DECIMAL(10,8) AS (JSON_EXTRACT(current_location, '$.lat')) STORED,
ADD COLUMN lon_generated DECIMAL(11,8) AS (JSON_EXTRACT(current_location, '$.lon')) STORED;

-- Tạo spatial index
CREATE SPATIAL INDEX idx_driver_location ON drivers((POINT(lon_generated, lat_generated)));
```

**2. Bounding Box Pre-filter:**
```php
// Thay vì tính khoảng cách cho tất cả drivers, filter trước theo hình vuông
$radiusKm = 10; // 10km radius
$latDelta = $radiusKm / 111; // ~1 degree latitude = 111km
$lonDelta = $radiusKm / (111 * cos(deg2rad($lat)));

$driver = Driver::has('profile')
    ->whereRaw("JSON_EXTRACT(current_location, '$.lat') BETWEEN ? AND ?", 
               [$lat2 - $latDelta, $lat2 + $latDelta])
    ->whereRaw("JSON_EXTRACT(current_location, '$.lon') BETWEEN ? AND ?", 
               [$lng2 - $lonDelta, $lng2 + $lonDelta])
    ->selectRaw("*, 6371 * acos(...) as distance")
    ->where('status', config('const.driver.status.free'))
    ->orderBy('distance')
    ->first();
```

### 💾 Caching Strategy

**1. Cache shipping fee calculation:**
```php
// Cache key based on coordinates (rounded)
$cacheKey = sprintf("shipping_fee_%s_%s", 
    round($fromLat, 3) . ',' . round($fromLon, 3),
    round($toLat, 3) . ',' . round($toLon, 3)
);

$shippingFee = Cache::remember($cacheKey, 300, function() use ($fromAddress, $toAddress) {
    return $this->calculateShippingFeeAmount($this->getDistanceInKilometer($fromAddress, $toAddress));
});
```

**2. Cache active drivers:**
```php
// Cache danh sách drivers online trong 30s
$onlineDrivers = Cache::remember('drivers_online', 30, function() {
    return Driver::has('profile')
        ->where('status', config('const.driver.status.free'))
        ->select('id', 'current_location', 'review_rate')
        ->get();
});
```

### 📊 Database Optimization

**1. Indexes cho performance:**
```sql
-- Index cho order queries
CREATE INDEX idx_orders_user_status ON orders(user_id, status_code);
CREATE INDEX idx_orders_distance ON orders(distance);

-- Index cho driver queries  
CREATE INDEX idx_drivers_status_rating ON drivers(status, review_rate);
CREATE INDEX idx_drivers_profile ON drivers(id) WHERE EXISTS(SELECT 1 FROM driver_profiles WHERE driver_id = drivers.id);
```

**2. Query optimization:**
```php
// Eager load relationships để tránh N+1
$orders = Order::with(['customer', 'driver.profile'])
    ->where('user_id', auth()->id())
    ->where('status_code', config('const.order.status.inprocess'))
    ->get();
```

### ⚡ Real-time Updates

**1. WebSocket cho vị trí tài xế:**
```javascript
// Frontend: Subscribe to driver location updates
Echo.channel(`order.${orderId}`)
    .listen('DriverLocationUpdated', (e) => {
        updateDriverMarkerOnMap(e.location);
        updateEstimatedArrival(e.estimated_time);
    });
```

**2. Background job cho location update:**
```php
// Dispatch job mỗi 30s để update vị trí tài xế
dispatch(new UpdateDriverLocationJob($driver))->delay(30);
```

---

## Best Practices & Tips

### 🎯 Recommendations

**1. Distance Calculation:**
- ✅ Luôn có fallback cho OSRM
- ✅ Log metrics để monitor OSRM health
- ✅ Cache kết quả tính toán để giảm API calls
- ✅ Validate GPS coordinates trước khi tính toán

**2. Driver Selection:**
- ✅ Ưu tiên khoảng cách trước, rating sau
- ✅ Exclude drivers đã decline đơn này
- ✅ Set timeout cho việc tìm driver (5 phút)
- ✅ Có mechanism retry với drivers xa hơn

**3. Performance:**
- ✅ Sử dụng spatial indexes cho GPS data
- ✅ Pre-filter drivers trong bounding box
- ✅ Cache danh sách drivers online
- ✅ Async processing cho heavy calculations

**4. Error Handling:**
- ✅ Graceful degradation khi OSRM fail
- ✅ User-friendly error messages
- ✅ Retry mechanism với exponential backoff
- ✅ Alert monitoring team khi fallback rate cao

### 🔧 Configuration Tips

```php
// .env settings
OSRM_BASE_URL=http://router.project-osrm.org
OSRM_TIMEOUT=10
OSRM_FALLBACK=true
DRIVER_SEARCH_RADIUS=50
MAX_ORDER_DISTANCE=100
SHIPPING_BASE_FEE=10000
SHIPPING_PER_KM=5000
```

### 🧪 Testing

**Unit Tests:**
```php
public function testDistanceCalculation()
{
    // Test OSRM happy path
    // Test OSRM fallback
    // Test Haversine accuracy
    // Test edge cases (same location, very far, invalid coordinates)
}

public function testDriverSelection()
{
    // Test nearest driver selection
    // Test rating priority when same distance
    // Test exclude declined drivers
    // Test no available drivers
}
```

---

Tài liệu này cung cấp cái nhìn toàn diện về hệ thống tính khoảng cách trong ứng dụng giao hàng. Hệ thống được thiết kế với tính sẵn sàng cao, performance tốt và có khả năng xử lý lỗi graceful.
