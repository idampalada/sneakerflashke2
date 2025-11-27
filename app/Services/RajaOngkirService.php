<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RajaOngkirService
{
    private $apiKey;
    private $baseUrl;
    private $timeout;
    private $cacheDuration;

    public function __construct()
    {
        $this->apiKey = config('services.rajaongkir.api_key');
        $this->baseUrl = 'https://rajaongkir.komerce.id/api/v1';
        $this->timeout = config('services.rajaongkir.timeout', 25);
        $this->cacheDuration = config('services.rajaongkir.cache_duration', 3600);

        Log::info('RajaOngkir V2 Service initialized (Fixed Format)', [
            'base_url' => $this->baseUrl,
            'timeout' => $this->timeout,
            'api_key_set' => !empty($this->apiKey)
        ]);
    }

    /**
     * Get all provinces - WORKING FORMAT
     * Response format: { meta: {...}, data: [{ id: 1, name: "PROVINCE NAME" }] }
     */
    public function getProvinces()
    {
        return Cache::remember('rajaongkir_v2_provinces_fixed', $this->cacheDuration, function () {
            try {
                Log::info('Fetching provinces from RajaOngkir V2 API (Fixed Format)');
                
                $response = Http::timeout($this->timeout)->withHeaders([
                    'key' => $this->apiKey
                ])->get($this->baseUrl . '/destination/province');

                if ($response->successful()) {
                    $data = $response->json();
                    
                    if (isset($data['data']) && is_array($data['data'])) {
                        $provinces = array_map(function($province) {
                            return [
                                'province_id' => $province['id'],      // Map to expected format
                                'province' => $province['name']        // Map to expected format
                            ];
                        }, $data['data']);
                        
                        Log::info('Successfully fetched ' . count($provinces) . ' provinces (Fixed Format)');
                        return $provinces;
                    }
                }

                Log::warning('RajaOngkir V2 provinces API returned unexpected format');
                return [];
            } catch (\Exception $e) {
                Log::error('RajaOngkir V2 Get Provinces Error', [
                    'message' => $e->getMessage()
                ]);
                return [];
            }
        });
    }

    /**
     * Search destinations - WORKING METHOD (since cities endpoint returns 404)
     * This replaces getCities since the cities endpoint doesn't work
     */
    public function searchDestinations($search, $limit = 10, $offset = 0)
    {
        try {
            Log::info('Searching destinations via RajaOngkir V2 (Fixed Method)', [
                'search' => $search,
                'limit' => $limit
            ]);
            
            $response = Http::timeout($this->timeout)->withHeaders([
                'key' => $this->apiKey
            ])->get($this->baseUrl . '/destination/domestic-destination', [
                'search' => $search,
                'limit' => $limit,
                'offset' => $offset
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['data']) && is_array($data['data'])) {
                    $destinations = array_map(function($dest) {
                        return [
                            'location_id' => $dest['id'],
                            'subdistrict_name' => $dest['subdistrict_name'],
                            'district_name' => $dest['district_name'],
                            'city_name' => $dest['city_name'],
                            'province_name' => $dest['province_name'],
                            'zip_code' => $dest['zip_code'],
                            'label' => $dest['label'],
                            'full_address' => $dest['subdistrict_name'] . ', ' . 
                                            $dest['district_name'] . ', ' . 
                                            $dest['city_name'] . ', ' . 
                                            $dest['province_name']
                        ];
                    }, $data['data']);
                    
                    Log::info('Successfully found ' . count($destinations) . ' destinations for search: ' . $search);
                    return $destinations;
                }
            }

            return [];
        } catch (\Exception $e) {
            Log::error('RajaOngkir V2 Search Destinations Error', [
                'message' => $e->getMessage(),
                'search' => $search
            ]);
            return [];
        }
    }

    /**
     * Get cities by searching for province name
     * Since direct cities endpoint doesn't work, we search by province name
     */
    public function getCitiesByProvince($provinceName)
    {
        try {
            Log::info('Getting cities by province via search', ['province' => $provinceName]);
            
            // Search for locations in this province
            $response = Http::timeout($this->timeout)->withHeaders([
                'key' => $this->apiKey
            ])->get($this->baseUrl . '/destination/domestic-destination', [
                'search' => $provinceName,
                'limit' => 50 // Get more results to find cities
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['data']) && is_array($data['data'])) {
                    // Group by city and get unique cities
                    $cities = [];
                    $uniqueCities = [];
                    
                    foreach ($data['data'] as $location) {
                        $cityKey = $location['city_name'];
                        
                        if (!isset($uniqueCities[$cityKey])) {
                            $uniqueCities[$cityKey] = true;
                            $cities[] = [
                                'city_id' => $location['id'], // Use location ID as city ID
                                'city_name' => $location['city_name'],
                                'province_name' => $location['province_name'],
                                'sample_location_id' => $location['id'] // For reference
                            ];
                        }
                    }
                    
                    Log::info('Found ' . count($cities) . ' unique cities for province: ' . $provinceName);
                    return $cities;
                }
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Error getting cities by province', [
                'message' => $e->getMessage(),
                'province' => $provinceName
            ]);
            return [];
        }
    }

    /**
     * Calculate shipping costs - ENDPOINT NEEDS TO BE FOUND
     * Current cost endpoints return 404, need to find working endpoint
     */
