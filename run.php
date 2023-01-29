<?php
require 'conf.php';
require 'lib/drupal-rest-php/drupal.php';

$db = new PDO($config['database']['dsn'], $config['database']['user'], $config['database']['pass']);
$drupal = new DrupalRestAPI($config['drupal']);

$attachment_types = [
  'audio/wav' => [
    'media_bundle' => 'audio',
    'file_field' => 'field_media_audio_file',
    'default_filename' => 'image.wav',
  ],
  'audio/mpeg' => [
    'media_bundle' => 'audio',
    'file_field' => 'field_media_audio_file',
    'default_filename' => 'image.mp3',
  ],
  'audio/microsoft-wave' => [
    'media_bundle' => 'audio',
    'file_field' => 'field_media_audio_file',
    'default_filename' => 'image.wav',
  ],
  'image/pjpeg' => [
    'media_bundle' => 'photography',
    'file_field' => 'field_image',
    'default_filename' => 'image.jpg',
  ],
  'image/jpeg' => [
    'media_bundle' => 'photography',
    'file_field' => 'field_image',
    'default_filename' => 'image.jpg',
    'default_original_filename' => 'image.tiff',
  ],
  'image/jpg' => [
    'media_bundle' => 'photography',
    'file_field' => 'field_image',
    'default_filename' => 'image.jpg',
  ],
  'image/bmp' => [
    'media_bundle' => 'photography',
    'file_field' => 'field_image',
    'default_filename' => 'image.jpg',
    'out_extension' => 'jpg',
    'recode' => 'convert %in %out',
  ],
  'image/tiff' => [
    'media_bundle' => 'photography',
    'file_field' => 'field_image',
    'default_filename' => 'image.jpg',
    'default_original_filename' => 'image.tiff',
    'out_extension' => 'jpg',
    'recode' => 'convert %in %out',
  ],
  'image/gif' => [
    'media_bundle' => 'photography',
    'file_field' => 'field_image',
    'default_filename' => 'image.gif',
  ],
  'image/png' => [
    'media_bundle' => 'photography',
    'file_field' => 'field_image',
    'default_filename' => 'image.png',
  ],
  'text/plain' => [
    'media_bundle' => 'document',
    'file_field' => 'field_media_document',
    'default_filename' => 'file.txt',
  ],
  'application/pdf' => [
    'media_bundle' => 'document',
    'file_field' => 'field_media_document',
    'default_filename' => 'file.pdf',
  ],
  'application/msword' => [
    'media_bundle' => 'document',
    'file_field' => 'field_media_document',
    'default_filename' => 'file.doc',
  ],
  'application/vnd.ms-excel' => [
    'media_bundle' => 'document',
    'file_field' => 'field_media_document',
    'default_filename' => 'file.xls',
  ],
  'application/vnd.ms-powerpoint' => [
    'media_bundle' => 'document',
    'file_field' => 'field_media_document',
    'default_filename' => 'file.ppt',
  ],
  'application/x-zip-compressed' => [
//    'media_bundle' => 'datei',
//    'file_field' => 'field_original_file',
//    'default_filename' => 'file.zip',
    'skip' => true,
  ],
  'application/x-msdownload' => [
    'skip' => true,
  ],
  'application/octet-stream' => [
    'skip' => true,
  ],
  'application/pkcs7-signature' => [
    'skip' => true,
  ],
  'application/ms-tnef' => [
    'skip' => true,
  ],
  'text/html' => [
    'skip' => true,
  ],
  'text/x-vcard' => [
    'skip' => true,
  ],
  'video/quicktime' => [
    'media_bundle' => 'video',
    'file_field' => 'field_media_video_file',
    'default_filename' => 'video.mp4',
    'out_extension' => 'mp4',
# -vf "scale=1650:-1" 
    'recode' => 'ffmpeg -y -i %in -acodec aac -strict experimental -vcodec libx264 -preset slow -crf 28 -pix_fmt yuv420p -threads 4 %out',
  ],
  'multipart/appledouble' => [
    'skip' => true,
  ],
];


