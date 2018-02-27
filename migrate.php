<?php

use Ramsey\Uuid\Uuid;

include 'vendor/autoload.php';
require 'migrate_config.php';

set_time_limit(0);
$flarum_db = new mysqli(Config::FLARUM_SERVER, Config::FLARUM_USER, Config::FLARUM_PASSWORD, Config::FLARUM_DB);
if ($flarum_db->connect_errno) {
    die('Flarum db connection failed: ' . $flarum_db->connect_error);
}
$discuz_db = new mysqli(Config::DISCUZ_SERVER, Config::DISCUZ_USER, Config::DISCUZ_PASSWORD, Config::DISCUZ_DB);
if ($discuz_db->connect_errno) {
    die('MyBB db connection failed: ' . $discuz_db->connect_error);
}

$discuz_db->query("SET CHARSET 'utf8'");
$flarum_db->query("SET CHARSET 'utf8'");
$parent_tags = [];
$user_ips = [];
$extension_installed = false;
$result = $flarum_db->query('SELECT 1 FROM ' . Config::FLARUM_PREFIX . 'recipients LIMIT 1');
if ($result !== false) {
    $extension_installed = true;
}
if ($extension_installed) {
    $flarum_db->query('SET FOREIGN_KEY_CHECKS = 0');
    $flarum_db->query('TRUNCATE TABLE ' . Config::FLARUM_PREFIX . 'recipients');
}

echo '<p>Migrating users ...<br />';

$users = $discuz_db->query('SELECT u.uid, u.username, u.email, u.password, u.salt, c.posts, c.threads, FROM_UNIXTIME( u.regdate ) AS regdate, FROM_UNIXTIME( u.lastlogintime ) AS lastvisit, m.groupid, u.lastloginip FROM ' . Config::DISCUZ_PREFIX . 'ucenter_members u INNER JOIN ' . Config::DISCUZ_PREFIX . 'common_member m ON m.uid = u.uid LEFT JOIN ' . Config::DISCUZ_PREFIX . 'common_member_count c ON c.uid = u.uid ');
if ($users->num_rows > 0) {
    $flarum_db->query('TRUNCATE TABLE ' . Config::FLARUM_PREFIX . 'users');
    $flarum_db->query('TRUNCATE TABLE ' . Config::FLARUM_PREFIX . 'users_groups');
    $emails = [];
    $usernames = [];
    while ($row = $users->fetch_assoc()) {
        $password = $row['password'] . '$' . $row['salt'];
        $row['threads'] = $row['threads'] ?? 0;
        $row['posts'] = $row['posts'] ?? 0;
        $row['email'] = strtolower($row['email']);
        $row['username'] = make_semiangle($row['username']);
        while (in_array($row['email'], $emails)) {
            $row['email'] = $row['email'].'.dup';
        }
        while (in_array(strtolower($row['username']), $usernames)) {
            $row['username'] = $row['username'].'1';
        }
        $ap = NULL;
        if (Config::MIGRATE_AVATARS) {
            if (!empty(Config::AVATAR_PATH)) {
                $avatar_path = Config::AVATAR_PATH . '/' . get_avatar($row['uid']);
                $avatar = $row['uid'] . '_' . random(10) . '.jpg';
                if (file_exists($avatar_path)) {
                    if (copy($avatar_path, Config::FLARUM_AVATAR_PATH . $avatar)) {
                        $ap = $avatar;
                    }
                }
            }
        }
        $result = $flarum_db->query('INSERT INTO ' . Config::FLARUM_PREFIX . "users (id, username, email, is_activated, password, join_time, last_seen_time, discussions_count, comments_count, avatar_path) VALUES ({$row['uid']},'{$flarum_db->escape_string($row['username'])}', '{$row['email']}', 1, '$password', '{$row['regdate']}', '{$row['lastvisit']}', {$row['threads']}, {$row['posts']}, ".($ap ? "'".$ap."'" : 'NULL').")");
        $emails[] = $row['email'];
        $usernames[] = strtolower($row['username']);
        if ($result === false) {
            die("Error executing query (at uid {$row['uid']}, saving as flarum user): " . $flarum_db->error);
        }
        $groupid = (int)$row['groupid'];
        $user_ips[(int)$row['uid']] = $row['lastloginip'];

        if ($groupid == 1 || in_array($groupid, Config::DISCUZ_SPECIAL_GROUPIDS)) {
            $flarum_db->query('INSERT INTO ' . Config::FLARUM_PREFIX . "users_groups (user_id, group_id) VALUES ({$row['uid']}, {$groupid})");
        } elseif (in_array($groupid, [2, 3])) {
            $flarum_db->query('INSERT INTO ' . Config::FLARUM_PREFIX . "users_groups (user_id, group_id) VALUES ({$row['uid']}, 4)");
        }
    }
    unset($emails);
    unset($usernames);
}
echo 'Done: migrated ' . $users->num_rows . ' users.</p>';

