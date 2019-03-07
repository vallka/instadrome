<?php

#$image_url = 'http://docs.imagga.com/static/images/docs/sample/japan-605234_1280.jpg';

$image_url = 'http://gallery.vallka.com/storage/cache/images/000/443/IMGP0698,xlarge.1511825128.jpg';

//$image_url = 'http://photo.vallka.com/albums/fotomodel-bruno/IMGP9648.jpg';

/*
$api_credentials = array(
'key' => 'acc_198112daf8098f8',
'secret' => '932bdff581b7c8186f345ba5830b5ed0'
);
*/


$api_url = 'https://keywordsready.com/api/analyzes';
//$image_url = ‘<URL TO JPEG IMAGE>’ ;
$api_key = '13411L7FWmFYiLab5Hmz8Z44sJwtt' ;
$headers = ["api-key: $api_key"];
$params = ['url'=>$image_url];

/*
response = RestClient.post api_url, {'url': image_url}, {'api-key': api_key} 
response_JSON = JSON.parse(response.body) 
*/

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HEADER, FALSE);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
//curl_setopt($ch, CURLOPT_USERPWD, $api_credentials['key'].':'.$api_credentials['secret']);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);



$response = curl_exec($ch);
curl_close($ch);

$json_response = json_decode($response);
var_dump($json_response);


// tags
//var_dump($json_response->result->tags);

/*
foreach ($json_response->result->tags as $tag) {
    print $tag->tag->en."\n";
}

Waterway,Sky,Castle,Water,Natural landscape,River,Moat,Bank,Building,Tree,Cloud,Reflection,Watercourse,Architecture,Fortification,Château,water,river,architecture,landscape,castle,outdoors,lake,sky,tree,reflection,horizontal,color image,no people,day,built structure,non-urban scene,building exterior
Tree,Forest,Branch,Old-growth forest,Jungle,tree,wood,nature,outdoors,environment,trunk,leaf,landscape,season,vertical,color image,Old Struan,Scotland,Europe,Northern Europe,natural parkland,public park,autumn,branch - plant part,plant,UK,non-urban scene,day
people,40-49 years,mature adult,snow,winter,sport,fun,adventure,ice,competition,cold,adult,real people,horizontal,gray,color image,copy space,skiing,leisure activity,recreational pursuit,motion,tourist resort,tourism,vitality,effort,endurance,determination,lifestyles,struggle,sports clothing,competitive sport,candid,adults only
Airplane,Aircraft,Vehicle,Air force,Aviation,Military aircraft,Fighter aircraft,Jet aircraft,outdoors,military,sky,house,architecture,horizontal,color image,no people,residential building,day,built structure,building exterior
*/
