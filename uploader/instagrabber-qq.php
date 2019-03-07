<?php

require 'meekrodb.2.3.class.php';

set_time_limit(0);
date_default_timezone_set('UTC');

$parno = isset($argv[1]) ? $argv[1] : '2';

$maxread = isset($argv[2]) ? $argv[2] : '100';

$start_tm = time();

print date('Y-m-d H:i:s')." - par:$parno:$maxread\n";


$instagrabber = new Instagrabber();
$instagrabber->run($parno,$maxread);


$end_tm = time();

print "***************************\n".date('Y-m-d H:i:s')." - " .($end_tm-$start_tm)." sec\n";


class Instagrabber {
    protected $parameters;
    protected $pdo;

    function run($parno,$maxread) {
        $this->readPars($parno);
        $this->connect_db();
        $this->do_a_feed($maxread);
    }

    function do_a_feed($maxread) {
                    $sql = "SELECT code,media_type FROM instagrab_gellifique_gel_colour where products!='' ORDER BY taken_at DESC";

                    $res = DB::query($sql);
                
                    foreach($res as $r) {
                        print "{$r['code']} {$r['media_type']}\n";
                    }
    }


    function connect_db() {
        var_dump($this->parameters);

        DB::$user = $this->parameters['database_user'];
        DB::$password = $this->parameters['database_password'];
        DB::$dbName = $this->parameters['database_name'];
        DB::$host = $this->parameters['database_host'];
        DB::$port = $this->parameters['database_port'];
        DB::$encoding = 'utf8mb4';


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

