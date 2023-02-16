<?php
require 'conf.php';
require 'lib/drupal-rest-php/drupal.php';

$drupal = new DrupalRestAPI($config['drupal']);

$files = json_decode($drupal->get('/dot-files.json'), true);
foreach ($files as $file) {
  print_r($file);
  if ($file['mid']) {
    $media = $drupal->mediaGet($file['mid']);
    $field = 'field_media_image';
    print_r($media[$field]);

    $update = [
      'bundle' => $media['bundle'],
    ];
    $update[$field] = [];

    foreach ($media[$field] as $image) {
      if ($image['target_id'] == $file['fid']) {
	print "REPLACE\n";

	$p = explode('/', $file['field_oldid']);
	$filepath = "/home/tramway/fotokiste_orig/attach/{$p[0]}/{$p[1]}_{$p[2]}";
	print "Uploading $filepath media/{$file['media_bundle']}/{$field}\n";
	$f = $drupal->fileUpload([
	    'filename' => 'image.jpg',
	    'content' => file_get_contents($filepath)
	  ], "media/{$file['media_bundle']}/{$field}"
	);

	$update[$field][] = ['target_id' => $f['fid'][0]['value']];
      } else {
	$update[$field][] = $image;
      }
    }

    print_r($update);
    $drupal->mediaSave($file['mid'], $update);
    $drupal->fileRemove($file['fid']);
  }
}

exit(0);

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
