<?php

// change php.ini settings
ini_set('memory_limit', '1024M'); // 1GB
ini_set('max_execution_time', '0');
ini_set('register_globals', 'Off');

DEFINE('MQ', ini_get('magic_quotes_gpc'));

ob_start();

// generic functions
function gpc($str) {
	if (MQ) return stripslashes($str);
	return $str;
}
function html($str) {
	return htmlentities($str);
}
function url($str) {
	return $str;
}

// set up some variables
$subject = isset($_GET['s']) ? realpath(gpc($_GET['s'])) : realpath(dirname($_SERVER['SCRIPT_FILENAME']));
if (!$subject) $subject = gpc($_GET['s']);
$action = isset($_GET['action']) ? gpc($_GET['action']) : 'dir';
$param = isset($_GET['p']) ? gpc($_GET['p']) : false;
$thisDir = realpath($subject . (is_file($subject) ? '/..' : ''));
$upDir = realpath("$subject/..");
$value = isset($_POST['v']) ? gpc($_POST['v']) : false;


function printDir($dir) {
	if (is_file($dir)) { // a file was supplied as a directory
		editFile($dir);
		return;
	}
	$entries = @scandir(realpath($dir));
	
	if ($entries === false) {
		echo '<strong>Error:</strong> Cannot open directory.';
		return;
	}
	if (count($entries) <= 2) {
		echo 'Empty directory';
		return;
	}
	
	echo '
<table class="data">
<th>&nbsp;</th><th width=\"350\">Name</th><th width=\"90\">Bytes</th><th width=\"150\">Changed</th><th>&nbsp;</th>';
	
	foreach ($entries as $z => $i) {
		if (preg_match('/^\.+$/', $i)) continue;
		
		$path = realpath("$dir/$i");
		$shade = $z % 2 ? '' : ' class=\'shaded\'';
		$change = date('j/m/Y g:ia', filectime($path));
		
		if (is_dir($path)) {
			echo "
<tr$shade id=\"row$z\">
	<td><a class=\"icon\" href=\"?action=dir&s=".html($path)."\"><font size=\"4\" face=\"Wingdings\">0</font></a></td>
	<td><a href=\"?action=dir&s=".html($path)."\">$i</a></td>
	<td>&nbsp;</td>
	<td>$change</td>
	<td id=\"actions\"><font face=\"Wingdings\" size=\"4\"><a href=\"javascript://\" class=\"icon\" onclick=\"del('".addslashes(url($path))."', '".url($i)."', true, document.getElementById('row$z'))\"?>û</a></font></td>
	<td>&nbsp;</td>
</tr>
";
		}
		else {
			$size = number_format(filesize($path), 0, '.', ',');
			echo "
<tr$shade id=\"row$z\">
	<td><a class=\"icon\" href=\"?action=dl&s=".html($path)."\"><font size=\"4\" face=\"Wingdings\">2</font></a></td>
	<td><a href=\"?action=dl&s=".html($path)."\">$i</a></td>
	<td>$size</td>
	<td>$change</td>
	<td><font face=\"Wingdings\" size=\"4\"><a href=\"javascript://\" class=\"icon\" onclick=\"del('".addslashes(url($path))."', '".url($i)."', false, document.getElementById('row$z'))\"?>û</a></font></td>
	<td><font face=\"Webdings\" size=\"4\"><a href=\"?action=edit&s=".url($path)."\" class=\"icon\">¥</a></font></td>
</tr>
";
		}
	}
	
	echo '</table>';
}

function downloadFile($file) {
	$filename = basename($file);
	if (function_exists('mime_content_type')) {
		$contype = mime_content_type($file);
	} else {
		$contype = 'foo';
		header("Content-Disposition: attachment; filename=\"$filename\"");
	}
	header("Content-type: $contype");
	echo file_get_contents($file);
	die;
}

function uploadFile($outdir) {
	echo '
<h2>Upload File</h2>';
	if($_FILES['upFile'] === NULL) {
		echo '
<form method="post" enctype="multipart/form-data">
<input id="upFile" name="upFile" type="file" /><br />
<input type="submit" value="Upload" style="font-weight: bold" />
<input type="button" value="Cancel" onclick="browseDir(\''.addslashes($outdir).'\');" />
</form>
';
	} else {
		$outdir .= '/';
		if(move_uploaded_file($_FILES['upFile']['tmp_name'],
		   $outdir . $_FILES['upFile']['name'])) {
			echo "Successfully uploaded. <a href=\"javascript:browseDir('".addslashes($outdir)."');\">Return</a>";
		} else {
			echo 'Upload failed. <a href="javascript:history.go(-1)">Retry</a>';
		}
	}
}

