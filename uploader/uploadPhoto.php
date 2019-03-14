<?php
require __DIR__ . '/vendor/autoload.php';
//use Google\Cloud\Vision\V1\ImageAnnotatorClient;
\InstagramAPI\Instagram::$allowDangerousWebUsageAtMyOwnRisk = true;

require 'meekrodb.2.3.class.php';

set_time_limit(0);
date_default_timezone_set('UTC');

$parno = isset($argv[1]) ? $argv[1] : '0';

$start_tm = time();

print date('Y-m-d H:i:s')." - par:$parno\n";


$uploader = new Uploader();
$uploader->run($parno);

class Uploader {
    protected $parameters;

    function run($parno) {
        $this->readPars($parno);
        $this->connect_db();
        $this->do_upload();
    }

    function readPars($parno) {
        $parametersFilepath = "config/parameters{$parno}.php";
        $parameters = require($parametersFilepath);

        $this->parameters = $parameters['parameters'];
    }

    function connect_db() {
        DB::$user = $this->parameters['database_user'];
        DB::$password = $this->parameters['database_password'];
        DB::$dbName = $this->parameters['database_name'];
        DB::$host = $this->parameters['database_host'];
        DB::$port = $this->parameters['database_port'];
        DB::$encoding = 'utf8mb4';
    }

    function do_upload() {
        $photoDir = '/mnt/d/Local/photo/instagram';

        $files = $this->scandir_jpg_hash($photoDir);
        
        if (!$files) {
            print "Nothing to do!\n";
            exit;
        }
        
        $file = $files[rand(0,count($files)-1)];
        //var_dump($file);
        
        $dname=dirname($file);
        $fname=basename($file);
        
        if ($dname and $dname!='.' and $dname[0]=='#') {
            $tags = explode('#',$dname);
            print ($fname .'!'. $dname);
            array_shift($tags);
            //var_dump($tags);
        }
        else {
            $tags = [];
            print ($fname);
        }
        $fullfilename = "$photoDir/$file";
        if ($this->process_file($fullfilename,$tags)) {
            rename($fullfilename,"$photoDir/done/$fname");
            print "\n\nDone ".date('Y-m-d H:i:s')."\n";
        }
        print "\n";

        return true;
    }

    function process_file($file,$main_tags=null) {
        $MAX_TAGS = 25;
        $im_tags = null;
        print "$file\n";
        $exif = exif_read_data($file);
        var_dump($exif);
        $this->removeGPS($file,$exif);
        //$caption = $exif['ImageDescription'];
        
        $iptc = $this->get_iptc_data($file);
    
        if (! $iptc['title'] and $iptc['location']) {
            $iptc['title'] = $iptc['location'];
            $iptc['location'] = null;
        }
        if (! $iptc['subject'] and $iptc['location'] and ($iptc['title']!=$iptc['location']))  {
            $iptc['subject'] = $iptc['location'];
            $iptc['location'] = null;
        }
    
        $caption = trim($iptc['title']);
        if ($caption and $caption[strlen($caption)-1] != '.') $caption .= '.';
        if ($caption and $iptc['subject']) $caption .= ' ';
        if ($iptc['subject']) $caption .= trim($iptc['subject']);
    
        if (! $iptc['tags']) {
            //$iptc['tags'] = array_merge($iptc['tags'],get_imagga_tags($file,10));
            $im_tags = $this->get_imagga_tags($file,30);
            $iptc['tags'] = $im_tags;
        }
    
        if ($iptc['tags']) {
            var_dump($iptc['tags']);
            if (!is_array($iptc['tags'])) {
                $kw = explode(',',$iptc['tags']);
                $kw = array_map('trim',$kw);
                $iptc['tags'] = $kw;
                //var_dump($iptc['tags']);
            }
            elseif (count($iptc['tags'])==1 and strpos($iptc['tags'][0],',')!==false) {
                $kw = explode(',',$iptc['tags'][0]);
                $kw = array_map('trim',$kw);
                $iptc['tags'] = $kw;
                var_dump($iptc['tags']);
            }
    
            if (count($iptc['tags']) > $MAX_TAGS)
                $rand_keys = array_rand($iptc['tags'], $MAX_TAGS);
            else     
                $rand_keys = array_keys($iptc['tags']);
    
            foreach ($rand_keys as $key) {
                $tag = trim($iptc['tags'][$key],' #');
    
                $tag = preg_replace("/'/",'',$tag);
                $tag = preg_replace("/\-/",'',$tag);
                if ($tag) $caption .= " #".preg_replace('/\s/','',$tag);   // no spaces for instagram
    
            }
        }
    
        if ($main_tags and is_array($main_tags)) {
            foreach ($main_tags as $t) {
                if (!in_array($t,$iptc['tags'])) {
                    $t = preg_replace("/'/",'',$t);
                    if ($t) $caption .= " #".preg_replace('/\s/','',$t);   // no spaces for instagram
                }
            }
        }
    
    
        $caption = trim($caption);
        print ">>$caption\n\n";
        //return false;
        $icode = $this->uploadPhoto($file,$caption);
        if ($icode) {
            $this->update_db($file,$icode,$im_tags);
        }

        return $icode;
    }

