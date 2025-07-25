<?php
/**
 * lib.php
 * This file is part of the FreeSentral Project http://freesentral.com
 *
 * FreeSentral - is a Web Graphical User Interface for easy configuration of the Yate PBX software
 * Copyright (C) 2008-2014 Null Team
 *
 * This software is distributed under multiple licenses;
 * see the COPYING file in the main directory for licensing
 * information for this specific distribution.
 *
 * This use of this software may be subject to additional restrictions.
 * See the LEGAL file in the main directory for details.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once("debug.php");

global $module, $method, $action, $vm_base, $limit, $db_true, $db_false, $limit, $page, $system_standard_timezone;

/**
 *  Include the classes for database objects.
 *  @param $path String. Path to the files to be included. 
 */ 
function include_classes($path='')
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	$classes_dirs = array("classes/", "ansql/default_classes");

	for ($i=0; $i<count($classes_dirs); $i++) {
		if (!is_dir($path.$classes_dirs[$i]))
			continue;
		$handle = opendir($path.$classes_dirs[$i]);

		while (false !== ($file = readdir($handle))) {
			if (substr($file,-4) != '.php')
				continue;
			else {
				if ($classes_dirs[$i] == "ansql/default_classes") {
					$file_name = substr($file,0,strlen($file)-4);
					global ${"custom_$file_name"};
					if (isset(${"custom_$file_name"}) && ${"custom_$file_name"})
						continue;
				}
				require_once($path.$classes_dirs[$i]."/$file");
			}
		}
	}
}

/**
 * Implementation of stripos function if it does not exist.
 * Find the position of the first occurrence of a case-insensitive substring in a string.
 */ 
if (!function_exists("stripos")) {
	// PHP 4 does not define stripos
	function stripos($haystack,$needle,$offset=0)
	{
		return strpos(strtolower($haystack),strtolower($needle),$offset);
	}
}

/**
 * Implementation of array_column function if it does not exist.
 * Return the values from a single column in the input array
 */ 
if (!function_exists("array_column")) {
	// PHP <5.5 does not define array_column
	function array_column($input, $column_key, $index_key=null) {
		Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

		$err_mess="";
		if ($index_key!==null || $column_key===null)
			$err_mess = "This functionality was not yet implemented for your php version. The function does not support reindexing arrays or returning complete objects or arrays yet.";
		else if (!is_array($input))
			$err_mess = "Parameter input must be an array.";
		else if (!ctype_digit(strval($column_key)) && !is_string($column_key))
			$err_mess = "Parameter column_key must be either a string or an integer. ";

		if ($err_mess!="") {
			errormess("array_column(): Please contact an administrator." . $err_mess,"no");
			Debug::trigger_report ("critical", $err_mess);
			return array();
		}
		
		$result = array();
		foreach ($input as $elem) {
			if (is_object($elem)) {
				$err_mess = "This functionality was not yet implemented for your php version. The parameter input array must not contain elements of type object.";
				errormess("array_column(): Please contact an administrator." . $err_mess, "no");
				Debug::trigger_report ("critical", $err_mess);
				return array();
			}
			if (!isset($elem[$column_key]) || !is_array($elem))
				continue;
			$result[] = $elem[$column_key];
		}
		return $result;
	}
}

escape_page_params();

if (!isset($system_standard_timezone))
	$system_standard_timezone = "GMT".substr(date("O"),0,3);

/**
 * Establish the name of the default function to be called using some predefind criteria.
 * @param $module String. The Module defined for each project. 
 * @param $method String. The Method associated to the Module.
 * @param $action String. The action associated with a method.
 * @param $call String. The name of the default function to be called.
 * @return string of type $call
 */ 
function get_default_function()
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	global $module, $method, $action;

	if (!$method)
		$method = $module;
	if (substr($method,0,4) == "add_")
		$method = str_replace("add_","edit_",$method);
	if ($action)
		$call = $method.'_'.$action;
	else
		$call = $method;
	return $call;
}

/**
 * Test a given Path.
 * @param $path String. 
 * @return 403 Forbidden page only if $path matches a regular expression
 */ 
function testpath($path)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	if (preg_match("/[^A-Za-z0-9_]/",$path, $matches)) {
 		// Client tried to hack around the path naming rules - ALERT!
		Debug::trigger_report('operational', "Preg_match function match path: ".print_r($matches)." session: ".print_r($_SESSION,true));
 		forbidden();
 	}
}

/**
 * Send a raw HTTP header with 403 Forbidden. Clears the session data. 
 * Displays a page with Forbidden message.
 * Terminates the current script.
 */ 
function forbidden()
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	header("403 Forbidden");
	session_unset();
	print '<html><body style="color:red">Forbidden</body></html>';
	exit();
}

/**
 * Builds the HTML <form> tag with all possible attributes:
 * @param $action String. The action of the FORM
 * @param $method String. Allowed values: post|get. Defaults to 'post'.
 * @param $allow_upload Bool. If true allow the upload of files. Defaults to false.
 * @param $form_name String. Fill the attribute name of the FORM. 
 * Defaults to global variable $module or 'current_form' if $module is not set or null
 * @param $class String. Fill the attribute class. No default value set.
 */ 
function start_form($action = NULL, $method = "post", $allow_upload = false, $form_name = NULL, $class = NULL)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	global $module;

	if (!$method)
		$method = "post";
	$form = (!$module) ? "current_form" : $module;
	if (!$form_name)
		$form_name = $form;
	if (!$action) {
		if (isset($_SESSION["main"]))
			$action = $_SESSION["main"];
		else
			$action = "index.php";
	}

	?><form action="<?php print $action;?>" name="<?php print $form_name;?>" id="<?php print $form_name;?>" <?php if ($class) print "class=\"$class\"";?> method="<?php print $method;?>" <?php if($allow_upload) print 'enctype="multipart/form-data"';?>><?php
}

/**
 * Ends a HTML FORM tag.
 */ 
function end_form()
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	?></form><?php
}

/**
 * Displays a given text as a note.
 * @param $note String Contains the note text.
 * @param $encode Bool True to use htmlentities on message before displaying, false for not using htmlentities.
 */ 
function note($note, $encode=true)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	
	$note = ($encode) ? htmlentities($note) : $note;
	print 'Note!! '.htmlentities($note).'<br/>';
}

/**
 * Displays an error note with a predefined css
 * @param $text String The message to be displayed.
 * @param $encode Bool True to use htmlentities on message before displaying, false for not using htmlentities.
 */ 
function errornote($text, $encode=true)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	
	$text = ($encode) ? htmlentities($text) : $text;
	print "<br/><font color=\"red\" style=\"font-weight:bold;\" > Error!!</font> <font style=\"font-weight:bold;\">".$text."</font><br/>";
}

/**
 * Displays a given text.
 * @param $text String The text to be displayed
 * @param $path String The path to use in link 
 * @param $return_text the link to return to requested Path
 * @param $encode Bool True to use htmlentities on message before displaying, false for not using htmlentities.
 */ 
function message($text, $path=NULL, $return_text="Go back to application", $encode=true)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $module,$method;

	$text = ($encode) ? htmlentities($text) : $text;
	
	print '<div class="notice">'."\n";
	print $text."\n";

	if ($path == 'no') {
		print '</div>';
		return;
	}

	link_to_main_page($path, $return_text);

	print '</div>';
}

/**
 * Displays a given text with a specific css for errors
 * @param $text String The text to be displayed
 * @param $path String The path to use in link
 * @param $return_text the link to return to requested Path
 * @param $encode Bool True to use htmlentities on message before displaying, false for not using htmlentities.
 */ 
function errormess($text, $path=NULL, $return_text="Go back to application", $encode=true)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $module;

	$text = ($encode) ? htmlentities($text) : $text;
	print '<div class="notice error">'."\n";
	print "<font class=\"error\"> Error!!</font>"."\n";
	print "<font style=\"font-weight:bold;\">". $text ."</font>"."\n";

	if ($path == 'no') {
		print '</div>';
		return;
	}

	link_to_main_page($path, $return_text);
	
	print '</div>';
}

/**
 * Prints a message as a notice or an error type message and calls a function 
 * @param $message String The message to be displayed.
 * @param $next_cb Callable/String. Setting it to 'no' stops the performing of the callback.  
 * @param $no_error Boolean If is true a message id displayed else an error type message is displayed. Defaults to true.
 * @param $encode Bool True to use htmlentities on message before displaying, false for not using htmlentities.
 */ 
function notice($message, $next_cb=NULL, $no_error = true, $encode=true)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $module;

	if (!$next_cb)
		$next_cb = $module;
	
	$message = ($encode) ? htmlentities($message) : $message;

	if ($no_error)
		print '<div class="notice">'.$message.'</div>';
	else
		print '<div class="notice error"><font class="error">Error!! </font>'.$message.'</div>';

	if ($next_cb != "no")
		call_user_func($next_cb);
}

/**
 * Displays a text with a bold font style set.
 * @param $text String The message to be displayed.
 * @param $encode Bool True to use htmlentities on message before displaying, false for not using htmlentities.
 */ 
function plainmessage($text, $encode=true)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$text = ($encode) ? htmlentities($text) : $text;
	print "<br/><font style=\"font-weight:bold;\">".$text."</font><br/><br/>";
}

/**
 * Displays a message or an error message depending on the data given in array
 * @param $res Array Contains on key 0:  true/false  
 * and on key 1: the message to be displayed 
 */ 
function notify($res)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $path;

	if ($res[0])
		message($res[1],$path);
	else
		errormess($res[1],$path);
}


/**
 * Displays a specific build link for application
 */ 
function link_to_main_page($path, $return_text)
{
	global $module;

	if (isset($_SESSION["main"]))
		$link = $_SESSION["main"];
	else
		$link = "main.php";
	$link .=  "?module=".$module;

	if ($path)
		$link .= "&method=".$path;

	print '<a class="information" href="'.$link.'">'.$return_text.'</a>';
}

/**
 * Escape the HTTP Request variables
 */ 
function escape_page_params()
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	foreach ($_POST as $param=>$value) {
		if (is_array($value)) {
			foreach ($value as $name=>$val) {
				$val = htmlspecialchars_decode($val, ENT_COMPAT);
				$_POST[$param][$name] = escape_page_param($val);
			}
		} else {
			$value = htmlspecialchars_decode($value, ENT_COMPAT);
			$_POST[$param] = escape_page_param($value);
		}
	}
	
	foreach ($_GET as $param=>$value) {
		$_GET[$param] = escape_page_param($value);
	}
	foreach ($_REQUEST as $param=>$value) {
		$_REQUEST[$param] = escape_page_param($value);
	}
}

/**
 * Convert all applicable characters to HTML entities for a value or an array of values
 * @param $value String / Array
 * @return the modified $value
 */ 
function escape_page_param($value)
{
	global $htmlentities_onall;
	global $use_urldecode;
	
	Debug::func_start(__FUNCTION__,func_get_args(),"paranoid");
	Debug::xdebug("paranoid","Global in ".__FUNCTION__.": htmlentities_onall=$htmlentities_onall,use_urldecode=$use_urldecode");
	
	if (!isset($htmlentities_onall))
		$htmlentities_onall = true;
	if (!isset($use_urldecode))
		$use_urldecode = false;
	
	if ($htmlentities_onall) {
		if (!is_array($value))
			$value = htmlentities($value);
		else {
			foreach ($value as $index=>$val)
				$value[$index] = htmlentities($val);
		}
	}
	
	if ($use_urldecode) {
		if (!is_array($value))
			$value = urldecode($value);
		else {
			foreach ($value as $index=>$val)
				$value[$index] = urldecode($val);
		}
	}
	
	return $value;
}

/**
 * Return the $_GET OR $_POST value of a given parameter
 * @param  $param String 
 * @return the value of the $param set in $_GET or $_POST
 * or NULL if is not set or if is a specific sql abreviation used in queries
 */ 
function getparam($param,$escape = true)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"paranoid");
	$ret = NULL;
	if (isset($_POST[$param]))
		$ret = $_POST[$param];
	else if (isset($_GET[$param]))
		$ret = $_GET[$param];
	else
		return NULL;
	if (is_array($ret)) {
		foreach($ret as $index => $value) 
			$ret[$index] = escape_sql_param($ret[$index]);
		return $ret;
	}
	$ret = escape_sql_param($ret);
	return $ret;
}

/**
 * Return NULL if the value of a given param is specific to SQL abreviations
 * or the value of the param
 */ 
function escape_sql_param($ret)
{
	if (substr($ret,0,6) == "__sql_")
		$ret = NULL; 
	if ($ret == "__empty")
		$ret = NULL;
	if ($ret == "__non_empty" || $ret == "__not_empty")
		$ret = NULL;
	if (substr($ret,0,6) == "__LIKE")
		$ret = NULL;
	if (substr($ret,0,10) == "__NOT LIKE")
		$ret = NULL;
	return $ret;
}

/**
 * Returns the new string with "_" where were spaces.
 * @param $value String
 */ 
function killspaces($value)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	return str_replace(' ','_',$value);
}

/**
 * Verifies if a string is numeric.
 * @param $num String the number to be checked
 * @param $very_big Bool if true verifies if every digit is numeric 
 * @return NULL if given string is not numeric.
 */ 
function Numerify($num, $very_big = false)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	if ($num == '0') 
		$num = '0';
	if ($very_big) {
		for($i=0; $i<strlen($num); $i++) {
			if(!is_numeric($num[$i]))
				return "NULL";
		}
	} else {
		if (!is_numeric($num) && strlen($num)) 
			$num = "NULL";
	}
	return $num;
}

/**
 * Build a full date string from parts
 * @return false on failure, true on empty
 */ 
function dateCheck($year,$month,$day,$hour,$end)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	if ("$year$month$day" == "") {
		if ($hour == "")
			return true;
		if (($hour<0) || ($hour>23))
			return false;
		$hour = sprintf(" %02u:%02u:%02u",$hour,$end,$end);
		return gmdate("Y-m-d") . $hour;
	}
	if (!($year && $month && $day))
		return false;
	if ($hour == "")
		$hour = $end ? 23 : 0;
	if (!(is_numeric($year) && is_numeric($month) && is_numeric($day) && is_numeric($hour)))
		return false;
	if (($year<2000) || ($month<1) || ($month>12) || ($day<1) || ($day>31) || ($hour<0) || ($hour>23))
		return false;
	return sprintf("%04u-%02u-%02u %02u:%02u:%02u",$year,$month,$day,$hour,$end,$end);
}

/**
 * Builds link from the parameters from the current REQUEST
 * @param $exclude_params Array. Parameters to be excluded from built link
 * @param $additional_url_elements Array. Parameters required into the built link
 * @return String. The link from the current $_REQUEST
 */
function build_link_request($exclude_params=array(), $additional_url_elements=array())
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $module, $method, $action;

	$link = (isset($_SESSION["main"]) && strlen($_SESSION["main"])) ? $_SESSION["main"] : "main.php";
	$link .= "?";
	foreach ($_REQUEST as $param=>$value) {

		if ($param == "page" || 
			$param == "PHPSESSID" ||
			($param == "action" && $action) ||
			($param == "method" && $method) || 
			($param == "module" && $module) ||
			in_array($param,$exclude_params) || 
			(!is_array($value) && !strlen($value))
		)
		continue;

		if (substr($link,-1) != "?")
			$link .= "&";

		if (count($additional_url_elements)) {
			foreach ($additional_url_elements as $k=>$element_name)
				if ($element_name == $param)
					$link .= "$param=".urlencode($value);
		} else {
			if (!is_array($value)) 
				$link .= "$param=".urlencode($value);
			else 
				foreach ($value as $arr_val) 
					$link .= "$param"."[]=".$arr_val;
		}
	}

	if (substr($link,-1) != "?")
		$link .= "&";
	if ($module)
		$link .= "module=$module";
	if ($method)
		$link .= "&method=$method";
	if ($action) {
		$call = get_default_function();
		if (function_exists($call))
			$link .= "&action=$action";
	}
	return $link;
}

/**
 * Displays number on page that are links which will
 * make a reload of page with a new limit request
 * @param $nrs Array contains the number of items to be displayed
 */ 
function items_on_page($nrs = array(20,50,100), $additional_url_elements=null)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $limit;

	if (!$nrs)
		$nrs = array(20,50,100);

	if ($additional_url_elements)
		$additional_url_elements = array_merge($additional_url_elements, array("total"));
	$link = build_link_request(array("limit"), $additional_url_elements);

	print "<div class=\"items_on_page\">";
	for($i=0; $i<count($nrs); $i++)
	{
		$option = $link."&limit=".$nrs[$i];
		if ($i>0)
			print '|';
		print '&nbsp;<a class="pagelink';
		if ($nrs[$i]==$limit)
			print " selected_pagelink";
		print '" href="'.$option.'">'.$nrs[$i].'</a>&nbsp;';
	}
	print "</div>";
}

/**
 * Builds and prints pagination links. Ex: 1 2 3 >| or |< 1 2 3 4 5. Takes into account the total number of objects and limit of items to display on page
 * @param $total Integer. Total number of entities
 * @param $additional_url_elements Array. Additional elements to add in links beside default ones(module, method, page, total)
 * Ex: array("status")
 */ 
function pages($total = NULL, $additional_url_elements=array())
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $limit, $page, $module, $method, $action;
	
	if (!$limit)
		$limit = 20;

	$link = isset($_SESSION["main"]) ? $_SESSION["main"] : "main.php";
	$link .= "?";
	$slink = $link;
	$page = 0;
	if (isset($_REQUEST["page"]))
		$page = $_REQUEST["page"];
	if (isset($_REQUEST["total"]))
		$total = $_REQUEST["total"];

	if (!count($additional_url_elements))
		$additional_url_elements = array("total");
	elseif (!in_array("total", $additional_url_elements))
		$additional_url_elements[] = "total";

	$link = build_link_request(array("limit"), $additional_url_elements);

	if (!$total)
		$total = 0;
	if ($total < $limit)
		return;

	$pages = floor($total/$limit);
	print '<center>';
	print '<div class="pages">';
	if ($page != 0) {
		/* jump to first page */
		print '<a class="pagelink" href="'.$link.'&page=0">|<</a>&nbsp;&nbsp;';

		/* jump back 5 pages */
		$prev5 = $page - 5*$limit;
		if ($prev5>0)
			print '<a class="pagelink" href="'.$link.'&page='.$prev5.'"><<</a>&nbsp;&nbsp;';

		/* jump to previous page */
		/*$prev_page = $page - $limit;
		print '<a class="pagelink" href="'.$link.'&page='.$prev_page.'"><</a>&nbsp;&nbsp;';*/

		$diff = floor(($total - ($page + $limit * 2))/$limit) * $limit;
		$sp = $page - $limit * 2;
		if ($diff < 0)
			$sp = $sp - abs($diff);
		while($sp<0)
			$sp += $limit;
		while($sp<$page) {
			$pg_nr = floor($sp/$limit) + 1;
			print '<a class="pagelink" href="'.$link.'&page='.$sp.'">'.$pg_nr.'</a>&nbsp;&nbsp;';
			$sp += $limit;
		}
	}
	$pg_nr = floor($page/$limit)+1;
	print '<font class="pagelink selected_pagelink" href="#">'.$pg_nr.'</font>&nbsp;&nbsp;';
	if (($page+$limit) < $total) {
		if($pg_nr >= 3)
			$stop_at = $pg_nr + 2;
		else
			$stop_at = $pg_nr + 5 - (floor($page/$limit)+1);

		$next_page = $page + $limit;
		while($next_page < $total && $pg_nr < $stop_at)	{
			$pg_nr++;
			print '<a class="pagelink" href="'.$link.'&page='.$next_page.'">'.$pg_nr.'</a>&nbsp;&nbsp;';
			$next_page += $limit;
		}

		/* jump to next page */
		/*$next_page = $page + $limit;
		if($next_page<$total)
			print '<a class="pagelink" href="'.$link.'&page='.$next_page.'">></a>&nbsp;&nbsp;';*/

		$next5 = $page + $limit*5;
		$last_page = floor($total/$limit) * $limit;
		if ($limit==1)
			$last_page = $total - 1;
		elseif (floor(($total/$limit))==$total/$limit)
			$last_page = floor(($total-1)/$limit) * $limit;

		/* jump 5 pages */
		if ($next5 < $last_page)
			print '<a class="pagelink" href="'.$link.'&page='.$next5.'">>></a>&nbsp;&nbsp;';

		/* jump to last page */
		print '<a class="pagelink" href="'.$link.'&page='.$last_page.'">>|</a>&nbsp;&nbsp;';
	}
	print '</div>';
	print '</center>';
}

/**
 * Create links used for navigation between pages (previous / next)
 */ 
