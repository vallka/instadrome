<?php
require __DIR__ . '/vendor/autoload.php';
use seregazhuk\PinterestBot\Factories\PinterestBot;
require 'meekrodb.2.3.class.php';

/*
$pinparams = [
    'login' => 'vallka@vallka.com',
    'password' => 'll440Hym&pi',
    'boardName' => 'Test board'
];
*/

$pinparams = [
    'login' => 'info@gellifique.com',
    'password' => 'dobroskokina1',
    'boardName' => 'Our products',
    'boardName2' => 'Best Works of Our Partners',
];

set_time_limit(0);
date_default_timezone_set('UTC');
$parno = isset($argv[1]) ? $argv[1] : '2';
$maxread = isset($argv[2]) ? $argv[2] : '2';
$showjson = isset($argv[3]) ? $argv[3] : 'n';

$start_tm = time();

print date('Y-m-d H:i:s')." - par:$parno:$maxread\n";



$pinta = new PintaPoster();
$pinta->run($parno,$maxread,$showjson,$pinparams);

class PintaPoster {
    protected $parameters;
    protected $pinparams;
    protected $pinbot;
    protected $boards;

    function __construct($pinparams=null) {
        $this->pinparams = $pinparams;

    }

    function init() {
        $this->pinbot = PinterestBot::create();
        $this->pinbot->auth->login($this->pinparams['login'], $this->pinparams['password'],false);

        if ($this->pinbot->user->isBanned()) {
            echo "Account has been banned!\n";
            die();
        }

        //$profile = $bot->user->profile();
        //var_dump($profile);

        // get board id
        //$boards = $bot->boards->forUser('my_username');
        $this->boards = $this->pinbot->boards->forMe();
        if (! $this->boards) {
            die('No boards found');
        }
    }

    function findBoardByName($name) {
        if (!$this->pinbot) $this->init();

        foreach ($this->boards as $b) {
            //print $b['name'].' '.$b['id']."\n";
            if ($b['name']==$name) return $b['id'];
        }

        return false;
    }

    function run($parno,$maxread,$showjson,$pinparams=null) {
        if ($pinparams) $this->pinparams = $pinparams;


        $this->readPars($parno);
        $this->connect_db();
        if (!$this->do_a_feed($maxread,$showjson,true)) {
            $this->do_a_feed(1,$showjson,false);
        }
    }    


    function do_a_feed($maxread,$showjson,$newer) {

        if ($newer) {
            $sql =<<<EOD
        
            SELECT username,code,caption,products FROM `instagrab_gellifique_gel_colour` i
            WHERE username in ('gellifique_gel_colour','rusea.nail.art')
            and created_dt>DATE_ADD(now(), INTERVAL -1 DAY)
            and not exists (
            select id from pin_gellifique_gel_colour where source=concat('IG:',i.code)
            )
            order by created_dt,taken_at
            limit 0,$maxread
EOD;
        }
        else {
            $sql =<<<EOD
        
            SELECT username,code,caption,products FROM `instagrab_gellifique_gel_colour` i
            WHERE username in ('gellifique_gel_colour','rusea.nail.art')
            and created_dt<=DATE_ADD(now(), INTERVAL -1 DAY)
            and taken_at>=DATE_ADD(now(), INTERVAL -1 year)
            and not exists (
            select id from pin_gellifique_gel_colour where source=concat('IG:',i.code)
            )
            order by taken_at desc
            limit 0,$maxread
EOD;
        }
        $igs = DB::query($sql);    

        $done = 0;

        if ($igs) {
            $id = $this->findBoardByName($this->pinparams['boardName']);
            $id2 = $this->findBoardByName($this->pinparams['boardName2']);

            if (!$id) {
                die('Board not found');
            }
            if (!$id2) {
                die('Board2 not found');
            }
    
            print "Boarda: $id,$id2\n";

            //var_dump($igs);
            foreach ($igs as $ig) {
                print "IG:{$ig['code']}:{$ig['products']}\n";

                $url = "https://www.instagram.com/p/{$ig['code']}/";
                if ($ig['products']) {
                    $products = array_filter(explode(',',$ig['products']));
                    if ($products[0]) {
                        $url = "https://www.gellifique.co.uk/index.php?controller=product&id_product={$products[0]}";
                    }
                }
                

                $img = "https://www.instagram.com/p/{$ig['code']}/media?size=l";

                $name = $ig['caption'];
                $name = preg_replace('/——+/',' ',$name);
                $name = substr($name,0,500);
                $board_id = $ig['username']=='gellifique_gel_colour' ? $id : $id2;

                $res = $this->pinbot->pins->create($img, $board_id, $name, $url);

                //var_dump($res);
        
                if (!$res) {
                    $error = $this->pinbot->getLastError();
                    print "\n\nE: $error \n";
                }
                else {
                    print ("Pin created: ".$res['id']);
                    $this->updateDb($res['id'],$ig['code'],$name);
                    $this->sleep();
                    $done++;

                }
            }
        }

        print "$newer: $done\n";
        return $done;
    }

    function updateDb($pinId,$igCode,$name) {
        DB::insertUpdate('pin_gellifique_gel_colour', [
            'name' => $name,
            'pin_id' => $pinId,
            'source' => "IG:$igCode"
        ]);
    }


    function connect_db() {
        DB::$user = $this->parameters['database_user'];
        DB::$password = $this->parameters['database_password'];
        DB::$dbName = $this->parameters['database_name'];
        DB::$host = $this->parameters['database_host'];
        DB::$port = $this->parameters['database_port'];
        DB::$encoding = 'utf8mb4';
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
    }
}
