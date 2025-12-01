<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Order Print</title>
    <style>
        body { font-family: sans-serif; }
        .header { text-align: center; margin-bottom: 20px; }
        .logo-container { 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            margin-bottom: 10px;
            width: 100%;
        }
        .logo-container img { 
            height: 80px;
            object-fit: contain;
        }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #000; padding: 8px; }
        .no-border { border: none; }
    </style>
</head>
<body>
<div class="header">
    <div class="logo-container">
        <img src="{{ public_path('images/logo-sneakerflash3.jpg') }}" alt="Logo">
    </div>
    <h2>ORDER SUMMARY</h2>
    <p>Order No: <strong>{{ $order->order_number }}</strong></p>
</div>


    <table>
        <tr><th>Nama</th><td>{{ $order->customer_name }}</td></tr>
        <tr><th>No Telepon</th><td>{{ $order->customer_phone ?? '-' }}</td></tr>
        <tr><th>Alamat</th><td>{{ $order->shipping_address ? (is_array($order->shipping_address) ? implode(', ', $order->shipping_address) : $order->shipping_address) : '-' }}</td></tr>
        <tr>
  <th>Shipping</th>
  <td>
    @php
        $shipping = 'N/A';
        if (!empty($order->notes)) {
            if (preg_match('/Shipping:\s*(\w+)\s+(\w+)/i', $order->notes, $matches)) {
                $shipping = $matches[1] . ' - ' . $matches[2];
            }
        }
    @endphp
    {{ $shipping }}
  </td>
</tr>

    </table>

    <h4 style="margin-top: 20px;">Item Pesanan</h4>
    <table>
        <thead>
            <tr>
                <th>Produk</th>
                <th>SKU</th>
                <th>Qty</th>
                <th>Harga</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->orderItems as $item)
            <tr>
                <td>
    {{ $item->product_name }}
    @if ($item->product && $item->product->available_sizes)
        - Size {{
            is_array($item->product->available_sizes)
                ? $item->product->available_sizes[0]
                : (json_decode($item->product->available_sizes, true)[0] ?? '')
        }}
    @endif
</td>

                <td>{{ $item->product_sku ?? 'N/A' }}</td>
                <td>{{ $item->quantity }}</td>
                <td>Rp {{ number_format($item->product_price, 0, ',', '.') }}</td>
                <td>Rp {{ number_format($item->total_price, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

</body>
</html>