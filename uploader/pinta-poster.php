<?php
require __DIR__ . '/vendor/autoload.php';
use seregazhuk\PinterestBot\Factories\PinterestBot;

$params = [
    'login' => 'vallka@vallka.com',
    'password' => 'll440Hym&pi',
    'boardName' => 'Test board'
];



$bot = PinterestBot::create();
$bot->auth->login($params['login'], $params['password'],false);

if ($bot->user->isBanned()) {
    echo "Account has been banned!\n";
    die();
}

//$profile = $bot->user->profile();

//var_dump($profile);

// get board id
//$boards = $bot->boards->forUser('my_username');
$boards = $bot->boards->forMe();

//var_dump($boards);

$id = findBoardByName($boards,$params['boardName']);

if (!$id) {
    die('Board not found');
}

print $id."\n";

$img = 'http://photo.vallka.com/cache/------2016/a7908fa435d6d9698f71ad5a340fe8b5eb89cc4f.IMG_20160816_174402_1200.jpg';
$img = 'http://photo.vallka.com/zp-core/i.php?a=2015-05%20-%20Falls%20of%20Bruar%20and%20around%20the%20wood&i=IMGP9706.jpg&s=1200&cw=0&ch=0&q=85&wmk=%21&check=47c8ccb196dcabe5a0267f64f898d545f68e408f';
$url = 'http://www.vallka.com/';

$res = $bot->pins->create($img, $id, "test pin 2", $url);

var_dump($res['id']);


function findBoardByName($boards,$name) {
    foreach ($boards as $b) {
        print $b['name'].' '.$b['id']."\n";
        if ($b['name']==$name) return $b['id'];
    }

    return false;
}

/*
$boardId = $boards[0]['id'];
// select image for posting
$images = glob('images/*.*');
if (empty($images)) {
    echo "No images for posting\n";
    die();
}
$image = $images[0];
// select keyword
$keyword = $keywords[array_rand($keywords)];
// create a pin
$bot->pins->create($image, $boardId, $keyword, $blogUrl);
// remove image
unlink($image);
*/