public function calculateShipping($originId, $destinationId, $weight, $courier = 'jne')
{
    try {
        Log::info('🚢 RajaOngkir Service shipping calculation (FIXED)', [
            'origin' => $originId,
            'destination' => $destinationId,
            'weight' => $weight,
            'courier' => $courier,
            'endpoint' => '/calculate/domestic-cost'
        ]);

        // Ensure weight is at least 1000g (1kg minimum)
        $weight = max(1000, (int) $weight);

        // FIXED: Use the exact same format as successful tinker test
        $response = Http::asForm()
            ->withHeaders([
                'accept' => 'application/json',
                'key' => $this->apiKey
            ])
            ->timeout(15)
            ->post($this->baseUrl . '/calculate/domestic-cost', [
                'origin' => $originId,
                'destination' => $destinationId,
                'weight' => $weight,
                'courier' => 'jne'  // Fixed to JNE only for now
            ]);

        Log::info("📡 Service API response", [
            'status' => $response->status(),
            'successful' => $response->successful()
        ]);

        if ($response->successful()) {
            $data = $response->json();
            Log::info("✅ Service API response received", [
                'has_data' => isset($data['data']),
                'response_structure' => array_keys($data ?? []),
                'data_count' => isset($data['data']) ? count($data['data']) : 0
            ]);
            
            // Parse the response using the correct format
            $shippingOptions = $this->parseShippingResponseFixed($data);
            
            if (!empty($shippingOptions)) {
                Log::info("🎯 Service found " . count($shippingOptions) . " shipping options");
                return $shippingOptions;
            } else {
                Log::warning("❌ Service parsed no shipping options from response");
                return [];
            }
        } else {
            Log::error("❌ Service API request failed", [
                'status' => $response->status(),
                'response' => $response->body()
            ]);
            return [];
        }
    } catch (\Exception $e) {
        Log::error('❌ Service shipping calculation error', [
            'error' => $e->getMessage(),
            'origin' => $originId,
            'destination' => $destinationId
        ]);
        return [];
    }
}

/**
 * Parse shipping response with the correct format from /calculate/domestic-cost
 * Response format: {"meta": {...}, "data": [{"name": "...", "code": "jne", "service": "...", "description": "...", "cost": 10000, "etd": "1 day"}]}
 */
// REPLACEMENT FUNCTION 1: parseShippingResponseFixed() dengan filtering
private function parseShippingResponseFixed($data)
{
    $options = [];
    
    try {
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $option) {
                $options[] = [
                    'courier' => strtoupper($option['code'] ?? 'JNE'),
                    'courier_name' => $option['name'] ?? 'Jalur Nugraha Ekakurir (JNE)',
                    'service' => $option['service'] ?? 'Unknown',
                    'description' => $option['description'] ?? $option['service'] ?? 'Shipping Service',
                    'cost' => (int) ($option['cost'] ?? 0),
                    'formatted_cost' => 'Rp ' . number_format($option['cost'] ?? 0, 0, ',', '.'),
                    'etd' => $option['etd'] ?? 'N/A',
                    'formatted_etd' => $option['etd'] ?? 'N/A',
                    'recommended' => false,
                    'type' => 'api',
                    'is_mock' => false
                ];
            }
        }
        
        // ⭐ FILTER: Hapus layanan JNE yang tidak diinginkan
        $options = $this->filterUnwantedJNEServices($options);
        
        Log::info('🎯 Service parsed and filtered ' . count($options) . ' shipping options', [
            'sample_options' => array_map(function($opt) {
                return $opt['service'] . ' - Rp ' . number_format($opt['cost']);
            }, array_slice($options, 0, 3))
        ]);
        
    } catch (\Exception $e) {
        Log::error('Error parsing shipping response', [
            'error' => $e->getMessage(),
            'data_structure' => is_array($data) ? array_keys($data) : 'not_array',
            'data_sample' => is_array($data) ? json_encode(array_slice($data, 0, 2)) : substr(json_encode($data), 0, 200)
        ]);
    }
    
    return $options;
}

