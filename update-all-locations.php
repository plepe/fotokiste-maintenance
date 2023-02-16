<?php
require 'conf.php';
require 'lib/drupal-rest-php/drupal.php';

$drupal = new DrupalRestAPI($config['drupal']);

foreach ($drupal->loadRestExport('/rest/media?bundle=image', ['paginated' => true, 'startPage' => 40]) as $media) {
  if ($media['mid'][0]['value'] > 0) {
    process($media);
  }
}

function process ($media) {
  global $drupal;
  global $errors;

  $p = explode('/', $media['field_oldid'][0]['value']);
  $filepath = "/home/tramway/fotokiste_orig/attach/{$p[0]}/{$p[1]}_{$p[2]}";
  exec('/usr/bin/exiftool -n ' . escapeshellarg($filepath), $output);
  foreach ($output as $row) {
    if (preg_match('/^([A-Za-z \/]+): (.*)$/', $row, $m)) {
      $fields[trim($m[1])] = $m[2];
    }
  }

  if (array_key_exists('GPS Longitude', $fields)) {
    $update = [
      'bundle' => $media['bundle'],
      'field_location' => [['value' => "POINT ({$fields['GPS Longitude']} {$fields['GPS Latitude']})"]],
    ];

    print "Update {$media['field_oldid'][0]['value']} ...";
    $drupal->mediaSave($media['mid'][0]['value'], $update);
    print " done\n";
  }

}
