# Firebase Setup Guide - Complete Guide

## 1. Firebase Console Setup

### Step 1: Create Firebase Project
1. Go to [Firebase Console](https://console.firebase.google.com/)
2. Click "Add project"
3. Enter project name: `delivery-app-datn`
4. Enable Google Analytics (optional)
5. Create project

### Step 2: Add Android/iOS Apps
```
Android Package Name: com.yourcompany.deliveryapp
iOS Bundle ID: com.yourcompany.deliveryapp
```

### Step 3: Download Config Files
- **Android**: Download `google-services.json` 
- **iOS**: Download `GoogleService-Info.plist`

### Step 4: Enable Cloud Messaging
1. Go to Project Settings > Cloud Messaging
2. Copy **Server Key** and **Sender ID**
3. Add to Laravel `.env`:
```env
FIREBASE_SERVER_KEY=your_server_key_here
FIREBASE_SENDER_ID=your_sender_id_here
```

### Step 5: Generate Service Account Key
1. Go to Project Settings > Service Accounts
2. Click "Generate new private key"
3. Download JSON file
4. Save as `storage/firebase-service-account.json`
5. Update `.env`:
```env
FIREBASE_CREDENTIALS=storage/firebase-service-account.json
FIREBASE_PROJECT_ID=your-project-id
```

## 2. Laravel Backend Configuration

### Update .env file:
```env
# Firebase Configuration
FIREBASE_PROJECT_ID=delivery-app-datn
FIREBASE_CREDENTIALS=storage/firebase-service-account.json
FIREBASE_SERVER_KEY=AAAA...your_server_key
FIREBASE_SENDER_ID=123456789

# Queue Configuration (for notifications)
QUEUE_CONNECTION=database
# or use Redis for better performance:
# QUEUE_CONNECTION=redis
# REDIS_HOST=127.0.0.1
# REDIS_PASSWORD=null
# REDIS_PORT=6379
```

### Run migrations and setup queue:
```bash
php artisan migrate
php artisan queue:table
php artisan migrate
```

### Start queue worker:
```bash
php artisan queue:work --tries=3
```

## 3. Flutter App Configuration

### Android Setup

#### 1. Add google-services.json
```
android/app/google-services.json
```

#### 2. Update android/build.gradle:
```gradle
buildscript {
    dependencies {
        classpath 'com.google.gms:google-services:4.3.15'
    }
}
```

#### 3. Update android/app/build.gradle:
```gradle
apply plugin: 'com.google.gms.google-services'

dependencies {
    implementation 'com.google.firebase:firebase-messaging:23.2.1'
}
```

### iOS Setup

#### 1. Add GoogleService-Info.plist
```
ios/Runner/GoogleService-Info.plist
```

#### 2. Update ios/Runner/Info.plist:
```xml
<key>CFBundleURLTypes</key>
<array>
    <dict>
        <key>CFBundleURLName</key>
        <string>REVERSED_CLIENT_ID</string>
        <key>CFBundleURLSchemes</key>
        <array>
            <string>YOUR_REVERSED_CLIENT_ID</string>
        </array>
    </dict>
</array>
```

### Flutter Dependencies
```yaml
dependencies:
  firebase_core: ^2.15.1
  firebase_messaging: ^14.6.7
  flutter_local_notifications: ^15.1.0+1
```

## 4. Flutter Code Implementation

### main.dart:
```dart
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'firebase_options.dart'; // Auto-generated

// Background message handler
@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
  print("Handling a background message: ${message.messageId}");
}

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  
  await Firebase.initializeApp(
    options: DefaultFirebaseOptions.currentPlatform,
  );

  FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);

  runApp(MyApp());
}
```

### firebase_service.dart:
```dart
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';

class FirebaseService {
  static final FirebaseMessaging _messaging = FirebaseMessaging.instance;
  static final FlutterLocalNotificationsPlugin _localNotifications = 
      FlutterLocalNotificationsPlugin();
  
  static const String baseUrl = 'https://your-api.com/api';

  // Initialize Firebase Messaging
  static Future<void> initialize() async {
    // Request permissions
    NotificationSettings settings = await _messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
    );

    if (settings.authorizationStatus == AuthorizationStatus.authorized) {
      print('User granted permission');
      
      // Get FCM token
      String? token = await _messaging.getToken();
      if (token != null) {
        await sendTokenToServer(token);
        print('FCM Token: $token');
      }

      // Subscribe to topics based on user type
      await subscribeToTopics();
      
      // Initialize local notifications
      await _initializeLocalNotifications();
      
      // Handle foreground messages
      FirebaseMessaging.onMessage.listen(_handleForegroundMessage);
      
      // Handle notification taps
      FirebaseMessaging.onMessageOpenedApp.listen(_handleNotificationTap);
      
      // Handle token refresh
      _messaging.onTokenRefresh.listen(sendTokenToServer);
    }
  }

  // Send token to Laravel backend
  static Future<void> sendTokenToServer(String token) async {
    try {
      final userType = getUserType(); // 'user' or 'driver'
      final userId = getCurrentUserId();
      
      final response = await http.post(
        Uri.parse('$baseUrl/$userType/fcm-token'),
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer ${getAuthToken()}',
        },
        body: jsonEncode({
          'fcm_token': token,
          'device_type': Platform.isAndroid ? 'android' : 'ios',
        }),
      );

      if (response.statusCode == 200) {
        print('FCM token sent successfully');
      } else {
        print('Failed to send FCM token: ${response.body}');
      }
    } catch (e) {
      print('Error sending FCM token: $e');
    }
  }

  // Subscribe to topics based on user type
  static Future<void> subscribeToTopics() async {
    final userType = getUserType();
    final userId = getCurrentUserId();
    
    if (userType == 'driver') {
      // Driver subscribes to their personal topic
      await _messaging.subscribeToTopic('driver-$userId');
      print('Subscribed to driver-$userId topic');
    } else {
      // User might subscribe to general topics
      await _messaging.subscribeToTopic('general-users');
      print('Subscribed to general-users topic');
    }
  }

  // Initialize local notifications
  static Future<void> _initializeLocalNotifications() async {
    const AndroidInitializationSettings initializationSettingsAndroid =
        AndroidInitializationSettings('@mipmap/ic_launcher');
    
    const DarwinInitializationSettings initializationSettingsIOS =
        DarwinInitializationSettings(
          requestAlertPermission: true,
          requestBadgePermission: true,
          requestSoundPermission: true,
        );

    const InitializationSettings initializationSettings =
        InitializationSettings(
      android: initializationSettingsAndroid,
      iOS: initializationSettingsIOS,
    );

    await _localNotifications.initialize(
      initializationSettings,
      onDidReceiveNotificationResponse: _onNotificationTap,
    );
  }

  // Handle foreground messages
  static Future<void> _handleForegroundMessage(RemoteMessage message) async {
    print('Received foreground message: ${message.messageId}');
    
    // Show local notification
    await _showLocalNotification(message);
  }

  // Show local notification
  static Future<void> _showLocalNotification(RemoteMessage message) async {
    const AndroidNotificationDetails androidPlatformChannelSpecifics =
        AndroidNotificationDetails(
      'delivery_channel',
      'Delivery Notifications',
      channelDescription: 'Notifications for delivery updates',
      importance: Importance.max,
      priority: Priority.high,
      showWhen: false,
    );

    const DarwinNotificationDetails iOSPlatformChannelSpecifics =
        DarwinNotificationDetails();

    const NotificationDetails platformChannelSpecifics = NotificationDetails(
      android: androidPlatformChannelSpecifics,
      iOS: iOSPlatformChannelSpecifics,
    );

    await _localNotifications.show(
      message.hashCode,
      message.notification?.title ?? 'New Notification',
      message.notification?.body ?? 'You have a new notification',
      platformChannelSpecifics,
      payload: jsonEncode(message.data),
    );
  }

  // Handle notification tap
  static void _handleNotificationTap(RemoteMessage message) {
    print('Notification tapped: ${message.data}');
    _processNotificationData(message.data);
  }

  // Handle local notification tap
  static void _onNotificationTap(NotificationResponse response) {
    if (response.payload != null) {
      final data = jsonDecode(response.payload!);
      _processNotificationData(data);
    }
  }

  // Process notification data and navigate
  static void _processNotificationData(Map<String, dynamic> data) {
    final notificationType = data['type'];
    final orderId = data['order_id'];
    
    switch (notificationType) {
      case 'driver_accepted':
      case 'driver_declined':
      case 'order_completed':
      case 'no_available_driver':
        // Navigate to order details
        navigateToOrderDetails(orderId);
        break;
      case 'wait_for_confirmation':
        // Navigate to driver order confirmation
        navigateToDriverConfirmation(orderId);
        break;
      default:
        print('Unknown notification type: $notificationType');
    }
  }

  // Helper methods (implement based on your app structure)
  static String getUserType() {
    // Return 'user' or 'driver' based on current user
    return 'user'; // Placeholder
  }
  
  static String getCurrentUserId() {
    // Return current user ID
    return '1'; // Placeholder
  }
  
  static String getAuthToken() {
    // Return JWT token
    return 'your_jwt_token'; // Placeholder
  }
  
  static void navigateToOrderDetails(String orderId) {
    // Implement navigation to order details
  }
  
  static void navigateToDriverConfirmation(String orderId) {
    // Implement navigation to driver confirmation
  }
}
```

### Usage in app:
```dart
class MyApp extends StatefulWidget {
  @override
  _MyAppState createState() => _MyAppState();
}

class _MyAppState extends State<MyApp> {
  @override
  void initState() {
    super.initState();
    FirebaseService.initialize();
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Delivery App',
      home: HomePage(),
    );
  }
}
```

## 5. Testing the Complete Flow

### Test FCM Token Registration:
```bash
# Test user FCM token
curl -X POST http://localhost:8000/api/fcm-token \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "fcm_token": "test_fcm_token_123",
    "device_type": "android"
  }'

# Test driver FCM token  
curl -X POST http://localhost:8000/api/driver/fcm-token \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_DRIVER_JWT_TOKEN" \
  -d '{
    "fcm_token": "test_driver_token_456",
    "device_type": "ios"
  }'
```

### Test Order Creation Flow:
```bash
# Create order (triggers notification to drivers)
curl -X POST http://localhost:8000/api/orders \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "pickup_address": "123 Main St",
    "pickup_latitude": 10.762622,
    "pickup_longitude": 106.660172,
    "delivery_address": "456 Oak Ave",
    "delivery_latitude": 10.771991,
    "delivery_longitude": 106.697792,
    "receiver_name": "John Doe",
    "receiver_phone": "+84901234567",
    "package_type": "food",
    "weight": 2.5,
    "notes": "Handle with care"
  }'
```

### Monitor Queue Jobs:
```bash
# Start queue worker with verbose output
php artisan queue:work --verbose

# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

## 6. Troubleshooting

### Common Issues:

1. **FCM Token not received**:
   - Check Firebase project settings
   - Verify google-services.json/GoogleService-Info.plist
   - Check app permissions

2. **Notifications not sending**:
   - Verify Firebase credentials in Laravel
   - Check queue worker is running
   - Monitor Laravel logs: `tail -f storage/logs/laravel.log`

3. **Background notifications not working**:
   - Add background message handler
   - Test with device in background/killed state

4. **Topic subscription fails**:
   - Check topic naming convention
   - Verify user authentication

### Debug Commands:
```bash
# Clear Laravel caches
php artisan config:clear
php artisan cache:clear
php artisan queue:clear

# Test FCM connectivity
php artisan tinker
> $driver = App\Models\Driver::find(1);
> $driver->notify(new App\Notifications\WaitForDriverConfirmation($order));

# Check notification logs
tail -f storage/logs/laravel.log | grep FCM
```

## 7. Production Checklist

- [ ] Firebase project in production mode
- [ ] Valid SSL certificates for API
- [ ] Queue worker as daemon (Supervisor)
- [ ] Error monitoring (Sentry, Bugsnag)
- [ ] Rate limiting for FCM APIs
- [ ] Backup FCM tokens regularly
- [ ] Monitor notification delivery rates

## 8. Security Best Practices

- [ ] Validate FCM tokens before storing
- [ ] Implement token refresh mechanism
- [ ] Use HTTPS for all API calls
- [ ] Sanitize notification content
- [ ] Implement rate limiting
- [ ] Log notification activities for audit
