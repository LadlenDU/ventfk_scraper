<?php

header('Content-Type: text/html; charset=utf-8');

require_once('configCommon.php');
require_once('config.php');

require_once('DataReader.class.php');
require_once('QueryCreator.class.php');

require_once('functions.php');

if (!empty($_POST['cid'])) {

    if (!empty($_POST['parse_brands'])) {
        $url = trim($_POST['url']);
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = false;
        $load = $dom->loadHTMLFile($url);
        $xpath = new DOMXPath($dom);
        //div.bx-filter-parameters-box-title span.bx-filter-parameters-box-hint
        //Бренд >
        //div.bx-filter-block
        //bx-filter-input-checkbox
        //div.bx-filter-parameters-box:nth-child(3) > div:nth-child(3) > div:nth-child(1)
        //html.bx-core.bx-no-touch.bx-no-retina.bx-firefox body div#main div#content div div div.container.catalog_list_page div.row div.col-lg-3.col-md-3.col-sm-14.col-xs-14.catalog_left_collumn div.bx-sidebar-block div.slide_block div.slide_block_content div.bx-filter div.bx-filter-section form.smartfilter div.bx-filter-parameters-box.bx-active div.bx-filter-block div.bx-filter-parameters-box-container
        //$items = $xpath->query("//div[@class='bx-filter-parameters-box-title']/span[@class='bx-filter-parameters-box-hint'][contains(text(),'Бренд')]");
        //$xpath->query("//div[@class='bx-filter-parameters-box-title']/span[@class='bx-filter-parameters-box-hint'][contains(text(),'Бренд')]/parent::div/following::div[@class='bx-filter-block'][1]//span[@class='bx-filter-input-checkbox']")
        $rootXPath = "//div[@class='bx-filter-parameters-box-title']/span[@class='bx-filter-parameters-box-hint'][contains(text(),'Бренд')]/parent::div/following::div[@class='bx-filter-block'][1]//span[@class='bx-filter-input-checkbox']";
        if ($brandsRoot = $xpath->query($rootXPath)) {
            foreach ($brandsRoot as $brand) {
                $imgElem = $xpath->query("./span[@class='bx-filter-param-text']/text()", $brand)->item(0)->textContent;
                $imgElem = trim($imgElem);
            }
        }

        /*$nameXPath = $rootXPath . "/span[@class='bx-filter-param-text']/text()";
        $items = $xpath->query($nameXPath);
        print_r($items);
        exit;*/
    } else {
        //parseBrand($_POST['url'], $_POST['cid'], $_POST['percent'], $_POST['cat_name'], $_POST['email']);
    }
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
        <label>E-mail отчета:<br><input type="text" name="email" value="<?php echo htmlspecialchars(EMAIL); ?>"
                                        style="width:100%"></label><br><br>
        <label>Название категории:<br><input type="text" name="cat_name" style="width:100%;background-color:#EEE"
                                             readonly="readonly"></label><br>
        <label>CID (размещение):<br><input type="text" name="cid" style="width:100%;background-color:#EEE"
                                           readonly="readonly"></label><br><br>
        <label><input type="checkbox" id="parse_brands_check" name="parse_brands">Парсить бренды</label><br>
        <label><span id="parse_brands_label"></span><br><input type="text" name="url"
                                                               style="width:100%"></label><br><br>
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
            e.preventDefault();
            setBrandsAttrs();
            return false;
        });

        if ($.cookie('use_brands_page') == 'yes') {
            $("#parse_brands_check").prop("checked", true);
        } else {
            $("#parse_brands_check").prop("checked", false);
        }
        setBrandsAttrs();
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
