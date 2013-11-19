<?php
//configurations
$urls=array(
		"http://some/page.jsp"=>"SOME WORDS ON THE PAGE",
	);//map from url to key words to be found on the url	
	
define('EMAIL',"YOUR EMAIL@SERVER.com"); //to whom it should send email
define('RETRIES',1);  //retry times
define('RETRY_WAIT',30); //interval between retries
define('DEFAULT_ENCODING','UTF-8');

define('SMTP_SERVER', "smtp.163.com");//SMTP Server
define('SMTP_PORT' ,25);//SMTP Server Port
define('SMTP_USER_EMAIL', "WHO@163.com");//SMTP User Email
define('SMTP_USER' , "WHO");//SMTP User name
define('SMTP_PASS' , "PASSWORD");//SMTP User password
	
define('LOG_TO_FILE','YOUR_SECRET_LOG_FILE.html');//define('LOG_TO_FILE',false);//log file path,php write access to the parent directory
date_default_timezone_set('Asia/Shanghai');
ini_set('date.timezone','Asia/Shanghai');
set_time_limit(60*10);
	

//HOW TO ADD CRONTAB
// crontab -e
// */20 * * * * wget http://[SERVER]/[script].php >/dev/null 2>&1

 
function work(){
	global $urls;
	$allok=true;
	foreach($urls as $url=>$words){
		$ok=false;
		for($r=0;$r<RETRIES+1 && !$ok;++$r){
			$html=gethtml($url);
			if($html!==false){
				if(strstr($html,$words)!==false){
					$ok=true;
				}
			}
			if(!$ok)sleep(RETRY_WAIT);
		}
		if(!$ok){
			$allok=false;
			$sSubject="Page Failure [$url]";
			$sMessage="Failed to find [$words] in [$url]";
			send_email(EMAIL,$sSubject,$sMessage,true);
		}
	}
	if($allok){
		//send_email(EMAIL,"CHECK ALL OK","");
		loghtml("CHECK ALL OK");
	}
}

function is_url($str){
	return preg_match("/^https?:\/\/.*/i", $str);
}
function gethtml($url){
	if(!is_url($url))return false;
	$accept = array(
	    'type' => array('application/rss+xml', 'application/xml', 'application/rdf+xml', 'text/xml'),
	    'charset' => array_diff(mb_list_encodings(), array('pass', 'auto', 'wchar', 'byte2be', 'byte2le', 'byte4be', 'byte4le', 'BASE64', 'UUENCODE', 'HTML-ENTITIES', 'Quoted-Printable', '7bit', '8bit'))
	);
	$header = array(
	    'Accept: '.implode(', ', $accept['type']),
	    'Accept-Charset: '.implode(', ', $accept['charset']),
	);
	$encoding = null;
	$curl = curl_init($url);
	@curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTP|CURLPROTO_HTTPS);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_HEADER, true);
	curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
	$response = curl_exec($curl);
	if (!$response) {
	    // error fetching the response
	    return false;
	} else {
	    $offset = strpos($response, "\r\n\r\n");
	    $header = substr($response, 0, $offset);
	    if (!$header || !preg_match('/^Content-Type:\s+([^;]+)(?:;\s*charset=(.*))?/im', $header, $match)) {
	        // error parsing the response
	    } else {
	        if (!in_array(strtolower($match[1]), array_map('strtolower', $accept['type']))) {
	            // type not accepted
	        }
	        $encoding = trim($match[2], '"\'');
	        $encoding = trim($encoding);
	    }
	    $body = substr($response, $offset + 4);
	    if (!$encoding) {	        
	        if (preg_match('/^<\?xml\s+version=(?:"[^"]*"|\'[^\']*\')\s+encoding=("[^"]*"|\'[^\']*\')/s', $body, $match)) {
	            $encoding = trim($match[1], '"\'');
	        }
	    }
	    if (!$encoding) {
	        $encoding = DEFAULT_ENCODING;
			$body = mb_convert_encoding($body, 'UTF-8', $encoding);
	    } else {
	        if (!in_array($encoding, array_map('strtolower', $accept['charset']))) {
	            // encoding not accepted
	            return false;
	        }			
			$encoding=strtoupper($encoding);
	        if ($encoding != 'UTF-8') {
	        	//if(iconv){
	        		$ret=iconv($encoding,'UTF-8'."//TRANSLIT",$body);
	        	//}	        	
	        	if($ret===false){
	        		$ret = mb_convert_encoding($body, 'UTF-8', $encoding);
	        	}
				$body=$ret;           
	        }
	    }
	    return $body;
	}
}


function send_email($sMailTo,$sSubject,$sMessage,$error=false){	
	$smtp = new smtp(SMTP_SERVER,SMTP_PORT,true,SMTP_USER,SMTP_PASS);//use smtp authentication

	$smtp->debug = FALSE;//
	
	loghtml("send_email to $sMailTo,subject=[$sSubject]",$error);
	$result= $smtp->sendmail($sMailTo, SMTP_USER_EMAIL, $sSubject, $sMessage, "HTML");	
	loghtml("send_email to $sMailTo,subject=[$sSubject],result=[$result]",!$result);
	return $result;
}

