# 📋 API Documentation: POST /shipping-fee (Updated)

## 🎯 Tổng Quan
API tính phí giao hàng dựa trên điểm đi và điểm đến. **Đã cập nhật từ GET sang POST** để gửi thông tin địa chỉ chi tiết hơn.

---

## 📍 Endpoint Information

**Method:** `POST`  
**URL:** `/api/shipping-fee`  
**Authentication:** Bearer Token (Required)  
**Content-Type:** `application/json`
**Middleware:** `auth:api`

---

## 📥 Request Body

### JSON Body Structure
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

### Request Example
```http
POST /api/shipping-fee
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...
Content-Type: application/json

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

### Validation Rules
```php
[
    'from_address' => 'required|json',
    'from_address.lat' => 'required|numeric|between:-90,90',
    'from_address.lon' => 'required|numeric|between:-180,180', 
    'from_address.desc' => 'required|string|max:255',
    'to_address' => 'required|json',
    'to_address.lat' => 'required|numeric|between:-90,90',
    'to_address.lon' => 'required|numeric|between:-180,180',
    'to_address.desc' => 'required|string|max:255',
]
```

---

## 📤 Response Format

### Success Response (200)
```json
{
    "data": {
        "distance": 5.2,
        "shipping_fee": 25000,
        "estimated_time": "15-30 phút",
        "from_address": {
            "lat": 10.8231,
            "lon": 106.6297,
            "desc": "123 Nguyễn Huệ, Quận 1, TP.HCM"
        },
        "to_address": {
            "lat": 10.7769,
            "lon": 106.7009,
            "desc": "456 Võ Văn Tần, Quận 3, TP.HCM"
        },
        "calculated_at": "2024-01-01T10:30:00.000000Z"
    }
}
```

### Error Response (422)
```json
{
    "error": true,
    "message": {
        "from_address.lat": [
            "The from address.lat field is required."
        ],
        "to_address.lon": [
            "The to address.lon must be between -180 and 180."
        ]
    }
}
```

### Distance Limit Error (422)
```json
{
    "error": true,
    "message": {
        "distance": [
            "Hệ thống tạm thời không hỗ trợ đơn hàng xa hơn 100km"
        ]
    }
}
```

---

## 🧮 Business Logic

### 📐 Distance Calculation
1. **Primary Method: OSRM API**
   - Sử dụng OpenStreetMap Routing Machine (OSRM)
   - Tính khoảng cách đường đi thực tế
   - Base URL: `http://router.project-osrm.org` (configurable)

2. **Fallback Method: Haversine Formula**
   - Khi OSRM API không khả dụng
   - Tính khoảng cách đường chim bay

### 💰 Shipping Fee Calculation
```php
$shippingFee = first_km_rate; // 10,000 VNĐ

if (distance > 1km) {
    $shippingFee += from_2nd_km_rate * (distance - 1); // 5,000 VNĐ/km
}

// Peak hour surcharge (+20%)
if (current_hour in [11,12,13,17,18,19]) {
    $shippingFee += 0.2 * $shippingFee;
}
```

### ⏱️ Estimated Time Calculation
```php
$averageSpeed = 30; // km/h in city
$timeInMinutes = ($distance / $averageSpeed) * 60 + 10; // +10 minutes buffer

// Return time ranges: "10-15 phút", "15-30 phút", etc.
```

---

## 💡 Examples

### Example 1: Short Distance (Normal Hours)
**Request:**
```json
{
    "from_address": {
        "lat": 10.8231,
        "lon": 106.6297,
        "desc": "Quận 1, TP.HCM"
    },
    "to_address": {
        "lat": 10.8250,
        "lon": 106.6300,
        "desc": "Gần Quận 1, TP.HCM"
    }
}
```

**Response:**
```json
{
    "data": {
        "distance": 0.5,
        "shipping_fee": 10000,
        "estimated_time": "10-15 phút",
        "from_address": {...},
        "to_address": {...},
        "calculated_at": "2024-01-01T10:30:00.000000Z"
    }
}
```

### Example 2: Medium Distance (Peak Hours)
**Request:**
```json
{
    "from_address": {
        "lat": 10.8231,
        "lon": 106.6297,
        "desc": "123 Nguyễn Huệ, Quận 1"
    },
    "to_address": {
        "lat": 10.7769,
        "lon": 106.7009,
        "desc": "456 Võ Văn Tần, Quận 3"
    }
}
```

**Distance:** 5.2 km  
**Time:** 12:00 (peak hour)  
**Calculation:** 10,000 + (5,000 × 4.2) + 20% = 37,200 VNĐ

**Response:**
```json
{
    "data": {
        "distance": 5.2,
        "shipping_fee": 37200,
        "estimated_time": "15-30 phút",
        "from_address": {...},
        "to_address": {...},
        "calculated_at": "2024-01-01T12:30:00.000000Z"
    }
}
```

---

## 🧪 Testing Guide

### 1. Postman Testing

**Environment Setup:**
```
API_BASE_URL = http://localhost:8000/api
ACCESS_TOKEN = {{your_token_here}}
```

**Test Cases:**

