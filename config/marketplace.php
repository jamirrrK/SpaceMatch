<?php

return [
    // Never expose fixture records outside an explicitly isolated testing harness.
    'allow_qa_listings' => false,
    'public_listings_enabled' => true,
    'terms_version' => '2026-10-19-draft-1',
    'privacy_version' => '2026-10-19-draft-1',
    'legal_effective_date' => '2026-10-19',
    'privacy_contact' => env('PRIVACY_CONTACT_EMAIL'),
    'operator_name' => env('PLATFORM_OPERATOR_NAME'),
    'map_tile_url' => env('MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
    'map_tile_attribution' => env('MAP_TILE_ATTRIBUTION', '&copy; OpenStreetMap contributors'),
    'map_default_lat' => env('MAP_DEFAULT_LAT', 12.8797),
    'map_default_lng' => env('MAP_DEFAULT_LNG', 121.7740),
    'map_default_zoom' => env('MAP_DEFAULT_ZOOM', 5),
];