function navbuttons($params=array(),$class = "llink")
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $module, $method, $page;

	$step = '';
	$link="main.php?module=$module&method=$method&";
	foreach($params as $key => $value) {
		if ($key=="page" || $key=="tot")
			continue;
		$link="$link$key=$value&";
		if ($key == "step")
			$step = $value;
	}
	$total = $params["tot"];
	
	if (!$step || $step == '')
		$step = 10;
?>
	<center>
	<table border="0" cellspacing="0" cellpadding="0">
		<tr>
			<td class="navbuttons">
<?php 
				$vl = $page-$step;
				if ($vl >= 0) { ?>
					<font size="-1"><a class="<?php print $class;?>" href="<?php print ("$link"."page"."=$vl");?>">Previous</a>&nbsp;&nbsp;</font>
<?php 
				} 
?>
			</td>
			<td class="navbuttons">
				<font size="-3">
				<?php
				$r = $page/$step+1;
 				print ("$r");
				?>
				</font>
			</td>
			<td class="navbuttons">
			    <?php
			    $vl = $page+$step;
			    if ($vl < $total) { ?>
				&nbsp;&nbsp;<font size="-1"><a class="<?php print $class;?>" href="<?php print ("$link"."page"."=$vl");?>">Next</a> </font><?php
				} ?>
			</td>
		</tr>
	</table>
	</center>
<?php
}

/**
 * Validates an email address
 * @param $mail String contains the email address string
 * $return true if valid email and false if string is not in email pattern 
 */ 
function check_valid_mail($mail)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	$pattern = '/^([_a-z0-9-]+)(\.[_a-z0-9-]+)*@([a-z0-9-]+)(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/i';
	return preg_match($pattern,$mail);
}

/**
 * Prints hidden type inputs used in page: module, method, action and additional parameters if set
 * @param $action String contains the name of action in the page
 * @param $additional Array contains the parameters and their values to be set as input hidden 
 * @param $empty_page_param Bool. If true it will set 'method' and 'module' hidden fields to existing value if they don't appear in $additional. Defaults to false
 */ 
function addHidden($action=NULL, $additional = array(), $empty_page_params=false, $skip_params=array())
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $method,$module;

	if (($method || $empty_page_params) && !isset($additional["method"]))
		print "<input type=\"hidden\" name=\"method\" id=\"method\" value=".html_quotes_escape($method)." />\n";

	if (is_array($module) && !isset($additional["module"]))
		print "<input type=\"hidden\" name=\"module\" id=\"module\" value=\"$module[0]\" />\n";
	elseif (($module || $empty_page_params) && !isset($additional["module"]))
		print "<input type=\"hidden\" name=\"module\" id=\"module\" value=\"$module\" />\n";

	print "<input type=\"hidden\" name=\"action\" id=\"action\" value=\"$action\" />\n";

	if (count($additional))
		foreach($additional as $key=>$value) 
			print '<input type="hidden" id="' . $key . '" name="' . $key . '" value=' . html_quotes_escape($value) . '>';

	if (isset($_SESSION["previous_page"])) {
		foreach ($_SESSION["previous_page"] as $param=>$value)
			if (!isset($additional[$param]) && $param!="module" && $param!="method" && $param!="action" && !in_array($param,$skip_params))
				print '<input type="hidden" id="'.$param.'" name="' . $param . '" value=' . html_quotes_escape ($value) . '>';
	}
}

/**
 * Creates a form for editing an object
 * @param $object Object that will be edited or NULL if fields don't belong to an object
 * @param $fields Array of type field_name=>field_formats
 * Ex: $fields =  array(
		"username"=>array("display"=>"fixed", "compulsory"=>true), 
		// if index 0 in the array is not set then this field will correspond to variable username of @ref $object
		// the field will be marked with a *(compulsory)
		"description"=>array("display"=>"textarea", "comment"=>"short description"), 
		// "comment" is used for inserting a comment under the html element 
		"password"=>array("display"=>"password", "compulsory"=>"yes"), 
		"birthday"=>array("date", "display"=>"include_date"), 
		// will call function include_date
		"category"=>array($categories, "display"=>"select") 
		// $categories is an array like 
		// $categories = array(array("category_id"=>"4", "category"=>"Nature"), array("category_id"=>"5", "category"=>"Movies")); when select category 'Nature' $_POST["category"] will be 4
		// or $categories = array("Nature", "Movies");
		"sex"=>array($sex, "display"=>"radio") 
		// $sex = array("male","female","don't want to answer");
		"name" => array ("display"=>"text","error_icon"=>true)
		//adds an error icon to that field, default is set to "display:none"
	); 
 * instead of "compulsory", "requited" can be also used
 * possible values for "display" are "textarea", "password", "fileselect", "text", "select", "radio", "radios", "checkbox", "fixed"
 * If not specified display is "text"
 * If the field corresponds to a bool field in the object given display is ignored and display is set to "checkbox"
 * @param $title Text representing the title of the form
 * @param $submit Text representing the value of the submit button or Array of values that will appear as more submit buttons
 * @param $compulsory_notice Bool true for using default notice, Text representing a notice that will be printed under the form if other notice is desired or NULL or false for no notice
 * @param $no_reset When set to true the reset button won't be displayed, Default value is false
 * @param $css Name of the css to be used when generating the elements. Default value is 'edit'
 * @param $form_identifier Text. Used to make the current fields unique(Used when this function is called more than once inside the same form with fields that can have the same name when being displayed)
 * @param $td_width Array or by default NULL. If Array("left"=>$value_left, "right"=>$value_right), force the widths to the ones provided. $value_left could be 20px or 20%.
 * @param $hide_advanced Bool default false. When true advanced fields will be always hidden when displaying form
 */
function editObject($object, $fields, $title, $submit="Submit", $compulsory_notice=NULL, $no_reset=false, $css=NULL, $form_identifier='', $td_width=NULL, $hide_advanced=false)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	if(!$css)
		$css = "edit";

	print '<table class="'.$css.'" cellspacing="0" cellpadding="0">';
	if($title) {
		print '<tr class="'.$css.'">';
		print '<th class="'.$css.'" colspan="2">'.$title.'</th>';
		print '</tr>';
	}

	$show_advanced = false;
	$have_advanced = false;

	$custom_submit = array();

	//find if there are any fields marked as advanced that have a value(if so then all advanced fields should be displayed)
	foreach($fields as $field_name=>$field_format)
	{
		if(!isset($field_format["advanced"]))
			continue;
		if($field_format["advanced"] != true)
			continue;
		$have_advanced = true;
		if($object)
			$value = (!is_array($field_name) && isset($object->{$field_name})) ? $object->{$field_name} : NULL;
		else
			$value = NULL;
		if(isset($field_format["value"]))
			$value = $field_format["value"];
		if (!$object || !is_object($object))
			break;
		$variable = $object->variable($field_name);
		if((!$variable && $value && !$hide_advanced))
		{
			$show_advanced = true;
			break;
		}
		if(!$variable)
			continue;
		if (($value && $variable->_type!="bool" && !$hide_advanced) || ($variable->_type=="bool" && bool_value($value) && !$hide_advanced))
		{
			$show_advanced = true;
			break;
		}
	}

	//if found errors in advanced fields, display the fields
	foreach($fields as $field_name=>$field_format) {
		if(!isset($field_format["advanced"]))
			continue;
		if (isset($field_format["error"]) && $field_format["error"]===true) {
			$show_advanced = true;
			break;
		}
	}

	foreach($fields as $field_name=>$field_format) {
		if (!isset($field_format["display"]) || $field_format["display"]!="custom_submit")
			display_pair($field_name, $field_format, $object, $form_identifier, $css, $show_advanced, $td_width);
		else
			$custom_submit[$field_name] = $field_format;
	}
	
	
	if (is_file("images/advanced.png"))
		$img_extension = "png";
	else
		$img_extension = "jpg";
	if($have_advanced && !$compulsory_notice)
	{
		print '<tr class="'.$css.'">';
		print '<td class="'.$css.' left_td advanced">&nbsp;</th>';
		print '<td class="'.$css.' left_right advanced"><img class="form_advanced" id="'.$form_identifier.'xadvanced"';
		if(!$show_advanced)
			print " src=\"images/advanced.$img_extension\" title=\"Show advanced fields\"";
		else
			print " src=\"images/basic.$img_extension\" title=\"Hide advanced fields\"";
		print ' onClick="advanced(\''.$form_identifier.'\');"/></th></tr>';
	}
	if($compulsory_notice && $compulsory_notice !== true)
	{
		if($have_advanced) {
		print '<tr class="'.$css.'">';
		print '<td class="'.$css.' left_td" colspan="2">';
		print '<img class="advanced" id="'.$form_identifier.'advanced" ';
		if(!$show_advanced)
			print "src=\"images/advanced.$img_extension\" title=\"Show advanced fields\"";
		else
			print "src=\"images/basic.$img_extension\" title=\"Hide advanced fields\"";
		print ' onClick="advanced(\''.$form_identifier.'\');"/>'.$compulsory_notice.'</td>';
		print '</tr>';
		}
	}elseif($compulsory_notice === true){
		print '<tr class="'.$css.'">';
		print '<td class="'.$css.' left_td" colspan="2">';
		if($have_advanced) {
		print '<img id="'.$form_identifier.'xadvanced"';
		if(!$show_advanced)
			print " class=\"advanced\" src=\"images/advanced.$img_extension\" title=\"Show advanced fields\"";
		else
			print " class=\"advanced\" src=\"images/basic.$img_extension\" title=\"Hide advanced fields\"";
		print ' onClick="advanced(\''.$form_identifier.'\');"/>';
		}
		print 'Fields marked with <font class="compulsory">*</font> are required.</td>';
		print '</tr>';
	}

	if(count($custom_submit))
		foreach($custom_submit as $field_name=>$field_format)
			display_pair($field_name, $field_format, $object, $form_identifier, $css, $show_advanced, $td_width);

	if($submit != "no" && $submit != "no_submit")
	{
		print '<tr class="'.$css.'">';
		print '<td class="'.$css.' trailer" colspan="2">';
		if(is_array($submit))
		{
			for($i=0; $i<count($submit); $i++)
			{
				print '&nbsp;&nbsp;';
				print '<input class="'.$css.'" type="submit" name="'.$submit[$i].'" value='.html_quotes_escape($submit[$i]).'/>';
			}
		}else
			print '<input class="'.$css.'" type="submit" name="'.$submit.'" value='.html_quotes_escape($submit).'/>';
		if(!$no_reset) {
			print '&nbsp;&nbsp;<input class="'.$css.'" type="reset" value="Reset"/>';
			$cancel_but = cancel_button($css);
			if ($cancel_but)
				print "&nbsp;&nbsp;$cancel_but";
		}
		print '</td>';
		print '</tr>';
	}
	print '</table>';
}

/** 
 * Creates an input cancel button with build onclick link
 * to return to the previous page
 */ 
function cancel_button($css="", $name="Cancel")
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$res = null;
	if (isset($_SESSION["previous_page"])) {
		$link = $_SESSION["main"]."?";
		foreach ($_SESSION["previous_page"] as $param=>$value) {
			if (is_array($value) || is_object ($value))
				continue;
			$link.= "$param=".urlencode($value)."&";
		}
		$res = '<input class="'.$css.'" type="button" value="'.$name.'" onClick="location.href=\''.$link.'\'"/>';
	}
	return $res;
}

/**
 * Returns a string with the link build from previous page data session variable
 */ 
function cancel_params()
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$link = "";
	foreach ($_SESSION["previous_page"] as $param=>$value)
		$link.= "$param=".urlencode($value)."&";
	return $link;
}

function html_quotes_escape($str)
{
	return '"'.htmlspecialchars($str, ENT_COMPAT).'"';
}

/**
 * Builds the HTML data for FORM
 */ 
function display_pair($field_name, $field_format, $object, $form_identifier, $css, $show_advanced, $td_width, $category_id=null)
{
	global $allow_code_comment, $use_comments_docs, $method, $add_selected_to_dropdown_if_missing;
	
	//Set this variable to true in defaults.php to add error icon for each field
	global $use_error_icon;
	
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	if (!isset($allow_code_comment))
		$allow_code_comment = true;
	if (!isset($use_comments_docs))
		$use_comments_docs = false;
	if (!isset($use_error_icon))
		$use_error_icon = false;

	$q_mark = false;
	if (isset($field_format["advanced"]))
		$have_advanced = true;

	if (isset($field_format["triggered_by"]))
		$needs_trigger = true;

	if ($object) {
		if (is_array($object))
			$value = (!is_array($field_name) && isset($object[$field_name])) ? $object[$field_name] : NULL;
		elseif (is_object($object))
			$value = (!is_array($field_name) && isset($object->{$field_name})) ? $object->{$field_name} : NULL;
	} else
		$value = NULL;
	if (isset($field_format["value"]))
		$value = $field_format["value"];

	if (!is_array($value) && !strlen($value) && isset($field_format["cb_for_value"]) && isset($field_format["cb_for_value"]["name"]) && is_callable($field_format["cb_for_value"]["name"])) {
		if (count($field_format["cb_for_value"])==2)
			$value = call_user_func_array($field_format["cb_for_value"]["name"],$field_format["cb_for_value"]["params"]);
		else
			$value = call_user_func($field_format["cb_for_value"]["name"]);
	}

	print '<tr id="tr_'.$form_identifier.$field_name.'"';
//		if($needs_trigger == true)	
//			print 'name="'.$form_identifier.$field_name.'triggered'.$field_format["triggered_by"].'"';

	if (isset($field_format["error"]) && $field_format["error"]===true)
		$css .= " error_field";
	print ' class="'.$css.'"';
	if(isset($field_format["advanced"]))
	{
		if(!$show_advanced) {
			print ' style="display:none;" advanced="true" ';
			if (isset($field_format["triggered_by"]))
				print " trigger=\"true\" ";

		} elseif(isset($field_format["triggered_by"])){
			if($needs_trigger)
				print ' style="display:none;" trigger=\"true\" ';
			else
				print ' style="display:table-row;" trigger=\"true\" ';
		} else
			print ' style="display:table-row;"';
	} elseif (isset($field_format["triggered_by"])) {
		if ($needs_trigger)
			print ' style="display:none;" trigger=\"true\" ';
		else
			print ' style="display:table-row;" trigger=\"true\" ';
	}
	print '>';

	// if $var_name is an array we won't use it
	$var_name = (isset($field_format[0])) ? $field_format[0] : $field_name;
	$display = (isset($field_format["display"])) ? $field_format["display"] : "text";

	if ($object && !is_array($object)) {
		$variable = (!is_array($var_name)) ? $object->variable($var_name) : NULL;
		if ($variable) {
			if ($variable->_type == "bool" && $display!="text")
				$display = "checkbox";
		}
	}

	if ($display=="message" || $display=="objtitle" || $display=="custom_submit" || $display=="custom_field") {
		$custom_field_css = ($display=="custom_field") ? "custom_field" : 'double_column';

		print '<td class="'.$css.' '.$custom_field_css.'" colspan="2">';
		if ($display=="objtitle")
			print "<div class='objtitle'>$value</div>";
		else
			print $value;
		print '</td>';
		print '</tr>';
		return;
	}

	if ($display=="hidden") {
		print '<input class="'.$css.'" type="'.$display.'" name="'.$form_identifier.$field_name.'" id="'.$form_identifier.$field_name.'"';		
		print " value=".html_quotes_escape($value);
		print " />";
		return;
	}

	print '<td class="'.$css.' left_td ';
	if (isset($field_format["custom_css_left"]))
		print $field_format["custom_css_left"];
	print '"';
	if (isset($td_width["left"]))
		print ' style="width:'.$td_width["left"].'"';
	print '>';
	if (!isset($field_format["column_name"]))
		print ucfirst(str_replace("_","&nbsp;",$field_name));
	else
		print ucfirst($field_format["column_name"]);
	if (isset($field_format["required"]))
		$field_format["compulsory"] = $field_format["required"];
	if (isset($field_format["object_required"]))
		$field_format["compulsory"] = $field_format["object_required"];
	if (isset($field_format["compulsory"]))
		if($field_format["compulsory"]===true || $field_format["compulsory"]=="yes" || bool_value($field_format["compulsory"]) || $field_format["compulsory"]=="true")
			print '<font class="compulsory">*</font>';
	
	// check if the error icon is set in fields
	if (isset($field_format["error_icon"])) {
		if($field_format["error_icon"]===true || $field_format["error_icon"]=="yes" || bool_value($field_format["error_icon"]) || $field_format["error_icon"]=="true")
			print "<img src='images/error.png' class='field_error' id='err_$field_name' style='display:none;'>";
	} else {
		if ($use_error_icon)
			print "<img src='images/error.png' class='field_error' id='err_$field_name' style='display:none;'>";
	}
	print '&nbsp;</td>';
	print '<td class="'.$css.' right_td ';
	if (isset($field_format["custom_css_right"]))
		print $field_format["custom_css_right"];
	print '"';
	if (isset($td_width["right"]))
		print ' style="width:'.$td_width["right"].'"';
	print '>';

	$field_comment = comment_field($field_format, $form_identifier, $field_name, $category_id, $display);

	switch($display) {
		case "textarea":
			$textarea_cols = 20;
			$textarea_rows = 5;
			if (isset($field_format["textarea_cols"]))
				$textarea_cols = $field_format["textarea_cols"];
			if (isset($field_format["textarea_rows"]))
				$textarea_rows = $field_format["textarea_rows"];
			print '<textarea class="'.$css.'" id="'.$form_identifier.$field_name.'" name="'.$form_identifier.$field_name.'" cols="'.$textarea_cols.'" rows="'.$textarea_rows.'" ';
			if (isset($field_format["javascript"]))
				print $field_format["javascript"];
			print '>';
			print $value;
			print '</textarea>'.$field_comment;
			break;
		case "select":
		case "mul_select":
		case "select_without_non_selected":

			if ($add_selected_to_dropdown_if_missing)
				$is_selected = false;

			print '<select class="'.$css.'" id="'.$form_identifier.$field_name.'" ';
			if (isset($field_format["javascript"]))
				print $field_format["javascript"];
			if ($display == "mul_select")
				print ' multiple="multiple" size="5" name="'.$form_identifier.$field_name.'[]"';
			else
				print ' name="'.$form_identifier.$field_name.'"';
			print '>';
			if ($display != "mul_select" && $display != "select_without_non_selected")
				print '<option value="">Not selected</option>';

			// PREVIOUS implementation when only 0 key could be used for dropdown options
			// $options = (is_array($var_name)) ? $var_name : array();

			if ($display != "mul_select") {
				// try gettting it from value
				if ($value && is_array($value))
					$options = $value;
				elseif (is_array($var_name))
					$options = $var_name;
				else
					$options = array();
			} else {
				// try gettting it from value
				// !! DEFINE "selected" even if selected is not defined
				if ($value && is_array($value) && array_key_exists("selected",$value))
					$options = $value;
				elseif (is_array($var_name))
					$options = $var_name;
				else
					$options = array();
			}

			if (isset($field_format["selected"]))
				$selected = $field_format["selected"];
			elseif (isset($options["selected"]))
				$selected = $options["selected"];
			elseif (isset($options["SELECTED"]))
				$selected = $options["SELECTED"];
			else
				$selected = '';

			foreach ($options as $var=>$opt) {
				if ($var === "selected" || $var === "SELECTED")
					continue;
				$css = (is_array($opt) && isset($opt["css"])) ? 'class="'.$opt["css"].'"' : "";

				// $opt = array("field"=>..., "field_id"=>...) 
				if (is_array($opt) && isset($opt[$field_name.'_id'])) {
					$optval = $field_name.'_id';
					$name = $field_name;

					// for extreme debug purposes  you might want to print val/type of each value and the selected value and data type
					//$printed = trim($opt[$name]).",".$selected.",". gettype($selected).",".$opt[$optval].",".gettype($opt[$optval]);
					$printed = trim($opt[$name]);
					if (substr($printed,0,4) == "<img") {
						$expl = explode(">",$printed);
						$printed = $expl[1];
						$jquery_title = " title=\"".str_replace("<img","",$expl[0])."\"";
					} else
						$jquery_title = '';

					if ( (string)$opt[$optval] === "$selected" || (is_array($selected) && in_array($opt[$optval],$selected))) {
						print '<option';
						print " value=".html_quotes_escape($opt[$optval]);
						print ' '.$css.' SELECTED ';
						if($opt[$optval] === "__disabled")
							print ' disabled="disabled"';
						print $jquery_title;
						print '>' . $printed . '</option>';
						if ($add_selected_to_dropdown_if_missing)
							$is_selected = true;
					} else {
						print '<option';
						print " value=".html_quotes_escape($opt[$optval]);
						print ' '.$css;
						if($opt[$optval] === "__disabled")
							print ' disabled="disabled"';
						print $jquery_title;
						print '>' . $printed . '</option>';
					}
				} else {
					if (($opt == $selected && strlen($opt)==strlen($selected)) ||  (is_array($selected) && in_array($opt,$selected))) {
						print '<option '.$css.' SELECTED >' . $opt . '</option>';
						if ($add_selected_to_dropdown_if_missing)
							$is_selected = true;
					} else
						print '<option '.$css.'>' . $opt . '</option>';
				}
			}

			if	($add_selected_to_dropdown_if_missing && strlen($selected) && !$is_selected)
				print '<option '.$css.' SELECTED >' . $selected . '</option>';

			print '</select>'.$field_comment;

			if(isset($field_format["add_custom"]))
				print $field_format["add_custom"];

			break;
		case "radios":
		case "radio":
			$options = (is_array($var_name)) ? $var_name : array();
			if (isset($options["selected"]))
				$selected = $options["selected"];
			elseif (isset($options["SELECTED"]))
				$selected = $options["SELECTED"];
			else
				$selected = "";
			foreach ($options as $var=>$opt) {
				if ($var === "selected" || $var === "SELECTED")
					continue;
				if (count($opt) == 2) {
					$optval = $field_name.'_id';
					$name = $field_name;
					$value = $opt[$optval];
					$name = $opt[$name];
				} else {
					$value = $opt;
					$name = $opt;
				}
				print '<input class="'.$css.'" type="radio" name="'.$form_identifier.$field_name.'" id="'.$form_identifier.$field_name.'"';
				print " value=".html_quotes_escape($value);
				if ($value == $selected)
					print ' CHECKED ';
				if (isset($field_format["javascript"]))
					print $field_format["javascript"];
				print '>' . $name . '&nbsp;&nbsp;';
			}
			print $field_comment;
			break;
		case "checkbox":
		case "checkbox-readonly":
			print '<input class="'.$css.'" type="checkbox" name="'.$form_identifier.$field_name.'" id="'.$form_identifier.$field_name.'"';
			if (bool_value($value) || $value=="on")
				print " CHECKED ";
			if (isset($field_format["javascript"]))
				print $field_format["javascript"];
			if ($display=="checkbox-readonly")
				print " disabled=''";
			print '/>'.$field_comment;
			break;
		case "text":
		case "password":
		case "file":
		case "text-nonedit":
			print '<input  class="'.$css.'" type="'.($display=="text-nonedit"?"text":$display).'" name="'.$form_identifier.$field_name.'" id="'.$form_identifier.$field_name.'"';
			if ($display != "file" && $display != "password") {
				if (!is_array($value)) {
					print " value=".html_quotes_escape($value);
				}
				else {
					if (isset($field_format["selected"]))
						$selected = $field_format["selected"];
					elseif (isset($value["selected"]))
						$selected = $value["selected"];
					elseif (isset($value["SELECTED"]))
						$selected = $value["SELECTED"];
					else
						$selected = '';				
					print " value=".html_quotes_escape($selected);
				}
			}
			if (isset($field_format["javascript"]))
				print $field_format["javascript"];
			if ($display == "text-nonedit")
				print " readonly=''";
			if (isset($field_format["autocomplete"]))
				print " autocomplete=\"".$field_format["autocomplete"]."\"";

			print '>'.$field_comment;

			if ($display == 'file' && isset($field_format["file_example"]) && $field_format["file_example"] != "__no_example")
				print '<br/><br/>Example: <a class="'.$css.'" href="example/'.$field_format["file_example"].'">'.$field_format["file_example"].'</a><br/><br/>';
			if ($display == 'file' && !isset($field_format["file_example"])) 
				Debug::trigger_report('critical', "For input type file a file example must be given as parameter.");
			break;
		case "fixed":
			if (strlen($value))
				print $value;
			else
				print "&nbsp;";
			print $field_comment;
			break;
		default:
			// make sure the function that displays the advanced field is included
			if (isset($field_format["advanced"]))
				print '<input type="hidden" name="'.$form_identifier.$field_name.'">';

			if (!is_callable($display))
				Debug::trigger_report('critical', "Callable ".print_r($display,true)." is not implemented.");

			// callback here
			$value = call_user_func_array($display, array($value,$form_identifier.$field_name)); 
			if ($value)
				print $value;
			print $field_comment;
	}

	print '</td>';
	print '</tr>';
}

