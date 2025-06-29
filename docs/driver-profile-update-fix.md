# API Driver Profile Update - Hướng Dẫn Test

## 🔧 **Những gì đã được sửa:**

### **1. Thêm validation cho email:**
```php
'email' => 'bail|nullable|email|max:255|unique:drivers,email,' . auth('driver')->id(),
```

### **2. Cập nhật driver info với email:**
```php
$driver = auth('driver')->user();
$updateData = ['name' => $request['name']];

if ($request->has('email') && $request->email !== null) {
    $updateData['email'] = $request->email; // ✅ LƯU EMAIL VÀO DATABASE
}

$driver->update($updateData);
```

### **3. Response cải thiện:**
```php
return response()->json([
    'success' => true,
    'message' => 'Profile updated successfully',
    'data' => [
        'driver' => $driver->fresh()->load('profile'),
        'profile' => $profile
    ]
]);
```

---

## 🧪 **Test API với Postman:**

### **1. Lấy Driver Token trước:**
```http
POST http://localhost:8000/api/driver/login/password
Content-Type: application/json

{
    "phone_number": "+84901234567",
    "password": "your_password"
}
```

### **2. Test Update Profile:**
```http
POST http://localhost:8000/api/driver/profile
Authorization: Bearer {driver_token_from_step1}
Content-Type: multipart/form-data

Body (form-data):
name: Nguyễn Văn A
email: driver.test@gmail.com
gplx_front_url: https://firebasestorage.googleapis.com/v0/b/project/o/gplx_front.jpg?alt=media&token=xxx
gplx_back_url: https://firebasestorage.googleapis.com/v0/b/project/o/gplx_back.jpg?alt=media&token=xxx
baohiem_url: https://firebasestorage.googleapis.com/v0/b/project/o/baohiem.jpg?alt=media&token=xxx
dangky_xe_url: https://firebasestorage.googleapis.com/v0/b/project/o/dangky.jpg?alt=media&token=xxx
cmnd_front_url: https://firebasestorage.googleapis.com/v0/b/project/o/cmnd_front.jpg?alt=media&token=xxx
cmnd_back_url: https://firebasestorage.googleapis.com/v0/b/project/o/cmnd_back.jpg?alt=media&token=xxx
reference_code: REF123
```

### **3. Expected Success Response:**
```json
{
    "success": true,
    "message": "Profile updated successfully",
    "data": {
        "driver": {
            "id": 1,
            "name": "Nguyễn Văn A",
            "phone_number": "+84901234567",
            "email": "driver.test@gmail.com",
            "status": 1,
            "avatar": "storage/driver/avatar.png",
            "profile": {
                "id": 1,
                "driver_id": 1,
                "gplx_front_url": "https://firebasestorage.googleapis.com/...",
                "gplx_back_url": "https://firebasestorage.googleapis.com/...",
                "baohiem_url": "https://firebasestorage.googleapis.com/...",
                "dangky_xe_url": "https://firebasestorage.googleapis.com/...",
                "cmnd_front_url": "https://firebasestorage.googleapis.com/...",
                "cmnd_back_url": "https://firebasestorage.googleapis.com/...",
                "reference_code": "REF123"
            }
        },
        "profile": {
            // Profile data
        }
    }
}
```

---

## 📱 **Flutter Implementation:**

```dart
Future<void> updateDriverProfile() async {
  try {
    FormData formData = FormData.fromMap({
      'name': nameController.text.trim(),
      'email': emailController.text.trim(),  // ✅ Email sẽ được lưu vào DB
      
      // Document URLs từ Firebase
      'gplx_front_url': gplxFrontUrl,
      'gplx_back_url': gplxBackUrl,
      'baohiem_url': baohiemUrl,
      'dangky_xe_url': dangkyXeUrl,
      'cmnd_front_url': cmndFrontUrl,
      'cmnd_back_url': cmndBackUrl,
      'reference_code': referenceController.text.trim(),
    });

    final response = await dio.post(
      '/api/driver/profile',
      data: formData,
      options: Options(
        headers: {
          'Authorization': 'Bearer $driverToken',
          'Content-Type': 'multipart/form-data',
        },
      ),
    );

    if (response.statusCode == 200 && response.data['success'] == true) {
      var driverData = response.data['data']['driver'];
      print('✅ Profile updated successfully');
      print('📧 Email saved: ${driverData['email']}');
      print('👤 Name saved: ${driverData['name']}');
      
      // Cache thông tin
      SharedPreferences prefs = await SharedPreferences.getInstance();
      await prefs.setString('driver_name', driverData['name'] ?? '');
      await prefs.setString('driver_email', driverData['email'] ?? '');
      
      // Navigate hoặc show success message
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Cập nhật profile thành công!'))
      );
    }

  } catch (e) {
    if (e is DioException) {
      print('❌ Error: ${e.response?.statusCode}');
      print('📄 Response: ${e.response?.data}');
      
      if (e.response?.statusCode == 422) {
        var errors = e.response?.data['message'];
        if (errors['email'] != null) {
          print('📧 Email validation error: ${errors['email']}');
        }
      }
    }
  }
}
```

---

## ✅ **Kiểm tra Email đã lưu:**

### **1. Test GET profile để verify:**
```http
GET http://localhost:8000/api/driver/profile
Authorization: Bearer {driver_token}
```

### **2. Kiểm tra database trực tiếp:**
```sql
SELECT id, name, phone_number, email, created_at, updated_at 
FROM drivers 
WHERE id = 1;
```

### **3. Test email unique validation:**
```http
POST /api/driver/profile
Body:
email: existing_email@gmail.com  // Email đã tồn tại
```
Response sẽ trả về lỗi unique validation.

---

## 🎯 **Kết quả mong đợi:**

### **✅ Email sẽ được lưu vào:**
- **Table**: `drivers`
- **Column**: `email` (varchar(255) nullable)
- **Validation**: email format, unique trong bảng drivers

### **✅ API sẽ trả về:**
- Driver info đầy đủ với email
- Profile documents đã cập nhật
- Response format chuẩn với success status

### **✅ Flutter app sẽ nhận được:**
- Email đã được lưu trong response
- Có thể cache email cho các tính năng khác
- Error handling cho validation email

**Email giờ đây sẽ được lưu an toàn vào database khi frontend gửi API!** 🚀
