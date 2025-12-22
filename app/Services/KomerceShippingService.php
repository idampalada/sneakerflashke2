<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KomerceShippingService
{
    private $apiKey;
    private $baseUrl;
    private $timeout;

    public function __construct()
    {
        $this->apiKey = env('KOMERCE_API_KEY', 'VDiLWH4R48172606d28bde1a3dHKapOZ');
        $this->baseUrl = env('KOMERCE_BASE_URL', 'https://api-sandbox.collaborator.komerce.id');
        $this->timeout = env('KOMERCE_TIMEOUT', 25);
        
        Log::info('Komerce Shipping Service initialized', [
            'base_url' => $this->baseUrl,
            'timeout' => $this->timeout,
            'api_key_set' => !empty($this->apiKey)
        ]);
    }

    /**
     * Calculate shipping costs using new Komerce API
     * 
     * @param int $originId - shipper destination ID
     * @param int $destinationId - receiver destination ID  
     * @param float $weight - weight in kg (will be converted from grams)
     * @param int $itemValue - item value for insurance calculation
     * @param bool $cod - Cash on Delivery (true/false)
     * @return array
     */
    public function calculateShipping($originId, $destinationId, $weight, $itemValue = 0, $cod = false)
    {
        try {
            Log::info('🚢 Komerce API shipping calculation', [
                'shipper_destination_id' => $originId,
                'receiver_destination_id' => $destinationId,
                'weight' => $weight,
                'item_value' => $itemValue,
                'cod' => $cod ? 'yes' : 'no',
                'endpoint' => '/tariff/api/v1/calculate'
            ]);

            // Convert weight from grams to kg with 3 decimal places
            $weightInKg = number_format($weight / 1000, 3, '.', '');

            // Build query parameters
            $params = [
                'shipper_destination_id' => $originId,
                'receiver_destination_id' => $destinationId,
                'weight' => $weightInKg,
                'item_value' => $itemValue,
                'cod' => $cod ? 'yes' : 'no'
            ];

            $startTime = microtime(true);

            // Make API request
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'accept' => 'application/json',
                'user-agent' => 'Laravel-Komerce-Integration'
            ])
            ->timeout($this->timeout)
            ->retry(2, 1000)
            ->get($this->baseUrl . '/tariff/api/v1/calculate', $params);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('📡 Komerce API Response', [
                'status_code' => $response->status(),
                'successful' => $response->successful(),
                'execution_time_ms' => $executionTime,
                'response_size' => strlen($response->body()),
                'content_type' => $response->header('content-type')
            ]);

            // Handle API errors
            if (!$response->successful()) {
                $errorBody = $response->body();
                $statusCode = $response->status();
                
                Log::error('❌ Komerce API Error', [
                    'status_code' => $statusCode,
                    'error_response' => $errorBody,
                    'request_params' => $params
                ]);

                return [
                    'success' => false,
                    'error' => 'API_REQUEST_FAILED',
                    'message' => $this->getErrorMessage($statusCode),
                    'debug' => [
                        'api_status' => $statusCode,
                        'execution_time_ms' => $executionTime
                    ]
                ];
            }

            // Parse successful response
            $data = $response->json();
            
            Log::info('✅ Komerce API Success Response', [
                'has_data' => isset($data['data']),
                'data_count' => isset($data['data']) ? count($data['data']) : 0,
                'execution_time_ms' => $executionTime,
                'status' => $data['status'] ?? 'unknown'
            ]);

            // Validate response structure
            if (!isset($data['data']) || !is_array($data['data']) || empty($data['data'])) {
                Log::warning('❌ Komerce API returned empty or invalid data', [
                    'has_data_key' => isset($data['data']),
                    'is_array' => isset($data['data']) ? is_array($data['data']) : false,
                    'data_count' => isset($data['data']) ? count($data['data']) : 0,
                    'response_structure' => array_keys($data ?? [])
                ]);

                return [
                    'success' => false,
                    'error' => 'EMPTY_RESPONSE',
                    'message' => 'No shipping options available for this route'
                ];
            }

            // Parse shipping options
            $shippingOptions = $this->parseKomerceResponse($data);
            
            if (!empty($shippingOptions)) {
                Log::info('🎯 Komerce found ' . count($shippingOptions) . ' shipping options', [
                    'sample_options' => array_map(function($opt) {
                        return $opt['service'] . ' - Rp ' . number_format($opt['cost']);
                    }, array_slice($shippingOptions, 0, 3))
                ]);

                return [
                    'success' => true,
                    'data' => $shippingOptions,
                    'execution_time_ms' => $executionTime
                ];
            } else {
                Log::warning('❌ No shipping options parsed from Komerce response');
                
                return [
                    'success' => false,
                    'error' => 'NO_OPTIONS_PARSED',
                    'message' => 'Unable to parse shipping options'
                ];
            }

        } catch (\Exception $e) {
            Log::error('❌ Komerce shipping calculation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'origin' => $originId,
                'destination' => $destinationId
            ]);

            return [
                'success' => false,
                'error' => 'CALCULATION_ERROR',
                'message' => 'Unable to calculate shipping costs'
            ];
        }
    }

    /**
     * Parse Komerce API response to standardized format
     */
    private function parseKomerceResponse($data)
    {
        $options = [];
        
        try {
            if (isset($data['data']) && is_array($data['data'])) {
                foreach ($data['data'] as $option) {
                    // Extract service information
                    $courierCode = strtoupper($option['courier_code'] ?? 'UNK');
                    $courierName = $option['courier_name'] ?? 'Unknown Courier';
                    $serviceCode = strtoupper($option['service_code'] ?? 'REG');
                    $serviceName = $option['service_name'] ?? $serviceCode;
                    $cost = (int) ($option['cost'] ?? $option['tariff'] ?? 0);
                    $etd = $option['etd'] ?? $option['estimated_delivery'] ?? 'N/A';
                    
                    // Build standardized option
                    $shippingOption = [
                        'courier' => $courierCode,
                        'courier_name' => $courierName,
                        'service' => $serviceCode,
                        'service_name' => $serviceName,
                        'description' => $serviceName,
                        'cost' => $cost,
                        'formatted_cost' => 'Rp ' . number_format($cost, 0, ',', '.'),
                        'etd' => $etd,
                        'formatted_etd' => $this->formatETD($etd),
                        'recommended' => $option['recommended'] ?? false,
                        'type' => 'komerce_api',
                        'is_mock' => false,
                        // Additional Komerce-specific data
                        'original_data' => $option
                    ];

                    $options[] = $shippingOption;
                }
            }
            
            // Apply filtering for JNE services (maintain compatibility)
            $options = $this->filterUnwantedServices($options);
            
            // Set first option as recommended if none is marked
            if (!empty($options)) {
                $hasRecommended = false;
                foreach ($options as &$option) {
                    if ($option['recommended']) {
                        $hasRecommended = true;
                        break;
                    }
                }
                
                if (!$hasRecommended && isset($options[0])) {
                    $options[0]['recommended'] = true;
                }
            }
            
            // Sort options: recommended first, then by cost
            usort($options, function($a, $b) {
                // Recommended service first
                if ($a['recommended'] && !$b['recommended']) return -1;
                if ($b['recommended'] && !$a['recommended']) return 1;
                
                // Then sort by cost
                return $a['cost'] <=> $b['cost'];
            });
            
            Log::info('📋 Parsed Komerce shipping options', [
                'total_options' => count($options),
                'options_preview' => array_map(function($opt) {
                    return $opt['courier'] . ' ' . $opt['service'] . ' - Rp ' . number_format($opt['cost']);
                }, array_slice($options, 0, 3))
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error parsing Komerce response', [
                'error' => $e->getMessage(),
                'data_structure' => is_array($data) ? array_keys($data) : 'not_array',
                'data_sample' => is_array($data) ? json_encode(array_slice($data, 0, 2)) : substr(json_encode($data), 0, 200)
            ]);
        }
        
        return $options;
    }

    /**
     * Filter unwanted shipping services (for compatibility with existing filtering)
     */
    private function filterUnwantedServices($options)
    {
        $unwantedServices = [
            'CTCSPS',     // JNE CTCSPS
            'JTR<130',    // JNE JTR<130
            'JTR>130',    // JNE JTR>130  
            'JTR>200',    // JNE JTR>200
        ];
        
        $filtered = [];
        foreach ($options as $option) {
            $serviceCode = $option['service'] ?? '';
            
            if (!in_array($serviceCode, $unwantedServices)) {
                $filtered[] = $option;
            } else {
                Log::info('🚫 Checkout filtering out service: ' . $option['courier'] . ' ' . $serviceCode);
            }
        }
        
        return $filtered;
    }

    /**
     * Format ETD for display
     */
    private function formatETD($etd)
    {
        if (empty($etd) || $etd === 'N/A') {
            return 'N/A';
        }
        
        // If already contains 'day' or 'hari', return as is
        if (stripos($etd, 'day') !== false || stripos($etd, 'hari') !== false) {
            return $etd;
        }
        
        // Add 'day' suffix
        return $etd . ' day';
    }

    /**
     * Get error message based on status code
     */
    private function getErrorMessage($statusCode)
    {
        switch ($statusCode) {
            case 400:
                return 'Invalid request parameters';
            case 401:
                return 'Invalid API key';
            case 403:
                return 'Access forbidden';
            case 404:
                return 'Endpoint not found';
            case 429:
                return 'Too many requests';
            case 500:
                return 'Server error';
            default:
                return 'Unknown error occurred';
        }
    }

    /**
     * Test API connection
     */
    public function testConnection($originId = 17485, $destinationId = 17551, $weight = 2500)
    {
        Log::info('🧪 Testing Komerce API connection');
        
        $result = $this->calculateShipping($originId, $destinationId, $weight, 70000, false);
        
        return [
            'connection_test' => $result['success'] ?? false,
            'api_response' => $result,
            'config' => [
                'base_url' => $this->baseUrl,
                'api_key_set' => !empty($this->apiKey),
                'timeout' => $this->timeout
            ]
        ];
    }
}