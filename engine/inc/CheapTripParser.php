<?php
error_reporting(E_ALL ^ E_DEPRECATED);

if( ! defined( 'DATALIFEENGINE' ) ) {
    define('DATALIFEENGINE', true);
}

if(isset($_SERVER['HTTP_HOST'])){

}else{
    $path = dirname(__FILE__);
    require_once($path . '/../classes/mysql.class.php');
    require_once($path . '/../data/dbconfig.php');
    require_once($path . '/../classes/parse.class.php');
}

class CheapTripParser {
    private $db;
    private $posts;
    private $rss_address = 'http://cheaptrip.livejournal.com/data/rss';
    private $post_table;
    private $default_autor;
    private $org_name;
    private $default_category;
    private $parse;
    private $post_right_now = 1;
    private $last_post_title;
    private $contact_info;
    private $default_link;
    private $keywords;
    private $rest_link;
    private $stop_tags;

    public function __construct($db, $pf){
        $this->db = $db;
        $this->posts = array();
        $this->post_table = PREFIX . '_post';
        $this->parse = $pf;
    }

    public function connect_db($db_user, $db_pass, $db_name, $db_location = 'localhost', $show_error=1){
        $this->db->connect($db_user, $db_pass, $db_name);
    }

    public function close_db(){
        $this->db->close();
    }

    public function query($query){
        $this->db->query($query);
    }

    public function get_data(){
        $this->db->query("SELECT id, author, org_name, default_category, contact_info, default_link, keywords, rest_link, stop_tags FROM " . PREFIX ."_cheaptrip_options LIMIT 1");
        while($row = $this->db->get_array()){
            $this->default_autor = $row['author'];
            $this->org_name = $row['org_name'];
            $this->default_category = $row['default_category'];
            $this->contact_info = $row['contact_info'];
            $this->default_link = $row['default_link'];
            $this->keywords = explode(',', $row['keywords']);
            $this->rest_link = $row['rest_link'];
            $this->stop_tags = explode(',', $row['stop_tags']);
        }

        if($this->default_autor == '' || $this->org_name == '' || $this->default_category == NULL){
            die('Set up default author and organization names in options');
        }
        $this->db->query("SELECT title FROM " . PREFIX . "_post WHERE autor='$this->default_autor' ORDER BY date DESC LIMIT 1");
            while($row = $this->db->get_row()){
                $this->last_post_title = $row['title'];
            }

        $available_categories = array();
        $this->db->query("SELECT id, name FROM " . PREFIX . "_category");
        while($row = $this->db->get_row()){
            $available_categories[$row['id']] = $row['name'];
        }

        $feed = new DOMDocument();
        $feed->load($this->rss_address);
        $items = $feed->getElementsByTagName('item');
        foreach($items as $item){
            $categories_array = $item->getElementsByTagName('category');
            $categories = array();
            $category = $this->default_category;

            foreach($categories_array as $c){
                $categories[] = $c->nodeValue;
                if(in_array($c->nodeValue, $available_categories)){
                    $category .= "," . array_search($c->nodeValue, $available_categories);
                }
            }

            $title = $this->parse->process(
                trim(
                    strip_tags($item->getElementsByTagName('title')->item(0)->nodeValue
                    )
                )
            );
            $title = $this->clear_title($title);

            if($title == $this->last_post_title){
                break;
            }

            if($this->isValid($categories, $title)){
                $pub_date = $this->parse_date(
                    $item->getElementsByTagName('pubDate')->item(0)->nodeValue
                );

                $short_story = trim(
                    $item->getElementsByTagName('description')->item(0)->nodeValue
                );
                $short_story = $this->clear_description($short_story, $this->keywords);

                $short_story = addslashes($short_story);
                $full_story = '';

                if(strlen($short_story) > 5000){
                    $pos =  strpos($short_story, '<p', 3500);
                    if($pos != 0){
                        $full_story = $short_story;
                        $short_story = substr($short_story, 0, $pos);
                    }
                }

                $short_story .= "<p class=\'contacts\'>Для бронирования обращайтесь по адресу:<br> $this->contact_info</p>";

                $alt_name = $this->slugify($title);

                $this->posts[] = new Post(
                    $this->default_autor,
                    $pub_date,
                    $short_story,
                    $full_story,
                    '',
                    $title,
                    '',
                    '',
                    $category,
                    $alt_name,
                    '',
                    implode(',', $this->clear_tags($categories)),
                    ''
                );
            }
        }
        $this->flush_posts();
        echo 'Flushed ' . count($this->posts) . " posts" . PHP_EOL;
    }

