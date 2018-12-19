<?php

require __DIR__ . '/vendor/autoload.php';
//use Google\Cloud\Vision\V1\ImageAnnotatorClient;


set_time_limit(0);
date_default_timezone_set('UTC');

$parametersFilepath = 'config/parameters2.php';
$parameters = require($parametersFilepath);

$host = $parameters['parameters']['database_host'];
$db   = $parameters['parameters']['database_name'];
$user = $parameters['parameters']['database_user'];
$pass = $parameters['parameters']['database_password'];

$_DB_PREFIX_ = $parameters['parameters']['database_prefix'];
$_DB_SUFFIX_ = $parameters['parameters']['database_suffix'];

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


do_a_feed($parameters);


$end_tm = time();

print "***************************\n".date('Y-m-d H:i:s')." - " .($end_tm-$start_tm)." sec\n";


function do_a_feed($parameters) {
    $debug = false;
    $truncatedDebug = true;

    $username = $parameters['parameters']['instagram_user'];
    $password = $parameters['parameters']['instagram_password'];
    $tags = $parameters['parameters']['instagram_tags'];
    $ignore = $parameters['parameters']['instagram_ignore'];


    $total = 0;

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

            process_tag($tag,$ignore,$items,$ig,$total);
        }
    } catch (\Exception $e) {
        echo 'Something went wrong: '.$e->getMessage()."\n";
        exit(0);
    }
}


function process_tag($tag,$ignore,$items,$ig,&$total)
{
    $like = true;
    $follow = true;
      

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

            $timepassed = time()-$item->getTakenAt();

            //$nofaces = recognise_no_faces($url);

            //if ($like_count<30 and $like_count>10 and $timepassed>600 and $nofaces and !exists($user)) {
            if (!in_array($user,$ignore) and !exists($user)) {
                printf("%d) %s %s User:%s Likes:%d\n", 
                    ++$total,
                    $taken_at,
                    //$timepassed,
                    //$caption,
                    //$url,
                    //$id_image, 
                    $code,
                    $user,
                    $like_count
                );

                $secs = rand(2,10);
                print "sleeping $secs...\n";
                sleep($secs);
    
                $iserinfo = $ig->people->getInfoByName($user);
                //$userid = $iserinfo->getUser()->getUserId();
                //$userId = $ig->people->getUserIdForName($item->getUser()->getUsername());
                $userid = $ig->people->getUserIdForName($user);

                //print "userid:$userid\n";
                //$iserinfo->printJson();
                //$iserinfo->getUser()->printJson();

                if ($like or $follow) {
                    if ($like) 
                        $ig->media->like($id_image);

                    if ($follow) 
                        $ig->people->follow($ig->people->getUserIdForName($user));
                    
                    update_db(
                        $tag,
                        $taken_at,
                        $caption,
                        $url,
                        $w,
                        $h,
                        $code,
                        $user,
                        $like_count,
                        $iserinfo->getUser()->getMediaCount(),
                        $iserinfo->getUser()->getFollowerCount(),
                        $iserinfo->getUser()->getFollowingCount(),
                        $iserinfo->getUser()->getIsBusiness(),
                        $iserinfo->getUser()->getPublicEmail(),
                        $iserinfo->getUser()->getFullName(),
                        $like,
                        $follow
                    );
    
                }
                $secs = rand(2,20);
                print "Sleeping $secs...\n";
                sleep($secs);

           }


        }

    return true;
}

function exists($u) {
    global $pdo, $_DB_PREFIX_,$_DB_SUFFIX_;

    $st = $pdo->prepare("select id from {$_DB_PREFIX_}instalike{$_DB_SUFFIX_} where username=:u");
    $st->execute(['u'=>$u]);
    $id = $st->fetchColumn(0);

    return $id ? 1 : 0;
}


function update_db(
    $tag,
    $taken_at,
    $caption,
    $url,
    $w,
    $h,
    $code,
    $user,
    $like_count,
    $media_count,
    $follower_count,
	$following_count,
    $is_busines,
    $email,
    $fullname,
    $like,
    $follow
) 
{
    global $pdo, $_DB_PREFIX_,$_DB_SUFFIX_;

    $st = $pdo->prepare("insert into {$_DB_PREFIX_}instalike{$_DB_SUFFIX_} (tag,taken_at,code,caption,username,url,width,height,".
                         "like_count,media_count,follower_count,following_count,is_business,email,fullname,liked,followed) ".
                        "values (:tag,:taken_at,:code,:caption,:user,:url,:w,:h,:like_count,:mc,:fer,:fing,:bu,:e,:fulln,:liked,:followed)");
    
    $st->execute([
        'tag'=>$tag,
        'taken_at'=>$taken_at,
        'code'=>$code,
        'caption'=>$caption,
        'user'=>$user,
        'url'=>$url,
        'w'=>$w,
        'h'=>$h,
        'like_count'=>$like_count,
        'mc'=>$media_count,
        'fer'=>$follower_count, 
        'fing'=>$following_count, 
        'bu'=>$is_busines, 
        'e'=>$email, 
        'fulln'=>$fullname, 
        'liked'=>$like, 
        'followed'=>$follow, 
        ]);


}