function comment_field($field_format, $form_identifier, $field_name, $category_id, $display)
{
	global $use_comments_docs, $allow_code_comment, $method;

	$res = "";
	if ($display != "hidden") {
		if (isset($field_format["comment"])) {
			if ($allow_code_comment) {

				$comment = $field_format["comment"];

				if (is_file("images/question.jpg"))
					$res .= '&nbsp;&nbsp;<img class="pointer" src="images/question.jpg" onClick="show_hide_comment(\''.$form_identifier.$field_name.'\');"/>';
				else
					$res .= '&nbsp;&nbsp;<font style="cursor:pointer;" onClick="show_hide_comment(\''.$form_identifier.$field_name.'\');"> ? </font>';

				$res .= '<font class="comment" style="display:none;" id="comment_'.$form_identifier.$field_name.'">'.$comment.'</font>';
			}
		}

		if ($use_comments_docs) {
			if (!$category_id)
				$category_id = str_replace(array("add_", "edit_"), "", $method);

			$comment_id = build_comment_id($field_format, $form_identifier, $field_name, $category_id);

			if (is_file("images/question.jpg"))
				$res .= '&nbsp;&nbsp;<img class="pointer" src="images/question.jpg" onClick="show_docs(\''.$category_id.'\', \''.$comment_id.'\');"/>';
			else
				$res .= '&nbsp;&nbsp;<font style="cursor:pointer;" onClick="show_docs(\''.$category_id.'\',\''.$comment_id.'\');"> ? </font>';
		}
	}
	return $res;
}

function build_comment_id($field_format, $form_identifier, $field_name, $category_id)
{
	if (isset($field_format["comment_id"]))
		return $field_format["comment_id"];

	if (isset($field_format["ref_comment_id"]))
		$field_name = $field_format["ref_comment_id"];

	$pieces = explode("_", $field_name);

	if (ctype_digit(strval($pieces[count($pieces)-1]))) {
		$sign = $fld = "";
		for ($i = 0; $i<count($pieces)-1; $i++) {
			$fld .= $sign.$pieces[$i];
			$sign = "_";
		}
		return $category_id."_".$form_identifier.$fld;
	}
	$last = substr($field_name, -1);
	$penultimate = substr($field_name, -2, 1);
	$fld = $field_name;
	if ($penultimate=="_" && ctype_digit(strval($last)))
		$fld = substr($field_name,0,strlen($field_name)-2);
	elseif ((ctype_digit(strval($penultimate)) && ctype_digit(strval($last))) ||
			$last == "_")
		$fld = substr($field_name,0,strlen($field_name)-1);	

	return $category_id."_".$form_identifier.$fld;
}

/**
 * Find a field value from the results of a PGSQL query
 * Function used in query_to_array()
 */ 
function find_field_value($res, $line, $field)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	for($j=0; $j<pg_num_fields($res); $j++) {
		if (pg_field_name($res,$j) == $field)
			return pg_fetch_result($res,$line,$j);
	}

	return NULL;
}

function tree($array, $func = "copacClick",$class="copac",$title=NULL)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $module,$path;

	$i = 0;
	if (!isset($array[$i]) && count($array))
		while(!isset($array[$i]))
			$i++;
	if (count($array)) {
		$num = count($array[$i]);
		$verify = array();
		for ($j=0;$j<$num;$j++)
			$verify[$j]="";
	}
	$level = 0;
	if (!$title)
		$title = $module;

	print '<div class="'.$class.'">';
	print '<div class="titlu">' . ucfirst($title). '</div>';
	print '<ul class="copac">';
	for ($i=0;$i<count($array);$i++) {
		$j = 0;
		foreach ($array[$i] as $fld => $val) {
	    		if ($val == "") {
				$j++;
				break;
			}
			if ($val == $verify[$j]) {
				$j++;
				continue;
			}
	    		for (;$level>$j;$level--)
				print "</ul>";
	    		if ($j > $level) {
				print "\n<ul class=\"copac_${level}\" id=\"copac_ul_${i}_${level}\" style=\"display:none\">";
				$level++;
			}
			$tip = ($j+1 < $num) ? "disc" : "square";
			print "\n<li class=\"copac_${j} \" id=\"copac_li_${i}_${j}\" type=\"${tip}\"><a class=\"copac\" href=\"#\" onClick=\"${func}(${i},${j},'${fld}'); return false\">$val</a></li>";
			$verify[$j] = $val;
			for ($k=$j+1; $k<$num; $k++)
				$verify[$k] = "";
			$j++;
		}
	}
	for (;$level>0;$level--)
		print "</ul><br/>";
    print "\n</ul></div>";
}

/**
 * Finds a field in the Keys of an array
 * Returns array with the searched field 
 */ 
function in_formats($field, $formats)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	foreach($formats as $key=>$value) {
		if (substr_count($key, $field))
			return array("key"=>$key, "value"=>$value);
	}

	return false;
}

/**
 *  Builds an array from a postgresql query results
 */ 
function query_to_array($res, $formats=array())
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$begginings = array('1_', '2_' ,'3_' , '4_', '5_', '6_', '7_', '8_', '9_', '0_');

	$array = array();
	for($i=0; $i<pg_num_rows($res);$i++) {
		$array[$i] = array();
		for($j=0; $j<pg_num_fields($res); $j++) {
			$nm = pg_field_name($res,$j);
			$value = pg_fetch_result($res,$i,$j);
			if (isset($formats[$nm]) || in_formats($nm, $formats)) {
				$arr = in_formats($nm, $formats);

				$val = $arr["value"];
				$nm = $arr["key"];
				if (in_array(substr($nm,0,2), $begginings))
					$nm = substr($nm,2,strlen($nm));
				$save_nm = $nm;

				if (substr($val,0,9) == "function_") {
					$name = substr($val,9,strlen($val));
					$arr = explode(':',$name);

					if (count($arr)>1) {
						$nm = $arr[1];
						$name = $arr[0];
					}

					if (str_replace(',','',$save_nm) == $save_nm) {
						$value = call_user_func($name,find_field_value($res,$i,$save_nm));
					} else {
						$save_nm = explode(',',$save_nm);
						$params = array();
						for($x=0; $x<count($save_nm); $x++)
							$params[trim($save_nm[$x])] = find_field_value($res, $i, trim($save_nm[$x]));
						$value = call_user_func_array($name,$params);
						$save_nm = implode(":",$save_nm);
					}
				} elseif ($val)
					$nm = $val;
			}
			$array[$i][$nm] = $value;
		}
	}
	return $array;
}

function tableOfObjects_objectnames($objects, $formats, $object_name, $object_actions=array(), $general_actions=array(),$object_actions_names=array())
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	tableOfObjects($objects, $formats, $object_name, $object_actions, $general_actions, NULL, false, "content", array(), $object_actions_names);
}

function tableOfObjects_ord($objects, $formats, $object_name, $object_actions=array(), $general_actions=array(), $order_by_columns=true)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	tableOfObjects($objects, $formats, $object_name, $object_actions, $general_actions, NULL, false, "content", array(),array(),false, $order_by_columns);
}

/**
 * Creates table of objects
 * @param $objects Array with the objects to be displayed
 * @param $formats Array with columns to be displayed in the table
 * Ex: array("username", "function_truncdate:registered_on"=>"date", "function_unifynames:name"=>"first_name,last_name")
 * will display a table having the column names : Username | Registered on | Name
 * "function_truncdate:registered_on"=>"date" means that on each variable named date 
 * for all objects function truncdate will be called and the result will be printed in the table under column Registered on
 * "function_unifynames:name"=>"first_name,last_name" means that under the Name column, for all objects 
 * function unifynames will be called with 2 parameters" the content of variables first_name and last_name from each object 
 * @param $object_name Name of the object to be displayed. It's important only when the number of objects to be printer is 0
 * @param $object_actions Array of $method=>$method_name, $method will be added in the link and $method_name will be printed
 * Ex: array("&method=edit_user"=>"Edit")
 * @param $general_actions Array of $method=>$method_name that will be printed at the end of the table
 * If key '__top' is present in the $general_actions then the position will be top otherwize bottom of the table
 * Ex: array("&method=add_user"=>"Add user") or with callback:
 * array("cb"=>"fun_name") or:
 * array("cb"=>array("name"=>"fun_name", "params"=> array(param1, param2,...)) or:
 * array("left"=>array("cb"=> "func_name"), "right"=>array("cb"=>"func_name")) or: 
 * array("left"=>array("cb"=>array("name"=>"func_name", "params"=>array(param1, param2,...)), "right"=>array("cb"=>"func_name"));
 * Ex: array("right"=>array("&method=add_user"=>"Add user"), "left"=>array(...))
 * @param $base Text representing the name of the page the links from @ref $object_name and @ref $general_actions will be sent
 * Ex: $base = "main.php"
 * If not sent, i will try to see if $_SESSION["main"] was set and create the link. If $_SESSION["main"] was not set then  
 * "main.php" is the default value 
 * @param $insert_checkboxes Bool value. If true then in front of each row a checkbox will be created. The name attribute
 * for it will be "check_".value of the id of the object printed at that row
 * Note!! This parameter is taken into account only if the objects have an id defined
 * @param $css Name of the css to use for this table. Default value is 'content'
 * @param $conditional_css Array ("css_name"=>$conditions) $css is the to be applied on certain rows in the table if the object corresponding to that row complies to the array of $conditions
 * @param $object_actions_names Array
 * @param  $select_all Bool
 * @param $order_by_columns Bool/Array Adds buttons to adds links on the fields from the table header. 
 * Defaults to false
 * @param $add_empty_row Bool
 */
function tableOfObjects($objects, $formats, $object_name, $object_actions=array(), $general_actions=array(), $base = NULL, $insert_checkboxes = false, $css = "content", $conditional_css = array(), $object_actions_names=array(), $select_all = false, $order_by_columns=false, $add_empty_row=false)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"paranoid");

	global $db_true, $db_false, $module, $method;

	if(!$db_true)
		$db_true = "yes";
	if(!$db_false)
		$db_false = "no";

	if(!count($objects)) {
		$plural = get_plural_form($object_name);
		print "<table class=\"$css\"><tr><td style=\"text-align:right;\">";
		plainmessage("There aren't any $plural in the database.");
		print "</td></tr>";
		if(!count($general_actions)) {
			print '</table>';
			return;
		}
	}

	if(!$base) {
		$main = (isset($_SESSION["main"])) ? $_SESSION["main"] : "main.php";
		$base = "$main?module=$module";
	}

	$ths = "";
	print '<table class="'.$css.'" id="'.$css.'_id" cellspacing="0" cellpadding="0">';

	$order_dir = (!getparam("order_dir") || getparam("order_dir")=="asc") ? "desc" : "asc";
	if(count($objects)) {
		$ths .= '<tr class="'.$css.'">';
		$no_columns = 0;

		if($insert_checkboxes) {
			$ths .= '<th class="'.$css.' first_th checkbox">';
			if ($select_all)
				$ths .= '<input type="checkbox" name="select_all" id="select_all" onclick="toggle_column(this);">';
			else
				$ths .= '&nbsp;';
			$ths .= '</th>';
			$no_columns++;
		}

		$request_link = build_link_request(array("order_by","order_dir"));

		// print the name of the columns + add column for each action on object
		foreach($formats as $column_name => $var_name) {
			$exploded = explode(":",$column_name);
			if(count($exploded)>1)
				$name = $exploded[1];
			else {
				$name = $column_name;
				if(substr($column_name, 0, 9) == "function_")
					$name = substr($column_name,9);
				if(is_numeric($column_name))
					$name = $var_name;
			}
			$ucss = ($no_columns == 0) ? "$css first_th" : $css;
			$ths .= '<th class="'.$ucss.'">';
			//$ths .= str_replace("_","&nbsp;",ucfirst($name));
			$column_link = false;
			if ($order_by_columns) {
				if (((is_numeric($column_name) || count(explode(",",$var_name))==1) && ($order_by_columns===true || !isset($order_by_columns[$name]))) || (isset($order_by_columns[$name]) && $order_by_columns[$name]==true)) {

					$ths .= "<a class='thlink' href='".$request_link."&order_by=$name&order_dir=$order_dir'>".str_replace("_","&nbsp;",ucfirst($name))."</a>";
					$column_link = true;
				}
			}
			if (!$column_link)
				$ths .= str_replace("_","&nbsp;",ucfirst($name));
			$ths .= '</th>';
			$no_columns++;
		}
		for($i=0; $i<count($object_actions); $i++) {
			$ths .= '<th class="'.$css.'">';
			if  (!isset($object_actions_names) || !isset($object_actions_names[$i]))
				$ths .= '&nbsp;';
			else
				$ths .= $object_actions_names[$i];
			$ths .= '</th>';
			$no_columns++;
		}
		$ths .= '</tr>';

		$vars = $objects[0]->extendedVariables();
		$class = get_class($objects[0]);
		$id_name = $objects[0]->getIdName();
	} else
		$no_columns = 2;

	if(count($general_actions) && in_array("__top",$general_actions))
		links_general_actions($general_actions, $no_columns, $css, $base, true);

	print $ths;
	for($i=0; $i<count($objects); $i++) {
		$cond_css = '';
		foreach($conditional_css as $css_name=>$conditions) {
			$add_css = true;
			foreach($conditions as $column=>$cond_column) {
				if($objects[$i]->{$column} != $cond_column) {
					$add_css = false;
					break;
				}
			}
			if($add_css)
				$cond_css .= " $css_name ";
		}
		if($i==(count($objects)-1))
			$cond_css.= " pre_end"; 
		print '<tr class="'.$css.'">';
		if($insert_checkboxes && $id_name) {
			print '<td class="'.$css.$cond_css;
			if($i%2 == 0)
				print " evenrow";
			print '">';
			print '<input type="checkbox" name="check_'.$objects[$i]->{$id_name}.'"/>';
			print '</td>';
		}
		$j=0;
		foreach($formats as $column_name => $var_name) {
			print '<td class="'.$css.$cond_css;
			if($i%2 == 0)
				print " evenrow";
			if($j == count($formats)-1 && !count($object_actions))
				print " end_cell";
			print '">';
			$use_vars = explode(",", $var_name);
			array_walk($use_vars, 'trim_value');
			$exploded_col = explode(":", $column_name);
			$column_value = '';

			if(substr($exploded_col[0],0,9) == "function_") {
				$function_name = substr($exploded_col[0],9,strlen($exploded_col[0]));
				if(count($use_vars)) {
					$params = array();
					for($var_nr=0; $var_nr<count($use_vars); $var_nr++)
						if(array_key_exists($use_vars[$var_nr], $vars))
							array_push($params, $objects[$i]->{$use_vars[$var_nr]});
						else
							array_push($params,null);
					$column_value = call_user_func_array($function_name,$params);
				}
			} elseif(isset($objects[$i]->{$var_name})){
				$column_value = $objects[$i]->{$var_name};
				$var = $objects[$i]->variable($use_vars[0]);
				if($var->_type == "bool") {
					if (bool_value($column_value))
						$column_value = $db_true;
					else
						$column_value = $db_false;
				}
			}
			if($column_value !== NULL)
				print $column_value;
			else
				print "&nbsp;";
			print '</td>';
			$j++;
		}
		$link = '';
		foreach($vars as $var_name => $var)
			if ($var_name!="password" && strlen($objects[$i]->{$var_name}) < 50)
				$link .= "&$var_name=".htmlentities(urlencode($objects[$i]->{$var_name}));
		$link_no = 0;
		foreach($object_actions as $methd => $methd_name) {
			print '<td class="'.$css.$cond_css;
			if($i%2 == 0)
				print ' evenrow object_action';
			else
				print ' object_action';
			if ($link_no == count($object_actions)-1)
				print ' end_cell';
			print '">';
		//	if($link_no)
		//		print '&nbsp;&nbsp;';
			print '<a class="'.$css.'" href="'.$base.$methd.$link.'">'.$methd_name.'</a>';
			print '</td>';
			$link_no++;
		}
		print '</tr>';
	}
	if ($add_empty_row) {
		$colspan = count($formats) + count($object_actions);
		if ($insert_checkboxes)
			$colspan += 1;
		print '<tr><td class="last_row" colspan="'.$colspan.'"></td></tr>';
	}
	if(count($general_actions) && !in_array("__top",$general_actions))
		links_general_actions($general_actions, $no_columns, $css, $base);

	print "</table>";
}

/**
 * Get column and direction to order table elements by
 * @param $fields Array. This is the same parameter as $fields from \ref tableOfObjects
 * @param $default String. Default order column
 * @return String. Column and direction to use in SELECT query or NULL if $default was not set
 * Ex: "pieces ASC", "username DESC"
 */
