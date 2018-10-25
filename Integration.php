<?
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("1С Интеграция");

use Bitrix\Main\Loader;

Loader::includeModule("iblock");
Loader::includeModule("catalog");

/*Начало класса Integration*/

class Integration
{
    public function GetProductArticles($result = [])
    {
        $yvalue = 35;
        $arSort = array(
//    'SORT' => 'ASC',
//    'ID' => 'DESC',
            'PROPERTY_CML2_ARTICLE' => 'ASC',
        );
        $arSelect = Array(/*"ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM", "PREVIEW_PICTURE", "DETAIL_PICTURE", "PROPERTY_*"*/);
        $arFilter = Array(
            "IBLOCK_ID" => IntVal($yvalue),
            "PROPERTY_CML2_ARTICLE" => 521
//            "PROPERTY_CML2_ARTICLE_VALUE" => "ЮС-119-01"
//            "!PROPERTY_CML2_ARTICLE" => false
        );
        $res = CIBlockElement::GetList($arSort, $arFilter, false, Array("nPageSize" => 15000), $arSelect);
        while ($ob = $res->GetNextElement()) {
            $arFields = $ob->GetFields();
            $arProps = $ob->GetProperties();
            //формируем необходимые поля
            $article["NAME"] = $arFields["NAME"];                                                   // название
            $article["ID"] = $arFields["ID"];                                                       // ID
            $article["PREVIEW_PICTURE"] = CFile::GetPath($arFields["PREVIEW_PICTURE"]);             // Картинки А
            $article["DETAIL_PICTURE"] = CFile::GetPath($arFields["DETAIL_PICTURE"]);               // Картинки Д
            $article["ARTICLE"] = trim($arProps["CML2_ARTICLE"]["VALUE"]);                          // Артикль
            $article["PROIZVODITEL"] = $arProps["PROIZVODITEL"]["VALUE"];                           // Производитель
//            $article["SOSTAV"] = converterProperty($arProps["SOSTAV"]["VALUE_ENUM_ID"]);          // Состав
            $article["SOSTAV"] = $arProps["SOSTAV"]["VALUE"];                                       // Состав
            //$article["KOLICHESTVO"] = converterProperty($arProps["KOLICHESTVO"]["VALUE_ENUM_ID"]);  // Комплект
            $article["TSENA"] = $arProps["TSENA"]["VALUE"];                                         // Цена
            $article["TSVET"] = converterСolor($arProps["TSVET"]["VALUE"]);                         // Цвет
            $article["CML2_BASE_UNIT"] = $arProps["CML2_BASE_UNIT"]["DESCRIPTION"];                         // Колличество
            //$article["RAZMER"] = $arProps["RAZMER"]["VALUE"];                                      // Размер
            array_push($result, $article);
//            $result[] = trim($arProps["CML2_ARTICLE"]["VALUE"]);
        }
        return $result;
    }

