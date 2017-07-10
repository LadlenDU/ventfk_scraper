<?php

error_reporting(E_ALL ^ E_WARNING);
ini_set('display_errors', 1);

$url = 'https://iclim.ru/catalog/ventilyatsiya/ventilyatsionnye_ustanovki/pritochno_vytyazhnye_ustanovki/tag/ostberg/?filter=arCatalogFilter_20_195255756:Y&sort=shows';

$dom = new DOMDocument;
$dom->preserveWhiteSpace = false;

$load = $dom->loadHTMLFile($url);

$xpath = new DOMXPath($dom);

//id=catalog_list

//$catalog = $dom->getElementById("catalog_list");

#$attr = $xpath->query("//*[@id='catalog_list']")->item(0)->getAttribute('style');

#$attr = $xpath->query("//bx_catalog_item_container/a")->item(0)->getAttribute('style');

$items = $xpath->query("//div[@id='catalog_list']/div[@class='bx_catalog_item']");

$products = [];

foreach ($items as $itm) {
    $prod = [];

    $imgElement = $xpath->query("./div/a[@class='bx_catalog_item_images']", $itm)->item(0);

    $imgStyle = $imgElement->getAttribute('style');
    preg_match("url\s*\(\s*['\"]\s*(.*)\s*['\"]\s*\)/U", $imgStyle, $matches);
    $prod['img_src'] = $matches[1];

    $prod['href_full_description'] = $imgElement->getAttribute('href');

    $products = $prod;
}

echo $attr;

