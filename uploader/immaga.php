<?php

#$image_url = 'http://docs.imagga.com/static/images/docs/sample/japan-605234_1280.jpg';

$image_url = 'http://gallery.vallka.com/storage/cache/images/000/443/IMGP0698,xlarge.1511825128.jpg';

$image_url = 'http://photo.vallka.com/albums/fotomodel-bruno/IMGP9648.jpg';
$api_credentials = array(
'key' => 'acc_198112daf8098f8',
'secret' => '932bdff581b7c8186f345ba5830b5ed0'
);

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, 'https://api.imagga.com/v2/tags?image_url='.$image_url);
#curl_setopt($ch, CURLOPT_URL, 'https://api.imagga.com/v2/categoriers/personal_photos?image_url='.$image_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HEADER, FALSE);
curl_setopt($ch, CURLOPT_USERPWD, $api_credentials['key'].':'.$api_credentials['secret']);

$response = curl_exec($ch);
curl_close($ch);

$json_response = json_decode($response);
var_dump($json_response);


// tags
//var_dump($json_response->result->tags);

foreach ($json_response->result->tags as $tag) {
    print $tag->tag->en."\n";
}