    function get_iptc_data( $image_path ) {
        /*
        $iptcHeaderArray = array
        (
            '2#005'=>'DocumentTitle',
            '2#010'=>'Urgency',
            '2#015'=>'Category',
            '2#020'=>'Subcategories',
            '2#040'=>'SpecialInstructions',
            '2#055'=>'CreationDate',
            '2#080'=>'AuthorByline',
            '2#085'=>'AuthorTitle',
            '2#090'=>'City',
            '2#095'=>'State',
            '2#101'=>'Country',
            '2#103'=>'OTR',
            '2#105'=>'Headline',
            '2#110'=>'Source',
            '2#115'=>'PhotoSource',
            '2#116'=>'Copyright',
            '2#120'=>'Caption',
            '2#122'=>'CaptionWriter'
        );
        */
    
    
        $return = array('title' => '', 'subject' => '', 'tags' => [], 'location'=>'');
        $size = getimagesize ( $image_path, $info);
    
        if(is_array($info) and isset($info["APP13"])) {
            $iptc = iptcparse($info["APP13"]);
            //var_dump($iptc); // this will show all the data retrieved but I'm only concerned with a few 
            $return['title'] = isset($iptc['2#005']) ? $iptc['2#005'][0] : '';
            $return['subject'] = isset($iptc['2#120']) ? $iptc['2#120'][0] : '';
            $return['tags'] = isset($iptc['2#025']) ? $iptc['2#025'] : [];
    
            $return['location'] = isset($iptc['2#090'])? $iptc['2#090'][0] : '';
            if (isset($iptc['2#095'])) {
                $return['location'] = $return['location'] ? ("{$return['location']}, ") : '';
                $return['location'] .= $iptc['2#095'][0];
            }
            if (isset($iptc['2#101'])) {
                $return['location'] = $return['location'] ? ("{$return['location']}, ") : '';
                $return['location'] .= $iptc['2#101'][0];
            }
    
        }
        return $return;
    }
    
    function scandir_jpg_hash($dir, $prefix = '') {
        $dir = rtrim($dir, '\\/');
        $result = array();
      
          foreach (scandir($dir) as $f) {
            if ($f !== '.' and $f !== '..') {
              if (is_dir("$dir/$f") and $f[0]=='#') {
                $result = array_merge($result, $this->scandir_jpg_hash("$dir/$f", "$prefix$f/"));
              } elseif (is_file("$dir/$f") and preg_match('/\.jpg$/i',$f)) {
                $result[] = $prefix.$f;
              }
            }
          }
      
        return $result;
    }

    function removeGPS($file,$exif) {
        if (array_key_exists('GPSVersion',$exif) or
            array_key_exists('GPSVersionID',$exif) or
            array_key_exists('GPSLatitudeRef',$exif) or
            array_key_exists('GPSLongitudeRef',$exif) or
            array_key_exists('GPSAltitudeRef',$exif) or
            array_key_exists('GPSTimeStamp',$exif) or
            array_key_exists('GPSDateStamp',$exif) or
            array_key_exists('GPSMapDatum',$exif)
         ) 
        {
            //$exiftools = '/usr/bin/exiftool';
            $exiftools = 'exiftool';
        
            print ("put_exiftool_data: $file\n");
        
            $args = '-overwrite_original ';
            //$args .="-GPSLatitude= -GPSLongitude= -GPSAltitude= -GPSVersion=  -GPSLatitudeRef= -GPSLongitudeRef= -GPSAltitudeRef= ";
            $args .="-GPSLatitude= -GPSLongitude= -GPSAltitude= -GPSLatitudeRef= -GPSLongitudeRef= -GPSAltitudeRef= ";
            $args .="-GPSVersion= -GPSVersionID= -GPSTimeStamp= -GPSDateStamp= -GPSMapDatum= ";
        
            print("exiftool: " . "$exiftools $args \"$file\"\n");
            $res = `$exiftools $args "$file"`;
            print("exiftool: $res\n");
        }
    }

