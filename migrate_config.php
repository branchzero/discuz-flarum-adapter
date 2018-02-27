<?php

use s9e\TextFormatter\Configurator;
use JellyBool\Translug\Translug;

class Config
{
    const FLARUM_SERVER = '127.0.0.1';
    const FLARUM_USER = '';
    const FLARUM_PASSWORD = '';
    const FLARUM_DB = '';
    const FLARUM_PREFIX = '';
    const FLARUM_AVATAR_PATH = 'assets/avatars/';   //relative path from the script, normally not needed to edit this. (Only used if $MIGRATE_AVATARS = true)
    const DISCUZ_SERVER = '127.0.0.1';
    const DISCUZ_USER = '';
    const DISCUZ_PASSWORD = '';
    const DISCUZ_DB = '';
    const DISCUZ_PREFIX = 'pre_';
    const AVATAR_PATH = '';        //absolute path of discuz installation
    const DISCUZ_SPECIAL_GROUPIDS = [];
    const YOUDAO_APP_KEY = '';
    const YOUDAO_APP_SECRET = '';
    const MIGRATE_AVATARS = true;
}

function rand_color()
{
    return '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
}
function to_slug($text, $check_exist = false)
{
    global $flarum_db, $translug;
    $text = $translug->translug($text);
    if ($check_exist) {
        $result = $flarum_db->query('SELECT slug FROM ' . Config::FLARUM_PREFIX . "tags WHERE slug = '$text'");
        if ($result->num_rows > 0) {
            $result = $flarum_db->query('SELECT slug FROM ' . Config::FLARUM_PREFIX . "tags WHERE slug LIKE '$text%'");
            $text .= $result->num_rows;
        }
    }
    return $text;
}

function get_avatar($uid, $size = 'middle', $type = '')
{
    $size = in_array($size, ['big', 'middle', 'small']) ? $size : 'middle';
    $uid = abs(intval($uid));
    $uid = sprintf('%09d', $uid);
    $dir1 = substr($uid, 0, 3);
    $dir2 = substr($uid, 3, 2);
    $dir3 = substr($uid, 5, 2);
    $typeadd = $type == 'real' ? '_real' : '';
    return $dir1 . '/' . $dir2 . '/' . $dir3 . '/' . substr($uid, -2) . $typeadd . "_avatar_$size.jpg";
}

function random($length, $numeric = 0)
{
    PHP_VERSION < '4.2.0' && mt_srand((double)microtime() * 1000000);
    if ($numeric) {
        $hash = sprintf('%0' . $length . 'd', mt_rand(0, pow(10, $length) - 1));
    } else {
        $hash = '';
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $hash .= $chars[mt_rand(0, $max)];
        }
    }
    return $hash;
}

function make_semiangle($str)
{
    $arr = array('０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4',
                 '５' => '5', '６' => '6', '７' => '7', '８' => '8', '９' => '9',
                 'Ａ' => 'A', 'Ｂ' => 'B', 'Ｃ' => 'C', 'Ｄ' => 'D', 'Ｅ' => 'E',
                 'Ｆ' => 'F', 'Ｇ' => 'G', 'Ｈ' => 'H', 'Ｉ' => 'I', 'Ｊ' => 'J',
                 'Ｋ' => 'K', 'Ｌ' => 'L', 'Ｍ' => 'M', 'Ｎ' => 'N', 'Ｏ' => 'O',
                 'Ｐ' => 'P', 'Ｑ' => 'Q', 'Ｒ' => 'R', 'Ｓ' => 'S', 'Ｔ' => 'T',
                 'Ｕ' => 'U', 'Ｖ' => 'V', 'Ｗ' => 'W', 'Ｘ' => 'X', 'Ｙ' => 'Y',
                 'Ｚ' => 'Z', 'ａ' => 'a', 'ｂ' => 'b', 'ｃ' => 'c', 'ｄ' => 'd',
                 'ｅ' => 'e', 'ｆ' => 'f', 'ｇ' => 'g', 'ｈ' => 'h', 'ｉ' => 'i',
                 'ｊ' => 'j', 'ｋ' => 'k', 'ｌ' => 'l', 'ｍ' => 'm', 'ｎ' => 'n',
                 'ｏ' => 'o', 'ｐ' => 'p', 'ｑ' => 'q', 'ｒ' => 'r', 'ｓ' => 's',
                 'ｔ' => 't', 'ｕ' => 'u', 'ｖ' => 'v', 'ｗ' => 'w', 'ｘ' => 'x',
                 'ｙ' => 'y', 'ｚ' => 'z',
                 '（' => '(', '）' => ')', '〔' => '[', '〕' => ']', '【' => '[',
                 '】' => ']', '〖' => '[', '〗' => ']', '“' => '[', '”' => ']',
                 '‘' => '[', '’' => ']', '｛' => '{', '｝' => '}', '《' => '<',
                 '》' => '>',
                 '％' => '%', '＋' => '+', '—' => '-', '－' => '-', '～' => '-',
                 '：' => ':', '。' => '.', '、' => ',', '，' => '.', '、' => '.',
                 '；' => ',', '？' => '?', '！' => '!', '…' => '-', '‖' => '|',
                 '”' => '"', '’' => '`', '‘' => '`', '｜' => '|', '〃' => '"',
                 '　' => ' ');
                 
    return strtr($str, $arr);
}

function human_filesize($bytes, $decimals = 0) {
    $size = array('B','kB','MB','GB','TB','PB','EB','ZB','YB');
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
}

