<?php

require __DIR__ . '/vendor/autoload.php';
//use Google\Cloud\Vision\V1\ImageAnnotatorClient;
\InstagramAPI\Instagram::$allowDangerousWebUsageAtMyOwnRisk = true;

require 'meekrodb.2.3.class.php';

set_time_limit(0);
date_default_timezone_set('UTC');

$parno = isset($argv[1]) ? $argv[1] : '2';

$maxread = isset($argv[2]) ? $argv[2] : '100';

$showjson = isset($argv[3]) ? $argv[3] : 'n';

$start_tm = time();

print date('Y-m-d H:i:s')." - par:$parno:$maxread\n";


$instagrabber = new Instagrabber();
$instagrabber->run($parno,$maxread,$showjson);


$end_tm = time();

print "***************************\n".date('Y-m-d H:i:s')." - " .($end_tm-$start_tm)." sec\n";


class Instagrabber {
    protected $parameters;
    protected $pdo;

    function run($parno,$maxread,$showjson) {
        $this->readPars($parno);
        $this->connect_db();
        $this->do_a_feed($maxread,$showjson);
    }

    function do_a_feed($maxread,$showjson) {
        $debug = false;
        $truncatedDebug = true;
    
        $username = $this->parameters['instagram_user'];
        $password = $this->parameters['instagram_password'];
        //$tags = $this->parameters['instagram_tags'];
    
    
        $ig = new \InstagramAPI\Instagram($debug, $truncatedDebug);
        try {
            $ig->login($username, $password);
        } catch (\Exception $e) {
            echo 'Something went wrong: '.$e->getMessage()."\n";
            exit(0);
        }
        try {
            $userId = $ig->people->getUserIdForName($username);
            // Starting at "null" means starting at the first page.
            $n=1;
            $i=1;
            $maxId = null;
            do {

                if ($this->parameters['instagram_selftag']) {
                    // Generate a random rank token.
                    $rankToken = \InstagramAPI\Signatures::generateUUID(); 
                    $response = $ig->hashtag->getFeed($this->parameters['instagram_selftag'],$rankToken, $maxId);
                }
                else {
                    $response = $ig->timeline->getUserFeed($userId, $maxId);
                }

                $items = $response->getItems();
                foreach ($items as $item) {
                    $carousel = $item->getCarouselMedia();
                    if ($carousel) {
                        $w =  $carousel[0]->getOriginalWidth();
                        $h =  $carousel[0]->getOriginalHeight();
                    }
                    else {
                        $w =  $item->getOriginalWidth();
                        $h =  $item->getOriginalHeight();
                    }
                    $caption = $item->getCaption() ? $item->getCaption()->getText() : 
                               ($item->getPreviewComments() ? $item->getPreviewComments()[0]->getText() : '');
                    $taken_at = date('Y-m-d H:i:s',$item->getTakenAt());
                    $code = $item->getCode();
                    $media_type = $item->getMediaType();
                    $like_count = $item->getLikeCount();
                    $username = $item->getUser()->getUsername();

                    $product_tags = $item->getProductTags();

                    if ($showjson=='y' or $showjson=='Y') {
                        $item->printJson();
                        print("\n---===================---\n");
                    }
                    //print $item->asJson();
                    //print("\n---===================---\n");
                    //var_dump($product_tags);

                    $prod_ids = '';

                    if ($product_tags) {
                        //$products = $product_tags->getIn()->getProducts();
                        $products = $product_tags->getIn();
                        foreach ($products as $product) {
                            //$product->printJson();
                            //print $product->getProduct()->getName();
                            $exurl = $product->getProduct()->getExternalUrl();

                            $prod_id = null;
                            if (preg_match('/\((\d+)\)/',$exurl,$aa)) {
                                $prod_ids .= $aa[1].',';
                            }

                            print   "$exurl = $prod_id\n";
                        }
                    }
                    
                    if (preg_match_all('/#Gellifique(\w+)/',$item->getCaption(),$aa)) {
                        foreach($aa[1] as $key) {
                            if ($prod_id = findPrestaProduct($key)) {
                                print   "$key = $prod_id\n";
                                $prod_ids .= $prod_id.',';
                            }
                        }
                    }

                    printf("$n:$i)===================\n%s:%s:%s:%d:%d:%d:[%s]\n", 
                        $taken_at,
                        $code,
                        $username,
                        #$caption,
                        $like_count,
                        $w,
                        $h,
                        $prod_ids
                    );

                    print("---===================---\n");

                    DB::insertUpdate('instagrab' . $this->parameters['database_suffix'], array(
                        'code' => $code,
                        'taken_at' => $taken_at,
                        'username' => $username,
                        'caption' => $caption,
                        'like_count' => $like_count,
                        'width' => $w,
                        'height' => $h,
                        'media_type' => $media_type,
                        'products' => $prod_ids,
                        'updated_dt' => DB::sqleval("NOW()")
                      ));

                    ++$i;
                    if ($i > $maxread) {
                        break;
                    }
                }
        
                // Now we must update the maxId variable to the "next page".
                // This will be a null value again when we've reached the last page!
                // And we will stop looping through pages as soon as maxId becomes null.
                $maxId = $response->getNextMaxId();
                //$this->sleep();
                ++$n;
            } while ($maxId !== null and $i<=$maxread and $this->sleep()); // Must use "!==" for comparison instead of "!=".
        } catch (\Exception $e) {
            echo 'Something went wrong: '.$e->getMessage()."\n";
        }

    }


