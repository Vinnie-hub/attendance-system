/**
 * assets/js/geo-location-service.js  –  Independent Geolocation Service
 * 
 * A self-contained, zero-dependency module that provides precise user location
 * using a two-phase strategy:
 * 
 *   Phase 1 – Google Geolocation API (via server proxy)
 *     Sends WiFi access point data to the server-side proxy (api/geo_location.php),
 *     which forwards it to the Google Geolocation API. This provides much better
 *     accuracy on desktop/laptop devices that lack dedicated GPS hardware but have
 *     WiFi connectivity. Google uses its vast database of WiFi BSSID locations to
 *     triangulate the device position.
 * 
 *   Phase 2 – Native Browser Geolocation (fallback)
 *     Falls back to the browser's built-in navigator.geolocation API if the Google
 *     API is unavailable, times out, or returns an error.
 * 
 * Usage:
 *   // One-shot position request
 *   GeoLocationService.getCurrentPosition()
 *     .then(result => {
 *       console.log(result.lat, result.lng, result.accuracy, result.source);
 *     })
 *     .catch(err => {
 *       console.error(err.message);
 *     });
 * 
 *   // Continuous position watching
 *   const watchId = GeoLocationService.watchPosition(
 *     (result) => { console.log('Position updated:', result); },
 *     (error)  => { console.error('Watch error:', error); }
 *   );
 *   // Later: GeoLocationService.stopWatching(watchId);
 * 
 *   // Check if Google Geolocation API is available
 *   GeoLocationService.isGoogleApiAvailable(); // true/false
 * 
 * This file has NO dependencies on any other project files. It can be dropped
 * into any web project and used immediately. The only requirement is that the
 * server provides the api/geo_location.php endpoint (or equivalent).
 * 
 * @version 1.0.0
 */

'use strict';