function replace_attach($aid, $row) {
    global $atts;
    $aid = intval($aid);
    if (!empty($atts[$row['tid'].'_'.$row['pid']][$aid])) {
        $att = $atts[$row['tid'].'_'.$row['pid']][$aid];
        if ($att['t'] == 'image-preview') {
            $ret = '<img src="'.$att['p'].'">';
        } else {
            $ret = '<a href="'.$att['p'].'" download="'.$att['n'].'">'.$att['n'].'</a>';
        }
        unset($atts[$row['tid'].'_'.$row['pid']][$aid]);
        return '<p>'.$ret.'</p>';
    }
    return '';
}

function get_mime_type($filename) {
    $idx = explode( '.', $filename );
    $count_explode = count($idx);
    $idx = strtolower($idx[$count_explode-1]);

    $mimet = array( 
        'txt' => 'text/plain',
        'htm' => 'text/html',
        'html' => 'text/html',
        'php' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'swf' => 'application/x-shockwave-flash',
        'flv' => 'video/x-flv',

        // images
        'png' => 'image/png',
        'jpe' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'ico' => 'image/vnd.microsoft.icon',
        'tiff' => 'image/tiff',
        'tif' => 'image/tiff',
        'svg' => 'image/svg+xml',
        'svgz' => 'image/svg+xml',

        // archives
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        'exe' => 'application/x-msdownload',
        'msi' => 'application/x-msdownload',
        'cab' => 'application/vnd.ms-cab-compressed',

        // audio/video
        'mp3' => 'audio/mpeg',
        'qt' => 'video/quicktime',
        'mov' => 'video/quicktime',

        // adobe
        'pdf' => 'application/pdf',
        'psd' => 'image/vnd.adobe.photoshop',
        'ai' => 'application/postscript',
        'eps' => 'application/postscript',
        'ps' => 'application/postscript',

        // ms office
        'doc' => 'application/msword',
        'rtf' => 'application/rtf',
        'xls' => 'application/vnd.ms-excel',
        'ppt' => 'application/vnd.ms-powerpoint',
        'docx' => 'application/msword',
        'xlsx' => 'application/vnd.ms-excel',
        'pptx' => 'application/vnd.ms-powerpoint',


        // open office
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
    );

    if (isset( $mimet[$idx] )) {
     return $mimet[$idx];
    } else {
     return 'application/octet-stream';
    }
 }

$translug = new Translug(['appKey' => Config::YOUDAO_APP_KEY, 'appSecret' => Config::YOUDAO_APP_SECRET]);

$configurator = new Configurator;
$configurator->rootRules->createParagraphs(true);
$configurator->BBCodes->addCustom('[table={NUMBER1}]{TEXT}[/table]', '<table width="{NUMBER1}"><tbody>{TEXT}</tbody></table>');
$configurator->BBCodes->addCustom('[tr]{TEXT}[/tr]', '<tr>{TEXT}</tr>');
$configurator->BBCodes->addCustom('[td]{TEXT}[/td]', '<td>{TEXT}</td>');
$configurator->BBCodes->addCustom('[free]{TEXT}[/free]', '<p>{TEXT}</p>');
$configurator->BBCodes->addCustom('[hide]{TEXT}[/hide]', '<p>{TEXT}</p>');
$configurator->BBCodes->addCustom('[font={TEXT1}]{TEXT2}[/font]', '{TEXT2}');
$configurator->BBCodes->addCustom('[hide={TEXT2}]{TEXT1}[/hide]', '<p>{TEXT1}</p>');
$configurator->BBCodes->addCustom('[hide={TEXT2},{TEXT3}]{TEXT1}[/hide]', '<p>{TEXT1}</p>');
$configurator->BBCodes->addCustom('[password]{TEXT}[/password]', '');
$configurator->BBCodes->addCustom('[qq]{TEXT}[/qq]', '');
$configurator->BBCodes->addCustom('[audio]{URL}[/audio]', '<audio src="{URL}"></audio>');
$configurator->BBCodes->addCustom('[img={NUMBER1},{NUMBER2}]{URL}[/img]', '<img src="{URL}">');
$configurator->BBCodes->addCustom('[media=x,{NUMBER1},{NUMBER2}]{URL}[/media]', '');
$configurator->BBCodes->addCustom('[austglcmp=1,{NUMBER1},{NUMBER2}]{URL}[/austglcmp]', '<video src="{URL}" controls="controls" style="width:{NUMBER1}px;height:{NUMBER2}px">您的浏览器不支持video标签</video>');
$configurator->BBCodes->addFromRepository('B');
$configurator->BBCodes->addFromRepository('I');
$configurator->BBCodes->addFromRepository('U');
$configurator->BBCodes->addFromRepository('S');
$configurator->BBCodes->addFromRepository('URL');
$configurator->BBCodes->addFromRepository('EMAIL');
$configurator->BBCodes->addFromRepository('CODE');
$configurator->BBCodes->addFromRepository('QUOTE');
$configurator->BBCodes->addFromRepository('LIST');
$configurator->BBCodes->addFromRepository('DEL');
$configurator->BBCodes->addFromRepository('*');
$configurator->BBCodes->addFromRepository('ALIGN');
$configurator->BBCodes->addFromRepository('FLOAT');
$configurator->BBCodes->addFromRepository('HR');
$configurator->BBCodes->addCustom('[page]', '');

extract($configurator->finalize());
