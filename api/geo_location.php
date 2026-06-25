<?php
/**
 * api/geo_location.php  – Server-side proxy for Google Geolocation API
 * 
 * This endpoint keeps the Google API key secure on the server.
 * It accepts WiFi access point data and cell tower data from the client,
 * forwards it to the Google Geolocation API, and returns the position.
 * 
 * If no WiFi/cell data is provided, it sends an empty request to Google,
 * which will use IP-based geolocation as a fallback.
 * 
 * Usage:
 *   POST /api/geo_location.php
 *   Body: { "wifiAccessPoints": [...], "cellTowers": [...] }  (optional)
 *   
 * Response:
 *   {
 *     "ok": true,
 *     "lat": -0.002704,
 *     "lng": 34.608207,
 *     "accuracy": 50,
 *     "source": "google_api"
 *   }
 *   
 *   On error:
 *   {
 *     "ok": false,
 *     "msg": "Error description",
 *     "fallback": true  // hint to client that native GPS should be used
 *   }
 */

// Ensure any PHP error is caught and returned as JSON
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        header('Content-Type: application/json');
        echo json_encode([
            'ok'       => false,
            'msg'      => 'Server error: ' . $error['message'],
            'fallback' => true,
        ]);
        exit;
    }
});

set_exception_handler(function (Throwable $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'ok'       => false,
        'msg'      => 'Server error: ' . $e->getMessage(),
        'fallback' => true,
    ]);
    exit;
});

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';

// Check if Google Geolocation API is enabled
if (!defined('GOOGLE_API_KEY') || !GOOGLE_API_KEY) {
    echo json_encode([
        'ok'       => false,
        'msg'      => 'Google Geolocation API is not configured.',
        'fallback' => true,
    ]);
    exit;
}

if (!defined('GOOGLE_GEO_API_ENABLED') || !GOOGLE_GEO_API_ENABLED) {
    echo json_encode([
        'ok'       => false,
        'msg'      => 'Google Geolocation API is disabled.',
        'fallback' => true,
    ]);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'ok'       => false,
        'msg'      => 'Only POST requests are accepted.',
        'fallback' => true,
    ]);
    exit;
}

// Parse request body
$input = json_decode(file_get_contents('php://input'), true);

// Build the request payload for Google API
// If the client provided WiFi access points or cell towers, include them
$googlePayload = [];

if (!empty($input['wifiAccessPoints']) && is_array($input['wifiAccessPoints'])) {
    // Validate/filter each access point to avoid sending garbage
    $validPoints = [];
    foreach ($input['wifiAccessPoints'] as $ap) {
        if (!empty($ap['macAddress'])) {
            $validPoints[] = [
                'macAddress'        => $ap['macAddress'],
                'signalStrength'    => isset($ap['signalStrength']) ? (int)$ap['signalStrength'] : null,
                'signalToNoiseRatio'=> isset($ap['signalToNoiseRatio']) ? (int)$ap['signalToNoiseRatio'] : null,
                'channel'           => isset($ap['channel']) ? (int)$ap['channel'] : null,
            ];
        }
    }
    if (!empty($validPoints)) {
        $googlePayload['wifiAccessPoints'] = $validPoints;
    }
}

if (!empty($input['cellTowers']) && is_array($input['cellTowers'])) {
    $validTowers = [];
    foreach ($input['cellTowers'] as $tower) {
        if (!empty($tower['cellId']) && !empty($tower['locationAreaCode'])) {
            $validTowers[] = [
                'cellId'           => (int)$tower['cellId'],
                'locationAreaCode' => (int)$tower['locationAreaCode'],
                'mobileCountryCode'=> isset($tower['mobileCountryCode']) ? (int)$tower['mobileCountryCode'] : null,
                'mobileNetworkCode'=> isset($tower['mobileNetworkCode']) ? (int)$tower['mobileNetworkCode'] : null,
                'signalStrength'   => isset($tower['signalStrength']) ? (int)$tower['signalStrength'] : null,
            ];
        }
    }
    if (!empty($validTowers)) {
        $googlePayload['cellTowers'] = $validTowers;
    }
}

// Consider the radio type if provided (gsm, cdma, wcdma, lte, nr)
if (!empty($input['radioType'])) {
    $validRadioTypes = ['gsm', 'cdma', 'wcdma', 'lte', 'nr'];
    if (in_array(strtolower($input['radioType']), $validRadioTypes)) {
        $googlePayload['radioType'] = strtolower($input['radioType']);
    }
}

// Consider home mobile country code / network code for CDMA
if (!empty($input['homeMobileCountryCode'])) {
    $googlePayload['homeMobileCountryCode'] = (int)$input['homeMobileCountryCode'];
}
if (!empty($input['homeMobileNetworkCode'])) {
    $googlePayload['homeMobileNetworkCode'] = (int)$input['homeMobileNetworkCode'];
}

// The carrier (optional, helps with CDMA)
if (!empty($input['carrier'])) {
    $googlePayload['carrier'] = $input['carrier'];
}

// Set a reasonable timeout for the Google API call
$timeout = 5; // seconds

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => 'https://www.googleapis.com/geolocation/v1/geolocate?key=' . urlencode(GOOGLE_API_KEY),
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($googlePayload),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => $timeout,
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_USERAGENT      => 'AttendTrack/1.0',
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Handle cURL errors (network issues, timeout, etc.)
if ($curlError) {
    echo json_encode([
        'ok'       => false,
        'msg'      => 'Network error contacting geolocation service: ' . $curlError,
        'fallback' => true,
    ]);
    exit;
}

// Parse Google API response
$data = json_decode($response, true);

if (!$data || $httpCode !== 200) {
    $errorMsg = 'Geolocation service returned an error';
    
    if (isset($data['error']['message'])) {
        $errorMsg .= ': ' . $data['error']['message'];
    } elseif (isset($data['error'])) {
        $errorMsg .= ': ' . (is_string($data['error']) ? $data['error'] : 'Unknown error');
    } else {
        $errorMsg .= ' (HTTP ' . $httpCode . ')';
    }
    
    echo json_encode([
        'ok'         => false,
        'msg'        => $errorMsg,
        'fallback'   => true,
        'error_code' => $data['error']['code'] ?? $httpCode,
    ]);
    exit;
}

// Success — return the location data
if (isset($data['location']['lat']) && isset($data['location']['lng'])) {
    echo json_encode([
        'ok'       => true,
        'lat'      => (float)$data['location']['lat'],
        'lng'      => (float)$data['location']['lng'],
        'accuracy' => isset($data['accuracy']) ? (float)$data['accuracy'] : null,
        'source'   => 'google_api',
    ]);
    exit;
}

// Unexpected response format
echo json_encode([
    'ok'       => false,
    'msg'      => 'Unexpected response from geolocation service.',
    'fallback' => true,
]);