echo '<p>Migrating categories to tags and forums to sub-tags ...<br />';
//categories
$categories = $discuz_db->query('SELECT f.fid, f.name, ff.description FROM ' . Config::DISCUZ_PREFIX . 'forum_forum f LEFT JOIN ' . Config::DISCUZ_PREFIX . "forum_forumfield ff ON ff.fid = f.fid WHERE f.type = 'group' AND f.status = '1'");
if ($categories->num_rows > 0) {
    $flarum_db->query('TRUNCATE TABLE ' . Config::FLARUM_PREFIX . 'tags');
    $c_pos = 0;
    $fids = [];
    while ($crow = $categories->fetch_assoc()) {
        $color = rand_color();
        $result = $flarum_db->query('INSERT INTO ' . Config::FLARUM_PREFIX . "tags (id, name, slug, description, color, position) VALUES ({$crow['fid']},'{$flarum_db->escape_string($crow['name'])}', '" . to_slug($crow['name']) . "', '{$flarum_db->escape_string($crow['description'])}',  '$color', $c_pos)");
        if ($result === false) {
            die("Error executing query (at fid {$crow['fid']}, saving category as tag): " . $flarum_db->error);
        }
        $fids[] = $crow['fid'];
        $parent_tags[$crow['fid']] = 0;
        //forums
        $forums = $discuz_db->query('SELECT f.fid, f.name, ff.description, ff.redirect FROM ' . Config::DISCUZ_PREFIX . 'forum_forum f LEFT JOIN ' . Config::DISCUZ_PREFIX . "forum_forumfield ff ON ff.fid = f.fid WHERE f.type = 'forum' AND f.fup = {$crow['fid']}");
        if ($forums->num_rows === 0) {
            continue;
        }
        $f_pos = 0;
        while ($srow = $forums->fetch_assoc()) {
            if (!empty($srow['redirect'])) {
                continue;
            }
            $fids[] = $srow['fid'];
            $result = $flarum_db->query('INSERT INTO ' . Config::FLARUM_PREFIX . "tags (id, name, slug, description, parent_id, color, position) VALUES ({$srow['fid']},'{$flarum_db->escape_string($srow['name'])}', '" . to_slug($srow['name'], true) . "', '{$flarum_db->escape_string($srow['description'])}', {$crow['fid']}, '$color', $f_pos)");
            if ($result === false) {
                die("Error executing query (at fid {$srow['fid']}, saving forum as tag): " . $flarum_db->error . '(' . $flarum_db->errno . ')');
            }
            $parent_tags[$srow['fid']] = $crow['fid'];
            $f_pos++;

            $subforums = $discuz_db->query('SELECT f.fid, f.name, ff.description, ff.redirect FROM ' . Config::DISCUZ_PREFIX . 'forum_forum f LEFT JOIN ' . Config::DISCUZ_PREFIX . "forum_forumfield ff ON ff.fid = f.fid WHERE f.type = 'sub' AND f.fup = {$srow['fid']}");
            if ($subforums->num_rows === 0) {
                continue;
            }
            while ($ssrow = $subforums->fetch_assoc()) {
                if (!empty($ssrow['redirect'])) {
                    continue;
                }
                $fids[] = $ssrow['fid'];
                $result = $flarum_db->query('INSERT INTO ' . Config::FLARUM_PREFIX . "tags (id, name, slug, description, parent_id, color, is_hidden) VALUES ({$ssrow['fid']}, '{$flarum_db->escape_string($ssrow['name'])}', '" . to_slug($ssrow['name'], true) . "', '{$flarum_db->escape_string($ssrow['description'])}', {$srow['fid']} ,'$color', 1)");
                if ($result === false) {
                    die("Error executing query (at fid {$ssrow['fid']}, saving subforum as tag): " . $flarum_db->error . '(' . $flarum_db->errno . ')');
                }
            }
        }
        $c_pos++;
    }
}


echo 'Done: migrated ' . $categories->num_rows . ' categories and their forums';

echo '<p>Migrating attachments...<br />';
$flarum_db->query('TRUNCATE TABLE ' . Config::FLARUM_PREFIX . 'flagrow_file_downloads');
$flarum_db->query('TRUNCATE TABLE ' . Config::FLARUM_PREFIX . 'flagrow_files');
$atts = [];
$aids = [];

