# Driver Orders API Documentation

## Tổng quan
Tài liệu này mô tả các API để shipper (driver) quản lý đơn hàng của họ.

## Authentication
Tất cả API đều yêu cầu authentication bằng Bearer Token:
```
Authorization: Bearer {driver_token}
```

## Danh sách API

### 1. Lấy danh sách đơn hàng của driver
**GET** `/api/driver/orders/my-orders`

Lấy tất cả đơn hàng mà driver đã nhận (có `driver_id` = driver hiện tại).

#### Parameters:
- `status` (optional): Trạng thái đơn hàng
  - `1`: Pending (chờ xử lý)
  - `2`: Inprocess (đang xử lý) 
  - `3`: Completed (đã hoàn thành)
  - `4`: Cancelled by driver (bị hủy bởi driver)
- `page` (optional): Số trang (default: 1)
- `per_page` (optional): Số đơn hàng mỗi trang (default: 15, max: 100)

#### Response:
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "user_id": 1,
        "driver_id": 5,
        "from_address": {
          "lat": 10.762622,
          "lon": 106.660172,
          "desc": "123 Đường ABC, Quận 1, TP.HCM"
        },
        "to_address": {
          "lat": 10.772622,
          "lon": 106.670172,
          "desc": "456 Đường XYZ, Quận 2, TP.HCM"
        },
        "items": [
          {
            "name": "Sản phẩm 1",
            "quantity": 2,
            "price": 100000,
            "note": "Ghi chú"
          }
        ],
        "shipping_cost": 25000,
        "distance": 5.2,
        "status_code": 2,
        "driver_accept_at": "2024-01-15T10:30:00Z",
        "created_at": "2024-01-15T10:00:00Z",
        "customer": {
          "id": 1,
          "name": "Nguyễn Văn A",
          "phone": "0123456789"
        }
      }
    ],
    "total": 10,
    "per_page": 15
  }
}
```

### 2. Lấy đơn hàng đang xử lý
**GET** `/api/driver/orders/inprocess`

Lấy danh sách đơn hàng đang xử lý của driver (status_code = 2).

#### Parameters:
- `page` (optional): Số trang (default: 1)
- `per_page` (optional): Số đơn hàng mỗi trang (default: 15, max: 100)

#### Response:
Tương tự như API `my-orders` nhưng chỉ trả về đơn hàng có `status_code = 2`.

### 3. Lấy đơn hàng đã hoàn thành
**GET** `/api/driver/orders/completed`

Lấy danh sách đơn hàng đã hoàn thành của driver (status_code = 3).

#### Parameters:
- `page` (optional): Số trang (default: 1)
- `per_page` (optional): Số đơn hàng mỗi trang (default: 15, max: 100)

#### Response:
Tương tự như API `my-orders` nhưng chỉ trả về đơn hàng có `status_code = 3`.

### 4. Lấy đơn hàng có sẵn để nhận
**GET** `/api/driver/orders/available`

Lấy danh sách đơn hàng chưa có driver nhận và chưa bị driver từ chối.

#### Parameters:
- `latitude` (optional): Vĩ độ hiện tại của driver
- `longitude` (optional): Kinh độ hiện tại của driver
- `radius` (optional): Bán kính tìm kiếm (km, default: 5.0)
- `page` (optional): Số trang (default: 1)
- `per_page` (optional): Số đơn hàng mỗi trang (default: 15, max: 100)

#### Response:
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 2,
        "user_id": 2,
        "driver_id": null,
        "from_address": {
          "lat": 10.762622,
          "lon": 106.660172,
          "desc": "123 Đường ABC, Quận 1, TP.HCM"
        },
        "to_address": {
          "lat": 10.772622,
          "lon": 106.670172,
          "desc": "456 Đường XYZ, Quận 2, TP.HCM"
        },
        "items": [
          {
            "name": "Sản phẩm 2",
            "quantity": 1,
            "price": 150000,
            "note": "Ghi chú"
          }
        ],
        "shipping_cost": 30000,
        "distance": 5.2,
        "status_code": 1,
        "created_at": "2024-01-15T11:00:00Z",
        "customer": {
          "id": 2,
          "name": "Trần Thị B",
          "phone": "0987654321"
        },
        "distance": 2.5
      }
    ],
    "total": 5,
    "per_page": 15
  }
}
```

### 5. Thống kê đơn hàng (API hiện có)
**GET** `/api/driver/orders/summary`

Lấy thống kê đơn hàng theo khoảng thời gian.

#### Parameters:
- `from` (required): Ngày bắt đầu (format: Y-m-d)
- `to` (required): Ngày kết thúc (format: Y-m-d)
- `status` (optional): Trạng thái đơn hàng

