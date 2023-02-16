<?php
require 'conf.php';
require 'lib/drupal-rest-php/drupal.php';
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$drupal = new DrupalRestAPI($config['drupal']);

function fotokiste_send_message($node, $recipients) {
  global $drupal;
  global $config;

  if (is_string($node) || is_int($node)) {
    $node = $drupal->nodeGet($node);
  }

  $author = $drupal->userGet($node['uid'][0]['target_id']);
  $attachments = [];
  foreach ($node['field_attachments'] as $i => $attachment) {
    $attachments[] = fotokisteMediaToAttachment($attachment['target_id']);
  }


  foreach ($recipients as $r) {
    $mail = new PHPMailer(true);
    $format = $r[2];

    //$mail->isSMTP();
    //$mail->Host = 'smtp.example.com';

    $mail->setFrom($config['mail']['from'], "{$author['field_name'][0]['value']} via {$config['mail']['sender']}");
    $mail->addAddress($r[1], $r[0]);

    $text = $node['body'][0]['value'];
    $url = "{$config['drupal']['url']}{$node['path'][0]['alias']}";
    if ($node['body'][0]['format'] !== 'text') {
      $text .= "<hr>Lies diese Mail online: <a href=\"{$url}\">{$url}</a>";
    }
    else {
      $text .= "\n--------------------------------------------------\nLies diese Mail online: {$url}";
    }

    $mail->isHTML($node['body'][0]['format'] !== 'text');
    $mail->Subject = "{$config['mail']['subjectPrefix']}{$node['title'][0]['value']}";
    $mail->Body = $text;
    $mail->CharSet = 'UTF-8';

    if ($format === 'full') {
      foreach ($attachments as $a) {
	$mail->addStringAttachment($a[0], $a[1]);
      }
    }

    try {
      $mail->send();
    }
    catch (Exception $e) {
      print $mail->ErrorInfo;
    }
  }
}

function fotokisteMediaToAttachment ($mid) {
  global $drupal;

  $media = $drupal->mediaGet($mid);

  $content = file_get_contents($media['field_media_image'][0]['url']);
  return [$content, $media['name'][0]['value']];
}

fotokiste_send_message(29294, [
  ['Stephan Bösch-Plepelits', 'skunk@xover.mud.at', 'full'],
  ['Test', 'skunk@cg.tuwien.ac.at', 'plain'],
]);
