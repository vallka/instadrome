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


function find_g_tags($f) {
    global $pdo, $_DB_PREFIX_;

    $f = basename($f);

    $st = $pdo->prepare("select g_labels,g_locations,title,subject,tags from {$_DB_PREFIX_}photo where filename=:f");
    $st->execute(['f'=>$f]);
    $row = $st->fetch();

    return $row;
}


function process_file($file,$main_tags=null) {
    print "$file\n";
    //$exif = exif_read_data($file);
    //$caption = $exif['ImageDescription'];
    
    $iptc = get_iptc_data($file);
    $g_tags = find_g_tags($file);
    $g_labels = $g_tags['g_labels'];
    $g_locations = $g_tags['g_locations'];

    if ($g_tags['title']) 
        $iptc['title'] = $g_tags['title'];
    
    if ($g_tags['subject']) 
        $iptc['subject'] = $g_tags['subject'];

    if ($g_tags['tags']) 
        $iptc['tags'] = explode(',',$g_tags['tags']);

    //var_dump($iptc);
    $g_labels = explode(',',$g_labels);
    //var_dump($g_labels);
    //var_dump($g_locations);

    foreach ($g_labels as $l) {
        if (!in_array($l,$iptc['tags']) and substr_count($l,' ')<2) {
            $iptc['tags'][] = $l;
        }
    }

    if (! $iptc['title'] and $g_locations) {
        $iptc['title'] = $g_locations;
    }

    $caption = trim($iptc['title']);
    if ($caption and $caption[strlen($caption)-1] != '.') $caption .= '.';
    if ($caption and $iptc['subject']) $caption .= ' ';
    if ($iptc['subject']) $caption .= trim($iptc['subject']);

    if ($main_tags and is_array($main_tags)) {
        foreach ($main_tags as $t) {
            $t = preg_replace("/'/",'',$t);
            if ($t) $caption .= " #".preg_replace('/\s/','',$t);   // no spaces for instagram
        }
    }
    if ($iptc['tags']) {

        if (count($iptc['tags']) > 9)
            $rand_keys = array_rand($iptc['tags'], 9);
        else     
            $rand_keys = array_keys($iptc['tags']);

        foreach ($rand_keys as $key) {
            $tag = trim($iptc['tags'][$key],' #');
            $tag = preg_replace("/'/",'',$tag);
            if ($tag) $caption .= " #".preg_replace('/\s/','',$tag);   // no spaces for instagram
        }
    }


    print ">>$caption\n\n";
    //return false;
    return uoloadPhoto($file,$caption);
}

function get_iptc_data( $image_path ) {
    $return = array('title' => '', 'subject' => '', 'tags' => []);
    $size = getimagesize ( $image_path, $info);

    if(is_array($info) and isset($info["APP13"])) {
        $iptc = iptcparse($info["APP13"]);
        //var_dump($iptc); // this will show all the data retrieved but I'm only concerned with a few 
        $return['title'] = isset($iptc['2#005']) ? $iptc['2#005'][0] : '';
        $return['subject'] = isset($iptc['2#120']) ? $iptc['2#120'][0] : '';
        $return['tags'] = isset($iptc['2#025']) ? $iptc['2#025'] : [];
    }
    return $return;
}

function uoloadPhoto($photoFilename,$captionText) {
    //return false;

    require __DIR__.'/vendor/autoload.php';
    /////// CONFIG ///////
    $username = 'val2ka';
    $password = 'll440Hym&ig';
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
        echo 'Something went wrong: '.$e->getMessage()."\n";
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
        echo 'Something went wrong: '.$e->getMessage()."\n";
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
