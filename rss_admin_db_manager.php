<?php

$plugin=array(
'name'=>'rss_admin_db_manager',
'version'=>'4.3',
'author'=>'Rob Sable',
'author_uri'=>'http://www.wilshireone.com/',
'description'=>'Database management system.',
'type'=>'3',
);

if (!defined('txpinterface')) @include_once('../zem_tpl.php');

# --- BEGIN PLUGIN CODE ---

if (@txpinterface == 'admin') {
	#==================================
	#	Strings + MLP Integration...
	#==================================
	global $rss_dbman_strings;
	if( !is_array($rss_dbman_strings))
		$rss_dbman_strings = array(
			'tab_db'=>'DB Manager',
			'tab_sql'=>'Run SQL',
			'tab_backup'=>'DB Backup',
			'runsql_text'=>'Each query must be on a single line.  You may run multiple queries at once by starting a new line.'.br.'Supported query types include SELECT, INSERT, UPDATE, CREATE, REPLACE, and DELETE.',
			'runsql_warn'=>'WARNING: All SQL run in this window will immediately and permanently change your database.',
			'goto_dbman'=>'Go to Database Manager',
			'runsql_stats'=>'{success}/{total} Query(s) Executed Successfully.',
			'runsql_unsupported'=>' - QUERY TYPE NOT SUPPORTED',
			'dbman_repaired'=>'Repaired: ',
			'dbman_repair'=>'Repair',
			'dbman_repair_all'=>'Repair All',
			'dbman_repaired_all'=>'Repaired All Tables',
			'dbman_backup'=>'Backup',
			'dbman_optimised'=>'Optimzed: ',
			'dbman_dropped'=>'Dropped: ',
			'dbman_host'=>'DB Host:',
			'dbman_name'=>'DB Name:',
			'dbman_user'=>'DB User:',
			'dbman_ver'=>'DB Version:',
			'dbman_total'=>'Total',
			'dbman_tables'=>'Tables',
			'dbman_records'=>'Records',
			'dbman_data'=>'Data Usage',
			'dbman_index'=>'Index Usage',
			'dbman_overhead'=>'Overhead',
			'dbman_errno'=>'ErrNo',
			'dbman_drop'=>'Drop',
			'dbman_optimise'=>'Optimise',
			'prefs_saved'=>'Preferences Saved',
			'backup_failed'=>'BACKUP FAILED: ',
			'folder_no_write'=>'Folder is not writable',
			'backed_up'=>'Backed Up: {db} to {file}',
			'restored'=>'Restored: {db} from {file}',
			'restore_failed'=>'FAILED TO RESTORE:',
			'deleted'=>'Deleted:',
			'delete_failed'=>'Unable to Delete:',
			'gzipped'=>'gzipped file',
			'backup_log'=>'Include txp_log?',
			'locktables'=>'Lock Tables?',
			'showdebug'=>'Show Debug?',
			'backup_path'=>'Backup Path:',
			'mysql_path'=>'MySQL Path:',
			'mysqldump_path'=>'"mysqldump" Path:',
			'existingfiles'=>'Previous Backup Files',
			'bk_fname'=>'Backup File Name',
			'bk_fdate'=>'Backup Date/Time',
			'bk_fsize'=>'Backup File Size',
			'restore'=>'Restore',
			'bk_files'=>'Backup File(s)',
			'no_backups'=>'You have no database backups.',
			'bk_new'=>'Create a new backup of the {db} database?',
			'sqlfile'=>'.sql file (text)',
			'dbman_pre323'=>'Could Not Show Table Status Because Your MYSQL Version Is Lower Than 3.23.',
			);

	if( !defined( 'RSS_DBMAN_PREFIX' ) )
		define( 'RSS_DBMAN_PREFIX' , 'rss_dbman' );

	register_callback( 'rss_dbman_enumerate_strings' , 'l10n.enumerate_strings' );
	function rss_dbman_enumerate_strings()
		{
		global $rss_dbman_strings;
		$r = array	(
					'owner'		=> 'rss_admin_db_manager',
					'prefix'	=> RSS_DBMAN_PREFIX,
					'lang'		=> 'en-gb',
					'event'		=> 'admin',
					'strings'	=> $rss_dbman_strings,
					);
		return $r;
		}

	function rss_dbman_gtxt( $what , $args=array() )
		{
		global $textarray;
		global $rss_dbman_strings;

		$what = strtolower($what);
		$key = RSS_DBMAN_PREFIX . '-' . $what;

		if (isset($textarray[$key]))
			{
			$str = $textarray[$key];
			}
		else
			{
			if (isset($rss_dbman_strings[$what]))
				$str = $rss_dbman_strings[$what];
			elseif (isset($textarray[$what]))
				$str = $textarray[$what];
			else
				$str = $what;
			}
		$str = strtr( $str , $args );
		return $str;
		}

	add_privs('rss_db_man', '1');
	register_tab("extensions", "rss_db_man", rss_dbman_gtxt('tab_db'));
	register_callback("rss_db_man", "rss_db_man");

	add_privs('rss_sql_run', '1');
	register_tab("extensions", "rss_sql_run", rss_dbman_gtxt('tab_sql'));
	register_callback("rss_sql_run", "rss_sql_run");

	add_privs('rss_db_bk', '1');
	register_tab("extensions", "rss_db_bk", rss_dbman_gtxt('tab_backup'));
	register_callback("rss_db_bk", "rss_db_bk");
}

