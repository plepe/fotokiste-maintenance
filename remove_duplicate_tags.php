<?php
require 'conf.php';
require 'lib/drupal-rest-php/drupal.php';

$drupal = new DrupalRestAPI($config['drupal']);

$duplicate = [];
$tags = [];
foreach ($drupal->loadRestExport('/rest/tags', ['paginated' => true]) as $tag) {
  if (array_key_exists($tag['name'][0]['value'], $tags)) {
    $duplicate[$tags[$tag['name'][0]['value']]][] = $tag['tid'][0]['value'];
  } else {
    $tags[$tag['name'][0]['value']] = $tag['tid'][0]['value'];
  }
}

$replacements  = [];
foreach ($duplicate as $tid => $d) {
  foreach ($d as $id) {
    $replacements[$id] = $tid;
  }
}

foreach ($duplicate as $tid => $d) {
  foreach ($d as $id) {
    $list = $drupal->loadRestExport("/rest/media?tags_id={$id}", ['paginated' => true]);
    foreach (iterator_to_array($list) as $media) {
      $update = [
        'bundle' => $media['bundle'],
	'field_tags' => [],
      ];

      foreach ($media['field_tags'] as $r) {
	$update['field_tags'][] = array_key_exists($r['target_id'], $replacements) ? ['target_id' => $replacements[$r['target_id']]] : $r;
      }
      print "{$media['mid'][0]['value']}: Replace $id by $tid\n";

      $drupal->mediaSave($media['mid'][0]['value'], $update);
    }

    print "Remove taxonomy {$id}\n";
    $drupal->taxonomyRemove($id);
  }
}
