<?php
// app/Console/Commands/FetchGineeWebhooks.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Frontend\GineeWebhookController;

class FetchGineeWebhooks extends Command
{
    protected $signature = 'ginee:fetch-webhooks';
    protected $description = 'Fetch webhooks from webhook.meltedcloud.cloud';

    public function handle()
    {
        $this->info('Fetching Ginee webhooks...');
        
        try {
            $response = Http::get('https://webhook.meltedcloud.cloud/api/recent-webhooks');
            
            if ($response->successful()) {
                $webhooks = $response->json();
                $this->info('Found ' . count($webhooks) . ' webhooks');
                
                $controller = new GineeWebhookController();
                
                foreach ($webhooks as $webhook) {
                    if (isset($webhook['entity']) && $webhook['entity'] === 'order') {
                        // Proses order dengan controller yang sudah dibuat
                        $this->info('Processing order: ' . ($webhook['payload']['orderId'] ?? 'unknown'));
                        $controller->handleOrderUpdate($webhook);
                    }
                }
                
                return 0;
            }
            
            $this->error('Failed to fetch webhooks');
            return 1;
            
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            Log::error('Webhook fetch error: ' . $e->getMessage());
            return 1;
        }
    }
}