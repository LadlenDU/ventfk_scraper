<?php

error_reporting(E_ALL ^ E_WARNING);
ini_set('display_errors', 1);

class DataTo
{
    const COOKIE = 'storeland.cookie.txt';

    /*public function __construct()
    {
        $ch = curl_init();
    }*/

    protected function login()
    {
        $data = [
            'act' => 'login',
            'action_to' => 'http://storeland.ru/',
            'site_id' => '',
            'to' => '',
            'hash' => '66281d27',
            'form[user_mail]' => 'twilighttower@mail.ru',
            'form[user_pass]' => 'FycUrYCa',
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_COOKIE, self::COOKIE);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt($ch, CURL_HTTPHEADER , "Content-Type: multipart/form-data" );
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_URL, "https://storeland.ru/user/login");

        $result = curl_exec($ch);

        curl_close($ch);
    }

    protected function setImage()
    {
        $img = __DIR__ . '/Sobranie_cover4.jpg';
        $data = ['form[ajax_images][]' => '@' . $img,
            'ajax_q' => 1,
            'form[goods_id]' => 'NaN',
            //'form[images_ids][0]' => 'img_699860198617'
            'form[images_ids][0]' => 'img_699860198618'
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_COOKIE, self::COOKIE);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt($ch, CURL_HTTPHEADER , "Content-Type: multipart/form-data" );
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_REFERER, "http://internalweb.com/");
        curl_setopt($ch, CURLOPT_URL, "http://ventfabrika.su/admin/store_goods_img_upload");

        $page = curl_exec($ch);

        curl_close($ch);
    }

    public function init()
    {
        $this->login();
        $this->setImage();
    }
}

$to = new DataTo();
$to->init();

exit;

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

    $products[] = array_map('trim', $prod);
}

print_r($products);


// post image
// http://ventfabrika.su/admin/store_goods_img_upload

/*
headers:

Content-Disposition: form-data; name="form[ajax_images][]"; filename="Wallpaper476.jpg"
Content-Type: image/jpeg
-----------
Content-Disposition: form-data; name="ajax_q"

1
---------------------------------
Content-Disposition: form-data; name="form[goods_id]"

NaN
-----------------------------
Content-Disposition: form-data; name="form[images_ids][0]"

img_699860198617
 */