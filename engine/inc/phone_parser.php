<?php

if( ! defined( 'DATALIFEENGINE' ) ) {
    die( "Hacking attempt!" );
}

if( $member_id['user_group'] != 1 ) {
    msg( "error", $lang['addnews_denied'], $lang['db_denied'] );
}

if($action == 'download'){
    require_once('PhoneParser.php');
    $phone_parser = new PhoneParser($db, null);
    $phone_parser->get_txt();
}

$inserted = 0;
if($action == 'upload' && $_SERVER['REQUEST_METHOD'] == 'POST'){

    if($_FILES["html_file"]['error'] > 0){
        throw new \Exception('Error: ' . $_FILES['html_file']['error']);
        die();
    }

    if($_FILES['html_file']['type'] !== 'application/zip'){
        throw new \Exception('Uploaded file has to be a zip archive with single html page inside.');
        die();
    }

    require_once('PhoneParser.php');
    $phone_parser = new PhoneParser($db, $_FILES['html_file']['tmp_name']);
    $phone_parser->parse();
    $inserted = $phone_parser->save();
}

$total = 0;
$db->query("SELECT COUNT(phone) as total FROM " . PREFIX . "_megafon_parser;");
while($row = $db->get_row()){
    $total = $row['total'];
}

echoheader("", "");

echo <<<HTML
<h1>Парсер детализации Мегафон</h1>
HTML;
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    echo "<p>В базу было внесено $inserted записей.</p>";
}
echo <<<HTML
<p>На данный момент в базе $total записей.</p>
<form action="$PHP_SELF?mod=phone_parser&action=upload" method="post" enctype="multipart/form-data">
    <label for="html_file">Файл детализации(zipped html|mht): </label>
    <input name="html_file" id="html_file" type="file"><br>
    <input type="submit" value="Загрузить"/>
</form>
<a href="$PHP_SELF?mod=phone_parser&action=download">Выгрузить в txt.</a>
HTML;


echofooter();