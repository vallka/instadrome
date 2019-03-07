<?php

#exit;

# imports the Google Cloud client library
require __DIR__ . '/vendor/autoload.php';
//use Google\Cloud\Vision\V1\ImageAnnotatorClient;


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

$start_tm = time();

print date('Y-m-d H:i:s')."\n";


doPhoto();


$end_tm = time();

print "***************************\n".date('Y-m-d H:i:s')." - " .($end_tm-$start_tm)."\n";


function doPhoto() {

    /////// CONFIG ///////
    $username = 'val2ka';
    $password = 'll440Hym&ig';
    $debug = false;
    $truncatedDebug = true;

//    'dronephotography','scotlandphotography','sailingphotography','amstaff'

    $tags = [
        'amstaff','scotlandphotography','sailingphotography','dronephotography',
    ];


    $ig = new \InstagramAPI\Instagram($debug, $truncatedDebug);
    try {
        $ig->login($username, $password);
    } catch (\Exception $e) {
        echo 'Something went wrong: '.$e->getMessage()."\n";
        exit(0);
    }
    try {
        // Generate a random rank token.
        $rankToken = \InstagramAPI\Signatures::generateUUID(); 

        foreach ($tags as $tag) {
            $response = $ig->hashtag->getFeed($tag,$rankToken);
            $items = $response->getItems();

            process_tag($tag,$items,$ig);
        }
    } catch (\Exception $e) {
        echo 'Something went wrong: '.$e->getMessage()."\n";
        exit(0);
    }
}


function process_tag($tag,$items,$ig)
{
    $realdo = true;
      

        print "$tag,count:".count($items)."\n";

        $i=1;
        foreach ($items as $item) {
            $carousel = $item->getCarouselMedia();
            if ($carousel) {
                $url = $carousel[0]->getImageVersions2()->getCandidates()[0]->getUrl();
                $w =  $carousel[0]->getImageVersions2()->getCandidates()[0]->getWidth();
                $h =  $carousel[0]->getImageVersions2()->getCandidates()[0]->getHeight();
            }
            else {
                $url = $item->getImageVersions2()->getCandidates()[0]->getUrl();
                $w =  $item->getImageVersions2()->getCandidates()[0]->getWidth();
                $h =  $item->getImageVersions2()->getCandidates()[0]->getHeight();
            }

            $caption = $item->getCaption() ? $item->getCaption()->getText() : null;
            $taken_at = date('Y-m-d H:i:s',$item->getTakenAt());
            $code = $item->getCode();
            $id_image = $item->getId();
            $like_count = $item->getLikeCount();
            $user = $item->getUser()->getUsername();

            sleep(rand(3,10));
            $iserinfo = $ig->people->getInfoByName($user);
            $mc = $iserinfo->getUser()->getMediaCount();
            $ferc = $iserinfo->getUser()->getFollowerCount();
            $fingc = $iserinfo->getUser()->getFollowingCount();

            $timepassed = time()-$item->getTakenAt();

            $nofaces = recognise_no_faces($url);

            if (
                $like_count < 30 and 
                $like_count > 10 and 
                $timepassed > 600 and
                $mc > 20 and
                $ferc < 2000 and   
                $fingc > ($ferc/2) and
                $nofaces and
                !exists($user)
                ) 
            {


                printf("%d) %s [%d] [%s] [%s] [%d / %d / %d / %d]\n", 
                    $i++,
                    $taken_at,
                    $timepassed,
                    $code,
                    $user,
                    $like_count,$mc,$ferc,$fingc
                );
                /*
                $userId = $ig->people->getUserIdForName($item->getUser()->getUsername());
                $followers = $ig->people->getFollowers($userId,$rankToken)->getUsers();
                print "\n$userId,".count($followers);
                */

                if ($realdo) {
                    $ig->media->like($id_image);
                    update_db(
                        $taken_at,
                        $caption,
                        $url,
                        $w,
                        $h,
                        $id_image, 
                        $code,
                        $user,
                        $like_count,
                        $tag
                    );
    
                    $secs = rand(2,20);
                    print "Sleeping $secs...\n";
                    sleep($secs);
                }

           }


        }

    return true;
}

function exists($u) {
    global $pdo, $_DB_PREFIX_;

    $st = $pdo->prepare("select id from {$_DB_PREFIX_}instalike where username=:u");
    $st->execute(['u'=>$u]);
    $id = $st->fetchColumn(0);

    return $id ? 1 : 0;
}

function recognise_no_faces($fileName) {
    return true;

    /*
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
            return $no_faces;
        }
    }

    //text_annotations
    $response = $imageAnnotator->textDetection($image);
    $logos  = $response->getTextAnnotations();

    if ($logos) {
        foreach ($logos as $label) {
            $no_faces = false;
            return $no_faces;
        }
    }

    //logo_annotations
    $response = $imageAnnotator->logoDetection($image);
    $logos  = $response->getLogoAnnotations();

    if ($logos) {
        foreach ($logos as $label) {
            $no_faces = false;
            return $no_faces;
        }
    }

    
    return $no_faces;
    */
}

function update_db(
    $taken_at,
    $caption,
    $url,
    $w,
    $h,
    $id_image, 
    $code,
    $user,
    $like_count,
    $tag
) 
{
    global $pdo, $_DB_PREFIX_;

    $st = $pdo->prepare("insert into {$_DB_PREFIX_}instalike (taken_at,code,id_image,caption,username,url,width,height,like_count,tag) ".
                        "values (:taken_at,:code,:id_image,:caption,:user,:url,:w,:h,:like_count,:tag)");
    
    $st->execute([
        'taken_at'=>$taken_at,
        'code'=>$code,
        'id_image'=>$id_image, 
        'caption'=>$caption,
        'user'=>$user,
        'url'=>$url,
        'w'=>$w,
        'h'=>$h,
        'like_count'=>$like_count,
        'tag'=>$tag
        ]);


}

