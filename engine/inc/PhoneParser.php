<?php

set_time_limit(180);

class PhoneParser{

    private $db;
    private $file;
    private $phone_numbers;
    private $pattern = '/^(7|8)*9\d{9}/';
    private $tmp_dir = './uploads/files/tmp/';

    public function __construct($db, $file = null){
        $this->db = $db;
        if($file != null){
            $this->file = $file;
        }
    }

    public function parse(){
        if(!$this->check()){
            throw new \Exception('Uploaded file has to be a zip archive with single html page inside.');
            die();
        }

        if(!$this->unzip()){
            throw new \Exception('Nothing to extract!');
            die();
        }

        $feed = $this->get_dom_content();
        $trs = $feed->getElementsByTagName('tr');
        foreach($trs as $tr){
            $tds = $tr->getElementsByTagName('td');
            foreach($tds as $td){
                $v = $td->nodeValue;
                if($v != ''){
                    if(preg_match($this->pattern, $v) > 0){
                        if(strlen($v) < 11){
                            $v = '7' . $v;
                        }else{
                            if($v[0] == '8'){
                                $v = '7' . substr($v, 1);
                            }
                        }
                        if(!in_array($v, $this->phone_numbers)){
                            $this->phone_numbers[] = $v;
                        }
                    }
                }
            }
        }
        $this->clear_tmp_dir($this->tmp_dir);
    }

    public function get_txt(){
        $this->load_data_from_db();
        $path = './uploads/files/';
        $filename = 'phone_base.txt';
        $file = fopen($path . $filename, 'w+');
        $content = $this->build_content();
        fwrite($file, $content);
        fclose($file);
        header("Content-Disposition: attachment; filename='$filename'");
        readfile($path . $filename);
        die();
    }

    public function save(){
        if(!$query = $this->build_query()){
            return 0;
        }
        $id = $this->db->query($query);
        return $this->db->get_affected_rows($id);
    }

    private function build_query(){
        if(count($this->phone_numbers) < 1){
            return false;
        }
        $query = 'INSERT IGNORE INTO ' . PREFIX . '_megafon_parser (phone) VALUES ';
        foreach($this->phone_numbers as $phone){
            $query .= "('$phone'), ";
        }
        $query = substr($query,0, -2);
        return $query;
    }

    private function get_dom_content(){
        $feed = new DOMDocument();
        if($feed->loadHTMLFile($this->file)){
            return $feed;
        }
        return false;
    }

    private function load_data_from_db(){
        $this->phone_numbers = array();
        $query = 'SELECT phone FROM ' . PREFIX . '_megafon_parser';
        $this->db->query($query);
        while($row = $this->db->get_row()){
            $this->phone_numbers[] = $row['phone'];
        }
    }

    private function build_content(){
        $content = '';
        foreach($this->phone_numbers as $phone){
            $content .= $phone . PHP_EOL;
        }
        return $content;
    }

    private function unzip(){
        if(!file_exists($this->tmp_dir)){
            mkdir($this->tmp_dir, 0777, true);
        }
        $this->clear_tmp_dir($this->tmp_dir);
        $zip = new ZipArchive();
        if($zip->open($this->file) === true){
            if($zip->extractTo($this->tmp_dir)){
                $files = glob($this->tmp_dir . '*');
                if(count($files) == 1){
                    $this->file = $files[0];
                    $zip->close();
                    return true;
                }
            }
            $zip->close();
        }
        return false;
    }

    private function clear_tmp_dir($dir){
        $res = array();
        $files = glob($dir . '*');
        foreach($files as $file){
            if(is_file($file)){
                unlink($file);
            }
        }
    }

    private function check(){
        return true;
//        $finfo = new finfo(FILEINFO_MIME_TYPE);
//        $ext = $finfo->file($this->file);
//
//        if($ext == 'application/zip'){
//            return true;
//        }
//        return false;
    }
}

?>