<?php
require_once 'MDRI_database.php';
require_once 'Interpreter.php';
require_once 'MDRI_responder.php';

class MDRI_delete {
  private $table= NULL;
  private $URI = NULL;
  private $attrib=NULL;
  private $entry=NULL;
  private $database=NULL;
  private $Defaultdatabase=NULL;
  private $configurationdatabase=NULL;
  private $URI_type=NULL;
  private $MDRI_database=NULL;
  private $my_interpreter=NULL;
  private $MDRI_responder=NULL;
  private $IPsender=NULL;
  private $user=NULL;
  private $where=NULL;
  private $join=NULL;
  private $conditions=NULL;
  
  /**
   * Constructor of the MDRI_delete class.
   * It uses variables taken from $config array (the array containing the configuration parameters) to initialize some of the variables.
   * This method will also create a new istance of MDRI_responder and MDRI_database classes.
   * It will save the URI path part inside the variable $URI and the query string inside the variable $conditions
   */
  function MDRI_delete($config) {
    $this->flush();
    $this->config=$config;
    unset($config);
    $this->user=$this->config["DEFAULT_USER"];
    $password=$this->config["DEFAULT_PASSWORD"];
    $this->IPsender=$_SERVER['REMOTE_ADDR'];
    $this->MDRI_responder =new MDRI_responder($this->user,$this->IPsender,$this->config["QUERY_LOG"],$this->config["ERROR_LOGGER"]);
    $this->MDRI_database =new MDRI_database($this->config["HOST"],$this->config["PORT"],$this->user,$password,$this->MDRI_responder);
    if (isset($_SERVER['REQUEST_URI'])) {
      $this->URI=rtrim($_SERVER["REQUEST_URI"], '/');
      $this->URI=parse_url($this->URI,PHP_URL_PATH);
      parse_str($_SERVER['QUERY_STRING'], $this->conditions);
    }
    else{
      $this->MDRI_responder(500);
      exit(0);
    }
  }
  
  public function init(){
    $this->delete();
  }
  
  /**
  * Method delete(); this method will create a new istance of class Interpreter.
  * It will use the Interpreter for analyze the URI and then according to the URI level :
  * 0 - can't delete using a URI level 0; returned error 405 (METHOD NOT ALLOWED)
  * 1 - delete database
  * 2 - delete table
  * 3 - delete entry
  * 4 - cant delete only a attribute of an entry; returned error 405 (METHOD NOT ALLOWED)
  **/
  private function delete(){
    $this->my_interpreter=new Interpreter($this->URI,$this->conditions, $this->MDRI_database,$this->MDRI_responder,"DELETE",$this->config["CONFIGURATION_DATABASE"],$this->config["DEFAULT_DATABASE"],$this->config["ERROR_LOGGER"]);											//dico alla classe database di ricordarsi le varie clausole di join
    list($this->URI_type, $this->database, $this->table, $this->entry, $this->attrib,$this->where,$this->join)= $this->my_interpreter->URIanalyzer();
    switch ($this->URI_type){
      case 0:
	$this->MDRI_responder->Return_code(405);
	exit(0);
	break;
      case 1:
	if($this->MDRI_database->Connect($this->config["DEFAULT_DATABASE"])){
	  if($this->MDRI_database->DoesDatabaseExists($this->database)){
	    if($this->MDRI_database->DeleteDatabase($this->database)){
	      $this->MDRI_responder->Return_code(200); 
	    }
	    else{
	      $this->MDRI_responder->Return_code(500); 
	    }
	  }
	  else {
	    $this->MDRI_responder->Return_code(404);  
	  }
	}
	else{
	  $this->MDRI_responder->Return_code(400);
	}
	break;
      case 2:											//ELIMINAZIONE DI UNA TABELLA
	if($this->MDRI_database->Connect($this->database)){
	  if(count($this->table)==1 && count($this->table[0])==1){
	    if($this->MDRI_database->Doestableexists($this->table[0][0])){
	      if($this->MDRI_database->DeleteTable($this->table[0][0])){
		$this->MDRI_responder->Return_code(200); 
	      }
	      else{
		$this->MDRI_responder->Return_code(500); 
	      }
	    }
	    else {
	      $this->MDRI_responder->Return_code(404);
	    }
	  }
	  else
	  {$this->MDRI_responder->Return_code(400);}}
	  else{
	    $this->MDRI_responder->Return_code(400);}
	    break;
      case 3:											//ELIMINAZIONE DI UNA TUPLA
	if($this->MDRI_database->Connect($this->database)){
		  list($this->conditions)=$this->my_interpreter->Conditions_analyzer();
		  $esit=$this->MDRI_database->DeleteEntry($this->table,$this->where,$this->join,$this->conditions);
		  switch ($esit) {
		    case 404:
		      $this->MDRI_responder->Return_code(404);
		      break;
		    case 1:
		      $this->MDRI_responder->Return_code(200);
		      break;
		    case (-1):
		      $this->MDRI_responder->Return_code(500);
		      break; 
		  }
	}
	else{
	  $this->MDRI_responder->Return_code(400);}
	  break;
	  break;
		    case 4:    
		      $this->MDRI_responder->Return_code(405);
		      break;
		    default:
		      break; 
    }
  }
  
  public function flush(){
    $this->table= NULL;
    $this->URI = NULL;
    $this->attrib=NULL;
    $this->entry=NULL;
    $this->database=NULL;
    $this->Defaultdatabase=NULL;
    $this->configurationdatabase=NULL;
    $this->URI_type=NULL;
    $this->MDRI_database=NULL;
    $this->my_interpreter=NULL;
    $this->MDRI_responder=NULL;
    $this->IPsender=NULL;
    $this->user=NULL;
    $this->where=NULL;
    $this->join=NULL;
    $this->conditions=NULL;
  }
  
  
}
?>