<?php

$photoDir = '/mnt/d/Local/photo/process';

$file_path = $photoDir . '/' . 'DJI_0286_Photolemur3-edit.jpg';

//var_dump(get_imagga_tags($file_path,10));

var_dump(get_iptc_data($file_path));

function get_imagga_tags($file,$count) {


    $api_credentials = array(
        'key' => 'acc_198112daf8098f8',
        'secret' => '932bdff581b7c8186f345ba5830b5ed0'
    );

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://api.imagga.com/v2/tags");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_USERPWD, $api_credentials['key'].':'.$api_credentials['secret']);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_POST, 1);
    $fields = [
        'image' => new \CurlFile($file, 'image/jpeg', 'image.jpg'),
        'limit' => $count
    ];
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);

    $response = curl_exec($ch);
    curl_close($ch);

    $json_response = json_decode($response);
    //var_dump($json_response);

    $tags = [];
    foreach ($json_response->result->tags as $tag) {
        $tags[] = $tag->tag->en;
        //print $tag->tag->en."\n";
    }
    return $tags;
}

function get_iptc_data( $image_path ) {
    /*
    $iptcHeaderArray = array
    (
        '2#005'=>'DocumentTitle',
        '2#010'=>'Urgency',
        '2#015'=>'Category',
        '2#020'=>'Subcategories',
        '2#040'=>'SpecialInstructions',
        '2#055'=>'CreationDate',
        '2#080'=>'AuthorByline',
        '2#085'=>'AuthorTitle',
        '2#090'=>'City',
        '2#095'=>'State',
        '2#101'=>'Country',
        '2#103'=>'OTR',
        '2#105'=>'Headline',
        '2#110'=>'Source',
        '2#115'=>'PhotoSource',
        '2#116'=>'Copyright',
        '2#120'=>'Caption',
        '2#122'=>'CaptionWriter'
    );
    */


    $return = array('title' => '', 'subject' => '', 'tags' => [], 'location'=>'');
    $size = getimagesize ( $image_path, $info);

    if(is_array($info) and isset($info["APP13"])) {
        $iptc = iptcparse($info["APP13"]);
        //var_dump($iptc); // this will show all the data retrieved but I'm only concerned with a few 
        $return['title'] = isset($iptc['2#005']) ? $iptc['2#005'][0] : '';
        $return['subject'] = isset($iptc['2#120']) ? $iptc['2#120'][0] : '';
        $return['tags'] = isset($iptc['2#025']) ? $iptc['2#025'] : [];

        $return['location'] = isset($iptc['2#090'])? $iptc['2#090'][0] : '';
        if (isset($iptc['2#101'])) {
            $return['location'] = $return['location'] ? ("{$return['location']}, ") : '';
            $return['location'] .= $iptc['2#101'][0];
        }

    }
    return $return;
}

