<?php

if( ! defined( 'DATALIFEENGINE' ) ) {
    die( "Hacking attempt!" );
}

if( $member_id['user_group'] != 1 ) {
    msg( "error", $lang['addnews_denied'], $lang['db_denied'] );
}

if($action == 'save_options'){
    $author = $db->safesql(strip_tags(trim($_REQUEST['author'])));
    $org_name = $db->safesql(strip_tags(trim($_REQUEST['org_name'])));
    $category = $db->safesql(strip_tags(trim($_REQUEST['category'])));
    $contact_info = $db->safesql(strip_tags(trim($_REQUEST['contact_info'])));
    $default_link = $db->safesql(strip_tags(trim($_REQUEST['link_href'])));
    $keywords = $db->safesql(strip_tags(trim($_REQUEST['keywords'])));
    $rest_link = $db->safesql(strip_tags(trim($_REQUEST['rest_link'])));
    $stop_tags = $db->safesql(strip_tags(trim($_REQUEST['stop_tags'])));


    if($author != '' && $org_name != ''){
        $query = "INSERT INTO " . PREFIX . "_cheaptrip_options VALUES (1, '$author', '$org_name', '$category', '$contact_info', '$default_link', '$keywords', '$rest_link', '$stop_tags') ON DUPLICATE KEY UPDATE author='$author', org_name='$org_name', default_category='$category', contact_info='$contact_info', default_link='$default_link', keywords='$keywords', rest_link='$rest_link', stop_tags='$stop_tags'";
        $db->query($query);
    }
    clear_cache();
    header( "Location: http://" . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . "?mod=cheaptrip_parser" );
}

if($action == 'run'){
    require_once('CheapTripParser.php');
    include_once ENGINE_DIR . '/classes/parse.class.php';

    $parse = new ParseFilter( Array (), Array (), 1, 1 );
    $ctp = new CheapTripParser(
        $db,
        $parse
    );
    $ctp->get_data();
    //$ctp->test();
    die('run');
}


$author = '';
$org_name = '';
$category = '';
$contact_info = '';
$db->query("SELECT * FROM " . PREFIX . "_cheaptrip_options LIMIT 1");
while($row = $db->get_array()){
    $author = $row['author'];
    $org_name = $row['org_name'];
    $category = $row['default_category'];
    $contact_info = $row['contact_info'];
    $default_link = $row['default_link'];
    $keywords = $row['keywords'];
    $rest_link = $row['rest_link'];
    $stop_tags = $row['stop_tags'];
}
$categories = array();
$db->query("SELECT id, name FROM " . PREFIX . "_category");
while($row = $db->get_array()){
    $categories[$row['id']] = $row['name'];
}




echoheader("", "");

echo <<<HTML
    <h2>Настройки модуля</h2>
    <form action="$PHP_SELF?mod=cheaptrip_parser&action=save_options" method="post">
        <table>
            <tr>
                <td>
                    <label for="author">Автор: </label>
                </td>
                <td>
                    <input type="text" name="author" id="author" value="$author" style="width: 100%"/>
                </td>
            </tr>
            <tr>
                <td>
                    <label for="org_name">Название организации: </label>
                </td>
                <td>
                    <input type="text" name="org_name" id="org_name" value="$org_name"  style="width: 100%"/>
                </td>
            </tr>
            <tr>
                <td>
                    <label for="category">Категория по-умолчанию: </label>
                </td>
                <td>
                    <select name="category" id="category"  style="width: 100%">
HTML;
                        foreach($categories as $k => $v){
                            $option = "<option value='$k'";
                            if($k == $category){
                                $option .= "selected";
                            }
                            $option .= ">$v</option>";
                            echo $option;
                        }
echo <<<HTML
                    </select>
                </td>
            </tr>
            <tr>
                <td>
                    <label for="contact_info">Контактная информация</label>
                </td>
                <td>
                    <textarea id="contact_info" name="contact_info" rows="10" cols="50">$contact_info</textarea>
                </td>
            </tr>
            <tr>
                <td><label for="link_href">Ссылка по-умолчанию: </label></td>
                <td><input type="text" id="link_href" name="link_href" value="$default_link"  style="width: 100%"/></td>
            </tr>
            <tr>
                <td><label for="keywords">Ключевые слова для ссылки: </label></td>
                <td><input type="text" id="keywords" name="keywords" value="$keywords"  style="width: 100%"/></td>
            </tr>
            <tr>
                <td><label for="rest_link">Остальные ссылки: </label></td>
                <td><input type="text" id="rest_link" name="rest_link" value="$rest_link"  style="width: 100%"/></td>
            </tr>
            <tr>
                <td><label for="stop_tags">Блокируемые теги: </label></td>
                <td><input type="text" id="stop_tags" name="stop_tags" value="$stop_tags"  style="width: 100%"></td>
            </tr>
            <tr>
                <td colspan="2">
                    <input type="submit" value="Сохранить"/>
                </td>
            </tr>
        </table>
    </form>
    <a href="$PHP_SELF?mod=cheaptrip_parser&action=run">Выполнить парсинг</a>
HTML;

echofooter();
?>