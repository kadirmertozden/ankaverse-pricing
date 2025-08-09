<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PriceController extends Controller
{
    public function products()
    {
        return response()->json([
            ['stockCode' => 'P11743S4450', 'name' => 'Ürün 1', 'price' => 100],
            ['stockCode' => 'P11856S4460', 'name' => 'Ürün 2', 'price' => 150],
        ]);
    }

    public function compute(Request $request)
    {
        $data = $request->validate([
            'stockCode' => 'required|string',
            'buyPriceVat' => 'required|numeric',
            'commissionRate' => 'required|numeric',
            'width' => 'required|numeric',
            'length' => 'required|numeric',
            'height' => 'required|numeric',
            'profit.mode' => 'required|string',
            'profit.value' => 'required|numeric',
            'freeShipThreshold' => 'required|numeric',
        ]);

        $price = $data['buyPriceVat'] + ($data['buyPriceVat'] * $data['commissionRate'] / 100);
        if ($data['profit']['mode'] === 'percent') {
            $price += ($price * $data['profit']['value'] / 100);
        } else {
            $price += $data['profit']['value'];
        }

        return response()->json([
            'stockCode' => $data['stockCode'],
            'calculatedPrice' => round($price, 2),
        ]);
    }
}
