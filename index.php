<?php

error_reporting(E_ALL ^ E_WARNING);
ini_set('display_errors', 1);

class DataTo
{
    private $cookie;

    public function __construct()
    {
        $this->cookie = __DIR__ . '/storeland.cookie.txt';
    }

    protected function setCommonCurlOpt($ch)
    {
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie);
        curl_setopt($ch, CURLOPT_COOKIESESSION, true);
        //curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:10.0.2) Gecko/20100101 Firefox/10.0.2');
    }

    protected function getCurlErrorInfo($ch)
    {
        $err = curl_errno($ch);
        $errmsg = curl_error($ch);
        $header = curl_getinfo($ch);
        return "Errno: $err\nError: $errmsg\nInfo: $header";
    }

    protected function loadLoginPage()
    {
        $ch = curl_init();

        $this->setCommonCurlOpt($ch);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_URL, "https://storeland.ru/user/login");

        $result = curl_exec($ch);

        if (!$result) {
            $info = $this->getCurlErrorInfo($ch);
            throw new Exception("Can't load login page. Info:\n$info\n");
        }

        curl_close($ch);

        preg_match('/<input type="hidden" name="hash" value="(.+)"/', $result, $matches);

        if (!$matches[1]) {
            throw new Exception("Can't get hash from\n>>>>>\n$result\n<<<<<\n");
        }

        return $matches[1];
    }

    protected function loadVentfabricaLoginPage($sessId, $sessHash)
    {
        $ch = curl_init();

        $data = [
            'sess_id' => $sessId,
            'sess_hash' => $sessHash,
        ];

        $this->setCommonCurlOpt($ch);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_URL, "http://ventfabrika.su/admin/login");

        $result = curl_exec($ch);

        if (!$result) {
            $info = $this->getCurlErrorInfo($ch);
            throw new Exception("Can't load ventfabrica login page. Info:\n$info\n");
        }

        curl_close($ch);
    }

    public function login()
    {
        unlink($this->cookie);  // не оптимально но так проще

        $hash = $this->loadLoginPage();

        $data = [
            'act' => 'login',
            'action_to' => 'http://storeland.ru/',
            'site_id' => '',
            'to' => '',
            'hash' => $hash,
            'form[user_mail]' => 'twilighttower@mail.ru',
            'form[user_pass]' => 'FycUrYCa',
        ];

        $ch = curl_init();

        $this->setCommonCurlOpt($ch);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_URL, "https://storeland.ru/user/login");

        $result = curl_exec($ch);

        if (!$result) {
            $info = $this->getCurlErrorInfo($ch);
            throw new Exception("Can't login to storeland. Info:\n$info\n");
        }

        curl_close($ch);

        preg_match('/<input type=hidden name="sess_id" value="(.+)"/', $result, $matches);
        if (!$matches[1]) {
            throw new Exception("Can't get sess_id from\n>>>>>\n$result\n<<<<<\n");
        }
        $sessId = $matches[1];

        preg_match('/<input type=hidden name="sess_hash" value="(.+)"/', $result, $matches);
        if (!$matches[1]) {
            throw new Exception("Can't get sess_hash from\n>>>>>\n$result\n<<<<<\n");
        }
        $sessHash = $matches[1];

        $this->loadVentfabricaLoginPage($sessId, $sessHash);
    }

    protected function genImageId()
    {
        $num = '';
        for ($i = 0; $i < 12; ++$i) {
            $num .= mt_rand(0, 9);
        }
        return 'img_' . $num;
    }

    public function setImage($url)
    {
        $imageId = $this->genImageId();

        $image = file_get_contents($url);
        $tmpName = tempnam(sys_get_temp_dir(), 'tmp');
        file_put_contents($tmpName, $image);

        $fName = pathinfo($url, PATHINFO_BASENAME);

        $cImage = new CURLFile($tmpName, mime_content_type($tmpName), $fName);
        $data = ['form[ajax_images][]' => $cImage,
            'ajax_q' => 1,
            'form[goods_id]' => 'NaN',
            //'form[images_ids][0]' => 'img_699860198617'
            'form[images_ids][0]' => $imageId,
        ];

        $ch = curl_init();

        $this->setCommonCurlOpt($ch);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_URL, "http://ventfabrika.su/admin/store_goods_img_upload");

        $result = curl_exec($ch);

        unset($tmpName);

        if (!$result) {
            $info = $this->getCurlErrorInfo($ch);
            throw new Exception("Can't upload image. Info:\n$info\n");
        }

        curl_close($ch);

        $pageDecoded = json_decode($result, true);

        if (!$pageDecoded['result'][$imageId]['image_id']) {
            throw new Exception("Can't get image_id from\n>>>>>\n$result\n<<<<<\n");
        }

        return $pageDecoded['result'][$imageId]['image_id'];
    }

    public function getCharacteristics()
    {
        $ch = curl_init();

        $this->setCommonCurlOpt($ch);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_URL, "http://ventfabrika.su/json/attr");

        $result = curl_exec($ch);

        if (!$result) {
            $info = $this->getCurlErrorInfo($ch);
            throw new Exception("Can't get add goods page. Info:\n$info\n");
        }

        curl_close($ch);

        $result = substr($result, 0, 21);

        $dRes = json_decode($result);
        if ($dRes) {
            throw new Exception("Can't get characteristics from\n>>>>>\n$result\n<<<<<\n");
        }

        return $dRes;
    }

    /**
     * Удаляет лишнее из строки.
     */
    public static function normalizeString($str)
    {
        $strMod = mb_strtolower(trim($str, ": \t\n\r\0\x0B"), 'utf-8');
        $strMod = preg_replace('\s+', ' ', $strMod);
        return $strMod;
    }

    public function getNormCharacteristics()
    {
        $chars = [];  // lowercase names

        $characts = $this->getCharacteristics();
        foreach ($characts as $elem) {
            $elem['name'] = self::normalizeString($elem['name']);
            $elem['values']['val'] = self::normalizeString($elem['values']['val']);
            $chars[] = $elem;
        }

        return $chars;
    }

    public function newItemPage()
    {
        $ch = curl_init();

        $this->setCommonCurlOpt($ch);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_URL, "http://ventfabrika.su/admin/store_goods_add");

        $result = curl_exec($ch);

        if (!$result) {
            $info = $this->getCurlErrorInfo($ch);
            throw new Exception("Can't get new item page. Info:\n$info\n");
        }

        curl_close($ch);

        preg_match('/<input type="hidden" name="hash" value="(.+)"/', $result, $matches);

        if (!$matches[1]) {
            throw new Exception("Can't get hash from\n>>>>>\n$result\n<<<<<\n");
        }

        return $matches[1];
    }
}

try {
    $to = new DataTo();
    $to->login();
    $to->getNormCharacteristics();
    //$to->setImage('http://www.waltercreech.com/images/artwork/pelican.jpg');
} catch (Exception $e) {
    $msg = date(DATE_RFC822) . " >\nLine: " . $e->getLine() . "\nMessage:\n" . $e->getMessage() . "\n\n";
    error_log($msg, 3, 'error.log');
    mail('TwilightTower@mail.ru', 'Ошибка в парсере', $msg);
}

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