# API Documentation - App Tài Xế (Driver App)

## Mục lục
1. [Authentication APIs](#authentication-apis)
2. [Profile Management APIs](#profile-management-apis)
3. [Order Management APIs](#order-management-apis)
4. [FCM Notification APIs](#fcm-notification-apis)
5. [Location & Status APIs](#location--status-apis)
6. [Common Response Format](#common-response-format)
7. [Error Codes](#error-codes)
8. [Testing Guide](#testing-guide)

---

## Authentication APIs

### 1. Đăng ký tài khoản tài xế
**POST** `/api/driver/register`

**Mô tả:** Đăng ký tài khoản tài xế mới bằng số điện thoại và OTP

**Request Body:**
```json
{
    "phone_number": "+84987654321",
    "otp": "1234",
    "name": "Nguyễn Văn Tài Xế"
}
```

**Response Success (201):**
```json
{
    "data": {
        "id": 1,
        "name": "Nguyễn Văn Tài Xế",
        "phone_number": "+84987654321",
        "email": null,
        "status": "offline",
        "current_location": null,
        "delivering_order_id": null,
        "review_rate": 0,
        "created_at": "2024-01-01T00:00:00.000000Z",
        "updated_at": "2024-01-01T00:00:00.000000Z"
    },
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
    "token_type": "Bearer",
    "expires_at": "2024-12-31T23:59:59.000000Z"
}
```

**Use Case:**
- Tài xế mới muốn đăng ký làm việc trên platform
- Chọn "Đăng ký tài khoản tài xế"
- Nhập số điện thoại → gọi API gửi OTP
- Nhập OTP và tên → gọi API này
- Sau đăng ký thành công, cần hoàn thiện profile để được duyệt

**Lỗi thường gặp:**
- 422: OTP không đúng, số điện thoại đã tồn tại
- 400: Thiếu thông tin bắt buộc

### 2. Gửi OTP đăng ký tài xế
**POST** `/api/driver/register/otp`

**Request Body:**
```json
{
    "phone_number": "+84987654321"
}
```

**Response Success (204):** Không có body, chỉ status code

**Use Case:**
- Tài xế nhập số điện thoại mới để đăng ký
- App gọi API này để gửi OTP
- Tài xế nhận SMS OTP và nhập vào app

### 3. Đăng nhập bằng OTP
**POST** `/api/driver/login`

**Request Body:**
```json
{
    "phone_number": "+84987654321",
    "otp": "1234"
}
```

**Response:** Giống như API đăng ký

**Use Case:**
- Tài xế đã có tài khoản nhưng quên mật khẩu
- Tài xế muốn đăng nhập nhanh bằng OTP
- Tài xế chuyển thiết bị mới

### 4. Gửi OTP đăng nhập
**POST** `/api/driver/login/otp`

**Request Body:**
```json
{
    "phone_number": "+84987654321"
}
```

**Response Success (204):** Không có body

### 5. Đăng nhập bằng mật khẩu
**POST** `/api/driver/login/password`

**Request Body:**
```json
{
    "phone_number": "+84987654321",
    "password": "123456"
}
```

**Response:** Giống như API đăng ký

**Use Case:**
- Tài xế đã có mật khẩu và muốn đăng nhập nhanh
- Không cần gửi OTP qua SMS

---

## Profile Management APIs

### 1. Lấy thông tin profile tài xế
**GET** `/api/driver/profile`
**Headers:** `Authorization: Bearer {access_token}`

**Response Success (200):**
```json
{
    "data": {
        "id": 1,
        "name": "Nguyễn Văn Tài Xế",
        "phone_number": "+84987654321",
        "email": "driver@example.com",
        "avatar": "https://firebasestorage.googleapis.com/...",
        "status": "free",
        "current_location": {
            "lat": 10.8231,
            "lon": 106.6297
        },
        "delivering_order_id": null,
        "review_rate": 4.8,
        "created_at": "2024-01-01T00:00:00.000000Z",
        "updated_at": "2024-01-01T00:00:00.000000Z",
        "profile": {
            "id": 1,
            "driver_id": 1,
            "gplx_front_url": "https://firebasestorage.googleapis.com/...",
            "gplx_back_url": "https://firebasestorage.googleapis.com/...",
            "baohiem_url": "https://firebasestorage.googleapis.com/...",
            "dangky_xe_url": "https://firebasestorage.googleapis.com/...",
            "cmnd_front_url": "https://firebasestorage.googleapis.com/...",
            "cmnd_back_url": "https://firebasestorage.googleapis.com/...",
            "reference_code": "REF12345",
            "is_verified": true,
            "created_at": "2024-01-01T00:00:00.000000Z",
            "updated_at": "2024-01-01T00:00:00.000000Z"
        }
    }
}
```

**Use Case:**
- Hiển thị thông tin trong màn hình Profile
- Kiểm tra trạng thái xác minh hồ sơ
- Hiển thị avatar, tên, rating trong header app

### 2. Cập nhật profile tài xế
**POST** `/api/driver/profile`
**Headers:** 
- `Authorization: Bearer {access_token}`
- `Content-Type: multipart/form-data`

**Request Body:**
```
name: "Nguyễn Văn Tài Xế Mới"
email: "driver@example.com" 
gplx_front: [file] (image, max 2MB, jpeg/png/jpg)
gplx_back: [file] (image, max 2MB, jpeg/png/jpg)
baohiem: [file] (image, max 2MB, jpeg/png/jpg)
dangky_xe: [file] (image, max 2MB, jpeg/png/jpg)
cmnd_front: [file] (image, max 2MB, jpeg/png/jpg)
cmnd_back: [file] (image, max 2MB, jpeg/png/jpg)
reference_code: "REF12345"
```

**Response Success (200):**
```json
{
    "success": true,
    "message": "Profile updated successfully",
    "data": {
        "driver": {
            "id": 1,
            "name": "Nguyễn Văn Tài Xế Mới",
            "phone_number": "+84987654321",
            "email": "driver@example.com",
            "avatar": "http://localhost:8000/storage/avatars/driver_1.jpg",
            "status": "free",
            "current_location": null,
            "delivering_order_id": null,
            "review_rate": 4.8,
            "created_at": "2024-01-01T00:00:00.000000Z",
            "updated_at": "2024-01-01T00:00:00.000000Z"
        },
        "profile": {
            "id": 1,
            "driver_id": 1,
            "gplx_front_url": "http://localhost:8000/storage/driver_documents/1_gplx_front_1704067200.jpg",
            "gplx_back_url": "http://localhost:8000/storage/driver_documents/1_gplx_back_1704067200.jpg",
            "baohiem_url": "http://localhost:8000/storage/driver_documents/1_baohiem_1704067200.jpg",
            "dangky_xe_url": "http://localhost:8000/storage/driver_documents/1_dangky_xe_1704067200.jpg",
            "cmnd_front_url": "http://localhost:8000/storage/driver_documents/1_cmnd_front_1704067200.jpg",
            "cmnd_back_url": "http://localhost:8000/storage/driver_documents/1_cmnd_back_1704067200.jpg",
            "reference_code": "REF12345",
            "is_verified": false,
            "created_at": "2024-01-01T00:00:00.000000Z",
            "updated_at": "2024-01-01T00:00:00.000000Z"
        }
    }
}
```

**Validation:**
- `name`: bắt buộc, tối đa 50 ký tự
- `email`: tùy chọn, định dạng email hợp lệ, unique
- `gplx_front`: bắt buộc, file ảnh (ảnh mặt trước GPLX), max 2MB, jpeg/png/jpg
- `gplx_back`: bắt buộc, file ảnh (ảnh mặt sau GPLX), max 2MB, jpeg/png/jpg
- `baohiem`: bắt buộc, file ảnh (ảnh bảo hiểm xe), max 2MB, jpeg/png/jpg
- `dangky_xe`: bắt buộc, file ảnh (ảnh đăng ký xe), max 2MB, jpeg/png/jpg
- `cmnd_front`: bắt buộc, file ảnh (ảnh mặt trước CMND/CCCD), max 2MB, jpeg/png/jpg
- `cmnd_back`: bắt buộc, file ảnh (ảnh mặt sau CMND/CCCD), max 2MB, jpeg/png/jpg
- `reference_code`: tùy chọn, mã giới thiệu

**Use Case:**
- Tài xế mới hoàn thiện hồ sơ để được duyệt
- Tài xế cập nhật thông tin cá nhân
- Upload các giấy tờ bắt buộc (6 ảnh: GPLX 2 mặt, CMND 2 mặt, đăng ký xe, bảo hiểm)
- Hệ thống tự động lưu ảnh vào local storage với tên file unique
- Tự động xóa ảnh cũ khi upload ảnh mới để tiết kiệm storage
- Sau khi upload, admin sẽ xác minh và cập nhật is_verified = true

**Upload Flow:**
1. Tài xế chọn 6 ảnh từ thư viện hoặc chụp mới
2. App upload multipart/form-data với các file ảnh
3. Backend validate (image, max 2MB, jpeg/png/jpg)
4. Lưu ảnh vào `storage/app/public/driver_documents/`
5. Tạo URL public để admin và tài xế có thể xem
6. Trả về URLs để hiển thị trong app

### 3. Đổi avatar tài xế
**POST** `/api/driver/profile/avatar`
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
        "avatar": "https://firebasestorage.googleapis.com/v0/b/project/o/avatars%2Fdriver_1_1640995200.jpg"
    }
}
```

**Use Case:**
- Tài xế chọn ảnh đại diện từ thư viện hoặc chụp mới
- Upload ảnh lên Firebase Storage
- Cập nhật URL avatar trong database
- User thấy avatar tài xế khi được assign đơn hàng

### 4. Đặt mật khẩu lần đầu
**POST** `/api/driver/set-password`
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
- Tài xế đăng ký bằng OTP lần đầu (chưa có mật khẩu)
- App gợi ý tài xế tạo mật khẩu để đăng nhập nhanh lần sau
- Chỉ được gọi khi tài xế chưa có mật khẩu

### 5. Đổi mật khẩu
**POST** `/api/driver/change-password`
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
- Tài xế muốn thay đổi mật khẩu hiện tại
- Tài xế đã đăng nhập và nhớ mật khẩu cũ

---

## Order Management APIs

### 1. Lấy tổng quan đơn hàng theo thời gian
**GET** `/api/driver/orders/summary`
**Headers:** `Authorization: Bearer {access_token}`

**Query Parameters:**
```
from=2024-01-01&to=2024-01-31&status=4
```

**Response Success (200):**
```json
{
    "data": [
        {
            "id": 123,
            "user_id": 5,
            "driver_id": 1,
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
            "user_note": "Gọi điện trước khi đến",
            "shipping_cost": 25000,
            "distance": 5.2,
            "status_code": 4,
            "driver_rate": 5,
            "driver_accept_at": "2024-01-01T10:05:00.000000Z",
            "driver_complete_at": "2024-01-01T10:30:00.000000Z",
            "created_at": "2024-01-01T10:00:00.000000Z",
            "updated_at": "2024-01-01T10:30:00.000000Z"
        }
    ]
}
```

**Query Parameters:**
- `from`: bắt buộc, ngày bắt đầu (YYYY-MM-DD)
- `to`: bắt buộc, ngày kết thúc (>= from)
- `status`: tùy chọn, lọc theo trạng thái đơn hàng

**Use Case:**
- Xem thống kê đơn hàng trong khoảng thời gian
- Tính toán thu nhập theo ngày/tuần/tháng
- Xem lịch sử các đơn đã giao
- Báo cáo hiệu suất làm việc

### 2. Chấp nhận đơn hàng
**POST** `/api/driver/orders/{order_id}/accept`
**Headers:** `Authorization: Bearer {access_token}`

**Response Success (200):**
```json
{
    "data": {
        "id": 123,
        "user_id": 5,
        "driver_id": 1,
        "status_code": 2,
        "driver_accept_at": "2024-01-01T10:05:00.000000Z",
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
        "user_note": "Gọi điện trước khi đến",
        "shipping_cost": 25000,
        "distance": 5.2,
        "user": {
            "id": 5,
            "name": "Nguyễn Văn User",
            "phone_number": "+84987654321"
        }
    }
}
```

**Business Logic:**
- Chỉ được accept đơn có status: pending hoặc cancelled_by_driver
- Tự động cập nhật status tài xế thành "busy"
- Gửi notification cho user báo tài xế đã chấp nhận
- Cập nhật delivering_order_id cho tài xế

**Use Case:**
1. Tài xế nhận notification có đơn hàng mới
2. Xem chi tiết đơn hàng (địa chỉ, phí, khoảng cách)
3. Quyết định accept hoặc decline
4. Nếu accept → chuyển sang màn hình navigation đến điểm đón
5. User nhận thông báo và có thể theo dõi tài xế

**Lỗi thường gặp:**
- 422: Đơn hàng không ở trạng thái có thể accept
- 401: Tài xế không có quyền với đơn này
- 403: Tài xế chưa được xác minh hồ sơ

### 3. Từ chối đơn hàng
**POST** `/api/driver/orders/{order_id}/decline`
**Headers:** `Authorization: Bearer {access_token}`

**Response Success (200):**
```json
{
    "data": {
        "id": 123,
        "status_code": 5,
        "message": "Đơn hàng đã được từ chối và sẽ tìm tài xế khác"
    }
}
```

**Business Logic:**
- Chỉ được decline đơn có status: pending hoặc waiting_confirmation
- Cập nhật status đơn thành "cancelled_by_driver"
- Tự động dispatch job tìm tài xế khác
- Gửi notification cho user báo tài xế từ chối và đang tìm tài xế mới

**Use Case:**
1. Tài xế nhận notification nhưng không thể nhận đơn
2. Có thể do: xa quá, đang bận, không muốn đi khu vực đó
3. Decline → hệ thống tự động tìm tài xế khác
4. User nhận thông báo và tiếp tục chờ

### 4. Hoàn thành đơn hàng
**POST** `/api/driver/orders/{order_id}/complete`
**Headers:** `Authorization: Bearer {access_token}`

**Response Success (200):**
```json
{
    "data": {
        "id": 123,
        "status_code": 4,
        "driver_complete_at": "2024-01-01T10:30:00.000000Z",
        "message": "Đơn hàng đã được hoàn thành thành công"
    }
}
```

**Business Logic:**
- Chỉ được complete đơn có status: in_transit hoặc driver_accepted
- Cập nhật status đơn thành "completed"
- Cập nhật status tài xế về "free" hoặc "offline"
- Xóa delivering_order_id của tài xế
- Gửi notification cho user báo đơn hàng đã hoàn thành
- User có thể đánh giá tài xế

**Use Case:**
1. Tài xế đã đến điểm đón và nhận hàng
2. Di chuyển đến điểm giao
3. Giao hàng thành công cho người nhận
4. Bấm "Hoàn thành đơn hàng"
5. User nhận thông báo và có thể đánh giá

### 5. Chi tiết đơn hàng
**GET** `/api/driver/orders/{order_id}`
**Headers:** `Authorization: Bearer {access_token}`

**Response Success (200):**
```json
{
    "data": {
        "id": 123,
        "user_id": 5,
        "driver_id": 1,
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
        "user_note": "Gọi điện trước khi đến",
        "shipping_cost": 25000,
        "distance": 5.2,
        "status_code": 2,
        "driver_accept_at": "2024-01-01T10:05:00.000000Z",
        "driver_complete_at": null,
        "created_at": "2024-01-01T10:00:00.000000Z",
        "updated_at": "2024-01-01T10:05:00.000000Z",
        "user": {
            "id": 5,
            "name": "Nguyễn Văn User",
            "phone_number": "+84987654321",
            "avatar": "https://firebasestorage.googleapis.com/..."
        }
    }
}
```

**Use Case:**
- Xem chi tiết đơn hàng đã được assign
- Hiển thị thông tin người đặt hàng
- Xem ghi chú đặc biệt từ user
- Navigation đến địa chỉ đón/giao
- Gọi điện cho user khi cần

### 6. Chia sẻ đơn hàng cho tài xế khác
**POST** `/api/driver/orders/{order_id}/drivers/sharing`
**Headers:** `Authorization: Bearer {access_token}`

**Request Body:**
```json
{
    "shared_to_driver_ids": [2, 3, 4]
}
```

**Response Success (200):**
```json
{
    "data": {
        "message": "Đơn hàng đã được chia sẻ cho 3 tài xế",
        "shared_drivers": [
            {
                "id": 2,
                "name": "Tài xế B",
                "phone_number": "+84912345679"
            },
            {
                "id": 3,
                "name": "Tài xế C", 
                "phone_number": "+84912345680"
            }
        ]
    }
}
```

**Use Case:**
- Tài xế nhận đơn nhưng phát hiện không thể thực hiện
- Chia sẻ cho đồng nghiệp gần đó
- Tài xế khác có thể accept đơn được chia sẻ

### 7. Chấp nhận đơn hàng được chia sẻ
**POST** `/api/driver/orders/{order_id}/drivers/sharing/accept`
**Headers:** `Authorization: Bearer {access_token}`

**Response:** Giống như accept đơn hàng thông thường

### 8. Từ chối đơn hàng được chia sẻ
**POST** `/api/driver/orders/{order_id}/drivers/sharing/decline`
**Headers:** `Authorization: Bearer {access_token}`

**Response Success (204):** Không có body

---

## FCM Notification APIs

### 1. Thêm FCM Token cho tài xế
**POST** `/api/driver/fcm/token`
**Headers:** `Authorization: Bearer {access_token}`

**Request Body:**
```json
{
    "fcm_token": "eA7Z9k2..._FCM_TOKEN_HERE_..."
}
```

**Response Success (204):** Không có body

**Use Case:**
- App tài xế khởi động lần đầu
- Tài xế cấp quyền nhận notification
- App refresh FCM token (token có thể thay đổi)
- Subscribe vào topic để nhận đơn hàng trong khu vực

### 2. Xóa FCM Token cho tài xế
**DELETE** `/api/driver/fcm/token`
**Headers:** `Authorization: Bearer {access_token}`

**Request Body:**
```json
{
    "fcm_token": "eA7Z9k2..._FCM_TOKEN_HERE_..."
}
```

**Response Success (204):** Không có body

**Use Case:**
- Tài xế logout khỏi app
- Tài xế tắt notification trong settings
- App bị uninstall
- Unsubscribe khỏi topic notification

### 3. Lấy danh sách thông báo
**GET** `/api/driver/notifications`
**Headers:** `Authorization: Bearer {access_token}`

**Response Success (200):**
```json
{
    "data": [
        {
            "id": "uuid-notification-1",
            "type": "App\\Notifications\\WaitForDriverConfirmation",
            "data": {
                "order_id": 123,
                "message": "Bạn có đơn hàng mới từ Nguyễn Văn A",
                "user_name": "Nguyễn Văn A",
                "from_address": "123 Nguyễn Huệ, Quận 1",
                "to_address": "456 Võ Văn Tần, Quận 3",
                "shipping_cost": 25000,
                "distance": 5.2
            },
            "read_at": null,
            "created_at": "2024-01-01T10:00:00.000000Z"
        }
    ]
}
```

**Use Case:**
- Hiển thị danh sách thông báo trong app
- Tài xế xem lại các notification đã nhận
- Tracking history các đơn hàng đã được thông báo

---

## Location & Status APIs

### 1. Cập nhật vị trí GPS hiện tại
**POST** `/api/driver/current-location`
**Headers:** `Authorization: Bearer {access_token}`

**Request Body:**
```json
{
    "lat": 10.8231,
    "lon": 106.6297
}
```

**Response Success (204):** Không có body

**Validation:**
- `lat`: bắt buộc, số thực, latitude hợp lệ (-90 đến 90)
- `lon`: bắt buộc, số thực, longitude hợp lệ (-180 đến 180)

**Use Case:**
- App tự động gửi GPS mỗi 10-30 giây khi tài xế đang online
- User có thể theo dõi vị trí tài xế real-time
- Hệ thống dùng để tính khoảng cách và tìm tài xế gần nhất
- Chỉ gửi khi tài xế đã cho phép chia sẻ vị trí

**Lưu ý:**
- Chỉ gửi khi tài xế ở trạng thái online (free hoặc busy)
- Không gửi khi offline để tiết kiệm pin và data
- Cần kiểm tra quyền location trước khi gửi

### 2. Đặt trạng thái online (sẵn sàng nhận đơn)
**POST** `/api/driver/setting/status/online`
**Headers:** `Authorization: Bearer {access_token}`

**Response Success (200):**
```json
{
    "data": {
        "id": 1,
        "name": "Nguyễn Văn Tài Xế",
        "status": "free",
        "current_location": {
            "lat": 10.8231,
            "lon": 106.6297
        },
        "delivering_order_id": null
    }
}
```

**Business Logic:**
- Nếu tài xế không có đơn đang giao → status = "free"
- Nếu tài xế có đơn đang giao → status = "busy"
- Bắt đầu nhận notification đơn hàng mới (chỉ khi free)
- Bắt đầu gửi location updates

**Use Case:**
- Tài xế bắt đầu ca làm việc
- Sẵn sàng nhận đơn hàng mới
- App hiển thị "Đang online" với dot xanh
- Bắt đầu tracking GPS

### 3. Đặt trạng thái offline (không nhận đơn)
**POST** `/api/driver/setting/status/offline`
**Headers:** `Authorization: Bearer {access_token}`

**Response Success (200):**
```json
{
    "data": {
        "id": 1,
        "name": "Nguyễn Văn Tài Xế",
        "status": "offline",
        "current_location": {
            "lat": 10.8231,
            "lon": 106.6297
        },
        "delivering_order_id": null
    }
}
```

**Business Logic:**
- Status = "offline"
- Ngừng nhận notification đơn hàng mới
- Ngừng gửi location updates (tiết kiệm pin)
- Nếu đang có đơn, vẫn có thể hoàn thành

**Use Case:**
- Tài xế kết thúc ca làm việc
- Tạm nghỉ (ăn trưa, đổ xăng...)
- App hiển thị "Đang offline" với dot đỏ
- Ngừng tracking GPS

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
| 403 | Forbidden | Tài xế chưa được xác minh hồ sơ → yêu cầu hoàn thiện |
| 422 | Validation Error | Hiển thị lỗi validation cho tài xế |
| 500 | Server Error | Hiển thị "Lỗi hệ thống, vui lòng thử lại" |

---

## Status Codes Đơn Hàng

| Status Code | Tên | Mô tả |
|-------------|-----|-------|
| 0 | pending | Đơn hàng mới tạo, chờ tài xế |
| 1 | waiting_confirmation | Đã assign tài xế, chờ xác nhận |
| 2 | driver_accepted | Tài xế đã chấp nhận |
| 3 | in_transit | Đang trên đường giao |
| 4 | completed | Đã hoàn thành |
| 5 | cancelled_by_driver | Tài xế từ chối |
| 6 | cancelled_by_user | User hủy |

## Status Codes Tài Xế

| Status | Mô tả |
|--------|-------|
| free | Sẵn sàng nhận đơn mới |
| busy | Đang giao đơn hàng |
| offline | Không hoạt động |

---

## Testing Guide

### 1. Postman Testing

**Setup Environment:**
```
API_BASE_URL = http://localhost:8000/api
DRIVER_ACCESS_TOKEN = (get from driver login response)
```

**Test Flow:**
1. Đăng ký/Đăng nhập tài xế → lấy access_token
2. **Cập nhật profile với 6 ảnh:**
   ```bash
   curl -X POST http://localhost:8000/api/driver/profile \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -F "name=Nguyễn Văn Test" \
     -F "email=test@driver.com" \
     -F "gplx_front=@/path/to/gplx_front.jpg" \
     -F "gplx_back=@/path/to/gplx_back.jpg" \
     -F "baohiem=@/path/to/baohiem.jpg" \
     -F "dangky_xe=@/path/to/dangky_xe.jpg" \
     -F "cmnd_front=@/path/to/cmnd_front.jpg" \
     -F "cmnd_back=@/path/to/cmnd_back.jpg" \
     -F "reference_code=REF123"
   ```
3. Test online/offline status
4. Test accept/decline/complete order
5. Test FCM token APIs
6. Test location updates

### 2. Flutter Driver App Testing

**Setup Firebase:**
```dart
// Initialize Firebase
await Firebase.initializeApp();

// Get FCM token
String? fcmToken = await FirebaseMessaging.instance.getToken();

// Add token to backend
await driverApiService.addFcmToken(fcmToken);

// Subscribe to driver topic
await FirebaseMessaging.instance.subscribeToTopic('driver-${driverId}');
```

**Handle Notifications:**
```dart
FirebaseMessaging.onMessage.listen((RemoteMessage message) {
    // Handle new order notification
    if (message.data['type'] == 'new_order') {
        showNewOrderDialog(message.data);
    }
});

FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
    // Handle notification tap
    navigateToOrderDetail(message.data['order_id']);
});
```

**Location Updates:**
```dart
Timer.periodic(Duration(seconds: 30), (timer) async {
    if (driverStatus == 'online') {
        Position position = await Geolocator.getCurrentPosition();
        await driverApiService.updateLocation(
            position.latitude, 
            position.longitude
        );
    }
});
```

### 3. Common Test Cases

**Authentication:**
- Test với số điện thoại chưa đăng ký
- Test với OTP sai
- Test token expiration
- Test profile verification requirements

**Orders:**
- Test accept order when offline
- Test accept order already taken by another driver
- Test complete order not in correct status
- Test concurrent order acceptance

**Location:**
- Test location updates with invalid coordinates
- Test location permissions denied
- Test location when offline
- Test GPS accuracy requirements

**FCM:**
- Test notification delivery to driver
- Test topic subscription/unsubscription
- Test notification when app closed
- Test multiple device tokens per driver

---

## Notification Types cho Tài Xế

### 1. WaitForDriverConfirmation
```json
{
    "title": "Đơn hàng mới",
    "body": "Bạn có đơn hàng mới từ Nguyễn Văn A",
    "data": {
        "type": "new_order",
        "order_id": "123",
        "user_name": "Nguyễn Văn A",
        "from_address": "123 Nguyễn Huệ, Q1",
        "to_address": "456 Võ Văn Tần, Q3",
        "shipping_cost": "25000",
        "distance": "5.2"
    }
}
```

### 2. OrderSharedNotification
```json
{
    "title": "Đơn hàng được chia sẻ",
    "body": "Tài xế Nguyễn Văn B chia sẻ đơn hàng cho bạn",
    "data": {
        "type": "shared_order",
        "order_id": "123",
        "shared_by": "Nguyễn Văn B"
    }
}
```

---

## Notes cho Developer

### Security & Permissions
- Tất cả API cần Authorization header với driver token
- API trong middleware 'profileVerified' yêu cầu profile đã xác minh
- Location API cần check GPS permissions
- FCM token nên được encrypt

### Performance & UX
- Location updates: 30s interval khi online, stop khi offline
- Cache order data để offline handling
- Preload maps cho navigation
- Background location tracking khi có đơn active

### Business Logic
- Driver chỉ nhận notification khi status = "free"
- Auto set status = "busy" khi accept order
- Auto set status = "free" khi complete order
- Distance calculation dùng OSRM (real road distance)

### Error Handling
- Graceful degradation khi GPS không available
- Retry logic cho location updates
- Notification fallback khi FCM fails
- Offline mode cho critical functions

### Integration với User App
- Real-time location sharing với user
- Bidirectional communication qua notifications
- Order status sync between apps
- Rating system integration

---

**Tài liệu này cung cấp đầy đủ thông tin để team mobile dev implement app tài xế. Đối với các tính năng real-time (location tracking, notifications), cần test kỹ trên thiết bị thật với network conditions khác nhau.**

---

## 🔥 Setup Firebase & FCM Toàn Diện

### ❌ **QUAN NIỆM SAI:** "Chỉ cần cấu hình Firebase là xong"

### ✅ **THỰC TẾ:** Cần setup đầy đủ cả Backend + Mobile Apps + Firebase Console

---

## 🏗️ Setup Backend Laravel

### 1. **Cài đặt Laravel Firebase packages**
```bash
composer require kreait/firebase-php
composer require laravel-notification-channels/fcm
```

### 2. **Tạo Firebase Service Account**
1. Vào **Firebase Console** → Project Settings → Service Accounts
2. Click **"Generate new private key"**
3. Download file JSON (ví dụ: `firebase-service-account.json`)
4. Đặt file vào `storage/app/firebase/` folder

### 3. **Cấu hình Laravel Environment**
```env
# .env file
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_PRIVATE_KEY_PATH=storage/app/firebase/firebase-service-account.json
FIREBASE_DATABASE_URL=https://your-project-id-default-rtdb.firebaseio.com/

# Optional: FCM Server Key (for legacy)
FCM_SERVER_KEY=your-fcm-server-key
```

### 4. **Cấu hình Firebase Service Provider**
```php
// config/firebase.php
<?php
return [
    'project_id' => env('FIREBASE_PROJECT_ID'),
    'private_key_path' => storage_path('app/firebase/firebase-service-account.json'),
    'database_url' => env('FIREBASE_DATABASE_URL'),
];
```

### 5. **Đăng ký Firebase Service**
```php
// app/Providers/AppServiceProvider.php
use Kreait\Firebase\Factory;

public function register()
{
    $this->app->singleton('firebase.messaging', function ($app) {
        $factory = (new Factory)
            ->withServiceAccount(config('firebase.private_key_path'))
            ->withProjectId(config('firebase.project_id'));
            
        return $factory->createMessaging();
    });
}
```

### 6. **Queue Configuration cho Background Jobs**
```env
# .env - Cấu hình Queue để xử lý notification async
QUEUE_CONNECTION=database
# Hoặc dùng Redis cho performance tốt hơn
# QUEUE_CONNECTION=redis
```

Chạy migration để tạo bảng jobs:
```bash
php artisan queue:table
php artisan migrate
```

Chạy queue worker để xử lý jobs:
```bash
php artisan queue:work
# Hoặc dùng supervisor trong production
```

---

## 📱 Setup Flutter User App

### 1. **Thêm Firebase dependencies**
```yaml
# pubspec.yaml
dependencies:
  firebase_core: ^2.24.0
  firebase_messaging: ^14.7.3
  flutter_local_notifications: ^16.2.0
  
dev_dependencies:
  firebase_app_check: ^0.2.1+7
```

### 2. **Cấu hình Firebase cho Android**
```
android/app/google-services.json (download từ Firebase Console)
```

```gradle
// android/build.gradle
buildscript {
    dependencies {
        classpath 'com.google.gms:google-services:4.3.15'
    }
}

// android/app/build.gradle
apply plugin: 'com.google.gms.google-services'

dependencies {
    implementation 'com.google.firebase:firebase-messaging:23.2.1'
}
```

### 3. **Cấu hình Firebase cho iOS**
```
ios/Runner/GoogleService-Info.plist (download từ Firebase Console)
```

### 4. **Initialize Firebase trong Flutter**
```dart
// main.dart
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  
  // Initialize Firebase
  await Firebase.initializeApp();
  
  // Request permission for notifications
  await FirebaseMessaging.instance.requestPermission(
    alert: true,
    badge: true,
    sound: true,
  );
  
  runApp(MyApp());
}
```

### 5. **Handle FCM trong User App**
```dart
// services/fcm_service.dart
class FCMService {
  static final FirebaseMessaging _messaging = FirebaseMessaging.instance;
  
  static Future<void> initialize() async {
    // Get FCM token
    String? token = await _messaging.getToken();
    print('FCM Token: $token');
    
    // Send token to backend
    if (token != null) {
      await ApiService.addFcmToken(token);
    }
    
    // Listen for token refresh
    _messaging.onTokenRefresh.listen((newToken) {
      ApiService.addFcmToken(newToken);
    });
    
    // Handle foreground messages
    FirebaseMessaging.onMessage.listen(_handleForegroundMessage);
    
    // Handle background tap
    FirebaseMessaging.onMessageOpenedApp.listen(_handleBackgroundTap);
  }
  
  static void _handleForegroundMessage(RemoteMessage message) {
    print('Received message: ${message.notification?.title}');
    
    if (message.data['type'] == 'driver_accepted') {
      // Navigate to order tracking
      NavigationService.navigateToOrderTracking(message.data['order_id']);
    } else if (message.data['type'] == 'driver_declined') {
      // Show message "Đang tìm tài xế khác"
      NotificationService.showLocalNotification(
        'Tài xế từ chối', 
        'Đang tìm tài xế khác cho bạn...'
      );
    }
  }
  
  static void _handleBackgroundTap(RemoteMessage message) {
    // Handle khi user tap notification từ background
    String? orderId = message.data['order_id'];
    if (orderId != null) {
      NavigationService.navigateToOrderDetail(orderId);
    }
  }
}
```

---

## 🚗 Setup Flutter Driver App

### 1. **Tương tự User App + Topic Subscription**
```dart
// services/driver_fcm_service.dart
class DriverFCMService {
  static final FirebaseMessaging _messaging = FirebaseMessaging.instance;
  
  static Future<void> initialize(String driverId) async {
    // Get token và gửi lên backend
    String? token = await _messaging.getToken();
    if (token != null) {
      await DriverApiService.addFcmToken(token);
    }
    
    // Subscribe to driver-specific topic
    await _messaging.subscribeToTopic('driver-$driverId');
    
    // Handle notifications
    FirebaseMessaging.onMessage.listen(_handleNewOrderNotification);
    FirebaseMessaging.onMessageOpenedApp.listen(_handleNotificationTap);
  }
  
  static void _handleNewOrderNotification(RemoteMessage message) {
    if (message.data['key'] == 'NewOder') { // Đúng như backend config
      String orderId = message.data['oderId']; // Chú ý là 'oderId' không phải 'orderId'
      String link = message.data['link']; // driver://AwaitAcceptOder
      
      // Show dialog với Accept/Decline buttons
      showDialog(
        context: navigatorKey.currentContext!,
        barrierDismissible: false,
        builder: (context) => NewOrderDialog(
          orderId: orderId,
          onAccept: () => _acceptOrder(orderId),
          onDecline: () => _declineOrder(orderId),
        ),
      );
    }
  }
  
  static Future<void> _acceptOrder(String orderId) async {
    try {
      await DriverApiService.acceptOrder(orderId);
      // Navigate to order detail/navigation
      NavigationService.navigateToOrderDetail(orderId);
    } catch (e) {
      // Handle error
      showErrorMessage('Không thể chấp nhận đơn hàng');
    }
  }
  
  static Future<void> _declineOrder(String orderId) async {
    try {
      await DriverApiService.declineOrder(orderId);
      // Close dialog
      Navigator.pop(navigatorKey.currentContext!);
    } catch (e) {
      showErrorMessage('Không thể từ chối đơn hàng');
    }
  }
  
  static Future<void> logout(String driverId) async {
    // Unsubscribe from topic
    await _messaging.unsubscribeFromTopic('driver-$driverId');
    
    // Remove FCM token từ backend
    String? token = await _messaging.getToken();
    if (token != null) {
      await DriverApiService.removeFcmToken(token);
    }
  }
}
```

---

## 🔧 Firebase Console Setup

### 1. **Tạo Firebase Project**
1. Vào https://console.firebase.google.com/
2. Click **"Create a project"**
3. Nhập project name (ví dụ: "delivery-app")
4. Enable Google Analytics (optional)

### 2. **Add Android Apps**
1. Click **"Add app"** → Android
2. Package name: `com.yourcompany.userapp` (User App)
3. Download `google-services.json`
4. Lặp lại cho Driver App: `com.yourcompany.driverapp`

### 3. **Add iOS Apps (nếu có)**
1. Click **"Add app"** → iOS  
2. Bundle ID: `com.yourcompany.userapp`
3. Download `GoogleService-Info.plist`

### 4. **Enable Cloud Messaging**
1. Vào **Project Settings** → **Cloud Messaging**
2. Copy **Server key** (for legacy, optional)
3. Enable **Firebase Cloud Messaging API** trong Google Cloud Console

### 5. **Generate Service Account**
1. **Project Settings** → **Service accounts**
2. Click **"Generate new private key"**
3. Download JSON file
4. Đặt vào Laravel backend

---

## 🧪 Test Notification Flow

### 1. **Test từ Firebase Console**
```
Firebase Console → Cloud Messaging → Send your first message

Target: Topic "driver-1" 
Title: "Test notification"
Body: "This is a test"
```

### 2. **Test từ Backend**
```php
// Tạo test route để gửi notification
Route::get('/test-notification/{driverId}', function ($driverId) {
    $driver = Driver::find($driverId);
    $order = Order::first(); // Lấy order mẫu
    
    $driver->notify(new WaitForDriverConfirmation($order));
    
    return 'Notification sent!';
});
```

### 3. **Debug Tools**
```dart
// Trong Flutter app, log FCM events
FirebaseMessaging.onMessage.listen((message) {
    print('🔥 Foreground message: ${message.toMap()}');
});

FirebaseMessaging.onBackgroundMessage((message) {
    print('🔥 Background message: ${message.toMap()}');
    return Future.value();
});
```

---

## ⚠️ Common Issues & Solutions

### 1. **Backend không gửi được notification**
```bash
# Check Laravel logs
tail -f storage/logs/laravel.log

# Check queue jobs
php artisan queue:failed
php artisan queue:retry all
```

**Solution:**
- Kiểm tra Firebase service account JSON đúng path
- Đảm bảo queue worker đang chạy
- Check Project ID trong config

### 2. **App không nhận được notification**
**Android:**
- Check `google-services.json` đúng package name
- Permissions trong `AndroidManifest.xml`
- Background app restrictions

**iOS:**  
- Check `GoogleService-Info.plist` đúng bundle ID
- APNS certificates configured
- Notification permissions granted

### 3. **Topic subscription không hoạt động**
```dart
// Verify subscription
FirebaseMessaging.instance.getToken().then((token) {
    print('FCM Token: $token');
});

// Check subscribed topics (Android only)
// Sử dụng Firebase console để test gửi đến topic
```

### 4. **Notification data không đúng format**
```php
// Trong WaitForDriverConfirmation.php
public function toArray($notifiable)
{
    return [
        'key' => "NewOder", // ✅ Đúng theo backend hiện tại
        'link' => "driver://AwaitAcceptOder",
        'oderId' => (string) $this->order->id, // ✅ Chú ý là 'oderId'
        'order_id' => (string) $this->order->id, // ➕ Thêm field chuẩn
        'user_name' => $this->order->customer->name ?? 'Unknown',
        'from_address' => $this->order->from_address['desc'] ?? '',
        'to_address' => $this->order->to_address['desc'] ?? '',
        'shipping_cost' => $this->order->shipping_cost,
        'distance' => $this->order->distance,
    ];
}
```

---

## 🎯 Production Checklist

### Backend:
- [ ] Firebase service account JSON secure
- [ ] Queue worker running with supervisor
- [ ] Error logging enabled
- [ ] Rate limiting cho FCM APIs
- [ ] Backup cho failed jobs

### Mobile Apps:
- [ ] Release build notifications working
- [ ] Background notifications working  
- [ ] App icon badges working
- [ ] Deep linking working
- [ ] Error handling cho network issues

### Firebase:
- [ ] Production project setup
- [ ] Analytics enabled
- [ ] Quotas và billing configured
- [ ] Security rules reviewed

---

**🚨 TÓM LẠI: Không phải chỉ cấu hình Firebase. Cần setup đầy đủ cả Backend Laravel + Firebase SDK + Mobile Apps + Testing để hệ thống notification hoạt động hoàn chỉnh!**