// REPLACEMENT FUNCTION 2: parseJNEShippingResponse() dengan filtering
private function parseJNEShippingResponse($data)
{
    $jneOptions = [];
    
    try {
        // Check for standard RajaOngkir response structure
        if (isset($data['rajaongkir']['results'])) {
            // Standard RajaOngkir format
            $results = $data['rajaongkir']['results'];
            
            foreach ($results as $result) {
                // FILTER: Only process JNE results
                if (strtolower($result['code'] ?? '') === 'jne' && isset($result['costs'])) {
                    foreach ($result['costs'] as $cost) {
                        $jneOptions[] = [
                            'courier' => 'JNE',
                            'courier_name' => 'JNE',
                            'service' => $cost['service'] ?? 'REG',
                            'description' => $cost['description'] ?? 'JNE Service',
                            'cost' => isset($cost['cost'][0]['value']) ? (int) $cost['cost'][0]['value'] : 0,
                            'formatted_cost' => 'Rp ' . number_format($cost['cost'][0]['value'] ?? 0, 0, ',', '.'),
                            'etd' => $cost['cost'][0]['etd'] ?? '2-3',
                            'formatted_etd' => ($cost['cost'][0]['etd'] ?? '2-3') . ' hari',
                            'recommended' => ($cost['service'] ?? '') === 'REG', // REG as recommended
                            'type' => 'api',
                            'is_mock' => false
                        ];
                    }
                }
            }
        }
        // Check for Komerce custom format
        elseif (isset($data['data']) && is_array($data['data'])) {
            // Komerce custom format - filter JNE only
            foreach ($data['data'] as $result) {
                if (strtolower($result['courier'] ?? '') === 'jne') {
                    $jneOptions[] = [
                        'courier' => 'JNE',
                        'courier_name' => 'JNE',
                        'service' => $result['service'] ?? 'REG',
                        'description' => $result['description'] ?? 'JNE Service',
                        'cost' => isset($result['cost']) ? (int) $result['cost'] : 0,
                        'formatted_cost' => 'Rp ' . number_format($result['cost'] ?? 0, 0, ',', '.'),
                        'etd' => $result['etd'] ?? '2-3',
                        'formatted_etd' => ($result['etd'] ?? '2-3') . ' hari',
                        'recommended' => ($result['service'] ?? '') === 'REG',
                        'type' => 'api',
                        'is_mock' => false
                    ];
                }
            }
        }
        
        // ⭐ FILTER: Hapus layanan JNE yang tidak diinginkan
        $jneOptions = $this->filterUnwantedJNEServices($jneOptions);
        
        // Sort JNE options: REG first, then by cost
        usort($jneOptions, function($a, $b) {
            // REG service first
            if ($a['service'] === 'REG' && $b['service'] !== 'REG') return -1;
            if ($b['service'] === 'REG' && $a['service'] !== 'REG') return 1;
            
            // Then sort by cost
            return $a['cost'] <=> $b['cost'];
        });
        
        Log::info("📋 Parsed and filtered JNE options", [
            'total_jne_options' => count($jneOptions),
            'services' => array_map(function($opt) {
                return $opt['service'] . ' (Rp ' . number_format($opt['cost']) . ')';
            }, $jneOptions)
        ]);
        
    } catch (\Exception $e) {
        Log::error('Error parsing JNE response', [
            'error' => $e->getMessage(),
            'data_structure' => is_array($data) ? array_keys($data) : 'not_array'
        ]);
    }
    
    return $jneOptions;
}

/**
 * Get JNE service types available
 */
public function getJNEServices()
{
    return [
        'REG' => 'Layanan Reguler',
        'YES' => 'Yakin Esok Sampai',
        'OKE' => 'Ongkos Kirim Ekonomis',
        'CTCYES' => 'City Courier Yes',
        'CTCREG' => 'City Courier Reguler'
    ];
}

/**
 * Parse shipping response from RajaOngkir API
 */
