<?php


$key = 'Dubai-06';
$key = 'Dubai06';
#$key = '068';

findPrestaProduct($key);

function findPrestaProduct($key) {

    $key = strtoupper($key);

    if (strlen($key)>=4) {
        $id = findPrestaProduct_($key);
    }
    else if (strlen($key)<4) {
        $id = findPrestaProduct_("-$key");
        if (!$id) {
            $id = findPrestaProduct_(" $key");
        }
    }

    if (!$id) {
        $key = preg_replace('/([A-Z]+)(\d+)/',"$1-$2",$key);
        $id = findPrestaProduct_($key);
    }

    print "===== $id\n";
    return $id;
}

function findPrestaProduct_($what) {

    print "--$what--\n";

    $base_url = 'https://www.gellifique.co.uk/api/';
    $api_credentials = array(
    'key' => 'VJSUQERDUXUH2DZPRAPH93UPRC243BQV',
    );

    $ch = curl_init();

    $url = "$base_url/products?filter[reference]=%[$what]";

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

    print "*** $prod_id ***";

    return $prod_id;

}

