<?php //ProblemList.phpから検索毎に呼ばれるAPI
require_once 'Env.php';
session_start();

//日付を書式化
function date2str($dt) {
    if (date('Ymd') == date('Ymd', $dt)) {
       return '今日 '.date('H時i分', $dt);
    } else if (date('Ymd', strtotime('-1 day')) == date('Ymd', $dt)) {
        return '昨日 '.date('H時i分', $dt);
    } else if (date('Ym') == date('Ym', $dt)) {
        return '今月 '.date('d日', $dt);
    } else if (date('Y') == date('Y', $dt)) {
        return date('m月d日', $dt);
    } else {
        return date('Y年m月d日', $dt);
    }
}

//JSON形式のPOSTデータを受け取る
$json = file_get_contents("php://input");
$param = json_decode($json, true);
if ($param === NULL) exit;
/*
$param['index'] 0.. 表示に含めるインデックス
$param['count'] 1..
$param['grades'][] グレード配列
$param['locations'][] ロケーション配列
$param['title'] 文字列
$param['myresult'] all/climbed/notclimbed/nottried
$param['myproblem'] 0/1
$param['pubtype'] active/deactive/all
$param['setter'] all/(userid)
$param['postmonth'] all/(yyyymm)

$param['disp'] ※表示モード 存在する場合は DisplayProblemからの呼び出し
*/

$join = "";
$sqlconds = [];
$sqlparams = [];

//基本条件
$sqlconds[] = '(`problem`.`publish` = 1 OR `problem`.`userid` = ?)';
$sqlparams[] = $_SESSION["ID"];

//グレード
if (count($param['grades']) != 0) {
    if (count($param['grades']) == 1) {
        $sqlconds[] = '`problem`.`grade` = ?';
    } else {
        $sqlconds[] = '`problem`.`grade` IN (?'.str_repeat(',?', count($param['grades'])-1).') ';
    }
    $sqlparams = array_merge($sqlparams, $param['grades']);
}

//ロケーション(壁)
if (count($param['locations']) != 0) {
    $ar = [];
    //foreach (explode(',', $param['locations']) as $value) {
    foreach ($param['locations'] as $value) {
        if (strlen($value) > 0) {
            $ar[] = "`problem`.`location` LIKE ?";
            $sqlparams[] = '%,'.$value.',%';
        }
    }
    $sqlconds[] = '('.implode(' OR ', $ar).')';
}

//タイトル
if (strlen($param['title']) > 0) {
    $sqlconds[] = "`problem`.`title` LIKE ?";
    $sqlparams[] = '%'.$param['title'].'%';
}

//登った記録
if ($param['myresult'] == 'climbed') {
    $join = 'INNER JOIN `climb` ON `problem`.`id` = `climb`.`problemid` AND `climb`.`climbed` = 1 ';
} else if ($param['myresult'] == 'notclimbed') {
    $join = 'INNER JOIN `climb` ON `problem`.`id` = `climb`.`problemid` AND `climb`.`climbed` = 0 ';
} else if ($param['myresult'] == 'nottried') {
    $join = "LEFT JOIN `climb` ON `problem`.`id` = `climb`.`problemid` AND `climb`.`userid` != ? ";
    $sqlconds[] = '`climb`.`userid` IS NULL';
    $sqlparams[] = $_SESSION["ID"];
}

//自分の課題
if (intval($param['myproblem']) == 1) {
    $sqlconds[] = "`problem`.`userid` = ?";
    $sqlparams[] = $_SESSION["ID"];
}

//公開タイプ
if ($param['pubtype'] == 'active') {
    $sqlconds[] = "`problem`.`active` = 1";
} else if ($param['pubtype'] == 'deactive') {
    $sqlconds[] = "`problem`.`active` = 0";
}

//作成者
if (is_numeric($param['setter'])) {
    $sqlconds[] = "`problem`.`userid` = ?";
    $sqlparams[] = $param['setter'];
}

//投稿日
if (is_numeric($param['postmonth'])) {
    $sqlconds[] = "EXTRACT(YEAR_MONTH FROM `problem`.`createdon`) = ?";
    $sqlparams[] = $param['postmonth'];
}

//課題データ取得SQLを作成
$require = (isset($param['disp'])) ? "`problem`.`id`" : "`problem`.*, `userdata`.`name`, `userdata`.`dispname`, `climb`.`climbed`"; //表示モードではproblem.idのみを取得
$sql1 =<<<EOD
    SELECT {$require} FROM `problem`
    {$join}
    INNER JOIN `userdata` ON `problem`.`userid` = `userdata`.`id`
    LEFT JOIN `climb` ON `problem`.`id` = `climb`.`problemid` AND `climb`.`userid` = ? 