private function parseShippingResponse($data)
{
    $options = [];
    
    try {
        // Check for standard RajaOngkir response structure
        if (isset($data['rajaongkir']['results'])) {
            // Standard RajaOngkir format
            $results = $data['rajaongkir']['results'];
            
            foreach ($results as $result) {
                if (isset($result['costs']) && is_array($result['costs'])) {
                    foreach ($result['costs'] as $cost) {
                        $options[] = [
                            'courier' => strtoupper($result['code'] ?? $result['name'] ?? 'Unknown'),
                            'courier_name' => $result['name'] ?? strtoupper($result['code'] ?? 'Unknown'),
                            'service' => $cost['service'] ?? 'Unknown',
                            'description' => $cost['description'] ?? $cost['service'] ?? 'Shipping Service',
                            'cost' => isset($cost['cost'][0]['value']) ? (int) $cost['cost'][0]['value'] : 0,
                            'formatted_cost' => 'Rp ' . number_format($cost['cost'][0]['value'] ?? 0, 0, ',', '.'),
                            'etd' => $cost['cost'][0]['etd'] ?? 'N/A',
                            'formatted_etd' => ($cost['cost'][0]['etd'] ?? 'N/A') . ' hari',
                            'recommended' => false,
                            'type' => 'api',
                            'is_mock' => false
                        ];
                    }
                }
            }
        }
        // Check for Komerce custom format
        elseif (isset($data['data']) && is_array($data['data'])) {
            // Komerce custom format
            foreach ($data['data'] as $result) {
                $options[] = [
                    'courier' => strtoupper($result['courier'] ?? 'Unknown'),
                    'courier_name' => $result['courier_name'] ?? strtoupper($result['courier'] ?? 'Unknown'),
                    'service' => $result['service'] ?? 'Unknown',
                    'description' => $result['description'] ?? $result['service'] ?? 'Shipping Service',
                    'cost' => isset($result['cost']) ? (int) $result['cost'] : 0,
                    'formatted_cost' => 'Rp ' . number_format($result['cost'] ?? 0, 0, ',', '.'),
                    'etd' => $result['etd'] ?? 'N/A',
                    'formatted_etd' => ($result['etd'] ?? 'N/A') . ' hari',
                    'recommended' => $result['recommended'] ?? false,
                    'type' => 'api',
                    'is_mock' => false
                ];
            }
        }
        // Check for direct array format
        elseif (is_array($data) && !empty($data)) {
            // Direct array format
            foreach ($data as $result) {
                if (is_array($result)) {
                    $options[] = [
                        'courier' => strtoupper($result['courier'] ?? 'Unknown'),
                        'courier_name' => $result['courier_name'] ?? strtoupper($result['courier'] ?? 'Unknown'),
                        'service' => $result['service'] ?? 'Unknown',
                        'description' => $result['description'] ?? $result['service'] ?? 'Shipping Service',
                        'cost' => isset($result['cost']) ? (int) $result['cost'] : 0,
                        'formatted_cost' => 'Rp ' . number_format($result['cost'] ?? 0, 0, ',', '.'),
                        'etd' => $result['etd'] ?? 'N/A',
                        'formatted_etd' => ($result['etd'] ?? 'N/A') . ' hari',
                        'recommended' => $result['recommended'] ?? false,
                        'type' => 'api',
                        'is_mock' => false
                    ];
                }
            }
        }
        
        // Mark first option as recommended if none is marked
        if (!empty($options)) {
            $hasRecommended = false;
            foreach ($options as $option) {
                if ($option['recommended']) {
                    $hasRecommended = true;
                    break;
                }
            }
            
            if (!$hasRecommended) {
                $options[0]['recommended'] = true;
            }
        }
        
        Log::info("📋 Parsed shipping options", [
            'total_options' => count($options),
            'options_preview' => array_map(function($opt) {
                return $opt['courier'] . ' ' . $opt['service'] . ' - Rp ' . number_format($opt['cost']);
            }, array_slice($options, 0, 3))
        ]);
        
    } catch (\Exception $e) {
        Log::error('Error parsing shipping response', [
            'error' => $e->getMessage(),
            'data_structure' => is_array($data) ? array_keys($data) : 'not_array',
            'data_sample' => is_array($data) ? json_encode(array_slice($data, 0, 2)) : substr(json_encode($data), 0, 200)
        ]);
    }
    
    return $options;
}

/**
 * Get working cost calculation endpoint (for testing)
 */