    function connect_db() {
        DB::$user = $this->parameters['database_user'];
        DB::$password = $this->parameters['database_password'];
        DB::$dbName = $this->parameters['database_name'];
        DB::$host = $this->parameters['database_host'];
        DB::$port = $this->parameters['database_port'];
        DB::$encoding = 'utf8mb4';

        /*

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
        */

    }
    function sleep() {
        $secs = rand(2,10);
        print "   sleeping $secs...\n";
        sleep($secs);

        return true;
    }

    function readPars($parno) {
        $parametersFilepath = "config/parameters{$parno}.php";
        $parameters = require($parametersFilepath);

        $this->parameters = $parameters['parameters'];
        
        $host = $parameters['parameters']['database_host'];
        $db   = $parameters['parameters']['database_name'];
        $user = $parameters['parameters']['database_user'];
        $pass = $parameters['parameters']['database_password'];
        
        
    }
}

function findPrestaProduct($key) {

    $key = strtoupper($key);

    if (strlen($key)>=4) {
        $id = findPrestaProduct_($key);
    }
    else if (strlen($key)<4) {
        $id = findPrestaProduct_("PRO-$key");
        if (!$id) $id = findPrestaProduct_("PRO%20$key");
        if (!$id) $id = findPrestaProduct_("-$key");
        if (!$id) $id = findPrestaProduct_("%20$key"); // ' ' must be url-encoded
        
    }

    if (!$id) {
        $key = preg_replace('/([A-Z]+)(\d+)/',"$1-$2",$key);
        $id = findPrestaProduct_($key);
        if (!$id) $key = str_replace('-','%20',$key);
        $id = findPrestaProduct_($key);
    }

    print "===== $id\n";
    return $id;
}

function findPrestaProduct_($what) {

    print "[$what]?\n";

    $base_url = 'https://www.gellifique.co.uk/api/';
    $api_credentials = array(
    'key' => 'VJSUQERDUXUH2DZPRAPH93UPRC243BQV',
    );

    $ch = curl_init();

    //$url = "$base_url/products?filter[reference]=%[$what]";
    $url = "$base_url/products?filter[name]=%[$what]%";
    print "[$url]?\n";

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_USERPWD, $api_credentials['key'].':'.$api_credentials['key']);

    $response = curl_exec($ch);
    curl_close($ch);
    //var_dump($response);

    $prod_id = null;
    if (preg_match('/id="(\d+)"/',$response,$a)) {
        $prod_id = $a[1];
    }

    print "*** [$prod_id] ***!\n";

    return $prod_id;

}