    public function SelectTradeOffer($cnt = 1, $previousProduct = "", $nextPreviousProduct = "", $result = [])
    {
        //Получаем массив с товарами отсортированных по артиклу
        $getProductArticles = Integration::GetProductArticles();
        for ($i = 0; $i < count($getProductArticles); $i++) {
            //если $nextPreviousProduct не пустой, значит уже был создан товар с торговым предложением
            //и в этом случае мы добавляем в $previousProduct предыдущий товар
            // для того чтобы можно было сравнить артикли
            if($nextPreviousProduct != ""){
                $previousProduct = $nextPreviousProduct;
                $cnt = 2;
            }
            // добавляем в переменную $previousProduct товар, чтобы на следующем круге сравнить артикли
            if ($cnt == 1)
            {
                $previousProduct = $getProductArticles[$i];
                $cnt++;
            }
            // если предыдущий и текущий артикль одинаковые то создаём товар с тоговым предложением
            // на данном этапе создаётся товар и два торговых предложения
            elseif ($cnt == 2) {
                $nextPreviousProduct = "";
                if ($previousProduct["ARTICLE"] == $getProductArticles[$i]["ARTICLE"]) {
                    $result["TOVAR" . " " . $i] = "Товар";
                    $result["TP1" . " " . $i] = "Первое торговое предложение";

                    // создаём товар
                    $productId = Integration::AddProduct(
                        trim($previousProduct["NAME"]),
                        $previousProduct["PROIZVODITEL"],
                        $previousProduct["KOLICHESTVO"],
                        $previousProduct["ARTICLE"],
                        $previousProduct["TSVET"]
                    );
//                    AddSellingProposition($productId, $name, $price, $COLOR, $RAZMER)

//                    return $previousProduct["AKTSIYA"];
                    // создаём первое торговое предложение
                    Integration::AddSellingProposition(
                        $productId,                                 // id товара
                        $previousProduct["ARTICLE"],                // артикул
                        trim($previousProduct["NAME"]),             // Название
                        $previousProduct["PREVIEW_PICTURE"],                  // Анонс картинка
                        $previousProduct["DETAIL_PICTURE"],                  // Детальная картинка
                        (int)$previousProduct["TSENA"],           // Цена
                        $previousProduct["TSVET"],               // Цвет
                        $previousProduct["RAZMER"],                  // Размер
                        $previousProduct["CML2_BASE_UNIT"]                  // Колличество товаров
                    );
                    // создаём второе торговое предложение
                    Integration::AddSellingProposition(
                        $productId,                                 // id товара
                        $getProductArticles[$i]["ARTICLE"],         // Артикль
                        trim($getProductArticles[$i]["NAME"]),     // Название
                        $getProductArticles[$i]["PREVIEW_PICTURE"],                  // Анонс картинка
                        $getProductArticles[$i]["DETAIL_PICTURE"],                  // Детальная картинка
                        (int)$getProductArticles[$i]["TSENA"],   // Цена
                        $getProductArticles[$i]["TSVET"],       // Цвет
                        $getProductArticles[$i]["RAZMER"],                  // Размер
                        $getProductArticles[$i]["CML2_BASE_UNIT"]                  // Колличество товаров
                    );
//                    die();
//
                    $previousProduct = $getProductArticles[$i];
                    $cnt++;
                } else {
                    $cnt = 1;
                }
            }
            // проверяем текущий и предыдущий артикль
            // если атрикли совпадают то создаём ещё торговое предложение, и так до тех пор пока артикли совпадают
            elseif ($cnt == 3) {
                if ($previousProduct["ARTICLE"] == $getProductArticles[$i]["ARTICLE"]) {
                    Integration::AddSellingProposition(
                        $productId,                                 // id товара
                        $getProductArticles[$i]["ARTICLE"],         //Артикль
                        trim($getProductArticles[$i]["NAME"]),     // Название
                        $getProductArticles[$i]["PREVIEW_PICTURE"],                  // Анонс картинка
                        $getProductArticles[$i]["DETAIL_PICTURE"],                  // Детальная картинка
                        (int)$getProductArticles[$i]["TSENA"],   // Цена
                        $getProductArticles[$i]["TSVET"],       // Цвет
                        $getProductArticles[$i]["RAZMER"],                  // Размер
                        $getProductArticles[$i]["CML2_BASE_UNIT"]                  // Колличество товаров
                    );

                    $previousProduct = $getProductArticles[$i];
                } else {
                    $nextPreviousProduct = $getProductArticles[$i];
                    $cnt=1;
                }
            }
        }
        return $result;
    }

    public function AddProduct($name, $STRANA, $KOLICHESTVO, $ARTICLE, $TSVET)
    {
//        $detailPicture = CFile::MakeFileArray($_SERVER["DOCUMENT_ROOT"] . "/android-chrome-192x192.png");

        // ID инфоблока
        $IBlock_ID = 25;
        // ID раздела
        //$section = 321;
        // ИМЯ
//        $name = "Тестовое имя";
        //транслируем строки текста
        $arParams = array("replace_space" => "-", "replace_other" => "-");
        $code = Cutil::translit($name, "ru", $arParams);
        //Артикул
        $artnumber = 220;

        //массив с пользовательскими свойствами
        $arOfferProps = array(
            228 => $STRANA, //  производитель
            231 => $ARTICLE, // артикль
            230 => $KOLICHESTVO, // колличество в комплекте
            225 => $TSVET, // состав
        );

        //собираем массив полей
        $arFields = Array(
            "IBLOCK_ID" => $IBlock_ID,
            //    "IBLOCK_SECTION_ID" => $section,
            "NAME" => $name,
            "CODE" => $code,
            "ACTIVE" => "Y",
            "DETAIL_TEXT_TYPE" => "html",
//            "DETAIL_TEXT" => $text,
//            "DETAIL_PICTURE" => $detailPicture,
            "PROPERTY_VALUES" => $arOfferProps
        );

        // создаем объект класса для работы
        $obElement = new CIBlockElement();
        // добавляем элемент, а ели не получается, то выводим ошибку
        $ID = $obElement->Add($arFields);
        if ($ID < 1) {
            echo $obElement->LAST_ERROR;
        }
        return $ID;


        $arFields = Array(
            "PRODUCT_ID" => $ID,
            "CATALOG_GROUP_ID" => 1,
            "PRICE" => 29.95,
            "CURRENCY" => "USD"
        );

//        CPrice::Add($arFields);
    }

