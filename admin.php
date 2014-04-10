<?php

/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     
 */
// must be run within Dokuwiki
if (!defined('DOKU_INC'))
    die();

if (!defined('DOKU_PLUGIN'))
    define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');

require_once(DOKU_PLUGIN . 'admin.php');

class admin_plugin_fksproblems extends DokuWiki_Admin_Plugin {

    function getMenuSort() {
        return 228;
    }

    function forAdminOnly() {
        return false;
    }

    function getMenuText($language) {
        $menutext = $this->getLang('menu');
        return $menutext;
        //return "upload zadani";
    }

    function handle() {
        global $lang;
    }

    function html() {

        if (!$_FILES["file"] && !$_POST["year"] && !$_POST["series"]) {
            $to_page.='<div class="fksproblems" ><form action="doku.php?id=ulohy:upload&do=admin&page=fksproblems" method="post"';
            $to_page.='enctype="multipart/form-data">';
            $to_page.='<label for="file">'. $this->getLang('filecoment') .'</label>';
            $to_page.='<input type="file" name="file" id="file"><br>';
            $to_page.='<label >'. $this->getLang('series') .'</label>';
            $to_page.='<select name="series">';
            $to_page.='<option value="1" selected="selected">1</option>';
            $to_page.='<option value="2" >2</option>';
            $to_page.='<option value="3" >3</option>';
            $to_page.='<option value="4" >4</option>';
            $to_page.='<option value="5" >5</option>';
            $to_page.='<option value="6" >6</option>';
            $to_page.='<option value="8" >PrS1</option>';
            $to_page.='<option value="9" >PrS2</option>';
            $to_page.='<option value="10" >PrS3</option>';
            $to_page.='</select><br>';
            $to_page.='<label >'. $this->getLang('year') .'</label>';
            $to_page.='<select name="year">';
            $to_page.='<option value="3" selected="selected">3</option>';
            $to_page.='<option value="4" >4</option>';
            $to_page.='</select>';
            $to_page.='<label >'. $this->getLang('year') .'</label>';
            $to_page.='<select name="typebatch">';
            $to_page.='<option value="solution" selected="selected">Rešení</option>';
            $to_page.='<option value="task" >Zadaní</option>';
            $to_page.='</select>';


            $to_page.='<div class="no">';
            //$to_page.='<input type="hidden" name="do" value="">';
            $to_page.='<input type="hidden" name="rev" value="0">';
            //$to_page.='<input id="yearseries" type="hidden" name="id" value="ulohy:upload"">';

            $to_page.='<input type="submit" onclick="" value="'. $this->getLang('subuploadfile') .'" class="button" >';
            $to_page.='</div>';
            $to_page.='</form>';
            $to_page.='</div>';
        } elseif ($_FILES["file"]) {
            if ($_FILES["file"]["error"] > 0) {
                $uploadinfo.= "Error: " . $_FILES["file"]["error"] . "<br>";
            } else {
// parameters uploaded file
                $uploadinfo.= $this->getLang('file') ." ". $_FILES["file"]["name"] . "<br>";
                $uploadinfo.= $this->getLang('type')." ". $_FILES["file"]["type"] . "<br>";
                $uploadinfo.= $this->getLang('size') ." ". ($_FILES["file"]["size"] / 1024) . " kB<br>";

                $uploadinfo.= $this->getLang('series') ." ". $_POST["series"] . "<br>";
                $uploadinfo.= $this->getLang('year') ." ". $_POST["year"] . "<br>";
                $uploadinfo.= "typ: ". $_POST["typebatch"] . "<br>";
            }

            $subor = fopen($_FILES["file"]["tmp_name"], "r");
            $uploadfile = fread($subor, 1000000);

            fclose($subor);

            $series = $_POST["series"];
            $year = $_POST["year"];
            $typebatch = $_POST["typebatch"];
            file_put_contents("data/pages/ulohy/upload.txt", $uploadfile);
           
            $to_page.= '<form id="problemsupload" action="doku.php?id=ulohy:year'.$year.':batch'.$series.'&do=admin&page=fksproblems" method="post">';
            $to_page.= $uploadinfo;
            $to_page.='<input type="hidden" name="series" value="'.$series.'">';
            $to_page.='<input type="hidden" name="year" value="'.$year.'">';
            $to_page.='<input type="hidden" name="typebatch" value="'.$typebatch.'">';
            $to_page.='<input type="hidden" name="rev" value="0">';
            //$to_page.='<input id="yearseries" type="hidden" name="id" value="ulohy:'.$year.':'.$series .'">';

            $to_page.='<input type="submit" onclick="" value="'.$this->getLang('subsavefile').'" class="button" >';
            $to_page.='</form>';
            $to_page.=p_render("xhtml", p_get_instructions($uploadfile), $info);
        }
        else {
            $series = $_POST["series"];
            $year = $_POST["year"];
            $typebatch = $_POST["typebatch"];
            $uploadfile=io_readFile("data/pages/ulohy/upload.txt", false);
            file_put_contents("data/pages/ulohy/year".$year."/".$typebatch.$series.".txt", $uploadfile);
            $to_page.= '<form id="problemsupload" action="doku.php?id=ulohy:year'.$year.':'.$typebatch.$series.'" method="post">';
            //$to_page.='<input type="hidden" name="do" value="edit">';
            $to_page.='<input type="hidden" name="rev" value="0">';
            //$to_page.='<input type="hidden" name="id" value="ulohy:'.$year.':'.$series .'">';
            $to_page.='<input type="submit" onclick="" value="'.$this->getLang('subdonefile').'" class="button" >';
        }
// save modified file
        /* if (!empty($_POST["problemtask"])){
          $series=$_POST["series"];
          $year=$_POST["year"];
          file_put_contents("$series.$year.txt" ,$_POST["problemtask"]);
          } */
        echo $to_page;
    }

}
