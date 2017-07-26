<?php

class DataReader
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

        $data = array(
            'sess_id' => $sessId,
            'sess_hash' => $sessHash,
        );

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

        $data = array(
            'act' => 'login',
            'action_to' => 'http://storeland.ru/',
            'site_id' => '',
            'to' => '',
            'hash' => $hash,
            'form[user_mail]' => STORELAND_LOGIN,
            'form[user_pass]' => STORELAND_PASSWORD,
        );

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
        $this->findSearchVersion();
    }

    protected function findSearchVersion()
    {
        $ch = curl_init();

        $this->setCommonCurlOpt($ch);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_URL, "http://ventfabrika.su/admin/store#,all_goods");

        $result = curl_exec($ch);

        if (!$result) {
            $info = $this->getCurlErrorInfo($ch);
            throw new Exception("Can't load search goods page. Info:\n$info\n");
        }

        curl_close($ch);

        preg_match("/,version: '(.+)'/", $result, $matches);
        $this->searchVersion = $matches[1];
        if (!$this->searchVersion) {
            throw new Exception("Can't find search version. Page:\n$result\n");
        }
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
        $data = array('form[ajax_images][]' => $cImage,
            'ajax_q' => 1,
            'form[goods_id]' => 'NaN',
            //'form[images_ids][0]' => 'img_699860198617'
            'form[images_ids][0]' => $imageId,
        );

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

    public static function genRndKey($length = 8)
    {
        $num = '';
        for ($i = 0; $i < $length; ++$i) {
            $num .= mt_rand(0, 9);
        }
        return $num;
    }

    public function getCharacteristics()
    {
        $ch = curl_init();

        $this->setCommonCurlOpt($ch);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_URL, "http://ventfabrika.su/json/attr?" . self::genRndKey());

        $result = curl_exec($ch);

        if (!$result) {
            $info = $this->getCurlErrorInfo($ch);
            throw new Exception("Can't get add goods page. Info:\n$info\n");
        }

        curl_close($ch);

        $result = substr($result, 21);

        $dRes = json_decode($result, true);
        if (!$dRes) {
            throw new Exception("Can't get characteristics from\n>>>>>\n$result\n<<<<<\n");
        }

        return $dRes;
    }

    /**
     * Удаляет лишнее из строки.
     */
    public static function normalizeString($str)
    {
        //$strMod = mb_strtolower(trim($str, ": \t\n\r\0\x0B"), 'utf-8');
        $strMod = trim($str, ": \t\n\r\0\x0B");
        $strMod = preg_replace('/\s+/u', ' ', $strMod);
        $strMod = preg_replace('/^\s*/u', '', $strMod);
        $strMod = preg_replace('/\s*$/u', '', $strMod);
        return $strMod;
    }

    public function getNormCharacteristics()
    {
        $chars = array();  // lowercase names

        $characts = $this->getCharacteristics();
        foreach ($characts as $elem) {
            $elem['name'] = self::normalizeString($elem['name']);
            foreach ($elem['values'] as $key => $val) {
                $elem['values'][$key]['val'] = self::normalizeString($val['val']);
            }
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

    public function postNewItem($params)
    {
        $ch = curl_init();

        $this->setCommonCurlOpt($ch);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_URL, "http://ventfabrika.su/admin/store_goods_add/");

        $result = curl_exec($ch);

        if (!$result) {
            $info = $this->getCurlErrorInfo($ch);
            throw new Exception("Can't post new item. Info:\n$info\n");
        }

        curl_close($ch);
    }

    public function ifItemExists($name)
    {
        $cycleCount = 0;
        for (; ;) {
            $ch = curl_init();

            $params = array(
                'method' => 'cat-data',
                'request_type' => 'store_catalog',
                'only_data' => 1,
                'per_page' => 5000,
                'page' => 0,
                'search_q' => $name,
                'id' => 'all_goods',
                //'version' => 'ddd187',
                'version' => $this->searchVersion,
                'ajax_q' => 1,
            );

            $this->setCommonCurlOpt($ch);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_URL, 'http://ventfabrika.su/admin/store_catalog');

            $result = curl_exec($ch);

            if (!$result) {
                $info = $this->getCurlErrorInfo($ch);
                throw new Exception("Can't find item. Info:\n$info\n");
            }

            curl_close($ch);

            $resultDec = json_decode($result, true);
            if (isset($resultDec['status']) && $resultDec['status'] == 'reload') {
                //$this->login();
                if (!empty($resultDec['version'])) {
                    $this->searchVersion = $resultDec['version'];
                } else {
                    $this->login();
                }
                if ($cycleCount++ < 3) {
                    continue;
                }
            }

            break;
        }

        if (!isset($resultDec['data'])) {
            throw new Exception("Не найден необходимый элемент 'data'. \$resultDec:\n$resultDec\n");
        }

        if (strpos($resultDec['data'], 'Не найдено ни одного товара') !== false) {
            return false;
        }

        // Иногда находит неправильно - поищем совпадения
        $nameA = $name . '</a>';
        if (strpos($resultDec['data'], $nameA) === false) {
            return false;
        }

        return true;
    }

    public function getCatalogJs()
    {
        $ch = curl_init();

        $this->setCommonCurlOpt($ch);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_URL, "http://ventfabrika.su/admin/store#,all_goods");

        $result = curl_exec($ch);

        if (!$result) {
            $info = $this->getCurlErrorInfo($ch);
            throw new Exception("Can't get catalog. Info:\n$info\n");
        }

        curl_close($ch);


        preg_match('/var\s+w1.+;/Usu', $result, $matches);
        $vars = $matches[0];
        preg_match("/eval\('var goodsCatalog = .+'\);/Usu", $result, $matches);

        //preg_match('/<script type="text\/javascript">\s*\$\(document\).ready\(function\(\)\{\s*var\s+w1.+<\/script>/Usu', $result, $matches);
        //preg_match('/<script type="text\/javascript">\s*\$\(document\)\.ready\(function\(\)\{\s*var\s+w1.+<\//U', $result, $matches);
        return $vars . "\n" . $matches[0];

        /*preg_match("/eval\('var goodsCatalog = (.*)'\)/U", $result, $matches);
        if (!$matches[0]) {
            throw new Exception("Can't get 'var goodsCatalog'. Result:\n$result\n");
        }*/

        /* $replacedResult = str_replace('" + "', '', $matches[1]);

         $w1 = json_encode(' <i title="Индекс отражает количество товаров, находящихся в категории склада «');
         $w2 = json_encode('»" class="JsTreeGoodsIndex">');
         $c1 = json_encode(' <i title="Индекс отражает количество товаров, размещенных в товарной категории «');
         $c2 = json_encode('»" class="JsTreeGoodsIndex">');

         $replacedResult = preg_replace('/"\s*\+\s*w1\s*\+\s*"/U', substr($w1, 1, -1), $replacedResult);
         $replacedResult = preg_replace('/"\s*\+\s*w2\s*\+\s*"/U', substr($w2, 1, -1), $replacedResult);
         $replacedResult = preg_replace('/"\s*\+\s*c1\s*\+\s*"/U', substr($c1, 1, -1), $replacedResult);
         $replacedResult = preg_replace('/"\s*\+\s*c2\s*\+\s*"/U', substr($c2, 1, -1), $replacedResult);

         $replacedResultDecoded = json_decode($replacedResult, true);

         $dom = new DOMDocument;
         $dom->preserveWhiteSpace = false;
         $dom->loadHTML($matches[1]);
         $xpath = new DOMXPath($dom);

         $catalogHtmlLink = $xpath->query("//li[@id='root']")->item(0);
         $catalogHtml = $dom->loadHTML($catalogHtmlLink);
         $xpath = new DOMXPath($dom);*/

        curl_close($ch);

        return $matches[0];
    }
}