function loghtml($cnt,$error=false){
	if(!LOG_TO_FILE)return;
	$file=dirname(__FILE__).DIRECTORY_SEPARATOR.LOG_TO_FILE;
	if(!file_exists($file)){
$header=<<<eos
<html>
<head>
<title>Log File</title>
<style>
.time{
	font-family:arial;
}
.logbox{
	border:1px solid #000;width:100%;background-color:#efefef;
	margin:2px;
}
.error{
	background-color:#ee8855;
}
.logbox p{
	margin:5px 2px;
}
.logbox:hover{
	background-color:#ccc;
}
</style>
</head>
<body>
eos;
		file_put_contents($file,$header);
	}
	$html="<div class='time'>".date("[Y-m-d H:i:s]").":</div>";
	$html.="<p>".$cnt."</p>";
	$errorclass=$error?"error":"";
	$html="<div class='logbox $errorclass'>".$html."</div>\n";
	file_put_contents($file,  $html,FILE_APPEND);
}















///**************smtp class******************
class smtp 
{ 
/* Public Variables */ 
var $smtp_port; 
var $time_out; 
var $host_name; 
var $log_file; 
var $relay_host; 
var $debug; 
var $auth; 
var $user; 
var $pass; 

/* Private Variables */ 
var $sock; 

/* Constractor */ 
function smtp($relay_host = "", $smtp_port = 25,$auth = false,$user,$pass) 
{ 
$this->debug = FALSE; 
$this->smtp_port = $smtp_port; 
$this->relay_host = $relay_host; 
$this->time_out = 30; //is used in fsockopen() 
$this->auth = $auth;//auth 
$this->user = $user; 
$this->pass = $pass; 
$this->host_name = "localhost"; //is used in HELO command 
$this->log_file = ""; 
$this->sock = FALSE; 
} 

/* Main Function */ 
function sendmail($to, $from, $subject = "", $body = "", $mailtype, $cc = "", $bcc = "", $additional_headers = "") 
{ 
$mail_from = $this->get_address($this->strip_comment($from)); 
$body = @ereg_replace("(^|(\r\n))(\.)", "\1.\3", $body); 
$header = "MIME-Version:1.0\r\n"; 
if($mailtype=="HTML") 
{ 
$header .= "Content-Type:text/html; charset=UTF-8\r\n"; 
} 
$header .= "To: ".$to."\r\n"; 
if ($cc != "") 
{ 
$header .= "Cc: ".$cc."\r\n"; 
} 
$header .= "From: $from<".$from.">\r\n"; 
$header .= "Subject: ".$subject."\r\n"; 
$header .= $additional_headers; 
$header .= "Date: ".date("r")."\r\n"; 
$header .= "X-Mailer:By Redhat (PHP/".phpversion().")\r\n"; 
list($msec, $sec) = explode(" ", microtime()); 
$header .= "Message-ID: <".date("YmdHis", $sec).".".($msec*1000000).".".$mail_from.">\r\n"; 
$TO = explode(",", $this->strip_comment($to)); 

if ($cc != "") 
{ 
$TO = array_merge($TO, explode(",", $this->strip_comment($cc))); 
} 
if ($bcc != "") 
{ 
$TO = array_merge($TO, explode(",", $this->strip_comment($bcc))); 
} 
$sent = TRUE; 
foreach ($TO as $rcpt_to) 
{ 
$rcpt_to = $this->get_address($rcpt_to); 
if (!$this->smtp_sockopen($rcpt_to)) 
{ 
$this->log_write("Error: Cannot send email to ".$rcpt_to."\n"); 
$sent = FALSE; 
continue; 
} 
if ($this->smtp_send($this->host_name, $mail_from, $rcpt_to, $header, $body)) 
{ 
$this->log_write("E-mail has been sent to <".$rcpt_to.">\n"); 
} 
else 
{ 
$this->log_write("Error: Cannot send email to <".$rcpt_to.">\n"); 
$sent = FALSE; 
} 
fclose($this->sock); 
$this->log_write("Disconnected from remote host\n"); 
} 
return $sent; 
} 

/* Private Functions */ 
function smtp_send($helo, $from, $to, $header, $body = "") 
{ 
if (!$this->smtp_putcmd("HELO", $helo)) 
{ 
return $this->smtp_error("sending HELO command"); 
} 

#auth 
if($this->auth) 
{ 
if (!$this->smtp_putcmd("AUTH LOGIN", base64_encode($this->user))) 
{ 
return $this->smtp_error("sending HELO command"); 
} 
if (!$this->smtp_putcmd("", base64_encode($this->pass))) 
{ 
return $this->smtp_error("sending HELO command"); 
} 
} 
if (!$this->smtp_putcmd("MAIL", "FROM:<".$from.">")) 
{ 
return $this->smtp_error("sending MAIL FROM command"); 
} 
if (!$this->smtp_putcmd("RCPT", "TO:<".$to.">")) 
{ 
return $this->smtp_error("sending RCPT TO command"); 
} 
if (!$this->smtp_putcmd("DATA")) 
{ 
return $this->smtp_error("sending DATA command"); 
} 
if (!$this->smtp_message($header, $body)) 
{ 
return $this->smtp_error("sending message"); 
} 
if (!$this->smtp_eom()) 
{ 
return $this->smtp_error("sending <CR><LF>.<CR><LF> [EOM]"); 
} 
if (!$this->smtp_putcmd("QUIT")) 
{ 
return $this->smtp_error("sending QUIT command"); 
} 
return TRUE; 
} 

function smtp_sockopen($address) 
{ 
if ($this->relay_host == "") 
{ 
return $this->smtp_sockopen_mx($address); 
} 
else 
{ 
return $this->smtp_sockopen_relay(); 
} 
} 

function smtp_sockopen_relay() 
{ 
$this->log_write("Trying to ".$this->relay_host.":".$this->smtp_port."\n"); 
$this->sock = @fsockopen($this->relay_host, $this->smtp_port, $errno, $errstr, $this->time_out); 
if (!($this->sock && $this->smtp_ok())) 
{ 
$this->log_write("Error: Cannot connenct to relay host ".$this->relay_host."\n"); 
$this->log_write("Error: ".$errstr." (".$errno.")\n"); 
return FALSE; 
} 
$this->log_write("Connected to relay host ".$this->relay_host."\n"); 
return TRUE;; 
} 

function smtp_sockopen_mx($address) 
{ 
$domain = @ereg_replace("^.+@([^@]+)$", "\1", $address); 
if (!@getmxrr($domain, $MXHOSTS)) 
{ 
$this->log_write("Error: Cannot resolve MX \"".$domain."\"\n"); 
return FALSE; 
} 
foreach ($MXHOSTS as $host) 
{ 
$this->log_write("Trying to ".$host.":".$this->smtp_port."\n"); 
$this->sock = @fsockopen($host, $this->smtp_port, $errno, $errstr, $this->time_out); 
if (!($this->sock && $this->smtp_ok())) 
{ 
$this->log_write("Warning: Cannot connect to mx host ".$host."\n"); 
$this->log_write("Error: ".$errstr." (".$errno.")\n"); 
continue; 
} 
$this->log_write("Connected to mx host ".$host."\n"); 
return TRUE; 
} 
$this->log_write("Error: Cannot connect to any mx hosts (".implode(", ", $MXHOSTS).")\n"); 
return FALSE; 
} 

function smtp_message($header, $body) 
{ 
fputs($this->sock, $header."\r\n".$body); 
$this->smtp_debug("> ".str_replace("\r\n", "\n"."> ", $header."\n> ".$body."\n> ")); 
return TRUE; 
} 

function smtp_eom() 
{ 
fputs($this->sock, "\r\n.\r\n"); 
$this->smtp_debug(". [EOM]\n"); 
return $this->smtp_ok(); 
} 

function smtp_ok() 
{ 
$response = str_replace("\r\n", "", fgets($this->sock, 512)); 
$this->smtp_debug($response."\n"); 
if (!@ereg("^[23]", $response)) 
{ 
fputs($this->sock, "QUIT\r\n"); 
fgets($this->sock, 512); 
$this->log_write("Error: Remote host returned \"".$response."\"\n"); 
return FALSE; 
} 
return TRUE; 
} 

function smtp_putcmd($cmd, $arg = "") 
{ 
if ($arg != "") 
{ 
if($cmd=="") 
{ 
$cmd = $arg; 
} 
else 
{ 
$cmd = $cmd." ".$arg; 
} 
} 
fputs($this->sock, $cmd."\r\n"); 
$this->smtp_debug("> ".$cmd."\n"); 
return $this->smtp_ok(); 
} 

function smtp_error($string) 
{ 
$this->log_write("Error: Error occurred while ".$string.".\n"); 
return FALSE; 
} 

function log_write($message) 
{ 
$this->smtp_debug($message); 
if ($this->log_file == "") 
{ 
return TRUE; 
} 
$message = date("M d H:i:s ").get_current_user()."[".getmypid()."]: ".$message; 
if (!@file_exists($this->log_file) || !($fp = @fopen($this->log_file, "a"))) 
{ 
$this->smtp_debug("Warning: Cannot open log file \"".$this->log_file."\"\n"); 
return FALSE;; 
} 
flock($fp, LOCK_EX); 
fputs($fp, $message); 
fclose($fp); 
return TRUE; 
} 

function strip_comment($address) 
{ 
$comment = "\([^()]*\)"; 
while (@ereg($comment, $address)) 
{ 
$address = @ereg_replace($comment, "", $address); 
} 
return $address; 
} 

function get_address($address) 
{ 
$address = @ereg_replace("([ \t\r\n])+", "", $address); 
$address = @ereg_replace("^.*<(.+)>.*$", "\1", $address); 
return $address; 
} 

function smtp_debug($message) 
{ 
if ($this->debug) 
{ 
echo $message; 
} 
} 

}
//***************************smtp class end****************************










work();
//loghtml('test');
//loghtml('test',true);
