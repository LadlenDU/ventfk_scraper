<?php

function array_map_recursive($callback, $array)
{
    $func = function ($item) use (&$func, &$callback) {
        return is_array($item) ? array_map($func, $item) : call_user_func($callback, $item);
    };

    return array_map($func, $array);
}

function putProduct($prod, $urlRoot, $cid, $priceCorrectPercent, &$newItems, &$oldItems, &$wrongItems)
{
    try {

        $qc = new QueryCreator($cid, $_POST['search_type']);
        $qc->setImage($urlRoot . $prod['img_src']);
        $qc->setName($prod['name']);
        $qc->setShortDescription($prod['short_description']);
        $qc->setFullDescription($prod['full_description']);
        $qc->setPrice($prod['price'], $priceCorrectPercent);
        $qc->setVendorCode($prod['vendor_code']);

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

    } catch (Exception $e) {
        $wrongItems[] = $prod['name'];
        $msg = date(DATE_RFC822) . " >\nLine: " . $e->getLine() . "\nMessage:\n" . $e->getMessage() . "\n\n";
        error_log($msg, 3, ERROR_LOG_FILE);
        mail(EMAIL, 'Ошибка в парсере', $msg);
        echo '<pre>';
        echo 'Произошла ошибка:<br>';
        echo $msg;
        echo '</pre>';
        return false;
    }

    return true;
}

