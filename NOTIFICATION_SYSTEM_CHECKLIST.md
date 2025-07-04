# Complete FCM Notification System Checklist

## ✅ BACKEND SETUP CHECKLIST

### 1. Laravel Configuration
- [ ] ✅ Firebase SDK installed (`composer require kreait/laravel-firebase`)
- [ ] ✅ Firebase service account JSON file in `storage/firebase-service-account.json`
- [ ] ✅ Environment variables configured in `.env`:
  ```env
  FIREBASE_PROJECT_ID=your-project-id
  FIREBASE_CREDENTIALS=storage/firebase-service-account.json
  FIREBASE_SERVER_KEY=your-server-key
  FIREBASE_SENDER_ID=your-sender-id
  QUEUE_CONNECTION=database
  ```
- [ ] ✅ Database tables migrated (`jobs`, `failed_jobs`)
- [ ] ✅ Queue worker running (`php artisan queue:work`)

### 2. Models & Database
- [ ] ✅ User model has `fcm_token` field (JSON array)
- [ ] ✅ Driver model has `fcm_token` field (string)
- [ ] ✅ Order model exists with all required fields
- [ ] ✅ FcmNotifiable trait implemented for both User and Driver

### 3. Controllers & Routes
- [ ] ✅ User FCM Controller: `/api/fcm-token` (POST)
- [ ] ✅ Driver FCM Controller: `/api/driver/fcm-token` (POST)
- [ ] ✅ Order Controller: `/api/orders` (POST) - triggers driver notification
- [ ] ✅ Driver Order Controller: accept/decline/complete endpoints

### 4. Notification System
- [ ] ✅ FcmTopic class for topic-based notifications (drivers)
- [ ] ✅ FcmDirect class for direct token notifications (users)
- [ ] ✅ WaitForDriverConfirmation notification (topic: driver-{id})
- [ ] ✅ DriverAcceptedOrder notification (direct to user)
- [ ] ✅ DriverDeclinedOrder notification (direct to user)
- [ ] ✅ NoAvailableDriver notification (direct to user)
- [ ] ✅ OrderHasBeenComplete notification (direct to user)

### 5. Jobs & Background Processing
- [ ] ✅ FindRandomDriverForOrder job
- [ ] ✅ FcmNotificationJob for processing notifications
- [ ] ✅ Job dispatching in OrderController::store()

## 🔥 FIREBASE CONSOLE CHECKLIST

### 1. Project Setup
- [ ] Firebase project created
- [ ] Cloud Messaging enabled
- [ ] Server key obtained
- [ ] Android app added with package name
- [ ] iOS app added with bundle ID
- [ ] Service account key generated and downloaded

### 2. Configuration Files
- [ ] `google-services.json` downloaded for Android
- [ ] `GoogleService-Info.plist` downloaded for iOS
- [ ] Service account JSON saved in Laravel backend

## 📱 MOBILE APP CHECKLIST

### 1. Flutter Dependencies
```yaml
dependencies:
  firebase_core: ^2.15.1
  firebase_messaging: ^14.6.7
  flutter_local_notifications: ^15.1.0+1
```

### 2. Android Configuration
- [ ] `google-services.json` in `android/app/`
- [ ] Google Services plugin in `android/build.gradle`
- [ ] Plugin applied in `android/app/build.gradle`
- [ ] Firebase Messaging dependency added

### 3. iOS Configuration  
- [ ] `GoogleService-Info.plist` in `ios/Runner/`
- [ ] Bundle ID matches Firebase configuration
- [ ] Push notification capability enabled

### 4. Flutter Code Implementation
- [ ] Firebase initialized in `main.dart`
- [ ] Background message handler implemented
- [ ] FCM token obtained and sent to backend
- [ ] Topic subscription for drivers
- [ ] Local notification setup
- [ ] Notification tap handling
- [ ] Foreground notification display

## 🧪 TESTING CHECKLIST

### 1. Backend Testing
- [ ] FCM token registration API works
- [ ] Order creation triggers job dispatch
- [ ] Queue worker processes jobs successfully
- [ ] Notifications sent to Firebase
- [ ] Error handling works properly

### 2. Mobile Testing
- [ ] FCM token received on app install
- [ ] Token sent to backend successfully
- [ ] Topic subscription works (drivers)
- [ ] Foreground notifications display
- [ ] Background notifications received
- [ ] Notification tap navigation works
- [ ] Token refresh handled properly