$drupal_user = [];
foreach ($drupal->loadRestExport('/rest/user', ['paginated' => true]) as $user) {
  foreach ($user['mail'] as $mail) {
    $drupal_user[strtolower($mail['value'])] = $user['uid'][0]['value'];
  }
  foreach ($user['field_aliases'] as $mail) {
    $drupal_user[strtolower($mail['value'])] = $user['uid'][0]['value'];
  }
}

$tags = [];
foreach ($drupal->loadRestExport('/rest/tags', ['paginated' => true]) as $tag) {
  $tags[$tag['name'][0]['value']] = $tag['tid'][0]['value'];
}
//$m = $drupal->mediaGet(21);
//$u = [
//  'bundle' => $m['bundle'],
//  'field_oldid' => [['value' => 'fotokiste-archiv/4/1']],
//];
//$drupal->mediaSave(21, $u);
//update_media($drupal->mediaGet(21));

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

function update_media ($media) {
  global $drupal;
  global $db;

  $oldid = explode('/', $media['field_oldid'][0]['value']);

  $mediaUpdate = [
    'bundle' => $media['bundle'],
    'field_keywords' => $media['field_keywords'],
  ];

  $existingTags = [];
  foreach ($media['field_keywords'] as $v) {
    $existingTags[$v['target_id']] = true;
  }

  $resT = $db->query("select * from tag where kistenname=" . $db->quote($oldid[0]) . " and msg_number=" . $db->quote($oldid[1]) . " and att_id=" . $db->quote($oldid[2]));
  while ($elemT = $resT->fetch()) {
    if (preg_match('/^[12][0-9][0-9x][0-9x](-.*|)$/', $elemT['tag'])) {
      if (!sizeof($media['field_date_time'])) {
	$mediaUpdate['field_date_time'] = [['value' => $elemT['tag']]];
      } else {
	print "  Got date {$elemT['tag']}, photo date {$media['field_date_time'][0]['value']}\n";
      }

      continue;
    }

    $v = get_tag($elemT['tag']);
    if (!array_key_exists($v, $existingTags)) {
      $mediaUpdate['field_keywords'][] = ['target_id' => $v];
    }
  }

  $drupal->mediaSave($media['mid'][0]['value'], $mediaUpdate);
}

function get_user_id ($_sender) {
  global $drupal_user;
  global $drupal;

  $sender = parse_sender($_sender);

  if (!array_key_exists('mail', $sender)) {
    print "UNKNOWN SENDER {$_sender}\n";
    return null;
  }

  $sender['mail'] = strtolower($sender['mail']);

  if (array_key_exists($sender['mail'], $drupal_user)) {
    return $drupal_user[$sender['mail']];
  }

  $id = explode('@', $sender['mail'])[0];

  $user = [
    'name' => [['value' => $id]],
    'field_name' => [['value' => $sender['name']]],
    'mail' => [['value' => $sender['mail']]],
    'status' => [['value' => true]],
  ];

  print "Create User: {$sender['mail']}\n";
  $user = $drupal->userSave(null, $user);

  $drupal_user[$sender['mail']] = $user['uid'][0]['value'];
  return $user['uid'][0]['value'];
}

