<?php
class Scander {
    var $version = '2.0.0';

    var $ds = '/';
    var $current_dir = '';
    var $working_dir = '';
    var $file = '';
    var $action = '';

    var $code;

    var $magic_quotes = false;
    var $init_scripts = array();

    // Runs when initialized.
    function Scander() {
        if(!$this->checkCompatible()) {
            if($this->directRequest()) {
                echo $this->getHTML($this->getChecklist());
            }
            return;
        }

        $this->setup();

        if(!$this->directRequest()) return;

        // Just eval the code and exit.
        if(isset($this->code)) {
            echo eval($this->gpc($this->code));
            return;
        }

        // User just requested a page, show it to them.
        $content = '';
        switch($this->action) {
        case 'eval':
            $content = $this->getEval();
            break;
        case 'upload':
            $content = $this->getUpload();
            break;
        case 'new':
            $content = $this->getNew();
            break;
        case 'edit':
            $content = $this->getEdit();
            break;
        case 'download':
            if(is_file($this->file)) {
                if(function_exists("mime_content_type")) {
	// Detect the mime-type.
                    header('Content-Type: ' . mime_content_type($this->file));
                } else {
	// Just supply a bogus mime type so they can download it.
                    header('Content-Type: scander/download-file');
                }

                // Just to make the file download have the right name.
                $basename = basename($this->file);
                header('Content-Disposition: inline; '.
                       'filename="' . $basename . '"');

                echo file_get_contents($this->file);
                return;
            }
        case 'list':
        default:
            $content = $this->getList();
            break;
        }
        echo $this->getHTML($content);
    }

    // See if this is the executing script (and it isn't just included).
    static function directRequest() {
        $ds = DIRECTORY_SEPARATOR;
        $local_path = str_replace('/', $ds, $_SERVER['SCRIPT_FILENAME']);
        return __FILE__ == realpath($local_path);
    }

    // Sets up starting variables.
    function setup() {
        $this->ds = DIRECTORY_SEPARATOR;


        $this->current_dir = getcwd();
        if($_GET['dir']) {
            $this->working_dir = $_GET['dir'];
        } else {
            $this->working_dir = $this->current_dir;
        }

        $this->file = $_GET['file'];

        $this->action = $_GET['action'];
        if(empty($this->action)) {
            $this->action = 'list';
        }

        if(isset($_POST['code'])) {
            $this->code = $_POST['code'];
        }

        // Figure out what URL we accessed scander from (without the GET params).
        $request_uri = $_SERVER['REQUEST_URI'];
        $query = $_SERVER['QUERY_STRING'];
        $query_pos = strrpos($request_uri, $query);
        if($query_pos + strlen($query) == strlen($request_uri)) {
            $url = substr($_SERVER['REQUEST_URI'], 0, $query_pos);
            $this->url = rtrim($url, '?&');
        }
    }

    // Check if this PHP can run Scander correctly.
    static function checkCompatible() {
        // Needs output buffering.
        $ob = ini_get("output_buffering");

        return $ob;
    }

    // Damn magic_quotes.
    static function gpc($string) {
        if(get_magic_quotes_gpc()) {
            return stripslashes($string);
        }
        return $string;
    }

    // Add some javascript to run when the document has loaded.
    function addInitJS($js) {
        $this->init_scripts[] = $js;
    }

    function getUpload() { 
        ob_start();
?>
<h2>Upload File - <?php echo $this->working_dir; ?></h2>
<form action="<?php echo $this->url; ?>" method="post" enctype="multipart/form-data">
<input type="file" name="upload_file" />
<div>
    <input type="submit" value="Upload" />
    <input type="reset" value="Reset" />
</div>
</form>
<?php
        return ob_get_clean();
    }

    // Get a new file dialog.
    function getNew() {
        ob_start();
?>
<h2>New File - <?php echo $this->working_dir; ?></h2>
<form action="<?php echo $this->url; ?>" method="post">
<div>
    <label for="file_name">Filename</label>
    <input id="file_name" name="file_name" type="text" />
</div>
<div>
    <label for="file_content">Content</label>
    <textarea id="file_content" name="file_content" cols="80" rows="24"></textarea>
</div>
<div>
    <input type="submit" value="Save" />
    <input type="reset" value="Reset" />
</div>
</form>
<?php
        return ob_get_clean();
    }

    function getEdit() {
        ob_start();
?>
<h2>Edit File - <?php echo $this->file; ?></h2>
<form action="<?php echo $this->url; ?>" method="post">
<textarea id="file_content" name="file_content" cols="80" rows="24"><?php echo htmlentities(file_get_contents($this->file)); ?></textarea>
<div>
    <input type="submit" value="Save" />
    <input type="reset" value="Reset" />
</div>
</form>
<?php
        return ob_get_clean();
    }

    // Get a PHP evaluator.
    function getEval() {
        ob_start();
?>
<h2>PHP Evaluation</h2>
<form action="#" onsubmit="evalPHP();return false;">
    <div>
        <textarea id="eval_textarea" cols="80"
            rows="24"><?php echo $eval; ?></textarea>
    </div>
    <div>
        <input id="eval_submit" type="submit" value="Run" />
        <input type="button" value="Clear output" onclick="clearEval();" />
    </div>
</form>
<div id="eval_output"></div>
<?php
        return ob_get_clean();
    }

