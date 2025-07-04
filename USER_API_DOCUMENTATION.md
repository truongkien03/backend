# API Documentation - App Người Dùng (User App)

## Mục lục
1. [Authentication APIs](#authentication-apis)
2. [Profile Management APIs](#profile-management-apis)
3. [Order Management APIs](#order-management-apis)
4. [FCM Notification APIs](#fcm-notification-apis)
5. [Location & Route APIs](#location--route-apis)
6. [Common Response Format](#common-response-format)
7. [Error Codes](#error-codes)
8. [Testing Guide](#testing-guide)

---

## Authentication APIs

### 1. Đăng ký tài khoản
**POST** `/api/register`

**Mô tả:** Đăng ký tài khoản mới bằng số điện thoại và OTP

**Request Body:**
```json
{
    "phone_number": "+84987654321",
    "otp": "1234",
    "name": "Nguyễn Văn A"
}
```

**Response Success (201):**
```json
{
    "data": {
        "id": 1,
        "name": "Nguyễn Văn A",
        "phone_number": "+84987654321",
        "address": null,
        "avatar": null,
        "email": null,
        "created_at": "2024-01-01T00:00:00.000000Z",
        "updated_at": "2024-01-01T00:00:00.000000Z"
    },
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
    "token_type": "Bearer",
    "expires_at": "2024-12-31T23:59:59.000000Z"
}
```

**Use Case:**
- User mở app lần đầu
- Chọn "Đăng ký tài khoản mới"
- Nhập số điện thoại → gọi API gửi OTP
- Nhập OTP và tên → gọi API này
- Lưu access_token để gọi các API khác

**Lỗi thường gặp:**
- 422: OTP không đúng, số điện thoại đã tồn tại
- 400: Thiếu thông tin bắt buộc

### 2. Gửi OTP đăng ký
**POST** `/api/register/otp`

**Request Body:**
```json
{
    "phone_number": "+84987654321"
}
```

**Response Success (204):** Không có body, chỉ status code

**Use Case:**
- User nhập số điện thoại mới để đăng ký
- App gọi API này để gửi OTP
- User nhận SMS OTP và nhập vào app

### 3. Đăng nhập bằng OTP
**POST** `/api/login`

**Request Body:**
```json
{
    "phone_number": "+84987654321",
    "otp": "1234"
}
```

**Response:** Giống như API đăng ký

**Use Case:**
- User đã có tài khoản nhưng quên mật khẩu
- User muốn đăng nhập nhanh bằng OTP
- User chuyển thiết bị mới

### 4. Gửi OTP đăng nhập
**POST** `/api/login/otp`

**Request Body:**
```json
{
    "phone_number": "+84987654321"
}
```

**Response Success (204):** Không có body

### 5. Đăng nhập bằng mật khẩu
**POST** `/api/login/password`

**Request Body:**
```json
{
    "phone_number": "+84987654321",
    "password": "123456"
}
```

**Response:** Giống như API đăng ký

**Use Case:**
- User đã có mật khẩu và muốn đăng nhập nhanh
- Không cần gửi OTP qua SMS

### 6. Quên mật khẩu
**POST** `/api/password/forgot`

**Request Body:**
```json
{
    "phone_number": "+84987654321"
}
```

**Response Success (204):** Gửi OTP qua SMS

### 7. Reset mật khẩu
**POST** `/api/password/reset`

**Request Body:**
```json
{
    "phone_number": "+84987654321",
    "otp": "1234",
    "password": "newpassword123",
    "password_confirmation": "newpassword123"
}
```

**Response Success (204):** Không có body

**Use Case:**
1. User quên mật khẩu → gọi API forgot
2. Nhận OTP qua SMS
3. Nhập OTP + mật khẩu mới → gọi API reset
4. Đăng nhập lại bằng mật khẩu mới

---

## Profile Management APIs

### 1. Lấy thông tin profile
**GET** `/api/profile`
**Headers:** `Authorization: Bearer {access_token}`

**Response Success (200):**
```json
{
    "id": 1,
    "name": "Nguyễn Văn A",
    "phone_number": "+84987654321",
    "email": "user@example.com",
    "address": {
        "lat": 10.8231,
        "lon": 106.6297,
        "desc": "123 Nguyễn Huệ, Quận 1, TP.HCM"
    },
    "avatar": "https://firebasestorage.googleapis.com/v0/b/project/o/avatars%2Fuser_1.jpg",
    "created_at": "2024-01-01T00:00:00.000000Z",
    "updated_at": "2024-01-01T00:00:00.000000Z"
}
```

**Use Case:**
- Hiển thị thông tin trong màn hình Profile
- Load thông tin để pre-fill form chỉnh sửa
- Hiển thị avatar, tên trong header app

### 2. Cập nhật profile
**POST** `/api/profile`
**Headers:** `Authorization: Bearer {access_token}`

**Request Body:**
```json
{
    "name": "Nguyễn Văn B",
    "email": "user@example.com",
    "address": {
        "lat": 10.8231,
        "lon": 106.6297,
        "desc": "456 Lê Lợi, Quận 1, TP.HCM"
    }
}
```

**Response Success (200):**
```json
{
    "data": {
        "id": 1,
        "name": "Nguyễn Văn B",
        "phone_number": "+84987654321",
        "email": "user@example.com",
        "address": {
            "lat": 10.8231,
            "lon": 106.6297,
            "desc": "456 Lê Lợi, Quận 1, TP.HCM"
        },
        "avatar": "https://firebasestorage.googleapis.com/...",
        "created_at": "2024-01-01T00:00:00.000000Z",
        "updated_at": "2024-01-01T00:00:00.000000Z"
    }
}
```

**Validation:**
- `name`: tùy chọn, tối đa 255 ký tự
- `email`: tùy chọn, định dạng email hợp lệ  
- `address`: bắt buộc
  - `lat`: bắt buộc, số thực
  - `lon`: bắt buộc, số thực  
  - `desc`: bắt buộc, chuỗi mô tả địa chỉ

**Use Case:**
- User chỉnh sửa thông tin cá nhân
- User thay đổi địa chỉ mặc định
- User cập nhật email để nhận thông báo

### 3. Đổi avatar
**POST** `/api/profile/avatar`
**Headers:** 
- `Authorization: Bearer {access_token}`
- `Content-Type: multipart/form-data`

**Request Body:**
```
avatar: [file] (image, max 2MB)
```

**Response Success (200):**
```json
{
    "data": {
        "avatar": "https://firebasestorage.googleapis.com/v0/b/project/o/avatars%2Fuser_1_1640995200.jpg"
    }
}
```

**Use Case:**
- User chọn ảnh từ thư viện hoặc chụp mới
- Upload ảnh lên Firebase Storage
- Cập nhật URL avatar trong database
- Hiển thị avatar mới trong app

### 4. Đổi mật khẩu
**POST** `/api/password`
**Headers:** `Authorization: Bearer {access_token}`

**Request Body:**
```json
{
    "password": "newpassword123",
    "password_confirmation": "newpassword123"
}
```

**Response Success (204):** Không có body

**Validation:**
- `password`: bắt buộc, tối thiểu 6 ký tự, phải có confirmation

**Use Case:**
- User muốn thay đổi mật khẩu hiện tại
- User đã đăng nhập và nhớ mật khẩu cũ

### 5. Đặt mật khẩu lần đầu
**POST** `/api/set-password`
**Headers:** `Authorization: Bearer {access_token}`

**Request Body:**
```json
{
    "password": "newpassword123",
    "password_confirmation": "newpassword123"
}
```

**Response Success (204):** Không có body

**Use Case:**
- User đăng ký bằng OTP lần đầu (chưa có mật khẩu)
- App gợi ý user tạo mật khẩu để đăng nhập nhanh lần sau
- Chỉ được gọi khi user chưa có mật khẩu

---

## Order Management APIs

### 1. Tạo đơn hàng
**POST** `/api/orders`
**Headers:** `Authorization: Bearer {access_token}`

**Request Body:**
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
    },
    "items": [
        {
            "name": "Combo cơm gà",
            "quantity": 2,
            "price": 45000,
            "note": "Không cay"
        },
        {
            "name": "Trà sữa trân châu",
            "quantity": 1,
            "price": 25000,
            "note": "Ít đá"
        }
    ],
    "receiver": {
        "name": "Nguyễn Thị B",
        "phone": "+84901234567",
        "note": "Gọi điện khi đến tầng 1"
    },
    "user_note": "Gọi điện trước khi đến",
    "discount": 5000
}
```

**Response Success (201):**
```json
{
    "data": {
        "id": 123,
        "user_id": 1,
        "driver_id": null,
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
        "items": [
            {
                "name": "Combo cơm gà",
                "quantity": 2,
                "price": 45000,
                "note": "Không cay"
            },
            {
                "name": "Trà sữa trân châu",
                "quantity": 1,
                "price": 25000,
                "note": "Ít đá"
            }
        ],
        "receiver": {
            "name": "Nguyễn Thị B",
            "phone": "+84901234567",
            "note": "Gọi điện khi đến tầng 1"
        },
        "user_note": "Gọi điện trước khi đến",
        "shipping_cost": 31000,
        "discount": 5000,
        "distance": 5.2,
        "status_code": 0,
        "created_at": "2024-01-01T10:00:00.000000Z",
        "updated_at": "2024-01-01T10:00:00.000000Z"
    }
}
```

**Validation:**
- `from_address`: bắt buộc, JSON object (lat, lon, desc)
- `to_address`: bắt buộc, JSON object (lat, lon, desc)
- `items`: bắt buộc, JSON array chứa thông tin sản phẩm
  - `name`: bắt buộc, tên sản phẩm
  - `quantity`: bắt buộc, số nguyên > 0
  - `price`: bắt buộc, số nguyên >= 0
  - `note`: tùy chọn, ghi chú cho sản phẩm
- `receiver`: bắt buộc, JSON object chứa thông tin người nhận
  - `name`: bắt buộc, tên người nhận
  - `phone`: bắt buộc, số điện thoại người nhận
  - `note`: tùy chọn, ghi chú cho người nhận
- `user_note`: tùy chọn, ghi chú từ người gửi, tối đa 1000 ký tự
- `discount`: tùy chọn, số tiền giảm giá (numeric)

**Business Logic:**
- Hệ thống tự động tính khoảng cách từ from_address đến to_address
- Tự động tính phí giao hàng dựa trên khoảng cách (không cần gửi trong request)
- Kiểm tra khoảng cách <= 100km (nếu vượt quá sẽ trả lỗi 422)
- Tự động dispatch job FindRandomDriverForOrder tìm tài xế ngẫu nhiên
- Gửi notification FCM cho tài xế trong bán kính gần nhất qua topic 'driver-{id}'
- Trạng thái ban đầu: status_code = 0 (pending)

**Use Case:**
1. User chọn điểm đi và điểm đến trên map
2. User thêm thông tin sản phẩm (tên, số lượng, giá, ghi chú)
3. User nhập thông tin người nhận (tên, số điện thoại, ghi chú)
4. App tính phí giao hàng (gọi API shipping-fee) để preview
5. User nhập ghi chú cho tài xế (nếu có)
6. User xác nhận tạo đơn → gọi API này
7. Backend tự động tính lại khoảng cách và phí giao hàng (10,000đ + 5,000đ/km từ km thứ 2)
8. Hệ thống tự động tìm tài xế và gửi thông báo
9. User chờ tài xế chấp nhận đơn

### 2. Tính phí giao hàng
**POST** `/api/shipping-fee`
**Headers:** `Authorization: Bearer {access_token}`

**Request Body:**
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

**Response Success (200):**
```json
{
    "data": {
        "distance": 5.2,
        "shipping_fee": 31000,
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

**Validation:**
- `from_address`: bắt buộc, JSON object
  - `lat`: bắt buộc, số thực từ -90 đến 90
  - `lon`: bắt buộc, số thực từ -180 đến 180
  - `desc`: bắt buộc, chuỗi mô tả địa chỉ, tối đa 255 ký tự
- `to_address`: bắt buộc, JSON object với cấu trúc giống from_address

**Business Logic:**
- Phí cơ bản: 10,000đ cho km đầu tiên
- Mỗi km tiếp theo: 5,000đ
- Peak hour surcharge: +20% trong khung giờ cao điểm (11h-13h, 17h-19h)
- Sử dụng OSRM để tính khoảng cách thực tế
- Có fallback về tính khoảng cách Haversine khi OSRM không khả dụng

**Use Case:**
- User chọn điểm đi và điểm đến
- App gọi API này để hiển thị phí và thời gian dự kiến trước khi tạo đơn
- User xem trước chi phí (phí cơ bản 10,000đ + 5,000đ/km thêm + peak hour surcharge nếu có)
- User có thể điều chỉnh route để tối ưu phí

### 3. Lấy route đường đi
**GET** `/api/route`
**Headers:** `Authorization: Bearer {access_token}`

**Query Parameters:**
```
from_lat=10.8231&from_lon=106.6297&to_lat=10.7769&to_lon=106.7009
```

**Response Success (200):**
```json
{
    "data": {
        "distance": 5.2,
        "duration": 900,
        "geometry": "gfp`A}zs_Sqw@..."
    }
}
```

**Use Case:**
- App vẽ đường đi trên map từ điểm A đến điểm B
- Hiển thị khoảng cách và thời gian dự kiến
- User xem route trước khi tạo đơn

### 4. Danh sách đơn hàng đang xử lý
**GET** `/api/orders/inproccess`
**Headers:** `Authorization: Bearer {access_token}`

**Response Success (200):**
```json
{
    "data": [
        {
            "id": 123,
            "user_id": 1,
            "driver_id": 5,
            "from_address": {
                "lat": 10.8231,
                "lon": 106.6297,
                "desc": "123 Nguyễn Huệ, Quận 1"
            },
            "to_address": {
                "lat": 10.7769,
                "lon": 106.7009,
                "desc": "456 Võ Văn Tần, Quận 3"
            },
            "items": [
                {
                    "name": "Combo cơm gà",
                    "quantity": 2,
                    "price": 45000,
                    "note": "Không cay"
                }
            ],
            "receiver": {
                "name": "Nguyễn Thị B",
                "phone": "+84901234567",
                "note": "Gọi điện khi đến"
            },
            "shipping_cost": 31000,
            "distance": 5.2,
            "status_code": 2,
            "driver": {
                "id": 5,
                "name": "Nguyễn Văn Tài Xế",
                "phone_number": "+84912345678",
                "vehicle_type": "motorbike",
                "license_plate": "59A1-12345",
                "current_location": {
                    "lat": 10.8200,
                    "lon": 106.6280
                }
            },
            "created_at": "2024-01-01T10:00:00.000000Z",
            "updated_at": "2024-01-01T10:05:00.000000Z"
        }
    ]
}
```

**Business Logic:**
- Chỉ trả về các đơn có status: pending, driver_accepted, in_transit
- Sắp xếp theo thời gian tạo mới nhất
- Kèm thông tin tài xế nếu đã được assign

**Use Case:**
- Hiển thị danh sách đơn hàng đang chờ hoặc đang giao
- User theo dõi trạng thái đơn hàng
- App tự động refresh để cập nhật trạng thái

### 5. Danh sách đơn hàng đã hoàn thành
**GET** `/api/orders/completed`
**Headers:** `Authorization: Bearer {access_token}`

**Response Success (200):**
```json
{
    "data": [
        {
            "id": 122,
            "user_id": 1,
            "driver_id": 3,
            "from_address": {
                "lat": 10.8231,
                "lon": 106.6297,
                "desc": "123 Nguyễn Huệ, Quận 1"
            },
            "to_address": {
                "lat": 10.7769,
                "lon": 106.7009,
                "desc": "456 Võ Văn Tần, Quận 3"
            },
            "items": [
                {
                    "name": "Bánh mì thịt nướng",
                    "quantity": 1,
                    "price": 15000,
                    "note": ""
                }
            ],
            "receiver": {
                "name": "Trần Văn C",
                "phone": "+84912345678",
                "note": ""
            },
            "shipping_cost": 31000,
            "distance": 5.2,
            "status_code": 4,
            "rating": 5,
            "review": "Tài xế thân thiện, giao hàng nhanh",
            "driver": {
                "id": 3,
                "name": "Trần Văn Driver",
                "phone_number": "+84901234567"
            },
            "completed_at": "2024-01-01T09:30:00.000000Z",
            "created_at": "2024-01-01T09:00:00.000000Z"
        }
    ]
}
```

**Use Case:**
- Xem lịch sử các đơn hàng đã giao thành công
- Kiểm tra chi phí và thông tin tài xế
- Reference để tạo đơn hàng tương tự

### 6. Chi tiết đơn hàng
**GET** `/api/orders/{order_id}`
**Headers:** `Authorization: Bearer {access_token}`

**Response Success (200):**
```json
{
    "data": {
        "id": 123,
        "user_id": 1,
        "driver_id": 5,
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
        "items": [
            {
                "name": "Combo cơm gà",
                "quantity": 2,
                "price": 45000,
                "note": "Không cay"
            },
            {
                "name": "Trà sữa trân châu",
                "quantity": 1,
                "price": 25000,
                "note": "Ít đá"
            }
        ],
        "receiver": {
            "name": "Nguyễn Thị B",
            "phone": "+84901234567",
            "note": "Gọi điện khi đến tầng 1"
        },
        "user_note": "Gọi điện trước khi đến",
        "shipping_cost": 31000,
        "distance": 5.2,
        "status_code": 3,
        "driver": {
            "id": 5,
            "name": "Nguyễn Văn Tài Xế",
            "phone_number": "+84912345678",
            "avatar": "https://firebasestorage.googleapis.com/...",
            "vehicle_type": "motorbike",
            "license_plate": "59A1-12345",
            "current_location": {
                "lat": 10.8200,
                "lon": 106.6280
            },
            "rating": 4.8
        },
        "timeline": [
            {
                "status": "pending",
                "time": "2024-01-01T10:00:00.000000Z",
                "description": "Đơn hàng được tạo"
            },
            {
                "status": "driver_accepted",
                "time": "2024-01-01T10:05:00.000000Z",
                "description": "Tài xế đã chấp nhận đơn hàng"
            },
            {
                "status": "in_transit",
                "time": "2024-01-01T10:10:00.000000Z",
                "description": "Tài xế đang trên đường đến"
            }
        ],
        "created_at": "2024-01-01T10:00:00.000000Z",
        "updated_at": "2024-01-01T10:10:00.000000Z"
    }
}
```

**Use Case:**
- Hiển thị màn hình chi tiết đơn hàng
- Theo dõi vị trí tài xế real-time
- Xem timeline trạng thái đơn hàng
- Gọi điện hoặc chat với tài xế

### 7. Lấy danh sách tài xế được đề xuất
**GET** `/api/orders/{order_id}/drivers/recommended`
**Headers:** `Authorization: Bearer {access_token}`

**Response Success (200):**
```json
{
    "data": [
        {
            "id": 5,
            "name": "Nguyễn Văn Tài Xế",
            "phone_number": "+84912345678",
            "avatar": "https://firebasestorage.googleapis.com/...",
            "vehicle_type": "motorbike",
            "license_plate": "59A1-12345",
            "rating": 4.8,
            "total_trips": 150,
            "distance_to_pickup": 2.1,
            "estimated_arrival": "5-7 phút"
        }
    ]
}
```

**Use Case:**
- Hiển thị danh sách tài xế gần nhất và tốt nhất
- User chọn tài xế cụ thể thay vì ngẫu nhiên
- So sánh rating và khoảng cách

### 8. Assign tài xế cụ thể
**POST** `/api/orders/{order_id}/drivers`
**Headers:** `Authorization: Bearer {access_token}`

**Request Body:**
```json
{
    "driver_id": 5
}
```

**Response Success (200):**
```json
{
    "data": {
        "id": 123,
        "status": "waiting_driver_confirmation",
        "driver_id": 5,
        "driver": {
            "id": 5,
            "name": "Nguyễn Văn Tài Xế",
            "phone_number": "+84912345678"
        }
    }
}
```

**Use Case:**
- User chọn tài xế cụ thể từ danh sách đề xuất
- Gửi notification cho tài xế được chọn
- Tài xế có thể accept hoặc decline

### 9. Assign tài xế ngẫu nhiên
**POST** `/api/orders/{order_id}/drivers/random`
**Headers:** `Authorization: Bearer {access_token}`

**Response Success (200):**
```json
{
    "data": {
        "message": "Đang tìm tài xế cho bạn...",
        "estimated_time": "2-5 phút"
    }
}
```

**Use Case:**
- User không muốn chọn tài xế cụ thể
- Hệ thống tự động tìm và assign tài xế tốt nhất
- Dispatch job FindRandomDriverForOrder

### 10. Đánh giá tài xế
**POST** `/api/orders/{order_id}/review`
**Headers:** `Authorization: Bearer {access_token}`

**Request Body:**
```json
{
    "rating": 5,
    "review": "Tài xế thân thiện, giao hàng nhanh chóng và an toàn"
}
```

**Response Success (204):** Không có body

**Validation:**
- `rating`: bắt buộc, số nguyên từ 1-5
- `review`: tùy chọn, tối đa 500 ký tự

**Use Case:**
- Sau khi đơn hàng hoàn thành
- App hiển thị popup đánh giá
- User chọn số sao và viết nhận xét

---

## FCM Notification APIs

### 1. Thêm FCM Token
**POST** `/api/fcm/token`
**Headers:** `Authorization: Bearer {access_token}`

**Request Body:**
```json
{
    "fcm_token": "eA7Z9k2..._FCM_TOKEN_HERE_..."
}
```

**Response Success (204):** Không có body

**Use Case:**
- App khởi động lần đầu
- User cấp quyền nhận notification
- App refresh FCM token (token có thể thay đổi)

### 2. Xóa FCM Token
**DELETE** `/api/fcm/token`
**Headers:** `Authorization: Bearer {access_token}`

**Request Body:**
```json
{
    "fcm_token": "eA7Z9k2..._FCM_TOKEN_HERE_..."
}
```

**Response Success (204):** Không có body

**Use Case:**
- User logout khỏi app
- User tắt notification trong settings
- App bị uninstall

### 3. Lấy danh sách thông báo
**GET** `/api/notifications`
**Headers:** `Authorization: Bearer {access_token}`

**Response Success (200):**
```json
{
    "data": [
        {
            "id": "uuid-notification-1",
            "type": "App\\Notifications\\DriverAcceptedOrder",
            "data": {
                "order_id": 123,
                "message": "Tài xế Nguyễn Văn A đã chấp nhận đơn hàng của bạn",
                "driver_name": "Nguyễn Văn A",
                "driver_phone": "+84912345678"
            },
            "read_at": null,
            "created_at": "2024-01-01T10:05:00.000000Z"
        }
    ],
    "links": {
        "first": "http://api.example.com/notifications?page=1",
        "last": "http://api.example.com/notifications?page=3",
        "prev": null,
        "next": "http://api.example.com/notifications?page=2"
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 3,
        "per_page": 15,
        "to": 15,
        "total": 42
    }
}
```

**Use Case:**
- Hiển thị danh sách thông báo trong app
- User xem lại các notification đã nhận
- Pagination để load nhiều notification

---

## Location & Route APIs

### API Route đã được trình bày ở phần Order Management

---

## Luồng Thông Báo FCM Chi Tiết

### 🔄 Toàn Bộ Luồng Từ Tạo Đơn Đến Nhận Thông Báo

#### **Bước 1: User Tạo Đơn Hàng**
```
POST /api/orders
```

**Điều gì xảy ra trong backend:**

1. **OrderController::createOrder()** nhận request từ app user
2. Validate dữ liệu (địa chỉ, khoảng cách, phí giao hàng)
3. Tạo record trong bảng `orders` với status = "pending"
4. **Tự động dispatch job**: `FindRandomDriverForOrder($order)`

```php
// Trong OrderController::createOrder()
$order = Order::create($request->only([
    'user_id', 'from_address', 'to_address', 
    'items', 'shipping_cost', 'distance', 'user_note', 'receiver'
]));

// 🔥 ĐIỂM QUAN TRỌNG: Tự động tìm tài xế
dispatch(new FindRandomDriverForOrder($order));

return response()->json(['data' => $order]);
```

#### **Bước 2: Job Tìm Tài Xế Được Xử Lý**
```php
// App\Jobs\FindRandomDriverForOrder::handle()
```

**Logic của job:**

1. **Tìm tài xế phù hợp**:
   - Có profile đã được xác minh (`has('profile')`)
   - Status = "free" (sẵn sàng nhận đơn)
   - Trong bán kính gần nhất với điểm đón
   - Sắp xếp theo khoảng cách và rating

```php
$driver = Driver::has('profile')
    ->selectRaw("*, 6371 * acos(...) as distance") // Tính khoảng cách GPS
    ->where('status', config('const.driver.status.free'))
    ->orderBy('distance')
    ->orderBy('review_rate', 'desc')
    ->first();
```

2. **Nếu tìm thấy tài xế**:
   - Gửi notification `WaitForDriverConfirmation` cho tài xế
   
3. **Nếu không tìm thấy tài xế**:
   - Gửi notification `NoAvailableDriver` cho user

#### **Bước 3: Gửi Notification Cho Tài Xế**
```php
// Trong FindRandomDriverForOrder::handle()
$driver->notify(new WaitForDriverConfirmation($order));
```

**Cơ chế notification:**

1. **WaitForDriverConfirmation** class:
   - Channel: `['broadcast', FcmTopic::class]`
   - Target: Topic `driver-{driver_id}`

2. **FcmTopic::send()** method:
   - Lấy topic từ `$driver->routeNotificationForFcm()` → `driver-{id}`
   - Build FCM message với data và notification
   - Dispatch `FcmNotificationJob` để gửi async

3. **FcmNotificationJob::handle()** method:
   - Gọi Firebase Messaging API
   - Gửi notification đến topic `driver-{driver_id}`

#### **Bước 4: Tài Xế Nhận Notification**

**Trong App Tài Xế:**

1. **Firebase SDK** nhận message từ topic `driver-{driver_id}`
2. **Foreground**: Hiển thị popup/dialog với thông tin đơn hàng
3. **Background**: Hiển thị system notification
4. **App closed**: System notification, tap để mở app

```dart
// Trong Flutter Driver App
FirebaseMessaging.onMessage.listen((RemoteMessage message) {
    if (message.data['key'] == 'NewOder') {
        showNewOrderDialog(
            orderId: message.data['oderId'],
            link: message.data['link'] // driver://AwaitAcceptOder
        );
    }
});
```

#### **Bước 5: Tài Xế Phản Hồi**

**Accept đơn:**
```
POST /api/driver/orders/{order_id}/accept
```
- Update order status = "driver_accepted"
- Gửi `DriverAcceptedOrder` notification cho user

**Decline đơn:**
```
POST /api/driver/orders/{order_id}/decline
```
- Update order status = "cancelled_by_driver"
- Tự động dispatch `FindRandomDriverForOrder` tìm tài xế khác
- Gửi `DriverDeclinedOrder` notification cho user

### 🔧 Cấu Hình FCM Topic

#### **Driver App Setup:**
```dart
// Khi driver đăng nhập
String driverId = "123";
await FirebaseMessaging.instance.subscribeToTopic('driver-$driverId');

// Khi driver logout
await FirebaseMessaging.instance.unsubscribeFromTopic('driver-$driverId');
```

#### **User App Setup:**
```dart
// User không cần subscribe topic, nhận notification trực tiếp qua FCM token
String? userFcmToken = await FirebaseMessaging.instance.getToken();
await apiService.addFcmToken(userFcmToken);
```

### 📱 Luồng Notification Chi Tiết

#### **1. User Tạo Đơn → Tài Xế Nhận Thông Báo**

```
User App → Backend → Job Queue → Firebase → Driver App

1. POST /api/orders (tạo đơn)
2. Tạo Order record
3. dispatch FindRandomDriverForOrder
4. Response order created
5. Tìm driver gần nhất + status=free
6. driver.notify(WaitForDriverConfirmation)
7. dispatch FcmNotificationJob
8. Gửi message đến topic 'driver-{id}'
9. Push notification
10. Hiển thị popup đơn hàng mới
```

#### **2. Tài Xế Accept → User Nhận Thông Báo**

```
Driver App → Backend → Firebase → User App

1. POST /orders/{id}/accept
2. Update order status
3. user.notify(DriverAcceptedOrder)
4. Gửi đến user FCM tokens
5. Push notification "Tài xế đã chấp nhận"
6. Navigate to order tracking
```

### 🎯 Các Loại Notification

#### **Cho Tài Xế:**
1. **WaitForDriverConfirmation**: Đơn hàng mới
2. **OrderSharedNotification**: Đồng nghiệp chia sẻ đơn

#### **Cho User:**
1. **DriverAcceptedOrder**: Tài xế chấp nhận đơn
2. **DriverDeclinedOrder**: Tài xế từ chối, đang tìm tài xế khác
3. **NoAvailableDriver**: Không tìm thấy tài xế
4. **OrderHasBeenComplete**: Đơn hàng hoàn thành

### 🔍 Debug & Troubleshooting

#### **Kiểm tra notification có gửi thành công:**

1. **Backend logs:**
```bash
tail -f storage/logs/laravel.log | grep FCM
```

2. **Firebase Console:**
   - Messaging → Campaign analytics
   - Xem delivery rate và error rate

3. **Driver app không nhận notification:**
   - Kiểm tra đã subscribe topic chưa
   - Kiểm tra quyền notification
   - Kiểm tra Firebase config

4. **User app không nhận notification:**
   - Kiểm tra FCM token đã add vào backend chưa
   - Kiểm tra token còn valid không
   - Kiểm tra background app restrictions

### ⚡ Performance & Best Practices

#### **Backend:**
- Dùng Job Queue để xử lý notification async
- Cache danh sách drivers online để tìm nhanh
- Retry mechanism khi FCM fail
- Rate limiting để tránh spam

#### **Mobile App:**
- Handle notification khi app ở các trạng thái khác nhau
- Local notification fallback
- Deep linking từ notification
- Background sync để update order status

---

## Common Response Format

### Success Response
```json
{
    "data": {
        // Dữ liệu chính
    }
}
```

### Error Response
```json
{
    "error": true,
    "message": [
        "Error message 1",
        "Error message 2"
    ],
    "errorCode": 422
}
```

### Validation Error Response
```json
{
    "error": true,
    "errorCode": {
        "field_name": [
            "Validation error message"
        ]
    }
}
```

---

## Error Codes

| HTTP Code | Mô tả | Xử lý |
|-----------|-------|-------|
| 200 | Success | Hiển thị dữ liệu |
| 201 | Created | Resource được tạo thành công |
| 204 | No Content | Action thành công, không có dữ liệu trả về |
| 400 | Bad Request | Kiểm tra lại request format |
| 401 | Unauthorized | Token hết hạn hoặc không hợp lệ → redirect to login |
| 422 | Validation Error | Hiển thị lỗi validation cho user |
| 500 | Server Error | Hiển thị "Lỗi hệ thống, vui lòng thử lại" |

---

## Testing Guide

### 1. Postman Testing

**Setup Environment:**
```
API_BASE_URL = http://localhost:8000/api
ACCESS_TOKEN = (get from login response)
```

**Test Flow:**
1. Đăng ký/Đăng nhập → lấy access_token
2. Cập nhật profile, upload avatar
3. Tính phí shipping cho route cụ thể
4. Tạo đơn hàng mới
5. Theo dõi trạng thái đơn hàng
6. Test FCM token APIs

### 2. Flutter App Testing

**Setup Firebase:**
```dart
// Initialize Firebase
await Firebase.initializeApp();

// Get FCM token
String? fcmToken = await FirebaseMessaging.instance.getToken();

// Add token to backend
await apiService.addFcmToken(fcmToken);
```

**Handle Notifications:**
```dart
FirebaseMessaging.onMessage.listen((RemoteMessage message) {
    // Handle foreground notification
    showNotificationDialog(message);
});

FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
    // Handle notification tap
    navigateToOrderDetail(message.data['order_id']);
});
```

### 3. Common Test Cases

**Authentication:**
- Test với số điện thoại không hợp lệ
- Test với OTP sai
- Test token expiration
- Test refresh token flow

**Orders:**
- Test tạo đơn với khoảng cách > 50km
- Test tạo đơn với địa chỉ không hợp lệ
- Test concurrent order creation
- Test order status updates

**FCM:**
- Test notification delivery
- Test notification khi app foreground/background
- Test multiple device tokens
- Test token cleanup on logout

**Performance:**
- Test với nhiều đơn hàng
- Test với danh sách notification dài
- Test upload ảnh avatar lớn
- Test concurrent API calls

---

## Notes cho Developer

### Security
- Tất cả API (trừ auth) cần Authorization header
- FCM token nên được encrypt khi lưu
- Validate tất cả input từ client
- Rate limiting cho API sensitive

### Performance
- Sử dụng pagination cho list APIs
- Cache response cho shipping fee
- Lazy load avatar và image
- Optimize query với eager loading

### UX Recommendations
- Auto-refresh order status mỗi 10s
- Show loading state cho API calls dài
- Offline handling với cached data
- Push notification sound và vibration
- Map animation cho driver location update

### Error Handling
- Network timeout: 30s cho API thường, 60s cho upload
- Retry logic cho API failure
- Fallback UI khi service unavailable
- User-friendly error messages

---