function parse_sender ($sender) {
  if (preg_match('/&(lt|gt|quot);/', $sender)) {
    $sender = html_entity_decode($sender);
  }

  if (preg_match('/^"?\'([^\']*)\' ([^\[]*) \[[A-Za-z0-9-]+\]" <.*@yahoogroups.com>$/', $sender, $m)) {
    return [
      'name' => $m[1],
      'mail' => $m[2],
    ];
  }
  elseif (preg_match('/^"([^@]*) ([^ ]*) \[[A-Za-z0-9-]+\]" <.*@yahoogroups.com>$/', $sender, $m)) {
    return [
      'name' => $m[1],
      'mail' => $m[2],
    ];
  }
  elseif (preg_match('/^([^@]*) ([^ ]*) \[[A-Za-z0-9-]+\] <.*@yahoogroups.com>$/', $sender, $m)) {
    return [
      'name' => $m[1],
      'mail' => $m[2],
    ];
  }
  elseif (preg_match('/^"([^ ]*)@([^ ]*) \[[A-Za-z0-9-]+\]" <.*@yahoogroups.com>$/', $sender, $m)) {
    return [
      'name' => $m[1],
      'mail' => "{$m[1]}@{$m[2]}",
    ];
  }
  elseif (preg_match('/^"(.*) <(.*)>" *<(.*)>$/', $sender, $m)) {
    return [
      'name' => $m[1],
      'mail' => $m[2],
    ];
  }
  elseif (preg_match('/^"(.*)" *<(.*)>$/', $sender, $m)) {
    return [
      'name' => $m[1],
      'mail' => $m[2],
    ];
  }
  elseif (preg_match('/^(.+) *<(.*)>$/', $sender, $m)) {
    return [
      'name' => trim($m[1]),
      'mail' => $m[2],
    ];
  }
  elseif (preg_match('/^<(.*)@(.*)>$/', $sender, $m)) {
    return [
      'name' => $m[1],
      'mail' => "{$m[1]}@{$m[2]}",
    ];
  }
  elseif (preg_match('/^(.*)@(.*)$/', $sender, $m)) {
    return [
      'name' => $m[1],
      'mail' => "{$m[1]}@{$m[2]}",
    ];
  }

  return [];
}

function convert_body ($str) {
  if (preg_match('/&[A-Za-z]+;/', $str)) {
    return html_entity_decode($str);
  }

  return $str;
}

function convert_title ($str) {
  $str = convert_body($str);

  if (preg_match('/(.*)\[stadtverkehr-austria-fotos\](.*)$/', $str, $m)) {
    return trim(trim($m[1]) . ' ' . trim($m[2]));
  }

  if (!$str) {
    $str = '(leer)';
  }

  return $str;
}

function add_attachment (&$node, $elem) {
  global $drupal;
  global $attachment_types;

  $existing = iterator_to_array($drupal->loadRestExport('/rest/media?oldid=' . urlencode("{$elem['kistenname']}/{$elem['msg_number']}/{$elem['att_id']}"), ['paginated' => false]));
  if (sizeof($existing)) {
    print "found attachment\n";
    $node['field_attachments'][] = $existing[0]['mid'][0]['value'];
    return;
  }

  $filepath = "/home/tramway/fotokiste_orig/attach/{$elem['kistenname']}/{$elem['msg_number']}_{$elem['att_id']}";
  if (!file_exists($filepath)) {
    print "{$filepath} does not exist!\n";
    return;
  }

  if (!filesize($filepath)) {
    print "{$filepath} is empty!\n";
    return;
  }

  $content_type = strtolower($elem['content_type']);
  if (array_key_exists($content_type, $attachment_types)) {
    $media_type = $attachment_types[$content_type];
  } else {
    print "UNKNOWN MEDIA TYPE! ";
    print_r($elem);
    exit(1);
  }

  if (array_key_exists('skip', $media_type)) {
    print "skipping {$elem['content_type']}\n";
    return;
  }

  print "  {$elem['filename']}\n";
  if (!preg_match('/\./', $elem['filename'], $m)) {
    $extension = explode('.', $media_type['default_filename'])[1];
    print "  Changing filename from {$elem['filename']} to {$elem['filename']}.{$extension}\n";
    $elem['filename'] = "{$elem['filename']}.{$extension}";
  }

  $media = [
    'bundle' => [['target_id' => $media_type['media_bundle']]],
    'name' => [['value' => $elem['filename'] ? $elem['filename'] : $media_type['default_filename']]],
    'uid' => $node['uid'],
    'created' => $node['created'],
    'field_oldid' => [['value' => "{$elem['kistenname']}/{$elem['msg_number']}/{$elem['att_id']}"]],
  ];

  if (preg_match('/^(.*\.jpg)./i', $elem['filename'], $m)) {
    print "  Changing filename from {$elem['filename']} to {$m[1]}\n";
    $elem['filename'] = $m[1];
  }

  if (array_key_exists('recode', $media_type)) {
    $recode_cmd = strtr($media_type['recode'], [
      '%in' => $filepath,
      '%out' => "/tmp/tmp." . $media_type['out_extension'],
    ]);
    if (system($recode_cmd) === false) {
      print "Error executing: {$recode_cmd}\n";
      exit(1);
    }

    $original_file = $drupal->fileUpload([
	'filename' => $elem['filename'] ? $elem['filename'] : $media_type['default_original_filename'],
	'content' => file_get_contents($filepath)
      ], "media/{$media_type['media_bundle']}/field_original_file");

    $media['field_original_file'] = [['target_id' => $original_file['fid'][0]['value']]];

    $filepath = "/tmp/tmp." . $media_type['out_extension'];
    $elem['filename'] = pathinfo($elem['filename'])['filename'] . '.' . $media_type['out_extension'];
  }

  $file = $drupal->fileUpload([
      'filename' => $elem['filename'] ? $elem['filename'] : $media_type['default_filename'],
      'content' => file_get_contents($filepath)
    ], "media/{$media_type['media_bundle']}/{$media_type['file_field']}"
  );

  $media[$media_type['file_field']] = [['target_id' => $file['fid'][0]['value']]];

  $media = $drupal->mediaSave(null, $media);

  update_media($media);

  $node['field_attachments'][] = $media['mid'][0]['value'];
}