function column_order($fields, $default, $custom_order=array())
{
	$order_by = getparam("order_by");
	$dir = (in_array(getparam("order_dir"),array("asc","desc"))) ? strtoupper(getparam("order_dir")) : "ASC";

	$col = null;
	if (isset($fields[$order_by])) {
		$col = $fields[$order_by];
	} elseif (in_array($order_by,$fields))
		$col = $order_by;
	
	if ($col) {
		if (isset($custom_order[$col])) {
			if ($custom_order[$col]===true)
				return "$col $dir";
			else
				return $custom_order[$col]." ".$dir;
		}

		if (count(explode(",",$col))==1)
			return "$col $dir";
		elseif ($default) {
			if (strtolower(substr($default,-4))!=" asc" && strtolower(substr($default,-4))!="desc")
				return "$default $dir";
			else
				return $default;
		} else
			return null;
	}

	foreach ($fields as $key=>$value) {
		if (substr($key,0,9)=="function_") {
			$key = explode(":",$key);
			$key = $key[1];
		}
		if ($key === $order_by) {
			if (isset($custom_order[$key])) {
				if ($custom_order[$key]===true)
					return "$key $dir";
				else
					return $custom_order[$key]." ".$dir;
			} elseif (count(explode(",",$value))==1)
				return "$value $dir";
			elseif ($default) {
				if (strtolower(substr($default,-4))!=" asc" && strtolower(substr($default,-4))!="desc")
					return "$default $dir";
				else
					return $default;
			} 
			return null;
		}
	}

	if ($default) {
		if (strtolower(substr($default,-4))!=" asc" && strtolower(substr($default,-4))!="desc")
			return "$default $dir";
		else
			return $default;
	}

	return null;
}

/**
 * Builds links or makes callbacks that will contain the general actions in the table of objects
 * @param $general_actions Array 
 * Ex: array("&method=add_user"=>"Add user") or array("left"=> array("&method=add_user"=>"Add user"), "right"=> array())
 * or with callback:
 * array("cb"=>"func_name") or array("cb"=>array("name"=>"func_name", "params"=> array(param1, param2,...)) or:
 * array("left"=>array("cb"=> "func_name"), "right"=>array("cb"=>"func_name")) or: 
 * array("left"=>array("cb"=>array("name"=>"func_name", "params"=>array(param1, param2,...)), "right"=>array("cb"=>"func_name"));
 * @param $no_columns Int the number of the columns. Used to build the colspan of the cell.
 * @param $css Text the css class name
 * @param $base Text. Name of the page the links from @ref $object_name and @ref $general_actions will be sent
 * Ex: $base = "main.php"
 * @param $on_top Bool if true add css class to cell 'starttable', otherwize 'endtable'  
 */ 
function links_general_actions($general_actions, $no_columns, $css, $base, $on_top=false)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	if (!count($general_actions))
		return;

	$pos_css = ($on_top) ? "starttable" : "endtable";

		print '<tr class="'.$css.' endtable">';
		if (isset($general_actions["left"])) {
			$left_actions = $general_actions["left"];
			$columns_left = floor($no_columns/2);
			$no_columns -= $columns_left;
			print '<td class="'.$css.' allleft '.$pos_css.'" colspan="'.$columns_left.'">';
			$link_no = 0;
			if (is_array($left_actions)) {
				foreach($left_actions as $methd => $methd_name)	{
					if ($methd === "cb")
						set_cb($methd_name);
					else {
						if ($link_no)
							print '&nbsp;&nbsp;';
						print '<a class="'.$css.'" href="'.$base.$methd.'">'.$methd_name.'</a>';
						$link_no++;
					}
				} 
			} else
				print $left_actions;
			print '</td>';
			if (isset($general_actions["right"]))
				$general_actions = $general_actions["right"];
			else
				$general_actions = array();
		}
		
		print '<td class="'.$css.' allright '.$pos_css.'" colspan="'.$no_columns.'">';
		$link_no = 0;
		if (!count($general_actions))
			print "&nbsp;";
		foreach($general_actions as $methd=>$methd_name) {
			if ($methd_name == "__top")
				continue;
			if ($methd === "cb") {
				set_cb($methd_name);
			} else {
				if ($link_no)
					print '&nbsp;&nbsp;';
				print '<a class="'.$css.'" href="'.$base.$methd.'">'.$methd_name.'</a>';
				$link_no++;
			}
		}
		print '</td>';
		print '</tr>';
}

/** 
 * Makes the callback for different methods
 * used in  links_general_actions() when a callback is set in $general_actions
 */ 
function set_cb($methd_name)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	if (is_callable($methd_name))
		call_user_func($methd_name);

	elseif (is_array($methd_name) && isset($methd_name["name"]) && isset($methd_name["params"])) {
		if (is_callable($methd_name["name"]))
			call_user_func_array($methd_name["name"], $methd_name["params"]);
		else
			Debug::trigger_report("critical", "The function '". $methd_name["name"] . "' is not implemented.");
	}
	else 
		Debug::trigger_report("critical", "Incorrect 'cb' - callback set in general actions.");
}

/**
 * Returns the plural of an object name
 */ 
function get_plural_form($object_name)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	if (substr($object_name,0,7)=="__keep_") {
		return substr($object_name,7);
	} elseif (class_exists($object_name)) {
		$obj = new $object_name;
		$plural = $obj->getTableName();
	} else {
		if (substr($object_name,-1) == "s")
			$plural = $object_name;
		elseif (substr($object_name, -1) == "y")
			$plural = substr($object_name,0,strlen($object_name)-1)."ies";
		else
			$plural = $object_name."s";
	}
	return $plural;
}

/**
 * Strip whitespace (or other characters) from the beginning and end of a string
 */ 
function trim_value(&$value) 
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$value = trim($value); 
}

/**
  * Creates table of elements
  * @param $array Array with the values of the elements to be displayed.
  * @param $formats Array with columns to be displayed in the table.
  * @param $element_name String. Name of the element to be displayed in the table. 
  * @param $element_actions Array of $method=>$method_name, $method will be added in the link and $method_name will be printed
  * Ex: array("&method=edit_user"=>"Edit")
  * @param $general_actions Array of $method=>$method_name that will be printed at the end of the table
  * Ex: array("&method=add_user"=>"Add user")
  * @param $base Text representing the name of the page the links from @ref $element_name and @ref $general_actions will be sent
  * Ex: $base = "main.php"
  * If not sent, i will try to see if $_SESSION["main"] was set and create the link. If $_SESSION["main"] was not set then  
  * "main.php" is the default value 
  * @param $insert_checkboxes Bool. If true then in front of each row a checkbox will be created. The name attribute
  * for it will be "check_".value of the id of the object printed at that row
  * Note!! This parameter is taken into account only if the objects have an id defined
  * @param $css Name of the css to use for this table. Default value is 'content'
  * @param $conditional_css Array ("css_name"=>$conditions) $css is the to be applied on certain rows in the table if the object corresponding to that row complies to the array of $conditions
  * @param $object_actions_names Array containg the action names for the $element_actions that are displayed in the table header
  * @param $table_id Text the id of table
  * @param $select_all Bool. If true select all checkboxes made when $insert_checkboxes=true. Defaults to false.
  * @param $order_by_columns Bool/Array Adds buttons to add links on the fields from the table header. Defaults to false.
  * @param $build_link_elements Array Contains the name of the elements used to build each row $element_actions.
  */
function table($array, $formats, $element_name, $id_name, $element_actions = array(), $general_actions = array(), $base = NULL, $insert_checkboxes = false, $css = "content", $conditional_css = array(), $object_actions_names = array(), $table_id = null, $select_all = false, $order_by_columns = false, $build_link_elements = array(), $add_empty_row=false)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $module;

	if (!$css)
		$css = "content";
	if (!count($array)) {
		$plural = get_plural_form($element_name);
		plainmessage("<table class=\"$css\"><tr><td>There aren't any $plural.</td></tr>", false);
		if (!count($general_actions)) {
			print '</table>';
			return;
		}
	}

	if (!$base) {
		$main = (isset($_SESSION["main"])) ? $_SESSION["main"] : "main.php";
		$base = "$main?module=$module";
	}

	$lines = count($array);
	if ($element_actions)
		$act = count($element_actions);
	else
		$act = NULL;

	print '<table class="'.$css.'" cellspacing="0" cellpadding="0"';
	if ($table_id)
		print " id=\"$table_id\"";
	print '>';

	$order_dir = (!getparam("order_dir") || getparam("order_dir")=="asc") ? "desc" : "asc";
	if ($lines) {
		print '<tr class="'.$css.'">';
		$no_columns = 0;

		$ths ="";
		if($insert_checkboxes) {
			$ths .= '<th class="'.$css.' first_th checkbox">';
			if ($select_all)
				$ths .= '<input type="checkbox" name="select_all" id="select_all" onclick="toggle_column(this);">';
			else
				$ths .= '&nbsp;';
			$ths .= '</th>';
			print $ths;
			$no_columns++;
		}
		// print the name of the columns + add column for each action on object

		$request_link = build_link_request(array("order_by","order_dir"));
		foreach ($formats as $column_name => $var_name) {
			$exploded = explode(":",$column_name);
			if (count($exploded)>1)
				$name = $exploded[1];
			else {
				$name = $column_name;
				if (substr($column_name, 0, 9) == "function_")
					$name = substr($column_name,9);
				if (is_numeric($column_name))
					$name = $var_name;
			}
			$ucss = ($no_columns == 0) ? "$css first_th" : $css;
			print '<th class="'.$ucss.'">';
			$column_link = false;
			if ($order_by_columns) {
				if ((is_numeric($column_name) || count(explode(",",$var_name))==1) && ($order_by_columns===true || !isset($order_by_columns[$name]) || $order_by_columns[$name]==true)) {
					print "<a class='thlink' href='".$request_link."&order_by=$name&order_dir=$order_dir'>".str_replace("_","&nbsp;",ucfirst($name))."</a>";
					$column_link = true;
				}
			}
			if (!$column_link)
				print str_replace("_","&nbsp;",ucfirst($name));
			print '</th>';
			$no_columns++;
		}
		for ($i=0; $i<count($element_actions); $i++) {
/*			print '<th class="'.$css.'">&nbsp;</th>';
			$no_columns++;*/
			print '<th class="'.$css.'">';
			if (!isset($object_actions_names) || !isset($object_actions_names[$i]))
				print '&nbsp;';
			else
				print $object_actions_names[$i];
			print '</th>';
			$no_columns++;
		}
		print '</tr>';
	} else
		$no_columns = 2;

	for ($i=0; $i<count($array); $i++) {
		$cond_css = '';
		foreach ($conditional_css as $css_name => $conditions) {
			$add_css = true;
			foreach ($conditions as $column => $cond_column) {
				if ($array[$i][$column] != $cond_column)	{
					$add_css = false;
					break;
				}
			}
			if ($add_css)
				$cond_css .= " $css_name ";
		}
		if ($i == (count($array)-1))
			$cond_css.= " pre_end";
		print '<tr class="'.$css.'">';
		if ($insert_checkboxes && $id_name) {
			print '<td class="'.$css. "$cond_css";
			if ($i%2 == 0)
				print " evenrow";
			print '">';
			print '<input type="checkbox" name="check_'.$array[$i][$id_name].'"/>';
			print '</td>';
		}
		$j=0;
		foreach ($formats as $column_name => $names_in_array) {
			print '<td class="'.$css. "$cond_css";
			if ($i%2 == 0)
				print " evenrow";

			if ($j == count($formats)-1 && !count($array))
				print " end_cell";
			print '">';
			$use_vars = explode(",", $names_in_array);
			$exploded_col = explode(":", $column_name);
			$column_value = '';

			if (substr($exploded_col[0],0,9) == "function_") {
				$function_name = substr($exploded_col[0],9,strlen($exploded_col[0]));
				if (count($use_vars)) {
					$params = array();
					for ($var_nr=0; $var_nr<count($use_vars); $var_nr++)
						if (isset($array[$i][$use_vars[$var_nr]]))
							array_push($params, $array[$i][$use_vars[$var_nr]]);
						else
							array_push($params, null);
					$column_value = call_user_func_array($function_name,$params);
				}
			} elseif (isset($array[$i][$names_in_array])) {
				$column_value = $array[$i][$names_in_array];
			}
			if (strlen($column_value))
				print $column_value;
			else
				print "&nbsp;";
			print '</td>';
		}
		$link = '';
		foreach ($array[$i] as $col_name => $col_value) {
			if ($col_name=="password")
				continue;
			if (is_array($col_value))
				continue;
			if (count($build_link_elements)) {
				foreach ($build_link_elements as $k=>$link_element_name)
					if ($col_name == $link_element_name)
						$link .= "&$link_element_name=".urlencode($col_value);
			} else {
				if (strlen($col_value)<55)
					$link .= "&$col_name=".urlencode($col_value);
			}
		}
		$link_no = 0;
		foreach ($element_actions as $methd => $methd_name) {
			print '<td class="'.$css. "$cond_css";
			if ($i%2 == 0)
				print ' evenrow object_action';
			if ($link_no == count($element_actions)-1)
				print ' end_cell';
			print '">';
			if ($link_no)
				print '&nbsp;&nbsp;';
			
			if (substr($methd,0,8) != "__clean_")
				$current_link = $base.$methd.$link;
			else 
				$current_link = substr($methd,8)."?".trim($link,"&");

			if (substr($methd_name,0,11) != "__inactive_")
				print '<a class="'.$css. "$cond_css".'" href="'.htmlentities($current_link).'">'.$methd_name.'</a>';
			else {
				$methd_name = substr($methd_name,11);
				if (substr($methd_name,0,4)=="<img") 
					$methd_name = str_replace("<img", "<img style=\"opacity:0.4\"", $methd_name);
			
				print $methd_name;
			}
			print '</td>';
			$link_no++;
		}
		print '</tr>';
	}

	if ($add_empty_row) {
		$colspan = count($formats) + count($element_actions);
		if ($insert_checkboxes)
			$colspan += 1; 
		print '<tr><td class="last_row" colspan="'.$colspan.'"></td></tr>';
	}
	if (count($general_actions)) {
		print '<tr>';
		if (isset($general_actions["left"])) {
			$left_actions = $general_actions["left"];
			$columns_left = floor($no_columns/2);
			$no_columns -= $columns_left;
			print '<td class="'.$css.' allleft endtable" colspan="'.$columns_left.'">';
			$link_no = 0;
			foreach ($left_actions as $methd => $methd_name) {
				if ($link_no)
					print '&nbsp;&nbsp;';
				print '<a class="'.$css.'" href="'.$base.$methd.'">'.$methd_name.'</a>';
				$link_no++;
			}
			print '</td>';
			if (isset($general_actions["right"]))
				$general_actions = $general_actions["right"];
			else
				$general_actions = array();
		}
		
		print '<td class="'.$css.' allright endtable" colspan="'.$no_columns.'">';
		$link_no = 0;
		if (!count($general_actions))
			print "&nbsp;";
		foreach ($general_actions as $methd=>$methd_name) {
			if ($link_no)
				print '&nbsp;&nbsp;';
			print '<a class="'.$css.'" href="'.$base.$methd.'">'.$methd_name.'</a>';
			$link_no++;
		}
		print '</td>';
		print '</tr>';
	}
	print "</table>";
}

/**
 * Used to make confirmation link before an object will be deleted
 * @param $object String the object to be deleted
 * @param $value the value of the param
 * @param $message Text the message to be appended to the question of deletation
 * @param $object_id String the id of the object added in the link
 * @param $value_id String the value id of the object 
 * @param $additional String added to the value id
 * @param $next String use it to change the method param 
 * @param $action_obj String. Action to acknowledge. Default: "delete" 
 */ 
function ack_delete($object, $value = NULL, $message = NULL, $object_id = NULL, $value_id = NULL, $additional = NULL, $next = NULL, $action_obj="delete")
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $module, $method;

	if (!$object_id)
		$object_id = $object.'_id';
	if (!$value_id)
		$value_id = getparam($object_id);

	print "<div class=\"ack_delete notice\">Are you sure you want to $action_obj ".str_replace("_","&nbsp;",$object)." $value?";
	if ($message) {
		if (substr($message,0,2) != "__") {
			if(substr($message,0,1) == ",")
				$message = substr($message, 1, strlen($message));
			print " If you delete it you will also delete or set to NULL it's associated objects from $message.";
		} else
			print substr($message,2);
	}

	print "<br/><br/>";

	$link = $_SESSION["main"] .'?';
	if (isset($_SESSION["previous_page"]))
		foreach ($_SESSION["previous_page"] as $param => $value)
			$link .= "$param=$value&";
	$link .= '&module=' . $module . '&method=' . $method . '&action=database&' . $object_id . '=' . $value_id . $additional;

	print '<a class="llink" href="'.htmlentities($link).'">Yes</a>';

	print '&nbsp;&nbsp;&nbsp;&nbsp;'; 

	if (!$next && isset($_SESSION["previous_page"])) {
		$link = $_SESSION["main"].'?';
		foreach ($_SESSION["previous_page"] as $param => $value)
			$link .= "$param=$value&";
	} else {
		$link = $_SESSION["main"].'?module='.$module.'&method='.$next;
	}
	print '<a class="llink" href="'.htmlentities($link).'">No</a>';
}

/**
 * Sets month and year in dropdown elements
 */ 
function month_year($value = null, $key = null)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$date = params_date($value);
	return set_month($date["month"],$key).set_year($date["year"],$key);
}

/**
 * Returns month_day_year_hour with the desired format
 */ 
function month_day_year_hour_end($val, $key = NULL)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	return month_day_year_hour($val,$key,true,true,false);
}

/**
 * Returns day_month_year_hour with the desired format
 */ 
function day_month_year_hour_end($val, $key = NULL)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	return day_month_year_hour($val,$key,true,true,false);
}

/**
 * Sets month, day, year format in dropdown elements
 */
function month_day_year($val, $key = null, $etiquettes = false)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$date = params_date($val);
	$res = set_month($date["month"], $key, $etiquettes);
	$res.= set_day($date["day"], $key, $etiquettes);
	$res.= set_year($date["year"], $key, $etiquettes);
	return $res;	
}

/**
 * Sets month, day, year, hour format in dropdown elements
 */

function month_day_year_hour($date, $key = '', $default_today = true, $etiquettes = true, $begin_hour = true)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$date = params_date($date, $default_today, $begin_hour);
	$res = set_month($date["month"], $key, $etiquettes);
	$res.= set_day($date["day"], $key, $etiquettes);
	$res.= set_year($date["year"], $key, $etiquettes);
	$res.= set_hour($date["hour"], $key, $etiquettes);
	return $res;
}

/**
 * Sets day, month, year, hour in dropdown elements
 */ 
function day_month_year_hour($date, $key = '', $default_today = true, $etiquettes = true, $begin_hour = true)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$date = params_date($date, $default_today, $begin_hour);
	$res = set_day($date["day"], $key, $etiquettes);
	$res.= set_month($date["month"], $key, $etiquettes);
	$res.= set_year($date["year"], $key, $etiquettes);
	$res.= set_hour($date["hour"], $key, $etiquettes);
	return $res;
}

/**
 * Returns an array with day, month, year, hour
 */ 
function params_date($date, $default_today = false, $begin_hour = null)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$month = $day = $year = $hour = "";
	if ((!$date || $date == '') && $default_today) {
		/*$today = explode("-",date('F-j-Y-H-i',time()));
		$month = $today[0]; 
		$day = $today[1]; 
		$year = $today[2];*/
		$today = date("Y-m-d H:i:s");
		$today = check_timezone($today);
		$expl = explode(" ",$today);
		$date = explode("-",$expl[0]);
		$time = explode(":",$expl[1]);
		$month = $date[1];
		$day = $date[2];
		$year = $date[0];
		if ($begin_hour === true)
			$hour = "0";
		elseif ($begin_hour === false)
			$hour = "23";
		else
			$hour = $today[3];
	} elseif ($date && $date != "") {
		$today = explode(" ",$date);
		$hour = explode(":",$today[1]);
		$hour = $hour[0];
		$today = explode("-",$today[0]); 
		$year = $today[0];
		$month = $today[1]; 
		$day = $today[2]; 
	}
	return array("day"=>$day, "month"=>$month, "year"=>$year, "hour"=>$hour);
}

/**
 * Sets hour in dropdown
 */ 
function set_hour($hour, $key = '', $etiquettes = false, $js = '')
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$res = "";
	if ($etiquettes)
		$res .= "&nbsp;Hour:&nbsp;";
	$res.= '<select name="'.$key.'hour" '.$js.'>'."\n";
	for ($i=0; $i<24; $i++) {
		$selected = ($hour == $i) ? "SELECTED" : "";
		$res.= "<option $selected>".$i."</option>"."\n";
	}
	$res.= "</select>";
	return $res;
}

