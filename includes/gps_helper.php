<?php
// includes/gps_helper.php - GPS Helper Functions

class GPSHelper {
    
    /**
     * Calculate distance between two coordinates in meters
     */
    public static function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + 
                cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        return $miles * 1609.344; // Return meters
    }
    
    /**
     * Validate if a location is within any active GPS fence
     */
    public static function validateLocation($latitude, $longitude, $conn) {
        $query = "SELECT * FROM gps_fences WHERE is_active = 1";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $fences = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($fences as $fence) {
            $distance = self::calculateDistance(
                $latitude, $longitude,
                $fence['latitude'], $fence['longitude']
            );
            
            if ($distance <= $fence['radius']) {
                return [
                    'valid' => true,
                    'fence' => $fence,
                    'distance' => $distance
                ];
            }
        }
        
        return [
            'valid' => false,
            'message' => 'You are outside the allowed geofence area'
        ];
    }
    
    /**
     * Get nearest GPS fence to a location
     */
    public static function getNearestFence($latitude, $longitude, $conn) {
        $query = "SELECT * FROM gps_fences WHERE is_active = 1";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $fences = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $nearest = null;
        $minDistance = PHP_INT_MAX;
        
        foreach ($fences as $fence) {
            $distance = self::calculateDistance(
                $latitude, $longitude,
                $fence['latitude'], $fence['longitude']
            );
            
            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearest = $fence;
                $nearest['distance'] = $distance;
            }
        }
        
        return $nearest;
    }
    
    /**
     * Format distance for display
     */
    public static function formatDistance($meters) {
        if ($meters < 1000) {
            return round($meters) . ' meters';
        } else {
            return round($meters / 1000, 2) . ' km';
        }
    }
}
?>