function update_all_files () {
  global $drupal;

  foreach ($drupal->loadRestExport('/rest/media', ['paginated' => true]) as $media) {
    $fid = $media['field_image'][0]['target_id'];

    $file = $drupal->fileGet($fid);

    $fileUpdate = [
      'uid' => $media['uid'],
      'created' => $media['created'],
      'type' => $file['type'],
    ];

    $drupal->fileSave($fid, $fileUpdate);
  }
}

$msg_number = file_get_contents('msg_number');
$res = $db->query("select * from message where kistenname='stadtverkehr-austria-fotos' and msg_number>" . $db->quote($msg_number) . " order by msg_number");
if (!$res) {
  print_r($db->errorInfo());
  exit(1);
}

while ($elem = $res->fetch()) {
  $nodes = iterator_to_array($drupal->loadRestExport('/rest/content?type=message&oldid=' . urlencode("{$elem['kistenname']}/{$elem['msg_number']}"), ['paginated' => false]));
  if (sizeof($nodes)) {
    $node = $nodes[0];
    if ($elem['replyto'] && !sizeof($node['field_reply_to_msgid'])) {
      $update = [
        'type' => $node['type'],
	'field_reply_to_msgid' => [['value' => $elem['replyto']]],
      ];

      print "  updating reply\n";
      $drupal->nodeSave($node['nid'][0]['value'], $update);
    }

    print "- Skip {$elem['kistenname']}/{$elem['msg_number']} - already exist\n";
    continue;
  }

  $user_id = get_user_id($elem['sender']);
  if (!$user_id) {
    print $elem['body'];
    print "{$elem['kistenname']}/{$elem['msg_number']}\n";
    exit(1);
  }

  $node = [
    'type' => [['target_id' => 'message']],
    'uid' => [['target_id' => $user_id]],
    'title' => [['value' => convert_title($elem['subject'])]],
    'field_oldid' => [['value' => "{$elem['kistenname']}/{$elem['msg_number']}"]],
    'created' => [['value' => $elem['date']]],
    'body' => [['value' => convert_body($elem['body']), 'format' => 'text']],
    'field_attachments' => [],
  ];

  if ($elem['replyto']) {
    $node['field_reply_to_msgid'] = [['value' => $elem['replyto']]];
  }

  $resA = $db->query("select * from attachment where kistenname=" . $db->quote($elem['kistenname']) . " and msg_number=" . $db->quote($elem['msg_number']));
  while ($elemA = $resA->fetch()) {
    add_attachment($node, $elemA);
  }

  $node = $drupal->nodeSave(null, $node);

  print "- Saved {$elem['kistenname']}/{$elem['msg_number']} -> {$node['nid'][0]['value']}\n";
  file_put_contents('msg_number', $elem['msg_number']);
}