/**
 * Sets Year in dropdown
 */
function set_year($year, $key = '', $etiquettes = false, $js = '')
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$res = "";
	if ($etiquettes)
		$res .= "&nbsp;Year:&nbsp;";
	$res.= '<input type="text" name="'.$key.'year" value="'.$year.'" size="4" '.$js.'/>'."\n";
	return $res;
}

/**
 * Sets month in dropdown
 */
function set_month($month, $key='', $etiquettes=false, $default_today=false, $js='')
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$months = array("January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December");;
	$res = "";
	if ($etiquettes)
		$res .= "Month:&nbsp;";
	$res .= '<select name="'.$key.'month" '.$js.'>'."\n";
	if (!$default_today)
		$res.= "<option value=\"\">-</option>";
	for ($i=0; $i<count($months); $i++) {
		$index = $i+1;
		$d_index = (strlen($index)==1) ? "0".$index : $index;
		$selected = ($month == $months[$i] || $month == $index || $month == $d_index) ? "SELECTED" : "";
		$res .= "<option $selected>".$months[$i]."</option>"."\n";
	}
	$res.= "</select>"."\n";
	return $res;
}

/**
 * Sets day in dropdown
 */ 
function set_day($day, $key='', $etiquettes=false, $default_today=false, $js="")
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$res = "";
	if ($etiquettes)
		$res .= "Day:&nbsp;";
	$res.= '<select name="'.$key.'day" '.$js.'>'."\n";
	if (!$default_today)
		$res.= "<option value=\"\">-</option>";
	for ($i=1; $i<32; $i++) {
		$selected = ($day == $i || $day == "0".$i) ? "SELECTED" : "";
		$res .= "<option $selected>".$i."</option>"."\n";
	}
	$res.= "</select>"."\n";
	return $res;
}

/**
 * Truncate seconds from time
 */ 
function seconds_trunc($time, $floor=false)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $date_format;

	$date = explode(" ",$time);
	if (count($date)>1) {
		// not just "time" but "date and time"
		if ($date_format == "d-m-y") {
			$new_date = european_date($date[0]);
			$time = $new_date." ".$date[1];
		}
	}

	$date = explode('.',$time);
	$date = $date[0];
	$date = explode(":",$date);
	$sec = count($date) - 1;

	if (!$floor)
		$date[$sec] ++;

	if (strlen($date[$sec]) == 1)
		$date[$sec] = '0'.$date[$sec];
	if ($date[$sec] == 60) {
		$date[$sec-1]++;
		$date[$sec] = 0;
	}
	$date = implode(":",$date);
	return $date;
}
/**
 * Truncate seconds with rounding up
 */ 
function seconds_trunc_floor($time)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	return seconds_trunc($time,true);
}

/**
 * Returns european date format if global is set and has the specific format
 * or takes the date from timestamp 
 */
function select_date($timestamp)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $date_format;

	$timestamp = check_timezone($timestamp);
	$timestamp = explode(" ",$timestamp);
	$date = $timestamp[0];
	if (isset($date_format) && $date_format == "d-m-y")
		$date = european_date($date);
	return $date;
}
/**
 * Returns european date format
 */ 
function european_date($date)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$date = explode("-",$date);
	return $date[2]."-".$date[1]."-".$date[0];
}

/**
 * Select time from a timestamp
 */ 
function select_time($timestamp)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$timestamp = check_timezone($timestamp);
	$date = seconds_trunc($timestamp);
	$date = explode(' ',$date);
	return $date[1];
}

/**
 * Returns timestamp without miliseconds
 */ 
function trunc_date($timestamp)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $date_format;

	$timestamp = check_timezone($timestamp);
	$date = explode(" ",$timestamp);
	if (count($date)>1) {
		// not just "time" but "date and time"
		if ($date_format == "d-m-y") {
			$new_date = european_date($date[0]);
			$timestamp = $new_date." ".$date[1];
		}
	}
	$timestamp = explode('.',$timestamp);
	return $timestamp[0];
}

/**
 * Format for $timestamp must be Y-m-d H:i:s.miliseconds
 */
function check_timezone($timestamp, $reverse_apply=false)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $system_standard_timezone;

	// check if $_SESSION["timezone"] is set and if is different from system timezone
	if (!isset($_SESSION["timezone"]))
		return $timestamp;

	$timezone = str_replace("GMT","",$_SESSION["timezone"]);
	if ($timezone == "")
		$timezone = 0;
/*	$system_tz = str_replace("0","",date("P"));
	$system_tz = str_replace(":","",$system_tz);*/
	$system_tz = str_replace("GMT","",$system_standard_timezone);
	if ($system_tz == "")
		$system_tz = 0;

	$diff = $timezone - $system_tz;
	if ($reverse_apply)
		$diff = -$diff;

	$arr = explode(".",$timestamp);
	$milisecs = (isset($arr[1])) ? $arr[1] : null;
	$date = $arr[0];
	$date = explode(" ",$date);
	$time = explode(":",$date[1]);
	$date = explode("-",$date[0]);
	$hours = $time[0] + $diff;	
	$new_timestamp = gmdate("Y-m-d H:i:s", gmmktime($hours, $time[1], $time[2], $date[1], $date[2], $date[0]));
	return $new_timestamp;
}

/**
 * Get the difference between session set timezone and system timezone
 */ 
function get_timezone_diff($reverse_apply=false)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $system_standard_timezone;

	// check if $_SESSION["timezone"] is set and if is different from system timezone
	if (!isset($_SESSION["timezone"]))
		return "0";

	$timezone = str_replace("GMT","",$_SESSION["timezone"]);
	if ($timezone == "")
		$timezone = 0;
/*	$system_tz = str_replace("0","",date("P"));
	$system_tz = str_replace(":","",$system_tz);*/
	$system_tz = str_replace("GMT","",$system_standard_timezone);
	if ($system_tz == "")
		$system_tz = 0;

	$diff = $timezone - $system_tz;
	if ($reverse_apply)
		$diff = -$diff;

	return $diff;
}

/**
 * Returns an interval from a timestamp
 */ 
function add_interval($timestamp, $unit, $nr)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$timestamp = explode(" " ,$timestamp);
	$date = explode("-",$timestamp[0]);
	$time = explode(".",$timestamp[1]);
	$time = explode(":",$time[0]);
	$year = $date[0];
	$month = $date[1];
	$day = $date[2];
	$hours = $time[0];
	$minutes = $time[1];
	$seconds = $time[2];

	${$unit} += $nr;
	$date = date('Y-m-d H:i:s',gmmktime($hours,$minutes,$seconds,$month,$day,$year));

	return $date;
}

/**
 * Get Unix timestamp for a GMT date
 */ 
function get_time($key='',$hour2='00',$min='00',$sec="00")
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$day = getparam($key."day");
	if (!$day)
		return null;
	$month = getparam($key."month");
	$month = getmonthnumber($month);
	if (strlen($month))
		$month = '0'.$month;
	$year = getparam($key."year");
	$hour = getparam($key."hour");
	if (!$hour)
		$hour = $hour2;

	if (!checkdate($month,$day,$year)) {
		errormess("This date does not exit : day=".$day.' month='.$month.' year='.$year,'no');
		return;
	}
	$date = gmmktime($hour,$min,0,$month,$day,$year);
	return $date;
}

/**
 * Returns the DATE in a specific format from $_REQUEST
 * The format is a string: "year-month-day H:M:S"
 */ 
function get_date($key='',$_hour='00',$min='00',$sec='00')
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$day = getparam($key."day");
	$month = getparam($key."month");
	if (!$day || !$month)
		return null;
	$month = getmonthnumber($month);
	$year = getparam($key."year");
	$hour = getparam($key."hour");
	if (strlen(!$hour))
		$hour = $_hour;
	if (strlen($hour)==1)
		$hour = "0".$hour;

	if (!checkdate($month,$day,$year)) {
		errormess("This date does not exit : day=".$day.' month='.$month.' year='.$year,'no');
		return;
	}
	if (strlen($month) == 1)
		$month = '0'.$month;
	if (strlen($day) == 1)
		$day = '0'.$day;
	if (strlen($_hour))
		$date = "$year-$month-$day $hour:$min:$sec";
	else
		$date = "$year-$month-$day";
	return $date;
}

/**
 * Display letters in alphabetic order, each is a link except the one selected
 * @param $link String the base of the link on which will be added params:
 * module, method and letter
 * @param $force_method String the new method to be added in link, default NULL
 * @return $letter the selected letter 
 */   
function insert_letters($link = "main.php", $force_method = NULL, $letter = NULL)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $module,$method;

	if (is_array($module))
		$module = $module[0];
	if ($force_method)
		$method = $force_method;

	if (!$letter)
		$letter = getparam("letter") ? getparam("letter") : 'A';

	$letters = array("A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "X", "Y", "W", "Z");

	for ($i=0; $i<count($letters); $i++) {
		print '&nbsp;';
		if ($letter == $letters[$i])
			print $letter;
		else {
			print '<a class="llink" href="'.$link;
			if (!strpbrk($link,"?"))
				print '?';
			else
				print "&";
			print 'module='.$module.'&method='.$method.'&letter='.$letters[$i].'">'.$letters[$i].'</a>';
		}
	}
	print '<br/><hr>';
	return $letter;
}

/**
 * Display 10 numbers each as a link, the selected one is displayed without link.
 * Returns the selected number.
 * @param $link String contains the base of the link on which will be added parameters:
 * module, method and the number
 * @return the selected number
 */ 
function insert_numbers($link = "main.php")
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $module,$method;

	$nr = getparam("nr");
	if (!$nr)
		$nr = 0;

	if (is_array($module))
		$module = $module[0];

	for ($i=0; $i<10; $i++) {
		print '&nbsp;';
		if ($nr == $i)
			print $nr;
		else
			print '<a class="llink" href="'.$link.'?module='.$module.'&method='.$method.'&nr='.$i.'">'.$i.'</a>';
	}
	print '<br/><hr>';
	return $nr;
}

/** 
 * Returns minutes from interval with format HOURS:MINUTES:SECONDS
 * The result is a rounded float value to 2 decimals
 */ 
function interval_to_minutes($interval)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	if (!$interval)
		return NULL;
	$interval2 = explode(':',$interval);

	return round($interval2[0]*60+$interval2[1]+$interval2[2]/60,2);
}

/** 
 * Returns string with format "HOURS:MINUTES:00" as interval
 * from a given integer as minutes
 * @param $minutes Integer the minutes that will be transformed
 * in interval
 */ 
function minutes_to_interval($minutes = NULL)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	//minutes should be integer: ignoring seconds
	$minutes = floor($minutes);

	if (!$minutes)
		return '00:00:00';

	$hours = floor($minutes / 60);
	$mins = $minutes - $hours*60;

	//don't care if $hours > 24
	if (strlen($hours) == 1)
		$hours = '0'.$hours;
	if (strlen($mins) == 1)
		$mins = '0'.$mins;
	return "$hours:$mins:00";
}

/**
 * Generate a random chars of a default length of 8
 * @param $lim Integer the length of the random generated 
 * characters
 */ 
function gen_random($lim = 8)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$nr = '';
	for ($digit = 0; $digit < $lim; $digit++) {
		$r = rand(0,1);
		$c = ($r==0) ? rand(65,90) : rand(97,122);
		$nr .= chr($c);
	}
	return $nr;
}

/**
 * Returns the corresponding number from given month 
 * @param $month String the name of the month written in english or 
 * romanian language
 */ 
function getmonthnumber($month)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	switch($month){
		case "January":
		case "Ianuarie":
			return '1';
		case "February":
		case "Februarie":
			return '2';
		case "March":
		case "Martie":
			return '3';
		case "April":
		case "Aprilie":
			return '4';
		case "May":
		case "Mai":
			return '5';
		case "June":
		case "Iunie":
			return '6';
		case "July":
		case "Iulie":
			return '7';
		case "August":
			return '8';
		case "September":
		case "Septembrie":
			return '9';
		case "October":
		case "Octombrie":
			return '10';
		case "November":
		case "Noiembrie":
			return '11';
		case "December":
		case "Decembrie":
			return '12';
	}
	return false;
}

/**
 * Returns month name from given number
 * @param $nr String contains the number of the month 
 */ 
function get_month($nr)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	switch($nr) {
		case "13":
		case "1":
		case "01":
			return "January";
		case "2":
		case "02":
		case "14":
			return "February";
		case "3":
		case "03":
		case "15":
			return "March";
		case "4":
		case "04":
			return "April";
		case "5":
		case "05":
			return "May";
		case "6":
		case "06":
			return "June";
		case "7":
		case "07":
			return "July";
		case "8":
		case "08":
			return "August";
		case "9":
		case "09":
			return "September";
		case "10":
			return "October";
		case "11":
			return "November";
		case "12":
			return "December";
		default:
			return "Invalid month: $nr";
	}
}

/**
 * Remove special chars from value so that it will contain only digits
 */ 
function make_number($value)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$value = str_replace(array("$", ' ', '%', '&'), "", $value);
	return $value;
}

/**
 * Returns the name for a .jpg without spaces
 */ 
function make_picture($name)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$name = strtolower($name);
	$name = str_replace(" ","_",$name);
	$name .= '.jpg';
	return $name;
}

/**
 * Builds the link for next page
 */ 
function next_page($max)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $module, $method, $limit;
	$offset = getparam("offset");
	if (!$offset)
		$offset = 0;

	$minus = $offset - $limit;
	$plus = $offset + $limit;
	$page = number_format($offset / $limit) + 1;
	print '<br/><center>';
	if ($minus >= 0)
		print '<a class="llink" href="main.php?module='.$module.'&method='.$method.'&offset='.$minus.'&max='.$max.'"><<</a>&nbsp;&nbsp;&nbsp;';
	print $page;
	if ($plus < $max)
		print '&nbsp;&nbsp;&nbsp<a class="llink" href="main.php?module='.$module.'&method='.$method.'&offset='.$plus.'&max='.$max.'">>></a>&nbsp;&nbsp;&nbsp;';
	print '</center>';
}

/**
 * Removes parameters from $_REQUEST
 */ 
function unsetparam($param)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	if (isset($_POST[$param]))
		unset($_POST[$param]);
	if (isset($_GET[$param]))
		unset($_GET[$param]);
	if (isset($_REQUEST[$param]))
		unset($_REQUEST[$param]);
}

/**
 * Returns a string from bytes
 */ 
function bytestostring($size, $precision = 0) 
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$sizes = array('YB', 'ZB', 'EB', 'PB', 'TB', 'GB', 'MB', 'kB', 'B');
	$total = count($sizes);

	while($total-- && $size > 1024) 
		$size /= 1024;

	return round($size, $precision).$sizes[$total];
}

/**
 * Returns an array with the parameters from $_REQUEST 
 */ 
function form_params($fields)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$params = array();
	for ($i=0; $i<count($fields); $i++)
		$params[$fields[$i]] = getparam($fields[$i]);
	return $params;
}

/**
 * Returns a field value if exists in an array or NULL otherwize
 */ 
function field_value($field, $array)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	if (isset($array[$field]))
		return $array[$field];
	return NULL;
}

/**
 * Displays a table in a div element.
 * The table has 2 rows:
 * first row contains a logo image and a title
 * the second row contains the method / default explanation
 * The style of the container of the table can be changed
 */
function explanations($logo, $title, $explanations, $style="explanation")
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $method;

	if (is_array($explanations))
		if (isset($explanations[$method]))
			$text = $explanations[$method];
		else
			$text = $explanations["default"];
	else
		$text = $explanations;
?>
<div class="<?php print $style; ?>">
    <table class="fillall" cellspacing="0" cellpadding="0">
	<tr>
	    <td class="logo_wizard" style="padding:5px;">
<?php	if ($logo && $logo != "") {?>
		<img src="<?php $logo; ?>" />
<?php	}
?>
	    </td>
	    <td class="title_wizard" style="padding:5px;">
		<div class="title_wizard"><?php print $title; ?></div>
	    </td>';	
	</tr>
	<tr>
	    <td class="step_description" style="font-size:13px;padding:5px;" colspan="2">
<?php		print $text; ?>
	    </td>
	</tr>
    </table>
</div>
<?php
}

/**
 * Builds the HTML dropdown
 */ 
function build_dropdown($arr, $name, $show_not_selected = true, $disabled = "", $css = "", $javascript = "", $just_options = false, $hidden = false, $set_not_selected = null)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	if (!$just_options) {
		$hidden = ($hidden) ? " style=\"display:none;\"" : "";
		$res = '<select class="dropdown '.$css.'" name="'.$name.'" id="'.$name.'" '.$disabled.$hidden.' ';
		if (substr($name,-2) == "[]")
			$res .= " multiple=\"multiple\" size=\"3\"";
		$res .= $javascript.'>'."\n";
	} else
		$res = '';
	if ($show_not_selected) {
		$val = "";
		if ($set_not_selected)
			$val = $set_not_selected;
		$res .= '<option value="'.$val.'"> - </option>'."\n";
	}
	$selected = (isset($arr["selected"]))? $arr["selected"] : "";
	unset($arr["selected"]);
	for ($i=0; $i<count($arr); $i++) {
		if (is_array($arr[$i])) {
			$value = $arr[$i]["field_id"];
			$value_name = $arr[$i]["field_name"];
			$css = (isset($arr[$i]["css"])) ? 'class="'.$arr[$i]["css"].'"' : "";
			$res .= "<option value=\"$value\" $css";
			if (is_array($selected)) {
				if (in_array($value,$selected))
					$res .= " SELECTED";
			} else {
				if ($selected == $value)
					$res .= " SELECTED";
			}
			if ($value === "__disabled")
				$res .= " disabled=\"disabled\"";
			$res .= ">$value_name</option>\n";
		}
	}
	if (!$just_options)
		$res .= "</select>\n";
	return $res;
}

/**
 * Builds the format for the dropdown 
 */ 
function format_for_dropdown($vals)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$arr = array();
	for ($i=0; $i<count($vals); $i++)
		array_push($arr, array("field_id"=>$vals[$i], "field_name"=>$vals[$i]));
	return $arr;
}

/**
 * Build a table with the rows of a html form data
 * Uses display_pair() to display the rows
 */ 
function formTable($rows, $th = null, $title = null, $submit = null, $width = null, $id = null, $css_first_column = '', $color_indexes = array(), $dif_css = "")
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	if (is_array($th))
		$cols = count($th);
	elseif (isset($rows[0]))
		$cols = count($rows[0]);
	else
		$cols = count($rows);
	if (substr($width,-2) != "px" && substr($width,-1) != "%")
		$width .= "px";
	$width = ($width) ? "style=\"width:".$width.";\"" : "";
	$id = ($id) ? " id=\"$id\"" : "";
	print '<table class="formtable '.$dif_css.'" cellspacing="0" cellpadding="0" '.$width.' '.$id.'>'."\n";
	if ($title) {
		print "<tr>\n";
		print '<th class="title_formtable" colspan="'.$cols.'">'.$title.'</td>'."\n";
		print "</tr>\n";
	}
	if (is_array($th)) {
		print "<tr>\n";
		for ($i=0; $i<count($th); $i++) {
			if (is_array($th[$i])) {
				$style = "style=\"width:".$th[$i]["width"].";\"";
				$info = $th[$i][0];
			} else {
				$style = "";
				$info = $th[$i];
			}
			print "<th class=\"formtable\" $style>".$info."</th>\n";
		}
		print "</tr>\n";
	}
	if (isset($rows[0])) {
		for ($i=0; $i<count($rows); $i++) {
			$row = $rows[$i];
			$custom_css = (in_array($i,$color_indexes)) ? "custom_border" : "";
			print "<tr>\n";
			if (is_array($row)) {
				for ($j=0; $j<count($row); $j++) {
					$css = ($i%2 == 0) ? "formtable evenrow_ftable $dif_css" : "formtable $dif_css";
					if (!$th && $i==0 && $j==0)
						$css .= "firsttd";
			//		if($j == 0)
			//			$css .= " $css_first_column"."";
					if ($i%2 == 0)
						print "<td class=\"$css $custom_css\">". $row[$j] ."</td>\n";
					else
						print "<td class=\"$css $custom_css\">". $row[$j] ."</td>\n";
				}
			} else {
				print '<td class="white_row" colspan="'.count($th).'">'.$row.'</td>';
			}
			print "</tr>\n";
		}
	} else {
		$i = 0;
		foreach ($rows as $key => $format) {
			print "<tr>\n";
			$css = ($i%2 === 0) ? "formtable evenrow $dif_css" : "formtable oddrow $dif_css";
			display_pair($key, $format, null, null, $css, null, null);
			print "</tr>\n";
			$i++;
		}
	}
	if ($submit) {
		print "<tr>\n";
		print "<td class=\"submit_formtable\" colspan=$cols>";
		print $submit;
		print "</td>";
		print "</tr>\n";
	}
	print "</table>\n";
}

