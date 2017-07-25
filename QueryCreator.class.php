<?php

require_once('DataReader.class.php');

class QueryCreator
{
    protected $params = [];

    protected static $to;

    protected $propKey;
    protected $propKeySub;

    public function __construct($type)
    {
        if (!self::$to) {
            self::$to = new DataReader();
            self::$to->login();
        }
        $hash = self::$to->newItemPage();
        //$to->getNormCharacteristics();

        $this->propKey = self::genHexKey();
        $this->propKeySub = self::genHexKey();

        $this->params['continue'] = 1;
        $this->params['form[ajax_images][]'] = '';
        $this->params['form[goods_cat_id]'] = ',' . $type;
        $this->params['form[goods_desc_large]'] = '';
        $this->params['form[goods_desc_short]'] = '';
        $this->params['form[goods_description]'] = '';
        $this->params['form[goods_keywords]'] = '';
        $this->params['form[goods_name]'] = '';
        $this->params['form[goods_path]'] = '';
        $this->params['form[goods_seo_desc_large]'] = '';
        $this->params['form[goods_seo_desc_short]'] = '';
        $this->params['form[goods_subdomain]'] = '';
        $this->params['form[goods_title]'] = '';
        $this->params['form[open_attr]'] = 1;
        $this->params['form[open_images]'] = 1;
        $this->params['form[open_main]'] = 1;
        $this->params['form[open_mod]'] = -1;
        $this->params['form[open_placement]'] = 1;
        $this->params['form[open_seo]'] = 1;
        $this->params["form[property][$this->propKey][art_number]"] = '';
        $this->params["form[property][$this->propKey][cost_now]"] = '0,00';
        $this->params["form[property][$this->propKey][cost_old]"] = '0,00';
        $this->params["form[property][$this->propKey][cost_supplier]"] = '0,00';
        $this->params["form[property][$this->propKey][description]"] = '';
        $this->params["form[property][$this->propKey][prop][$this->propKeySub][name]"] = 1491308;
        $this->params["form[property][$this->propKey][prop][$this->propKeySub][new_name]"] = '';
        $this->params["form[property][$this->propKey][prop][$this->propKeySub][new_value]"] = '';
        $this->params["form[property][$this->propKey][prop][$this->propKeySub][value]"] = 6809740;
        $this->params["form[property][$this->propKey][rest_value]"] = 1;
        $this->params["form[property][$this->propKey][rest_value_measure_id]"] = 1;
        $this->params['hash'] = $hash;
    }

    public static function genHexKey($length = 8)
    {
        $num = '';
        for ($i = 0; $i < $length; ++$i) {
            $num .= dechex(mt_rand(0, 15));
        }
        return $num;
    }

    public function setImage($url)
    {
        $imageId = self::$to->setImage($url);
        $this->params["form[images_data_by_id][$imageId][desc]"] = '';
        $this->params["form[images_data_by_id][$imageId][id]"] = $imageId;
        $this->params["form[images_data_by_id][$imageId][main]"] = 1;
    }

    public function setName($name)
    {
        $this->params["form[goods_name]"] = $name;
        $this->params["form[goods_title]"] = $name;
    }

    public function setShortDescription($descr)
    {
        $this->params["form[goods_desc_short]"] = $descr;
        //$this->params["form[goods_seo_desc_short]"] = $descr;
        $this->params["form[goods_description]"] = $descr;
    }

    public function setFullDescription($descr)
    {
        $this->params["form[goods_desc_large]"] = $descr;
        //$this->params["form[goods_seo_desc_large]"] = $descr;
    }

    public function setPrice($price, $correctPercent) {
        $floatPrice = (float)$price;
        $modPrice = $floatPrice + ($floatPrice / 100 * (int)$correctPercent);
        $this->params["form[property][$this->propKey][cost_now]"] = $modPrice;
    }

    public function setFeature($fName, $fValue)
    {
        $nc = self::$to->getNormCharacteristics();
        $hexKey = $this->genHexKey();

        $nameFound = false;
        foreach ($nc as $char) {
            if (mb_strtolower($char['name'], 'utf-8') == mb_strtolower($fName, 'utf-8')) {
                $this->params["form[attr][$hexKey][name]"] = $char['id'];
                $this->params["form[attr][$hexKey][new_name]"] = '';

                $valFound = false;
                foreach ($char['values'] as $val) {
                    if (mb_strtolower($val['val'], 'utf-8') == mb_strtolower($fValue, 'utf-8')) {
                        $this->params["form[attr][$hexKey][new_value]"] = '';
                        $this->params["form[attr][$hexKey][value]"] = $val['id'];
                        $valFound = true;
                        break;
                    }
                }
                if (!$valFound) {
                    $this->params["form[attr][$hexKey][new_value]"] = $fValue;
                    $this->params["form[attr][$hexKey][value]"] = 0;
                }

                $nameFound = true;
                break;
            }
        }
        if (!$nameFound) {
            $this->params["form[attr][$hexKey][name]"] = 0;
            $this->params["form[attr][$hexKey][new_name]"] = $fName;
            $this->params["form[attr][$hexKey][new_value]"] = $fValue;
            $this->params["form[attr][$hexKey][value]"] = 0;
        }
    }

    public function postNewItem()
    {
        if (self::$to->ifItemExists($this->params['form[goods_name]'])) {
            return 'already_exists';
        }
        self::$to->postNewItem($this->params);
        return 'item_set';
    }
}