function editFile($filename, $new) {
	global $thisDir;
	
	$filename = realpath($filename);
	/*if (!is_file($filename)) {
		echo '<strong>Error:</strong> Not a file or doesn\'t exist';
		return;
	}*/
	
	$name = basename($filename);
	$file = file_get_contents($filename);
	$title = $new ? '<input type="text" id="f" size="89" style="font-size: 1.4em" /><br />' : '<h2>'.html($name).'</h2>
	<input type="hidden" id="f" size="120" value="'.html($filename).'" />';
	$saveArgs = $new ? "'".addslashes($filename)."/' + document.getElementById('f').value, document.getElementById('v').value" : "document.getElementById('f').value, document.getElementById('v').value";
	
	/*echo "
$title

<textarea id=\"v\" cols=\"90\" rows=\"25\" onchange=\"setSaveStatus('Unsaved', false)\">".html($file)."</textarea><br />
<input type=\"button\" onclick=\"save('".html(addslashes($filename))."', document.getElementById('v').value);\" value=\"Save\" id=\"saveBtn\" style=\"font-weight: bold\" />
<input type=\"button\" value=\"Exit\" id=\"exitBtn\" onclick=\"browseDir('".addslashes($thisDir)."');\" /> <span id=\"saveStatus\"></span>
";*/

	echo "
$title

<textarea id=\"v\" cols=\"90\" rows=\"25\" onchange=\"setSaveStatus('Unsaved', false)\">".html($file)."</textarea><br />
<input type=\"button\" onclick=\"save($saveArgs);\" value=\"Save\" id=\"saveBtn\" style=\"font-weight: bold\" />
<input type=\"button\" value=\"Exit\" id=\"exitBtn\" onclick=\"browseDir('".addslashes($thisDir)."');\" /> <span id=\"saveStatus\"></span>
";
}

function saveFile($filename, $contents) {
	echo file_put_contents($filename, $contents) !== false;
	die;
}

function deleteSubject($subject) {
	if (is_file($subject)) {
		echo @unlink($subject);
	}
	else if (is_dir($subject)) {
		echo @rmdir($subject);
	}
	else echo false;
	die;
}

function evalBox($command) {
	if ($command === false) {
		echo '
<h2>Run PHP</h2>
<form action="javascript://" onsubmit="execEval()">
	<textarea id="v" cols="80" rows="20"></textarea>
	<br /><input id="evalbtn" type="submit" value="Run" style="font-weight: bold" />
	<input type="button" value="Clear Output" onclick="clearEvalOutput()" />
</form>
<div id="command_output"></div>
';
	}
	else {
		ob_end_clean();
		eval($command);
		die;
	}
}

function printCSS() {
	ob_end_clean();
	header('Content-Type: text/css');
	echo 'body, td {
	font: 80% sans-serif;
}
a {
	text-decoration: none;
}
h2 {
	margin-bottom: 0;
}
hr {
	border: 1px solid #DDD;
	margin: 1em 0;
}

table.data tr.shaded {
	background-color: #EEE;
}
table.data th {
	text-align: left;
}
table.data td {
	padding: 0 0.8em;
	margin-right: 2em;
}
table.data td a {
	display: block;
}
table.data td a:hover {
	color: #77F;
}

.icon {
	text-decoration: none;
	display: block;
	color: black;
}

#nav {
	font-size: 180%;
	margin-bottom: 0.2em;
}
#nav a {
	text-decoration: none;
	color: black;
	background-color: #EEE;
	border: 1px solid #CCC;
	padding: 0 0.3em;
}
#nav a:hover {
	color: #555;
	background-color: #FFF;
}
';
	die;
}

function printScript() {
	ob_end_clean();
	header('Content-Type: text/javascript');
	echo 'function newXMLHTTP() {
	try {
		return new XMLHttpRequest();
	}
	catch (e) {
		try {
			return new ActiveXObject("Msxml2.XMLHTTP");
		}
		catch (e) {
			try {
				return new ActiveXObject("Microsoft.XMLHTTP");
			}
			catch (e) {
				return false;
			}
		}
	}
}

function del(path, name, isDir, row) {
	var question = isDir ? "Are you sure you would like to delete the folder \"" + name + "\" and all of its contents?" : "Are you sure you would like to delete the file \"" + name + "\"?";
	if (confirm(question)) {
		var ajax = newXMLHTTP();
		ajax.onreadystatechange = function() {
			if (ajax.readyState == 4) {
				if (ajax.responseText == "1") {
					row.style.backgroundColor = "red";
				}
				else {
					alert("Error deleting \"" + name + "\"! The file/folder may no longer exist or is still in use.");
					row.style.backgroundColor = "orange";
				}
			}
		}
		ajax.open("GET", "?action=del&s=" + escape(path), true);
		ajax.send(null);
	}
	else {
		return false;
	}
}

