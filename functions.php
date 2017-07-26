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

        $qc = new QueryCreator($cid);
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