    function get_imagga_tags($file,$count) {


        $api_credentials = array(
            'key' => 'acc_198112daf8098f8',
            'secret' => '932bdff581b7c8186f345ba5830b5ed0'
        );
    
        $ch = curl_init();
    
        curl_setopt($ch, CURLOPT_URL, "https://api.imagga.com/v2/tags");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_USERPWD, $api_credentials['key'].':'.$api_credentials['secret']);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, 1);
        $fields = [
            'image' => new \CurlFile($file, 'image/jpeg', 'image.jpg'),
            'limit' => $count
        ];
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    
        $response = curl_exec($ch);
        curl_close($ch);
    
        $json_response = json_decode($response);
        //var_dump($json_response);
    
        $tags = [];
        foreach ($json_response->result->tags as $tag) {
            $tags[] = $tag->tag->en;
            //print $tag->tag->en."\n";
        }
        return $tags;
    }
    
    function uploadPhoto($photoFilename,$captionText) {
        $username = $this->parameters['instagram_user'];
        $password = $this->parameters['instagram_password'];
        $debug = false;
        $truncatedDebug = true;

        $ig = new \InstagramAPI\Instagram($debug, $truncatedDebug);
        try {
            $ig->login($username, $password);
        } catch (\Exception $e) {
            echo 'Something went wrong one: '.$e->getMessage()."\n";
            exit(0);
        }
        try {
            // The most basic upload command, if you're sure that your photo file is
            // valid on Instagram (that it fits all requirements), is the following:
            // $ig->timeline->uploadPhoto($photoFilename, ['caption' => $captionText]);
            // However, if you want to guarantee that the file is valid (correct format,
            // width, height and aspect ratio), then you can run it through our
            // automatic photo processing class. It is pretty fast, and only does any
            // work when the input file is invalid, so you may want to always use it.
            // You have nothing to worry about, since the class uses temporary files if
            // the input needs processing, and it never overwrites your original file.
            //
            // Also note that it has lots of options, so read its class documentation!
            $photo = new \InstagramAPI\Media\Photo\InstagramPhoto($photoFilename);
    
            print "uploading... ($captionText):\n";
            $r = $ig->timeline->uploadPhoto($photo->getFile(), ['caption' => $captionText]);
    
    
            //var_dump($r);
            //print "media:\n";
            //var_dump($r->getMedia());
            print "media code:\n";
            $icode = $r->getMedia()->getCode();
            var_dump($icode);
    
        } catch (\Exception $e) {
            echo 'Something went wrong two: '.$e->getMessage()."\n";
            exit(0);
        }
    
        return $icode;
    }
    
    function update_db($file,$icode,$im_tags) {
        $file = basename($file);
        if (is_array($im_tags)) {
            $im_tags = implode(',',$im_tags);
        }

        DB::insertUpdate($this->parameters['database_prefix'] . 'allphoto', array(
            'filename' => $file,
            //'taken_at' => $taken_at,
            'instagram_code' => $icode,
            'im_tags' => $im_tags,
            'published_in' => 'instagram',
            'updated_at' => DB::sqleval("NOW()")
        ));
    }

        
}

/*
set_time_limit(0);
date_default_timezone_set('UTC');

$parametersFilepath = 'config/parameters0.php';
$parameters = require($parametersFilepath);

$host = $parameters['parameters']['database_host'];
$db   = $parameters['parameters']['database_name'];
$user = $parameters['parameters']['database_user'];
$pass = $parameters['parameters']['database_password'];
$_DB_PREFIX_ = $parameters['parameters']['database_prefix'];

$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
$pdo = new PDO($dsn, $user, $pass, $opt);

print date('Y-m-d H:i:s')."\n";
*/


///////////////////////////////////////

/*
function update_db($filename,$icode) {
    require 'meekrodb.2.3.class.php';

    function connect_db() {
        DB::$user = $this->parameters['database_user'];
        DB::$password = $this->parameters['database_password'];
        DB::$dbName = $this->parameters['database_name'];
        DB::$host = $this->parameters['database_host'];
        DB::$port = $this->parameters['database_port'];
        DB::$encoding = 'utf8mb4';


    $filename = basename($filename);
    $st = $pdo->prepare("update {$_DB_PREFIX_}photo set instagram_code=:c where filename=:f and instagram_code is null");
    
    print ("$filename,$icode\n");
    $st->execute([
        'f'=>$filename,
        'c'=>$icode,
    ]);

    $oldcode = preg_replace('/\.jpg$/','',$filename);
    $st = $pdo->prepare("update {$_DB_PREFIX_}instagram set code2=:c,reposted_dt=now() where code=:f");
    print ("$filename,$icode\n");
    $st->execute([
        'f'=>$oldcode,
        'c'=>$icode,
    ]);

}
*/