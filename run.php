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

function get_user_id ($sender) {
  global $drupal_user;
  global $drupal;

  $sender = parse_sender($sender);

  if (array_key_exists($sender['mail'], $drupal_user)) {
    return $drupal_user[$sender['mail']];
  }

  $user = [
    'name' => [['value' => $sender['name']]],
    'field_name' => [['value' => $sender['name']]],
    'mail' => [['value' => $sender['mail']]],
    'status' => [['value' => false]],
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

  print "$sender\n";

  return [];
}

$res = $db->query('select sender from message group by sender');
if (!$res) {
  print_r($db->errorInfo());
  exit(1);
}

while ($elem = $res->fetch()) {
  print "* {$elem['sender']}: ";
  print_r(parse_sender($elem['sender']));
}
