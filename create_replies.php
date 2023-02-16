<?php
require 'conf.php';
require 'lib/drupal-rest-php/drupal.php';

$db = new PDO($config['database']['dsn'], $config['database']['user'], $config['database']['pass']);
$drupal = new DrupalRestAPI($config['drupal']);
$messages = [];

$r = $db->query("select m.kistenname, m.msg_number as msg_number, r.kistenname as reply_kistenname, r.msg_number reply_msg_number from message m join message r on m.replyto=r.msg_id");
while ($elem = $r->fetch()) {
  $msg = get_message($elem['kistenname'], $elem['msg_number']);
  $reply = get_message($elem['reply_kistenname'], $elem['reply_msg_number']);

  if (!$reply || !$msg) {
    print "invalid\n";
    continue;
  }

  print "{$msg['nid'][0]['value']} -> {$reply['nid'][0]['value']} ... ";

  if (sizeof($msg['field_reply_to'])) {
    print " skip\n";
    continue;
  }

  $update = [
    'type' => [['target_id' => 'message']],
    'field_reply_to' => [['target_id' => $reply['nid'][0]['value']]],
  ];
  $drupal->nodeSave($msg['nid'][0]['value'], $update);
  print " done\n";
}

function get_message($kistenname, $msg_number) {
  global $drupal;
  global $messages;

  $oldid = "{$kistenname}/{$msg_number}";

  if (!array_key_exists($oldid, $messages)) {
    $list = iterator_to_array($drupal->loadRestExport('/rest/content?type=message&oldid=' . urlencode($oldid), ['paginated' => false]));
    if (sizeof($list) > 1) {
      print "TOO MANY RESULTS!\n";
    }

    $messages[$oldid] = sizeof($list) ? $list[0] : null;
  }

  return $messages[$oldid];
}
