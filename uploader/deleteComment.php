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


doPhoto();


function doPhoto() {
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
        //$photo = new \InstagramAPI\Media\Photo\InstagramPhoto($photoFilename);
       // $r = $ig->timeline->uploadPhoto($photo->getFile(), ['caption' => $captionText]);

       $response = $ig->timeline->getSelfUserFeed(null);


       $media_id = '1928777740549317763_2934881344';
       print "media media_id:$media_id\n";

       $items = $response->getItems();
       foreach ($items as $item) {
           $id_image = $item->getId();

           print("$id_image\n\n");

           if ($id_image==$media_id) {
               $co = $item->getCommentCount();
               var_dump($co);
               $media = $item->getMedia();
               $co = $media->getComments();
               var_dump($co);


               exit;
           }
       }


        //var_dump($r);
        //print "media:\n";
        //var_dump($r->getMedia());

    } catch (\Exception $e) {
        echo 'Something went wrong: '.$e->getMessage()."\n";
        exit(0);
    }

    return true;
}

