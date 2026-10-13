<?php
/**
 * Copyright (C) 2007,2008  Arie Nugraha (dicarve@yahoo.com), Hendro Wicaksono (hendrowicaksono@yahoo.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

// be sure that this file not accessed directly
if (!defined('INDEX_AUTH')) {
    die("can not access this file directly");
} elseif (INDEX_AUTH != 1) {
    die("can not access this file directly");
}

/* Showing record detail in HTML and XML */

if (isset($_GET['inXML']) AND !empty($_GET['inXML'])) {
    if (!$sysconf['enable_xml_detail']) {
        die('XML Detail is disabled');
    }
    // filter the ID
    $detail_id = intval($_GET['id']);
    // include detail library and template
    include LIB.'detail.inc.php';
    // create detail object
    $detail = new detail($dbs, $detail_id, 'mods');
    $output = $detail->showDetail();
    // send http header
    header('Content-Type: text/xml');
    echo '<?xml version="1.0" encoding="UTF-8" ?>'."\n";
    echo $detail->getPrefix();
    echo $output;
    echo $detail->getSuffix();
    exit();
} else if (isset($_GET['JSONLD']) AND !empty($_GET['JSONLD'])) {
    if (!$sysconf['jsonld_detail']) {
      die('JSON-LD detail is disabled');
    }
    // filter the ID
    $detail_id = intval($_GET['id']);
    // include detail library and template
    include LIB.'detail.inc.php';
    // create detail object
    $detail = new detail($dbs, $detail_id, 'json-ld');
    $output = $detail->showDetail();
    // send http header
    header('Content-Type: application/ld+json');
    echo $output;
    exit();
} else if (isset($_GET['MARC']) AND !empty($_GET['MARC'])) {
    // filter the ID
    $detail_id = intval($_GET['id']);
    include MDLBS . 'bibliography/File/MARC.php';
    $biblio = new Biblio($dbs, null);
    header('Content-type: application/marc');
    header('Content-disposition: attachment; filename=biblio-detail-' . $detail_id . '.mrc');
    echo $biblio->marc_export($detail_id);
    exit;
} else {
    // filter the ID
    $detail_id = intval($_GET['id']);

    // === RECOMMENDATION: catat log view buku ===
    if (isset($_SESSION['mid']) && !empty($_SESSION['mid']) && $detail_id > 0) {
        $rec_member_id = $_SESSION['mid'];
        $rec_stmt = $dbs->prepare(
            "INSERT INTO rec_view_log (member_id, biblio_id, viewed_at) VALUES (?, ?, NOW())"
        );
        $rec_stmt->bind_param('si', $rec_member_id, $detail_id);
        $rec_stmt->execute();
        $rec_stmt->close();
    }
    // === END RECOMMENDATION ===

    // include detail library and template
    include LIB.'detail.inc.php';
    if ($sysconf['comment']['enable']) {
		  include LIB.'comment.inc.php';
    }

    if (isset($_POST['comment']) && $_POST['comment']<>"" && ISSET($_SESSION['mid']) && $sysconf['comment']['enable']) {
      if (! \Volnix\CSRF\CSRF::validate($_POST) ) {
        echo '<script type="text/javascript">';
        echo 'alert("Invalid comment form!");';
        echo 'location.href = \'index.php?p=show_detail&id='.$detail_id.'\';';
        echo '</script>';
        exit();
      }
      require SIMBIO.'simbio_DB/simbio_dbop.inc.php';
      $data['comment'] = $dbs->escape_string(trim(strip_tags($_POST['comment'], ['p','em','ol','ul','li','strong'])));
      $data['biblio_id'] = $detail_id;
      $data['member_id'] = $_SESSION['mid'];

      $data['input_date'] = date('Y-m-d H:i:s');
      $data['last_update'] = date('Y-m-d H:i:s');

      /* INSERT RECORD MODE */
      // insert the data
      $sql_op = new simbio_dbop($dbs);
      $insert = $sql_op->insert('comment', $data);
      if ($insert) {
        utility::jsToastr(__('Success'), __('Thank you for your comment.'), 'success');
      } else { 
        utility::jsToastr(__('Error'), __('FAILED to store you comment. Please Contact System Administrator') . (ENVIRONMENT === 'development' ? $sql_op->error : ''), 'error'); 
      }
    }

    if (isset($_GET['keywords'])) {
      $keywords = trim($_GET['keywords']);
      $keywords_array = explode(' ', $keywords);
      $searched_words_js_array = '[';
      foreach($keywords_array as $word) {
        $word = str_replace(array('"', ',', "'", '-'), '', $word);
        $searched_words_js_array .= "'$word',";
      }
      $searched_words_js_array = substr_replace($searched_words_js_array, '', -1);
      $searched_words_js_array .= ']';
    }

    // create detail object
    $detail = new detail($dbs, $detail_id);
    $detail->setTemplate($sysconf['template']['dir'].'/'.$sysconf['template']['theme'].'/detail_template.php');

    // set the content for info box
    $info = '<strong>'.strtoupper(__('Record Detail')).'</strong><hr />';
    if (!defined('LIGHTWEIGHT_MODE')) {
        $info .= '<a href="javascript: history.back();" class="btn btn-default" title="Back to previous page">'.__('Back To Previous').'</a>';
    }
    
    if (isset($sysconf['enable_xml_detail']) && $sysconf['enable_xml_detail'] && !defined('LIGHTWEIGHT_MODE')) {
      $info .= '<a href="index.php?p=show_detail&inXML=true&id='.$detail_id.'" class="xmlDetailLink s-xml-detail" title="'.__('Show detail in XML format').'" target="_blank">'.__('XML Detail').'</a>';
      $info .= '<a href="index.php?p=cite&id='.$detail_id.'" class="openPopUp citationLink" title="'.str_replace('{title}', substr($detail?->record_title??'', 0, 50) , __('Citation for: {title}')).'" target="_blank">'.__('Cite this').'</a>';
    }

    // output the record detail
    echo $detail->showDetail();
    $opac->page_title = ($detail->record_title??'Not found').' | '.$sysconf['library_name'];
    $opac->metadata   = $detail->metadata;
    $opac->image_src  = $detail->image_src;
    $opac->notes      = $detail->notes;

    // get keywords
    $opac->subject    = '';
    if(isset($detail->subjects[0]) && count($detail->subjects[0]) > 0) {
      foreach($detail->subjects as $_subject) {
        $opac->subject .= strtolower($_subject['topic']).',';
      }
      $opac->subject = substr($opac->subject,0,-1);
    }
    echo '<br />'."\n";

    // === WIDGET REKOMENDASI ===
    ?>
    <style>
    /* Widget "Buku dengan Konten Serupa" — selaras dgn tampilan OPAC SLiMS (template default / Bootstrap 4) */
    #rec-widget {
        margin-top: 1.5rem;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        color: #212529;
    }
    #rec-widget .rec-head { margin: 0 0 1rem; }
    #rec-widget .rec-head h3 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 500;
        line-height: 1.2;
        color: #212529;
    }
    #rec-widget .rec-head .rec-sub {
        display: block;
        margin-top: .25rem;
        font-size: .875rem;
        color: #6c757d;
    }
    #rec-list {
        display: grid;
        grid-template-columns: repeat(5, 1fr);   /* 5 kolom -> 10 buku = 5 atas + 5 bawah, simetris */
        gap: 1rem;
        align-items: stretch;
    }
    @media (max-width: 991.98px) { #rec-list { grid-template-columns: repeat(4, 1fr); } }
    @media (max-width: 767.98px) { #rec-list { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 479.98px) { #rec-list { grid-template-columns: repeat(2, 1fr); } }
    #rec-list .rec-loading {
        grid-column: 1 / -1;
        margin: 0;
        font-size: .875rem;
        color: #6c757d;
    }
    a.rec-card {
        display: flex;
        flex-direction: column;
        text-decoration: none;
        color: #212529;
        background: #fff;
        border: 1px solid rgba(0,0,0,.125);
        border-radius: .25rem;
        overflow: hidden;
        box-shadow: 0 .125rem .25rem rgba(0,0,0,.075);
        transition: box-shadow .15s ease-in-out, transform .15s ease-in-out;
    }
    a.rec-card:hover {
        text-decoration: none;
        color: #212529;
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.15);
        transform: translateY(-3px);
    }
    .rec-card-cover {
        width: 100%;
        aspect-ratio: 12 / 17;          /* sama dgn rasio cover.php (120x170) -> sampul buatan tampil penuh */
        background: #f1f3f5;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    .rec-card-cover img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .rec-card-cover img.is-fallback {   /* dipakai hanya jika cover.php pun gagal dimuat */
        width: 46px;
        height: auto;
        object-fit: contain;
        opacity: .6;
    }
    .rec-card-body {
        padding: .5rem .625rem .625rem;
        display: flex;
        flex-direction: column;
        gap: .4rem;
        flex: 1;
    }
    .rec-card-title {
        font-size: .75rem;
        line-height: 1.4;
        color: rgb(33, 33, 33);
        margin: 0;
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
    }
    .rec-card-pct {
        align-self: flex-start;
        margin: 0;
        font-size: .6875rem;
        font-weight: 600;
        color: #007bff;
        background: #e7f1ff;
        border-radius: .2rem;
        padding: .1rem .4rem;
    }
    @media (max-width: 575.98px) {
        #rec-list { grid-template-columns: repeat(auto-fill, minmax(115px, 1fr)); }
    }
    </style>

    <div id="rec-widget">
        <div class="rec-head">
            <h3>Buku dengan Konten Serupa</h3>
            <span class="rec-sub">Berdasarkan kemiripan judul, subjek, dan klasifikasi (Content-Based Filtering)</span>
        </div>
        <div id="rec-list">
            <p class="rec-loading">Memuat rekomendasi&hellip;</p>
        </div>
    </div>

    <script>
    (function () {
        var BOOK_SVG = 'data:image/svg+xml;utf8,' + encodeURIComponent(
            '<svg xmlns="http://www.w3.org/2000/svg" width="54" height="72" viewBox="0 0 54 72">' +
            '<rect x="6" y="3" width="42" height="66" rx="4" fill="#C7D2FE"/>' +
            '<rect x="3" y="3" width="6" height="66" rx="3" fill="#A5B4FC"/>' +
            '<rect x="14" y="14" width="26" height="3" rx="1.5" fill="#ffffff" opacity=".8"/>' +
            '<rect x="14" y="22" width="20" height="3" rx="1.5" fill="#ffffff" opacity=".6"/>' +
            '</svg>'
        );

        var biblioId  = <?= intval($detail_id) ?>;
        var apiUrl    = 'plugins/recommendation/recommend.php?biblio_id=' + biblioId;
        var container = document.getElementById('rec-list');

        fetch(apiUrl)
            .then(function (r) { return r.json(); })
            .then(function (books) {
                container.innerHTML = '';

                if (!books || !books.length) {
                    var msg = document.createElement('p');
                    msg.className = 'rec-loading';
                    msg.textContent = 'Belum ada rekomendasi untuk buku ini.';
                    container.appendChild(msg);
                    return;
                }

                books.forEach(function (book) {
                    var pct = Math.round((book.similarity || 0) * 100);

                    /* --- cover (selalu ada gambar: lokal / online cache / SVG buatan) --- */
                    var img = document.createElement('img');
                    img.loading = 'lazy';
                    img.alt = book.title || '';
                    img.src = book.image || BOOK_SVG;
                    img.onerror = function () {
                        this.onerror = null;
                        this.className = 'is-fallback';
                        this.src = BOOK_SVG;
                    };

                    var coverDiv = document.createElement('div');
                    coverDiv.className = 'rec-card-cover';
                    coverDiv.appendChild(img);

                    /* --- body --- */
                    var titleEl = document.createElement('p');
                    titleEl.className = 'rec-card-title';
                    titleEl.textContent = book.title || '';

                    var pctEl = document.createElement('p');
                    pctEl.className = 'rec-card-pct';
                    pctEl.textContent = 'Kecocokan ' + pct + '%';

                    var bodyDiv = document.createElement('div');
                    bodyDiv.className = 'rec-card-body';
                    bodyDiv.appendChild(titleEl);
                    bodyDiv.appendChild(pctEl);

                    /* --- card --- */
                    var card = document.createElement('a');
                    card.href      = book.detail_url;
                    card.className = 'rec-card';
                    card.appendChild(coverDiv);
                    card.appendChild(bodyDiv);

                    container.appendChild(card);
                });
            })
            .catch(function () {
                container.innerHTML = '<p class="rec-loading">Gagal memuat rekomendasi.</p>';
            });
    })();
    </script>
    <?php
    // === END WIDGET ===
}
