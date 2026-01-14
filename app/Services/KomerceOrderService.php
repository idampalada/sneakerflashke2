<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class KomerceOrderService
{
    private $apiKey;
    private $baseUrl;
    private $timeout;
    private $shipperDestinationId;

    public function __construct()
    {
        $this->apiKey = env('KOMERCE_API_KEY', 'VDiLWH4R48172606d28bde1a3dHKapOZ');
        $this->baseUrl = env('KOMERCE_BASE_URL', 'https://api-sandbox.collaborator.komerce.id');
        $this->timeout = env('KOMERCE_TIMEOUT', 30);
        $this->shipperDestinationId = env('KOMERCE_SHIPPER_DESTINATION_ID', '17485');
        
        Log::info('Komerce Order Service initialized', [
            'base_url' => $this->baseUrl,
            'timeout' => $this->timeout,
            'shipper_destination_id' => $this->shipperDestinationId,
            'api_key_set' => !empty($this->apiKey)
        ]);
    }

    /**
     * ✅ FIXED: Generate and get shipping label
     * 
     * @param string $orderNo
     * @param string $pageSize (page_2, page_4, etc.)
     * @return array
     */
    public function printLabel($orderNo, $pageSize = 'page_2')
    {
        try {
            Log::info('🏷️ Komerce: Generating shipping label', [
                'order_no' => $orderNo,
                'page_size' => $pageSize
            ]);

            $startTime = microtime(true);

            // Use asForm() for form data instead of JSON
            $response = Http::asForm()->withHeaders([
                'x-api-key' => $this->apiKey
            ])
            ->timeout($this->timeout)
            ->post($this->baseUrl . '/order/api/v1/orders/print-label?order_no=' . $orderNo, [
                'page' => $pageSize
            ]);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('📡 Komerce Print Label Response', [
                'status_code' => $response->status(),
                'successful' => $response->successful(),
                'content_type' => $response->header('content-type'),
                'response_size' => strlen($response->body()),
                'execution_time_ms' => $executionTime,
                'request_data' => [
                    'order_no' => $orderNo,
                    'page' => $pageSize,
                    'request_type' => 'form_data'
                ]
            ]);

            if (!$response->successful()) {
                Log::error('❌ Komerce Print Label Failed', [
                    'status_code' => $response->status(),
                    'error_response' => $response->body(),
                    'order_no' => $orderNo,
                    'request_data' => [
                        'page' => $pageSize,
                        'order_no' => $orderNo
                    ]
                ]);

                return [
                    'success' => false,
                    'error' => 'LABEL_GENERATION_FAILED',
                    'message' => $this->getErrorMessage($response->status())
                ];
            }

            $responseBody = $response->body();
            $contentType = $response->header('content-type');

            // Check if response is JSON
            if (strpos($contentType, 'application/json') !== false) {
                try {
                    $data = $response->json();
                    
                    // 📋 DETAILED LOGGING LIKE TRACKING
                    Log::info('📋 Komerce Label Response Data', [
                        'full_response' => $data,
                        'has_data' => isset($data['data']),
                        'has_meta' => isset($data['meta']),
                        'meta_status' => $data['meta']['status'] ?? 'unknown',
                        'meta_message' => $data['meta']['message'] ?? 'no message',
                        'data_keys' => isset($data['data']) ? array_keys($data['data']) : [],
                        'order_no' => $orderNo
                    ]);

                    // Check if it's success response with label info
                    if (isset($data['meta']['status']) && $data['meta']['status'] === 'success') {
                        $labelData = $data['data'] ?? [];
                        
                        // Extract available fields for analysis
                        $availableFields = array_keys($labelData);
                        
                        Log::info('✅ Komerce Label Generation Success', [
                            'order_no' => $orderNo,
                            'available_fields' => $availableFields,
                            'has_download_url' => isset($labelData['download_url']),
                            'has_base_64' => isset($labelData['base_64']),
                            'has_label_url' => isset($labelData['label_url']),
                            'has_path' => isset($labelData['path']),
                            'has_filename' => isset($labelData['filename']),
                            'download_url' => $labelData['download_url'] ?? null,
                            'path' => $labelData['path'] ?? null,
                            'filename' => $labelData['filename'] ?? null,
                            'execution_time_ms' => $executionTime
                        ]);

                        return [
                            'success' => true,
                            'data' => array_merge($labelData, [
                                'order_no' => $orderNo,
                                'generated_at' => now()->toISOString()
                            ]),
                            'execution_time_ms' => $executionTime
                        ];
                    } else {
                        Log::error('❌ Komerce Label Generation Failed', [
                            'order_no' => $orderNo,
                            'meta_status' => $data['meta']['status'] ?? 'unknown',
                            'meta_message' => $data['meta']['message'] ?? 'Unknown error',
                            'full_response' => $data,
                            'execution_time_ms' => $executionTime
                        ]);

                        return [
                            'success' => false,
                            'error' => 'LABEL_GENERATION_FAILED',
                            'message' => $data['meta']['message'] ?? 'Unknown error in JSON response',
                            'data' => $data
                        ];
                    }
                } catch (\Exception $jsonError) {
                    Log::error('❌ Failed to parse JSON response', [
                        'json_error' => $jsonError->getMessage(),
                        'raw_response' => substr($responseBody, 0, 500), // First 500 chars for preview
                        'content_type' => $contentType,
                        'order_no' => $orderNo
                    ]);
                }
            } else {
                // Non-JSON response (could be PDF binary)
                Log::info('📄 Komerce Non-JSON Response', [
                    'content_type' => $contentType,
                    'response_size' => strlen($responseBody),
                    'is_pdf' => strpos($contentType, 'application/pdf') !== false,
                    'response_preview' => substr($responseBody, 0, 100), // First 100 bytes
                    'order_no' => $orderNo
                ]);
            }

            // Fallback: assume success but unknown format
            Log::warning('⚠️ Unknown response format, treating as success', [
                'content_type' => $contentType,
                'response_size' => strlen($responseBody),
                'response_preview' => substr($responseBody, 0, 200),
                'order_no' => $orderNo
            ]);

            return [
                'success' => true,
                'data' => [
                    'raw_response' => $responseBody,
                    'content_type' => $contentType,
                    'order_no' => $orderNo,
                    'generated_at' => now()->toISOString(),
                    'note' => 'Unknown response format - check raw_response'
                ],
                'execution_time_ms' => $executionTime
            ];

        } catch (\Exception $e) {
            Log::error('❌ Komerce Label Generation Error', [
                'error' => $e->getMessage(),
                'order_no' => $orderNo,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'LABEL_GENERATION_ERROR',
                'message' => 'Failed to generate label: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create order in Komerce system
     */
    public function createOrder($orderData)
    {
        try {
            Log::info('📦 Komerce: Creating order', [
                'shipper_destination_id' => $orderData['shipper_destination_id'] ?? null,
                'receiver_destination_id' => $orderData['receiver_destination_id'] ?? null,
                'grand_total' => $orderData['grand_total'] ?? 0,
                'items_count' => count($orderData['order_details'] ?? [])
            ]);

            $this->validateOrderData($orderData);
            $orderData = $this->setOrderDefaults($orderData);

            $startTime = microtime(true);

            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])
            ->timeout($this->timeout)
            ->retry(2, 1000)
            ->post($this->baseUrl . '/order/api/v1/orders/store', $orderData);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            if (!$response->successful()) {
                Log::error('❌ Komerce Order Creation Failed', [
                    'status_code' => $response->status(),
                    'error_response' => $response->body()
                ]);

                return [
                    'success' => false,
                    'error' => 'ORDER_CREATION_FAILED',
                    'message' => $this->getErrorMessage($response->status())
                ];
            }

            $data = $response->json();
            
            Log::info('✅ Komerce Order Created Successfully', [
                'order_no' => $data['data']['order_no'] ?? 'unknown',
                'status' => $data['data']['status'] ?? 'unknown',
                'execution_time_ms' => $executionTime
            ]);

            return [
                'success' => true,
                'data' => $data['data'],
                'execution_time_ms' => $executionTime
            ];

        } catch (\Exception $e) {
            Log::error('❌ Komerce Order Creation Error', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'ORDER_CREATION_ERROR',
                'message' => 'Failed to create order: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Request pickup for orders
     */
    public function requestPickup($pickupDate, $pickupTime, $orderNumbers, $pickupVehicle = 'Motor')
    {
        try {
            if (is_string($orderNumbers)) {
                $orderNumbers = [$orderNumbers];
            }

            Log::info('🚚 Komerce: Requesting pickup DEBUG', [
                'pickup_date' => $pickupDate,
                'pickup_time' => $pickupTime,
                'pickup_vehicle' => $pickupVehicle,
                'order_numbers' => $orderNumbers,
                'orders_count' => count($orderNumbers)
            ]);

            $orders = array_map(function($orderNo) {
                return ['order_no' => $orderNo];
            }, $orderNumbers);

            $payload = [
                'pickup_vehicle' => $pickupVehicle,
                'pickup_time' => $pickupTime,
                'pickup_date' => $pickupDate,
                'orders' => $orders
            ];

            Log::info('📡 Komerce Pickup Payload', [
                'url' => $this->baseUrl . '/order/api/v1/pickup/request',
                'method' => 'POST',
                'headers' => [
                    'x-api-key' => $this->apiKey,
                    'Content-Type' => 'application/json'
                ],
                'payload' => $payload
            ]);

            $startTime = microtime(true);

            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])
            ->timeout($this->timeout)
            ->post($this->baseUrl . '/order/api/v1/pickup/request', $payload);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('📡 Komerce Pickup Full Response', [
                'status_code' => $response->status(),
                'response_headers' => $response->headers(),
                'raw_response' => $response->body(),
                'json_response' => $response->json(),
                'execution_time_ms' => $executionTime
            ]);

            if (!$response->successful()) {
                Log::error('❌ Komerce Pickup Request Failed', [
                    'status_code' => $response->status(),
                    'error_response' => $response->body()
                ]);

                return [
                    'success' => false,
                    'error' => 'PICKUP_REQUEST_FAILED',
                    'message' => $this->getErrorMessage($response->status())
                ];
            }

            $data = $response->json();

            Log::info('📊 Komerce Response Structure Analysis', [
                'has_meta' => isset($data['meta']),
                'has_data' => isset($data['data']),
                'meta_content' => $data['meta'] ?? null,
                'data_content' => $data['data'] ?? null,
                'data_type' => gettype($data['data'] ?? null),
                'data_count' => is_array($data['data'] ?? null) ? count($data['data']) : 0
            ]);

            if (isset($data['data']) && is_array($data['data']) && count($data['data']) > 0) {
                $firstOrder = $data['data'][0];
                
                Log::info('📋 Komerce Response Parsing', [
                    'response_data_type' => 'array',
                    'first_order' => $firstOrder,
                    'extracted_status' => $firstOrder['status'] ?? 'unknown',
                    'extracted_awb' => $firstOrder['awb'] ?? null,
                    'extracted_order_no' => $firstOrder['order_no'] ?? null
                ]);

                return [
                    'success' => true,
                    'data' => [
                        'status' => $firstOrder['status'] ?? 'success',
                        'awb' => $firstOrder['awb'] ?? null,
                        'airway_bill' => $firstOrder['awb'] ?? null,
                        'order_no' => $firstOrder['order_no'] ?? null,
                        'pickup_date' => $pickupDate,
                        'pickup_time' => $pickupTime,
                        'all_orders' => $data['data']
                    ],
                    'execution_time_ms' => $executionTime
                ];
            }

            return [
                'success' => true,
                'data' => $data,
                'execution_time_ms' => $executionTime
            ];

        } catch (\Exception $e) {
            Log::error('❌ Komerce Pickup Request Error', [
                'error' => $e->getMessage(),
                'pickup_date' => $pickupDate,
                'order_numbers' => $orderNumbers ?? []
            ]);

            return [
                'success' => false,
                'error' => 'PICKUP_REQUEST_ERROR',
                'message' => 'Failed to request pickup: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Track shipment using airway bill
     */
    public function trackShipment($airwayBill, $shipping = 'JNE')
    {
        try {
            Log::info('📍 Komerce: Tracking shipment', [
                'airway_bill' => $airwayBill,
                'shipping' => $shipping
            ]);

            $params = [
                'shipping' => $shipping,
                'airway_bill' => $airwayBill
            ];

            $startTime = microtime(true);

            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Accept' => 'application/json'
            ])
            ->timeout($this->timeout)
            ->get($this->baseUrl . '/order/api/v1/orders/history-airway-bill', $params);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('📡 Komerce Track Shipment Response', [
                'status_code' => $response->status(),
                'successful' => $response->successful(),
                'execution_time_ms' => $executionTime
            ]);

            if (!$response->successful()) {
                Log::error('❌ Komerce Shipment Tracking Failed', [
                    'status_code' => $response->status(),
                    'error_response' => $response->body(),
                    'airway_bill' => $airwayBill,
                    'shipping' => $shipping
                ]);

                return [
                    'success' => false,
                    'error' => 'TRACKING_FAILED',
                    'message' => $this->getErrorMessage($response->status())
                ];
            }

            $data = $response->json();
            
            // Log full response for debugging
            Log::info('📋 Komerce Track Response Data', [
                'full_response' => $data,
                'has_data' => isset($data['data']),
                'has_meta' => isset($data['meta'])
            ]);

            // Check if successful response
            if (isset($data['meta']['status']) && $data['meta']['status'] === 'success') {
                $trackingData = $data['data'] ?? [];
                
                // Extract tracking information
                $currentStatus = $trackingData['last_status'] ?? 'Unknown';
                $history = $trackingData['history'] ?? [];
                $airwayBillFromResponse = $trackingData['airway_bill'] ?? $airwayBill;
                
                Log::info('✅ Komerce Shipment Tracking Retrieved', [
                    'airway_bill' => $airwayBillFromResponse,
                    'tracking_count' => count($history),
                    'current_status' => $currentStatus,
                    'execution_time_ms' => $executionTime
                ]);

                return [
                    'success' => true,
                    'data' => [
                        'status' => $currentStatus,
                        'last_status' => $currentStatus,
                        'airway_bill' => $airwayBillFromResponse,
                        'history' => array_map(function($item) {
                            return [
                                'description' => $item['desc'] ?? '',
                                'date' => $item['date'] ?? '',
                                'status' => $item['status'] ?? '',
                                'code' => $item['code'] ?? ''
                            ];
                        }, $history)
                    ],
                    'execution_time_ms' => $executionTime
                ];
            } else {
                Log::warning('⚠️ Komerce tracking response not successful', [
                    'meta' => $data['meta'] ?? null,
                    'airway_bill' => $airwayBill
                ]);

                return [
                    'success' => false,
                    'error' => 'TRACKING_RESPONSE_ERROR',
                    'message' => $data['meta']['message'] ?? 'Unknown tracking error',
                    'data' => $data
                ];
            }

        } catch (\Exception $e) {
            Log::error('❌ Komerce Shipment Tracking Error', [
                'error' => $e->getMessage(),
                'airway_bill' => $airwayBill,
                'shipping' => $shipping,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'TRACKING_ERROR',
                'message' => 'Failed to track shipment: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Helper: Check if string is valid JSON
     */
    private function isJsonString($string)
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Validate order data before sending to API
     */
    private function validateOrderData($orderData)
    {
        $required = [
            'brand_name', 'shipper_name', 'shipper_phone', 'shipper_email',
            'shipper_destination_id', 'shipper_address',
            'receiver_name', 'receiver_phone', 'receiver_destination_id', 'receiver_address',
            'shipping', 'shipping_type', 'shipping_cost',
            'grand_total', 'order_details'
        ];

        foreach ($required as $field) {
            if (!isset($orderData[$field]) || empty($orderData[$field])) {
                throw new \Exception("Required field missing: {$field}");
            }
        }

        if (empty($orderData['order_details']) || !is_array($orderData['order_details'])) {
            throw new \Exception("Order details must be a non-empty array");
        }
    }

    /**
     * Set default values for order data
     */
    private function setOrderDefaults($orderData)
    {
        $defaults = [
            'order_date' => now()->format('Y-m-d H:i:s'),
            'shipping_cashback' => 0,
            'service_fee' => 0,
            'additional_cost' => 0,
            'cod_value' => 0,
            'insurance_value' => 0,
            'payment_method' => 'BANK TRANSFER'
        ];

        return array_merge($defaults, $orderData);
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
            case 422:
                return 'Validation error';
            case 429:
                return 'Too many requests';
            case 500:
                return 'Server error';
            default:
                return 'Unknown error occurred';
        }
    }
}