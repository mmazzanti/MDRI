<?php
require_once 'MDRI_database.php';
require_once 'Interpreter.php';
require_once 'MDRI_responder.php';
/**
* The MDRI_get class will be used for parsing HTTP GET requests
*/
class MDRI_get {
  private $table= NULL;
  var $output= NULL;
  private $URI = NULL;
  private $attrib=NULL;
  private $entry=NULL;
  private $query=NULL;
  private $database=NULL;
  private $URI_type=NULL;
  private $MDRI_database=NULL;
  private $my_interpreter=NULL;
  private $MDRI_responder=NULL;
  private $where=NULL;
  private $IPsender=NULL;
  private $join=NULL;
  private $config=NULL;
  private $user=NULL;
  private $conditions=NULL;
  
  /**
  * Constructor of the MDRI_get class.
  * It uses variables taken from $config array (the array containing the configuration parameters) to initialize some of the variables.
  * This method will also create a new istance of MDRI_responder and MDRI_database classes.
  * It will save the URI path part inside the variable $URI and the query string inside the variable $conditions
  */
  function MDRI_get($config) {
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
      parse_str($_SERVER['QUERY_STRING'], $this->conditions);
    }
    else{
      $this->MDRI_responder(500);
      exit(0);
    }
  }
  
  public function init(){
    $this->get();
  }
  
  /**
  * This method will be used by the class MDRI_head.
  * It will Silence the MDRI_responder, the latter will not put a JSON representation of a resource in the response body.
  * It will also insert headers (one foreach table used) containing the related privileges (INSERT, UPDATE, SELECT ecc...)
  */
  public function Silenceresponder(){
    $this->MDRI_responder->Silencer();
    $this->MDRI_database->PrintHeaders(TRUE);
    return;
  }
  
  /**
  * This method is the core of MDRI_get class.
  * It will use the interpreter to analyze the URI and estract:
  * 	- level of URI
  * 	- database
  * 	- list of tables
  * 	- list of entry
  * 	- list fo attributes
  * 	- where clause
  * 	- list of joins clause
  * With these parameters it will create the SQL SELECT query and estract data from the database.
  */
  private function get(){
    $this->my_interpreter=new Interpreter($this->URI,$this->conditions, $this->MDRI_database,$this->MDRI_responder,"SELECT",$this->config["CONFIGURATION_DATABASE"],$this->config["DEFAULT_DATABASE"],$this->config["ERROR_LOGGER"],$this->config["QUERY_LIMIT"]);
    list($this->URI_type, $this->database, $this->table, $this->entry, $this->attrib,$this->where,$this->join)= $this->my_interpreter->URIanalyzer();
    list($this->conditions,$HAVING,$GROUP,$ORDER,$offset)=$this->my_interpreter->Conditions_analyzer();
    if(!level_control($this->URI_type,"SELECT")){
      $this->MDRI_responder->Return_code(403);
      exit(0);
    }
    
    if(!$this->MDRI_database->Connect($this->database)){
      $this->MDRI_responder->Return_code(400);
      exit(0);
    }
    //CREATING THE SQL QUERY
    $this->query=$this->MDRI_database->SelectQueryConstructor($this->URI_type,$this->attrib,$this->table,$this->join,$this->where,$this->conditions,$this->config["QUERY_LIMIT"],$offset,$GROUP,$ORDER,$HAVING);
    //EXECUTING THE QUERY
    $this->output=$this->MDRI_database->Execute($this->query);
    $this->MDRI_responder->Return_value($this->output);
  }

  private function flush(){
	  $this->method='GET';
	  $this->table= NULL;
	  $this->output= NULL;
	  $this->URI = NULL;
	  $this->attrib=NULL;
	  $this->entry=NULL;
	  $this->query=NULL;
	  $this->database=NULL;
	  $this->URI_type=NULL;
	  $this->MDRI_database=NULL;
	  $this->my_interpreter=NULL;
	  $this->MDRI_responder=NULL;
	  $this->where=NULL;
	  $this->IPsender=NULL;
	  $this->join=NULL;
	  $this->config=NULL;
	  $this->user=NULL;
	  $this->conditions=NULL;	  
  }
  }
  ?>