function parseBrand($url, $cid, $percent, $cat_name, $email)
{
    //$url = 'https://iclim.ru/catalog/ventilyatsiya/ventilyatsionnye_ustanovki/pritochno_vytyazhnye_ustanovki/tag/ostberg/?filter=arCatalogFilter_20_195255756:Y&sort=shows';
    $origUrl = $url = trim($url);

    $urlElements = parse_url($url);
    $urlRoot = $urlElements['scheme'] . '://' . $urlElements['host'];

    //$cid = 'cid_4951221';   // Ostberg
    $cid = trim($cid);
    $pricePercent = trim($percent);

    $dom = new DOMDocument;
    $dom->preserveWhiteSpace = false;

    $products = array();

    $oldItems = array();
    $newItems = array();
    $wrongItems = array();

//    header('Content-Type: text/html; charset=utf-8');

    $stubCounter = 1;

    do {

        if ($stubCounter++ > PAGES_LIMIT) {
            break;
        }

        $load = $dom->loadHTMLFile($url);
        $xpath = new DOMXPath($dom);

        $items = $xpath->query("//div[@id='catalog_list']/div[@class='bx_catalog_item']/div[@class='bx_catalog_item_container']");

        foreach ($items as $itmKey => $itm) {
            $prod = array();

            $imgElem = $xpath->query("./a[@class='bx_catalog_item_images']", $itm)->item(0);
            if (!$imgElem) {
                $wrongItems[] = array('url' => $url, 'key' => $itmKey, 'stage' => 1);
                continue;
            }

            $imgStyle = $imgElem->getAttribute('style');
            preg_match("/url\s*\(\s*['\"]\s*(.*)\s*['\"]\s*\)/U", $imgStyle, $matches);
            $prod['img_src'] = $matches[1];

            $prod['href_full_description'] = $imgElem->getAttribute('href');

            $tmp = $xpath->query("./div[@class='discribe']/div[@class='bx_catalog_item_title']/a", $itm)->item(0);
            if (!$tmp) {
                $tmp[] = array('url' => $url, 'key' => $itmKey, 'stage' => 2);
                continue;
            }
            $prod['name'] = $tmp->nodeValue;
            $tmp = $xpath->query("./div[@class='discribe']/div[@class='prev_txt']", $itm)->item(0);
            if (!$tmp) {
                $tmp[] = array('url' => $url, 'key' => $itmKey, 'stage' => 3);
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
            $priceFullElem = $xpathFull->query("//div[@class='item_current_price']")->item(0);
            $vendorCodeFullElem = $xpathFull->query("//div[@class='detail_articul']/span")->item(0);

            if (!$descrFullElem && !$featureFullElem) {
                $wrongItems[] = array('url' => $fullUrl, 'key' => $itmKey, 'stage' => 4);
                continue;
            }
            /*if (!$priceFullElem) {
                $wrongItems[] = array('url' => $fullUrl, 'key' => $itmKey, 'stage' => 5);
                continue;
            }*/

            $prod['full_description'] = $descrFullElem ? $descrFullElem->ownerDocument->saveHTML($descrFullElem) : '';
            $prod['full_feature'] = $featureFullElem ? $featureFullElem->ownerDocument->saveHTML($featureFullElem) : '';
            $prod['price'] = $priceFullElem ? str_replace(array('руб.', 'руб', ' '), '', $priceFullElem->textContent) : 0;
            $prod['vendor_code'] = trim($vendorCodeFullElem->textContent);

            // Характеристики
            $domFeature = new DOMDocument;
            $domFeature->preserveWhiteSpace = false;
            $loadFeature = $domFeature->loadHTML('<?xml encoding="utf-8" ?>' . $prod['full_feature']);
            $xpathFeature = new DOMXPath($domFeature);

            $prod['features'] = array();

            $trs = $xpathFeature->query("//div[@class='item_info_section']/table/tr");
            foreach ($trs as $tr) {

                $feature = array();

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
            sleep(SLEEP_BEFORE_PUT_PRODUCT);
            putProduct($prod, $urlRoot, $cid, $pricePercent, $newItems, $oldItems, $wrongItems);
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

    $cat_name = trim($cat_name);
    $email = trim($email);

    $str = "Завершен парсинг страницы\n"
        . "$origUrl\nв пункт '$cat_name, id:$cid'\n"
        . "Отчет отправлен на адрес $email\n"
        . "Добавлены элементы:\n"
        . print_r($newItems, true) . "\n\n"
        . "Элементы, проигнорированные как уже существующие:\n"
        . print_r($oldItems, true) . "\n\n"
        . "Пропущенные по причине ошибок элементы:\n"
        . print_r($wrongItems, true);

    $resultString = "<pre>\n$str\n</pre>";

    $msg = date(DATE_RFC822) . " >\n$str\n\n";
    error_log($msg, 3, RESULT_LOG_FILE);

    mail($email, "Произведен парсинг для категории $cat_name", $msg);
    //exit;
}

/**
 * Вернуть парамерт location из ответов заголовков.
 *
 * @param $url
 * @return string
 */
function getResponseLocationHeader($url)
{
    $location = '';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if (curl_exec($ch)) {
        //echo curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cInfo = curl_getinfo($ch);
        $location = $cInfo['url'];
    }
    curl_close($ch);

    return $location;
}


function parseBrandRusklimat($url, $cid, $percent, $cat_name, $email)
{
    $origUrl = $url = trim($url);

    $urlElements = parse_url($url);
    $urlRoot = $urlElements['scheme'] . '://' . $urlElements['host'];

    $cid = trim($cid);
    $pricePercent = trim($percent);

    $dom = new DOMDocument;
    $dom->preserveWhiteSpace = false;

    $products = array();

    $oldItems = array();
    $newItems = array();
    $wrongItems = array();

//    header('Content-Type: text/html; charset=utf-8');

    $stubCounter = 1;

    do {

        if ($stubCounter++ > PAGES_LIMIT) {
            break;
        }

        $load = $dom->loadHTMLFile($url);
        $xpath = new DOMXPath($dom);

        //$items = $xpath->query("//div[@id='catalog_list']/div[@class='bx_catalog_item']/div[@class='bx_catalog_item_container']");
        $items = $xpath->query("//div[@class='results-line']/div");

        foreach ($items as $itmKey => $itm) {
            $prod = array();

            //$imgElem = $xpath->query("./a[@class='bx_catalog_item_images']", $itm)->item(0);
            $imgElem = $xpath->query(".//a[@class='pic']", $itm)->item(0);
            if (!$imgElem) {
                $wrongItems[] = array('url' => $url, 'key' => $itmKey, 'stage' => 1);
                continue;
            }

            $imgStyle = $imgElem->getAttribute('style');
            preg_match("/url\s*\(\s*['\"]\s*(.*)\s*['\"]\s*\)/U", $imgStyle, $matches);
            $prod['img_src'] = $matches[1];

            $prod['href_full_description'] = trim($imgElem->getAttribute('href'));

            //$tmp = $xpath->query("./div[@class='discribe']/div[@class='bx_catalog_item_title']/a", $itm)->item(0);
            $tmp = $xpath->query(".//div[@class='ttl']/a", $itm)->item(0);
            if (!$tmp) {
                $tmp[] = array('url' => $url, 'key' => $itmKey, 'stage' => 2);
                continue;
            }
            $prod['name'] = trim($tmp->nodeValue);

            //$tmp = $xpath->query("./div[@class='discribe']/div[@class='prev_txt']", $itm)->item(0);
            $tmp = $xpath->query(".//div[@class='cln-chars']", $itm)->item(0);
            if (!$tmp) {
                $tmp[] = array('url' => $url, 'key' => $itmKey, 'stage' => 3);
                continue;
            }
            #$prod['short_description'] = trim($tmp->nodeValue);
            $prod['short_description'] = '';
            $shortDescription = trim($tmp->nodeValue);
            if ($shortDescriptionList = preg_split("/\\r\\n|\\r|\\n/", $shortDescription)) {
                foreach ($shortDescriptionList as $key => $txtLine) {
                    $prod['short_description'] .= trim($txtLine);
                    if ($key % 2) {
                        $prod['short_description'] .= "\n";
                    } else {
                        $prod['short_description'] = rtrim($prod['short_description'], ':') . ': ';
                    }
                }
                //// remove '<br>'
                //$prod['short_description'] = substr($prod['short_description'], 0, -4);
            }

            // Переход к подробностям
            $fullUrl = $urlRoot . $prod['href_full_description'];

            $domFull = new DOMDocument;
            $domFull->preserveWhiteSpace = false;
            $fContent = file_get_contents($fullUrl);
            $fContent = str_replace('<!--<div class="item_b"><span class="icon icon_delivery"></span>Бесплатная&nbsp;доставка&nbsp;до&nbsp;транспортной&nbsp;компании</div>--!>	', '', $fContent);
            $loadFull = $domFull->loadHTML($fContent);
            //$loadFull = $domFull->loadHTMLFile($fullUrl);
            $xpathFull = new DOMXPath($domFull);

            //$descrFullElem = $xpathFull->query("//div[@id='desc_txt_tab']/div/div[@class='bx_item_description']")->item(0);
            $descrFullElem = $xpathFull->query("//div[@id='tabDesc']")->item(0);
            //$featureFullElem = $xpathFull->query("//div[@id='prop_txt_tab']/div/div[@class='item_info_section']")->item(0);
            $featureFullElem = $xpathFull->query("//div[@id='tabChar']/table[contains(@class,'tbl-char')]")->item(0);
            //$priceFullElem = $xpathFull->query("//div[@class='item_current_price']")->item(0);
            $priceFullElem = $xpathFull->query("//div[@class='prices']/div[@class='price']")->item(0);
            //$vendorCodeFullElem = $xpathFull->query("//div[@class='detail_articul']/span")->item(0);
            $vendorCodeFullElem = $xpathFull->query("//div[@class='article']/span")->item(0);

            if (!$descrFullElem && !$featureFullElem) {
                $wrongItems[] = array('url' => $fullUrl, 'key' => $itmKey, 'stage' => 4);
                continue;
            }
            /*if (!$priceFullElem) {
                $wrongItems[] = array('url' => $fullUrl, 'key' => $itmKey, 'stage' => 5);
                continue;
            }*/

            $prod['full_description'] = $descrFullElem ? $descrFullElem->ownerDocument->saveHTML($descrFullElem) : '';
            $prod['full_feature'] = $featureFullElem ? $featureFullElem->ownerDocument->saveHTML($featureFullElem) : '';
            $prod['price'] = $priceFullElem ? str_replace(array('руб.', 'руб', ' '), '', $priceFullElem->textContent) : 0;
            $prod['vendor_code'] = trim($vendorCodeFullElem->textContent);

            // Характеристики
            $domFeature = new DOMDocument;
            $domFeature->preserveWhiteSpace = false;
            $loadFeature = $domFeature->loadHTML('<?xml encoding="utf-8" ?>' . $prod['full_feature']);
            $xpathFeature = new DOMXPath($domFeature);

            $prod['features'] = array();

            //$trs = $xpathFeature->query("//div[@class='item_info_section']/table/tr");
            //$trs = $xpathFeature->query("//table[contains(@class,'tbl-char')]/tr[not(@class='sp')]");
            $trs = $xpathFeature->query("//table[contains(@class,'tbl-char')]/tr[not(@class='sp')]/td[not(@colspan='2')]/parent::tr");
            foreach ($trs as $tr) {

                /*if (!$xpathFeature->query(".//td[not(@colspan='2')]", $tr)->item(0)) {
                    continue;
                }*/

                $feature = array();

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
            sleep(SLEEP_BEFORE_PUT_PRODUCT);
            putProduct($prod, $urlRoot, $cid, $pricePercent, $newItems, $oldItems, $wrongItems);
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

    $cat_name = trim($cat_name);
    $email = trim($email);

    $str = "Завершен парсинг страницы\n"
        . "$origUrl\nв пункт '$cat_name, id:$cid'\n"
        . "Отчет отправлен на адрес $email\n"
        . "Добавлены элементы:\n"
        . print_r($newItems, true) . "\n\n"
        . "Элементы, проигнорированные как уже существующие:\n"
        . print_r($oldItems, true) . "\n\n"
        . "Пропущенные по причине ошибок элементы:\n"
        . print_r($wrongItems, true);

    $resultString = "<pre>\n$str\n</pre>";

    $msg = date(DATE_RFC822) . " >\n$str\n\n";
    error_log($msg, 3, RESULT_LOG_FILE);

    mail($email, "Произведен парсинг для категории $cat_name", $msg);
    //exit;
}