    public function AddSellingProposition($productId, $ARTICLE, $name, $previewPicture, $detailPicture, $price, $COLOR, $RAZMER, $quantity)
    {
        $IBlockOffersCatalogId = 26; // ID инфоблока предложений (должен быть торговым каталогом)

        $arCatalog = CCatalog::GetByID($IBlockOffersCatalogId);

        $SKUPropertyId = $arCatalog['SKU_PROPERTY_ID']; // ID свойства в инфоблоке предложений типа "Привязка к товарам (SKU)"
//        $productId = 20275; // добавили товар, получили ID

        $obElement = new CIBlockElement();

//        $COLOR = "purple";
//        $RAZMER = 3;
        // свойства торгвоого предложения
        $arOfferProps = array(
            $SKUPropertyId => $productId,
            216 => $ARTICLE, // артикул
            226 => $COLOR, // цвет
            234 => $RAZMER, // размер
        );
//        $name = "Скуби ду";
//        $price = 700;

        $arParams = array("replace_space" => "-", "replace_other" => "-");
        $code = Cutil::translit($name, "ru", $arParams);
        $previewPicture = CFile::MakeFileArray($previewPicture);
        $detailPicture = CFile::MakeFileArray($detailPicture);
        $arOfferFields = array(
            'NAME' => $name,
            "PREVIEW_PICTURE" => $previewPicture,
            "DETAIL_PICTURE" => $detailPicture,
            "CODE" => $code,
//            "CML2_ARTICLE" => "222",
            'IBLOCK_ID' => 26,
            'ACTIVE' => 'Y',
            "CURRENCY" => "RUB",            // валюта
            "PRICE" => $price,              // значение цены
            "CATALOG_GROUP_ID" => 1,        //
            'PROPERTY_VALUES' => $arOfferProps,
        );

        // Создаём торговое предложение и получаем его ID
        $offerId = $obElement->Add($arOfferFields);

//        $price = 3700;

        $arFields = Array(
            "PRODUCT_ID" => $offerId,
            "CATALOG_GROUP_ID" => 1,
            "PRICE" => $price,
            "CURRENCY" => "RUB"
        );
        // добавляем цену торговому предложению
        CPrice::Add($arFields);

        // добавляет доступное колличество
        $quantity = array(
            "ID" => $offerId,
            "QUANTITY" => $quantity
        );
        CCatalogProduct::Add($quantity);
    }
}

//debug(Integration::AddSellingProposition(20306, "Торговое предложение_3", 3750,00, "purple", 750));

/*Конец класса Integration*/

debug(Integration::SelectTradeOffer());
//debug(Integration::AddProduct("апрапп", "STRANA", "2a1d0fb4-c234-11e8-b93f-00247e53250b", "ARTICLE", 85));
//debug(Integration::GetProductArticles());
//echo CFile::GetPath(5328);
//Integration::AddSellingProposition(
//    132734,                                 // id товара
//    700,                // артикул
//    "Любое",             // Название
//    700,           // Цена
//    "shokolad",               // Цвет
//    2                  // Размер
//);

$PRODUCT_ID = 20275;
$PRICE_TYPE_ID = 1;
// добавляем цены нашему товару
// собираем массив
//$arFields = Array(
//    "CURRENCY" => "RUB",       // валюта
//    "PRICE" => 5569,      // значение цены
//    "CATALOG_GROUP_ID" => 1,           // ID типа цены
//    "CURRENCY" => "USD",
//    "PRODUCT_ID" => $ID,  // ID товара
//);
$arFields = Array(
    "PRODUCT_ID" => $PRODUCT_ID,
    "CATALOG_GROUP_ID" => 1,
    "PRICE" => 29.95,
    "CURRENCY" => "USD"
);

//        CPrice::Add($arFields);
// добавляем
//CPrice::Add($arFields);

// Установим для товара с кодом 15 цену типа 2 в значение 29.95 USD
//$PRODUCT_ID = 9496;
//$PRICE_TYPE_ID = 1;
//
//$arFields = Array(
//    "PRODUCT_ID" => $PRODUCT_ID,
//    "CATALOG_GROUP_ID" => $PRICE_TYPE_ID,
//    "PRICE" => 29.95,
//    "CURRENCY" => "USD",
//    "QUANTITY_FROM" => 1,
//    "QUANTITY_TO" => 10
//);

//$res = CPrice::GetList(
//    array(),
//    array(
//        "PRODUCT_ID" => $PRODUCT_ID,
//        "CATALOG_GROUP_ID" => $PRICE_TYPE_ID
//    )
//);

//if ($arr = $res->Fetch())
//{
//    CPrice::Update($arr["ID"], $arFields);
//}
//else
//{
//    CPrice::Add($arFields);
//}
?>