$attach_collections = $discuz_db->query('SELECT aid, tableid FROM ' . Config::DISCUZ_PREFIX . 'forum_attachment');
if ($attach_collections->num_rows > 0) {
    while ($ac = $attach_collections->fetch_assoc()) {
        $att_query = $discuz_db->query('SELECT aid, tid, pid, uid, FROM_UNIXTIME(dateline) as dateline, filename, filesize, attachment FROM ' . Config::DISCUZ_PREFIX . 'forum_attachment_'.$ac['tableid'].' WHERE aid = '.$ac['aid']);
        $row = $att_query ? $att_query->fetch_array(MYSQLI_ASSOC) : NULL;
        if (!empty($row)) {
            if (in_array($row['aid'], $aids)) {
                continue;
            }
            $aids[] = $row['aid'];
            $tag = in_array(strtolower(substr($row['filename'], -3)), ['png', 'bmp', 'gif', 'peg', 'jpg']) ? 'image-preview' : 'file';
            $uuid = Uuid::uuid4()->toString();
            $result = $flarum_db->query('INSERT INTO ' . Config::FLARUM_PREFIX . "flagrow_files (id, actor_id, discussion_id, post_id, base_name, path, url, type, size, upload_method, created_at, uuid, tag) VALUES ({$row['aid']}, {$row['uid']}, {$row['tid']}, {$row['pid']}, '{$row['filename']}', '{$row['attachment']}', '/assets/files/{$row['attachment']}', '".get_mime_type($row['filename'])."', {$row['filesize']}, 'local', '{$row['dateline']}', '{$uuid}', '{$tag}')");
            if ($result === false) {
                die("Error executing query (at aid {$row['aid']}, saving attachment): " . $flarum_db->error);
            }
            if (empty($atts[$row['tid'].'_'.$row['pid']])) {
                $atts[$row['tid'].'_'.$row['pid']] = [];
            }
            $atts[$row['tid'].'_'.$row['pid']][$row['aid']] = [
                't' => $tag,
                'n' => $row['filename'],
                'p' => '/assets/files/'.$row['attachment']
            ];
        }
    }
}

unset($aids);

