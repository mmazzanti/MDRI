<?php
class MDRI_responder{
  private $user = NULL;
  private $silence=FALSE;
  private $IP =NULL;
  private $logtime = NULL;
  private $error_logger= NULL;
  
  /**
  * Constructor of MDRI_responder class.
  * This will set the variables used by the query-logger
  */
  public function MDRI_responder($user,$IP,$logtime,$errorlogger){
    $this->user=$user;
    $this->IP=$IP;
    $this->logtime=intval($logtime);
    $this->error_logger=$errorlogger;
  }

  public function Silencer(){
    $this->silence=TRUE;
  }

  /**
  * This method will return the representation in JSON format of the resource selected.
  * If $silence is setted TRUE (HEAD request) the method will only return a response with code 200 (OK) and the Content-Lenght header (with the size of the representation in JSON format of the resource)
  */
  public function Return_value($array,$primary = NULL){
    if(!empty($array)){
      $json=json_encode($array);
      if($this->silence==FALSE){
	$contlength=strlen($json);
	header("Content-Length: ".$contlength);
	$this->Return_code(200);
	echo($json);
      }
      else{
	$contlength=strlen($json);
	header("Content-Length: ".$contlength);
	$this->Return_code(200);
      }
    }
    else {
      $this->Return_code(404);
    }
  }
  
  /**
  * This method will log all of the query ($string) executed by the interface.
  * It will also log the name of the user who sent the request and the time on which the request was received.
  * The log file used is query.log.
  */
  function echo_query($string){
    $time=microtime(true);
    date_default_timezone_set('UTC');
    $date=date("r",$time);
    $day=(int)date("z",$time);
    if($this->logtime<0 || $this->logtime > 365){
      $this->logtime=15;
      $this->error_logger->log_errors("ERROR SETTING MAX TIME FOR QUERY LOG. Using default (15)");
    }
    if(((int)$day/15)& 1 ==1){
      $use="01";
    }
    else{
      $use="00";
    }
    $last_modified=filemtime("query-".$use.".log");
    if($time>$last_modified+(24*60*60*$this->logtime)){
      delete("query-".$use.".log");
    }
    $handle = fopen("query-".$use.".log", "a") or die();
    fwrite($handle,$this->IP." : ".$this->user." : ".$date." : ".$string."\n");
    fclose($handle);
  }

  /**
  * This method will generate the response code using the relative method.
  * $extraaction is used only in case of a response code 201 (CREATED); it will also generate the Location header.
  */
  public function Return_code($code,$extraaction=FALSE){
    switch($code){
      case 200:
	$this->OK();
	break;
      case 201:
	$this->created($extraaction);
	break;
      case 204:
	$this->noContent();
	break;
      case 400:
	$this->badRequest();
	break;
      case 401:
	$this->unauthorized();
	break;
      case 403:
	$this->Forbidden();
	break;
      case 404:
	$this->notFound();
	break;
      case 405:
	$this->methodNotAllowed();
	break;
      case 406:
	$this->notAcceptable();
	break;
      case 411:
	$this->lengthRequired();
	break;
      case 500:
	$this->internalServerError();
	break;
    }
  }
  
  private function OK(){
    header('HTTP/1.0 200 OK');
  }

  private function created($url = FALSE) {
    header('HTTP/1.0 201 Created');
    if ($url) {
      header('Location: '.$url);   
    }
  }

  private function noContent() {
    header('HTTP/1.0 204 No Content');
  }

  private function badRequest() {
    header('HTTP/1.0 400 Bad Request');
  }

  private function unauthorized() {
    header('HTTP/1.0 401 Unauthorized');
  }

  private function Forbidden() {
    header('HTTP/1.0 403 Forbidden');
  }

  private function notFound() {
    header('HTTP/1.0 404 Not Found');
  }

  private function methodNotAllowed() {
    header('HTTP/1.0 405 Method Not Allowed');
  } 

  private function notAcceptable() {
    header('HTTP/1.0 406 Not Acceptable');
    echo join(', ', array_keys($this->config['renderers']));
  }

  private function lengthRequired() {
    header('HTTP/1.0 411 Length Required');
  }

  private function internalServerError() {
    header('HTTP/1.0 500 Internal Server Error');
  }
}
?>