function rss_db_bk($event, $step) {
  global $prefs, $rss_dbbk_path, $rss_dbbk_dump, $rss_dbbk_mysql, $rss_dbbk_lock, $rss_dbbk_txplog, $rss_dbbk_debug, $DB, $file_base_path;

  if (!isset($rss_dbbk_lock)) {
    $rss_dbbk_lock = "1";
    $rs = safe_insert('txp_prefs', "name='rss_dbbk_lock', val='$rss_dbbk_lock', prefs_id='1'");
  }

  if (!isset($rss_dbbk_txplog)) {
    $rss_dbbk_txplog = "1";
    $rs = safe_insert('txp_prefs', "name='rss_dbbk_txplog', val='$rss_dbbk_txplog', prefs_id='1'");
  }

  if (!isset($rss_dbbk_debug)) {
    $rss_dbbk_debug = "0";
    $rs = safe_insert('txp_prefs', "name='rss_dbbk_debug', val='$rss_dbbk_debug', prefs_id='1'");
  }

  if (!isset($rss_dbbk_path)) {
    $rss_dbbk_path = $file_base_path;
    $rs = safe_insert('txp_prefs', "name='rss_dbbk_path', val='".addslashes($rss_dbbk_path)."', prefs_id='1'");
  }

  if (!isset($rss_dbbk_dump)) {
    $rss_dbbk_dump = "mysqldump";
    $rs = safe_insert('txp_prefs', "name='rss_dbbk_dump', val='".addslashes($rss_dbbk_dump)."', prefs_id='1'");
  }

  if (!isset($rss_dbbk_mysql)) {
    $rss_dbbk_mysql = "mysql";
    $rs = safe_insert('txp_prefs', "name='rss_dbbk_mysql', val='".addslashes($rss_dbbk_mysql)."', prefs_id='1'");
  }

  include(txpath . '/include/txp_prefs.php');

  $bkpath = $rss_dbbk_path;
  $iswin = preg_match('/Win/',php_uname());
  $mysql_hup = ' -h'.$DB->host.' -u'.$DB->user.' -p'.escapeshellcmd($DB->pass);
	$txplogps = ps('rss_dbbk_txplog');

  if (ps("save")) {

      pagetop(rss_dbman_gtxt('tab_db'), rss_dbman_gtxt('prefs_saved'));
      safe_update("txp_prefs", "val = '".addslashes(ps('rss_dbbk_path'))."'","name = 'rss_dbbk_path' and prefs_id ='1'");
      safe_update("txp_prefs", "val = '".addslashes(ps('rss_dbbk_dump'))."'","name = 'rss_dbbk_dump' and prefs_id ='1'");
      safe_update("txp_prefs", "val = '".addslashes(ps('rss_dbbk_mysql'))."'","name = 'rss_dbbk_mysql' and prefs_id ='1'");
      safe_update("txp_prefs", "val = '".ps('rss_dbbk_lock')."'","name = 'rss_dbbk_lock' and prefs_id ='1'");
      if (isset($txplogps)) safe_update("txp_prefs", "val = '".ps('rss_dbbk_txplog')."'","name = 'rss_dbbk_txplog' and prefs_id ='1'");
      safe_update("txp_prefs", "val = '".ps('rss_dbbk_debug')."'","name = 'rss_dbbk_debug' and prefs_id ='1'");
      header("Location: index.php?event=rss_db_bk");

  }  else if (gps("bk")) {

			$bk_table = (gps("bk_table")) ? " --tables ".gps("bk_table")." " : "";
			$tabpath = (gps("bk_table")) ? "-".gps("bk_table") : "";
      $gzip = gps("gzip");
			$filename = time().'-'.$DB->db.$tabpath;
      $backup_path = $bkpath.'/'.$filename.'.sql';
      $lock = ($rss_dbbk_lock) ? "" : " --skip-lock-tables --skip-add-locks ";
      echo $txplogps;
      $nolog = ($rss_dbbk_txplog) ? "" : " --ignore-table=".$DB->db.".txp_log ";
      $nolog = (isset($bk_table) && gps("bk_table") == "txp_log") ? "" : $nolog;

      if($gzip) {
        $backup_path.= '.gz';
        $backup_cmd = $rss_dbbk_dump.$mysql_hup.' -Q --add-drop-table '.$lock.$nolog.$DB->db.$bk_table.' | gzip > '.$backup_path;
      } else {
        $backup_cmd = $rss_dbbk_dump.$mysql_hup.' -Q --add-drop-table '.$lock.$nolog.$DB->db.$bk_table.' > '.$backup_path;
      }
	    $bkdebug = ($rss_dbbk_debug) ? $backup_cmd : '';
      $error = "";

      if (function_exists('passthru')) {
        passthru($backup_cmd, $error);
      } else {
        $dumpIt=popen($backup_cmd, 'r');
        pclose($dumpIt);
      }

      if(!is_writable($bkpath)) {
        pagetop(rss_dbman_gtxt('tab_db'), rss_dbman_gxt('backup_failed').' '.rss_dbman_gxt('folder_no_write') );
      } elseif($error) {
        unlink($backup_path);
        pagetop(rss_dbman_gtxt('tab_db'), rss_dbman_gxt('backup_failed').' '.rss_dbman_gtxt('dbman_errno').': '.$error);
      } else if(!is_file($backup_path)) {
        pagetop(rss_dbman_gtxt('tab_db'), rss_dbman_gxt('backup_failed').' '.rss_dbman_gtxt('dbman_errno').': '.$error);
      } else if(filesize($backup_path) == 0) {
        unlink($backup_path);
        pagetop(rss_dbman_gtxt('tab_db'), rss_dbman_gxt('backup_failed').' '.rss_dbman_gtxt('dbman_errno').': '.$error);
      } else {
        pagetop(rss_dbman_gtxt('tab_db'), rss_dbman_gtxt('backed_up',array( '{db}'=>$DB->db,'{file}'=>$filename)));
      }

  } else if (gps("download")) {

    $fn = gps("download");
    $file_path = $bkpath.'/'.$fn;
    header("Pragma: public");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Content-Type: application/force-download");
    header("Content-Type: application/octet-stream");
    header("Content-Type: application/download");
    if (substr($fn, -2) == "gz") header("Content-Type: application/zip");
    header("Content-Disposition: attachment; filename=".basename($file_path).";");
    header("Content-Transfer-Encoding: binary");
    header("Content-Length: ".filesize($file_path));
    @readfile($file_path);

  } else if (gps("restore")) {

    if(stristr(gps("restore"), '.gz')) {
      $backup_cmd = 'gunzip < '.$bkpath.'/'.gps("restore").' | '.$rss_dbbk_mysql.$mysql_hup.' '.$DB->db;
    } else {
      $backup_cmd = $rss_dbbk_mysql.$mysql_hup.' '.$DB->db.' < '.$bkpath.'/'.gps("restore");
    }

    $bkdebug = ($rss_dbbk_debug) ? $backup_cmd : '';
    $error = "";

    if (function_exists('passthru')) {
      passthru($backup_cmd, $error);
    } else {
      $dumpIt=popen($backup_cmd, 'r');
      pclose($dumpIt);
    }

    if($error) {
      pagetop(rss_dbman_gtxt('tab_db'), rss_dbman_gtxt('restore_failed').' '.$error);
    } else {
      pagetop(rss_dbman_gtxt('tab_db'), rss_dbman_gtxt('restored', array( '{db}'=>$DB->db,'{file}'=>gps('restore'))));
    }

  } else if(gps("delete")) {

      if(is_file($bkpath.'/'.gps("delete"))) {
        if(!unlink($bkpath.'/'.gps("delete"))) {
          pagetop(rss_dbman_gtxt('tab_db'), rss_dbman_gtxt('delete_failed').' '.gps("delete"));
        } else {
          pagetop(rss_dbman_gtxt('tab_db'), rss_dbman_gtxt('deleted').' '.gps("delete"));
        }
      } else {
        pagetop(rss_dbman_gtxt('tab_db'), rss_dbman_gtxt('delete_failed').' '.gps("delete"));
      }

  } else {
    pagetop(rss_dbman_gtxt('tab_backup'));
  }

  $gzp = (!$iswin) ? " | ".href(rss_dbman_gtxt('gzipped'), "index.php?event=rss_db_bk&amp;bk=$DB->db&amp;gzip=1") : "";

  $sqlversion = getRow("SELECT VERSION() AS version");
  $sqlv = explode("-", $sqlversion['version']);
  $allownologs = ((float)$sqlv[0] >= (float)"4.1.9") ? tda(rss_dbman_gtxt('backup_log'), ' style="text-align:right;vertical-align:middle"').tda(yesnoRadio("rss_dbbk_txplog", $rss_dbbk_txplog), ' style="text-align:left;vertical-align:middle"') : '';

	if (isset($bkdebug) && $bkdebug) echo '<p align="center">'.$bkdebug.'</p>';

  echo
  startTable('list').
  form(
  tr(
    tda(rss_dbman_gtxt('locktables'), ' style="text-align:right;vertical-align:middle"').tda(yesnoRadio("rss_dbbk_lock", $rss_dbbk_lock), ' style="text-align:left;vertical-align:middle"').
    $allownologs.
    tda(rss_dbman_gtxt('showdebug'), ' style="text-align:right;vertical-align:middle"').tda(yesnoRadio("rss_dbbk_debug", $rss_dbbk_debug), ' style="text-align:left;vertical-align:middle"').
    tda(fInput("submit","save",gTxt("save_button"),"publish").eInput("rss_db_bk").sInput('saveprefs'), " colspan=\"2\" class=\"noline\"")
  ).
  tr(
    tda(rss_dbman_gtxt('backup_path'), ' style="text-align:right;vertical-align:middle"').tda(text_input("rss_dbbk_path",$rss_dbbk_path,'50'), ' colspan="15"')
  ).
  tr(
    tda(rss_dbman_gtxt('mysqldump_path'), ' style="text-align:right;vertical-align:middle"').tda(text_input("rss_dbbk_dump",$rss_dbbk_dump,'50'), ' colspan="15"')
  ).
  tr(
    tda(rss_dbman_gtxt('mysql_path'), ' style="text-align:right;vertical-align:middle"').tda(text_input("rss_dbbk_mysql",$rss_dbbk_mysql,'50'), ' colspan="15"'))
  ).endTable().
  startTable("list").
  tr(
    tda(hed(rss_dbman_gtxt('bk_new', array('{db}'=>strong($DB->db)) ).br.
    href(rss_dbman_gtxt('sqlfile'), "index.php?event=rss_db_bk&amp;bk=$DB->db").$gzp,3),' colspan="7" style="text-align:center;"')
    ).
  tr(tdcs(hed(rss_dbman_gtxt('existingfiles'),1),7)).
  tr(
    hcell('#').
    hcell(rss_dbman_gtxt('bk_fname')).
    hcell(rss_dbman_gtxt('bk_fdate')).
    hcell(rss_dbman_gtxt('bk_fsize')).
    hcell("").
    hcell("").
    hcell("")
  );

  $totalsize = 0;
  $no = 0;
  if(!is_folder_empty($bkpath)) {
    if ($handle = opendir($bkpath)) {
      $database_files = array();
      while (false !== ($file = readdir($handle))) {
        if (($file != '.' && $file != '..') && (substr($file, -4) == ".sql" || substr($file, -7) == ".sql.gz")) {
          $database_files[] = $file;
        }
      }
      closedir($handle);
      for($i = (sizeof($database_files)-1); $i > -1; $i--) {
        $no++;
        $style = ($no%2 == 0) ? ' style="background-color: #eee;"' : '';
        $database_text = substr($database_files[$i], 11);
        $date_text = strftime("%A, %B %d, %Y [%H:%M:%S]", substr($database_files[$i], 0, 10));
        $size_text = filesize($bkpath.'/'.$database_files[$i]);
        $totalsize += $size_text;

        echo
        tr(
          td($no).
          td($database_text).
          td($date_text).
          td(prettyFileSize($size_text)).
          '<td><a href="index.php?event=rss_db_bk&amp;download='.$database_files[$i].'">'.gTxt('download').'</a></td>'.
          '<td><a href="index.php?event=rss_db_bk&amp;restore='.$database_files[$i].'"  onclick="return verify(\''.gTxt('are_you_sure').'\')">'.rss_dbman_gtxt('restore').'</a></td>'.
          '<td><a href="index.php?event=rss_db_bk&amp;delete='.$database_files[$i].'"  onclick="return verify(\''.gTxt('are_you_sure').'\')">'.gTxt('delete').'</a></td>', $style
        );
      }

    echo
      tr(
        tag($no.' '.rss_dbman_gtxt('bk_files'), "th", ' colspan="3"').
        tag(prettyFileSize($totalsize), "th", ' colspan="4"')
      );

    } else {
      echo
      tr(
        tda(hed(rss_dbman_gtxt('no_backups').br.rss_dbman_gtxt('bk_new', array('{db}'=>strong($DB->db)) ).br.
        href(rss_dbman_gtxt('sqlfile'), "index.php?event=rss_db_bk&amp;bk=$DB->db").$gzp,3),' colspan="7" style="text-align:center;"')
        );
    }
  } else {
      echo
      tr(
        tda(hed(rss_dbman_gtxt('no_backups').br.rss_dbman_gtxt('bk_new', array('{db}'=>strong($DB->db)) ).br.
        href(rss_dbman_gtxt('sqlfile'), "index.php?event=rss_db_bk&amp;bk=$DB->db").$gzp,3),' colspan="7" style="text-align:center;"')
        );
  }
  echo endTable();
}