echo '<p>Migrating threads and thread posts...<br />';
$threads = $discuz_db->query('SELECT t.tid, t.fid, t.subject, FROM_UNIXTIME(t.dateline) as dateline, p.pid, t.authorid, FROM_UNIXTIME(t.lastpost) as lastpost, m.uid, t.closed, t.displayorder FROM ' . Config::DISCUZ_PREFIX . 'forum_thread t INNER JOIN ' . Config::DISCUZ_PREFIX . 'forum_post p ON t.tid = p.tid AND p.first = 1 LEFT JOIN ' . Config::DISCUZ_PREFIX . 'common_member m ON t.lastposter = m.username WHERE t.displayorder >= 0 ORDER BY t.tid ASC');
if ($threads->num_rows > 0) {
    $flarum_db->query('TRUNCATE TABLE ' . Config::FLARUM_PREFIX . 'discussions');
    $flarum_db->query('TRUNCATE TABLE ' . Config::FLARUM_PREFIX . 'discussions_tags');
    $flarum_db->query('TRUNCATE TABLE ' . Config::FLARUM_PREFIX . 'posts');
    while ($trow = $threads->fetch_assoc()) {
        if (!in_array($trow['fid'], $fids)) {
            continue;
        }
        if (intval($trow['uid']) == 0) {
            $trow['uid'] = NULL;
            $trow['lastpost'] = NULL;
        }
        $participants = [];
        $result = $flarum_db->query('INSERT INTO ' . Config::FLARUM_PREFIX . "discussions (id, title, start_time, start_user_id, start_post_id, last_time, last_user_id, slug, is_approved, is_locked, is_sticky)
        VALUES ({$trow['tid']}, '{$flarum_db->escape_string($trow['subject'])}', '{$trow['dateline']}', {$trow['authorid']}, {$trow['pid']}, ".($trow['lastpost'] ? "'".$trow['lastpost']."'" : 'NULL').", ".($trow['uid'] ? "'".$trow['uid']."'" : 'NULL').", '" . $trow['tid'] . "', 1, " . ($trow['closed'] == '1' ? '1' : '0') . ', 0)');

        if ($result === false) {
            die("Error executing query (at tid {$trow['tid']}, saving thread as discussion): " . $flarum_db->error);
        }
        $flarum_db->query('INSERT INTO ' . Config::FLARUM_PREFIX . "discussions_tags (discussion_id, tag_id) VALUES ({$trow['tid']}, {$trow['fid']})");
        if (array_key_exists($trow['fid'], $parent_tags)) {
            $flarum_db->query('INSERT INTO ' . Config::FLARUM_PREFIX . "discussions_tags (discussion_id, tag_id) VALUES ({$trow['tid']}, {$parent_tags[$trow['fid']]})");
        }
        $posts = $discuz_db->query('SELECT pid, tid, FROM_UNIXTIME(dateline) as dateline, authorid, message FROM ' . Config::DISCUZ_PREFIX . "forum_post WHERE tid = {$trow['tid']}");
        $lastpost = null;
        if ($posts->num_rows === 0) {
            continue;
        }
        $lastpostnumber = 0;
        while ($row = $posts->fetch_assoc()) {
            if (!in_array($row['authorid'], $participants)) {
                $participants[] = (int)$row['authorid'];
            }

            $content = preg_replace_callback('/\[url=(home|forum)\.php([^\]]+)\](.*)\[\/url\]/', function($matches) { 
                return $matches[3];
            }, $row['message']);

            $content = preg_replace_callback('/{:(\d+)_(\d+):}/', function($matches) { 
                return '';
            }, $content);

            $content = str_replace('[img]static/image/common/back.gif[/img]', '', $content);

            $content = $renderer->render($parser->parse($content));

            $content = preg_replace_callback(['/\[img\]([^\[]+)\[\/img\]/'], function($matches) {
                return '<img src="'.$matches[1].'">';
            }, $content);

            $content = preg_replace_callback(['/\[font=([^\]]+)\]/'], function($matches) {
                return '';
            }, $content);

            $content = preg_replace_callback(['/\[size=([^\]]+)\]/'], function($matches) {
                return '<font size="'.$matches[1].'">';
            }, $content);

            $content = preg_replace_callback(['/\[color=([^\]]+)\]/'], function($matches) {
                return '<font color="'.$matches[1].'">';
            }, $content);

            $content = preg_replace_callback(['/\[align=(left|center|right)\]/'], function($matches) {
                return '<div style="text-align:'.$matches[1].'">';
            }, $content);

            $content = preg_replace_callback(['/\[\/font\]/'], function($matches) {
                return '';
            }, $content);

            $content = preg_replace_callback(['/\[\/size\]/'], function($matches) {
                return '</font>';
            }, $content);

            $content = preg_replace_callback(['/\[\/color\]/'], function($matches) {
                return '</font>';
            }, $content);

            $content = preg_replace_callback(['/\[\/align\]/'], function($matches) {
                return '</div>';
            }, $content);

            $content = preg_replace_callback('/\[attach(img)?\](\d+)\[\/attach(img)?\]/', function($matches) use ($row) { 
                return replace_attach($matches[2], $row);
            }, $content);

            if (!empty($atts[$row['tid'].'_'.$row['pid']])) {
                foreach ($atts[$row['tid'].'_'.$row['pid']] as $att) {
                    if ($att['t'] == 'image-preview') {
                    $ret = '<img src="'.$att['p'].'">';
                    } else {
                        $ret = '<a href="'.$att['p'].'" download="'.$att['n'].'">'.$att['n'].'</a>';
                    }
                    $content .= '<p>'.$ret.'</p>';
                }
            }

            $content = '<t>'.$flarum_db->escape_string($content).'</t>';

            $lastpostnumber++;
            
            $result = $flarum_db->query('INSERT INTO ' . Config::FLARUM_PREFIX . "posts (id, discussion_id, time, user_id, type, content, is_approved, number, ip_address) VALUES ({$row['pid']}, {$trow['tid']}, '{$row['dateline']}', {$row['authorid']}, 'comment', '$content', 1, $lastpostnumber, '" . (isset($user_ips[$row['authorid']]) ? $user_ips[$row['authorid']] : '127.0.0.1') . "')");
            if ($result === false) {
                die("Error executing query (at pid {$row['pid']}, saving as post): " . $flarum_db->error);
            }
            $lastpost = (int)$row['pid'];
        }
        $flarum_db->query('UPDATE ' . Config::FLARUM_PREFIX . 'discussions SET participants_count = ' . count($participants) . ", comments_count =  $lastpostnumber, last_post_id = $lastpost, last_post_number = $lastpostnumber WHERE id = {$trow['tid']}");
    }
}

echo 'Done: migrated ' . $threads->num_rows . ' threads with their posts';
echo '<p>Migrating custom usergroups...<br />';
$groups = $discuz_db->query('SELECT * FROM ' . Config::DISCUZ_PREFIX . "common_usergroup WHERE type = 'special'");
if ($groups->num_rows > 0) {
    $flarum_db->query('DELETE FROM ' . Config::FLARUM_PREFIX . 'groups WHERE id > 4');
    while ($row = $groups->fetch_assoc()) {
        if (!in_array(intval($row['groupid']), Config::DISCUZ_SPECIAL_GROUPIDS)) {
            continue;
        }
        $result = $flarum_db->query('INSERT INTO ' . Config::FLARUM_PREFIX . "groups (id, name_singular, name_plural, color) VALUES ({$row['groupid']}, '{$row['grouptitle']}', '{$row['grouptitle']}', '" . rand_color() . "')");
        if ($result === false) {
            die("Error executing query (at groupid {$row['groupid']}, saving usergroup as group): " . $flarum_db->error);
        }
    }
}
echo 'Done: migrated ' . $groups->num_rows . ' custom groups.</p>';
if (!$extension_installed) {
    exit;
}
