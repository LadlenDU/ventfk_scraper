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
        $strMod = mb_strtolower(trim($str, ": \t\n\r\0\x0B"), 'utf-8');
        $strMod = preg_replace('/\s+/', ' ', $strMod);
        return $strMod;
    }

    public function getNormCharacteristics()
    {
        $chars = [];  // lowercase names

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
}
