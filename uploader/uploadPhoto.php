<?php
set_time_limit(0);
date_default_timezone_set('UTC');

$parametersFilepath = 'config/parameters.php';
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

$photoDir = '/mnt/d/Local/photo/instagram';


$files = scandir_jpg_hash($photoDir);
//shuffle($files);
//var_dump($files);

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
if (process_file($fullfilename,$tags)) {
    rename($fullfilename,"$photoDir/done/$fname");
    print "\n\nDone ".date('Y-m-d H:i:s')."\n";
}
print "\n";
exit;

///////////////////////////////////////

function scandir_jpg_hash($dir, $prefix = '') {
    $dir = rtrim($dir, '\\/');
    $result = array();
  
      foreach (scandir($dir) as $f) {
        if ($f !== '.' and $f !== '..') {
          if (is_dir("$dir/$f") and $f[0]=='#') {
            $result = array_merge($result, scandir_jpg_hash("$dir/$f", "$prefix$f/"));
          } elseif (is_file("$dir/$f") and preg_match('/\.jpg$/i',$f)) {
            $result[] = $prefix.$f;
          }
        }
      }
  
    return $result;
}

/*
function find_g_tags($f) {
    global $pdo, $_DB_PREFIX_;

    $f = basename($f);

    $st = $pdo->prepare("select g_labels,g_locations,title,subject,tags from {$_DB_PREFIX_}photo where filename=:f");
    $st->execute(['f'=>$f]);
    $row = $st->fetch();

    return $row;
}
*/


function process_file($file,$main_tags=null) {
    $MAX_TAGS = 25;
    print "$file\n";
    $exif = exif_read_data($file);
    //var_dump($exif);
    //$caption = $exif['ImageDescription'];
    
    $iptc = get_iptc_data($file);
    //var_dump($iptc);
    /*
    $g_tags = find_g_tags($file);
    $g_labels = $g_tags['g_labels'];
    $g_locations = $g_tags['g_locations'];

    if ($g_tags['title']) 
        $iptc['title'] = $g_tags['title'];
    
    if ($g_tags['subject']) 
        $iptc['subject'] = $g_tags['subject'];

    if ($g_tags['tags']) 
        $iptc['tags'] = explode(',',$g_tags['tags']);

    //$g_labels = explode(',',$g_labels);
    //var_dump($g_labels);
    //var_dump($g_locations);
    foreach ($g_labels as $l) {
        if (!in_array($l,$iptc['tags']) and substr_count($l,' ')<2) {
            $iptc['tags'][] = $l;
        }
    }
    */

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
        $iptc['tags'] = get_imagga_tags($file,30);
    }

    if ($iptc['tags']) {

        var_dump($iptc['tags']);
        if (!is_array($iptc['tags'])) {
            $kw = explode(',',$iptc['tags']);
            $kw = array_map('trim',$kw);
            $iptc['tags'] = $kw;
            var_dump($iptc['tags']);
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


    if (isset($exif['GPSLatitudeRef'])) {
        removeGPS($file);
    }

    print ">>$caption\n\n";
    //return false;
    return uploadPhoto($file,$caption);
}

function removeGPS($file) {
    $exiftools = '/usr/local/bin/exiftool';

    print ("put_exiftool_data: $file\n");

    $args = '-overwrite_original ';
    $args .="-GPSLatitude= -GPSLongitude= -GPSAltitude= -GPSVersion= -GPSLatitudeRef= -GPSLongitudeRef= -GPSAltitudeRef= ";

    print("exiftool: " . "$exiftools $args \"$file\"\n");
    $res = `$exiftools $args "$file"`;
    print("exiftool: $res\n");
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
    //return false;

    require __DIR__.'/vendor/autoload.php';
    /////// CONFIG ///////
    $username = 'val2ka';
    $password = 'll440Hym&ig';

    //$username = 'gellifiquegel';
    //$password = 'Marusya1';

    $debug = false;
    $truncatedDebug = true;
    //////////////////////
    /////// MEDIA ////////
    // = '/mnt/d/Local/photo/instagram/_IGP1503_Photolemur3-edit.jpg';
    // = 'Павлин-мавлин в Ла Манге #lamanga #spain';
    //////////////////////
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

        if ($icode) {
            update_db($photoFilename,$icode);
        }

    } catch (\Exception $e) {
        echo 'Something went wrong two: '.$e->getMessage()."\n";
        exit(0);
    }

    return true;
}

function update_db($filename,$icode) {
    global $pdo, $_DB_PREFIX_;

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