1. **Valid Request:**
```
POST {{API_BASE_URL}}/shipping-fee
Authorization: Bearer {{ACCESS_TOKEN}}
Content-Type: application/json

{
    "from_address": {
        "lat": 10.8231,
        "lon": 106.6297,
        "desc": "Quận 1, TP.HCM"
    },
    "to_address": {
        "lat": 10.7769,
        "lon": 106.7009,
        "desc": "Quận 3, TP.HCM"
    }
}
```

2. **Missing Required Fields:**
```
POST {{API_BASE_URL}}/shipping-fee
Authorization: Bearer {{ACCESS_TOKEN}}
Content-Type: application/json

{
    "from_address": {
        "lat": 10.8231
        // Missing lon and desc
    }
}
```

3. **Invalid Coordinates:**
```
POST {{API_BASE_URL}}/shipping-fee
Authorization: Bearer {{ACCESS_TOKEN}}
Content-Type: application/json

{
    "from_address": {
        "lat": 200, // Invalid latitude
        "lon": 106.6297,
        "desc": "Invalid location"
    },
    "to_address": {
        "lat": 10.7769,
        "lon": 106.7009,
        "desc": "Valid location"
    }
}
```

### 2. Flutter Implementation

```dart
class ShippingService {
  static const String baseUrl = 'http://your-api.com/api';
  
  static Future<ShippingFeeResponse> calculateShippingFee({
    required AddressModel fromAddress,
    required AddressModel toAddress,
  }) async {
    
    final requestBody = {
      'from_address': fromAddress.toJson(),
      'to_address': toAddress.toJson(),
    };
    
    final response = await http.post(
      Uri.parse('$baseUrl/shipping-fee'),
      headers: {
        'Authorization': 'Bearer ${await getAccessToken()}',
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: json.encode(requestBody),
    );
    
    if (response.statusCode == 200) {
      final data = json.decode(response.body);
      return ShippingFeeResponse.fromJson(data['data']);
    } else if (response.statusCode == 422) {
      final error = json.decode(response.body);
      throw ValidationException(error['message']);
    } else {
      throw Exception('Failed to calculate shipping fee');
    }
  }
}

// Usage Example
class OrderCreationScreen extends StatefulWidget {
  AddressModel? fromAddress;
  AddressModel? toAddress;
  ShippingFeeResponse? shippingFee;
  
  void onLocationSelected() async {
    if (fromAddress != null && toAddress != null) {
      try {
        setState(() {
          isCalculating = true;
        });
        
        shippingFee = await ShippingService.calculateShippingFee(
          fromAddress: fromAddress!,
          toAddress: toAddress!,
        );
        
        setState(() {
          isCalculating = false;
        });
        
      } catch (e) {
        setState(() {
          isCalculating = false;
        });
        showErrorDialog(e.toString());
      }
    }
  }
}

// Models
class AddressModel {
  final double lat;
  final double lon;
  final String desc;
  
  AddressModel({
    required this.lat,
    required this.lon,
    required this.desc,
  });
  
  Map<String, dynamic> toJson() => {
    'lat': lat,
    'lon': lon,
    'desc': desc,
  };
}

class ShippingFeeResponse {
  final double distance;
  final int shippingFee;
  final String estimatedTime;
  final AddressModel fromAddress;
  final AddressModel toAddress;
  final DateTime calculatedAt;
  
  ShippingFeeResponse({
    required this.distance,
    required this.shippingFee,
    required this.estimatedTime,
    required this.fromAddress,
    required this.toAddress,
    required this.calculatedAt,
  });
  
  factory ShippingFeeResponse.fromJson(Map<String, dynamic> json) => 
    ShippingFeeResponse(
      distance: json['distance'].toDouble(),
      shippingFee: json['shipping_fee'],
      estimatedTime: json['estimated_time'],
      fromAddress: AddressModel(
        lat: json['from_address']['lat'].toDouble(),
        lon: json['from_address']['lon'].toDouble(),
        desc: json['from_address']['desc'],
      ),
      toAddress: AddressModel(
        lat: json['to_address']['lat'].toDouble(),
        lon: json['to_address']['lon'].toDouble(),
        desc: json['to_address']['desc'],
      ),
      calculatedAt: DateTime.parse(json['calculated_at']),
    );
}
```

---

## ✅ **Lợi Ích Của Việc Đổi Sang POST:**

### 🔒 **Security & Validation:**
- Dữ liệu địa chỉ không hiển thị trong URL
- Validation chi tiết cho từng field
- Support JSON structure phức tạp

### 📱 **Mobile Development:**
- Dễ dàng gửi object Address từ mobile
- Không cần encode/decode query parameters
- Consistent với API tạo đơn hàng

### 🛠️ **Maintainability:**
- Code rõ ràng và dễ đọc hơn
- Validation rules chi tiết
- Response format thống nhất

---

## 🔗 Related APIs

- `POST /orders` - Create order (uses same address structure)
- `GET /route` - Get detailed route with geometry
- `GET /orders/{id}` - Order details

---

**API đã được cập nhật và sẵn sàng sử dụng với method POST! 🚀**
