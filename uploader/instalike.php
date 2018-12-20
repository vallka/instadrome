<?php

require __DIR__ . '/vendor/autoload.php';
//use Google\Cloud\Vision\V1\ImageAnnotatorClient;
\InstagramAPI\Instagram::$allowDangerousWebUsageAtMyOwnRisk = true;

set_time_limit(0);
date_default_timezone_set('UTC');


$start_tm = time();

print date('Y-m-d H:i:s')."\n";


$instalike = new Instalike();
$instalike->run();


$end_tm = time();

print "***************************\n".date('Y-m-d H:i:s')." - " .($end_tm-$start_tm)." sec\n";


class Instalike {
    protected $parameters;
    protected $pdo;

    function run() {
        $this->readPars();
        $this->do_a_feed();
    }

    function connect_db() {
        if ($this->pdo) return;

        $charset = 'utf8mb4';

        $host = $this->parameters['database_host'];
        $port = $this->parameters['database_port'];
        $db = $this->parameters['database_name'];
        $user = $this->parameters['database_user'];
        $pass = $this->parameters['database_password'];

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $opt = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $this->pdo = new PDO($dsn, $user, $pass, $opt);
        
        /*
        * doesnt work
        $st = $this->pdo->prepare("set time_zone='+0:00'");
        $st->execute();
        */

    }
    function sleep() {
        $secs = rand(2,10);
        print "   sleeping $secs...\n";
        sleep($secs);
    }

    function readPars() {
        $parametersFilepath = 'config/parameters.php';
        $parameters = require($parametersFilepath);

        $this->parameters = $parameters['parameters'];
        
        $host = $parameters['parameters']['database_host'];
        $db   = $parameters['parameters']['database_name'];
        $user = $parameters['parameters']['database_user'];
        $pass = $parameters['parameters']['database_password'];
        
        
    }

function do_a_feed() {
    $debug = false;
    $truncatedDebug = true;

    $username = $this->parameters['instagram_user'];
    $password = $this->parameters['instagram_password'];
    $tags = $this->parameters['instagram_tags'];



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

        $all_items = [];
        $item_tags = [];

        foreach ($tags as $tag) {
            $response = $ig->hashtag->getFeed($tag,$rankToken);
            $items = $response->getItems();
            
            foreach ($items as $item) {
                $item_tags[$item->getCode()] = $tag;
            }

            /*foreach ($items as $item) {
                $all_items[] = [$tag => $item];
            }*/

            $all_items = array_merge($all_items,$items);


            print $tag . ':' .count($all_items);

            $this->sleep();
        }

        shuffle($all_items);

        $this->process_items($item_tags,$all_items,$ig);


    } catch (\Exception $e) {
        echo 'Something went wrong: '.$e->getMessage()."\n";
        exit(0);
    }
}


    function process_items($item_tags,$items,$ig)
    {


        $total = 0;
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
            $userid = $item->getUser()->getPk();

            $timepassed = time()-$item->getTakenAt();
            $tag = $item_tags[$code];

            if ($timepassed < $this->parameters['instagram_post_age']) {
                print "skipping time $timepassed\n";
                continue;
            }
            if ($like_count < $this->parameters['instagram_min_like_count'] or
                $like_count > $this->parameters['instagram_max_like_count']) 
            {
                print "skipping like_count $like_count\n";
                continue;
            }

            $ignore = $this->parameters['instagram_ignore'];
            if (in_array($user,$ignore)) {
                print "skipping ignored $user\n";
                continue;
            }
            if ($this->exists($user,$userid,$code)) {
                print "skipping existing $user,$userid,$code\n";
                continue;
            }

            $iserinfo = $ig->people->getInfoByName($user);
            $mc = $iserinfo->getUser()->getMediaCount();
            $ferc = $iserinfo->getUser()->getFollowerCount();
            $fingc = $iserinfo->getUser()->getFollowingCount();
            $this->sleep();

            if ($mc < $this->parameters['instagram_min_media_count'] or
                $mc > $this->parameters['instagram_max_media_count']) 
            {
                print "skipping media_count $mc\n";
                continue;
            }
            if ($ferc < $this->parameters['instagram_min_followers_count'] or
                $ferc > $this->parameters['instagram_max_followers_count']) 
            {
                print "skipping followers_count $ferc\n";
                continue;
            }
            if ($fingc < $this->parameters['instagram_min_following_count'] or
                $fingc > $this->parameters['instagram_max_following_count']) 
            {
                print "skipping following_count $fingc\n";
                continue;
            }


            //$nofaces = recognise_no_faces($url);

            //$iserinfo->printJson();
            //$iserinfo->getUser()->printJson();
            
            $total++;

            $like = $total <= $this->parameters['instagram_max_to_like'];
            $follow = $total <= $this->parameters['instagram_max_to_follow'];
                        
            if ($like or $follow) {
                printf("%d) %s %d s. %s %s U:%s(%s) L:%d\n", 
                    $total,
                    $taken_at,
                    $timepassed,
                    $tag,
                    $code,
                    $user,
                    $userid,
                    $like_count
                );

                if ($like) {
                    $ig->media->like($id_image);
                    print "Liked.";
                }

                if ($follow) {
                    $ig->people->follow($ig->people->getUserIdForName($user));
                    print "Followed.";
                }
                
                $this->update_db(
                    $tag,
                    $taken_at,
                    $caption,
                    $url,
                    $w,
                    $h,
                    $code,
                    $user,
                    $userid,
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
                print "Logged.\n";
    
            }
            else if (!$like and !$follow) {
                break;
            }



        }

        $total--;
        print "\n\n$total processed\n\n";
        return true;
    }

    function exists($u,$uid,$code) {
        $this->connect_db();

        $_DB_PREFIX_ = $this->parameters['database_prefix'];
        $_DB_SUFFIX_ = $this->parameters['database_suffix'];

        $st = $this->pdo->prepare("select id from {$_DB_PREFIX_}instalike{$_DB_SUFFIX_} where username=:u or userid=:id or code=:c");
        $st->execute(['u'=>$u,'id'=>$uid,'c'=>$code]);
        $id = $st->fetchColumn(0);

        return $id ? 1 : 0;
    }


    function update_db(
        $tagg,
        $taken_at,
        $caption,
        $url,
        $w,
        $h,
        $code,
        $user,
        $userid,
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
        $this->connect_db();

        $_DB_PREFIX_ = $this->parameters['database_prefix'];
        $_DB_SUFFIX_ = $this->parameters['database_suffix'];

        $st = $this->pdo->prepare("insert into {$_DB_PREFIX_}instalike{$_DB_SUFFIX_} (tag,taken_at,code,caption,username,userid,url,width,height,".
                                "like_count,media_count,follower_count,following_count,is_business,email,fullname,liked,followed,created_dt) ".
                                "values (:tag,:taken_at,:code,:caption,:user,:userid,:url,:w,:h,:like_count,:mc,:fer,:fing,:bu,:e,:fulln,:liked,:followed,:now)");
        
        $now = date('Y-m-d H:i:s');

        $a = 
            [
            'tag'=>$tagg,
            'taken_at'=>"$taken_at",
            'code'=>$code,
            'caption'=>$caption,
            'user'=>$user,
            'userid'=>$userid,
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
            'now'=>$now, 
            ];




        $st->execute($a);

    }

}
