<?php
class Scander {
    var $current_dir = '';
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

        // FIXME: Doesn't currently work with Symlinks.
        //if(!$this->directRequest()) return;

        // Just eval the code and exit.
        if($this->code) {
            echo eval($this->gpc($this->code));
            return;
        }

        // User just requested a page, show it to them.
        $content = '';
        switch($this->action) {
        case 'eval':
            $content = $this->getEval();
            break;
        case 'list':
        default:
            $content = $this->getList();
            break;
        }
        echo $this->getHTML($content);
    }

    // See if this is the executing script (and it isn't just included).
    static function directRequest() {
        return str_replace('\\', '/', __FILE__) == $_SERVER['SCRIPT_FILENAME'];
    }

    // Sets up starting variables.
    function setup() {
        $this->current_dir = getcwd();

        $this->action = $_GET['action'];
        if(empty($this->action)) {
            $this->action = 'list';
        }

        if($_POST['code']) {
            $this->code = $_POST['code'];
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

    // Get a PHP evaluator.
    function getEval() {
        ob_start();
?>
<form action="" onsubmit="evalPHP();return false;">
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
        $filenames = scandir($this->current_dir);
        ob_start();
?>
<table>
    <tr><th>Filename</th></tr>
<?php
         foreach($filenames as $file):
?>
    <tr><td><?php echo $file; ?></td></tr>
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
<title>Scander - <?php echo htmlentities($this->current_dir); ?></title>
<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
<style type="text/css">
body {
    background: #FFE;
    font-family: sans-serif;
    margin: 0;
    padding: 0;
}

#container {
    margin: 0 auto;
    width: 80%;
}

#eval_textarea {
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
            <li>Home</li>
            <li>Eval</li>
        </ul>
    </div>
    <div id="content">
        <?php echo $content; ?>
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
