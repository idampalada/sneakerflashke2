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

    public function __construct()
    {
        $this->apiKey = env('KOMERCE_API_KEY', 'VDiLWH4R48172606d28bde1a3dHKapOZ');
        $this->baseUrl = env('KOMERCE_BASE_URL', 'https://api-sandbox.collaborator.komerce.id');
        $this->timeout = env('KOMERCE_TIMEOUT', 30);
        
        Log::info('Komerce Order Service initialized', [
            'base_url' => $this->baseUrl,
            'timeout' => $this->timeout,
            'api_key_set' => !empty($this->apiKey)
        ]);
    }

    /**
     * Create order in Komerce system
     * 
     * @param array $orderData
     * @return array
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

            // Validate required fields
            $this->validateOrderData($orderData);

            // Set default values
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

            Log::info('📡 Komerce Order API Response', [
                'status_code' => $response->status(),
                'successful' => $response->successful(),
                'execution_time_ms' => $executionTime,
                'response_size' => strlen($response->body())
            ]);

            if (!$response->successful()) {
                $errorBody = $response->body();
                $statusCode = $response->status();
                
                Log::error('❌ Komerce Order Creation Failed', [
                    'status_code' => $statusCode,
                    'error_response' => $errorBody,
                    'order_data' => $orderData
                ]);

                return [
                    'success' => false,
                    'error' => 'ORDER_CREATION_FAILED',
                    'message' => $this->getErrorMessage($statusCode),
                    'debug' => [
                        'api_status' => $statusCode,
                        'api_response' => $errorBody,
                        'execution_time_ms' => $executionTime
                    ]
                ];
            }

            $data = $response->json();
            
            Log::info('✅ Komerce Order Created Successfully', [
                'order_no' => $data['order_no'] ?? 'unknown',
                'status' => $data['status'] ?? 'unknown',
                'execution_time_ms' => $executionTime
            ]);

            return [
                'success' => true,
                'data' => $data,
                'execution_time_ms' => $executionTime
            ];

        } catch (\Exception $e) {
            Log::error('❌ Komerce Order Creation Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'order_data' => $orderData ?? null
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
     * 
     * @param string $pickupDate (Y-m-d format)
     * @param string $pickupTime (H:i format)
     * @param array $orderNumbers
     * @param string $pickupVehicle (Motor/Mobil)
     * @return array
     */
    public function requestPickup($pickupDate, $pickupTime, $orderNumbers, $pickupVehicle = 'Motor')
    {
        try {
            Log::info('🚚 Komerce: Requesting pickup', [
                'pickup_date' => $pickupDate,
                'pickup_time' => $pickupTime,
                'pickup_vehicle' => $pickupVehicle,
                'orders_count' => count($orderNumbers)
            ]);

            $pickupData = [
                'pickup_vehicle' => $pickupVehicle,
                'pickup_time' => $pickupTime,
                'pickup_date' => $pickupDate,
                'orders' => array_map(function($orderNo) {
                    return ['order_no' => $orderNo];
                }, $orderNumbers)
            ];

            $startTime = microtime(true);

            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])
            ->timeout($this->timeout)
            ->post($this->baseUrl . '/order/api/v1/pickup/request', $pickupData);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('📡 Komerce Pickup Request Response', [
                'status_code' => $response->status(),
                'successful' => $response->successful(),
                'execution_time_ms' => $executionTime
            ]);

            if (!$response->successful()) {
                $errorBody = $response->body();
                Log::error('❌ Komerce Pickup Request Failed', [
                    'status_code' => $response->status(),
                    'error_response' => $errorBody,
                    'pickup_data' => $pickupData
                ]);

                return [
                    'success' => false,
                    'error' => 'PICKUP_REQUEST_FAILED',
                    'message' => $this->getErrorMessage($response->status())
                ];
            }

            $data = $response->json();
            
            Log::info('✅ Komerce Pickup Requested Successfully', [
                'pickup_id' => $data['pickup_id'] ?? 'unknown',
                'status' => $data['status'] ?? 'unknown',
                'execution_time_ms' => $executionTime
            ]);

            return [
                'success' => true,
                'data' => $data,
                'execution_time_ms' => $executionTime
            ];

        } catch (\Exception $e) {
            Log::error('❌ Komerce Pickup Request Error', [
                'error' => $e->getMessage(),
                'pickup_data' => $pickupData ?? null
            ]);

            return [
                'success' => false,
                'error' => 'PICKUP_REQUEST_ERROR',
                'message' => 'Failed to request pickup: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cancel order
     * 
     * @param string $orderNo
     * @return array
     */
    public function cancelOrder($orderNo)
    {
        try {
            Log::info('❌ Komerce: Canceling order', [
                'order_no' => $orderNo
            ]);

            $cancelData = [
                'order_no' => $orderNo
            ];

            $startTime = microtime(true);

            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])
            ->timeout($this->timeout)
            ->put($this->baseUrl . '/order/api/v1/orders/cancel', $cancelData);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('📡 Komerce Cancel Order Response', [
                'status_code' => $response->status(),
                'successful' => $response->successful(),
                'execution_time_ms' => $executionTime
            ]);

            if (!$response->successful()) {
                Log::error('❌ Komerce Order Cancellation Failed', [
                    'status_code' => $response->status(),
                    'error_response' => $response->body(),
                    'order_no' => $orderNo
                ]);

                return [
                    'success' => false,
                    'error' => 'ORDER_CANCELLATION_FAILED',
                    'message' => $this->getErrorMessage($response->status())
                ];
            }

            $data = $response->json();
            
            Log::info('✅ Komerce Order Canceled Successfully', [
                'order_no' => $orderNo,
                'status' => $data['status'] ?? 'canceled',
                'execution_time_ms' => $executionTime
            ]);

            return [
                'success' => true,
                'data' => $data,
                'execution_time_ms' => $executionTime
            ];

        } catch (\Exception $e) {
            Log::error('❌ Komerce Order Cancellation Error', [
                'error' => $e->getMessage(),
                'order_no' => $orderNo
            ]);

            return [
                'success' => false,
                'error' => 'ORDER_CANCELLATION_ERROR',
                'message' => 'Failed to cancel order: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate and get shipping label
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

            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Accept' => 'application/pdf'
            ])
            ->timeout($this->timeout)
            ->post($this->baseUrl . '/order/api/v1/orders/print-label', [
                'order_no' => $orderNo,
                'page' => $pageSize
            ]);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('📡 Komerce Print Label Response', [
                'status_code' => $response->status(),
                'successful' => $response->successful(),
                'content_type' => $response->header('content-type'),
                'response_size' => strlen($response->body()),
                'execution_time_ms' => $executionTime
            ]);

            if (!$response->successful()) {
                Log::error('❌ Komerce Label Generation Failed', [
                    'status_code' => $response->status(),
                    'error_response' => $response->body(),
                    'order_no' => $orderNo
                ]);

                return [
                    'success' => false,
                    'error' => 'LABEL_GENERATION_FAILED',
                    'message' => $this->getErrorMessage($response->status())
                ];
            }

            // Check if response is PDF
            $contentType = $response->header('content-type');
            if (strpos($contentType, 'application/pdf') !== false) {
                Log::info('✅ Komerce Label Generated Successfully', [
                    'order_no' => $orderNo,
                    'pdf_size' => strlen($response->body()),
                    'execution_time_ms' => $executionTime
                ]);

                return [
                    'success' => true,
                    'data' => [
                        'pdf_content' => base64_encode($response->body()),
                        'content_type' => $contentType,
                        'filename' => 'label_' . $orderNo . '.pdf'
                    ],
                    'execution_time_ms' => $executionTime
                ];
            } else {
                // Response might be JSON with error or success info
                $data = $response->json();
                
                return [
                    'success' => true,
                    'data' => $data,
                    'execution_time_ms' => $executionTime
                ];
            }

        } catch (\Exception $e) {
            Log::error('❌ Komerce Label Generation Error', [
                'error' => $e->getMessage(),
                'order_no' => $orderNo
            ]);

            return [
                'success' => false,
                'error' => 'LABEL_GENERATION_ERROR',
                'message' => 'Failed to generate label: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Track shipment using airway bill
     * 
     * @param string $airwayBill
     * @param string $shipping (JNE, TIKI, POS, etc.)
     * @return array
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
            
            Log::info('✅ Komerce Shipment Tracking Retrieved', [
                'airway_bill' => $airwayBill,
                'tracking_count' => count($data['tracking'] ?? []),
                'current_status' => $data['current_status'] ?? 'unknown',
                'execution_time_ms' => $executionTime
            ]);

            return [
                'success' => true,
                'data' => $data,
                'execution_time_ms' => $executionTime
            ];

        } catch (\Exception $e) {
            Log::error('❌ Komerce Shipment Tracking Error', [
                'error' => $e->getMessage(),
                'airway_bill' => $airwayBill
            ]);

            return [
                'success' => false,
                'error' => 'TRACKING_ERROR',
                'message' => 'Failed to track shipment: ' . $e->getMessage()
            ];
        }
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