<?php

error_reporting(E_ALL ^ E_WARNING);
ini_set('display_errors', 1);

require_once('DataReader.class.php');
require_once('QueryCreator.class.php');

function array_map_recursive($callback, $array)
{
    $func = function ($item) use (&$func, &$callback) {
        return is_array($item) ? array_map($func, $item) : call_user_func($callback, $item);
    };

    return array_map($func, $array);
}


/*try {
    $to = new DataReader();
    $to->login();
    $to->getNormCharacteristics();
    //$to->setImage('http://www.waltercreech.com/images/artwork/pelican.jpg');
} catch (Exception $e) {
    $msg = date(DATE_RFC822) . " >\nLine: " . $e->getLine() . "\nMessage:\n" . $e->getMessage() . "\n\n";
    error_log($msg, 3, 'error.log');
    mail('TwilightTower@mail.ru', 'Ошибка в парсере', $msg);
}

exit;*/

$urlRoot = 'https://iclim.ru';
$url = $urlRoot . '/catalog/ventilyatsiya/ventilyatsionnye_ustanovki/pritochno_vytyazhnye_ustanovki/tag/ostberg/?filter=arCatalogFilter_20_195255756:Y&sort=shows';

$dom = new DOMDocument;
$dom->preserveWhiteSpace = false;
$load = $dom->loadHTMLFile($url);
$xpath = new DOMXPath($dom);

$items = $xpath->query("//div[@id='catalog_list']/div[@class='bx_catalog_item']/div[@class='bx_catalog_item_container']");

$products = [];

foreach ($items as $itm) {
    $prod = [];

    $imgElem = $xpath->query("./a[@class='bx_catalog_item_images']", $itm)->item(0);

    $imgStyle = $imgElem->getAttribute('style');
    preg_match("/url\s*\(\s*['\"]\s*(.*)\s*['\"]\s*\)/U", $imgStyle, $matches);
    $prod['img_src'] = $matches[1];

    $prod['href_full_description'] = $imgElem->getAttribute('href');

    $prod['name'] = $xpath->query("./div[@class='discribe']/div[@class='bx_catalog_item_title']/a", $itm)->item(0)->nodeValue;
    $prod['short_description'] = $xpath->query("./div[@class='discribe']/div[@class='prev_txt']", $itm)->item(0)->nodeValue;

    // Переход к подробностям
    $fullUrl = $urlRoot . $prod['href_full_description'];

    $domFull = new DOMDocument;
    $domFull->preserveWhiteSpace = false;
    $loadFull = $domFull->loadHTMLFile($fullUrl);
    $xpathFull = new DOMXPath($domFull);

    $descrFullElem = $xpathFull->query("//div[@id='desc_txt_tab']/div/div[@class='bx_item_description']")->item(0);
    $featureFullElem = $xpathFull->query("//div[@id='prop_txt_tab']/div/div[@class='item_info_section']")->item(0);

    $prod['full_description'] = $descrFullElem->ownerDocument->saveHTML($descrFullElem);
    $prod['full_feature'] = $featureFullElem->ownerDocument->saveHTML($featureFullElem);

    // Характеристики
    $domFeature = new DOMDocument;
    $domFeature->preserveWhiteSpace = false;
    $loadFeature = $domFeature->loadHTML('<?xml encoding="utf-8" ?>' . $prod['full_feature']);
    $xpathFeature = new DOMXPath($domFeature);

    //$this->to->getNormCharacteristics;

    $prod['features'] = [];

    $trs = $xpathFeature->query("//div[@class='item_info_section']/table/tr");
    foreach ($trs as $tr) {

        $feature = [];

        $counter = 0;
        foreach ($tr->childNodes as $node) {
            if ($node->nodeName == 'td') {
                $textContent = DataReader::normalizeString($node->textContent);
                if ($counter == 0) {
                    ++$counter;
                    if ($textContent == 'бренд') {
                        break;
                    }
                    $feature['name'] = $textContent;
                } else {
                    $feature['value'] = $textContent;
                    break;
                }
            }
        }

        if ($feature) {
            $prod['features'][] = $feature;
        }
    }


    $products[] = array_map_recursive('trim', $prod);
    break;
}

header('Content-Type: text/html; charset=utf-8');
echo '<pre>';
print_r($products);
echo '</pre>';

try {
    $qc = new QueryCreator('cid_4951221');
    $qc->setImage($urlRoot . $products[0]['img_src']);
    $qc->setName($products[0]['name']);
    $qc->setShortDescription($products[0]['short_description']);
    $qc->setFullDescription($products[0]['full_description']);

    $qc->postNewItem();

} catch (Exception $e) {
    $msg = date(DATE_RFC822) . " >\nLine: " . $e->getLine() . "\nMessage:\n" . $e->getMessage() . "\n\n";
    error_log($msg, 3, 'error.log');
    mail('TwilightTower@mail.ru', 'Ошибка в парсере', $msg);
    exit;
}

