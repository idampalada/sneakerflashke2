@php
    $orderNumber = $order->order_number;
@endphp

<div
    x-data="{
        loading: true,
        error: null,
        tracking: null,

        async load() {
            try {
                const res = await fetch(
                    `/admin/orders/{{ $orderNumber }}/track`,
                    { headers: { 'Accept': 'application/json' } }
                );

                if (!res.ok) throw new Error('Failed to fetch tracking');

                const json = await res.json();
                this.tracking = json.tracking;
            } catch (e) {
                this.error = e.message;
            } finally {
                this.loading = false;
            }
        }
    }"
    x-init="load()"
    class="space-y-4"
>
    <template x-if="loading">
        <div class="text-gray-500">⏳ Loading tracking data...</div>
    </template>

    <template x-if="error">
        <div class="text-red-600">❌ Failed to fetch tracking data</div>
    </template>

    <template x-if="tracking">
        <div class="space-y-2">
            <div>
                <strong>AWB:</strong>
                <span x-text="tracking.airway_bill"></span>
            </div>

            <div>
                <strong>Status:</strong>
                <span x-text="tracking.last_status"></span>
            </div>

            <hr>

            <ul class="space-y-2">
                <template x-for="item in tracking.history" :key="item.date">
                    <li class="border p-2 rounded">
                        <div class="font-semibold" x-text="item.status"></div>
                        <div x-text="item.description"></div>
                        <div class="text-xs text-gray-500" x-text="item.date"></div>
                    </li>
                </template>
            </ul>
        </div>
    </template>
</div>
