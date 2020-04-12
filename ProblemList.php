<?php
require_once 'Env.php';
session_start();

// ログイン状態チェック
if (!isset($_SESSION["NAME"])) {

    if (isset($_GET['cond'])) {
        //ログイン後にここに戻れるようにbackパラメータにURLを渡す
        $path = urlencode($baseurl.basename(__FILE__).'?cond='.$_GET['cond']);
        header('Location: Login.php?back='.$path);
    } else {
        header('Location: Login.php');
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="./css/bootstrap.min.css">
    <title>課題の投稿</title>
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

        <div class="col-12">
            <button class="btn mt-2 mb-1" id="cond_button" data-toggle="collapse" data-target="#collapse_cond" aria-expand="false" aria-controls="collapse_cond">検索条件</button>
            <button class="btn mt-2 mb-1" id="filter_button" data-toggle="collapse" data-target="#collapse_filter" aria-expand="false" aria-controls="collapse_filter">絞り込み</button>
            <button class="btn btn-secondary mt-2 mb-1" id="other_button" data-toggle="collapse" data-target="#collapse_other" aria-expand="false" aria-controls="collapse_other">その他</button>
        </div>
        <div class="collapse" id="collapse_cond">
            <div class="card card-body">
                <h5>検索条件</h5>

                <!-- グレード -->
                <div class="row bg-light mb-2">
                    <div class="col-4  px-3 py-2">グレード</div>
                    <div class="col-8 px-3 py-2 border-left">
                        <div class="form-group mb-0">
                            <div class="form-inline" id="grades">
                                <?php
                                    foreach ($grades as $value => $name) {
                                        echo <<<EOD
                                            <label class="checkbox-inline pl-2 py-1">
                                                <input type="checkbox" name="problem_grade[]" value="{$value}">{$name}
                                            </label>

EOD;
                                    }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 場所 -->
                <div class="row bg-light mb-2">
                    <div class="col-4  px-3 py-2">場所</div>
                    <div class="col-8 pl-1 pr-3 py-1 border-left">
                        <div class="form-group mb-0">
                            <div class="form-inline" id="locations">
                                <?php
                                    foreach ($walls as $value => $name) {
                                        echo <<<EOD
                                            <label class="checkbox-inline pl-2 py-1">
                                                <input type="checkbox" name="problem_location[]" value="{$value}">{$name}
                                            </label>

EOD;
                                    }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- あなたの記録 -->
                <div class="row bg-light mb-2">
                    <div class="col-4 px-3 py-2">あなたの記録</div>
                    <div class="col-8 px-3 py-2 border-left">
                        <select class="custom-select" id="my_result" name="my_result">
                            <option value="all" selected></option>
                            <option value="climbed">完登した課題</option>
                            <option value="notclimbed">失敗した課題</option>
                            <option value="nottried">未踏の課題</option>
                        </select>
                    </div>
                </div>

                <!-- その他 -->
                <div class="row bg-light mb-2">
                    <div class="col-4  px-3 py-2">その他</div>
                    <div class="col-8 px-3 py-2 border-left">
                        <div class="form-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="my_problem" id="my_problem" >
                                <label class="form-check-label" for="my_problem">自作の課題</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 適用ボタン -->
                <div class="col-12 clearfix pr-0">
                    <div class="float-right">
                        <button class="btn btn-primary mr-2" id="apply_cond">適用する</button>
                        <button class="btn btn-secondary" id="cancel_cond">キャンセル</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="collapse" id="collapse_filter">
            <div class="card card-body">
                <h5>絞り込み</h5>

                <!-- 投稿日 -->
                <div class="row bg-light mb-2">
                    <div class="col-4 px-3 py-2">投稿日</div>
                    <div class="col-8 px-3 py-2 border-left">
                        <select class="custom-select" id="post_month" name="post_month">
                            <option value="all" selected></option>
                        </select>
                    </div>
                </div>

                <!-- 投稿者 -->
                <div class="row bg-light mb-2">
                    <div class="col-4 px-3 py-2">投稿者</div>
                    <div class="col-8 px-3 py-2 border-left">
                        <select class="custom-select" id="post_user" name="post_user">
                            <option value="all" selected></option>
                        </select>
                    </div>
                </div>

                <!-- 適用ボタン -->
                <div class="col-12 clearfix pr-0">
                    <div class="float-right">
                        <button class="btn btn-primary mr-2" id="apply_filter">適用する</button>
                        <button class="btn btn-secondary" id="cancel_filter">キャンセル</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="collapse" id="collapse_other">
            <div class="card card-body">
                <h5>その他の設定</h5>

                <!-- 表示数 -->
                <div class="row bg-light mb-2">
                    <div class="col-4 px-3 py-2">表示数</div>
                    <div class="col-8 px-3 py-2 border-left">
                        <select class="custom-select" id="num_disply" name="num_disply">
                            <option value="4">4</option>
                            <option value="8" selected>8</option>
                            <option value="16">16</option>
                            <option value="32">32</option>
                        </select>
                    </div>
                </div>

                <!-- サイズ -->
                <div class="row bg-light mb-2">
                    <div class="col-4 px-3 py-2">表示サイズ</div>
                    <div class="col-8 px-3 py-2 border-left">
                        <select class="custom-select" id="problem_size" name="problem_size">
                            <option value="7">XS</option>
                            <option value="10" selected>S</option>
                            <option value="15">M</option>
                            <option value="20">L</option>
                            <option value="25">LL</option>
                        </select>
                    </div>
                </div>

                <!-- 閉じるボタン -->
                <div class="col-12 clearfix pr-0">
                    <div class="float-right">
                        <button class="btn btn-primary mr-2" id="close_other">閉じる</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ページネーション -->
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-end pagination-lg my-2" id="pagination">
                <li class="page-item disabled" id="page_prev">
                    <a class="page-link" href="#" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                <!-- <li class="page-item"><a class="page-link" href="#">1</a></li> -->
                <li class="page-item disabled" id="page_next">
                    <a class="page-link" href="#" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- ここに検索結果の課題一覧またはエラーを表示 -->
        <div class="row justify-content-center mb-5" id="problem_body">
<!--
            <div class="card" style="width:10rem;">
                <img class="card-img-top" src="./uploaded_problems/16_t.jpg" alt="課題画像">
                <div class="card-body">
                    <h5 class="card-title">Card title</h5>
                    <h6 class="card-subtitle text-muted">Subtitle</h6>
                    <p class="card-text">A card is a flexible and extensible content container. It includes ...</p>
                    <a href="#" class="btn btn-primary">Go somewhere</a>
                </div>
            </div>
-->
        </div>

        <!-- このページのショートカットを表示 
        <hr>
        <div class="clearfix mb-2">
            <div class="float-left">
                <?php
                    $path = $baseurl.basename(__FILE__);
                ?>
                <img width="100%" id="qrcode" src="qrgen.php?text=<?php echo urlencode($path); ?>" alt="ショートカット">
            </div>
            <h5>このページへのショートカット</h5>
            <small><?php echo htmlspecialchars($path, ENT_QUOTES); ?></small>
        </div>
        -->
    </div>

    <script type="text/javascript" src="./js/jquery-3.4.1.min.js"></script>
    <script type="text/javascript" src="./js/jquery.cookie.js"></script>
    <script type="text/javascript" src="./js/bootstrap.bundle.min.js"></script>
    <script>$(function(){
        var startIndex = 0;     //表示中のレコードインデックス(最初)
        var lastIndex = 0;      //表示中のレコードインデックス(最後)
        //var maxItemCount;       //表示可能な最大数(後で設定)
        var totalCount = 0;     //現在の条件で検索されたレコード数
        var cond_bak = {};      //検索条件を記憶（条件キャンセル時の設定値復帰やGETパラメータによる多ページ移動に使用する）
        var cardStyle;          //課題の表示スタイル(後で設定)

        //検索条件コラプスが表示された
        $('#collapse_cond').on('show.bs.collapse', function () {
            $('#collapse_filter').collapse('hide');   //コラプスを閉じる
            $('#collapse_other').collapse('hide');   //コラプスを閉じる
        });

        //絞り込み条件コラプスが表示された
        $('#collapse_filter').on('show.bs.collapse', function () {
            $('#collapse_cond').collapse('hide');   //コラプスを閉じる
            $('#collapse_other').collapse('hide');   //コラプスを閉じる
        });

        //その他の設定コラプスが表示された
        $('#collapse_filter').on('show.bs.collapse', function () {
            $('#collapse_filter').collapse('hide');   //コラプスを閉じる
            $('#collapse_cond').collapse('hide');   //コラプスを閉じる
        });

        //検索条件の適用ボタンがクリックされた
        $('#apply_cond').on('click', function () {
            $('#collapse_cond').collapse('hide');   //コラプスを閉じる

            //絞り込み条件をクリア
            $('#post_month').val('')
            $('#post_user').val('')

            //検索開始
            startIndex = 0;
            updateCondition();
        });

        //検索条件のキャンセルボタンがクリックされた
        $('#cancel_cond').on('click', function () {
            $('#collapse_cond').collapse('hide');   //コラプスを閉じる

            //記憶していた条件を元に戻す
            $('input[name="problem_grade[]"]').each(function (index, element) {
                $(element).prop('checked', (cond_bak['grade'][index] == 1));
            });
            $('input[name="problem_location[]"]').each(function (index, element) {
                $(element).prop('checked', (cond_bak['location'][index] == 1));
            });
            $('#my_result').val(cond_bak['myresult']);
            $('#my_problem').prop('checked', (cond_bak['myproblem'] == 1));
        });

        //絞り込み条件の適用ボタンがクリックされた
        $('#apply_filter').on('click', function () {
            $('#collapse_filter').collapse('hide');   //コラプスを閉じる

            //検索開始
            startIndex = 0;
            updateCondition();
        });

        //絞り込み条件のキャンセルボタンがクリックされた
        $('#cancel_filter').on('click', function () {
            $('#collapse_filter').collapse('hide');   //コラプスを閉じる

            //記憶していた条件を元に戻す
            $('#post_month').val(cond_bak['postmonth']);
            $('#post_user').val(cond_bak['postuser']);
        });

        //その他の閉じるボタンがクリックされた
        $('#close_other').on('click', function () {
            $('#collapse_other').collapse('hide');   //コラプスを閉じる
        });

        //その他の表示数が変更された
        $('#num_disply').on('change', function () {
            //maxItemCount = $('#num_disply').val();
            $.cookie("problemlist_num_disply", $('#num_disply').val(), { expires: 30 });//日
            updateCondition();
        });

        //その他のサイズが変更された
        $('#problem_size').on('change', function () {
            var sz = $('#problem_size').val();
            cardStyle = 'class="card mb-2 mr-2" style="width:' + sz + 'rem;"'; //styleで課題の大きさを変えられる
            $.cookie("problemlist_problem_size", sz, { expires: 30 });//日
            updateCondition();
        });

        //ページネーションの[<<]がクリックされた
        $('#page_prev').on('click', function() {
            if ($(this).hasClass('disabled') == false) {
                startIndex -= $('#num_disply').val();//maxItemCount;
                if (startIndex < 0) startIndex = 0;
                updateCondition();
            }
        });

        //ページネーションの[>>]がクリックされた
        $('#page_next').on('click', function() {
            if ($(this).hasClass('disabled') == false) {
                var maxCount = $('#num_disply').val();
                startIndex += maxCount;//maxItemCount;
                if (startIndex >= totalCount) startIndex = totalCount - maxCount;//maxItemCount;
                if (startIndex < 0) startIndex = 0;
                updateCondition();
            }
        });

        //ページネーションの番号がクリックされた
        $('#pagination').on('click', 'a.page-link', function () {
            var page = $(this).data('page');
            if (typeof page != 'undefined') {
                startIndex = page * $('#num_disply').val();//maxItemCount;
                updateCondition();
            }
        });

        //表示課題の画像がクリックされた
        $('#problem_body').on('click', 'img.card-img-top', function () {
            //検索条件はcookieにある
            location.href = $(this).data('url');// + "&cond=" + encodeURIComponent(window.btoa(JSON.stringify(cond_bak)));
        });
        
        //検索する
        function updateCondition() {
            var jsonData = JSON.stringify({
                //myid: <?php //echo $_SESSION["ID"]; ?>,
                index: startIndex,
                count: $('#num_disply').val(),//maxItemCount,
                grades: $('input[name="problem_grade[]"]:checked').map(function(){ return $(this).val(); }).get(),
                locations: $('input[name="problem_location[]"]:checked').map(function(){ return $(this).val(); }).get(),
                title: '', //$('#problem_title').val(),
                myresult: $('#my_result').val(),
                myproblem: $('#my_problem').prop('checked') ? 1 : 0,
                pubtype: 'active', //$('#publish_type').val(),
                setter: ($('#post_user').val() == null) ? 'all' : $('#post_user').val(),
                postmonth: ($('#post_month').val() == null) ? 'all' : $('#post_month').val()
            });
            console.log("post data = " + jsonData);

            $.post('./problemlist_api.php', jsonData, null, 'json')
            .done(function(data1,textStatus,jqXHR) {
                //console.log(jqXHR.status);
                //console.log(textStatus);
                //console.log(data1);

                if (data1.error == 0) {
                    //成功

                    //成功した条件を記憶(上記のjsonDataとは内容が少し違う)
                    cond_bak['grade'] = $('input[name="problem_grade[]"]').map(function(){ return ($(this).prop('checked')) ? 1 : 0; }).get();
                    cond_bak['location'] = $('input[name="problem_location[]"]').map(function(){ return ($(this).prop('checked')) ? 1 : 0; }).get();
                    cond_bak['myresult'] = $('#my_result').val();
                    cond_bak['myproblem'] = ($('#my_problem').prop('checked')) ? 1 : 0;
                    cond_bak['postmonth'] = ($('#post_month').val() == null) ? 'all' : $('#post_month').val();
                    cond_bak['postuser'] = ($('#post_user').val() == null) ? 'all' : $('#post_user').val();

                    //条件に応じて検索条件ボタンの色を変える
                    var hasCond = false, i;
                    for(i = 0; i < cond_bak['grade'].length; i++) {
                        hasCond |= (cond_bak['grade'][i] == 1);
                    }
                    for(i = 0; i < cond_bak['location'].length; i++) {
                        hasCond |= (cond_bak['location'][i] == 1);
                    }
                    hasCond |= (cond_bak['myresult'] != 'all');
                    hasCond |= (cond_bak['myproblem'] == 1);
                    if (hasCond > 0) {
                        //何か条件が設定されているとき
                        $('#cond_button').removeClass('btn-secondary').addClass('btn-primary');
                    } else {
                        //何も条件が設定されていないとき
                        $('#cond_button').removeClass('btn-primary').addClass('btn-secondary');
                    }

                    //条件に応じて絞り込み条件ボタンの色を変える
                    if (cond_bak['postmonth'] != 'all' || cond_bak['postuser'] != 'all') {
                        //何か条件が設定されているとき
                        $('#filter_button').removeClass('btn-secondary').addClass('btn-primary');
                    } else {
                        //何も条件が設定されていないとき
                        $('#filter_button').removeClass('btn-primary').addClass('btn-secondary');
                    }


                    totalCount = data1.rowCount;
                    lastIndex = startIndex + data1.dataRows.length - 1;

                    //絞り込み条件の投稿日を更新
                    $('#post_month > option').remove();
                    $('#post_month').append('<option value="all" selected></option>');
                    data1.postMonths.forEach(ym => $('#post_month').append($('<option>').html(ym.slice(0, 4) + '/' + ym.slice(4)).val(ym)));

                    //絞り込み条件の投稿者を更新
                    $('#post_user > option').remove();
                    $('#post_user').append('<option value="all" selected></option>');
                    data1.setters.forEach(setter => $('#post_user').append($('<option>').html(setter.name).val(setter.userid)));

                    //記憶していた絞り込み条件を復帰
                    $('#post_month').val(cond_bak['postmonth']);
                    $('#post_user').val(cond_bak['postuser']);

                    //課題一覧を表示
                    $('#problem_body > div').remove();
                    data1.dataRows.forEach(function(row) {
                        var climbresult;
                        switch (row.climbed) {
                        case 0: climbresult = ' 完登'; break;
                        case 1: climbresult = ' 失敗'; break;
                        default: climbresult = '';
                        }
                        var title;
                        if (row.title.length > 0) {
                            title = row.id + '. ' + row.title;
                        } else {
                            title = row.id + '. ' + '<span class="text-muted">NO TITLE</span>';
                        }
                        var html = '<div ' + cardStyle + '>'
                                + '  <img class="card-img-top" src="' + row.thumb + '?' + Math.random() + '" alt="課題 No.' + row.id + '" data-url="' + row.url + '">'
                                + '  <div class="card-body">'
                                + '    <h5 class="card-title">' + title + '</h5>'
                                + '    <h6 class="card-subtitle text-secondary">' + row.createdon + '</h6>'
                                + '    <p class="card-text text-secondary">' + row.grade + ' ' + row.location.join(' ') + climbresult + '</p>'
                                + '  </div>'
                                + '</div>';
                        $('#problem_body').append(html);
                    });

                    //ショートカットＱＲコードを更新、検索条件を含める
                    //encodeURIComponentだけではQRコードをスキャンしたときにパラメータが丸見えになってダサいので
                    //btoaでJSONデータをBASE64エンコードしている。
                    //※btoaはユニコードを含むとうまく変換できなくなるようなので注意する
                    cond_bak['index'] = startIndex; //追加の情報
                    //var s = encodeURIComponent(window.btoa(JSON.stringify(cond_bak)));
                    //$('#qrcode').attr('src', "qrgen.php?text=<?php echo urlencode($path); ?>?cond=" + s);

console.log("cond_bak="+JSON.stringify(cond_bak));
                    //クッキーに保存
                    $.cookie('cond_bak', JSON.stringify(cond_bak));

                    //console.log("?cond=" + window.btoa(JSON.stringify(cond_bak)));
                } else {
                    //PHP側でエラー

                    totalCount = 0;
                    lastIndex = -1;

                    $('#problem_body > div').remove();
                    var html = '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
                             + '  <strong>エラー！</strong> ' + data1.errorMessage
                             + '  <button type="button" class="close" data-dismiss="alert" aria-label="Close">'
                             + '    <span aria-hidden="true">&times;</span>'
                             + '  </button>'
                             + '</div>';
                    $('#problem_body').append(html);

                    //ショートカットＱＲコードをデフォルトに戻す
                    $('#qrcode').attr('src', "qrgen.php?text=<?php echo urlencode($path); ?>");
                }
            })
            .fail(function(jqXHR, textStatus, errorThrown ) {
                //console.log(jqXHR.status); //例：404
                //console.log(textStatus); //例：error
                //console.log(errorThrown); //例：NOT FOUND

                //サーバーまたは通信エラー

                $('#problem_body > div').remove();
                var html = '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
                            + '  <strong>エラー！</strong> ' + jqXHR.status + ' ' + errorThrown
                            + '  <button type="button" class="close" data-dismiss="alert" aria-label="Close">'
                            + '    <span aria-hidden="true">&times;</span>'
                            + '  </button>'
                            + '</div>';
                $('#problem_body').append(html);

                //ショートカットＱＲコードをデフォルトに戻す
                $('#qrcode').attr('src', "qrgen.php?text=<?php echo urlencode($path); ?>");
            })
            .always(function() {
                //ページネーションを更新
                setupPagenation();
            });
        }

        //ページネーションを設定する
        function setupPagenation() {
            //ボタンに表示するページ番号を決める
            var maxCount = $('#num_disply').val();
            var totalPage = Math.ceil(totalCount / maxCount);//maxItemCount);
            var currentPage = Math.floor(startIndex / maxCount);//maxItemCount);
            var pageNumbers = [];

            if (currentPage > 1) pageNumbers.push(0);

            if (currentPage > 0) {
                if (pageNumbers.length > 0 && ((currentPage - 1) - pageNumbers[pageNumbers.length - 1]) > 1)
                    pageNumbers.push(-1); //...
                pageNumbers.push(currentPage - 1);
            }

            var currentPageIndex = pageNumbers.length;
            if (pageNumbers.length > 0 && ((currentPage) - pageNumbers[pageNumbers.length - 1]) > 1)
                pageNumbers.push(-1); //...
            pageNumbers.push(currentPage);

            if (currentPage + 1 < totalPage) {
                if (pageNumbers.length > 0 && ((currentPage + 1) - pageNumbers[pageNumbers.length - 1]) > 1)
                    pageNumbers.push(-1); //...
                pageNumbers.push(currentPage + 1);
            }

            if (currentPage + 2 < totalPage) {
                if (pageNumbers.length > 0 && ((totalPage - 1) - pageNumbers[pageNumbers.length - 1]) > 1)
                    pageNumbers.push(-1); //...
                pageNumbers.push(totalPage - 1);
            }

            //[<<][>>]以外の数字部分を削除
            $('#pagination li').each(function(index, element) {
                if (typeof $(element).attr('id') == 'undefined') {
                    $(element).remove();
                }
            });

            //ページ番号を更新
            pageNumbers.forEach(function (value, index) {
                var html;
                if (value >= 0) {
                    html = '<li class="page-item ' + ((index == currentPageIndex) ? 'active' : '') + '"><a class="page-link" href="#" data-page="' + value + '">' + (value + 1) + '</a></li>';
                } else {
                    //html = '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                    html = '<li class="page-item disabled">&nbsp;</li>'; //幅狭いバージョン
                }
                $('#page_next').before(html);
            });

            //[<<][>>]の有効化
            if (currentPage > 0) {
                $('#page_prev').removeClass('disabled')
            } else {
                $('#page_prev').addClass('disabled')
            }
            if (currentPage < totalPage - 1) {
                $('#page_next').removeClass('disabled')
            } else {
                $('#page_next').addClass('disabled')
            }
        }

        //cookieのcond_bakを復元し条件に応じてUIを変更する
        function setupCond() {
//$.removeCookie('cond_bak');
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
console.log("cond loaded " + s);
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

        //クッキーの設定値をUIに反映させる
        //UIから設定値を取得する
        function setupCookies() {
            var value = $.cookie("problemlist_num_disply");
            if (value) {
                $('#num_disply').val(value);
            }
            var value = $.cookie("problemlist_problem_size");
            if (value) {
                $('#problem_size').val(value);
            }

            //maxItemCount = $('#num_disply').val();
            cardStyle = 'class="card mb-2 mr-2" style="width:' + $('#problem_size').val() + 'rem;"'; //styleで課題の大きさを変えられる
        }

        //初回の検索
        setupCond();
        setupCookies();
        updateCondition();

    });</script>
</body>
</html>
