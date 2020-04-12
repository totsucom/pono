<?php
require_once 'Env.php';
session_start();

// ログイン状態チェック
if (!isset($_SESSION["NAME"])) {

    if (isset($_GET['pid'])) {
        //ログイン後にここに戻れるようにbackパラメータにURLを渡す
        $path = urlencode($baseurl.basename(__FILE__).'?pid='.$_GET['pid']);
        header('Location: Login.php?back='.$path);
    } else {
        header('Location: Login.php');
    }
    exit;
}

//成功メッセージを表示できる
if (isset($_GET['msg'])) $message = $_GET['msg'];

if (isset($_GET['delete'])) {
    //課題の削除

    $pid = $_GET['delete']; //problem id
    $dsn = sprintf('mysql: host=%s; dbname=%s; charset=utf8', $db['host'], $db['dbname']);

    try {
        $pdo = new PDO($dsn, $db['user'], $db['pass'], array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));

        $stmt = $pdo->prepare('SELECT `id`,`userid` FROM `problem` WHERE `id` = ?');
        $stmt->execute(array($pid));

        //$row[]に投稿者を読み込む
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['userid'] == $_SESSION["ID"]) {
                
                //ユーザーと投稿者が一致するので削除を実行
                $stmt = $pdo->prepare('DELETE FROM `problem` WHERE `id` = ?');
                $stmt->execute(array($pid));

                $successMessage = '課題 No.'.$pid.' を削除しました';
            } else {
                $errorMessage = '指定された課題を削除する権限がありません';
            }
        } else {
            $errorMessage = '指定された課題が見つかりませんでした';
        }
    } catch (PDOException $e) {
        $errorMessage = 'データベースエラー';
        echo $e->getMessage();  //デバッグ
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="./css/bootstrap.min.css">
    <title>課題の表示</title>
</head>
<body>
    <nav class="navbar navbar-expand-sm navbar-light bg-light">
        <a href="./Main.php" class="navbar-brand">
            <img src="./img/real-estate.png" width="18" height="18" alt="">
            Home
        </a>
        <button class="navbar-toggler" type="button"
            data-toggle="collapse"
            data-target="#navmenu1"
            aria-controls="navmenu1"
            aria-expanded="false"
            aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navmenu1">
            <div class="navbar-nav">
                <a class="nav-item nav-link" href="./Account.php">アカウント設定</a>
                <a class="nav-item nav-link" href="./Logout.php">ログアウト</a>
            </div>
        </div>
    </nav>

    <div class = "container">
        <?php
            if (isset($message)) {
                //エラーのないときは使い方を表示
                echo <<<EOD
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <strong>成功！</strong> {$message}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>                

EOD;
            }
            if (isset($errorMessage)) {
                //エラー
                $msg = htmlspecialchars($errorMessage, ENT_QUOTES);
                echo <<<EOD
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>エラー！</strong> {$msg}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                </div>
            </body>
        </html>

EOD;
                exit; //エラーの場合はここで終わり
            }
        ?>
        
        <!-- 検索条件がある場合はアイテムを切り替えるボタンを表示 -->
        <div class="row" id="search-tool" style="display:none">
            <div class="col align-self-start pr-1">
                <a class="btn btn-secondary mt-2 mb-1" role="btn" href="./ProblemList.php">検索に戻る</a>
            </div>
            <!-- ページネーション -->
            <div class="col align-self-end pl-1">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-end pagination-lg my-2" id="pagination">
                        <li class="page-item disabled" id="item_prev">
                            <a class="page-link" href="#" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <li class="page-item disabled"><a class="page-link" id="item_pos" href="#">/</a></li>
                        <li class="page-item disabled" id="item_next">
                            <a class="page-link" href="#" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>

        <div class="wrapper mb-5">
            <div class="row mb-1">
                <h3 class="col" id="title_text"></h3>
                <a class="col-2 btn btn-light ml-auto mr-1" role="btn" href="#" id="fold-content">
                    <img src="./img/up-arrow.png" width="18" height="18" alt="" id="up-icon">
                    <img src="./img/down-arrow.png" width="18" height="18" alt="" id="down-icon" style="display:none">
                </a>
            </div>

            <div class="m-0 p-0" id="content">
                <div class="alert alert-danger alert-dismissible fade show" id="js_error_frame" role="alert" style="display:none">
                    <strong>エラー！</strong> <span id="js_error_msg"></span>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <!-- 無効チェック -->
                <div class="alert alert-warning fade show" role="alert" id="inactive_msg" style="display:none">
                    <strong>無効な課題</strong> この課題は無効になっています。壁が存在しないのかもしれません。
                </div>                

                <!-- 投稿日 -->
                <div class="row bg-light mb-2">
                    <div class="col-4 px-3 py-2">投稿日</div>
                    <div class="col-8 px-3 py-2 border-left" id="createdon"></div>
                </div>

                <!-- 投稿者 -->
                <div class="row bg-light mb-2">
                    <div class="col-4 px-3 py-2">投稿者</div>
                    <div class="col-8 px-3 py-2 border-left" id="username"></div>
                </div>

                <!-- タイトル -->
                <div class="row bg-light mb-2">
                    <div class="col-4 px-3 py-2">タイトル</div>
                    <div class="col-8 px-3 py-2 border-left" id="title"></div>
                </div>

                <!-- グレード -->
                <div class="row bg-light mb-2">
                    <div class="col-4 px-3 py-2">グレード</div>
                    <div class="col-8 px-3 py-2 border-left" id="grade"></div>
                </div>

                <!-- その他 -->
                <div class="row bg-light mb-2">
                    <div class="col-4 px-3 py-2">その他</div>
                    <div class="col-4 px-3 py-2 border-left" id="other"></div>
                </div>

                <!-- 場所 -->
                <div class="row bg-light mb-2">
                    <div class="col-4 px-3 py-2">場所</div>
                    <div class="col-8 px-3 py-2 border-left" id="location"></div>
                </div>

                <!-- コメント -->
                <div class="row bg-light mb-3" id="comment_frame">
                    <div class="col-4 px-3 py-2">コメント</div>
                    <div class="col-8 px-3 py-2 border-left" id="comment"></div>
                </div>

                <!-- あなたの記録 -->
                <div class="row border border-primary p-2 mt-3 mb-3" id="myresult">
                    <div class="col-4 px-3 py-2">あなたの記録</div>
                    <div class="col-8 px-3 py-2 border-left">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="radio_climb" id="radio1" value="1">
                            <label class="form-check-label text-success" for="radio1">完登</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="radio_climb" id="radio2" value="0">
                            <label class="form-check-label text-warning" for="radio2">失敗</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="radio_climb" id="radio3" value="-1">
                            <label class="form-check-label text-muted" for="radio3">未踏</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 課題画像 -->
<!--
            <div class="row p-2" id="loading_spin">
                <div class="spinner-border >spinner-border-sm align-middle" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
                <div class="d-inline align-middle ml-3">Loading...</div>
            </div>
            <div class="alert alert-danger alert-dismissible fade show" role="alert" id="loading_alert" style="display:none">
                <strong>エラー！</strong> 投稿画像を読み込めませんでした
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>                
-->
            <img id="preview" class="img-fluid">
        </div>
        
        <!-- 投稿者メニュー -->
        <div class="wrapper mb-5" id="usermenu" style="display:none">
            <div class="col-12">
                <select class="custom-select d-inline col-6" id="operation" name="operation" required>
                    <option value="" selected>投稿者メニュー</option>
                    <option value="edit">内容を編集する</option>
                    <option value="editwall">壁を編集する</option>
                    <option value="delete">削除する</option>
                </select>
                <button type="button" class="btn btn-secondary" id="exebtn" disabled>実行</button>
            </div>
            <div class="col-12 mt-2 mb-5" id="confdelete" style="display:none">
                <div class="text-danger d-inline ml-1">本当に削除しますか？</div>
                <button type="button" class="btn btn-danger d-inline ml-3" id="exec_delete">はい</button>
                <button type="button" class="btn btn-secondary ml-2" id="exec_cancel">いいえ</button>
            </div>
        </div>

        <!-- このページのショートカットを表示 -->
        <hr>
        <div class="clearfix mb-2">
            <div class="float-left">
                <img width="100%" id="qrcode" src="" alt="ショートカット">
            </div>
            <h5>このページへのショートカット</h5>
            <small id="qrcode_path"></small>
        </div>

        <?php
            if (isset($row['id'])) {
                $path = $baseurl.basename(__FILE__).'?pid='.$row['id'];
                $path1 = urlencode($path);
                $path2 = htmlspecialchars($path, ENT_QUOTES);
                echo <<<EOD
                    <hr>
                    <div class="clearfix mb-2">
                        <div class="float-left">
                            <img width="100%" src="qrgen.php?text={$path1}" alt="ショートカット">
                        </div>
                        <h5>このページへのショートカット</h5>
                        <small>{$path2}</small>
                    </div>

EOD;
            }
        ?>

        <!-- ProblemList.phpから飛んできた場合に裏検索するための隠しフォーム -->
        <div style="display:none">

            <!-- 検索条件 -->

                <!-- グレード -->
                <div id="grades">
                    <?php
                        foreach ($grades as $value => $name) {
                            echo <<<EOD
                                <input type="checkbox" name="problem_grade[]" value="{$value}">{$name}

EOD;
                        }
                    ?>
                </div>

                <!-- 場所 -->
                <div id="locations">
                    <?php
                        foreach ($walls as $value => $name) {
                            echo <<<EOD
                                <input type="checkbox" name="problem_location[]" value="{$value}">{$name}

EOD;
                        }
                    ?>
                </div>

                <!-- あなたの記録 -->
                <select id="my_result" name="my_result">
                    <option value="all" selected></option>
                    <option value="climbed">完登した課題</option>
                    <option value="notclimbed">失敗した課題</option>
                    <option value="nottried">未踏の課題</option>
                </select>

                <!-- その他 -->
                <input type="checkbox" name="my_problem" id="my_problem" >

            <!-- 絞り込み -->

                <!-- 投稿日 -->
                <select id="post_month" name="post_month">
                    <option value="all" selected></option>
                </select>

                <!-- 投稿者 -->
                <select id="post_user" name="post_user">
                    <option value="all" selected></option>
                </select>
        </div>
    </div>

    <script type="text/javascript" src="./js/jquery-3.4.1.min.js"></script>
    <script type="text/javascript" src="./js/jquery.cookie.js"></script>
    <script type="text/javascript" src="./js/bootstrap.bundle.min.js"></script>
    <script>$(function(){
        var retry = 0;
        var current_id = -1;    //読み込めたproblem.id
        var startIndex;         //検索条件を持ったとき、のレコードインデックス(最初)
        var maxItemCount;       //検索条件を持ったとき、
        var problemIdList = []; //検索条件を持ったときの、検索結果のproblem.idを持つ
        var current_index = -1; //検索条件を持ったときの、この条件が成立する problemIdList[current_index] = current_id
        var totalCount = 0;
        var cond_bak = {};


        //検索に戻る ボタンがクリックされた
        /*$('#back-button').on('click', function() {
            //console.log(JSON.stringify(cond_bak));
            location.href = 'ProblemList.php?cond=' + encodeURIComponent(window.btoa(JSON.stringify(cond_bak)));
        });*/
/*
        //投稿画像の読み込みを開始する
        function loadImage() {
            $('#preview').attr('src', "<?php /* echo $urlpaths['problem_image'], $row['imagefile']; */ ?>");
        }

        //投稿画像の読み込みに失敗した
        $('#preview').on('error', function(){
            $('#preview').removeAttr('src');
            if (++retry <= 3) {
                setTimeout(function() {
                    loadImage();                //リロードする
                }, (retry - 1) * 3000 + 2000);  //2,5,8秒
            } else {
                //あきらめた
                $('#loading_spin').hide();
                $('#loading_alert').show();
            }
        });

        //投稿画像の読み込みに成功した
        $('#preview').on('load', function(){
            $('#loading_spin').hide();
            $(this).show();
        });
*/
        //1回目の画像読み込みを開始する
        //loadImage();

        //[<<]ボタンがクリックされた
        $('#item_prev').on('click', function () {
            if (!$(this).hasClass('disabled')) {
                if (current_index == 0 && startIndex > 0) {
                    //端まで行ったのでproblem.id配列を読み込みなおす
                    startIndex -= maxItemCount;
                    if (startIndex < 0) startIndex = 0;
                    updateCondition(-1);  //読み込み後、最後のインデックスを表示
                } else if (current_index > 0) {
                    //１つ前の課題を読み込む
                    current_index--;
                    loadProblem(problemIdList[current_index]);
                    if (startIndex == 0 && current_index == 0) {
                        $(this).addClass('disabled');
                    }
                    $('#item_next').removeClass('disabled');
                    //位置を更新
                    $('#item_pos').html((current_index + startIndex + 1) + '/' + totalCount);
                }
            }
        });

        //[>>]ボタンがクリックされた
        $('#item_next').on('click', function () {
            if (!$(this).hasClass('disabled')) {
                if (current_index == (problemIdList.length - 1) && (startIndex + problemIdList.length) < totalCount) {
                    //端まで行ったのでproblem.id配列を読み込みなおす
                    startIndex += problemIdList.length;
                    updateCondition(1);  //読み込み後、最初のインデックスを表示
                } else if (current_index < (problemIdList.length - 1)) {
                    //１つ後ろの課題を読み込む
                    current_index++;
                    loadProblem(problemIdList[current_index]);
                    if (current_index == (problemIdList.length - 1) && (startIndex + problemIdList.length) == totalCount) {
                        $(this).addClass('disabled');
                    }
                    $('#item_prev').removeClass('disabled');
                    //位置を更新
                    $('#item_pos').html((current_index + startIndex + 1) + '/' + totalCount);
                }
            }
        });

        function foldContent(f) {
            if (f) {
                $('#content').hide();
                $('#up-icon').hide();
                $('#down-icon').show();
                $.cookie('fold_problem_content', 1);
            } else {
                $('#content').show();
                $('#up-icon').show();
                $('#down-icon').hide();
                $.removeCookie('fold_problem_content');
            }
        }

        //折り畳みボタンがクリックされた
        $('#fold-content').on('click', function () {
            foldContent($('#content').is(':visible'));
        });

        //あなたの記録 ラジオボタンが変更された
        $('input[name="radio_climb"]:radio').change(function() {
            var obj = {
                problemid: current_id,
                climbed: $(this).val()
            }
            //console.log(JSON.stringify(obj));

            //POSTでデータベース上のフラグを更新する
            $.post('./updateclimbresult_api.php',JSON.stringify(obj),null,'json')
            .done(function(data1,textStatus,jqXHR) {
                //console.log(jqXHR.status);
                //console.log(textStatus);
                //console.log(data1);

                if (data1.error == 0) {
                    //成功
                    if($('#myresult_alert').length != 0) {
                        //前回のアラートが存在するときは削除
                        $('#myresult_alert').remove();
                    }
                } else {
                    //エラー
                    if($('#myresult_alert').length == 0) {
                        //前回のアラートが存在しないときにアラートを追加
                        var alert = '<div class="alert alert-danger alert-dismissible fade show" role="alert" id="myresult_alert">';
                        alert +=    '  <strong>エラー！</strong>結果を更新できませんでした。' + data1.errorMessage;
                        alert +=    '  <button type="button" class="close" data-dismiss="alert" aria-label="Close">';
                        alert +=    '    <span aria-hidden="true">&times;</span>';
                        alert +=    '  </button>';
                        alert +=    '</div>';
                        $('#myresult').after(alert);
                    }
                }
            })
            .fail(function(jqXHR, textStatus, errorThrown ) {
                //console.log(jqXHR.status); //例：404
                //console.log(textStatus); //例：error
                //console.log(errorThrown); //例：NOT FOUND

                if($('#myresult_alert').length == 0) {
                    //前回のアラートが存在しないときにアラートを追加
                    var alert = '<div class="alert alert-danger alert-dismissible fade show" role="alert" id="myresult_alert">';
                    alert +=    '  <strong>エラー！</strong>結果を更新できませんでした';
                    alert +=    '  <button type="button" class="close" data-dismiss="alert" aria-label="Close">';
                    alert +=    '    <span aria-hidden="true">&times;</span>';
                    alert +=    '  </button>';
                    alert +=    '</div>';
                    $('#myresult').after(alert);
                }
            })
            .always(function() {
                //console.log('end');
            });
        });

        //投稿者メニューが変更された
        $('#operation').on('change', function () {
            if ($(this).val() != '') {
                $('#exebtn').removeAttr('disabled');
            } else {
                $('#exebtn').prop('disabled', true);
            }
        });

        //投稿者メニューの実行ボタンがクリックされた
        $('#exebtn').on('click', function() {
            var op = $('#operation').val();
            if (op == 'edit') {
                $('#operation').val('');                //セレクタ選択なし
                $('#exebtn').prop('disabled', true);    //実行ボタン無効
                location.href = "./UploadProblem.php?pid=" + current_id;
            } else if (op == 'editwall') {
                $('#operation').val('');                //セレクタ選択なし
                $('#exebtn').prop('disabled', true);    //実行ボタン無効
                location.href = "./EditHolds.php?pid=" + current_id;
            } else if (op == 'delete') {
                $('#operation').prop('disabled', true); //セレクタ無効
                $('#exebtn').prop('disabled', true);    //実行ボタン無効
                $('#confdelete').show();                //はい、いいえボタン表示
            }
        });
        $('#exec_delete').on('click', function() {
            //本当に削除するらしい
            $('#confdelete').hide();                    //はい、いいえボタン非表示
            $('#operation').removeAttr('disabled');     //セレクタ有効
            $('#operation').val('');                    //セレクタ選択なし
            $('#exebtn').prop('disabled', true);        //実行ボタン無効
            location.href = "./DisplayProblem.php?delete=" + current_id;
        });
        $('#exec_cancel').on('click', function() {
            //削除をキャンセルした
            $('#confdelete').hide();                    //はい、いいえボタン非表示
            $('#operation').removeAttr('disabled');     //セレクタ有効
            $('#operation').val('');                    //セレクタ選択なし
            $('#exebtn').prop('disabled', true);        //実行ボタン無効
        });

        //課題を読み込む
        function loadProblem(problemid) {
            var jsonData = JSON.stringify({
                id: problemid
            });
            //console.log(jsonData);

            $.post('./displayproblem_api.php', jsonData, null, 'json')
            .done(function(data1,textStatus,jqXHR) {
                //console.log(jqXHR.status);
                //console.log(textStatus);
                //console.log(data1);

                if (data1.error == 0) {
                    //成功

                    current_id = problemid; 

                    $('#title_text').html('課題 No.' + data1.problemid + ((data1.publish == 0) ? '（非公開）' : ''));
                    $('#js_error_frame').hide();
                    if (data1.active == 1) {
                        $('#inactive_msg').hide();
                    } else {
                        $('#inactive_msg').show();
                    }
                    $('#createdon').html(data1.createdon);
                    $('#username').html(data1.name);
                    if (data1.title.length > 0) {
                        $('#title').html(data1.title).removeClass('text-muted');
                    } else {
                        $('#title').html('NO TITLE').addClass('text-muted');
                    }
                    $('#grade').html(data1.grade);
                    $('#other').html((data1.footfree == 1) ? '足自由' : '');
                    $('#location').html(data1.locations);
                    $('#comment').html(data1.comment);
                    if (data1.comment.length > 0) {
                        $('#comment_frame').show();
                    } else {
                        $('#comment_frame').hide();
                    }
console.log("climbed=" + data1.climbed);
                    $('input[name="radio_climb"]').val([data1.climbed]).removeAttr('disabled');
                    $('#preview').attr('src', data1.imagefile + '?' + Math.random());
                    if (data1.userid == <?php echo $_SESSION["ID"]; ?>) {
                        $('#usermenu').show();
                    } else {
                        $('#usermenu').hide();
                    }
console.log(cond_bak);
                    //var path = '<?php echo $baseurl.basename(__FILE__), '?pid='; ?>' + current_id + "&cond=" + encodeURIComponent(window.btoa(JSON.stringify(cond_bak)));
                    //$('#qrcode').attr('src', 'qrgen.php?text=' + encodeURI(path));
                    //$('#qrcode_path').html(htmlEscape(path));

                    var path = '<?php echo $baseurl.basename(__FILE__).'?pid='; ?>' + data1.problemid;
                    $('#qrcode').attr('src', 'qrgen.php?text=' + encodeURI(path));
                    $('#qrcode_path').html(htmlEscape(path));

                } else {
                    //PHP側でエラー

                    current_id = -1;

                    $('#title_text').html('課題 No.' + problemid);
                    $('#js_error_frame').show();
                    $('#js_error_msg').html(data1.errorMessage);
                    $('#inactive_msg').hide();
                    $('#createdon').html('');
                    $('#username').html('');
                    $('#title').html('');
                    $('#grade').html('');
                    $('#other').html('');
                    $('#location').html('');
                    $('#comment').html('');
                    $('input[name="radio_climb"]').val(-1).attr('disabled', 'disabled');
                    $('#preview').attr('src', '');
                    $('#usermenu').hide();
                    var path = '<?php echo $baseurl.basename(__FILE__); ?>';
                    $('#qrcode').attr('src', 'qrgen.php?text=' + encodeURI(path));
                    $('#qrcode_path').html(htmlEscape(path));
                }
            })
            .fail(function(jqXHR, textStatus, errorThrown ) {
                //console.log(jqXHR.status); //例：404
                //console.log(textStatus); //例：error
                //console.log(errorThrown); //例：NOT FOUND

                //サーバーまたは通信エラー

                current_id = -1;

                $('#title_text').html('課題 No.' + problemid);
                $('#js_error_frame').show();
                $('#js_error_msg').html(htmlEscape(errorThrown));
                $('#inactive_msg').hide();
                $('#createdon').html('');
                $('#username').html('');
                $('#title').html('');
                $('#grade').html('');
                $('#other').html('');
                $('#location').html('');
                $('#comment').html('');
                $('input[name="radio_climb"]').val(-1).attr('disabled', 'disabled');
                $('#preview').attr('src', '');
                $('#usermenu').hide();
                var path = '<?php echo $baseurl.basename(__FILE__); ?>';
                $('#qrcode').attr('src', 'qrgen.php?text=' + encodeURI(path));
                //$('#qrcode_path').html(htmlEscape(path));
                $('#qrcode_path').html('qrgen.php?text=' + encodeURI(path)); //デバッグ用。フルパス
            })
            .always(function() {
                //ページネーションを更新
                //setupPagenation();
            });
        }

        function htmlEscape(string) {
            if(typeof string !== 'string') {
                return string;
            }
            return string.replace(/[&'`"<>]/g, function(match) {
                return {
                '&': '&amp;',
                "'": '&#x27;',
                '`': '&#x60;',
                '"': '&quot;',
                '<': '&lt;',
                '>': '&gt;',
                }[match]
            });
        }

        //検索する
        // op = 0:読み込み
        // op =-1:読み込み後、最後のインデックスの課題を表示
        // op = 1:読み込み後、最初のインデックスの課題を表示
        function updateCondition(op) {
            var jsonData = JSON.stringify({
                index: startIndex,
                count: maxItemCount,
                grades: $('input[name="problem_grade[]"]:checked').map(function(){ return $(this).val(); }).get(),
                locations: $('input[name="problem_location[]"]:checked').map(function(){ return $(this).val(); }).get(),
                title: '', //$('#problem_title').val(),
                myresult: $('#my_result').val(),
                myproblem: $('#my_problem').prop('checked') ? 1 : 0,
                pubtype: 'active', //$('#publish_type').val(),
                setter: ($('#post_user').val() == null) ? 'all' : $('#post_user').val(),
                postmonth: ($('#post_month').val() == null) ? 'all' : $('#post_month').val(),
                disp: 1
            });
            console.log("post data = " + jsonData);
            console.log("maxItemCount = " + maxItemCount);

            if (op != 0) current_id = -1;
            problemIdList = [];
            totalCount = 0;
            cond_bak = {};

            $.post('./problemlist_api.php', jsonData, null, 'json')
            .done(function(data1,textStatus,jqXHR) {
                //console.log(jqXHR.status);
                //console.log(textStatus);
                console.log(data1);

                if (data1.error == 0) {
                    //成功

                    data1.dataRows.forEach(row => {
                        problemIdList.push(row.id)
                    });
                    totalCount = data1.rowCount;

                    if (problemIdList.length > 0) {
                        if (op == 1) {
                            current_index = 0;
                            current_id = problemIdList[current_index];
                            loadProblem(current_id);
                        } else if (op == -1) {
                            current_index = problemIdList.length - 1;
                            current_id = problemIdList[current_index];
                            loadProblem(current_id);
                        }
                    }

                    //成功した条件を記憶
                    cond_bak['grade'] = $('input[name="problem_grade[]"]').map(function(){ return ($(this).prop('checked')) ? 1 : 0; }).get();
                    cond_bak['location'] = $('input[name="problem_location[]"]').map(function(){ return ($(this).prop('checked')) ? 1 : 0; }).get();
                    cond_bak['myresult'] = $('#my_result').val();
                    cond_bak['myproblem'] = ($('#my_problem').prop('checked')) ? 1 : 0;
                    cond_bak['postmonth'] = ($('#post_month').val() == null) ? 'all' : $('#post_month').val();
                    cond_bak['postuser'] = ($('#post_user').val() == null) ? 'all' : $('#post_user').val();
                    cond_bak['index'] = startIndex;     //追加の情報
                    //cond_bak['count'] = maxItemCount;

                    //クッキーに保存
                    $.cookie('cond_bak', JSON.stringify(cond_bak));

                    //console.log(problemIdList);

                } else {
                    //PHP側でエラー

                    if ($("#js_error_frame").is(":hidden")) {
                        //これまでエラーが表示されてない場合にエラーを表示
                        $('#js_error_frame').show();
                        $('#js_error_msg').html(data1.errorMessage);
                    }
                }
            })
            .fail(function(jqXHR, textStatus, errorThrown ) {
                //console.log(jqXHR.status); //例：404
                //console.log(textStatus); //例：error
                console.log(errorThrown); //例：NOT FOUND

                //サーバーまたは通信エラー

                if ($("#js_error_frame").is(":hidden")) {
                    //これまでエラーが表示されてない場合にエラーを表示
                    $('#js_error_frame').show();
                    $('#js_error_msg').html(errorThrown);
                }
            })
            .always(function() {
                //ページネーションを更新
                setupPagenation();
            });
        }

        //ページネーションを設定する
        function setupPagenation() {

            current_index = -1;
            if (startIndex >= 0 && current_id >= 0 && problemIdList.length > 0 && totalCount > 0) {
                current_index = problemIdList.indexOf(''+current_id);
            }
            if (current_index < 0) {
                //無効化
                $('#item_prev').attr('disabled', 'disabled');
                $('#item_pos').html('/');
                $('#item_next').attr('disabled', 'disabled');
            } else {
                //[<<]ボタンの有効化
                if (current_index > 0 || startIndex > 0) {
                    $('#item_prev').removeClass('disabled');
                } else {
                    $('#item_prev').addClass('disabled');
                }
                //位置を表示
                $('#item_pos').html((current_index + startIndex + 1) + '/' + totalCount);
                //[>>]ボタンの有効化
                if (current_index < problemIdList.length - 1 || startIndex + problemIdList.length < totalCount) {
                    $('#item_next').removeClass('disabled');
                } else {
                    $('#item_next').addClass('disabled');
                }
            }
        }









        //GETのcondパラメータで検索条件が渡された場合、条件に応じて「隠しUI」を変更する
        function setupCond() {
            //var s = $.cookie('cond_bak');

            var s;
            <?php
                if (!isset($_GET['cond'])) {
                    echo "s = $.cookie('cond_bak');";
                } else {
                    echo "s = '" . base64_decode($_GET['cond']) . "'";
                }
            ?>
            if (s) {
                var j = JSON.parse(s);
                //console.log(j);

                startIndex = j.index;

                $('input[name="problem_grade[]"]').each(function (index, element) {
                    $(element).prop('checked', (j.grade[index] == 1));
                });
                $('input[name="problem_location[]"]').each(function (index, element) {
                    $(element).prop('checked', (j.location[index] == 1));
                });
                $('#my_result').val(j.myresult);
                $('#my_problem').prop('checked', (j.myproblem == 1));
                $('#post_month').val(j.postmonth);
                $('#post_user').val(j.postuser);
            }
        }
        setupCond();

        <?php
            if (isset($_GET['pid']) && is_numeric($_GET['pid'])) {
                echo 'loadProblem(', $_GET['pid'], ');';
            }

            //if (isset($_GET['cond'])) {
            //    echo 'updateCondition(0)';
            //}
        ?>

        if ($.cookie('cond_bak')) {
            maxItemCount = $.cookie("problemlist_num_disply");
            if (!maxItemCount) {
                maxItemCount = 8;
            }

            updateCondition(0);
            $('#search-tool').show();
        }

        foldContent($.cookie('fold_problem_content'));

    });</script>
</body>
</html>
