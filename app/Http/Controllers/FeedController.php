<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FeedController extends Controller
{
    public function feed(Request $r)
    {
        // Şimdilik basit bir örnek feed; sonra gerçek XML’den okuyup hesaplanmış alanları ekleyeceğiz
        $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<Root>
  <Products>
    <Product>
      <StockCode>P11743S4450</StockCode>
      <Name>4 Shot Bardaklı Matara Seti</Name>
      <OnerilenSatisFiyati>349.90</OnerilenSatisFiyati>
      <KomisyonOrani>18.00</KomisyonOrani>
      <KargoFiyati>0.00</KargoFiyati>
      <NetKar>...</NetKar>
      <KarOrani>...</KarOrani>
      <ShippingIncluded>true</ShippingIncluded>
    </Product>
  </Products>
</Root>
XML;
        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