/**
 * Builds a HTML <form> with the formTable() builded table
 */ 
function set_default_object($fields, $selected, $method, $message)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	start_form();
	if ($method)
		addHidden("database", array("method"=>$method));
	if (!$selected) {
		subtitle($message);
		print '<table class="error" cellspacing="0" cellpadding="0"><tr><td>'."\n";
	}
	formTable($fields, null, null, null, null, null, '', array(), "blue_letters");
	if (!$selected)
		print '</td></tr></table>'."\n";
	else
		hr();
	end_form();
	br();
}

/**
 * Sets the fields value by using getparam() to take the data set in FORM
 */ 
function set_form_fields(&$fields, $error_fields, $field_prefix='', $use_urldecode=false)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	if (!$error_fields)
		$error_fields = array();

	foreach ($fields as $name=>$def) {
		if (!isset($def["display"]))
			$def["display"] = "text";
		if ($def["display"] == "hidden" || $def["display"]=="message" || $def["display"]=="fixed" || $def["display"]=="objtitle" || $def["display"]=="custom_field" || $def["display"]=="custom_submit")
			continue;
		if (in_array(strtolower($name), $error_fields))
			$fields[$name]["error"] = true;
		if (substr($name,-2) == "[]" && $def["display"] == "mul_select")
			$val = (isset($_REQUEST[$field_prefix.substr($name,0,strlen($name)-2)])) ? $_REQUEST[$field_prefix.substr($name,0,strlen($name)-2)] : null;
		else
			$val = (isset($_REQUEST[$field_prefix.$name])) ? $_REQUEST[$field_prefix.$name] : null;

		if ($use_urldecode)
			$val = urldecode($val);

		if ($val!==NULL) {
			if (isset($fields[$name][0]) && is_array($fields[$name][0]))
				$fields[$name][0]["selected"] = $val;
			elseif ($def["display"] == "checkbox")
				$fields[$name]["value"] = ($val == "on") ? "t" : "f";
			else 
				$fields[$name]["value"] = $val;
		}
	}
}

/**
 * Check the error message and add specified error fields in error_fields array
 * @param $error String representing the error message. In order for fields to be added they must be marked with 'error_on_column'
 * Ex: Please set 'username' and 'email'. 
 * @param &$error_fields Array containing fields where errors occured. Fields parsed from $error will be added to it.
 */ 
function set_error_fields($error, &$error_fields)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	// fields between '' are considered names
	$field = '';
	$start = false;
	$error = strtolower($error);
	for ($i=0; $i<strlen($error); $i++) {
		if ($error[$i] != "'") {
			if ($start)
				$field .= $error[$i];
		} else {
			if ($start) {
				if (!in_array($field, $error_fields))
					$error_fields[] = $field;
				$field = '';
				$start = false;
			} else
				$start = true;
		}
	}
}

/**
 * Handle the errors by displaing the error using errormess();
 * sets the errors in array fields and then sets the data in form fields
 */ 
function error_handle($error, &$fields, &$error_fields, $field_prefix='', $use_urldecode=false)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	if ($error) {
		errormess($error,"no");
		set_error_fields($error, $error_fields);
		set_form_fields($fields, $error_fields, $field_prefix, $use_urldecode);
	}
	if ($error===false) {
		set_error_fields($error, $error_fields);
		set_form_fields($fields, $error_fields, $field_prefix, $use_urldecode);
	}
}

/**
 * Display a link in a div with the build link from previous page data
 */ 
function return_button($method=null, $_module=null, $align="right", $name="Return", $div_container=true)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $module;

	if ($method) {
		if (!$_module)
			$_module = $module;
		$link = $_SESSION["main"].'?module='.$_module.'&method='.$method;
	} elseif (isset($_SESSION["previous_page"])) {
		$link = $_SESSION["main"]."?";
		foreach ($_SESSION["previous_page"] as $param=>$value) {
			if (is_array($value) || is_object ($value))
				continue;
			$link.= "$param=".urlencode($value)."&";
		}
	} else
		$link = $_SESSION["main"]. "?"."module=".$module;
	
	if ($div_container) {
		print '<div style="float:'.$align.'"><a class="llink" href="'.$link.'">'.$name.'</a></div>';
		br();
	} else
		print '<a class="llink" href="'.$link.'">'.$name.'</a>';
}

/**
 * Display a HTML <hr> element with css class 'bluehr'
 */ 
function hr()
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	print "<hr class=\"bluehr\" />";
}

/**
 * Return true if module was found in the array $exceptions_to_save
 */ 
function exception_to_save()
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $method, $module, $exceptions_to_save;

	if (isset($exceptions_to_save) && isset($exceptions_to_save[$_SESSION["level"]]) && isset($exceptions_to_save[$_SESSION["level"]]["$module"]) && (in_array($method, $exceptions_to_save[$_SESSION["level"]]["$module"]) || (in_array("__all", $exceptions_to_save[$_SESSION["level"]]["$module"]))))
		return true;

	if (!isset($_SESSION["level"]) && isset($exceptions_to_save["__default"][$method]))
		return true;

	return false;
}

/**
 * Saves page info, puts the parameters from $_REQUEST in array $_SESSION["previous_page"]
 * If exceptions_to_save() is true and method biggins with one of: edit,add_,delete, import, export
 * then page info is not saved.
 * 
 * !! It is really important to test the whole project again if this is added later on, not at the start of the project.
 * Information added in this SESSION field is automatically added in forms/links built in ansql, and it might override the visual fields from forms/start of links
 */ 
function save_page_info()
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $method, $module, $exceptions_to_save;

	// don't save info for edit/add/delete pages
	if (substr($method,0,4) == "edit" || substr($method,0,4) == "add_" || substr($method,0,6) == "delete" || exception_to_save() || substr($method,0,6)=="import" || substr($method,0,6)=="export") {
		return;
	}

	$_SESSION["previous_page"] = array();

	$param_exceptions = array("PHPSESSID", "old_submit");
	foreach ($_REQUEST as $param => $value) {
		if (in_array($param, $param_exceptions) || substr($param,0,5)=="__utm")
			continue;
		if ($param == "module")
			$value = $module;
		elseif ($param == "method")
			$value = $method;
		$_SESSION["previous_page"][$param] = $value;
	}
}

/**
 * Creates a HTML form structure to make a routing test
 */ 
function make_routing_test($fields)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $module;

	if ($_SESSION["level"] != "admin")
		return;

	if (!$fields) 
		$fields = array("called"=>"", "caller"=>"");

	$fields["th_custom"] = array("display"=>"message", "value"=>"<div style=\"width:100px; float:left; text-align:center;\">Param</div>   <div style=\"width:100px; float:left; position:relative; text-align:center;\">Value</div>", "triggered_by"=>"1");
	$fields["custom_1"] = array("display"=>"message", "value"=>link_for_custom_field(1));
	for ($i=1; $i<=20; $i++)
		$fields["custom$i"] = array("display"=>"message", "triggered_by"=>"$i", "value"=>routing_test_custom_field($i));

	start_form();
	addHidden("yate");
	editObject(null, $fields, "Make routing test", "Send");
	end_form();
}

/**
 * Creates link for a custom field with a predefined index value
 */ 
function link_for_custom_field($index)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$ret = "<a class=\"llink\" id=\"custom_field_index$index\" onClick=\"";
	$ret.= ($index == 1) ? "show_hide('tr_th_custom');" : "";
	$ret.= "show_hide('tr_custom$index'); show_hide('custom_field_index$index');";
	if ($index == 1)
		$ret.= "show_hide('tr_custom_$index');";
	$ret.= "\" > Add parameter</a>";
	return $ret;
}

/**
 * Creates the custom input fields for routing test
 */ 
function routing_test_custom_field($index)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$param_name = getparam("param_name$index");
	$param_value = getparam("param_value$index");
	$ret = "<input type=\"text\" name=\"param_name$index\" value=\"$param_name\" style=\"width:100px;\"/> = \n";
	$ret.= "<input type=\"text\" name=\"param_value$index\" value=\"$param_value\" style=\"width:100px;\" />";
	$index++;
	$ret.= link_for_custom_field($index);
	return $ret;
}

/**
 * Returns the fields containing the parameters 
 * and their values from the routing test yate form
 */ 
function make_routing_test_yate($fields=null)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	if (!$fields) {
		$fields = array();
		$fields["called"] = getparam("called");
		$fields["caller"] = getparam("caller");
	}

	for ($i=1; $i<=20; $i++) {
		if (!getparam("param_name$i"))
			continue;
		$fields[getparam("param_name$i")] = getparam("param_value$i");
	}
	return $fields;
}

/**
 * Sends routing test command with fields parameters and values 
 * through the socket connection and return an array with 
 * true set on first key and the response of the given command
 * on second key  if no errors where encounted
 * If errors are found prints the error and returns
 * an array with false as the value of the first key
 */ 
function send_routing_test_yate($fields)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$command = "routing_test ";
	foreach ($fields as $name=>$value) {
		if ($command != "routing_test ")
			$command .= ";";
		$name = str_replace(";","",$name);
		$value = str_replace(";","",$value);
		$command .= "$name=$value";
	}

	$socket = new SocketConn;
	if ($socket->error == "") {
		return array(true,$socket->command($command/*"uptime"*/));
	} else {
		errormess("Can't make test: ".print_r($socket->error,true), "no");
		return array(false);
	}
}

/**
 * Insert at least 1 line break 
 * @param $count Integer the number of the line breaks to be printed. 
 */ 
function br($count = null)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	if (!$count)
		$count = 1;

	for ($i=0; $i<$count; $i++) 
		print "<br/>"."\n";
}

/**
 * Print or return at least 1 non-breaking space 
 * @param $count Integer how many non-breaking space to be printed or returned
 * @param $print Bool set to true to print the non-breaking space, false to return 
 * the string with the non-breaking spaces. Defaults is set to true. 
 */ 
function nbsp($count = null, $print=true)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	if (!$count)
		$count = 1;

	$res = '';
	for ($i=0; $i<$count; $i++) 
		$res .= "&nbsp;";
	
	if ($print)
		print $res;
	else
		return $res;
}

/**
 * Sends an email 
 * @param $emailaddress String the email address where to send tha mail
 * @param $fromaddress String from whom address
 * @param $emailsubject String the subject of the email
 * @param $body String the content of the email
 * @param $attachments Bool 
 *
 */ 
function send_mail($emailaddress, $fromaddress, $emailsubject, $body, $attachments = false, $names = null, $notice = true, $link_with_notice = false, $content_type="text/html", $transfer_encoding="QUOTED-PRINTABLE")
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $path;
	$now = time();
	# Is the OS Windows or Mac or Linux
	if (strtoupper(substr(PHP_OS,0,3)=='WIN')) {
	    $eol="\r\n";
	} elseif (strtoupper(substr(PHP_OS,0,3)=='MAC')) {
	    $eol="\r";
	} else {
	    $eol="\n";
	}
	$mime_boundary=md5(time());

	if(!$names)
		$names = '';//$fromaddress;

	# Common Headers
	$headers = 'From: '.$names.'<'.$fromaddress.'>'.$eol;
	$headers .= 'Reply-To: <'.$fromaddress.'>'.$eol;
	$headers .= 'Return-Path: <'.$fromaddress.'>'.$eol;    // these two to set reply address
	$server = (isset($_SERVER['SERVER_NAME'])) ? $_SERVER['SERVER_NAME'] : "localhost.lan";
	$headers .= "Message-ID: <".$now." TheSystem@".$server.">".$eol;
	$headers .= "X-Mailer: PHP v".phpversion().$eol;          // These two to help avoiker spam-filters
	# Boundry for marking the split & Multitype Headers
	$headers .= 'MIME-Version: 1.0'.$eol;
	$headers .= "Content-Type: multipart/mixed; boundary=\"".$mime_boundary."\"".$eol;
	$msg = "";
 
	#this is the body of the message
	$msg .= "--".$mime_boundary.$eol;
	$msg .= "Content-Type: $content_type; charset=ISO-8859-1".$eol;
	$msg .= "Content-Transfer-Encoding: $transfer_encoding".$eol.$eol;
	//$msg .= strip_tags(str_replace("<br>", "\n", $body)).$eol.$eol;
	//$msg .= strip_tags(str_replace("\n", "<br/>", $body)).$eol.$eol;
	$msg .= $body.$eol.$eol;

	if($attachments !== false  && count($attachments) > 0) {
	# Boundry for marking the split & Multitype Headers
		$headers .= 'MIME-Version: 1.0'.$eol;
		$headers .= "Content-Type: multipart/mixed; boundary=\"".$mime_boundary."\"".$eol;

		$msg = "";
	
		#this is the body of the message
		$msg .= "--".$mime_boundary.$eol;
		$msg .= "Content-Type: $content_type; charset=ISO-8859-1".$eol;

		//$msg .= strip_tags(str_replace("<br>", "\n", $body)).$eol.$eol;
		//$msg .= strip_tags(str_replace("\n", "<br/>", $body)).$eol.$eol;
		$msg .= $body.$eol.$eol;
	
		#support for multiple attachments (i won't be using this for now)
		if ($attachments !== false  && count($attachments) > 0)
		{
	
			for($i=0; $i < count($attachments); $i++)
			{
				if (is_file($attachments[$i]["file"]))
				{  
				# File for Attachment
					$file_name = substr($attachments[$i]["file"], (strrpos($attachments[$i]["file"], "/")+1));
		
					$handle=fopen($attachments[$i]["file"], 'rb');
					$f_contents=fread($handle, filesize($attachments[$i]["file"]));
					$f_contents=chunk_split(base64_encode($f_contents));    //Encode The Data For Transition using base64_encode();
					fclose($handle);
		
					# Attachment
					$msg .= "--".$mime_boundary.$eol;
					$msg .= "Content-Type: ".$attachments[$i]["content_type"]."; name=\"".$file_name."\"".$eol;
					$msg .= "Content-Transfer-Encoding: base64".$eol;
					$msg .= "Content-Disposition: attachment; filename=\"".$file_name."\"".$eol.$eol; // !! This line needs TWO end of lines !! IMPORTANT !!
					$msg .= $f_contents.$eol.$eol;
				}
			}
		}
	}
	$msg .= "--".$mime_boundary.'--'.$eol.$eol;
	
	# SEND THE EMAIL
	ini_set('sendmail_from',$fromaddress);  // the INI lines are to force the From Address to be used !
	if (mail($emailaddress, $emailsubject, $msg, $headers)){
		if(php_sapi_name() == 'cli')
			print "E-mail message was sent succesfully to ".$emailaddress;
		elseif($notice && !$link_with_notice)
			message("E-mail message was sent succesfully to ".$emailaddress,'no');
		elseif($notice && $link_with_notice)
			message("E-mail message was sent succesfully to ".$emailaddress);
		return true;
	}else{
		if(php_sapi_name() == 'cli')
			print "Could not send e-mail message to ".$emailaddress;
		elseif($notice && !$link_with_notice)
			errormess("Could not send e-mail message to ".$emailaddress,'no');
		elseif($notice && $link_with_notice)
			errormess("Could not send e-mail message to ".$emailaddress);
		return false;
	}

	ini_restore('sendmail_from');
}

// function taken from user comments from php.net site
function get_mp3_len($file) 
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$rate1 = array(0, 32, 64, 96, 128, 160, 192, 224, 256, 288, 320, 352, 384, 416, 448, "bad");
	$rate2 = array(0, 32, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320, 384, "bad");
	$rate3 = array(0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320, "bad");
	$rate4 = array(0, 32, 48, 56, 64, 80, 96, 112, 128, 144, 160, 176, 192, 224, 256, "bad");
	$rate5 = array(0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160, "bad");

	$bitrate = array(
			'1'  => $rate5,
			'2'  => $rate5,
			'3'  => $rate4,
			'9'  => $rate5,
			'10' => $rate5,
			'11' => $rate4,
			'13' => $rate3,
			'14' => $rate2,
			'15' => $rate1
		);

	$sample = array(
			'0'  => 11025,
			'1'  => 12000,
			'2'  => 8000,
			'8'  => 22050,
			'9'  => 24000,
			'10' => 16000,
			'12' => 44100,
			'13' => 48000,
			'14' => 32000
		);

	$fd = fopen($file, 'rb');
	$header = fgets($fd, 5);
	fclose($fd);

	$bits = "";
	while (strlen($header) > 0) {
		//var_dump($header);
		$bits .= str_pad(base_convert(ord($header{0}), 10, 2), 8, '0', STR_PAD_LEFT);
		$header = substr($header, 1);
	}

	$bits = substr($bits, 11); // lets strip the frame sync bits first.

	$version = substr($bits, 0, 2); // this gives us the version
	$layer = base_convert(substr($bits, 2, 2), 2, 10); // this gives us the layer
	$verlay = base_convert(substr($bits, 0, 4), 2, 10); // this gives us both

	$rateidx = base_convert(substr($bits, 5, 4), 2, 10); // this gives us the bitrate index
	$sampidx = base_convert($version.substr($bits, 9, 2), 2, 10); // this gives the sample index
	$padding = substr($bits, 11, 1); // are we padding frames?

	$rate = $bitrate[$verlay][$rateidx];
	$samp = $sample[$sampidx];

	$framelen = 0;
	$framesize = 384; // Size of the frame in samples
	if ($layer == 3) { // layer 1?
		$framelen = (12 * ($rate * 1000) / $samp + $padding) * 4;
	} else { // Layer 2 and 3
		$framelen = 144 * ($rate * 1000) / $samp + $padding;
		$framesize = 1152;
	}

	$headerlen = 4 + ($bits{4} == 0 ? '2' : '0');

	return (filesize($file) - $headerlen) / $framelen / ($samp / $framesize);
}

function arr_to_csv($file_name, $arr, $formats=null, $func="write_in_file", $title_text=null)
{
	global $download_option;

	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $upload_path;

	$format = ".csv";

	$date = date("YMd_H_i_s");
	$file = $upload_path."/$file_name$format";
	if (is_file($file)) {
		unlink($file);
	}
	$fh = fopen($file, "w") or die("Can't open file for writting: ".$file);

	if ($title_text)
		fwrite($fh,$title_text."\r\n");
	if (!$func)
		$func = "write_in_file";

	$sep = ",";
	$func($fh, $formats, $arr, $sep);
	fclose($fh);
	chmod($file, 0777);

	if (isset($download_option) && $download_option=="direct_file")
		print "Content was exported. <a href=\"$upload_path/$file_name$format\">Download</a>";
	else
		print "Content was exported. <a href=\"download.php?file=$file_name$format\">Download</a>";
}

function mysql_in_file($fh, $formats, $array, $sep)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$col_nr = 0;
	
	if (!$formats && mysql_num_rows($array))
		for($i=0; $i<mysql_num_fields($array); $i++)
			$formats[mysql_field_name($array,$i)] = mysql_field_name($array,$i);
	if ($formats!="no") {
		foreach($formats as $column_name => $var_name)
		{
			$exploded = explode(":",$column_name);
			if(count($exploded)>1)
				$name = $exploded[1];
			else{
				$name = $column_name;
				if(substr($column_name, 0, 9) == "function_")
					$name = substr($column_name,9);
				if(is_numeric($column_name))
					$name = $var_name;
			}
			$val = str_replace("_"," ",ucfirst($name));
			$val = str_replace("&nbsp;"," ",$val);
			$val = ($col_nr) ? $sep."\"$val\"" : "\"$val\"";
			fwrite($fh, $val);
			$col_nr++;
		} 
		fwrite($fh,"\n");
	}
	//for($i=0; $i<mysql_num_rows($array); $i++) 
	while($row = mysql_fetch_assoc($array))
	{
		$col_nr = 0;

		foreach($formats as $column_name=>$names_in_array)
		{
			$use_vars = explode(",", $names_in_array);
			$exploded_col = explode(":", $column_name);
			$column_value = '';

			if(substr($exploded_col[0],0,9) == "function_") 
			{
				$function_name = substr($exploded_col[0],9,strlen($exploded_col[0]));
				if(count($use_vars)) 
				{
					$params = array();
					for($var_nr=0; $var_nr<count($use_vars); $var_nr++)
						array_push($params, $row[$use_vars[$var_nr]]);
					$column_value = call_user_func_array($function_name,$params);
				}
			}elseif(isset($row[$names_in_array])){
				$column_value = $row[$names_in_array];
			}
			if(!strlen($column_value))
				$column_value = "";
			$column_value = "\"=\"\"".$column_value."\"\"\"";
			if ($col_nr)
				$column_value =  $sep.$column_value;
			fwrite($fh,$column_value);
			$col_nr++;
		}

		fwrite($fh,"\n");
	}
}
/**
 * Writes the data given in $array in a specific format given in $formats into a given file resource $fh
 * @param $fh Resource. The file handle opened in write mode
 * @param $formats Array or String. The format of the columns that will be written in file
 * Ex: array(":name_column"=>"name_key_from_array"); or "no". If set to "no" $formats will be
 * build from $array, the name of the columns are the same as the ones that will be written in file
 * @param $array Array. The data values specific for each column.
 * @param $sep String. The separator used between the columns in file.
 * @param $key_val_arr Bool. True if the columns are set directly in $array. 
 * False to get directly the values from arrays of arrays. 
 * @param $col_header Bool. True to set header parameters from $formats 
 * @param $keep_bools Bool. False by default to not keep boolean values.
 */ 
