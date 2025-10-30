<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ShoppingCart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function index()
    {
        // Sync cart on page load if user is authenticated
        if (Auth::check()) {
            $this->syncCartOnPageLoad();
        }
        
        $cartItems = $this->getCartItems();
        $total = $this->calculateTotal($cartItems);
        
        return view('frontend.cart.index', compact('cartItems', 'total'));
    }

public function add(Request $request, $productId = null)
{
    try {
        // TAMBAHKAN KODE INI DI AWAL METODE
        // Check if user is authenticated for AJAX requests
        if (!Auth::check() && $request->ajax()) {
            // For AJAX requests, return a JSON response with redirect URL
            return response()->json([
                'success' => false,
                'redirect' => route('login'),
                'message' => 'Please login to add items to cart'
            ], 401);
        } else if (!Auth::check()) {
            // For non-AJAX requests, redirect to login
            return redirect()->route('login');
        }
        
        // Get product ID
        if (!$productId) {
            $productId = $request->input('product_id');
        }

        if (!$productId) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product ID is required.'
                ], 400);
            }
            return back()->with('error', 'Product ID is required.');
        }

        // Validate request
        $request->validate([
            'quantity' => 'nullable|integer|min:1|max:10',
            'size' => 'nullable|string|max:50'
        ]);
        
        $quantity = $request->quantity ?? 1;
        $selectedSize = $request->size;
        
        // Get product
        $product = Product::find($productId);
        
        if (!$product || !$product->is_active) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found or not available.'
                ], 404);
            }
            return back()->with('error', 'Product not found or not available.');
        }
        
        // Check stock
        $currentStock = $product->stock_quantity ?? 0;
        if ($currentStock < $quantity) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient stock. Available: ' . $currentStock
                ], 400);
            }
            return back()->with('error', 'Insufficient stock. Available: ' . $currentStock);
        }
        
        // Calculate current quantity in cart for this product
        $cart = Session::get('cart', []);
        $currentQuantityInCart = 0;
        foreach ($cart as $item) {
            if (isset($item['product_id']) && $item['product_id'] == $productId) {
                $currentQuantityInCart += $item['quantity'];
            }
        }
        
        // Check if adding this quantity would exceed stock
        if (($currentQuantityInCart + $quantity) > $currentStock) {
            $availableToAdd = $currentStock - $currentQuantityInCart;
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot add {$quantity} items. You can only add {$availableToAdd} more (you already have {$currentQuantityInCart} in cart, stock available: {$currentStock})"
                ], 400);
            }
            
            return back()->with('error', "Cannot add {$quantity} items. You can only add {$availableToAdd} more (you already have {$currentQuantityInCart} in cart, stock available: {$currentStock})");
        }
        
        // Add to cart
        $cartKey = $this->getCartKey($productId, $selectedSize);
        
        // Update session cart
        if (isset($cart[$cartKey])) {
            $cart[$cartKey]['quantity'] += $quantity;
        } else {
            $cart[$cartKey] = [
                'product_id' => $productId,
                'name' => $product->name,
                'price' => $product->sale_price ?: $product->price,
                'original_price' => $product->price,
                'image' => $product->featured_image ?? $product->image_main,
                'quantity' => $quantity,
                'size' => $selectedSize,
                'slug' => $product->slug,
                'stock' => $currentStock
            ];
        }
        
        Session::put('cart', $cart);
        
        // Save to database if user is logged in
        if (Auth::check()) {
            $this->addToDatabase($productId, $quantity, $selectedSize);
        }

        Log::info('Product added to cart successfully', [
            'user_id' => Auth::id(),
            'product_id' => $productId,
            'quantity' => $quantity,
            'size' => $selectedSize
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Product added to cart successfully!',
                'cart_count' => $this->getCartCount(),
                'cart_key' => $cartKey
            ]);
        }

        return back()->with('success', 'Product added to cart successfully!');

    } catch (\Exception $e) {
        Log::error('Error adding product to cart', [
            'user_id' => Auth::id(),
            'product_id' => $productId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding product to cart. Please try again.'
            ], 500);
        }

        return back()->with('error', 'Error adding product to cart. Please try again.');
    }
}

    private function addToDatabase($productId, $quantity, $size = null)
    {
        try {
            $productOptions = $size ? json_encode(['size' => $size]) : null;
            
            // Check if item already exists in database cart
            $existingItem = ShoppingCart::where('user_id', Auth::id())
                ->where('product_id', $productId)
                ->when($productOptions, function ($query) use ($productOptions) {
                    $query->where('product_options->size', json_decode($productOptions, true)['size']);
                }, function ($query) {
                    $query->whereNull('product_options');
                })
                ->first();

            if ($existingItem) {
                $existingItem->update([
                    'quantity' => $existingItem->quantity + $quantity
                ]);
            } else {
                ShoppingCart::create([
                    'user_id' => Auth::id(),
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'product_options' => $productOptions ? json_decode($productOptions, true) : null
                ]);
            }

            Log::info('Product added to database cart', [
                'user_id' => Auth::id(),
                'product_id' => $productId,
                'quantity' => $quantity,
                'size' => $size
            ]);

        } catch (\Exception $e) {
            Log::error('Error adding to database cart', [
                'user_id' => Auth::id(),
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function update(Request $request, $identifier)
    {
        try {
            $request->validate([
                'quantity' => 'required|integer|min:1|max:50'
            ]);
            
            $quantity = $request->quantity;
            $cart = Session::get('cart', []);
            $cartKey = $this->findCartKey($cart, $identifier);
            
            if (!$cartKey || !isset($cart[$cartKey])) {
                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Item not found in cart.'
                    ], 404);
                }
                return back()->with('error', 'Item not found in cart.');
            }
            
            $productId = $cart[$cartKey]['product_id'];
            $product = Product::find($productId);
            
            if (!$product) {
                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Product not found.'
                    ], 404);
                }
                return back()->with('error', 'Product not found.');
            }
            
            $availableStock = $product->stock_quantity ?? 0;
            
            if ($quantity > $availableStock) {
                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => "Insufficient stock. Available: {$availableStock}"
                    ], 400);
                }
                return back()->with('error', "Insufficient stock. Available: {$availableStock}");
            }
            
            $cart[$cartKey]['quantity'] = $quantity;
            Session::put('cart', $cart);
            
            if (Auth::check()) {
                $this->updateDatabaseCart($productId, $quantity, $cart[$cartKey]['size'] ?? null);
            }
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Cart updated successfully!',
                    'cart_count' => $this->getCartCount()
                ]);
            }
            
            return back()->with('success', 'Cart updated successfully!');
            
        } catch (\Exception $e) {
            Log::error('Error updating cart', [
                'identifier' => $identifier,
                'error' => $e->getMessage()
            ]);
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error updating cart.'
                ], 500);
            }
            
            return back()->with('error', 'Error updating cart.');
        }
    }

    private function updateDatabaseCart($productId, $quantity, $size = null)
    {
        try {
            $productOptions = $size ? ['size' => $size] : null;
            
            $existingItem = ShoppingCart::where('user_id', Auth::id())
                ->where('product_id', $productId)
                ->when($productOptions, function ($query) use ($productOptions) {
                    $query->where('product_options->size', $productOptions['size']);
                }, function ($query) {
                    $query->whereNull('product_options');
                })
                ->first();

            if ($existingItem) {
                $existingItem->update(['quantity' => $quantity]);
            }
        } catch (\Exception $e) {
            Log::error('Error updating database cart', [
                'user_id' => Auth::id(),
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function remove(Request $request, $identifier)
    {
        try {
            $cart = Session::get('cart', []);
            $cartKey = $this->findCartKey($cart, $identifier);
            
            if (!$cartKey || !isset($cart[$cartKey])) {
                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Item not found in cart.'
                    ], 404);
                }
                return back()->with('error', 'Item not found in cart.');
            }
            
            $item = $cart[$cartKey];
            unset($cart[$cartKey]);
            Session::put('cart', $cart);
            
            if (Auth::check()) {
                $this->removeFromDatabaseCart($item['product_id'], $item['size'] ?? null);
            }
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Item removed from cart!',
                    'cart_count' => $this->getCartCount()
                ]);
            }
            
            return back()->with('success', 'Item removed from cart!');
            
        } catch (\Exception $e) {
            Log::error('Error removing from cart', [
                'identifier' => $identifier,
                'error' => $e->getMessage()
            ]);
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error removing item from cart.'
                ], 500);
            }
            
            return back()->with('error', 'Error removing item from cart.');
        }
    }

    private function removeFromDatabaseCart($productId, $size = null)
    {
        try {
            $query = ShoppingCart::where('user_id', Auth::id())
                ->where('product_id', $productId);
                
            if ($size) {
                $query->where('product_options->size', $size);
            } else {
                $query->whereNull('product_options');
            }
            
            $query->delete();
        } catch (\Exception $e) {
            Log::error('Error removing from database cart', [
                'user_id' => Auth::id(),
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function clear(Request $request)
    {
        try {
            Session::forget('cart');
            
            if (Auth::check()) {
                ShoppingCart::where('user_id', Auth::id())->delete();
            }
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Cart cleared successfully!',
                    'cart_count' => 0
                ]);
            }
            
            return back()->with('success', 'Cart cleared successfully!');
            
        } catch (\Exception $e) {
            Log::error('Error clearing cart', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error clearing cart.'
                ], 500);
            }
            
            return back()->with('error', 'Error clearing cart.');
        }
    }

    public function getCartCount()
    {
        $cart = Session::get('cart', []);
        $count = 0;
        
        foreach ($cart as $item) {
            $count += $item['quantity'] ?? 0;
        }
        
        return $count;
    }

    public function getCartData()
    {
        $cart = Session::get('cart', []);
        $items = [];
        $total = 0;
        
        foreach ($cart as $cartKey => $details) {
            $productId = $details['product_id'] ?? null;
            $product = null;
            
            if ($productId) {
                $product = Product::find($productId);
            }
            
            if (!$product || !$product->is_active) {
                continue;
            }
            
            $itemPrice = $details['price'] ?? ($product->sale_price ?: $product->price);
            $itemTotal = $itemPrice * ($details['quantity'] ?? 1);
            
            $items[] = [
                'cart_key' => $cartKey,
                'product_id' => $productId,
                'name' => $details['name'] ?? $product->name,
                'price' => $itemPrice,
                'quantity' => $details['quantity'] ?? 1,
                'total' => $itemTotal,
                'size' => $details['size'] ?? null,
                'image' => $dbItem->product->featured_image ?? $dbItem->product->image_main,
                'slug' => $details['slug'] ?? $product->slug
            ];
            
            $total += $itemTotal;
        }
        
        return [
            'items' => $items,
            'total' => $total,
            'count' => $this->getCartCount()
        ];
    }

    public function syncCart(Request $request)
    {
        if (Auth::check()) {
            $this->syncCartOnLogin(Auth::id());
            return response()->json(['success' => true]);
        }
        
        return response()->json(['success' => false]);
    }

    public function syncCartOnLogin($userId)
    {
        try {
            // Load database cart to session
            $this->loadDatabaseCartToSession($userId);
            
            Log::info('Cart synced on login', ['user_id' => $userId]);
        } catch (\Exception $e) {
            Log::error('Error syncing cart on login', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function loadDatabaseCartToSession($userId)
{
    try {
        $databaseCartItems = ShoppingCart::where('user_id', $userId)
            ->with('product')
            ->get();

        if ($databaseCartItems->isEmpty()) {
            return;
        }

        $sessionCart = [];  // ← PENTING: Jangan merge dengan session lama, reset dulu

        foreach ($databaseCartItems as $dbItem) {
            if (!$dbItem->product || !$dbItem->product->is_active) {
                continue;
            }

            $size = isset($dbItem->product_options['size']) ? $dbItem->product_options['size'] : null;
            $cartKey = $this->getCartKey($dbItem->product_id, $size);

            $sessionCart[$cartKey] = [
                'product_id' => $dbItem->product_id,
                'name' => $dbItem->product->name,
                'price' => $dbItem->product->sale_price ?: $dbItem->product->price,
                'original_price' => $dbItem->product->price,
                'image' => $dbItem->product->featured_image ?? $dbItem->product->image_main,  // ← FIX INI
                'quantity' => $dbItem->quantity,
                'size' => $size,
                'slug' => $dbItem->product->slug,
                'stock' => $dbItem->product->stock_quantity ?? 0
            ];
        }

        Session::put('cart', $sessionCart);
        
        Log::info('Database cart loaded to session', [
            'user_id' => $userId,
            'items_count' => count($sessionCart)
        ]);

    } catch (\Exception $e) {
        Log::error('Error loading database cart to session', [
            'user_id' => $userId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}

    public function saveSessionCartToDatabase($userId)
    {
        try {
            $sessionCart = Session::get('cart', []);

            foreach ($sessionCart as $cartKey => $details) {
                $productId = $details['product_id'] ?? null;
                $quantity = $details['quantity'] ?? 1;
                $size = $details['size'] ?? null;
                
                if (!$productId) continue;

                $productOptions = $size ? json_encode(['size' => $size]) : null;

                $existingItem = ShoppingCart::where('user_id', $userId)
                    ->where('product_id', $productId)
                    ->when($productOptions, function ($query) use ($productOptions) {
                        $query->where('product_options', '::jsonb', [$productOptions]);
                    }, function ($query) {
                        $query->whereNull('product_options');
                    })
                    ->first();

                if ($existingItem) {
                    $existingItem->update(['quantity' => $quantity]);
                } else {
                    ShoppingCart::create([
                        'user_id' => $userId,
                        'product_id' => $productId,
                        'quantity' => $quantity,
                        'product_options' => $productOptions ? json_decode($productOptions, true) : null
                    ]);
                }
            }

            Log::info('Session cart saved to database', ['user_id' => $userId]);

        } catch (\Exception $e) {
            Log::error('Failed to save session cart to database', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function syncCartOnPageLoad()
    {
        if (Auth::check()) {
            $this->loadDatabaseCartToSession(Auth::id());
        }
    }

    private function getCartKey($productId, $size = null)
    {
        if ($size && $size !== 'One Size') {
            return $productId . '_' . str_replace([' ', '.'], '_', strtolower($size));
        }
        
        return (string) $productId;
    }

    private function findCartKey($cart, $identifier)
    {
        if (isset($cart[$identifier])) {
            return $identifier;
        }
        
        foreach ($cart as $cartKey => $item) {
            if (isset($item['product_id']) && $item['product_id'] == $identifier) {
                return $cartKey;
            }
        }
        
        foreach ($cart as $cartKey => $item) {
            if (str_starts_with($cartKey, $identifier . '_')) {
                return $cartKey;
            }
        }
        
        return null;
    }

    private function getCartItems()
    {
        $cart = Session::get('cart', []);
        $cartItems = collect();
        
        foreach ($cart as $cartKey => $details) {
            $productId = $details['product_id'] ?? null;
            $product = null;
            
            if ($productId) {
                $product = Product::find($productId);
            }
            
            $currentStock = $product ? ($product->stock_quantity ?? 0) : 0;
            
            if (!$product || !$product->is_active) {
                continue;
            }
            
            $itemName = $details['name'] ?? ($product->name ?? 'Unknown Product');
            $itemPrice = $details['price'] ?? ($product->sale_price ?: ($product->price ?? 0));
            $itemOriginalPrice = $details['original_price'] ?? ($product->price ?? 0);
            $itemImage = $details['image'] ?? ($product->featured_image ?? $product->image_main);
            $quantity = $details['quantity'] ?? 1;
            $size = $details['size'] ?? null;
            $slug = $details['slug'] ?? $product->slug;
            
            $cartItems->push([
                'cart_key' => $cartKey,
                'product_id' => $productId,
                'name' => $itemName,
                'price' => $itemPrice,
                'original_price' => $itemOriginalPrice,
                'image' => $itemImage,
                'quantity' => $quantity,
                'total' => $itemPrice * $quantity,
                'size' => $size,
                'slug' => $slug,
                'stock' => $currentStock
            ]);
        }
        
        return $cartItems;
    }

    private function calculateTotal($cartItems)
    {
        return $cartItems->sum('total');
    }
}