<?php

require_once('configCommon.php');
require_once('config.php');

require_once('DataReader.class.php');
require_once('QueryCreator.class.php');

require_once('functions.php');

if (!empty($_POST['cid'])) {

    //$url = 'https://iclim.ru/catalog/ventilyatsiya/ventilyatsionnye_ustanovki/pritochno_vytyazhnye_ustanovki/tag/ostberg/?filter=arCatalogFilter_20_195255756:Y&sort=shows';
    $origUrl = $url = trim($_POST['url']);

    $urlElements = parse_url($url);
    $urlRoot = $urlElements['scheme'] . '://' . $urlElements['host'];

    //$cid = 'cid_4951221';   // Ostberg
    $cid = trim($_POST['cid']);
    $pricePercent = trim($_POST['percent']);

    $dom = new DOMDocument;
    $dom->preserveWhiteSpace = false;

    $products = array();

    $oldItems = array();
    $newItems = array();
    $wrongItems = array();

    header('Content-Type: text/html; charset=utf-8');

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

            if (!$descrFullElem || !$featureFullElem) {
                $wrongItems[] = array('url' => $fullUrl, 'key' => $itmKey, 'stage' => 4);
                continue;
            }
            if (!$priceFullElem) {
                $wrongItems[] = array('url' => $fullUrl, 'key' => $itmKey, 'stage' => 5);
                continue;
            }

            $prod['full_description'] = $descrFullElem->ownerDocument->saveHTML($descrFullElem);
            $prod['full_feature'] = $featureFullElem->ownerDocument->saveHTML($featureFullElem);
            $prod['price'] = str_replace(['руб.', 'руб', ' '], '', $priceFullElem->textContent);
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

    $cat_name = trim($_POST['cat_name']);
    $email = trim($_POST['email']);

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

$dr = new DataReader();
$dr->login();
$catalog = $dr->getCatalogJs();

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Парсер</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.2.1/themes/default/style.min.css"/>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/1.12.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.2.1/jstree.min.js"></script>

    <style>
        .JsTreeGoodsIndex {
            color: green;
            vertical-align: super;
            font-size: 0.6em;
        }
    </style>
</head>
<body>

<?php if (!empty($resultString)): ?>
<?php echo $resultString ?><br><br>
<?php endif; ?>

<div id="categories" style="width:550px;float:left;font-size: 13px;overflow-x: auto">
    Размещение:<br>

    <div id="positions_cid"></div>
</div>
<div style="width:500px;float:left">
    <form method="post">
        <label>E-mail отчета:<br><input type="text" name="email" value="<?php echo htmlspecialchars(EMAIL); ?>"
                                        style="width:100%"></label><br><br>
        <label>Название категории:<br><input type="text" name="cat_name" style="width:100%;background-color:#EEE"
                                             readonly="readonly"></label><br>
        <label>CID (размещение):<br><input type="text" name="cid" style="width:100%;background-color:#EEE"
                                           readonly="readonly"></label><br><br>
        <label>Url донора (первая страница):<br><input type="text" name="url" style="width:100%"></label><br><br>
        <label>Проценты к цене (отрицательное значение - минус проценты):<br><input type="text" name="percent"
                                                                                    style="width:100%"
                                                                                    value="-3"></label><br><br>
        <button>Начать парсинг</button>
    </form>
</div>

<script>
    <?php echo $catalog; ?>

    $(function () {
        prepCat(goodsCatalog);
        $('#positions_cid').jstree({
            'core': {
                "multiple": false,
                'data': [goodsCatalog]
            }
        });

        $('#positions_cid')
            // listen for event
            .on('changed.jstree', function (e, data) {
                var text = data.instance.get_node(data.selected[0]).text;
                var catName = text.match(/^(.+), id:/);
                var cid = text.match(/id:(.+)$/);
                var modCatName = catName[1].replace(/<i.+i>/, '');
                $("input[name=cat_name]").val(modCatName);
                $("input[name=cid]").val(cid[1]);
            })
    });

    var prepCatCount = 0;
    function prepCat(elem) {
        elem.text = elem.data + ", id:" + elem.attributes.id;
        if (prepCatCount++ < 3) {
            elem.state = {opened: true};
        }
        if (elem.children) {
            for (var key in elem.children) {
                prepCat(elem.children[key]);
            }
        }
    }
</script>


</body>
</html>
