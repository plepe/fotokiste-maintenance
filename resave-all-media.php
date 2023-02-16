<?php
require 'conf.php';
require 'lib/drupal-rest-php/drupal.php';

$drupal = new DrupalRestAPI($config['drupal']);

//$tags = [];
//foreach ($drupal->loadRestExport('/rest/tags', ['paginated' => true]) as $tag) {
//  if (!array_key_exists($tag['name'][0]['value'], $tags)) {
//    $tags[$tag['name'][0]['value']] = $tag['tid'][0]['value'];
//  }
//}

function get_tag ($str) {
  global $tags;
  global $drupal;

  if (!array_key_exists($str, $tags)) {
    $tag = [
      'vid' => [['target_id' => 'tags']],
      'name' => [['value' => $str]],
    ];

    $tag = $drupal->taxonomySave(null, $tag);

    $tags[$tag['name'][0]['value']] = $tag['tid'][0]['value'];
  }

  return $tags[$str];
}


//process('stadtverkehr-austria-fotos', 22417, 1);
//foreach (['stadtverkehr-austria-fotos'] as $kistenname) {
//  $path = "/home/tramway/fotokiste_orig/attach/{$kistenname}";
//  $dir = opendir($path);
//  while ($file = readdir($dir)) {
//    if ($file[0] === '.') {
//      continue;
//    }
//
//    [$msg_number, $att_id] = explode('_', $file);
//    process($kistenname, $msg_number, $att_id);
//  }
//}
$errors = json_decode(file_get_contents('errors.json'), true);

foreach ($drupal->loadRestExport('/rest/media?bundle=image', ['paginated' => true, 'startPage' => 540]) as $media) {
  if ($media['mid'][0]['value'] > 27905) {
    process($media);
  }
}

function process ($media) {
  global $drupal;
  global $errors;

  $currentTags = $media['field_tags'];

  print "Updating {$media['mid'][0]['value']} ({$media['field_oldid'][0]['value']}) (file {$media['field_media_image'][0]['target_id']}) ...";
  $update = [
    'bundle' => $media['bundle'],
    'field_media_image' => [],
  ];
  $drupal->mediaSave($media['mid'][0]['value'], $update);

  $update = [
    'bundle' => $media['bundle'],
    'field_media_image' => $media['field_media_image'],
  ];
  try {
    $media = $drupal->mediaSave($media['mid'][0]['value'], $update);
  }
  catch (Exception $e) {
    $errors[$media['mid'][0]['value']] = $media['field_media_image'][0]['target_id'];
    file_put_contents('errors.json', json_encode($errors));
    print "ERROR\n";
    return;
  }

  $tags = array_map(function ($v) {
    return $v['target_id'];
  }, $media['field_tags']);

  $updateTags = $media['field_tags'];
  foreach ($currentTags as $t) {
    if (!in_array($t['target_id'], $tags)) {
      $updateTags[] = $t;
    }
  }

  if (sizeof($updateTags) !== sizeof($tags)) {
    $update = [
      'bundle' => $media['bundle'],
      'field_tags' => $updateTags,
    ];

    print_r($updateTags);
    $media = $drupal->mediaSave($media['mid'][0]['value'], $update);
  }

  print " done\n";
}

//  $path = "/home/tramway/fotokiste_orig/attach/{$kistenname}";

  //print_r(exif_read_data("{$path}/{$msg_number}_{$att_id}"));
exit(0);

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