function write_in_file($fh, $formats, $array, $sep, $key_val_arr=true, $col_header=true, $keep_bools=false)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$col_nr = 0;
	
	if (!$formats && count($array))
		foreach ($array[0] as $name=>$val)
			$formats[$name] = $name;
	if ($col_header && $formats!="no") {
		foreach ($formats as $column_name => $var_name)
		{
			$exploded = explode(":",$column_name);
			if (count($exploded)>1)
				$name = $exploded[1];
			else {
				$name = $column_name;
				if (substr($column_name, 0, 9) == "function_")
					$name = substr($column_name,9);
				if (is_numeric($column_name))
					$name = $var_name;
			}
			$val = str_replace("_"," ",ucfirst($name));
			$val = str_replace("&nbsp;"," ",$val);
			$val = ($col_nr) ? $sep."\"$val\"" : "\"$val\"";
			fwrite($fh, $val);
			$col_nr++;
		} 
		fwrite($fh,"\n");
	}

	for ($i=0; $i<count($array); $i++) 
	{
		$col_nr = 0;
		if ($key_val_arr) {
			foreach ($formats as $column_name=>$names_in_array)
			{
				$use_vars = explode(",", $names_in_array);
				$exploded_col = explode(":", $column_name);
				$column_value = '';

				if (substr($exploded_col[0],0,9) == "function_") 
				{
					$function_name = substr($exploded_col[0],9,strlen($exploded_col[0]));
					if (count($use_vars)) 
					{
						$params = array();
						for ($var_nr=0; $var_nr<count($use_vars); $var_nr++)
							array_push($params, $array[$i][$use_vars[$var_nr]]);
						$column_value = call_user_func_array($function_name,$params);
					}
				} elseif (isset($array[$i][$names_in_array])) {
					$column_value = $array[$i][$names_in_array];
				} elseif (substr($names_in_array, 0, 15) == "retrieve_column") {
					$col = explode(":", $names_in_array);
					$function_name = $col[0];
					$params = array($col[1] => array());
					if (isset($array[$i][$col[1]]))
						$params = array($array[$i][$col[1]]);
					for ($no=2; $no < count($col); $no++) 
						array_push($params,$col[$no]);
					$column_value = call_user_func_array($function_name,$params);
				}

				if (!$keep_bools) {
					if (!strlen($column_value))
						$column_value = "";
					$column_value = "\"=\"\"".$column_value."\"\"\"";
				} else {
					if (!is_bool($column_value) && $column_value!==null)
						$column_value = "\"=\"\"".$column_value."\"\"\"";
					
				}
				if ($col_nr)
					$column_value =  $sep.$column_value;
				fwrite($fh,$column_value);
				$col_nr++;
			}
		} else {
			for ($j=0; $j<count($array[$i]); $j++) {
				$column_value = "\"".$array[$i][$j]."\"";
				if ($col_nr)
					$column_value =  $sep.$column_value;
				fwrite($fh, $column_value);
				$col_nr++;
			}
		}
		fwrite($fh,"\n");
	}
}

/**
 * function used to check on_page authentication
 * used in debug_all.php
 */ 
function is_auth($identifier)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global ${"pass_".$identifier."_page"};

	if (isset($_SESSION["pass_$identifier"]) || !isset(${"pass_".$identifier."_page"}) || !strlen(${"pass_".$identifier."_page"}))
		return true;
	return false;
}

/**
 *  function used to check on_page authentication/authenticate
 *  used in debug_all.php
 */ 
function check_auth($identifier)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global ${"pass_".$identifier."_page"};

	if (strlen(${"pass_".$identifier."_page"}) && !isset($_SESSION["pass_$identifier"])) {
		$pass = (isset($_REQUEST["pass_$identifier"])) ? $_REQUEST["pass_$identifier"] : '';
		if ($pass != ${"pass_".$identifier."_page"}) 
			return false;
		$_SESSION["pass_$identifier"] = true;
	}
	return true;
}

/**
 * Generates a numeric token with a specified length
 *
 * @param $length the length of the token
 * @return String containing the random token
 */ 
function generateNumericToken($length)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$str = "";
	for ($i=0; $i<$length; $i++)
	{
		$c = mt_rand(0,9);
		$str .= $c;
	}
	return $str;
}

function display_bit_field($val)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$bits = array("on","true","enabled","enable","yes","1","t");
	if (in_array($val,$bits)) {
		return "yes";
	}

	return "no";
}

function display_field($field_name,$field_format,$form_identifier='',$css=null)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$q_mark = false;

	$value = NULL;
	if(isset($field_format["value"]))
		$value = $field_format["value"];

	if (!strlen($value) && isset($field_format["cb_for_value"]) && is_callable($field_format["cb_for_value"]["name"])) {
		if (count($field_format["cb_for_value"])==2)
			$value = call_user_func_array($field_format["cb_for_value"]["name"],$field_format["cb_for_value"]["params"]);
		else
			$value = call_user_func($field_format["cb_for_value"]["name"]);
	}

	$display = (isset($field_format["display"])) ? $field_format["display"] : "text";
	$var_name = (isset($field_format[0])) ? $field_format[0] : $field_name;

	$res = "";
	switch($display)
	{
		case "select":
		case "mul_select":
		case "select_without_non_selected":
			$res .= '<select class="'.$css.'" name="'.$form_identifier.$field_name.'" id="'.$form_identifier.$field_name.'" ';
			if(isset($field_format["javascript"]))
				$res .= $field_format["javascript"];
			if($display == "mul_select")
				$res .= ' multiple="multiple" size="5"';
			$res .=  '>';
			if($display != "mul_select" && $display != "select_without_non_selected")
				$res .=  '<option value="">Not selected</option>';

			// PREVIOUS implementation when only 0 key could be used for dropdown options
			// $options = (is_array($var_name)) ? $var_name : array();

			if ($display != "mul_select") {
				// try gettting it from value
				if ($value && is_array($value))
					$options = $value;
				elseif (is_array($var_name))
					$options = $var_name;
				else
					$options = array();
			} else {
				if (is_array($var_name))
					$options = $var_name;
				else
					$options = array();
			}

			if (isset($field_format["selected"]))
				$selected = $field_format["selected"];
			elseif(isset($options["selected"]))
				$selected = $options["selected"];
			elseif(isset($options["SELECTED"]))
				$selected = $options["SELECTED"];
			else
				$selected = '';
			foreach ($options as $var=>$opt) {
				if ($var === "selected" || $var === "SELECTED")
					continue;
				$css = (is_array($opt) && isset($opt["css"])) ? 'class="'.$opt["css"].'"' : "";
				if(is_array($opt) && isset($opt[$field_name.'_id'])) {
					$optval = $field_name.'_id';
					$name = $field_name;

					$printed = trim($opt[$name]);
					if (substr($printed,0,4) == "<img") {
						$expl = explode(">",$printed);
						$printed = $expl[1];
						$jquery_title = " title=\"".str_replace("<img","",$expl[0])."\"";
					} else
						$jquery_title = '';

					if ( (string)$opt[$optval] === "$selected" || (is_array($selected) && in_array($opt[$optval],$selected))) {
						$res .= '<option value=\''.$opt[$optval].'\' '.$css.' SELECTED ';
						if($opt[$optval] == "__disabled")
							print ' disabled="disabled"';
						$res .= $jquery_title;
						$res .= '>' . $printed . '</option>';
					} else {
						$res .= '<option value=\''.$opt[$optval].'\' '.$css;
						if($opt[$optval] == "__disabled")
							print ' disabled="disabled"';
						$res .= $jquery_title;
						$res .= '>' . $printed . '</option>';
					}
				}else{
					if ($opt == $selected ||  (is_array($selected) && in_array($opt[$optval],$selected)))
						$res .= '<option '.$css.' SELECTED >' . $opt . '</option>';
					else
						$res .= '<option '.$css.'>' . $opt . '</option>';
				}
			}
			$res .= '</select>';
			break;
		case "checkbox":
		case "checkbox-readonly":
			$res .= '<input class="'.$css.'" type="checkbox" name="'.$form_identifier.$field_name.'" id="'.$form_identifier.$field_name.'"';
			if (bool_value($value) || $value=="on")
				$res .= " CHECKED ";
			if (isset($field_format["javascript"]))
				$res .= $field_format["javascript"];
			if ($display=="checkbox-readonly")
				$res .= " readonly=''";
			$res .= '/>';
			break;
		case "text":
		case "password":
		case "file":
		case "hidden":
		case "text-nonedit":
			$res .= '<input class="'.$css.'" type="'.$display.'" name="'.$form_identifier.$field_name.'" id="'.$form_identifier.$field_name.'"';
			if ($display != "file" && $display != "password") {
				if (!is_array($value))
					$res .= ' value='.html_quotes_escape($value);
				
				else {
					
					if (isset($field_format["selected"]))
						$selected = $field_format["selected"];
					elseif (isset($value["selected"]))
						$selected = $value["selected"];
					elseif (isset($value["SELECTED"]))
						$selected = $value["SELECTED"];
					else
						$selected = '';
					$res .= ' value='.html_quotes_escape($selected);
				}
			}
			if(isset($field_format["javascript"]))
				$res .= $field_format["javascript"];
			if($display == "text-nonedit")
				$res .= " readonly=''";
			if(isset($field_format["autocomplete"]))
				$res .= " autocomplete=\"".$field_format["autocomplete"]."\"";
			if($display != "hidden" && isset($field_format["comment"])) {
				$q_mark = true;
				if (is_file("images/question.jpg"))
					$res .= '>&nbsp;&nbsp;<img class="pointer" src="images/question.jpg" onClick="show_hide_comment(\''.$form_identifier.$field_name.'\');"/>';
				else
					$res .= '&nbsp;&nbsp;<font style="cursor:pointer;" onClick="show_hide_comment(\''.$form_identifier.$field_name.'\');"> ? </font>';
			} else
				$res .= '>';
			break;
		case "fixed":
			if(strlen($value))
				$res .= $value;
			else
				$res .= "&nbsp;";
			break;
	}
	if(isset($field_format["comment"]))
	{
		$comment = $field_format["comment"];
		if(!$q_mark) {
			if (is_file("images/question.jpg"))
				$res .= '&nbsp;&nbsp;<img class="pointer" src="images/question.jpg" onClick="show_hide_comment(\''.$form_identifier.$field_name.'\');"/>';
			else
				$res .= '&nbsp;&nbsp;<font style="cursor:pointer;" onClick="show_hide_comment(\''.$form_identifier.$field_name.'\');"> ? </font>';
		}
		$res .= '<font class="comment" style="display:none;" id="comment_'.$form_identifier.$field_name.'">'.$comment.'</font>';
	}
	return $res;
}

function stdClassToArray($d) 
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	if (is_object($d)) {
		// Gets the properties of the given object
		// with get_object_vars function
		$d = get_object_vars($d);
	}

	if (is_array($d)) {
		/*
		* Return array converted to object
		* Using __FUNCTION__ (Magic constant)
		* for recursive call
		*/
		return array_map(__FUNCTION__, $d);
	}
	else {
		// Return array
		return $d;
	}
}

function get_param($array,$name)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	return isset($array[$name]) ? $array[$name] : null;
}

function missing_param($param)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	return ($param === null) || ($param == "");
}

function generic_tabbed_settings($options,$config,$section_to_open=array(),$show_advanced=false, $form_name=null,$form_submit=true,$specif_ind="",$custom_css=null)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $module, $tabs_cb;

	if (!$form_name)
		$form_name = $module;

	print '<table class="sections '.$custom_css.'" cellspacing="0" cellpadding="0">'."\n";
	print '<tr>'."\n";
	$total_options = count($options);
	$disp_show = "";
	$disp_hide = ' style="display:none;"';

	$initial_show_advanced = $show_advanced;
	if (!isset($section_to_open["-"]))
		$show_advanced = true;

	$first_open = NULL;
	foreach($section_to_open as $sect_name=>$def) {
		$first_open = $sect_name;
		$first_subsec = (!strlen($def)) ? "-" : $def;
		break;
	}

	for ($i=0; $i<$total_options; $i++) {
		$name = $options[$i];
		$js_name = $specif_ind.$i;

		$css = ($first_open!=$name && !(!$i && $first_open=="-")) ? "section" : "section_selected";
		if (!$i)
			$css .= " basic";
		$css .= " $custom_css";
		if ($first_open==$name || ($first_open=="-" && !$i))
			$css .= "_selected";
		$td_css = (!$i) ? "section basic $custom_css" : "$custom_css";

		print '<td class="'.$td_css.'" id="td_'.$js_name.'">';
		if ($i) {
			print '<div class="fill_section" id="fill_'.$js_name.'" ';
			if ($show_advanced)
				print $disp_hide;
			print '>&nbsp;</div>';
		}

		print '<div class="'.$css.'" onClick="show_section(\''.$i.'\','.$total_options.',\''.$specif_ind.'\',\''.$custom_css.'\');" id="tab_'.$js_name.'" ';
		if ($i && !$show_advanced)
			print $disp_hide;
		print '>'.ucwords($name);

		if (!$i && $initial_show_advanced!==NULL) {
			$img = ($show_advanced) ? "sm_hide.png" : "sm_show.png";
			print "&nbsp;&nbsp;<img src=\"images/$img\" class=\"pointer\" id=\"img_show_tabs$specif_ind\" onclick=\"show_all_tabs($total_options,'$specif_ind');\" width=\"11px\" height=\"11px\"/>";
		}

		print '</div></td>'."\n";
		print '<td class="space_section">&nbsp;</td>'."\n";
	}
	print '<td class="fillspace_section">&nbsp;</td>'."\n";
	print '</tr>'."\n";
	print '<tr>'."\n";
	$colspan = $total_options*2+1;
	print '<td class="section_content" colspan="'.$colspan.'">'."\n";

	for ($i=0; $i<$total_options; $i++) {
		$name = $options[$i];
		$js_name = $specif_ind.$i;
		$current_fields = $config[$options[$i]];
		$disp = ($first_open!=$options[$i] && !(!$i && $first_open=="-")) ? " style='display:none;'" : '';

		print "<div class='section_fields' id='sect_$js_name'$disp>";
		if (isset($current_fields["subsections"])) {
			$sub_sections = $current_fields["subsections"];
			$names = array();
			foreach ($sub_sections as $name=>$fields)
				$names[] = $name;
			generic_tabbed_settings($names, $sub_sections, array("$first_subsec"=>""), false, "", false, $js_name."_", "subsec");
		} else {
			if (isset($tabs_cb))
				$tabs_cb($name, $current_fields);
			editObject(NULL,$current_fields,NULL,"no");
		}
		print "</div>";
	}

	if ($form_submit===true) {
		print '<div class="section_submit">';
		//print '<input type="button" value="Save" onClick="submit_form(\''.$form_name.'\')" />&nbsp;';
		//print '<input type="reset" value="Reset" onClick="reset_form(\''.$form_name.'\')"/>&nbsp;';
		print '<input type="submit" value="Save" />&nbsp;';
		print '<input type="reset" value="Reset" />&nbsp;';
		print cancel_button('','Cancel');
		print '&nbsp;</div>';
	} elseif (is_array($form_submit)) {
		print '<div class="section_submit">';
		foreach($form_submit as $name=>$func)
			if ($func!="no_cancel")
				print '<input type="button" value="'.$name.'" onClick="'.$func.'(\''.$form_name.'\')" />&nbsp;';
		if (!in_array("no_cancel",$form_submit))
			print cancel_button('','Cancel');
		print '&nbsp;</div>';
	}
	print '</td>';
	print '</tr>';
	print '</table>';
}

function load_page($page, $timeout=0)
{
	$page = build_link($page);

	if (!is_numeric($timeout))
		$timeout = 0;
	
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	if (headers_sent() || $timeout)
		print "<meta http-equiv=\"REFRESH\" content=\"$timeout;url=$page\">";
	else
		header("Location: $page");
}

function build_link($page)
{
	if (substr($page,0,7)=="http://" || substr($page,0,8)=="https://")
		return $page;

	$current = $_SERVER["PHP_SELF"];
	$current = explode("/",$current);
	$current[count($current)-1] = $page;
	$current = implode("/",$current);
	
	if (isset($_SERVER["SERVER_PORT"]) && $_SERVER["SERVER_PORT"]!=80)
		$page = $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$current;
	else
		$page = $_SERVER["SERVER_NAME"].$current;
	$page = (!isset($_SERVER["HTTPS"])) ? "http://".$page : "https://".$page;

	return $page;
}

function include_javascript($module=null, $set_version=false)
{
	if (is_file("ansql/javascript.js")) {
		// include the ansql javascript functions
		if (!$set_version) {
			print '<script language="javascript" type="text/javascript" src="ansql/javascript.js"></script>';
		} else { 
			$src = set_file_version("ansql/javascript.js");
			print '<script language="javascript" type="text/javascript" src="'.$src.'"></script>';
		}
	}

	if (is_file("javascript/generic_js.php"))
		include("javascript/generic_js.php");

	if ($module && is_file("javascript/$module.php"))
		include("javascript/$module.php");
}

/**
 * Builds global javascript array map variable: required_fields 
 * Ex on generated object: required_fields={"username":"username", "contact_info":"Contact information"}
 * @param $form_fields Array. Fields
 * @param $exclude_fields Array. Array with key step names that should be excluded
 * Ex: array("step_name", "step_image", "step_description") -- usage when used from Wizard class
 */
function requiredFieldsJs($form_fields, $excluded_fields)
{
	?><script>
	// save the name of the required fields: field_name:column_name
	// column_name is the field the user sees, field_name is the key in the current step array
	required_fields = {};
	<?php

	foreach ($form_fields as $field_name=>$field_def) {
		if (isset($excluded_fields[$field_name]))
			continue;
		if (isset($field_def["display"])) {
			if ($field_def["display"] == "message" || $field_def["display"] == "fixed")
				continue;
		}

		if ( (isset($field_def["required"]) && ($field_def["required"]===true || $field_def["required"]=="true")) ||
		    (isset($field_def["compulsory"]) && ($field_def["compulsory"]===true || $field_def["compulsory"]=="true"))) {
				$real_name = isset($field_def["column_name"]) ? $field_def["column_name"] : str_replace("_", " ", ucfirst($field_name));
				echo "required_fields[\"".$field_name."\"]=\"".$real_name."\";";
		}
	}
	?>
	</script><?php
}

/**
 * Build generic search box: Dropdown to select option to search by and input for searched value
 * @param $search_options Array. Options to search by
 * @param $custom_fields Array. Custom search fields that will be added before the $search_options.
 * @param $value_readonly Bool. If true set 'Value' field as readonly, when 'Search by' is not selected.
 * Ex: array("title"=>array("Search1", "Search2"),array("<input type.../>", "<select>..</select>"))
 */
function generic_search($search_options, $custom_fields=array(), $value_readonly=false)
{
	$search_options = format_for_dropdown($search_options);
	$search_options["selected"] = getparam("col");

	$title = array("Search by", "Value", "&nbsp;");
	$ro = (!$value_readonly) ? "undefined" : "true";
	$fields = array(array(
			build_dropdown($search_options, "col", true, "", "", 'onchange="clean_cols_search('.$ro.')"'),
			"<input type=\"text\" value=". html_quotes_escape(getparam("col_value"))." name=\"col_value\" id=\"col_value\" size=\"10\"/>",
			"<input type=\"submit\" value=\"Search\" />"
		));

	if ($value_readonly && !getparam("col_value"))
		$fields[0][1] = "<input type=\"text\" value=". html_quotes_escape(getparam("col_value"))." name=\"col_value\" id=\"col_value\" size=\"10\" readonly/>";

	if (isset($custom_fields["title"]) && isset($custom_fields["fields"])) {
		$title = array_merge($custom_fields["title"],$title);
		$fields = array(array_merge($custom_fields["fields"],$fields[0]));
	}

	start_form();
	if ($value_readonly)
		addHidden(null,array(),false,array("col_value", "col"));
	else
		addHidden(null,array(),false,array("col_value"));
	formTable($fields,$title);
	end_form();
}

