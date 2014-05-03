<?php
require_once 'MDRI_database.php';
require_once 'Interpreter.php';
require_once 'MDRI_responder.php';

class MDRI_head {
  private $URI=NULL;
  private $user=NULL;
  private $database=NULL;
  private $Defaultdatabase=NULL;
  private $MDRI_database=NULL;
  private $configurationdatabase=NULL;
  private $MDRI_responder=NULL;
  private $my_interpreter=NULL;
  private $URI_type=NULL;
  private $IPsender=NULL;
  private $table=NULL;
  private $entry=NULL;
  private $attrib=NULL;
  private $config=NULL;
  
  /**
   * Constructor of the MDRI_head class.
   * It uses variables taken from $config array (the array containing the configuration parameters) to initialize some of the variables.
   * This method will also create a new istance of MDRI_responder and MDRI_database classes.
   */
  function MDRI_head($config) {
    $this->flush();
    $this->config=$config;
    unset($config);
    $this->user=$this->config["DEFAULT_USER"];
    $password=$this->config["DEFAULT_PASSWORD"];
    $this->IPsender=$_SERVER['REMOTE_ADDR'];
    $this->MDRI_responder =new MDRI_responder($this->user,$this->IPsender,$this->config["QUERY_LOG"],$this->config["ERROR_LOGGER"]);
    $this->MDRI_database =new MDRI_database($this->config["HOST"],$this->config["PORT"],$this->user,$password,$this->MDRI_responder);
    if (isset($_SERVER['REQUEST_URI'])) {
      $this->URI=parse_url($this->URI,PHP_URL_PATH);
      $this->URI=rtrim($_SERVER["REQUEST_URI"], '/');
    }
  }
  
  public function init(){
    $this->head();
  }
  
  /**
   * This method is the core of MDRI_head class.
   * It will use analyze the headers and the POE configuration (if enabled or disabled).
   * If POE is disabled or is not set the POE header in request, it will create a new MDRI_get instance. In this case the analysis of the URI is entrusted to the latter.
   * This new MDRI_get object will act like a normal MDRI_get object except for the fact that it will not return any JSON representation of data.
   * If the POE functionality is enabled and is set the POE header in the HTTP request it will create a new hash string and save all parameters in the MDRI_POE table in Configuration Database.
   * The hash created will be returned, to the client, inside the HTTP response header "POE_Link".
   */
  private function head(){
    $request_headers=apache_request_headers();
    if(!isset($request_headers["POE"]) || !IsPOEenabled()){
      $MDRI_GET=new MDRI_get($this->config);
      $MDRI_GET->Silenceresponder();
      $MDRI_GET->init();
      exit(0);
    }
    elseif(IsPOEenabled() && isset($request_headers["POE"])){
      $this->my_interpreter=new Interpreter($this->URI,"", $this->MDRI_database,$this->MDRI_responder,"HEAD", $this->config["CONFIGURATION_DATABASE"],$this->config["DEFAULT_DATABASE"],$this->config["ERROR_LOGGER"]);
      list($this->URI_type, $this->database, $this->table, $this->entry, $this->attrib)= $this->my_interpreter->URIanalyzer();
      if($this->URI_type<3 && count($this->table)<=1 && count($this->table[0])<=1){
	$time=microtime(true);
	$string=$this->user.$this->URI.$time;
	//CREATING HASH.
	$hash=sha1($string);
	$this->MDRI_database->Connect($this->config["CONFIGURATION_DATABASE"]);
	//INSERTING ALL DATA (HASH, USER, TIME, URI) IN MDRI_POE TABLE.
	if($this->MDRI_database->POEinsert($hash,$this->user,$time,$this->URI)){
	  $this->MDRI_database->Disconnect();
	  //THE HASH IS RETURNED TO CLIENT INSIDE "POE_Link" HEADER.
	  header("POE-Link: ".$this->URI."/poe-hash-".$hash);
	}
	else{
	  $this->MDRI_responder->Return_code(500,0);
	  $this->MDRI_database->Disconnect();
	}}
	else{
	  $this->MDRI_responder->Return_code(400,0);
	}
    }
  }
  
  public function flush(){
    $this->URI=NULL;
    $this->user=NULL;
    $this->database=NULL;
    $this->Defaultdatabase=NULL;
    $this->MDRI_database=NULL;
    $this->configurationdatabase=NULL;
    $this->MDRI_responder=NULL;
    $this->my_interpreter=NULL;
    $this->URI_type=NULL;
    $this->IPsender=NULL;
    $this->table=NULL;
    $this->entry=NULL;
    $this->attrib=NULL;
    $this->config=NULL;
  }
}
?>