    // Get a list of the files in the current directory.
    function getList() {
        $filenames = scandir($this->working_dir);
        ob_start();
?>
<h2>Listing - <?php echo $this->working_dir; ?></h2>
<table id="file_list">
    <tr class="heading">
        <th>Type</th>
        <th>Filename</th>
        <th>Size</th>
        <th>Modified</th>
        <th>&nbsp;</th>
        <th>&nbsp;</th>
        <th>&nbsp;</th>
    </tr>
<?php
        $odd = false;
        foreach($filenames as $file):
            if(is_dir($this->working_dir . $this->ds . $file)) {
                $file .= $this->ds;
            }
            $fullpath = realpath($this->working_dir . $this->ds . $file);

            $action = is_dir($fullpath) ? 'list' : 'download';
            $target = is_dir($fullpath) ? 'dir' : 'file';
            $odd = !$odd;
?>
    <tr class="<?php echo $target; ?> <?php echo $odd ? 'odd' : 'even'; ?>">
        <td class="type"><?php echo is_dir($fullpath) ? 'D': 'F'; ?></td>
        <td class="filename"><a href="<?php echo $this->url; ?>?action=<?php echo $action; ?>&amp;<?php echo $target; ?>=<?php echo $fullpath; ?>"><?php echo $file; ?></a></td>
        <td class="size"><?php echo number_format(filesize($fullpath)); ?>B</td>
        <td class="modified"><?php echo date('Y-m-d H:i:s', filemtime($fullpath)); ?></td>
        <td class="delete">Delete</td>
        <td class="rename">Rename</td>
        <td class="edit"><a href="<?php echo $this->url; ?>?action=edit&amp;<?php echo $target; ?>=<?php echo $fullpath; ?>"><?php echo is_dir($fullpath) ? '' : 'Edit'; ?></a></td>
    </tr>
<?php
        endforeach;
?>
</table>
<?php
        return ob_get_clean();
    }

    // Get HTML output with specified `content`.
    function getHTML($content = '') {
        ob_start();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
            "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<title>Scander - <?php echo htmlentities($this->working_dir); ?></title>
<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
<style type="text/css">
body {
    background: #FFF5E5;
    font-family: sans-serif;
    margin: 0;
    padding: 0;
}

a {
    color: #630;
}

a:hover {
    background: #FEA;
}

label {
    display: block;
}

#container {
    padding: 1em;
    background: #FFE;
    margin: 0 auto;
    width: 50em;
}

#navigation ul {
    margin: 0;
    padding: 0;
}

#navigation ul li {
    display: inline;
}

#footer {
    padding-top: 1em;
    text-align: center;
}

#eval_textarea, #file_name, #file_content {
    width: 100%;
}

#eval_output {
    margin-top: 1em;
    border-top: 2px solid #444;
    background: #000;
    color: #FFF;
    white-space: pre;
    font-family: monospace;
    padding: 1em;
}

#file_list {
    width: 100%;
}

#file_list td {
    text-align: center;
    /*padding: 0;*/
}

#file_list a {
    display: block;
}

#file_list .dir a:hover {
    background: #FED;
}

#file_list .odd {
    background: #FEC;
}

#file_list .filename {
    text-align: left;
}

#file_list .size {
    text-align: right;
}

.eval_error {
    color: #F44;
}
</style>
<script type="text/javascript">
function initJS() {
    <?php echo implode('', $this->init_scripts); ?>
}

<?php echo $this->getJS(); ?>
</script>
</head>

<body onload="initJS()">
<div id="container">
    <div id="header">
        <h1>Scander</h1>
    </div>
    <div id="navigation">
        <ul>
            <li><a href="<?php echo $this->url; ?>?action=list">Home</a></li>
            <li><a href="<?php echo $this->url; ?>?action=upload">Upload</a></li>
            <li><a href="<?php echo $this->url; ?>?action=new">New</a></li>
            <li><a href="<?php echo $this->url; ?>?action=eval">Eval</a></li>
        </ul>
    </div>
    <div id="content">
        <?php echo $content; ?>
    </div>
    <div id="footer">
        Scander <?php echo $this->version; ?> by <a href="http://github.com/pufuwozu">Brian McKenna</a>
    </div>
</div>
</body>
</html>
<?php
        return ob_get_clean();
    }

    // Get any static JS (doesn't change).
    static function getJS() {
        ob_start();
?>
function newXMLHTTP() {
    try {
        return new XMLHttpRequest();
    }
    catch (e) {
        try {
            return new ActiveXObject("Msxml2.XMLHTTP");
        } catch (e) {
            try {
                return new ActiveXObject("Microsoft.XMLHTTP");
            } catch (e) {
                return false;
            }
        }
    }
}

function clearEval() {
    document.getElementById("eval_output").innerHTML = '';
}
    
function evalPHP() {
    var ajax = newXMLHTTP();
    var evalBox = document.getElementById("eval_textarea");
    var evalButton = document.getElementById("eval_submit");
    var evalOutput = document.getElementById("eval_output");
    var params = "code=" + encodeURIComponent(evalBox.value);

    evalButton.disabled = true;

    ajax.onreadystatechange = function() {
        if (ajax.readyState == 4) {
            if (ajax.status == 200) {
                evalOutput.innerHTML += '<div>' + ajax.responseText + '</div>';
            } else {
                evalOutput.innerHTML +=
                    '<div class="eval_error">Error executing PHP.</div>';
            }
            evalButton.disabled = false;
        }
    }
    ajax.open("POST", document.location, true);
    ajax.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    ajax.setRequestHeader("Content-length", params.length);
    ajax.setRequestHeader("Connection", "close");
    ajax.send(params);
}
<?php
        return ob_get_clean();
    }

    // Prints a list of things that need to be setup.
    static function getChecklist() {
        ob_start();
?>
<h2>Sorry, Scander doesn&rsquo;t want to work!</h2>
<p>We checked your configuration and found a few things wrong with it. Please
check the following items to continue using Scander:</p>
<ul>
<li>Output buffering</li>
</ul>
<?php
        return ob_get_clean();
    }
}
new Scander();
?>
