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

    /**
     * Обход защиты DDOS
     *
     * @param string $result тело ответа
     */
    protected function getRedirectPageParameters($result)
    {
        preg_match('#<script>window.location="(.+)";</script>#', $result, $matches);
        if (empty($matches[1])) {
            throw new Exception("Can't get redirect address from\n>>>>>\n$result\n<<<<<\n");
        }

        return $matches[1];
    }

    protected function loadLoginPage($params = false)
    {
        $ch = curl_init();

        $this->setCommonCurlOpt($ch);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_URL, "https://storeland.ru/user/login" . ($params ? $params : ''));

        $result = curl_exec($ch);

        if (!$result) {
            $info = $this->getCurlErrorInfo($ch);
            throw new Exception("Can't load login page. Info:\n$info\n");
        }

        curl_close($ch);

        preg_match('/<input type="hidden" name="hash" value="(.+)"/', $result, $matches);

        if (empty($matches[1])) {
            if ($params) {
                throw new Exception("Can't get hash from\n>>>>>\n$result\n<<<<<\n");
            } else {
                if ($foundParams = $this->getRedirectPageParameters($result)) {
                    return $this->loadLoginPage($foundParams);
                }
            }
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

    protected function loginToLoginPage($hash, $params = false)
    {
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
        curl_setopt($ch, CURLOPT_URL, "https://storeland.ru/user/login" . ($params ? $params : ''));

        $result = curl_exec($ch);

        if (!$result) {
            $info = $this->getCurlErrorInfo($ch);
            throw new Exception("Can't login to storeland. Info:\n$info\n");
        }

        curl_close($ch);

        preg_match('/<input type=hidden name="sess_id" value="(.+)"/', $result, $matches);

        if (empty($matches[1])) {
            if ($params) {
                throw new Exception("Can't get sess_id from\n>>>>>\n$result\n<<<<<\n");
            } else {
                if ($foundParams = $this->getRedirectPageParameters($result)) {
                    return $this->loginToLoginPage($hash, $foundParams);
                }
            }
        }

        $sessId = $matches[1];

        preg_match('/<input type=hidden name="sess_hash" value="(.+)"/', $result, $matches);
        if (empty($matches[1])) {
            throw new Exception("Can't get sess_hash from\n>>>>>\n$result\n<<<<<\n");
        }
        $sessHash = $matches[1];

        return array('sessId' => $sessId, 'sessHash' => $sessHash);
    }

    public function login()
    {
        unlink($this->cookie);  // не оптимально но так проще

        $hash = $this->loadLoginPage();

        $sessParams = $this->loginToLoginPage($hash);

        $this->loadVentfabricaLoginPage($sessParams['sessId'], $sessParams['sessHash']);
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

        //$cImage = new CURLFile($tmpName, mime_content_type($tmpName), $fName);
        $data = array('form[ajax_images][]' => ('@' . $tmpName), //$cImage,
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

    public function ifItemExists($name, $cid = 'all_goods')
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
                'id' => $cid,
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

        return $vars . "\n" . $matches[0];
    }

    public function getSublist($cid)
    {
        $cid = trim($cid);
        //$url = 'http://ventfabrika.su/admin/store#,' . $cid;
        $url = 'http://ventfabrika.su/admin/store_catalog';

        $ch = curl_init();

        $params = array(
            'method' => 'cat-data',
            'request_type' => 'store_catalog',
            'page' => 0,
            'id' => $cid,
            'version' => $this->searchVersion,
            'ajax_q' => 1,
        );

        $this->setCommonCurlOpt($ch);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_URL, $url);

        $result = curl_exec($ch);

        if (!$result) {
            $info = $this->getCurlErrorInfo($ch);
            throw new Exception("Can't find item (getSublist). Info:\n$info\n");
        }

        curl_close($ch);

        $resultArr = json_decode($result, true);
        if (!$resultArr) {
            throw new Exception('Неверный Json (getSublist): ' . $result);
        }

        if (empty($resultArr['data'])) {
            throw new Exception('Отсутствует необходимый элемент "data" в Json: ' . $result);
        }

        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = false;
        $load = $dom->loadHTML($resultArr['data']);
        $xpath = new DOMXPath($dom);

        $brandsRet = [];

        //$brands = $xpath->query("//ul[@class='nested_categories']/li/a[2]/text()");
        $brands = $xpath->query("//ul[@class='nested_categories']/li/a[2]");
        if ($brands) {
            foreach ($brands as $b) {
                $onclick = $b->getAttribute('onclick');
                preg_match('/(cid_.*)\s/U', $onclick, $matches);
                $key = $matches[1];
                if (!$key) {
                    throw new Exception('Не могу получить ключ. Cid: ' . $cid . '; result: ' . $result);
                }
                $brandsRet[$key] = trim($b->textContent);
            }
        } else {
            throw new Exception('Ошибка поисков брендов. Cid: ' . $cid . '; result: ' . $result);
        }

        return $brandsRet;
    }

    /**
     * Создать подэлемент.
     *
     * @param string $cid cid элемента, под которым надо создать
     * @param string $name название создаваемого элемента
     * @throws Exception
     */
    public function createSubelement($cid, $name)
    {
        $cid = trim($cid);
        $url = 'http://ventfabrika.su/admin/store_catalog';

        $cycleCount = 0;
        for (; ;) {
            $ch = curl_init();

            $params = array(
                'method' => 'create',
                'request_type' => 'store_catalog',
                'type' => 'inside',
                //'title' => $name,
                'ref_id' => $cid,
                'version' => $this->searchVersion,
                'ajax_q' => 1,
            );

            $this->setCommonCurlOpt($ch);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_URL, $url);

            $result = curl_exec($ch);

            if (!$result) {
                $info = $this->getCurlErrorInfo($ch);
                throw new Exception("Can't create item (createSubelement). Info:\n$info\n");
            }

            $resultArr = json_decode($result, true);
            if (!$resultArr) {
                throw new Exception('Неверный Json (createSubelement): ' . $result);
            }

            if (isset($resultArr['status']) && $resultArr['status'] == 'reload') {
                if (!empty($resultArr['version'])) {
                    $this->searchVersion = $resultArr['version'];
                } else {
                    $this->login();
                }
                if ($cycleCount++ < 3) {
                    curl_close($ch);
                    continue;
                }
            }

            if (empty($resultArr['status']) || $resultArr['status'] != 'ok') {
                throw new Exception('Ошибка при создании объекта (createSubelement): ' . $result);
            }

            break;
        }

        // TODO: Теперь надо переименовать (такая особенность системы)

        $newElementCid = 'cid_' . $resultArr['object']['id'];

        $params = array(
            'method' => 'rename',
            'request_type' => 'store_catalog',
            'id' => $newElementCid,
            'title' => $name,
            'version' => $resultArr['version'],
            'ajax_q' => 1,
        );

        $this->setCommonCurlOpt($ch);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_URL, $url);

        $result = curl_exec($ch);

        if (!$result) {
            $info = $this->getCurlErrorInfo($ch);
            throw new Exception("Can't rename item (createSubelement). Info:\n$info\n");
        }

        $resultArr = json_decode($result, true);
        if (!$resultArr) {
            throw new Exception('Неверный Json при rename (createSubelement): ' . $result);
        }

        if (empty($resultArr['status']) || $resultArr['status'] != 'ok'
            || empty($resultArr['version'])
        ) {
            throw new Exception('Ошибка при переименовании объекта (createSubelement): ' . $result);
        }

        $this->searchVersion = $resultArr['version'];

        curl_close($ch);

        return $newElementCid;
    }
}