function rss_db_man($event, $step) {
  global $DB;

  if (gps("opt_table")) {
    $query = "OPTIMIZE TABLE ".gps("opt_table");
    safe_query($query);
    pagetop(rss_dbman_gtxt('tab_db'), rss_dbman_gtxt('dbman_optimised').gps("opt_table"));
  } else  if (gps("rep_table")) {
    $query = "REPAIR TABLE ".gps("rep_table");
    safe_query($query);
    pagetop(rss_dbman_gtxt('tab_db'), rss_dbman_gtxt('dbman_repaired').gps("rep_table"));
	} else 	if (gps("rep_all")) {
		$query = "REPAIR TABLE ".gps("rep_all");
		safe_query($query);
		pagetop(rss_dbman_gtxt('tab_db'), rss_dbman_gtxt('dbman_repaired_all'));
  } else  if (gps("drop_table")) {
    $query = "DROP TABLE ".gps("drop_table");
    safe_query($query);
    pagetop(rss_dbman_gtxt('tab_db'), rss_dbman_gtxt('dbman_dropped').gps("drop_table"));
  } else {
    pagetop(rss_dbman_gtxt('tab_db'));
  }

	$sqlversion = getRow("SELECT VERSION() AS version");
	$headatts = ' style="color:#0069D1;padding:0 10px 0 5px;"';

	echo
	startTable('dbinfo').
	tr(
		hcell(rss_dbman_gtxt('dbman_host')).
		tda($DB->host, $headatts).
		hcell(rss_dbman_gtxt('dbman_name')).
		tda($DB->db, $headatts).
		hcell(rss_dbman_gtxt('dbman_user')).
		tda($DB->user, $headatts).
		hcell(rss_dbman_gtxt('dbman_ver')).
		tda("MySQL v".$sqlversion['version'], $headatts)
	).
	endTable().br;

	echo
	startTable('list').
	tr(
		hcell('#').
		hcell(rss_dbman_gtxt('dbman_tables')).
		hcell(rss_dbman_gtxt('dbman_records')).
		hcell(rss_dbman_gtxt('dbman_data')).
		hcell(rss_dbman_gtxt('dbman_index')).
		hcell(rss_dbman_gtxt('dbman_total')).
		hcell(rss_dbman_gtxt('dbman_overhead')).
		//hcell(rss_dbman_gtxt('dbman_optimise')).
		hcell(rss_dbman_gtxt('dbman_errno')).
		hcell(rss_dbman_gtxt('dbman_repair')).
		hcell(rss_dbman_gtxt('dbman_backup')).
		hcell(rss_dbman_gtxt('dbman_drop'))
	);

		if($sqlversion['version'] >= '3.23') {
			$no = 0;
			$row_usage = 0;
			$data_usage = 0;
			$index_usage =  0;
			$overhead_usage = 0;
			$alltabs = array();

			$tablesstatus = getRows("SHOW TABLE STATUS");
			foreach($tablesstatus as  $tablestatus) {
				extract($tablestatus);

				$q = "SHOW KEYS FROM `".$Name."`";
				safe_query($q);
				$mysqlErrno = mysql_errno();
				$alltabs[] = $Name;

				$color = ($mysqlErrno != 0) ? ' style="color:#D10000;"' : ' style="color:#4B9F00;"';
				$color2 = ($Data_free > 0) ? ' style="color:#D10000;"' : ' style="color:#4B9F00;"';
				$style = ($no%2 == 0) ? ' style="background-color: #eee;"' : '';

				$no++;
				$row_usage += $Rows;
				$data_usage += $Data_length;
				$index_usage +=  $Index_length;
				$overhead_usage += $Data_free;

				echo
				tr(
					td($no).
					td(href($Name, "index.php?event=rss_sql_run&amp;tn=".$Name)).
					td(" ".$Rows).
					td(prettyFileSize($Data_length)).
					td(prettyFileSize($Index_length)).
					td(prettyFileSize($Data_length + $Index_length)).
					tda(prettyFileSize($Data_free), $color2).
					tda(' '.$mysqlErrno, $color).
					td(href(rss_dbman_gtxt('dbman_repair'), "index.php?event=rss_db_man&amp;rep_table=".$Name)).
					td(href(rss_dbman_gtxt('dbman_backup'), "index.php?event=rss_db_bk&amp;bk=1&amp;bk_table=".$Name).
					'<td><a href="index.php?event=rss_db_man&amp;drop_table='.$Name.'"  onclick="return verify(\''.gTxt('are_you_sure').'\')">'.rss_dbman_gtxt('dbman_drop').'</a></td>'), $style
				);
			}

			echo
			tr(
				hcell(rss_dbman_gtxt('dbman_total')).
				hcell($no.' '.rss_dbman_gtxt('dbman_tables')).
				hcell(number_format($row_usage)).
				hcell(prettyFileSize($data_usage)).
				hcell(prettyFileSize($index_usage)).
				hcell(prettyFileSize($data_usage + $index_usage)).
				hcell(prettyFileSize($overhead_usage)).
				hcell().
				tda(href(strong(rss_dbman_gtxt('dbman_repair_all')), "index.php?event=rss_db_man&amp;rep_all=".implode(",",$alltabs)), ' style="text-align:center;" colspan="3"'), $style
			);

		} else {
			echo
			tr(
				tda(rss_dbman_gtxt('dbman_pre323'), ' style="text-align:center;" colspan=14"')
			);
		}

echo
	tr(
		tda(href(rss_dbman_gtxt('tab_sql'), "index.php?event=rss_sql_run"), ' style="text-align:center;" colspan="14"')
	).
	endTable();
}

