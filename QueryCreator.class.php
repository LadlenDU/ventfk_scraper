<?php

require_once('DataReader.class.php');

class QueryCreator
{
    public $params = [];

    public function __construct($type)
    {
        $to = new DataReader();
        $to->login();
        $hash = $to->newItemPage();
        //$to->getNormCharacteristics();

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
        $this->params['form[property][8d6ffd58][art_number]'] = '';
        $this->params['form[property][8d6ffd58][cost_now]'] = '0,00';
        $this->params['form[property][8d6ffd58][cost_old]'] = '0,00';
        $this->params['form[property][8d6ffd58][cost_supplier]'] = '0,00';
        $this->params['form[property][8d6ffd58][description]'] = '';
        $this->params['form[property][8d6ffd58][prop][ffd58855][name]'] = 1491308;
        $this->params['form[property][8d6ffd58][prop][ffd58855][new_name]'] = '';
        $this->params['form[property][8d6ffd58][prop][ffd58855][new_value]'] = '';
        $this->params['form[property][8d6ffd58][prop][ffd58855][value]'] = 6809740;
        $this->params['form[property][8d6ffd58][rest_value]'] = 1;
        $this->params['form[property][8d6ffd58][rest_value_measure_id]'] = 1;
        $this->params['hash'] = $hash;
    }
}