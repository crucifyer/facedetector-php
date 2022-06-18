<?php

if(!isset($_SERVER['argv'][1])) die("php example.php filename\n");
if(!file_exists($_SERVER['argv'][1])) die("{$_SERVER['argv'][1]} file not found\n");

chdir(__DIR__);
include_once '../vendor/autoload.php';

$detector = new \Xeno\Image\FaceDetector($_SERVER['argv'][1]);
if(!$face = $detector->FaceDetect()) die("The face cannot be detected.\n");
$size = $detector->getImageSize();
$direction = \Xeno\Image\FaceDetector::AlignDirection($size['width'], $size['height'], $face['x'], $face['y'], $face['w']);

$im = $detector->getImage();
imagerectangle($im, $face['x'], $face['y'], $face['x'] + $face['w'], $face['y'] + $face['w'], 0xff0000);
imagejpeg($im, 'detected.jpg');

print_r([
	$face, $direction
]);

$faces = $detector->FaceDetect(true);

print_r([
	$faces
]);

$faces = \Xeno\Image\FaceDetector::FilterSmallFaces($faces);

print_r([
	$faces
]);

$detector = new \Xeno\Image\FaceDetector($_SERVER['argv'][1]);
$direction = $detector->getDirection();
echo "direction: $direction\n";

$detector = new \Xeno\Image\FaceDetector($_SERVER['argv'][1]);
$detector->cropThumbnail(300, null, 'thumbnail.300.webp');
$detector->cropThumbnail(500, null, 'thumbnail.500.webp');
