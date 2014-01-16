<?php
/*
 * Migration script from Drupal 6 forum to PhpBB 3 
 * Based on http://www.frihost.com/forums/vt-124293.html
 * Customized for Ubuntu Mexico
 *
 * Author: Riccardo Padovani <riccardo@rpadovani.com>
 * Copyright: 2014 Riccardo Padovani
 * License: GPL-2+
 */

/*
 * Preparation (using MySql as database)
 *
 * 0. Create a db and import old drupal installation:
 * mysql -u root -p 
 * CREATE DATABASE ubuntu_mx;
 * quit;
 * mysql -u root -p ubuntu_mx < ubuntumexico_forum_database.sql
 *
 * 1. Create a db for PhpBB:
 * mysql -u root -p 
 * CREATE DATABASE ubuntu_mx_forum;
 * quit; 
 *
 * 2. Install PhpBB:
 * Follow istructions on browser
 * Create old sections and give to the "registered users" and "guests" groups the "Standard Access" permission role on each section
 * Populate array $forums with couples $oldSectionId => $newSectionId
 *
 * 3: Run the script:
 * Put this script in phpbb/adm/
 * Go to admin panel and sobstitute in address bar index.php with script.php.
 * KEEP THE ?sid=abcdefgh1234567 !!!!!!!
 * The script takes a lot of time
 */

// POPULATE BEFORE STARTING.
$forums = array(24=>2);
$db_username = 'old_database_username';
$db_password = 'old_database_password'; 
$db_database = 'ubuntu_mx';


define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : '../';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
include($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
include($phpbb_root_path . 'includes/functions_display.' . $phpEx);
include($phpbb_root_path . 'includes/functions_user.' . $phpEx);
include($phpbb_root_path . 'includes/message_parser.' . $phpEx);

$old = new PDO('mysql:host=localhost;dbname=' . $db_database, $db_username, $db_password);

$user->session_begin();

$users = array(0 => array(
  'username' => 'Anonymous',
  'user_id' => 1,
  'user_ip' => '0.0.0.0',
));
foreach ($old->query('SELECT * FROM users WHERE uid > 0') as $u)
{
  $q2 = $old->query('SELECT hostname FROM accesslog WHERE uid = ' . $u['uid'] .
   ' ORDER BY timestamp DESC LIMIT 1');
  $q2->execute();
  $r2 = $q2->fetch();
  $a = (empty($r2) ? array('hostname' => '0.0.0.0') : $r2[0]);
  $users[$u['uid']] = array(
    // Added utf8_encode for Mexican names
    'username'      => utf8_encode($u['name']),
    'user_password' => $u['pass'], // phpBB will support md5 passwords! Yes!
    'user_email'    => $u['mail'],
    'group_id'      => ($u['uid'] == 1 ? 5 : 2),
    'user_timezone' => ((float) $u['timezone']) / 3600,
    'user_type'     => 0,
    'user_ip'       => $a['hostname'],
    'user_regdate'  => $u['created'],
  );
  $users[$u['uid']]['user_id'] = user_add($users[$u['uid']]);
}
// Until here all ok!

// THIS PART DOESN"T WORK AS EXPECTED

//s/Forums/Foros
foreach ($old->query("SELECT n.*, t.*, v.* FROM node n INNER JOIN term_node t ON t.nid=n.nid INNER JOIN node_revisions v ON v.nid=n.nid WHERE v.vid=n.vid AND n.type='forum' AND t.tid IN (SELECT term_data.tid FROM term_data INNER JOIN vocabulary ON term_data.vid=vocabulary.vid WHERE vocabulary.name='Foros')") as $topic)
{
  set_time_limit(600);

  $message = utf8_normalize_nfc($topic['body']);
  $topic_title = utf8_normalize_nfc($topic[21]);
  $uid;
  $bitfield;
  $flags;

  $user->ip = $users[$topic['uid']]['user_ip'];
  $user->data['user_id'] = $users[$topic['uid']]['user_id'];
  $user->data['user_colour'] = ($topic['uid'] == 1 ? 'AA0000' : '');
  $user->data['is_registered'] = ($topic['uid'] != 0);
  $user->data['username'] = $users[$topic['uid']]['username'];
  print_r($user->data);
  print_r($auth->acl);
  $auth->acl($user->data);

  generate_text_for_storage($message, $uid, $bitfield, $flags, true, true, true);

  $data = array(
    'forum_id' => $forums[$topic['tid']],
    'topic_id' => 0,
    'icon_id' => false,
    'enable_bbcode' => true,
    'enable_smilies' => true,
    'enable_urls' => true,
    'enable_sig' => true,
    'message' => $message,
    'message_md5' => md5($message),
    'post_edit_locked' => 0,
    'topic_title' => $topic_title,
    'notify_set' => false,
    'notify' => false,
    'post_time' => $topic['timestamp'],
    'forum_name' => '',
    'enable_indexing' => true,
    'force_approved_state' => true,
    'bbcode_uid' => $uid,
    'bbcode_bitfield' => $bitfield,
    'poster_ip' => $user->ip,
    'topic_time_limit' => 0,
  );

  $poll = array();
  submit_post('post', $topic_title, 'Anonymous', ($topic['sticky'] ? POST_STICKY : POST_NORMAL), $poll, $data);

  foreach ($old->query("SELECT * FROM comments WHERE nid='" . $topic['nid'] . "'") as $post)
  {
    $message = utf8_normalize_nfc($post['comment']);
    $uid;
    $bitfield;
    $flags;

    $user->ip = $post['hostname'];
    $user->data['user_id'] = $users[$post['uid']]['user_id'];
    $user->data['user_colour'] = ($post['uid'] == 1 ? 'AA0000' : '');
    $user->data['is_registered'] = ($post['uid'] != 0);
    $user->data['username'] = $users[$post['uid']]['username'];
    $auth->acl($user->data);

    generate_text_for_storage($message, $uid, $bitfield, $flags, true, true, true);

    $data1 = array(
      'forum_id' => $forums[$topic['tid']],
      'topic_id' => $data['topic_id'],
      'icon_id' => false,
      'enable_bbcode' => true,
      'enable_smilies' => true,
      'enable_urls' => true,
      'enable_sig' => true,
      'message' => $message,
      'message_md5' => md5($message),
      'post_edit_locked' => 0,
      'topic_title' => $post['subject'],
      'notify_set' => false,
      'notify' => false,
      'post_time' => $post['timestamp'],
      'forum_name' => '',
      'enable_indexing' => true,
      'force_approved_state' => true,
      'bbcode_uid' => $uid,
      'bbcode_bitfield' => $bitfield,
      'poster_ip' => $user->ip,
      'topic_time_limit' => 0,
    );

    $poll = array();
    submit_post('reply', (isset($post['title']) ? utf8_normalize_nfc($post['title']) : 'Re: ' . $topic_title), 'Anonymous', ($topic['sticky'] ? POST_STICKY : POST_NORMAL), $poll, $data1);
  }
} 

echo 'End!';
?>