function save(file, value) {
	var ajax = newXMLHTTP();
	var params = "v=" + escape(value);
	var saveStatus = document.getElementById("saveStatus");
	var filenameBox = document.getElementById("f");
	
	setSaveStatus("Saving...", true);
	ajax.onreadystatechange = function() {
		if (ajax.readyState == 4) {
			if (ajax.responseText == "1") {
				setSaveStatus("Saved", false);
				filenameBox.disabled = true;
			}
			else {
				setSaveStatus("Save failed", false);
			}
			saveBtn.enabled = true;
		}
	}
	ajax.open("POST", "?action=save&s=" + escape(file), true);
	ajax.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
	ajax.setRequestHeader("Content-length", params.length);
	ajax.setRequestHeader("Connection", "close");
	ajax.send(params);
}

function setSaveStatus(status, disable) {
	var saveBtn = document.getElementById("saveBtn");
	var exitBtn = document.getElementById("exitBtn");
	
	saveBtn.disabled = exitBtn.disabled = disable;
	saveStatus.innerHTML = status;
}

function browseDir(dir) {
	location.href = "?action=dir&s=" + dir;
}

function execEval() {
	var ajax = newXMLHTTP();
	var evalBox = document.getElementById("v");
	var params = "v=" + escape(evalBox.value);
	var evalBtn = document.getElementById("evalbtn");
	var comOut = document.getElementById("command_output");

	evalBtn.disabled = true;

	ajax.onreadystatechange = function() {
		if (ajax.readyState == 4) {
			if (ajax.status == 200) {
				comOut.innerHTML += (comOut.innerHTML.length ? "" : "<hr />") + ajax.responseText + "<br />";
			}
			else {
				comOut.innerHTML += "<span style=\"color: red\">Couldn\'t execute.</font><br />";
			}
			evalBtn.disabled = false;
		}
	}
	ajax.open("POST", "?action=eval", true);
	ajax.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
	ajax.setRequestHeader("Content-length", params.length);
	ajax.setRequestHeader("Connection", "close");
	ajax.send(params);
}

function clearEvalOutput() {
	document.getElementById("command_output").innerHTML = "";
}
';
	die;
}

header('Content-Type: text/html; charset=ISO-8859-1');
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1">
<title>Scander</title>
<link rel="stylesheet" href="?action=getcss" type="text/css" />
<script type="text/javascript" src="?action=getscript"></script>
</head>

<body>
<h1>Scander</h1>

<form method="get">
	<input type="hidden" name="action" value="dir" />
	<input type="text" size="90" name="s" value="<?php echo html($subject); ?>" />
	<input type="submit" value="Go" style="font-weight: bold" />
</form>

<div id="nav">
	<a href="javascript:history.go(-1)"><font face="Wingdings">ï</font></a>
	<a href="javascript:history.go(1)"><font face="Wingdings">ð</font></a>
	<a href="?action=dir&s=<?php echo $upDir; ?>"><font face="Wingdings">ñ</font></a>
	<a href="javascript://" onclick="location.reload(true)"><font face="Webdings">q</font></a>
	<a href="?"><font face="Webdings">H</font></a>
	&nbsp;<span style="border-left: 1px solid #CCC; margin">&nbsp;</span>
	<a href="?action=edit&p=new&s=<?php echo html($thisDir); ?>"><font face="Wingdings">2</font></a>
	<a href="?action=eval&s=<?php echo html($thisDir); ?>"><span style="font-size: 0.8em">&lt;?</span></a>
	<a href="?action=ul&s=<?php echo html($thisDir); ?>"><span style="font-size: 0.8em">^</span></a>
</div>
<br />

<?php

// determine action
switch ($action) {
	case 'del':
		ob_clean();
		deleteSubject($subject);
		break;
	case 'dl':
		ob_clean();
		downloadFile($subject);
		break;
	case 'edit':
		editFile($subject, $param == 'new');
		break;
	case 'save':
		ob_clean();
		saveFile($subject, $value);
		break;
	case 'eval':
		evalBox($value);
		break;
	case 'ul':
		uploadFile($subject);
		break;
	case 'dir':
		printDir($subject);
		break;
	case 'getcss':
		printCSS();
		break;
	case 'getscript':
		printScript();
		break;
	default:
		printDir($subject);
		break;	
}

?>
</body>
</html>
