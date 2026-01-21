<?php
// This script acts as a proxy to bypass browser CORS restrictions.
header('Content-Type: application/json');

// Get parameters from the client-side request
$service = isset($_GET['service']) ? $_GET['service'] : '';
$query = isset($_GET['q']) ? $_GET['q'] : '';
$coords = isset($_GET['coords']) ? $_GET['coords'] : '';
$lat = isset($_GET['lat']) ? $_GET['lat'] : '';
$lon = isset($_GET['lon']) ? $_GET['lon'] : '';

$url = '';

if ($service === 'geocode' && !empty($query)) {
    // Geocoding service (Nominatim)
    $url = "https://nominatim.openstreetmap.org/search?format=json&q=" . urlencode($query) . "&countrycodes=PH&limit=1&addressdetails=1&extratags=1";
} elseif ($service === 'reverse' && !empty($lat) && !empty($lon)) {
    // Reverse Geocoding service (Nominatim)
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=" . urlencode($lat) . "&lon=" . urlencode($lon) . "&addressdetails=1&extratags=1";
} elseif ($service === 'route' && !empty($coords)) {
    // Routing service (OSRM)
    $url = "https://router.project-osrm.org/route/v1/driving/" . $coords . "?overview=full&geometries=geojson&alternatives=false";
} else {
    echo json_encode(['error' => 'Invalid service or missing parameters.']);
    exit;
}

// Use cURL to make the server-side request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
// IMPORTANT: Nominatim requires a User-Agent header.
curl_setopt($ch, CURLOPT_USERAGENT, 'SLATE Logistics App/1.0 (https://yourdomain.com)');
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    http_response_code(500);
    echo json_encode(['error' => 'Proxy request failed', 'details' => $error]);
} else {
    echo $response;
}
?>
