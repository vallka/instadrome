<?php
# imports the Google Cloud client library
require __DIR__ . '/vendor/autoload.php';
#use Google\Cloud\Vision\V1\ImageAnnotatorClient;

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
$photoDir = '/mnt/d/Local/photo/process/';

$photos = get_photos();

while ($photo=get_photo($photos)) {
    sleep(2);
    //$photo['url'] = preg_replace('/\?.*$/','',$photo['url']);
    #if (recognise_no_faces($photo['url'])) {
        $newphoto = downloadPhoto($photo['url'],$photo['code']);
        rename($newphoto,$photoDir . basename($newphoto));
        print $photoDir . basename($newphoto) . "\nDone\n";
        break;
    #}
}

/*
if ($photo and $newphoto and uploadPhoto($newphoto,$photo['caption'],$photo['code'])) {
    print "Done.\n";
}
*/

function get_photos($mon=24) {
    global $pdo, $_DB_PREFIX_;
    $sql = "SELECT id FROM {$_DB_PREFIX_}instagram where taken_at<date_sub(now(),interval $mon month) and code2 IS NULL";

    print "$sql\n";

    $st = $pdo->prepare($sql);
    $st->execute();
    $rows = $st->fetchAll();

    //var_dump($rows);
    return $rows;

}

function get_photo(&$rows) {
    global $pdo, $_DB_PREFIX_;

    if (count($rows)<1) return false;

    $id = rand(0,count($rows)-1);
    var_dump($rows[$id]['id']);


    $sql = "SELECT * FROM {$_DB_PREFIX_}instagram where id=:id";

    print "$sql\n";

    $st = $pdo->prepare($sql);
    $st->execute(['id'=>$rows[$id]['id']]);
    $row = $st->fetch();

    var_dump($row);
    array_slice($rows,$id,1);
    return $row;

}

exit();

function downloadPhoto($url,$fn) {
    $photoDir = '/mnt/d/Local/photo/instagram_reposted';

    $fn = "$photoDir/$fn.jpg";

    $f = file_get_contents($url);
    file_put_contents($fn,$f);

    //var_dump($f);
    return $fn;
}
/*
function process_file($file) {
    print "$file\n";
    //$exif = exif_read_data($file);
    //$caption = $exif['ImageDescription'];
    
    $iptc = get_iptc_data($file);

    //var_dump($iptc);

    $caption = trim($iptc['title']);
    if ($caption and $caption[strlen($caption)-1] != '.') $caption .= '.';
    if ($caption) $caption .= ' ';
    if ($iptc['subject']) $caption .= trim($iptc['subject']);

    if ($iptc['tags']) {
        $i=0;
        foreach ($iptc['tags'] as $tag) {
            if ($i++ < 9) {
                $caption .= " #".preg_replace('/\s/','',$tag);   // no spaces for instagram
            }
        }
    }


    print "$caption\n\n";
    return uoloadPhoto($file,$caption);
}

function get_iptc_data( $image_path ) {
    $return = array('title' => '', 'subject' => '', 'tags' => '');
    $size = getimagesize ( $image_path, $info);

    if(is_array($info)) {
        $iptc = iptcparse($info["APP13"]);
        //var_dump($iptc); // this will show all the data retrieved but I'm only concerned with a few 
        $return['title'] = isset($iptc['2#005']) ? $iptc['2#005'][0] : '';
        $return['subject'] = isset($iptc['2#120']) ? $iptc['2#120'][0] : '';
        $return['tags'] = isset($iptc['2#025']) ? $iptc['2#025'] : '';
    }
    return $return;
}
*/

function uploadPhoto($photoFilename,$captionText,$oldcode) {
    //return false;

    //require __DIR__.'/vendor/autoload.php';
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
            update_db($photoFilename,$icode,$oldcode);
        }

    } catch (\Exception $e) {
        echo 'Something went wrong: '.$e->getMessage()."\n";
        exit(0);
    }

    return true;
}

function recognise_no_faces($fileName) {
    # the name of the image file to annotate

    # instantiates a client
    $imageAnnotator = new ImageAnnotatorClient();

    # prepare the image to be annotated
    $image = file_get_contents($fileName);


    $response = $imageAnnotator->faceDetection($image);
    $faces  = $response->getFaceAnnotations();

    $no_faces = true;
    if ($faces) {
        foreach ($faces as $label) {
            $no_faces = false;
        }
    }
    
    var_dump($no_faces);
    return $no_faces;
}

function update_db($filename,$icode,$oldcode) {
    global $pdo, $_DB_PREFIX_;
    print ("$filename,$icode,$oldcode\n");

    /*
    $filename = basename($filename);
    $st = $pdo->prepare("update {$_DB_PREFIX_}photo set instagram_code=:c where filename=:f and instagram_code is null");
    
    $st->execute([
        'f'=>$filename,
        'c'=>$icode,
    ]);
        */

    $st = $pdo->prepare("update {$_DB_PREFIX_}instagram set code2=:c,reposted_dt=now() where code=:oc");
    $st->execute([
        'c'=>$icode,
        'oc'=>$oldcode,
    ]);

    return true;

}
