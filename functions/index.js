/**
 * Import function triggers from their respective submodules:
 *
 * const {onCall} = require("firebase-functions/v2/https");
 * const {onDocumentWritten} = require("firebase-functions/v2/firestore");
 *
 * See a full list of supported triggers at https://firebase.google.com/docs/functions
 */

const {setGlobalOptions} = require("firebase-functions");
const {onRequest} = require("firebase-functions/https");
const {onValueUpdated} = require("firebase-functions/v2/database");
const logger = require("firebase-functions/logger");
const admin = require("firebase-admin");

// Initialize Firebase Admin
admin.initializeApp();

// For cost control, you can set the maximum number of containers that can be
// running at the same time. This helps mitigate the impact of unexpected
// traffic spikes by instead downgrading performance. This limit is a
// per-function limit. You can override the limit for each function using the
// `maxInstances` option in the function's options, e.g.
// `onRequest({ maxInstances: 5 }, (req, res) => { ... })`.
// NOTE: setGlobalOptions does not apply to functions using the v1 API. V1
// functions should each use functions.runWith({ maxInstances: 10 }) instead.
// In the v1 API, each function can only serve one request per container, so
// this will be the maximum concurrent request count.
setGlobalOptions({ maxInstances: 10 });

// Create and deploy your first functions
// https://firebase.google.com/docs/functions/get-started

// Firebase Function để lắng nghe thay đổi tọa độ và cập nhật database
exports.onLocationUpdated = onValueUpdated(
  {
    ref: "/realtime-locations/{driverId}",
    region: "asia-southeast1", // Thay đổi region phù hợp
  },
  async (event) => {
    try {
      const driverId = event.params.driverId;
      const locationData = event.data.after.val();
      const previousData = event.data.before.val();

      logger.info(`Location updated for driver: ${driverId}`, {
        structuredData: true,
        locationData: locationData,
        previousData: previousData
      });

      // Kiểm tra nếu có dữ liệu mới
      if (!locationData) {
        logger.warn(`No location data for driver: ${driverId}`);
        return;
      }

      // Chuẩn bị dữ liệu để lưu vào database
      const trackerData = {
        driver_id: driverId,
        latitude: locationData.latitude || 0,
        longitude: locationData.longitude || 0,
        accuracy: locationData.accuracy || 0,
        bearing: locationData.bearing || 0,
        speed: locationData.speed || 0,
        is_online: locationData.isOnline || false,
        status: locationData.status || 0,
        timestamp: locationData.timestamp ? new Date(locationData.timestamp) : new Date(),
        created_at: new Date(),
        updated_at: new Date()
      };

      // Lưu vào Firestore (hoặc có thể gửi HTTP request đến Laravel API)
      const db = admin.firestore();
      
      // Lưu vào collection 'trackers'
      await db.collection('trackers').doc(driverId).set(trackerData, { merge: true });
      
      logger.info(`Successfully updated tracker for driver: ${driverId}`, {
        structuredData: true,
        trackerData: trackerData
      });

      // Gửi HTTP request đến Laravel API để cập nhật database
      await updateLaravelDatabase(driverId, trackerData);

    } catch (error) {
      logger.error(`Error updating location for driver: ${event.params.driverId}`, {
        structuredData: true,
        error: error.message,
        stack: error.stack
      });
    }
  }
);

// Function để gửi HTTP request đến Laravel API
async function updateLaravelDatabase(driverId, trackerData) {
  try {
    const axios = require('axios');
    
    // Thay đổi URL này thành URL thực tế của Laravel API
    const laravelApiUrl = 'http://localhost:8000/api/tracker/update';
    
    const response = await axios.post(laravelApiUrl, {
      driver_id: driverId,
      latitude: trackerData.latitude,
      longitude: trackerData.longitude,
      accuracy: trackerData.accuracy,
      bearing: trackerData.bearing,
      speed: trackerData.speed,
      is_online: trackerData.is_online,
      status: trackerData.status,
      timestamp: trackerData.timestamp
    }, {
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer 92sScwy7i3xlal7VdDpqpp0ge3hUb9EYzlaVrZeSBwJSvF2ZLr2dXHnV34s8'
      },
      timeout: 10000 // 10 seconds timeout
    });

    logger.info(`Successfully sent data to Laravel API for driver: ${driverId}`, {
      structuredData: true,
      response: response.data
    });

  } catch (error) {
    logger.error(`Error sending data to Laravel API for driver: ${driverId}`, {
      structuredData: true,
      error: error.message
    });
  }
}

// Function để lắng nghe thay đổi trạng thái online/offline
exports.onDriverStatusChanged = onValueUpdated(
  {
    ref: "/realtime-locations/{driverId}/isOnline",
    region: "asia-southeast1",
  },
  async (event) => {
    try {
      const driverId = event.params.driverId;
      const isOnline = event.data.after.val();
      const wasOnline = event.data.before.val();

      logger.info(`Driver status changed: ${driverId}`, {
        structuredData: true,
        isOnline: isOnline,
        wasOnline: wasOnline
      });

      // Cập nhật trạng thái driver trong database
      const db = admin.firestore();
      await db.collection('drivers').doc(driverId).update({
        is_online: isOnline,
        last_status_update: new Date()
      });

      // Gửi notification nếu cần
      if (isOnline && !wasOnline) {
        logger.info(`Driver ${driverId} went online`);
        // Có thể gửi notification ở đây
      } else if (!isOnline && wasOnline) {
        logger.info(`Driver ${driverId} went offline`);
        // Có thể gửi notification ở đây
      }

    } catch (error) {
      logger.error(`Error updating driver status: ${driverId}`, {
        structuredData: true,
        error: error.message
      });
    }
  }
);

// Function để lắng nghe tất cả thay đổi trong realtime-locations
exports.onRealtimeLocationsChanged = onValueUpdated(
  {
    ref: "/realtime-locations",
    region: "asia-southeast1",
  },
  async (event) => {
    try {
      const allLocations = event.data.after.val();
      
      if (!allLocations) {
        logger.info("No locations data available");
        return;
      }

      logger.info(`Processing ${Object.keys(allLocations).length} driver locations`, {
        structuredData: true
      });

      // Xử lý từng driver location
      const promises = Object.entries(allLocations).map(async ([driverId, locationData]) => {
        try {
          const trackerData = {
            driver_id: driverId,
            latitude: locationData.latitude || 0,
            longitude: locationData.longitude || 0,
            accuracy: locationData.accuracy || 0,
            bearing: locationData.bearing || 0,
            speed: locationData.speed || 0,
            is_online: locationData.isOnline || false,
            status: locationData.status || 0,
            timestamp: locationData.timestamp ? new Date(locationData.timestamp) : new Date(),
            updated_at: new Date()
          };

          const db = admin.firestore();
          await db.collection('trackers').doc(driverId).set(trackerData, { merge: true });

          return { driverId, success: true };
        } catch (error) {
          logger.error(`Error processing driver ${driverId}:`, error.message);
          return { driverId, success: false, error: error.message };
        }
      });

      const results = await Promise.all(promises);
      const successCount = results.filter(r => r.success).length;
      const errorCount = results.filter(r => !r.success).length;

      logger.info(`Batch update completed: ${successCount} success, ${errorCount} errors`, {
        structuredData: true,
        results: results
      });

    } catch (error) {
      logger.error("Error in batch location update:", {
        structuredData: true,
        error: error.message,
        stack: error.stack
      });
    }
  }
);
