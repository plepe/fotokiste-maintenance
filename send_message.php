<?php
require 'conf.php';
require 'lib/drupal-rest-php/drupal.php';
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$drupal = new DrupalRestAPI($config['drupal']);

function fotokiste_send_message($nid, $recipients) {
  global $drupal;
  global $config;

  $node = $drupal->nodeGet($nid);

  $mail = new PHPMailer(true);

//  $mail->isSMTP();
//  $mail->Host = 'smtp.example.com';

  $mail->setFrom($config['mail']['from']);
  foreach ($recipients as $r) {
    $mail->addAddress($r[1], $r[0]);
  }

  $mail->isHTML($node['body'][0]['format'] !== 'text');
  $mail->Subject = $node['title'][0]['value'];
  $mail->Body = $node['body'][0]['value'];
  $mail->CharSet = 'UTF-8';
  $mail->Encoding = 'base64';

  foreach ($node['field_attachments'] as $i => $attachment) {
    [$file, $fileName] = fotokisteMediaToAttachment($attachment['target_id']);
    $mail->addStringAttachment($file, $fileName);
  }

  try {
    $mail->send();
  }
  catch (Exception $e) {
    print $mail->ErrorInfo;
  }

  //print_r($node);
}

function fotokisteMediaToAttachment ($mid) {
  global $drupal;

  $media = $drupal->mediaGet($mid);

  $content = file_get_contents($media['field_media_image'][0]['url']);
  return [$content, $media['name'][0]['value']];
}

fotokiste_send_message(29260, [
  ['Stephan Bösch-Plepelits', 'skunk@xover.mud.at']
]);