public function findWorkingCostEndpoint($originId, $destinationId, $weight = 1000)
{
    $endpoints = [
        '/cost',
        '/shipping/cost', 
        '/destination/cost',
        '/calculate',
        '/shipping/calculate'
    ];
    
    $testData = [
        'origin' => $originId,
        'destination' => $destinationId,
        'weight' => $weight,
        'courier' => 'jne'
    ];
    
    foreach ($endpoints as $endpoint) {
        try {
            $response = Http::timeout(10)->withHeaders([
                'key' => $this->apiKey
            ])->post($this->baseUrl . $endpoint, $testData);
            
            if ($response->successful()) {
                $data = $response->json();
                Log::info("✅ Working endpoint found: {$endpoint}", [
                    'status' => $response->status(),
                    'has_results' => isset($data['rajaongkir']['results'])
                ]);
                return $endpoint;
            }
            
        } catch (\Exception $e) {
            Log::warning("❌ Endpoint {$endpoint} failed: {$e->getMessage()}");
            continue;
        }
    }
    
    return null;
}

    /**
     * Find locations by search term (main method to use)
     */
    public function findLocations($searchTerm, $limit = 10)
    {
        return $this->searchDestinations($searchTerm, $limit);
    }

    /**
     * Get major cities by searching for common city names
     */
    public function getMajorCities()
    {
        $majorCityNames = [
            'jakarta', 'bandung', 'surabaya', 'medan', 'semarang', 
            'makassar', 'palembang', 'batam', 'bogor', 'depok'
        ];

        $cities = [];
        
        foreach ($majorCityNames as $cityName) {
            $results = $this->searchDestinations($cityName, 3);
            
            if (!empty($results)) {
                $cities[$cityName] = $results[0]; // Take first result as representative
            }
        }

        return $cities;
    }

    /**
     * Check API connection
     */
    public function testConnection()
    {
        try {
            $response = Http::timeout(10)->withHeaders([
                'key' => $this->apiKey
            ])->get($this->baseUrl . '/destination/province');

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'message' => $response->successful() ? 'Connection successful' : 'Connection failed',
                'data' => $response->successful() ? $response->json() : null,
                'endpoints_status' => [
                    'provinces' => $response->successful() ? 'WORKING' : 'FAILED',
                    'cities' => 'NOT AVAILABLE (404)',
                    'search' => 'WORKING',
                    'cost_calculation' => 'ENDPOINT NOT FOUND'
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'status_code' => 0,
                'message' => 'Connection error: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Get service configuration
     */
    public function getConfig()
    {
        return [
            'api_version' => 'v2',
            'base_url' => $this->baseUrl,
            'timeout' => $this->timeout,
            'cache_duration' => $this->cacheDuration,
            'api_key_configured' => !empty($this->apiKey),
            'working_endpoints' => [
                'provinces' => true,
                'search' => true,
                'cities' => false,
                'cost_calculation' => false
            ],
            'recommended_approach' => 'Use search-based location selection'
        ];
    }
    public function searchLocationAdvanced($searchTerm, $limit = 15)
{
    try {
        $searchVariations = $this->generateSearchVariations($searchTerm);
        $allResults = [];
        
        foreach ($searchVariations as $term) {
            try {
                $response = Http::timeout(15)
                    ->withHeaders(['key' => $this->apiKey])
                    ->get($this->baseUrl . '/destination/domestic-destination', [
                        'search' => $term,
                        'limit' => $limit,
                        'offset' => 0
                    ]);
                
                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['data']) && is_array($data['data'])) {
                        $allResults = array_merge($allResults, $data['data']);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('RajaOngkir search variation failed', [
                    'term' => $term,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $this->filterAndRankResults($allResults, $searchTerm);
        
    } catch (\Exception $e) {
        Log::error('searchLocationAdvanced failed', [
            'search_term' => $searchTerm,
            'error' => $e->getMessage()
        ]);
        return [];
    }
}

/**
 * Generate search variations for better location matching
 */
private function generateSearchVariations($searchTerm)
{
    $searchLower = strtolower(trim($searchTerm));
    $variations = [$searchLower];
    
    // Tambahkan variasi untuk kelurahan dengan area Jakarta
    $jakartaAreas = ['jakarta', 'jakarta selatan', 'jakarta utara', 'jakarta barat', 'jakarta timur', 'jakarta pusat'];
    foreach ($jakartaAreas as $area) {
        $variations[] = $searchLower . ' ' . $area;
    }
    
    // Tambahkan variasi untuk area umum lainnya
    $commonAreas = ['tangerang', 'bekasi', 'depok', 'bogor', 'bandung', 'surabaya', 'medan', 'makassar'];
    foreach ($commonAreas as $area) {
        $variations[] = $searchLower . ' ' . $area;
    }
    
    // Jika input mengandung koma, pisahkan dan coba setiap bagian
    if (strpos($searchLower, ',') !== false) {
        $parts = array_map('trim', explode(',', $searchLower));
        $variations = array_merge($variations, $parts);
        
        // Coba kombinasi terbalik
        if (count($parts) >= 2) {
            $variations[] = $parts[1] . ' ' . $parts[0];
        }
    }
    
    // Tambahkan variasi dengan spasi dan tanpa spasi
    if (strpos($searchLower, ' ') !== false) {
        $variations[] = str_replace(' ', '', $searchLower);
    } else {
        // Jika tidak ada spasi, coba dengan spasi di tempat yang umum
        $withSpaces = [
            preg_replace('/([a-z])([A-Z])/', '$1 $2', $searchTerm), // camelCase to spaced
            str_replace(['kota', 'kab'], ['kota ', 'kabupaten '], $searchLower)
        ];
        $variations = array_merge($variations, $withSpaces);
    }
    
    // Remove duplicates dan empty values
    $variations = array_filter(array_unique($variations), function($var) {
        return !empty(trim($var)) && strlen(trim($var)) >= 2;
    });
    
    return array_values($variations);
}

/**
 * Filter and rank search results based on relevance
 */
private function filterAndRankResults($results, $originalSearch)
{
    if (empty($results)) {
        return [];
    }
    
    $searchLower = strtolower(trim($originalSearch));
    $scored = [];
    $processedIds = []; // To track duplicates
    
    foreach ($results as $result) {
        // Skip duplicates based on ID
        if (in_array($result['id'], $processedIds)) {
            continue;
        }
        $processedIds[] = $result['id'];
        
        $score = $this->calculateRelevanceScore($result, $searchLower);
        
        if ($score > 0) {
            $scored[] = [
                'data' => $this->formatLocationResult($result),
                'score' => $score,
                'search_term' => $searchLower
            ];
        }
    }
    
    // Sort by score (descending) then by location hierarchy
    usort($scored, function($a, $b) {
        if ($a['score'] === $b['score']) {
            // If same score, prioritize by location hierarchy
            return $this->compareLocationHierarchy($a['data'], $b['data']);
        }
        return $b['score'] <=> $a['score'];
    });
    
    // Extract only the data part
    return array_map(function($item) {
        return $item['data'];
    }, $scored);
}
public function calculateMultipleCouriers($originId, $destinationId, $weight, $couriers = ['jne', 'tiki', 'pos'])
{
    $allOptions = [];
    
    foreach ($couriers as $courier) {
        try {
            $options = $this->calculateShipping($originId, $destinationId, $weight, $courier);
            $allOptions = array_merge($allOptions, $options);
        } catch (\Exception $e) {
            Log::warning("Failed to calculate shipping for courier: $courier", [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    // Sort by cost (cheapest first)
    usort($allOptions, function($a, $b) {
        return $a['cost'] <=> $b['cost'];
    });
    
    return $allOptions;
}

/**
 * Get available couriers list
 */
public function getAvailableCouriers()
{
    return [
        'jne' => 'JNE',
        'tiki' => 'TIKI',
        'pos' => 'POS Indonesia',
        'rpx' => 'RPX',
        'pandu' => 'Pandu Logistics',
        'wahana' => 'Wahana',
        'sicepat' => 'SiCepat',
        'jnt' => 'J&T Express',
        'pahala' => 'Pahala Express',
        'sap' => 'SAP Express',
        'jet' => 'JET Express',
        'rex' => 'REX Express',
        'first' => 'First Logistics',
        'ninja' => 'Ninja Express',
        'lion' => 'Lion Parcel',
        'idl' => 'IDL Cargo',
        'sentral' => 'Sentral Cargo'
    ];
}

private function validateShippingData($originId, $destinationId, $weight, $courier)
{
    $errors = [];
    
    if (empty($originId)) {
        $errors[] = 'Origin ID is required';
    }
    
    if (empty($destinationId)) {
        $errors[] = 'Destination ID is required';
    }
    
    if ($weight < 1) {
        $errors[] = 'Weight must be at least 1 gram';
    }
    
    if (empty($courier)) {
        $errors[] = 'Courier is required';
    }
    
    if (!empty($errors)) {
        throw new \InvalidArgumentException('Validation failed: ' . implode(', ', $errors));
    }
    
    return true;
}


/**
 * Calculate relevance score for a location result
 */
private function calculateRelevanceScore($result, $searchTerm)
{
    $score = 0;
    
    $subdistrict = strtolower($result['subdistrict_name'] ?? '');
    $district = strtolower($result['district_name'] ?? '');
    $city = strtolower($result['city_name'] ?? '');
    $province = strtolower($result['province_name'] ?? '');
    
    // Exact match scores (highest priority)
    if ($subdistrict === $searchTerm) {
        $score += 1000; // Kelurahan exact match
    } elseif ($district === $searchTerm) {
        $score += 800;  // Kecamatan exact match
    } elseif ($city === $searchTerm) {
        $score += 600;  // Kota exact match
    }
    
    // Starts with scores
    if (str_starts_with($subdistrict, $searchTerm)) {
        $score += 500;
    } elseif (str_starts_with($district, $searchTerm)) {
        $score += 400;
    } elseif (str_starts_with($city, $searchTerm)) {
        $score += 300;
    }
    
    // Contains scores
    if (strpos($subdistrict, $searchTerm) !== false) {
        $score += 200;
    }
    if (strpos($district, $searchTerm) !== false) {
        $score += 150;
    }
    if (strpos($city, $searchTerm) !== false) {
        $score += 100;
    }
    if (strpos($province, $searchTerm) !== false) {
        $score += 50;
    }
    
    // Word boundary matches (more accurate than simple contains)
    $searchWords = explode(' ', $searchTerm);
    foreach ($searchWords as $word) {
        if (strlen($word) >= 3) { // Only check meaningful words
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $subdistrict)) {
                $score += 300;
            }
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $district)) {
                $score += 200;
            }
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $city)) {
                $score += 100;
            }
        }
    }
    
    // Bonus for complete address match
    $fullAddress = $subdistrict . ' ' . $district . ' ' . $city . ' ' . $province;
    if (strpos(strtolower($fullAddress), $searchTerm) !== false) {
        $score += 75;
    }
    
    // Penalty for very common/generic names to prioritize specific matches
    $genericTerms = ['jakarta', 'bandung', 'surabaya', 'medan', 'bekasi', 'tangerang'];
    foreach ($genericTerms as $generic) {
        if ($subdistrict === $generic || $district === $generic) {
            $score -= 50;
        }
    }
    
    return $score;
}

/**
 * Compare location hierarchy for sorting
 */
private function compareLocationHierarchy($a, $b)
{
    // Prioritize Jakarta > other major cities > smaller cities
    $majorCities = [
        'jakarta' => 10,
        'bandung' => 8,
        'surabaya' => 8,
        'medan' => 7,
        'bekasi' => 6,
        'tangerang' => 6,
        'depok' => 5,
        'bogor' => 5
    ];
    
    $cityA = strtolower($a['city_name'] ?? '');
    $cityB = strtolower($b['city_name'] ?? '');
    
    $priorityA = 0;
    $priorityB = 0;
    
    foreach ($majorCities as $city => $priority) {
        if (strpos($cityA, $city) !== false) {
            $priorityA = $priority;
            break;
        }
    }
    
    foreach ($majorCities as $city => $priority) {
        if (strpos($cityB, $city) !== false) {
            $priorityB = $priority;
            break;
        }
    }
    
    return $priorityB <=> $priorityA; // Higher priority first
}

/**
 * Format location result for consistent output
 */
private function formatLocationResult($result)
{
    return [
        'id' => $result['id'],
        'location_id' => $result['id'], // For backward compatibility
        'subdistrict_name' => $result['subdistrict_name'] ?? '',
        'district_name' => $result['district_name'] ?? '',
        'city_name' => $result['city_name'] ?? '',
        'province_name' => $result['province_name'] ?? '',
        'zip_code' => $result['zip_code'] ?? '',
        'label' => $this->generateLocationLabel($result),
        'display_name' => $this->generateDisplayName($result),
        'full_address' => $this->generateFullAddress($result)
    ];
}

/**
 * Generate location label for display
 */
private function generateLocationLabel($result)
{
    $parts = array_filter([
        $result['subdistrict_name'] ?? '',
        $result['district_name'] ?? '',
        $result['city_name'] ?? '',
        $result['province_name'] ?? '',
        $result['zip_code'] ?? ''
    ]);
    
    return implode(', ', $parts);
}

/**
 * Generate short display name
 */
private function generateDisplayName($result)
{
    $parts = array_filter([
        $result['subdistrict_name'] ?? '',
        $result['city_name'] ?? ''
    ]);
    
    return implode(', ', $parts);
}

/**
 * Generate full address
 */
private function generateFullAddress($result)
{
    return $this->generateLocationLabel($result);
}
// ============================================
    // HIERARCHICAL LOCATION METHODS (NEW - NO CONFLICTS)
    // ============================================

    /**
     * Get all provinces for hierarchical dropdown (different from existing getProvinces)
     */
    public function getProvincesHierarchical()
    {
        $cacheKey = 'rajaongkir_provinces_hierarchical_v2';
        
        return Cache::remember($cacheKey, $this->cacheDuration, function () {
            try {
                Log::info('🏛️ Fetching provinces for hierarchical dropdown');
                
                $response = Http::timeout($this->timeout)
                    ->withHeaders(['key' => $this->apiKey])
                    ->get($this->baseUrl . '/destination/province');

                if ($response->successful()) {
                    $data = $response->json();
                    
                    if (isset($data['data']) && is_array($data['data'])) {
                        $provinces = collect($data['data'])->map(function ($province) {
                            return [
                                'id' => $province['id'],
                                'name' => $province['name'],
                                'label' => $province['name'] // For display in dropdown
                            ];
                        })->sortBy('name')->values()->toArray();

                        Log::info('✅ Hierarchical provinces fetched successfully', [
                            'count' => count($provinces)
                        ]);

                        return $provinces;
                    }
                }

                Log::warning('⚠️ Invalid response format from provinces API');
                return [];

            } catch (\Exception $e) {
                Log::error('❌ Error fetching hierarchical provinces', [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine()
                ]);
                return [];
            }
        });
    }

    /**
     * Get cities by province ID - HIERARCHICAL METHOD (different from existing)
     */
    public function getCitiesByProvinceId($provinceId)
{
    try {
        Log::info('Loading cities for province', ['province_id' => $provinceId]);
        
        $response = Http::timeout($this->timeout)
            ->withHeaders(['key' => $this->apiKey])
            ->get($this->baseUrl . "/destination/city/{$provinceId}");

        if ($response->successful()) {
            $data = $response->json();
            
            if (isset($data['data']) && is_array($data['data'])) {
                $cities = [];
                foreach ($data['data'] as $city) {
                    $cities[] = [
                        'id' => $city['id'],
                        'name' => $city['name'],
                        'label' => $city['name']
                    ];
                }
                
                Log::info('Cities loaded successfully', ['count' => count($cities)]);
                return $cities;
            }
        }
        
        return [];
        
    } catch (\Exception $e) {
        Log::error('Error loading cities', ['error' => $e->getMessage()]);
        return [];
    }
}

    /**
     * Get districts by city ID
     */
    public function getDistrictsByCityId($cityId)
{
    try {
        Log::info('Loading districts for city', ['city_id' => $cityId]);
        
        $response = Http::timeout($this->timeout)
            ->withHeaders(['key' => $this->apiKey])
            ->get($this->baseUrl . "/destination/district/{$cityId}");

        if ($response->successful()) {
            $data = $response->json();
            
            if (isset($data['data']) && is_array($data['data'])) {
                $districts = [];
                foreach ($data['data'] as $district) {
                    $districts[] = [
                        'id' => $district['id'],
                        'name' => $district['name'],
                        'label' => $district['name'],
                        // Hanya tambahkan zip_code jika ada
                        'zip_code' => isset($district['zip_code']) ? $district['zip_code'] : null
                    ];
                }
                
                Log::info('Districts loaded successfully', ['count' => count($districts)]);
                return $districts;
            }
        }
        
        return [];
        
    } catch (\Exception $e) {
        Log::error('Error loading districts', ['error' => $e->getMessage()]);
        return [];
    }
}

    /**
     * Get sub-districts by district ID
     */
    public function getSubDistrictsByDistrictId($districtId)
{
    try {
        Log::info('Loading sub-districts for district', ['district_id' => $districtId]);
        
        $response = Http::timeout($this->timeout)
            ->withHeaders(['key' => $this->apiKey])
            ->get($this->baseUrl . "/destination/sub-district/{$districtId}");

        if ($response->successful()) {
            $data = $response->json();
            
            if (isset($data['data']) && is_array($data['data'])) {
                $subDistricts = [];
                foreach ($data['data'] as $subDistrict) {
                    $subDistricts[] = [
                        'id' => $subDistrict['id'],
                        'name' => $subDistrict['name'],
                        'label' => $subDistrict['name'],
                        'zip_code' => isset($subDistrict['zip_code']) ? $subDistrict['zip_code'] : null
                    ];
                }
                
                Log::info('Sub-districts loaded successfully', ['count' => count($subDistricts)]);
                return $subDistricts;
            }
        }
        
        return [];
        
    } catch (\Exception $e) {
        Log::error('Error loading sub-districts', ['error' => $e->getMessage()]);
        return [];
    }
}

    /**
     * Test all hierarchical endpoints
     */
    public function testHierarchicalEndpoints()
    {
        try {
            $results = [
                'provinces' => ['status' => 'testing', 'count' => 0],
                'cities' => ['status' => 'testing', 'count' => 0],
                'districts' => ['status' => 'testing', 'count' => 0],
                'sub_districts' => ['status' => 'testing', 'count' => 0]
            ];

            // Test 1: Provinces
            $provinces = $this->getProvincesHierarchical();
            $results['provinces'] = [
                'status' => !empty($provinces) ? 'working' : 'failed',
                'count' => count($provinces),
                'sample' => array_slice($provinces, 0, 2)
            ];

            // Test 2: Cities (use first province)
            if (!empty($provinces)) {
                $firstProvince = $provinces[0];
                $cities = $this->getCitiesByProvinceId($firstProvince['id']);
                $results['cities'] = [
                    'status' => !empty($cities) ? 'working' : 'failed',
                    'count' => count($cities),
                    'sample' => array_slice($cities, 0, 2),
                    'tested_province' => $firstProvince['name']
                ];

                // Test 3: Districts (use first city)
                if (!empty($cities)) {
                    $firstCity = $cities[0];
                    $districts = $this->getDistrictsByCityId($firstCity['id']);
                    $results['districts'] = [
                        'status' => !empty($districts) ? 'working' : 'failed',
                        'count' => count($districts),
                        'sample' => array_slice($districts, 0, 2),
                        'tested_city' => $firstCity['name']
                    ];

                    // Test 4: Sub Districts (use first district)
                    if (!empty($districts)) {
                        $firstDistrict = $districts[0];
                        $subDistricts = $this->getSubDistrictsByDistrictId($firstDistrict['id']);
                        $results['sub_districts'] = [
                            'status' => !empty($subDistricts) ? 'working' : 'failed',
                            'count' => count($subDistricts),
                            'sample' => array_slice($subDistricts, 0, 2),
                            'tested_district' => $firstDistrict['name']
                        ];
                    }
                }
            }

            return [
                'success' => true,
                'message' => 'Hierarchical endpoints tested successfully',
                'results' => $results,
                'api_info' => [
                    'base_url' => $this->baseUrl,
                    'api_key_set' => !empty($this->apiKey),
                    'timeout' => $this->timeout,
                    'cache_duration' => $this->cacheDuration
                ],
                'method_names' => [
                    'provinces' => 'getProvincesHierarchical()',
                    'cities' => 'getCitiesByProvinceId($id)',
                    'districts' => 'getDistrictsByCityId($id)',
                    'sub_districts' => 'getSubDistrictsByDistrictId($id)'
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Hierarchical test failed: ' . $e->getMessage(),
                'results' => [],
                'error_details' => [
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                    'trace' => $e->getTraceAsString()
                ]
            ];
        }
    }
    private function filterUnwantedJNEServices($shippingOptions)
{
    $excludedServices = [
        'CTCSPS',    // City Courier Special
        'JTR<130',   // JTR Trucking under 130kg
        'JTR>130',   // JTR Trucking over 130kg  
        'JTR>200',   // JTR Trucking over 200kg
    ];
    
    if (empty($shippingOptions) || !is_array($shippingOptions)) {
        return $shippingOptions;
    }
    
    $filteredOptions = [];
    
    foreach ($shippingOptions as $option) {
        if (!is_array($option) || !isset($option['service'])) {
            continue;
        }
        
        $service = trim($option['service']);
        
        // Skip layanan yang ada dalam daftar excluded
        if (in_array($service, $excludedServices)) {
            Log::info("🚫 Filtering out JNE service: {$service}");
            continue;
        }
        
        // Skip layanan JTR dengan pattern khusus
        if ($this->isJTRService($service)) {
            Log::info("🚫 Filtering out JTR service: {$service}");
            continue;
        }
        
        $filteredOptions[] = $option;
    }
    
    Log::info("✅ JNE Service filtering completed", [
        'original_count' => count($shippingOptions),
        'filtered_count' => count($filteredOptions),
        'removed_count' => count($shippingOptions) - count($filteredOptions)
    ]);
    
    return $filteredOptions;
}

/**
 * Cek apakah service adalah JTR yang tidak diinginkan
 *
 * @param string $service
 * @return bool
 */
private function isJTRService($service)
{
    $jtrPatterns = [
        '/^JTR<\d+$/',     // JTR<130, JTR<100, dll
        '/^JTR>\d+$/',     // JTR>130, JTR>200, dll  
        '/^JTR\d+$/',      // JTR130, JTR200, dll
    ];
    
    foreach ($jtrPatterns as $pattern) {
        if (preg_match($pattern, $service)) {
            return true;
        }
    }
    
    return false;
}
    
}