    private function flush_posts(){
        foreach($this->posts as $post){
            $query = "INSERT INTO $this->post_table (autor, date, short_story, full_story, xfields, title, descr, keywords, category, alt_name, symbol, tags, approve) VALUES('$post->autor', '$post->date', '$post->short_story', '$post->full_story', '$post->xfields', '$post->title', '$post->descr', '$post->keywords', '$post->category', '$post->alt_name', '$post->symbol', '$post->tags', $this->post_right_now)";
            $this->query($query);
        }
    }

    public function test(){
        $this->db->query("SELECT * FROM " . PREFIX . "_post WHERE id=186");
        $row = $this->db->get_row();
        $short_story = $row['short_story'];
        $full_story = '';
        if(strlen($short_story) > 3500 && strlen($short_story) < 5000){
            $pos =  strpos($short_story, '<p', 3500);
            if($pos != 0){
                $full_story = $short_story;
                $short_story = substr($short_story, 0, $pos);
            }
        }
        print_r($short_story);
        echo 'FUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUULL' . PHP_EOL;
        print_r($full_story);

    }

    private function parse_date($d){
        $date_array = date_parse($d);
        $date = new DateTime();
        $date->setDate(
            $date_array['year'],
            $date_array['month'],
            $date_array['day']
        );
        $date->setTime(
            $date_array['hour'],
            $date_array['minute'],
            $date_array['second']
        );
        return $date->format('Y-m-d H:i:s');
    }

    private function fix_encoding($s){
        return mb_convert_encoding((string)$s, 'windows-1251', 'utf8');
    }

    private function slugify($text){
        $chars = array(
            'ґ'=>'g','ё'=>'e','є'=>'e','ї'=>'i','і'=>'i',
            'а'=>'a', 'б'=>'b', 'в'=>'v',
            'г'=>'g', 'д'=>'d', 'е'=>'e', 'ё'=>'e',
            'ж'=>'zh', 'з'=>'z', 'и'=>'i', 'й'=>'i',
            'к'=>'k', 'л'=>'l', 'м'=>'m', 'н'=>'n',
            'о'=>'o', 'п'=>'p', 'р'=>'r', 'с'=>'s',
            'т'=>'t', 'у'=>'u', 'ф'=>'f', 'х'=>'h',
            'ц'=>'c', 'ч'=>'ch', 'ш'=>'sh', 'щ'=>'sch',
            'ы'=>'y', 'э'=>'e', 'ю'=>'u', 'я'=>'ya', '?'=>'e', '&'=>'and',
            'ь'=>'', 'ъ' => '',
        );

        $text = mb_strtolower($text);
        $text = strtr($text, $chars);
        $text = preg_replace('/\W/', ' ', $text);

        $text = preg_replace('/\ +/', '-', $text);

        $text = preg_replace('/\-$/', '', $text);
        $text = preg_replace('/^\-/', '', $text);

        return $text;
    }

    private function isValid($categories, $title){
        $preg = '/график работы/i';
        if(preg_match($preg, $title) > 0){
            return false;
        }
        $preg = '/wanted/i';
        if(preg_match($preg, $title) > 0){
            return false;
        }

        $category = 'предложение';
//        $exclude = array(
//            'вестник',
//            'Спецпроект Ч',
//            'лекторий',
//            'конкурсы',
//            'secondhand',
//            'отчет',
//            'вакансии',
//            'запрос',
//            'pre-market report',
//            'people-to-go',
//            'чтиво'
//        );
        $exclude = $this->stop_tags;

        if(count($categories) == 2){
            if(in_array('предложение', $categories)){
                if(in_array('Чиптрип®', $categories)){
                    return false;
                }
            }
        }

        //selects only approved categories
        if (in_array($category, $categories)){
            foreach($exclude as $e){
                if(in_array($e, $categories)){
                    return false;
                }
            }
            return true;
        }
        return false;
    }

