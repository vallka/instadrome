<?php
set_time_limit(0);
date_default_timezone_set('UTC');

# includes the autoloader for libraries installed with composer
require __DIR__ . '/vendor/autoload.php';

# imports the Google Cloud client library
use Google\Cloud\Vision\V1\ImageAnnotatorClient;

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


$photoDir = '/mnt/d/Local/photo/process';

$files = scandir($photoDir);

$done = 0;
foreach($files as $file) {
    $fullfilename = "$photoDir/$file";
    if (is_file($fullfilename) and preg_match('/\.jpg/i',$file)) {
        if (process_file($fullfilename)) {
            //rename($fullfilename,"$photoDir/done/$file");
            print "\n\nDone ".date('Y-m-d H:i:s')."\n";
            $done++;
            break;
        }

    }
}

if (!$done) {
    print "Nothing to do\n";

}

function process_file($file) {
    $f = array();
    $f['filename'] = basename($file);

    if (!exists($f)) {
        $f = array_merge($f,get_iptc_data($file),recognise ($file));
        save($f);
    }

    print "$file\n";
    //var_dump($f);

    return 0;
}

function recognise($fileName) {
    # the name of the image file to annotate

    $r = array();

    # instantiates a client
    $imageAnnotator = new ImageAnnotatorClient();

    # prepare the image to be annotated
    $image = file_get_contents($fileName);

    # performs label detection on the image file
    $response = $imageAnnotator->labelDetection($image);
    $labels = $response->getLabelAnnotations();

    if ($labels) {
        //echo("Labels:" . PHP_EOL);
        foreach ($labels as $label) {
            //echo($label->getDescription() . PHP_EOL);
            $r['labels'][] = $label->getDescription();
        }
    }

    $response = $imageAnnotator->landmarkDetection($image);
    $locations = $response->getLandmarkAnnotations();

    if ($locations) {
        //echo("Labels:" . PHP_EOL);
        foreach ($locations as $label) {
            //echo($label->getDescription() . PHP_EOL);
            $r['locations'][] = $label->getDescription();
        }
    }

    return $r;
}

function get_iptc_data( $image_path ) {
    $return = array('title' => '', 'subject' => '', 'tags' => '', 'width'=>0, 'height'=>0 ,'ymd'=>'','hms'=>'');
    $size = getimagesize ( $image_path, $info);
    //var_dump($size);
    $return['width'] = $size[0];
    $return['height'] = $size[1];

    if(is_array($info)) {
        $iptc = iptcparse($info["APP13"]);
        //var_dump($iptc); // this will show all the data retrieved but I'm only concerned with a few 
        $return['title'] = isset($iptc['2#005']) ? $iptc['2#005'][0] : '';
        $return['subject'] = isset($iptc['2#120']) ? $iptc['2#120'][0] : '';
        $return['tags'] = isset($iptc['2#025']) ? $iptc['2#025'] : '';
        $return['ymd'] = isset($iptc['2#055']) ? $iptc['2#055'][0] : '';
        $return['hms'] = isset($iptc['2#060']) ? $iptc['2#060'][0] : '';
    }
    return $return;
}

function exists($f) {
    global $pdo, $_DB_PREFIX_;

    $st = $pdo->prepare("select id from {$_DB_PREFIX_}photo where filename=:f");
    $st->execute(['f'=>$f['filename']]);
    $id = $st->fetchColumn(0);

    return $id ? 1 : 0;
}

function save($f) {
    global $pdo, $_DB_PREFIX_;

    $st = $pdo->prepare("insert into {$_DB_PREFIX_}photo (filename,taken_at,title,subject,tags,g_labels,g_locations,width,height) ".
                            " values (:f,:a,:t,:s,:tags,:lb,:lc,:w,:h)");
    
    $taken_at = substr($f['ymd'],0,4).'-'.substr($f['ymd'],4,2).'-'.substr($f['ymd'],6,2).' '.
                substr($f['hms'],0,2).':'.substr($f['hms'],2,2).':'.substr($f['hms'],4,2);

    $tags='';
    $lb='';
    $lc='';                
    if (isset($f['tags']) and is_array($f['tags']))
        $tags = implode(',',$f['tags']);
    if (isset($f['labels']) and is_array($f['labels']))
        $lb = implode(',',$f['labels']);
    if (isset($f['locations']) and is_array($f['locations']))
        $lc = implode(',',$f['locations']);

    print "$taken_at;$tags;$lb;$lc\n\n";
    $st->execute([
        'f'=>$f['filename'],
        'a'=>$taken_at,
        't'=>$f['title'],
        's'=>$f['subject'],
        'tags'=>$tags,
        'lb'=>$lb,
        'lc'=>$lc,
        'w'=>$f['width'],
        'h'=>$f['height'],
    ]);

    return 1;    
}