<?php
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



set_time_limit(0);
date_default_timezone_set('UTC');
require __DIR__.'/vendor/autoload.php';
/////// CONFIG ///////
$username = 'val2ka';
$password = 'll440Hym&ig';
$debug = false;
$truncatedDebug = false;
//////////////////////
\InstagramAPI\Instagram::$allowDangerousWebUsageAtMyOwnRisk = true;
$ig = new \InstagramAPI\Instagram($debug, $truncatedDebug);

try {
    $ig->login($username, $password);
} catch (\Exception $e) {
    echo 'Something went wrong: '.$e->getMessage()."\n";
    exit(0);
}

try {
    // Get the UserPK ID for "natgeo" (National Geographic).
    //$userId = $ig->people->getUserIdForName('gellifique_gel_colour');
    // Starting at "null" means starting at the first page.
    $n=0;
    $maxId = null;
    do {
        // Request the page corresponding to maxId.
        //$response = $ig->timeline->getUserFeed($userId, $maxId);
        $response = $ig->timeline->getSelfUserFeed($maxId);
        // In this example we're simply printing the IDs of this page's items.
        $items = $response->getItems();
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
            $url = $pdo->quote($url);
            $code = $pdo->quote($code);
            $caption = $pdo->quote($caption);
            $id_image = $pdo->quote($id_image);

            printf("%s:%s:%d\n[%s] [%s] [%s]\n\n", 
                $caption,
                $taken_at,
                $item->getLikeCount(),
                $item->getId(), 
                $item->getCode(),
                $url

            );

            $st = $pdo->prepare("select id from {$_DB_PREFIX_}instagram where code=$code");
            $st->execute();
            $eid = $st->fetchColumn(0);
            if ($eid) {
                $sql=<<<EOD

                update {$_DB_PREFIX_}instagram 
                    set taken_at='$taken_at',caption=$caption,like_count=$like_count,url=$url,width=$w,height=$h,updated_dt=now()
                where code=$code    
EOD;

                }
            else {
                $sql=<<<EOD

                insert into {$_DB_PREFIX_}instagram (
                    taken_at,code,id_image,caption,like_count,url,width,height 
                ) 
                values (
                    '$taken_at',$code,$id_image,$caption,$like_count,$url,$w,$h
                )
EOD;
            }

        
        
        
            $stmt = $pdo->query($sql);

        }
        // Now we must update the maxId variable to the "next page".
        // This will be a null value again when we've reached the last page!
        // And we will stop looping through pages as soon as maxId becomes null.
        $maxId = $response->getNextMaxId();
        // Sleep for 5 seconds before requesting the next page. This is just an
        // example of an okay sleep time. It is very important that your scripts
        // always pause between requests that may run very rapidly, otherwise
        // Instagram will throttle you temporarily for abusing their API!
        ++$n;
        echo "Sleeping for 5s...\n";
        sleep(5);
    } while ($maxId !== null and $n<1); // Must use "!==" for comparison instead of "!=".
} catch (\Exception $e) {
    echo 'Something went wrong: '.$e->getMessage()."\n";
}