function rss_sql_run($event, $step) {
  pagetop(rss_dbman_gtxt('tab_sql'));
  $text="";
  $rsd[]="";
  $sql_query2="";

  if (gps("tn")) {
    $tq = "select * from ".gps("tn");
  }

  if (gps("sql_query") || gps("tn")) {
    $sql_queries2 = (gps("sql_query")) ? trim(gps("sql_query")) : trim($tq);
    $totalquerycount = 0;
    $successquery = 0;
    if($sql_queries2) {
      $sql_queries = array();
      $sql_queries2 = explode("\n", $sql_queries2);
      foreach($sql_queries2 as $sql_query2) {
        $sql_query2 = trim(stripslashes($sql_query2));
        $sql_query2 = preg_replace("/[\r\n]+/", '', $sql_query2);
        if(!empty($sql_query2)) {
          $sql_queries[] = $sql_query2;
        }
      }

      foreach($sql_queries as $sql_query) {
        if (preg_match("/^\\s*(insert|update|replace|delete|create|truncate) /i",$sql_query)) {
          $run_query = safe_query($sql_query);
          if(!$run_query) {
            $text .= graf(mysql_error(), ' style="color:#D10000;"');
            $text .= graf($sql_query, ' style="color:#D10000;"');
          } else {
            $successquery++;
            $text .= graf($sql_query, ' style="color:#4B9F00;"');
          }
          $totalquerycount++;
        } elseif (preg_match("/^\\s*(select) /i",$sql_query)) {
          $run_query = safe_query($sql_query);
          if($run_query) $successquery++;
            if ($run_query && mysql_num_rows($run_query) > 0) {

              /* get column metadata */
              $i = 0;
              $headers = "";
              while ($i < mysql_num_fields($run_query)) {
                 $meta = mysql_fetch_field($run_query, $i);
                 $headers.=hcell($meta->name);
                 $i++;
              }

              $rsd[] =
              '<div class="scrollWrapper">'.startTable('list', '', 'scrollable').
              '<thead>'.tr($headers).'</thead><tbody>';

              while ($a = mysql_fetch_assoc($run_query)) $out[] = $a;
              mysql_free_result($run_query);

              foreach ($out as $b) {
                $data = "";
                foreach ($b as $f) {
                  $data.=td($f);
                }
                $rsd[] = tr($data);
              }

              $rsd[] = '</tbody>'.endTable().'</div>'.br;
              $out = array();
            } else {
              $text .= graf(mysql_error(), ' style="color:#D10000;"');
            }
          $text .= graf($sql_query, ' style="color:#D10000;"');
          $totalquerycount++;
        } elseif (preg_match("/^\\s*(drop|show|grant) /i",$sql_query)) {
          $text .= graf($sql_query.rss_dbman_gtxt('runsql_unsupported'), ' style="color:#D10000;"');
          $totalquerycount++;
        }
      }
      $text .= graf( rss_dbman_gtxt('runsql_stats',array('{success}'=>$successquery,'{total}'=>$totalquerycount)), ' style="color:#0069D1;"');  
    }
  }

  echo
  startTable('edit').
  tr(
    td(
      form(
        graf(rss_dbman_gtxt('runsql_text')).
        graf(rss_dbman_gtxt('runsql_warn'), ' style="font-weight:bold;"').
        text_area('sql_query','200','550',$sql_query2).br.
        fInput('submit','run',gTxt('Run'),'publish').href(rss_dbman_gtxt('goto_dbman'), "index.php?event=rss_db_man").
        eInput('rss_sql_run'), '', ' verify(\''.gTxt('are_you_sure').'\')"'
      )
    )
  ).
  tr(
    td(
        graf($text.br.implode('', $rsd))
    )
  ).
  endTable();

}
function prettyFileSize ($bytes) {
  if ($bytes < 1024) {
      return "$bytes bytes";
  } else if (strlen($bytes) <= 9 && strlen($bytes) >= 7) {
      return number_format($bytes / 1048576,2)." MB";
  } elseif (strlen($bytes) >= 10) {
      return number_format($bytes / 1073741824,2)." GB";
  }
  return number_format($bytes / 1024,2)." KB";
}