var GeoLocationService = (function () {

  // ── Configuration ──────────────────────────────────────────────
  // These can be overridden by calling GeoLocationService.configure()
  var CONFIG = {
    // URL of the server-side Google Geolocation API proxy
    apiEndpoint: '/attendance-system/api/geo_location.php',

    // Timeout for the Google API call (milliseconds)
    googleApiTimeoutMs: 5000,

    // Timeout for native GPS fallback (milliseconds)
    nativeGpsTimeoutMs: 8000,

    // Enable high accuracy for native GPS fallback
    nativeGpsHighAccuracy: true,

    // Maximum age of a cached position (milliseconds) — 0 means always fresh
    nativeGpsMaximumAge: 0,

    // Whether to collect WiFi access points for Google API (requires
    // the browser to support navigator.connection or a WiFi scanning API)
    collectWifiData: true,

    // Whether to attempt Google Geolocation API first
    useGoogleApi: true,
  };

  // ── Internal State ─────────────────────────────────────────────
  var _googleApiAvailable = null;  // null = not yet checked, true/false = known
  var _watchCounter = 0;
  var _activeWatches = {};

  // ── Public API ─────────────────────────────────────────────────

  /**
   * Override default configuration values.
   * 
   * @param {Object} overrides  Key-value pairs to merge into CONFIG.
   *   Supported keys: apiEndpoint, googleApiTimeoutMs, nativeGpsTimeoutMs,
   *   nativeGpsHighAccuracy, nativeGpsMaximumAge, collectWifiData, useGoogleApi
   * 
   * @example
   *   GeoLocationService.configure({ apiEndpoint: '/my-api/geo.php' });
   */
  function configure(overrides) {
    if (!overrides || typeof overrides !== 'object') return;
    for (var key in overrides) {
      if (overrides.hasOwnProperty(key) && CONFIG.hasOwnProperty(key)) {
        CONFIG[key] = overrides[key];
      }
    }
    // Reset cached availability when config changes
    _googleApiAvailable = null;
  }

  /**
   * Check whether the Google Geolocation API is available by making a
   * lightweight probe request to the server proxy.
   * 
   * @param {number} [timeoutMs=3000]  Timeout for the probe request.
   * @returns {Promise<boolean>}  Resolves to true if Google API is available.
   */
  function isGoogleApiAvailable(timeoutMs) {
    timeoutMs = timeoutMs || 3000;

    // Return cached result if we already checked
    if (_googleApiAvailable !== null) {
      return Promise.resolve(_googleApiAvailable);
    }

    return new Promise(function (resolve) {
      var xhr = new XMLHttpRequest();
      var timer = setTimeout(function () {
        xhr.abort();
        _googleApiAvailable = false;
        resolve(false);
      }, timeoutMs);

      xhr.open('POST', CONFIG.apiEndpoint, true);
      xhr.setRequestHeader('Content-Type', 'application/json');
      xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
          clearTimeout(timer);
          try {
            var data = JSON.parse(xhr.responseText);
            // If we get a response at all, the API is configured.
            // Even error responses (except "not configured") mean the endpoint exists.
            _googleApiAvailable = data && data.source === 'google_api';
            // If it returned an error but the endpoint is reachable, we still
            // consider it "available" — it just might fail for other reasons.
            if (!_googleApiAvailable && data && data.fallback === true) {
              // Check if the error is specifically "not configured"
              if (data.msg && data.msg.indexOf('not configured') !== -1) {
                _googleApiAvailable = false;
              } else {
                // Endpoint exists and is reachable — that's enough
                _googleApiAvailable = true;
              }
            }
          } catch (e) {
            _googleApiAvailable = false;
          }
          resolve(_googleApiAvailable);
        }
      };
      xhr.onerror = function () {
        clearTimeout(timer);
        _googleApiAvailable = false;
        resolve(false);
      };
      xhr.send(JSON.stringify({}));
    });
  }

  /**
   * Get the user's current position using the best available method.
   * 
   * Strategy:
   *   1. If Google API is enabled, attempt Google Geolocation API (with WiFi data)
   *   2. If Google API fails or is disabled, fall back to native browser geolocation
   * 
   * @param {Object} [options]
   * @param {boolean} [options.useGoogleApi=true]  Whether to try Google API first.
   * @param {number}  [options.timeoutMs]          Overall timeout.
   * @returns {Promise<GeoLocationResult>}
   * 
   * @typedef {Object} GeoLocationResult
   * @property {number}  lat       - Latitude
   * @property {number}  lng       - Longitude
   * @property {number}  accuracy  - Accuracy radius in meters
   * @property {string}  source    - 'google_api' or 'native_gps'
   * @property {number}  timestamp - Unix timestamp (milliseconds)
   */
  function getCurrentPosition(options) {
    options = options || {};
    var useGoogle = (options.useGoogleApi !== undefined) ? options.useGoogleApi : CONFIG.useGoogleApi;
    var timeoutMs = options.timeoutMs || Math.max(CONFIG.googleApiTimeoutMs, CONFIG.nativeGpsTimeoutMs);

    return new Promise(function (resolve, reject) {
      var overallTimer = setTimeout(function () {
        reject(createError('Geolocation request timed out after ' + timeoutMs + 'ms', 'timeout'));
      }, timeoutMs);

      function handleResult(result) {
        clearTimeout(overallTimer);
        resolve(result);
      }

      function handleError(err) {
        clearTimeout(overallTimer);
        reject(err);
      }

      if (useGoogle) {
        attemptGoogleGeo()
          .then(handleResult)
          .catch(function (googleErr) {
            // Google API failed — fall back to native GPS
            console.warn('GeoLocationService: Google API failed, falling back to native GPS:', googleErr.message);
            attemptNativeGeo()
              .then(handleResult)
              .catch(handleError);
          });
      } else {
        attemptNativeGeo()
          .then(handleResult)
          .catch(handleError);
      }
    });
  }

  /**
   * Start watching the user's position. The callback is invoked each time
   * the position is updated (or when a more accurate reading is available).
   * 
   * Note: This uses native browser geolocation's watchPosition under the hood,
   * but it will attempt Google API once at the start for an initial accurate fix.
   * 
   * @param {Function} onPosition  Callback receiving GeoLocationResult.
   * @param {Function} [onError]   Callback receiving error object.
   * @param {Object}   [options]   Same as getCurrentPosition options.
   * @returns {number}  Watch ID (use with stopWatching).
   */
  function watchPosition(onPosition, onError, options) {
    options = options || {};
    var watchId = ++_watchCounter;
    var nativeWatchId = null;
    var started = false;

    var watchEntry = {
      id: watchId,
      onPosition: onPosition,
      onError: onError || function () {},
      options: options,
      nativeWatchId: null,
      stopped: false,
    };

    _activeWatches[watchId] = watchEntry;

    // Step 1: Try Google API for an initial accurate fix
    var useGoogle = (options.useGoogleApi !== undefined) ? options.useGoogleApi : CONFIG.useGoogleApi;

    function startNativeWatch() {
      if (watchEntry.stopped) return;
      if (!navigator.geolocation) {
        watchEntry.onError(createError('Geolocation is not supported by this browser.', 'not_supported'));
        return;
      }

      nativeWatchId = navigator.geolocation.watchPosition(
        function (pos) {
          if (watchEntry.stopped) return;
          watchEntry.onPosition({
            lat: pos.coords.latitude,
            lng: pos.coords.longitude,
            accuracy: pos.coords.accuracy,
            source: 'native_gps',
            timestamp: Date.now(),
          });
        },
        function (err) {
          if (watchEntry.stopped) return;
          watchEntry.onError(createError(
            'Native GPS error: ' + (err.message || 'Unknown error'),
            'native_error'
          ));
        },
        {
          enableHighAccuracy: options.nativeGpsHighAccuracy !== undefined
            ? options.nativeGpsHighAccuracy : CONFIG.nativeGpsHighAccuracy,
          timeout: options.nativeGpsTimeoutMs || CONFIG.nativeGpsTimeoutMs,
          maximumAge: options.nativeGpsMaximumAge !== undefined
            ? options.nativeGpsMaximumAge : CONFIG.nativeGpsMaximumAge,
        }
      );

      watchEntry.nativeWatchId = nativeWatchId;
    }

    if (useGoogle) {
      attemptGoogleGeo()
        .then(function (result) {
          if (watchEntry.stopped) return;
          watchEntry.onPosition(result);
          // Start native watch for continuous updates
          startNativeWatch();
        })
        .catch(function () {
          // Google failed, just start native watch
          startNativeWatch();
        });
    } else {
      startNativeWatch();
    }

    return watchId;
  }

  /**
   * Stop watching a position.
   * 
   * @param {number} watchId  The ID returned by watchPosition().
   */
  function stopWatching(watchId) {
    var entry = _activeWatches[watchId];
    if (!entry) return;

    entry.stopped = true;

    if (entry.nativeWatchId !== null && navigator.geolocation) {
      navigator.geolocation.clearWatch(entry.nativeWatchId);
    }

    delete _activeWatches[watchId];
  }

  /**
   * Stop all active position watches.
   */
  function stopAllWatches() {
    for (var id in _activeWatches) {
      if (_activeWatches.hasOwnProperty(id)) {
        stopWatching(parseInt(id, 10));
      }
    }
  }

  // ── Internal Methods ───────────────────────────────────────────

  /**
   * Attempt to get position via Google Geolocation API (through server proxy).
   * 
   * @returns {Promise<GeoLocationResult>}
   */
  function attemptGoogleGeo() {
    return new Promise(function (resolve, reject) {
      var timer = setTimeout(function () {
        reject(createError('Google Geolocation API timed out.', 'timeout'));
      }, CONFIG.googleApiTimeoutMs);

      // Collect WiFi access points if supported
      var payload = {};

      if (CONFIG.collectWifiData) {
        collectWifiAccessPoints()
          .then(function (accessPoints) {
            if (accessPoints && accessPoints.length > 0) {
              payload.wifiAccessPoints = accessPoints;
            }
            // Also try to collect cell tower data
            return collectCellTowers();
          })
          .then(function (cellTowers) {
            if (cellTowers && cellTowers.length > 0) {
              payload.cellTowers = cellTowers;
            }
            // Send the request
            return sendGoogleGeoRequest(payload);
          })
          .then(function (result) {
            clearTimeout(timer);
            resolve(result);
          })
          .catch(function (err) {
            clearTimeout(timer);
            reject(err);
          });
      } else {
        // No WiFi data collection — send empty request (IP-based fallback)
        sendGoogleGeoRequest(payload)
          .then(function (result) {
            clearTimeout(timer);
            resolve(result);
          })
          .catch(function (err) {
            clearTimeout(timer);
            reject(err);
          });
      }
    });
  }

  /**
   * Send a request to the server-side Google Geolocation API proxy.
   * 
   * @param {Object} payload  The request body (wifiAccessPoints, cellTowers, etc.)
   * @returns {Promise<GeoLocationResult>}
   */
  function sendGoogleGeoRequest(payload) {
    return new Promise(function (resolve, reject) {
      var xhr = new XMLHttpRequest();
      xhr.open('POST', CONFIG.apiEndpoint, true);
      xhr.setRequestHeader('Content-Type', 'application/json');

      xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) return;

        var responseText = (xhr.responseText || '').trim();

        // Check for non-JSON response (e.g. HTML error page, authentication page)
        if (responseText.length > 0 && responseText.charAt(0) !== '{' && responseText.charAt(0) !== '[') {
          // Not JSON — likely an HTML error or redirect page.
          // Extract a useful preview for debugging but don't crash.
          var preview = responseText.substring(0, 200).replace(/\s+/g, ' ').trim();
          reject(createError(
            'Geolocation service returned non-JSON response: "' + preview + '..."',
            'invalid_response'
          ));
          return;
        }

        var data;
        try {
          data = JSON.parse(responseText);
        } catch (e) {
          reject(createError(
            'Geolocation service returned unparseable response: ' + e.message,
            'parse_error'
          ));
          return;
        }

        if (data && data.ok === true && data.lat !== undefined && data.lng !== undefined) {
          resolve({
            lat: data.lat,
            lng: data.lng,
            accuracy: data.accuracy !== null && data.accuracy !== undefined ? data.accuracy : 100,
            source: 'google_api',
            timestamp: Date.now(),
          });
        } else {
          var msg = (data && data.msg) ? data.msg : 'Geolocation service returned an error.';
          reject(createError(msg, 'service_error'));
        }
      };
      xhr.onerror = function () {
        reject(createError('Network error contacting geolocation service.', 'network_error'));
      };

      xhr.ontimeout = function () {
        reject(createError('Geolocation service request timed out.', 'timeout'));
      };

      xhr.timeout = CONFIG.googleApiTimeoutMs;
      xhr.send(JSON.stringify(payload));
    });
  }

  /**
   * Attempt to get position via native browser geolocation API.
   * 
   * @returns {Promise<GeoLocationResult>}
   */
  function attemptNativeGeo() {
    return new Promise(function (resolve, reject) {
      if (!navigator.geolocation) {
        reject(createError('Geolocation is not supported by this browser.', 'not_supported'));
        return;
      }

      navigator.geolocation.getCurrentPosition(
        function (pos) {
          resolve({
            lat: pos.coords.latitude,
            lng: pos.coords.longitude,
            accuracy: pos.coords.accuracy,
            source: 'native_gps',
            timestamp: Date.now(),
          });
        },
        function (err) {
          var msg;
          switch (err.code) {
            case err.PERMISSION_DENIED:
              msg = 'Location access denied by user. Please enable location permissions.';
              break;
            case err.POSITION_UNAVAILABLE:
              msg = 'Location information is unavailable.';
              break;
            case err.TIMEOUT:
              msg = 'Location request timed out.';
              break;
            default:
              msg = 'Unknown geolocation error: ' + (err.message || '');
          }
          reject(createError(msg, 'native_error'));
        },
        {
          enableHighAccuracy: CONFIG.nativeGpsHighAccuracy,
          timeout: CONFIG.nativeGpsTimeoutMs,
          maximumAge: CONFIG.nativeGpsMaximumAge,
        }
      );
    });
  }

  /**
   * Collect WiFi access point data from the browser.
   * 
   * The W3C Network Information API and WiFi scanning APIs are not widely
   * supported. However, some browsers (especially on Android) expose this
   * data. We attempt to collect whatever is available.
   * 
   * For browsers that don't support WiFi scanning, we return an empty array,
   * and the Google API will fall back to IP-based geolocation.
   * 
   * @returns {Promise<Array>}  Array of { macAddress, signalStrength, ... }
   */
  function collectWifiAccessPoints() {
    return new Promise(function (resolve) {
      // Check for the experimental WiFi scanning API
      // (navigator.wifi is non-standard, but some Chromium-based browsers support it)
      if (navigator.wifi && typeof navigator.wifi.getVisibleNetworks === 'function') {
        navigator.wifi.getVisibleNetworks(function (networks) {
          if (networks && networks.length > 0) {
            var accessPoints = networks.map(function (net) {
              return {
                macAddress: net.bssid || net.macAddress || '',
                signalStrength: net.signalStrength !== undefined ? net.signalStrength : null,
                channel: net.channel || null,
              };
            }).filter(function (ap) {
              return ap.macAddress.length > 0;
            });
            resolve(accessPoints);
          } else {
            resolve([]);
          }
        }, function () {
          resolve([]);
        });
      } else {
        // No WiFi scanning API available — return empty array
        resolve([]);
      }
    });
  }

  /**
   * Collect cell tower data from the browser.
   * 
   * This is even less supported than WiFi scanning. We include it for
   * completeness, but it will likely return an empty array in most browsers.
   * 
   * @returns {Promise<Array>}  Array of { cellId, locationAreaCode, ... }
   */
  function collectCellTowers() {
    return new Promise(function (resolve) {
      // navigator.mozMobileConnection or similar are Firefox OS / legacy APIs
      // Modern browsers do not expose cell tower data for privacy reasons.
      // We return an empty array — Google API will use IP-based fallback.
      resolve([]);
    });
  }

  /**
   * Create a standardized error object.
   * 
   * @param {string} message  Human-readable error description.
   * @param {string} code     Machine-readable error code.
   * @returns {Error}
   */
  function createError(message, code) {
    var err = new Error(message);
    err.code = code || 'unknown';
    return err;
  }

  // ── Public API Surface ─────────────────────────────────────────
  return {
    configure: configure,
    isGoogleApiAvailable: isGoogleApiAvailable,
    getCurrentPosition: getCurrentPosition,
    watchPosition: watchPosition,
    stopWatching: stopWatching,
    stopAllWatches: stopAllWatches,
  };

})();