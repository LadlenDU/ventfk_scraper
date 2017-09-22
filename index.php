<?php

header('Content-Type: text/html; charset=utf-8');

try {
    require_once('configCommon.php');
    require_once('config.php');

    require_once('DataReader.class.php');
    require_once('QueryCreator.class.php');

    require_once('functions.php');

    if (!empty($_POST['cid'])) {

        if ($_POST['site'] == 'iclim.ru') {
            require_once('parse.iclim.ru.php');
        } elseif ($_POST['site'] == 'rusklimat.ru') {
            require_once('parse.rusklimat.ru.php');
        } else {
            die('wrong site');
        }

        exit;
    }

    $dr = new DataReader();
    $dr->login();
    $catalog = $dr->getCatalogJs();

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

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Парсер</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.2.1/themes/default/style.min.css"/>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/1.12.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.2.1/jstree.min.js"></script>
    <script src="/jquery.cookie.js"></script>

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
        <label>Сайт:
            <select name="site">
                <option value="iclim.ru">iclim.ru</option>
                <option value="rusklimat.ru">rusklimat.ru</option>
            </select>
        </label><br><br>

        <label>E-mail отчета:<br><input type="text" name="email" value="<?php echo htmlspecialchars(EMAIL); ?>"
                                        style="width:100%"></label><br><br>
        <label>Название категории:<br><input type="text" name="cat_name" style="width:100%;background-color:#EEE"
                                             readonly="readonly"></label><br>
        <label>CID (размещение):<br><input type="text" name="cid" style="width:100%;background-color:#EEE"
                                           readonly="readonly"></label><br><br>
        <label><input type="checkbox" id="parse_brands_check" name="parse_brands">Парсить бренды</label><br>
        <label><span id="parse_brands_label"></span><br><input type="text" name="url"
                                                               style="width:100%"></label>

        <br><br>

        <select name="search_type">
            <option value="search_in_folder_to_put">Искать совпадения только в разделе где идет размещение</option>
            <option value="search_everywhere">Искать совпадения везде (во всех разделах)</option>
            <option value="no_search">Не искать совпадения</option>
        </select>

        <br><br>

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
            });

        $("#parse_brands_check").click(function (e) {
            //e.preventDefault();
            setBrandsAttrs();
            //return false;
        });

        if ($.cookie('use_brands_page') == 'yes') {
            $("#parse_brands_check").prop("checked", true);
        } else {
            $("#parse_brands_check").prop("checked", false);
        }
        setBrandsAttrs();

        var site = $.cookie('site');
        if (site) {
            $('[name="site"] [value="' + site + '"]').prop('selected', true);
        }
        $('[name="site"]').click(function () {
            var cookieOptions = {expires: 70, path: '/'};
            $.cookie('site', $(this).val(), cookieOptions);
        });
    });

    function setBrandsAttrs() {
        var cookieOptions = {expires: 70, path: '/'};
        if ($("#parse_brands_check").prop("checked")) {
            $("#parse_brands_label").text('Url донора (со списком брендов):');
            $.cookie('use_brands_page', 'yes', cookieOptions);
        } else {
            $("#parse_brands_label").text('Url донора (первая страница):');
            $.cookie('use_brands_page', 'no', cookieOptions);
        }
    }

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
