<?php
$db = new PDO('mysql:dbname=fotokiste', 'skunk', 'PASSWORD');
require 'lib/drupal-rest-php/drupal.php';
$drupal = new DrupalRestAPI([
  'url' => 'https://xover.mud.at/~tramway/fotokiste_neu',
  'user' => 'plepelits@xover.mud.at',
  'pass' => 'PASSWORD',
  'authMethod' => 'cookie',
]);


$drupal_user = [];
foreach ($drupal->loadRestExport('/rest/user', ['paginated' => true]) as $user) {
  foreach ($user['mail'] as $mail) {
    $drupal_user[$mail['value']] = $user['uid'][0]['value'];
  }
  foreach ($user['field_aliases'] as $mail) {
    $drupal_user[$mail['value']] = $user['uid'][0]['value'];
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
    $v = get_tag($elemT['tag']);
    if (!array_key_exists($v, $existingTags)) {
      $mediaUpdate['field_keywords'][] = ['target_id' => $v];
    }
  }

  $drupal->mediaSave($media['mid'][0]['value'], $mediaUpdate);
}

function get_user_id ($sender) {
  global $drupal_user;
  global $drupal;

  $sender = parse_sender($sender);

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

  return $str;
}

function add_attachment (&$node, $elem) {
  global $drupal;

  $filepath = "/home/tramway/fotokiste_orig/attach/{$elem['kistenname']}/{$elem['msg_number']}_{$elem['att_id']}";

  $file = $drupal->fileUpload([
      'filename' => $elem['filename'],
      'content' => file_get_contents($filepath)
    ], 'media/photography/field_image'
  );

  $media = [
    'bundle' => [['target_id' => 'photography']],
    'name' => [['value' => $elem['filename']]],
    'uid' => $node['uid'],
    'created' => $node['created'],
    'field_image' => [['target_id' => $file['fid'][0]['value']]],
    'field_oldid' => [['value' => "{$elem['kistenname']}/{$elem['msg_number']}/{$elem['att_id']}"]],
  ];

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

$res = $db->query('select sender from message group by sender');
if (!$res) {
  print_r($db->errorInfo());
  exit(1);
}

while ($elem = $res->fetch()) {
  print_r($elem);
  $node = [
    'type' => [['target_id' => 'message']],
    'uid' => [['target_id' => get_user_id($elem['sender'])]],
    'title' => [['value' => convert_title($elem['subject'])]],
    'field_oldid' => [['value' => "{$elem['kistenname']}/{$elem['msg_number']}"]],
    'created' => [['value' => $elem['date']]],
    'body' => [['value' => convert_body($elem['body']), 'format' => 'text']],
    'field_attachments' => [],
  ];

  $resA = $db->query("select * from attachment where kistenname=" . $db->quote($elem['kistenname']) . " and msg_number=" . $db->quote($elem['msg_number']));
  while ($elemA = $resA->fetch()) {
    add_attachment($node, $elemA);
  }

  $node = $drupal->nodeSave(null, $node);

  print "- Saved {$elem['kistenname']}/{$elem['msg_number']} -> {$node['nid'][0]['value']}\n";
}
