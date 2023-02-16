<?php
require 'conf.php';
require 'lib/drupal-rest-php/drupal.php';

$drupal = new DrupalRestAPI($config['drupal']);

$errors = json_decode(file_get_contents('errors.json'), true);
foreach ($errors as $mid => $fid) {
  print "$mid ...";
  $media = $drupal->mediaGet($mid);
  $update = [
    'bundle' => $media['bundle'],
    'field_media_image' => [['target_id' => $fid]],
  ];
  $drupal->mediaSave($mid, $update);
  print " done\n";
}