### 3. End-to-End Flow Testing
```
User creates order → 
Backend dispatches job → 
Job finds available drivers → 
Sends notification to driver topic → 
Driver receives notification → 
Driver accepts/declines → 
User receives response notification
```

## 🚀 DEPLOYMENT CHECKLIST

### 1. Production Backend
- [ ] Environment variables secured
- [ ] Queue worker as daemon (Supervisor)
- [ ] Firebase credentials secured
- [ ] SSL certificates configured
- [ ] Error monitoring setup (Sentry/Bugsnag)
- [ ] Log rotation configured
- [ ] Database backups enabled

### 2. Production Mobile
- [ ] Release builds tested
- [ ] Firebase project in production mode
- [ ] App store certificates valid
- [ ] Push notification certificates valid
- [ ] Analytics tracking enabled

### 3. Monitoring & Maintenance
- [ ] Firebase Console monitoring dashboard
- [ ] Queue job monitoring
- [ ] Failed notification tracking
- [ ] Performance monitoring
- [ ] User feedback collection

## 🔧 TROUBLESHOOTING GUIDE

### Common Issues:

1. **"FCM token not received"**
   - ✅ Check Firebase configuration files
   - ✅ Verify app permissions
   - ✅ Test on real device (not emulator)
   - ✅ Check network connectivity

2. **"Notifications not sending from backend"**
   - ✅ Verify Firebase credentials
   - ✅ Check queue worker is running
   - ✅ Monitor Laravel logs
   - ✅ Test FCM server key validity

3. **"Background notifications not working"**
   - ✅ Implement background message handler
   - ✅ Test with app killed/background
   - ✅ Check device battery optimization
   - ✅ Verify notification channel setup

4. **"Topic subscription fails"**
   - ✅ Check topic naming convention (a-zA-Z0-9-_.)
   - ✅ Verify user authentication
   - ✅ Check Firebase project permissions

### Debug Commands:

```bash
# Backend debugging
php artisan queue:work --verbose
php artisan queue:failed
tail -f storage/logs/laravel.log

# Flutter debugging  
flutter logs
flutter run --verbose

# Test notification manually
php artisan tinker
> $driver = App\Models\Driver::find(1);
> $order = App\Models\Order::find(1);
> $driver->notify(new App\Notifications\WaitForDriverConfirmation($order));
```

## 📋 QUICK VALIDATION SCRIPT

Run this to validate your setup:

```bash
# 1. Check Laravel environment
php artisan config:clear
php artisan config:cache

# 2. Test database connection
php artisan migrate:status

# 3. Test queue system
php artisan queue:work --once

# 4. Run notification flow test
php test_notification_flow.php

# 5. Check Firebase connectivity
php artisan tinker
> app('firebase.messaging')->send([]);
```

## 🎯 SUCCESS CRITERIA

Your notification system is working correctly when:

- [ ] ✅ Users can register FCM tokens via API
- [ ] ✅ Drivers can register FCM tokens via API  
- [ ] ✅ Order creation automatically notifies nearby drivers
- [ ] ✅ Driver responses trigger user notifications
- [ ] ✅ All notifications appear on mobile devices
- [ ] ✅ Notification taps navigate to correct screens
- [ ] ✅ Background/foreground notifications both work
- [ ] ✅ Failed notifications are logged and retryable
- [ ] ✅ System handles token refresh automatically
- [ ] ✅ Performance is acceptable under load

## 📞 SUPPORT & RESOURCES

- **Firebase Documentation**: https://firebase.google.com/docs/cloud-messaging
- **Laravel Notifications**: https://laravel.com/docs/notifications
- **Flutter Firebase Messaging**: https://firebase.flutter.dev/docs/messaging/overview
- **Backend API Documentation**: `USER_API_DOCUMENTATION.md`, `DRIVER_API_DOCUMENTATION.md`
- **Quick Setup Guide**: `QUICK_SETUP_GUIDE.md`
- **Firebase Setup Guide**: `FIREBASE_SETUP_GUIDE.md`

---

**Remember**: This is a complex system with many moving parts. Test each component individually before testing the complete flow. Monitor logs closely during initial deployment and have a rollback plan ready.