#### Response:
```json
{
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "driver_id": 5,
      "shipping_cost": 25000,
      "status_code": 3,
      "created_at": "2024-01-15T10:00:00Z"
    }
  ]
}
```

### 6. Chi tiết đơn hàng (API hiện có)
**GET** `/api/driver/orders/{order_id}`

Lấy chi tiết một đơn hàng cụ thể.

#### Response:
```json
{
  "id": 1,
  "user_id": 1,
  "driver_id": 5,
  "from_address": {...},
  "to_address": {...},
  "items": [...],
  "shipping_cost": 25000,
  "distance": 5.2,
  "status_code": 2,
  "customer": {...}
}
```

### 7. Cập nhật trạng thái đã tới địa điểm giao hàng (API mới)
**POST** `/api/driver/orders/{order_id}/arrived`

Cập nhật trạng thái đơn hàng thành 3 (đã tới địa điểm giao) và thêm dữ liệu vào bảng tracker.

#### Parameters:
- `order_id` (required): ID của đơn hàng (path parameter)

#### Request Body (optional):
```json
{
  "note": "Ghi chú của driver (tùy chọn)",
  "description": {
    "additional_info": "Thông tin bổ sung (tùy chọn)"
  }
}
```

#### Response:
```json
{
  "success": true,
  "message": "Đã cập nhật trạng thái đã tới địa điểm giao hàng",
  "data": {
    "id": 1,
    "user_id": 1,
    "driver_id": 5,
    "status_code": 3,
    "completed_at": "2024-01-15T11:30:00Z",
    "from_address": {...},
    "to_address": {...},
    "items": [...],
    "shipping_cost": 25000,
    "distance": 5.2
  }
}
```

#### Điều kiện sử dụng:
- Driver phải là người được giao đơn hàng (`driver_id` = driver hiện tại)
- Đơn hàng phải đang trong trạng thái đang xử lý (`status_code` = 2)

#### Chức năng:
1. Cập nhật `status_code` = 3 (đã tới địa điểm giao)
2. Cập nhật `completed_at` = thời gian hiện tại
3. Thêm record vào bảng `trackers` với:
   - `order_id` = ID đơn hàng
   - `status` = 3
   - `note` = ghi chú từ request (nếu có)
   - `description` = thông tin chi tiết (action, timestamp, driver info, location)
4. Gửi thông báo FCM cho customer

#### Ví dụ sử dụng:
```bash
curl -X POST "https://api.example.com/api/driver/orders/1/arrived" \
  -H "Authorization: Bearer {driver_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "note": "Đã tới địa điểm giao hàng, chờ khách ra nhận",
    "description": {
      "parking_location": "Trước cửa chính",
      "vehicle_info": "Xe màu trắng"
    }
  }'
```

## Trạng thái đơn hàng

| Code | Tên | Mô tả |
|------|-----|-------|
| 1 | Pending | Chờ xử lý (chưa có driver nhận) |
| 2 | Inprocess | Đang xử lý (driver đã nhận và đang giao) |
| 3 | Completed | Đã hoàn thành |
| 4 | Cancelled by driver | Bị hủy bởi driver |

## Lưu ý quan trọng

1. **API `available`**: Chỉ trả về đơn hàng mà driver chưa từ chối (không có trong `except_drivers`)
2. **Tính khoảng cách**: Nếu cung cấp `latitude` và `longitude`, API sẽ tính khoảng cách và sắp xếp theo khoảng cách gần nhất
3. **Phân trang**: Tất cả API đều hỗ trợ phân trang với `page` và `per_page`
4. **Authentication**: Driver phải đăng nhập và có profile đã được verify
5. **Quyền truy cập**: Driver chỉ có thể xem đơn hàng của mình hoặc đơn hàng có sẵn để nhận

## Ví dụ sử dụng

### Lấy đơn hàng đang xử lý:
```bash
curl -X GET "https://api.example.com/api/driver/orders/inprocess" \
  -H "Authorization: Bearer {driver_token}"
```

### Lấy đơn hàng có sẵn gần vị trí hiện tại:
```bash
curl -X GET "https://api.example.com/api/driver/orders/available?latitude=10.762622&longitude=106.660172&radius=3.0" \
  -H "Authorization: Bearer {driver_token}"
```

### Lấy tất cả đơn hàng đã hoàn thành:
```bash
curl -X GET "https://api.example.com/api/driver/orders/completed?page=1&per_page=20" \
  -H "Authorization: Bearer {driver_token}"
``` 