EOD;
$sql1params = $sqlparams;
array_unshift($sql1params, $_SESSION["ID"]); //パラメータの先頭に追加
if (count($sqlconds) > 0) {
    $sql1 .= 'WHERE '.implode(' AND ', $sqlconds).' ';
}

$startIndex = intval($param['index'] / $param['count']) * $param['count'];
$sql1 .= "ORDER BY `problem`.`id` DESC LIMIT ${startIndex}, ${param['count']}"; //ORDER はユニークなカラムでソートすべき。 LIMITパラメータはsql1paramsに入れられないみたい

//投稿日一覧取得SQLを作成
$sql2 = 'SELECT DISTINCT EXTRACT(YEAR_MONTH FROM `problem`.`createdon`) AS yearmonth FROM `problem` '.$join;
if (count($sqlconds) > 0) {
    $sql2 .= 'WHERE '.implode(' AND ', $sqlconds);
}

//投稿者一覧取得SQLを作成
$sql3 = 'SELECT DISTINCT `problem`.`userid`, `userdata`.`name`, `userdata`.`dispname` FROM `problem` '.$join.' INNER JOIN `userdata` ON `problem`.`userid` = `userdata`.`id`';
if (count($sqlconds) > 0) {
    $sql3 .= 'WHERE '.implode(' AND ', $sqlconds);
}

//総レコード数取得SQLを作成
$sql4 = 'SELECT COUNT(`problem`.`id`) AS count FROM `problem` '.$join;
if (count($sqlconds) > 0) {
    $sql4 .= 'WHERE '.implode(' AND ', $sqlconds);
}

//結果配列を初期化
$result = [];
$result['dataRows'] = [];
$result['rowCount'] = 0;
$result['postMonths'] = [];
$result['setters'] = [];
$result['error'] = 0;
$result['errorMessage'] = '';

$result['json']['sql'] = $json;
//$result['sql1']['sql'] = $sql1;
//$result['sql1']['param'] = $sql1params;
//$result['param'] = $param;

$dsn = sprintf('mysql: host=%s; dbname=%s; charset=utf8', $db['host'], $db['dbname']);
try {
    $pdo = new PDO($dsn, $db['user'], $db['pass'], array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));

    $stmt = $pdo->prepare($sql1);
    $stmt->execute($sql1params);

    //課題を読み込む
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $ar = [];
        $ar['id'] = $row['id'];

        if (!isset($param['disp'])) {
            $ar['active'] = $row['active'];
            $ar['userid'] = $row['userid'];
            $ar['title'] = htmlspecialchars($row['title'], ENT_QUOTES);
            $ar['grade'] = isset($grades[$row['grade']]) ? $grades[$row['grade']] : '';
            $ar['footfree'] = $row['footfree'];
            $ar['location'] = [];
            foreach (explode(',', $row['location']) as $value) {
                if (strlen($value) > 0 && isset($walls[$value])) {
                    $ar['location'][] = $walls[$value];
                }
            }
            $ar['publish'] = $row['publish'];
            $ar['comment'] = htmlspecialchars($row['comment'], ENT_QUOTES);
            $ar['createdon'] = date2str(strtotime($row['createdon']));
            $ar['name'] = htmlspecialchars(is_null($row['dispname']) ? $row['name'] : $row['dispname'], ENT_QUOTES);
            $ar['climbed'] = is_null($row['climbed']) ? -1 : $row['climbed'];
            $ar['url'] = './DisplayProblem.php?pid='.$row['id'];
            $ar['thumb'] = $urlpaths['problem_image'].$row['imagefile_t'];
        }
        $result['dataRows'][] = $ar;
    }

    if (!isset($param['disp'])) {
        //表示モードではsql2,sql3は実行しない

        $stmt = $pdo->prepare($sql2);
        $stmt->execute($sqlparams);

        //投稿日を読み込む
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result['postMonths'][] = $row['yearmonth'];
        }

        $stmt = $pdo->prepare($sql3);
        $stmt->execute($sqlparams);

        //投稿者を読み込む
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result['setters'][] = $row;
        }
    }

    $stmt = $pdo->prepare($sql4);
    $stmt->execute($sqlparams);

    //総レコード数を読み込む
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $result['rowCount'] = $row['count'];
    }

} catch (PDOException $e) {
    $result['error'] = 1;
    $result['errorMessage'] = htmlspecialchars($e->getMessage(), ENT_QUOTES);
}

header("Access-Control-Allow-Origin: *");
echo json_encode($result);
?>