/**
 * Get conditions from search box built with \ref generic_search
 * @param $custom_cols Array (key=>value). If column is key in this array it will be renamed to $value in returned arr
 * @return Array
 * Ex: array("username"=>"LIKE%mary%") / array("__sql_customer"=>"LIKE%entils%")
 */
function get_search_conditions($custom_cols=array())
{
	$col = getparam("col");
	$col_value = getparam("col_value");

	if (!strlen($col) || !strlen($col_value))
		return array();

	if (isset($custom_cols[$col]))
		return array($custom_cols[$col]=>"__LIKE%$col_value%");

	return array($col=>"__LIKE%$col_value%");
}

/**
 * Reads a file in reverse order and returns the last N lines 
 * as an array (N given as parameter).
 * @param $path String the path with the filename.
 * @param $line_count Integer the lines number that will be read. 
 * @param $block_size Integer the bytes that will be read.
 * @param $offset Integer the offset in bytes
 * @param $leftover String the remaining data read.
 * @param $lines Array the remaining lines from previous data read.
 * @returns Array with:
 *      - the lines readed from the end of the file, 
 *      - the value of the offset
 *      - the leftover string remained from previous reading data
 *      - the remaining lines already read
 */ 
function get_file_last_lines($path, $line_count, $block_size = 512, $offset = null, $leftover = '', $lines = array())
{
	if (count($lines) >= $line_count)
		return array(array_slice($lines, 0, $line_count), $offset, $leftover, array_slice($lines, $line_count));

	$fh = fopen($path, 'r');
	
	if ($offset === null)
		// go to the end of the file
		fseek($fh, 0, SEEK_END);
	else
		// go to the last offset in the file
		fseek($fh, $offset, SEEK_SET);


	do {
		// need to know whether we can actually go back
		// $block_size bytes
		$can_read = $block_size;
		if (ftell($fh) < $block_size)
			$can_read = ftell($fh);

		// go back as many bytes as we can
		// read them to $data and then move the file pointer
		// back to where we were.
		fseek($fh, -$can_read, SEEK_CUR);
		if ($can_read == 0)
			$data = '';
		else
			$data = fread($fh, $can_read);
		$data .= $leftover;
		fseek($fh, -$can_read, SEEK_CUR);
		
		// remove the last element from data
		if (substr($data, strlen($data)-1, strlen($data)) == "\n")
			$data = substr($data, 0, strlen($data)-1);

		// split lines by \n. Then reverse them,
		// now the last line is most likely not a complete
		// line which is why we do not directly add it, but
		// append it to the data read the next time.
		$split_data = array_reverse(explode("\n", $data));
		$new_lines = array_slice($split_data, 0, -1);
		$lines = array_merge($lines, $new_lines);
		$leftover = $split_data[count($split_data) - 1];
	} while(count($lines) < $line_count && ftell($fh) != 0);


	if (ftell($fh) == 0) {
		$lines[] = $leftover;
		$leftover = "";
	}
	
	$offset = ftell($fh);
	fclose($fh);
	
	// Usually, we will read too many lines, correct that here.
	return array(array_slice($lines, 0, $line_count), $offset, $leftover, array_slice($lines, $line_count));
}

// Replace \n with <br/> and " " with &nbsp;
function format_comment($text)
{
	$text = str_replace("\n","<br/>",$text);
	$text = str_replace(" ","&nbsp;",$text);
	return $text;
}

/**
 * Build net addresses dropdown from received data
 * The types of dropdown are 'select_without_non_selected' || 'select'
 * @param $net_interfaces Array The interfaces with their associated IPs
 * @param $select Bool The type of dropdown, if true 'select' otherwize 'select_without_non_selected'
 * @param $type_ip String The type of IP: 'ipv4', 'ipv6', 'both' to be displayed in dropdown
 * @param $dropdown_key String The name of the dropdown from FORM
 * @return $interfaces_ips Array The format used to display the dropdown
 */
function build_net_addresses_dropdown($net_interfaces, $select=false, $type_ip = "both", $dropdown_key = "local")
{
	$interfaces_ips = "";

	foreach ($net_interfaces["net_address"] as $key=>$interface) {
		if (!$select)
			$interfaces_ips[] = array($dropdown_key."_id"=>"__disabled", $dropdown_key=>"------".$interface["interface"]."------");

		if ($type_ip == "both" || $type_ip == "ipv4") {
			if (!isset($interface["ipv4"]))
				continue;
			foreach ($interface["ipv4"] as $k=>$ip) {
				if (!$select)
					$interfaces_ips[] = array($dropdown_key."_id"=>$ip["address"], $dropdown_key=>$ip["address"]);
				else
					$interfaces_ips[] = $ip["address"];
			}
		}
		if ($type_ip == "both" || $type_ip == "ipv6") {
			if (!isset($interface["ipv6"]))
				continue;
			foreach ($interface["ipv6"] as $k=>$ip) {
				if (!$select)
					$interfaces_ips[] = array($dropdown_key."_id"=>$ip["address"], $dropdown_key=>$ip["address"]);
				else
					$interfaces_ips[] = $ip["address"];
			}
		}
	}
	return $interfaces_ips;
}

/**
 * Change the old key with a new key in an specific array used to build 
 * a dropdown that is used to display:'select_without_non_selected'
 */   
function change_keys_dropdown($array, $old_key, $new_key)
{
	$old_keys = array($old_key."_id", $old_key);
	$new_keys = array($new_key."_id", $new_key);

	for ($i=0; $i<count($array); $i++) {
		for ($j=0; $j<2; $j++) {
			if (isset($array[$i][$old_keys[$j]])) {
				$array[$i][$new_keys[$j]] = $array[$i][$old_keys[$j]];
				if (is_array($array[$i]) && isset($array[$i][$old_keys[$j]]))
					unset($array[$i][$old_keys[$j]]);
			}
		}
	}

	return $array;
}

/**
 * Allow flushing buffers on request
 */
function flush_buffers()
{
    @ob_end_flush();
    @ob_flush();
    
    // after testing this seemed enough
    flush();

    ob_start(); 
}

/**
 * Displays node statistics. 
 * @param $query_stats Array The statistics of a product(HSS, UCN, SDR, etc)
 * @param $product_name Text The name of the product
 */ 
function display_query_stats($query_stats, $product_name)
{
/*
 * {"code":0,
 *  "stats":{
 *          "engine":{"version":"5.5.1","revision":6202,"nodename":"ybtsUNCONFIG","runid":1491988359,"plugins":23,"inuse":1,"handlers":222,"hooks":4,"messages":0,
 *                     "maxqueue":2,"messagerate":2,"maxmsgrate":14,"enqueued":745014,"dequeued":745014,"dispatched":745061,"supervised":true,"runattempt":9,"lastsignal":0,
 *                     "threads":39,"workers":10,"mutexes":197,"semaphores":18,"acceptcalls":"accept","congestion":0},
 *          "uptime":{"wall":620866,"user":84312,"kernel":44640},
 *          "bladerf":{"ifaces":1},
 *          "yrtp":{"chans":0,"mirrors":0},
 *          "sip":{"routed":0,"routing":0,"total":0,"chans":0,"transactions":0},
 *          "mbts":{"state":"RadioUp"},
 *          "ybts":{"count":0}
 *          }
 * }
 *
 * {"code":0,
 *  "stats":{
 *            "engine":{"version":"5.5.1","revision":1815,"nodename":"hostedcore_test","plugins":27,"inuse":0,"handlers":354,"hooks":5,"messages":0,"supervised":true,
 *                      "runattempt":1,"lastsignal":0,"threads":24,"workers":5,"mutexes":297,"semaphores":0,"acceptcalls":"accept","congestion":0},
 *            "ucn_gmsc":{"inbound":0,"routed":0,"diverted":0,"failed":0},
 *            "ucn_pgw":{"apns":0,"sessions":0,"bearers":0,"reservations":0,"redirecting":0},
 *            "ucn_ati":{"sent":0,"recv":0,"errs":0,"fail":0},
 *            "ucn_gtt":{"local":0,"stp":0,"back":0,"fail":0}
 *          }
 * }
 *
 * {"code":0,
 *  "stats":{
 *           "engine": {"version":"5.5.1","revision":1815,"nodename":"hostedcore_test","plugins":14,"inuse":0,"handlers":180,"hooks":4,"messages":0,"supervised":true,
 *           			"runattempt":1,"lastsignal":0,"threads":22,"workers":5,"mutexes":229,"semaphores":1,"acceptcalls":"accept","congestion":0},
 *           "hss_cluster":{"nodename":"hostedcore_test","remote":0,"state":"standalone"},
 *           "hss_repair":{"clean":true,"cycles":11087,"checks":2510095,"errors":0,"fixed":0},
 *           "auc_map":{"auth2g":0,"auth3g":0,"auth4g":0,"resyncs":0,"unknowns":0,"illegals":0,"inactives":0,"reports":0},
 *           "hss_gtt":{"local":0,"imsi":0,"msisdn":0,"stp":0,"back":0,"fail":0},
 *           "auc_diam":{"auth2g":0,"auth3g":0,"auth4g":0,"resyncs":0,"unknowns":0,"illegals":0,"inactives":0}
 *           }
 * }
 *
 */ 
	if (!isset($query_stats["stats"]))
		return;

	if ($product_name == 'hss') {
		display_hlr($query_stats["stats"]);
		return;
	}

	$stats = $query_stats["stats"];
	if ($product_name == 'Yate-SDR') {
		$stats_order = array("engine","uptime","bladerf","mbts","sip","yrtp","ybts");

		foreach ($stats_order as $k=>$stat_nm) {
			if (isset($stats[$stat_nm])) {
				$reordered_stats[$stat_nm] = $stats[$stat_nm];
				unset($stats[$stat_nm]);
			}
		}

		$new_stats = array_merge($reordered_stats, $stats);
		$stats = $new_stats;
	}

	$max = 0;
	foreach (array_keys($stats) as $i => $k) {
		if (!$i)
			continue;
		
		$wide_line = have_wide_line($stats[$k]);
		
		// count of rows under property $k
		$count = count($stats[$k]);
		// if field has more than 8 properties, display it split in 2
		if ($count>8 && !$wide_line)
			$count = $count/2;
		$max = max($max, $count);
		if (!($i % 3)) {
			$length[(int)($i / 3) - 1] = $max;
			$max = 0;
		}
	}
	if ($max)
		$length[(int)floor($i / 3)] = $max;

	$display_splitted_count = 8;
	$table_css = 'query_stats';
	$td_css = 'container_qs';
	$i = 0;
	$j = 0;
	
	print "<table class='container_query_stats' cellspacing='0' cellpadding='0'>";
	print "<tr>";
	foreach ($stats as $stat_name=>$stat_props) {
		if ($i==0) {
			$rowspan = (int)floor(count($stats) / 3) + 1;
			print "<td class='$td_css' rowspan='".$rowspan."'>";
		} else
			print "<td class='$td_css'>";
		display_stats_format(count($stat_props),$display_splitted_count,$stat_props,$stat_name,$table_css,$length[(int)floor($j / 3)]);
		print "</td>";
		$i++;
		if ($i==(4+$j)) {
			print "</tr><tr>";
	//		print "</tr><tr><td></td>";
			$td_css = 'container_right_modif';
			$j=$j+3;
		}
	}
	print "</tr>";
	print "</table>";
}

function have_wide_line($stats)
{
	foreach ($stats as $key=>$val) {
		$line_width = strlen($key) + strlen($val);
		if ($line_width > 40) {
			return true;
		}
	}
	return false;
}

function display_splitted_props($stats,$stat_name, $display)
{
	$i=1;
	print "<table class='$display $stat_name' cellspacing='0' cellpadding='0'>";
	print "<tr> <td class='prop_lev1' colspan='4'>".$stat_name." </td></tr>";
	
	$wide_line = have_wide_line($stats);	
	foreach ($stats as $prop=>$val) {
		$val = ($val===false) ? "false" : $val;
		$val = ($val===true) ? "true" : $val;
		if ($i%2==1 || $wide_line)
			print "<tr>";
		print "<td class='cat_prop2'>".$prop."</td>";
		print "<td class='cat_prop2_desc'>".$val."</td>";
		if ($i%2==0 || $wide_line)
			print "</tr>";
		$i++;
	}
	if (count($stats)%2!=0)
		print "<td class='cat_prop2'>&nbsp;</td><td class='cat_prop2_desc'>&nbsp;</td></tr>";
	print "</table>";
}

/**
 * Split HSS query stats into lines using matchin rules
 */ 
function split_lines($stats, $match, $default)
{
	$ret = array();
	foreach ($stats as $prop=>$details) {
		$line = $default;
		foreach ($match as $preg=>$ls)
			if (preg_match($preg,$prop)) {
				$line = $ls;
				break;
			}
		if ($line < 0)
			continue;
		if (!isset($ret[$line]))
			$ret[$line] = array();
		$ret[$line][$prop] = $details;
	}
	return $ret;
}

/**
 * Display hss node stats
 * The order logic of each line displayed in table:
 * LEFT:
 * 		line1: engine stats,
 * 		line2: uptime stats
 * RIGHT:
 *    line1: match /hss/: hss_cluster, hss_repair, hss_gtt
 *    line2: match /map/: auc_map, hss_map 
 *    line3: match /diam/: diameter, auc_diam, hss_diam
 *    line4: match /sig/: sig_routers, sig_links, sig_sccp
 *    line5: the rest that don't match any of the above
 *
 * Compact the lines if the current line and the next one has less 
 * than 3 blocks
 */ 
function display_hlr($stats)
{
	$default = 4;
	$split_stats = array(
		"left"	=> split_lines($stats, array(
				"/engine/"	=> 0,
				"/uptime/"	=> 1,
			), -1),
		"right" => split_lines($stats, array(
				"/engine/"	=> -1,
				"/uptime/"	=> -1,
				"/map/"		=> 1,
				"/diam/"	=> 2,
				"/sig/"		=> 3,
				"/hss/"		=> 0,
			), $default)
		);

	// compact the lines 
	$comp = array();
	if (!isset($split_stats['right'][$default]))
		$default--;
	for ($i = 0; $i < $default; $i++) {
		$curr = $split_stats['right'][$i];
		$next = $split_stats['right'][$i+1];
		if (count($curr) + count($next) <= 3) {
			$split_stats['right'][$i+1] = array_merge($curr, $next);
			continue;
		}
		$comp[] = $curr;
	}
	$comp[] = $split_stats['right'][$default];
	$split_stats['right'] = $comp;

	$length = array();
	foreach ($split_stats['right'] as $i => $line) {
		$max = 0;
		foreach ($line as $prop) {
			$count = count($prop);
			if ($count>8)
				$count = $count/2;
			$max = max($max, $count);
		}
		$length[$i] = $max;
	}

	$display_splitted_count = 8;
    $table_css = 'query_stats';
    $td_css = 'container_qs';
	print "<table class='holdcontainer_query_stats' cellspacing='0' cellpadding='0'>";
	print "<tr>";
	foreach ($split_stats as $side=>$side_stats) {
		print "<td class='hold_stats'>";
		print "<table class='container_query_stats' cellspacing='0' cellpadding='0'>";
		print "<tr>";
		foreach ($side_stats as $line=>$stats) {
				print "<tr>";
				foreach ($stats as $stat_name=>$stat_props) {
					print "<td class='$td_css'>";
					display_stats_format(count($stat_props),$display_splitted_count,$stat_props,$stat_name,$table_css,$length[$line]);
					print "</td>";
				}
				print "</tr>";
		}
		print "</tr>";
		print "</table>";
		print "</td>";
	}
		print "</tr>";
	print "</table>";
}

function display_stats_format($stats_length, $max_columns, $cat_props, $prop, $table_css,$max_length)
{
	if ($stats_length > $max_columns) //if a category has more than 8 proprieties splitted into 2 columns
		display_splitted_props($cat_props, $prop, $table_css);
	else
		display_cat_prop($cat_props, $prop, $table_css,$max_length);
}

function display_cat_prop($stat_props,$stat_name,$display,$max_length)
{
	print "<table class='$display' cellspacing='0' cellpadding='0'>";
	print "<tr> <td class='prop_lev1'>".$stat_name." </td></tr>";

	foreach ($stat_props as $stat_name=>$stat_val) {
		print "<tr>";
		print "<td class='cat_lev2'>";
		print $stat_name;
		print "</td>";
		print "<td class='prop_lev2'>";
		if ($stat_val === true)
			$stat_val = "true";
		elseif ($stat_val === false)
			$stat_val = "false";
		print $stat_val;
		print "</td>";
		print "</tr>";
	}
		if (count($stat_props)<$max_length)
			fill_td($max_length-count($stat_props));
	print "</table>";
}

function fill_td($total)
{
	for ($i=0; $i<$total; $i++)
		print "<tr><td class='cat_lev2'>&nbsp;</td></tr>";
}

function bool_value($value) 
{
	if ($value==="t" || $value==="1" || $value===1)
		return true;
	return false;
}

function suffix_index($index)
{
	switch (substr($index,-1)) {
		case 1:
			$suffix = "st";
			break;
		case 2:
			$suffix = "nd";
			break;
		case 3:
			$suffix = "rd";
			break;
		default:
			$suffix = "th";
	}
	return $suffix;
}

/**
* Format a flat JSON string to make it more human-readable
* Thank you: https://github.com/GerHobbelt/nicejson-php
*
* @param string $json The original JSON string to process
*        When the input is not a string it is assumed the input is RAW
*        and should be converted to JSON first of all.
* @return string Indented version of the original JSON string 
*/
function json_format($json) 
{
	if (!is_string($json)) {
		if (phpversion() && phpversion() >= 5.4) {
			return json_encode($json, JSON_PRETTY_PRINT);
		}
		$json = json_encode($json);
	}
	
	$result      = '';
	$pos         = 0;               // indentation level
	$strLen      = strlen($json);
	$indentStr   = "\t";
	$newLine     = "\n";
	$prevChar    = '';
	$outOfQuotes = true;
	for ($i = 0; $i < $strLen; $i++) {
		// Speedup: copy blocks of input which don't matter re string detection and formatting.
		$copyLen = strcspn($json, $outOfQuotes ? " \t\r\n\",:[{}]" : "\\\"", $i);
		if ($copyLen >= 1) {
			$copyStr = substr($json, $i, $copyLen);
			// Also reset the tracker for escapes: we won't be hitting any right now
			// and the next round is the first time an 'escape' character can be seen again at the input.
			$prevChar = '';
			$result .= $copyStr;
			$i += $copyLen - 1;      // correct for the for(;;) loop
			continue;
		}

		// Grab the next character in the string
		$char = substr($json, $i, 1);

		// Are we inside a quoted string encountering an escape sequence?
		if (!$outOfQuotes && $prevChar === '\\') {
			// Add the escaped character to the result string and ignore it for the string enter/exit detection:
			$result .= $char;
			$prevChar = '';
			continue;
		}
		// Are we entering/exiting a quoted string?
		if ($char === '"' && $prevChar !== '\\') {
			$outOfQuotes = !$outOfQuotes;
		}
		// If this character is the end of an element,
		// output a new line and indent the next line
		else if ($outOfQuotes && ($char === '}' || $char === ']')) {
			$result .= $newLine;
			$pos--;
			for ($j = 0; $j < $pos; $j++) {
			  $result .= $indentStr;
			}
		}
		// eat all non-essential whitespace in the input as we do our own here and it would only mess up our process
		else if ($outOfQuotes && false !== strpos(" \t\r\n", $char)) {
			continue;
		}
		// Add the character to the result string
		$result .= $char;
		// always add a space after a field colon:
		if ($outOfQuotes && $char === ':') {
			$result .= ' ';
		}
		// If the last character was the beginning of an element,
		// output a new line and indent the next line
		else if ($outOfQuotes && ($char === ',' || $char === '{' || $char === '[')) {
			$result .= $newLine;
			if ($char === '{' || $char === '[') {
				$pos++;
			}
			for ($j = 0; $j < $pos; $j++) {
				$result .= $indentStr;
			}
		}
		$prevChar = $char;
	}
	return $result;
}
/* vi: set ts=8 sw=4 sts=4 noet: */

// Creates the structure for the popup (container + overlay)
function create_popup_container()
{
	print "<div class='lightbox_overlay' style='display:none;' id='maximize_overlay'></div>";
	print "<div class='maximized_container' style='display:none;' id='maxedcontainer'></div>";
}

// Retrieve HTTP code received when trying to call specific link
function get_http_code($link)
{
	$ch = curl_init($link);
	if ($ch===false)
		return false;
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
	$res = curl_exec($ch);
	if ($res===false)
		return false;
	
	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE );
	
	return $http_code;
}
?>
