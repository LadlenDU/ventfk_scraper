<?php

require_once('configCommon.php');
require_once('config.php');

require_once('DataReader.class.php');
require_once('QueryCreator.class.php');

function array_map_recursive($callback, $array)
{
    $func = function ($item) use (&$func, &$callback) {
        return is_array($item) ? array_map($func, $item) : call_user_func($callback, $item);
    };

    return array_map($func, $array);
}

$url = 'https://iclim.ru/catalog/ventilyatsiya/ventilyatsionnye_ustanovki/pritochno_vytyazhnye_ustanovki/tag/ostberg/?filter=arCatalogFilter_20_195255756:Y&sort=shows';

$urlElements = parse_url($url);
$urlRoot = $urlElements['scheme'] . '://' . $urlElements['host'];

$cid = 'cid_4951221';   // Ostberg

$dom = new DOMDocument;
$dom->preserveWhiteSpace = false;

$wrongItems = [];

$products = [];

do {
    $load = $dom->loadHTMLFile($url);
    $xpath = new DOMXPath($dom);

    $items = $xpath->query("//div[@id='catalog_list']/div[@class='bx_catalog_item']/div[@class='bx_catalog_item_container']");

    foreach ($items as $itmKey => $itm) {
        $prod = [];

        $imgElem = $xpath->query("./a[@class='bx_catalog_item_images']", $itm)->item(0);
        if (!$imgElem) {
            $wrongItems[] = ['url' => $url, 'key' => $itmKey, 'stage' => 1];
            continue;
        }

        $imgStyle = $imgElem->getAttribute('style');
        preg_match("/url\s*\(\s*['\"]\s*(.*)\s*['\"]\s*\)/U", $imgStyle, $matches);
        $prod['img_src'] = $matches[1];

        $prod['href_full_description'] = $imgElem->getAttribute('href');

        $tmp = $xpath->query("./div[@class='discribe']/div[@class='bx_catalog_item_title']/a", $itm)->item(0);
        if (!$tmp) {
            $tmp[] = ['url' => $url, 'key' => $itmKey, 'stage' => 2];
            continue;
        }
        $prod['name'] = $tmp->nodeValue;
        $tmp = $xpath->query("./div[@class='discribe']/div[@class='prev_txt']", $itm)->item(0);
        if (!$tmp) {
            $tmp[] = ['url' => $url, 'key' => $itmKey, 'stage' => 3];
            continue;
        }
        $prod['short_description'] = $tmp->nodeValue;

        // Переход к подробностям
        $fullUrl = $urlRoot . $prod['href_full_description'];

        $domFull = new DOMDocument;
        $domFull->preserveWhiteSpace = false;
        $fContent = file_get_contents($fullUrl);
        $fContent = str_replace('<!--<div class="item_b"><span class="icon icon_delivery"></span>Бесплатная&nbsp;доставка&nbsp;до&nbsp;транспортной&nbsp;компании</div>--!>	', '', $fContent);
        $loadFull = $domFull->loadHTML($fContent);
        //$loadFull = $domFull->loadHTMLFile($fullUrl);
        $xpathFull = new DOMXPath($domFull);

        $descrFullElem = $xpathFull->query("//div[@id='desc_txt_tab']/div/div[@class='bx_item_description']")->item(0);
        $featureFullElem = $xpathFull->query("//div[@id='prop_txt_tab']/div/div[@class='item_info_section']")->item(0);

        if (!$descrFullElem || !$featureFullElem) {
            $wrongItems[] = ['url' => $fullUrl, 'key' => $itmKey, 'stage' => 4];
            continue;
        }

        $prod['full_description'] = $descrFullElem->ownerDocument->saveHTML($descrFullElem);
        $prod['full_feature'] = $featureFullElem->ownerDocument->saveHTML($featureFullElem);

        // Характеристики
        $domFeature = new DOMDocument;
        $domFeature->preserveWhiteSpace = false;
        $loadFeature = $domFeature->loadHTML('<?xml encoding="utf-8" ?>' . $prod['full_feature']);
        $xpathFeature = new DOMXPath($domFeature);

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
                        if (mb_strtolower($textContent, 'utf-8') == 'бренд') {
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
        //break;
    }

    $notLastPage = false;

    //.catalog_pageNav .navstring ul li.bx-pag-next a (get href)
    //$nextPage = $xpath->query("//div[@id='catalog_list']/div[@class='bx_catalog_item']/div[@class='bx_catalog_item_container']");
    $nextPage = $xpath->query("//div[@class='catalog_pageNav']/div[@class='navstring']/ul/li[@class='bx-pag-next']/a");
    if ($nextPage->length) {
        if ($newUrl = $nextPage->item(0)->getAttribute('href')) {
            $url = $urlRoot . $newUrl;
            $notLastPage = true;
        }
    }


} while ($notLastPage);

header('Content-Type: text/html; charset=utf-8');

try {

    $oldItems = [];
    $newItems = [];

    foreach ($products as $prod) {
        $qc = new QueryCreator($cid);
        $qc->setImage($urlRoot . $prod['img_src']);
        $qc->setName($prod['name']);
        $qc->setShortDescription($prod['short_description']);
        $qc->setFullDescription($prod['full_description']);

        foreach ($prod['features'] as $feature) {
            $qc->setFeature($feature['name'], $feature['value']);
        }

        $res = $qc->postNewItem();
        if ($res == 'already_exists') {
            $oldItems[] = $prod['name'];
        } elseif ($res == 'item_set') {
            $newItems[] = $prod['name'];
        } else {
            throw new Exception('internal error');
        }
    }

    $str = "Завершен парсинг страницы\n"
        . "$url\nв пункт '$cid'\n"
        . "Добавлены элементы:\n"
        . print_r($newItems, true) . "\n\n"
        . "Элементы, проигнорированные как уже существующие:\n"
        . print_r($oldItems, true)
        . "Пропущенные по причине ошибок элементы:\n"
        . print_r($wrongItems, true);

    echo "<pre>\n$str\n</pre>";

    $msg = date(DATE_RFC822) . " >\n$str\n\n";
    error_log($msg, 3, RESULT_LOG_FILE);

    mail(EMAIL, 'Произведен парсинг', $msg);

} catch (Exception $e) {
    $msg = date(DATE_RFC822) . " >\nLine: " . $e->getLine() . "\nMessage:\n" . $e->getMessage() . "\n\n";
    error_log($msg, 3, ERROR_LOG_FILE);
    mail(EMAIL, 'Ошибка в парсере', $msg);
    echo '<pre>';
    echo 'Произошла ошибка:<br>';
    echo $msg;
    echo '</pre>';
    exit;
}

/*echo '<pre>';
print_r($products);
echo '</pre>';*/