function is_folder_empty($dir) {
  if (is_dir($dir)) {
    $dl=opendir($dir);
    if ($dl) {
      while($name = readdir($dl)) {
        if (!is_dir("$dir/$name")) {
          return false;
          break;
      }
    }
    closedir($dl);
    }
    return true;
  } else return true;
}
# --- END PLUGIN CODE ---
if (0) {
?>
<!-- /*
# --- BEGIN PLUGIN HELP ---
<p>
h1. Textpattern Database Manager</p>

	<p>The rss_admin_db_manager plugin adds 3 new tabs to your Textpattern admin interface.  Each tab contains different functionality to help manage<br />
your <a href="http://www.mysql.com/">MySQL</a> database.  You can think of this plugin as a lightweight replacement for <a href="http://www.phpmyadmin.net/home_page/">phpMyAdmin</a>.</p>

	<h2>Database Backup</h2>

	<p>The <strong>DB Backup tab</strong> allows you to backup, download and restore the MySQL database that is used for your Textpattern installation.<br />
The database backups and restores are run using MySQL&#8217;s <a href="http://dev.mysql.com/doc/mysql/en/mysqldump.html">mysqldump</a> command.<br />
On this tab you are able to:</p>

	<ul>
		<li>Create a .sql backup file on windows with the additional option of creating a gzipped backup on *nix operating systems</li>
		<li>View a list of previous backup files</li>
		<li>Restore your database from one of the previous backups</li>
		<li>Download a backup file</li>
		<li>Delete old backups</li>
	</ul>

	<h2>Backup Preferences</h2>

	<p>You have the ability to set several preferences related to your database backups.  You can set these options on the backup tab.  The options include:</p>

	<ul>
		<li><strong>Lock Tables</strong> &#8211; Your host may or may not support this option.  For example, by default, Textdrive doesn&#8217;t allow table locking.  If your backup fails, try setting this to &#8220;No&#8221;.</li>
		<li><strong>Debug Mode</strong> &#8211; Turning debugging on will echo the command being run to the screen.</li>
		<li><strong>Backup Path</strong> &#8211; Set the directory that your backups will be saved to.</li>
		<li><strong>Mysqldump Path</strong> &#8211; Its likely that the default will work for you.  If not, enter the full path the the executable.</li>
		<li><strong>Mysql Path</strong> &#8211; Its likely that the default will work for you.  If not, enter the full path the the executable.</li>
	</ul>

	<h2>Database Manager</h2>

	<p>The <strong>DB Manager tab</strong> displays information about your MySQL database and all of its tables.  A detailed list includes the name of the table, number of rows and file space usage.<br />
You will also be alerted of any overhead or errors that need to be repaired.  Tables can be repaired, dropped or backed up from this listing.</p>

	<ul>
		<li>Clicking on the name of the table will run a select * [table name] <span class="caps">SQL</span> statement and take you to the <strong>Run <span class="caps">SQL</span> tab</strong> to display the results.</li>
		<li>Repair a single table in the listing by clicking the Repair link.</li>
		<li>Repair all tables in the listing by clicking the Repair All link.</li>
		<li>Backup a single table in the listing by clicking the Backup link.</li>
		<li>Drop a single table in the listing by clicking the Drop link.</li>
	</ul>

	<h2>Run <span class="caps">SQL</span> Window</h2>

	<p>The <strong>Run <span class="caps">SQL</span> tab</strong> allows for free form entry and execution of <span class="caps">SQL</span> statements.  The <span class="caps">SQL</span> window accepts<br />
<span class="caps">SELECT</span>, <span class="caps">INSERT</span>, <span class="caps">UPDATE</span>, <span class="caps">CREATE</span>, <span class="caps">REPLACE</span>, <span class="caps">TRUNCATE</span>, and <span class="caps">DELETE</span> statements.  If a <span class="caps">SELECT</span> statement is run, the results will be displayed to you below the <span class="caps">SQL</span> window in a table.<br />
The table markup allows you to add your own styles for creating a <a href="http://www.agavegroup.com/?p=31"><span class="caps">CSS</span> Scrollable Table</a>.</p>

	<h2>Major Ransom Contributors</h2>

	<p><ul>
		<li>Jan Willem de Bruijn</li>
		<li>Heikki Yl</li></ul></p>
# --- END PLUGIN HELP ---
*/ -->
<?php
}
?>