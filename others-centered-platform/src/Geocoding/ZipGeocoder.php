<?php

namespace OthersCentered\Platform\Geocoding;

class ZipGeocoder
{
    /**
     * Geocode a ZIP/postal code and return ['lat' => float, 'lng' => float].
     *
     * @param string $zip
     * @param string $country Country code (default: US)
     *
     * @return array|null
     */
    public static function geocode_zip(string $zip, string $country = 'US'): ?array
    {
        $zip = trim($zip);

        // Basic ZIP/postal format check — avoids URL injection
        if (!preg_match('/^[A-Za-z0-9\- ]{3,12}$/', $zip)) {
            return null;
        }

        $transient_key = 'oc_zip_geo_' . md5($zip . $country);
        $cached = get_transient($transient_key);

        if ($cached !== false) {
            return $cached;
        }

        // Retrieve API key
        $api_key = get_option('oc_google_maps_api_key', '');
        $api_key = apply_filters('oc_geocode_api_key', $api_key);

        if (empty($api_key)) {
            return null;
        }

        $params = [
            'address' => urlencode($zip . ',' . $country),
            'key'     => $api_key,
        ];

        $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query($params);

        $resp = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => ['Accept' => 'application/json']
        ]);

        if (is_wp_error($resp)) {
            self::log_error('Request failed: ' . $resp->get_error_message());
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($resp), true);

        // Google API soft errors
        if (empty($data['status']) || $data['status'] !== 'OK') {
            $status = $data['status'] ?? 'UNKNOWN';
            self::log_error("Geocode error '{$status}' for ZIP {$zip}");
            return null;
        }

        // No results
        if (empty($data['results'][0]['geometry']['location'])) {
            self::log_error("No results for ZIP {$zip}");
            return null;
        }

        $loc = $data['results'][0]['geometry']['location'];

        $out = [
            'lat' => (float) $loc['lat'],
            'lng' => (float) $loc['lng'],
        ];

        // Cache for 7 days
        set_transient($transient_key, $out, 7 * DAY_IN_SECONDS);

        return $out;
    }

    /**
     * Optional filtered error logging.
     */
    protected static function log_error(string $message): void
    {
        if (apply_filters('oc_geocode_log_errors', true)) {
            error_log('OC Geocoder: ' . $message);
        }
    }
}
