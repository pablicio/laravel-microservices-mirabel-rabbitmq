<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/** Black Friday: a tiny cart that refuses to sell more than the stock. */
class BlackFridayController extends Controller
{
    private const PRODUCTS = [
        'headset' => ['name' => 'Pulse Headset', 'category' => 'Audio', 'price' => 8990, 'stock' => 4, 'accent' => '#d85d4c'],
        'keyboard' => ['name' => 'Atlas Keyboard', 'category' => 'Desk', 'price' => 12990, 'stock' => 7, 'accent' => '#176b87'],
        'camera' => ['name' => 'Orbit Camera', 'category' => 'Creator', 'price' => 21990, 'stock' => 2, 'accent' => '#8b6f47'],
    ];

    public function show()
    {
        $products = [];
        foreach (self::PRODUCTS as $id => $product) {
            $products[] = ['id' => $id] + $product;
        }

        return view('black-friday', ['products' => $products]);
    }

    public function checkout(Request $request)
    {
        $items = $request->validate(['items' => ['required', 'array']])['items'];
        $accepted = [];
        $blocked = [];
        $contention = [];
        $total = 0;

        foreach ($items as $id => $quantity) {
            $product = self::PRODUCTS[$id] ?? null;
            $quantity = (int) $quantity;
            if ($product === null || $quantity < 1) {
                continue;
            }

            if ($quantity > $product['stock']) {
                $blocked[] = [
                    'name' => $product['name'],
                    'requested' => $quantity,
                    'available' => $product['stock'],
                ];
                continue;
            }

            $accepted[] = ['name' => $product['name'], 'quantity' => $quantity];
            $total += $product['price'] * $quantity;

            $atomicOrders = min(2, intdiv($product['stock'], $quantity));
            $atomicReserved = $atomicOrders * $quantity;
            $uncoordinatedTotal = $quantity * 2;
            $contention[] = [
                'name' => $product['name'],
                'stock' => $product['stock'],
                'quantity_per_checkout' => $quantity,
                'uncoordinated_orders' => 2,
                'uncoordinated_total' => $uncoordinatedTotal,
                'oversold' => max(0, $uncoordinatedTotal - $product['stock']),
                'atomic_approved_orders' => $atomicOrders,
                'atomic_reserved' => $atomicReserved,
                'remaining_stock' => $product['stock'] - $atomicReserved,
            ];
        }

        return response()->json([
            'status' => count($blocked) > 0 ? 'partial' : 'approved',
            'accepted' => $accepted,
            'blocked' => $blocked,
            'contention' => $contention,
            'total' => number_format($total / 100, 2, ',', '.'),
        ]);
    }
}
