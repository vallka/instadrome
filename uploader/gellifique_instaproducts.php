<?php
require 'meekrodb.2.3.class.php';

go();

function go() {

    $id = $_GET['id'];

    $parno = 2;

    $parametersFilepath = "config/parameters{$parno}.php";
    $parameters = require($parametersFilepath);

    $parameters = $parameters['parameters'];

    //var_dump($parameters);

    DB::$user = $parameters['database_user'];
    DB::$password = $parameters['database_password'];
    DB::$dbName = $parameters['database_name'];
    DB::$host = $parameters['database_host'];
    DB::$port = $parameters['database_port'];
    DB::$encoding = 'utf8mb4';


    if ($id and is_numeric($id)) {
        $w = "%,$id,%";
    }
    else {
        print "error";
        exit;
    }

    $sql = "SELECT code,media_type FROM instagrab_gellifique_gel_colour where products!='' and username='gellifique_gel_colour' and concat(',',products) like %s ORDER BY taken_at DESC";

    $res = DB::query($sql,$w);

    $a = [];
    foreach($res as $r) {
        //print "{$r['code']} {$r['media_type']}\n";
        $a[] = [$r['code'],$r['media_type']];
    }


    print json_encode($a);
}