    //clears old contacts
    private function clear_description($s, $keywords){
        //replace links by keywords
        //foreach($keywords as $keyword){
            //catch all links
            $preg = '<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>';
            $matches = array();
            /*
             * $matches - list of all links in document
             */
            if(preg_match_all("/$preg/siU", $s, $matches) > 0){
                foreach($matches[0] as $key_link => $link){
                    $preg = '/cheaptrip/i';
                    //catch all links related to cheaptrip site
                    if(preg_match($preg, $matches[2][$key_link]) > 0){
                        // replaced link flag, just to save some time and not to check other keywords for presence in one link
                        $changed = false;
                        foreach($keywords as $keyword){
                            if(!$changed){
                                //pattern for keyword look
                                $preg = "/" . $keyword . "/i";
                                //check for keyword presence
                                if(preg_match($preg, $matches[3][$key_link]) > 0){
                                    //link var
                                    $link_rpl = "<a href='" . $this->default_link . "' class='rpl'>" . $matches[3][$key_link] . "</a>";
                                    $preg = $matches[0][$key_link];
                                    $s = preg_replace("~$preg~siU", $link_rpl, $s);
                                    //set flag to true
                                    $changed = true;
                                }
                            }
                        }
                        //if not keywords are present change link to default
                        if(!$changed){
                            $preg = $matches[0][$key_link];
                            $link_rpl = "<a href='" . $this->rest_link . "' class='empty'>" . $matches[3][$key_link] . "</a>";
                            $s = preg_replace("~$preg~siU", $link_rpl, $s);
                        }
                    }
                }
/*
                $preg = '/cheaptrip/i';
                //catch all link leading to cheaptrip
                if(preg_match($preg, $matches[1]) > 0){
                    //compare with each keyword
                    foreach($keywords as $key){
                        $preg = "/" . $key . "/i";
                        if(preg_match($preg, $matches[2])){
                            $link .= $this->default_link . "' class='rpl'>" . $matches[2] . "</a>";
                            $preg = addslashes($matches[0]);
                            var_dump($preg);
                            $s = preg_replace($preg, $link ,$s);
                        }
                    }
                }
*/
            }

        //clear links
        //$preg = '/<p.*<a.*http:\/\/.*cheaptrip.*\".*<\/p>/i';
        //don't need that anymore as all cheaptrip links should be already changed
//        $preg = '/href=\"[^>]*cheaptrip[^>]*/i';
//        $s = preg_replace($preg, "class='empty' href='$this->default_link'", $s);

        //remove Покупать в
        $preg = '/<p.*Покупать.*<\/p>/i';
        $s = preg_replace($preg, "", $s);

        //remove Члены клуба
        $preg = '/<p.*Члены клуба.*<\/p>/i';
        $s = preg_replace($preg, "", $s);

        //remove Чиптрип
        $preg = '/Чиптрип/i';
        $s = preg_replace($preg, $this->org_name, $s);

        //remove чиптикет
        $preg = '/Чипти[^\s]*/i';
        $s = preg_replace($preg, 'Дешевые билеты', $s);

        //remove Регионы безнал
        $preg = '/<p.*Регионы могут забронировать.*<\/p>/i';
        $s = preg_replace($preg, '', $s);

        //remove Идеи/Маршруты
        $preg = '/<p.*идеи\/маршруты.*<\/p>/i';
        $s = preg_replace($preg, '', $s);

        //remove original club.cheaptrip() email address
        $preg = '/<p.*club\.cheaptrip\(\).*<\/p>/i';
        $s = preg_replace($preg, '', $s);

        return $s;
    }

    private function clear_title($s){
        //remove Чиптрип
        $preg = '/Чиптрип/i';
        $s = preg_replace($preg, $this->org_name, $s);

        //remove чиптикет
        $preg = '/Чипти[^\s]*/i';
        $s = preg_replace($preg, 'Дешевые билеты', $s);

        return $s;
    }

    private function clear_tags($tags){
        $preg = '/Чиптрип/i';
        $new_tags = array();
        foreach($tags as $tag){
            $tag = preg_replace($preg, $this->org_name, $tag);
            $new_tags[] = $tag;
        }
        return $new_tags;
    }
}

class Post{
    public $autor;
    public $date;
    public $short_story;
    public $full_story;
    public $xfields;
    public $title;
    public $descr;
    public $keywords;
    public $category;
    public $alt_name;
    public $symbol;
    public $tags;
    public $metatitle;

    public function __construct($autor, $date, $short_story, $full_story, $xfields, $title, $descr, $keywords, $category, $alt_name, $symbol, $tags, $metatitle){
        $this->autor = $autor;
        $this->date = $date;//->format('Y-m-d H:i:s');
        $this->short_story = $short_story;
        $this->full_story = $full_story;
        $this->xfields = $xfields;
        $this->title = $title;
        $this->descr = $descr;
        $this->keywords = $keywords;
        $this->category = $category;
        $this->alt_name = $alt_name;
        $this->symbol = $symbol;
        $this->tags = $tags;
        $this->metatitle = $metatitle;
    }
}

if(!isset($_SERVER['HTTP_HOST'])){
    $parser = new ParseFilter(Array(), Array(), 1, 1);
    $cheap_trip_parser = new CheapTripParser($db, $parser);
    $cheap_trip_parser->connect_db(DBUSER, DBPASS, DBNAME);

    $cheap_trip_parser->get_data();
//$cheap_trip_parser->flush_posts();
    //$cheap_trip_parser->close();
}

?>