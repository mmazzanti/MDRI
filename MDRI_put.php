<?php
require_once 'MDRI_database.php';
require_once 'Interpreter.php';
require_once 'MDRI_responder.php';

class MDRI_put {
  private $columnlist=NULL;
  private $URI = NULL;
  private $user=NULL;
  private $database=NULL;
  private $MDRI_database=NULL;
  private $MDRI_responder=NULL;
  private $requestData=NULL;
  private $URI_type=NULL;
  private $configurationdatabase=NULL;
  private $IPsender=NULL;
  private $table=NULL;
  private $entry=NULL;
  private $query='';
  private $attrib=NULL;
  private $pairs=NULL;
  private $my_interpreter=NULL;
  private $privileges=NULL;
  private $config=NULL;
  
   /**
   * Constructor of the MDRI_put class.
   * It uses variables taken from $config array (the array containing the configuration parameters) to initialize some of the variables.
   * This method will also create a new istance of MDRI_responder and MDRI_database classes.
   * It will save the URI path part inside the variable $URI and the query string inside the variable $conditions and save body content in the variable $requestData
   * If no content is set it will return error 204 (NO CONTENT)
   */
  function MDRI_put($config) {
    $this->flush();
    $this->config=$config;
    unset($config);
    $this->user=$this->config["DEFAULT_USER"];
    $this->IPsender=$_SERVER['REMOTE_ADDR'];
    $password=$this->config["DEFAULT_PASSWORD"];
    $this->MDRI_responder =new MDRI_responder($this->user,$this->IPsender,$this->config["QUERY_LOG"],$this->config["ERROR_LOGGER"]);
    $this->MDRI_database =new MDRI_database($this->config["HOST"],$this->config["PORT"],$this->user,$password,$this->MDRI_responder);
    if (isset($_SERVER['REQUEST_URI'])) {
      $this->URI=rtrim($_SERVER["REQUEST_URI"], '/');
    }
    else{
      $this->MDRI_responder(500);
      exit(0);
    }
    if (isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
      $this->requestData = '';
      $httpContent = fopen('php://input', 'r');			//php://input is a read-only stream that allows to read raw data from the request body. In the case of POST requests, it is preferable to use php://input instead of $HTTP_RAW_POST_DATA as it does not depend on special php.ini directives
      while ($data = fread($httpContent, 1024)) {
	$this->requestData .= $data;
      }
      fclose($httpContent);
    }
    else{								//else, no data set
      $this->MDRI_responder->Return_code(204,0);
      exit(0);
    }
  }
  
  public function init(){
    $this->privileges=UpdateorInsert("PUT");			//load POST use: UPDATE, INSERT or both
    if($this->privileges===FALSE){				//if method UpdateorInsert() returns FALSE there were errors in the analysis of configuration
      $this->MDRI_responder->Return_code(403);
      exit(0);
    }
    $this->put();
  }

  /**
  * Method delete(), main core of the MDRI_put class.
  * The last part of the URI will be stripped from URI as this part identifies the resource that you are going to create.
  * This method will create a new istance of class Interpreter; it will it for analyze the URI (without the last part) and then according to the URI level :
  * 0 - create/modify a new database
  * 1 - create/modify a new table
  * 2 - create/modify a new entry 
  * If the new resource can be created without errors it will return a code 201 (CREATED) and the header "Location" containing the URI of the newly created resource.
  * If the resource can be modified without errors il will return a code 200 (OK).
  **/
  private function put(){
    $this->my_interpreter=new Interpreter($this->URI,"", $this->MDRI_database,$this->MDRI_responder,"PUT",$this->configurationdatabase,$this->config["CONFIGURATION_DATABASE"],$this->config["DEFAULT_DATABASE"],$this->config["ERROR_LOGGER"]);
    $lastpartofURI=$this->my_interpreter->URIcutter($this->URI);
    $this->URI=str_replace("/".$lastpartofURI,'',$this->URI);
    $this->my_interpreter->SetURI($this->URI);
    list($this->URI_type, $this->database, $this->table, $this->entry, $this->attrib)= $this->my_interpreter->URIanalyzer();
    if(count($this->table)<=1 && count($this->entry)<=1 && count($this->table[0])<=1){			//the number of tables must be 1 (no multiple tables or virtual tables are admitted)
      switch ($this->URI_type){
	case 0:
	  if(!$this->MDRI_database->Connect($this->config["DEFAULT_DATABASE"])){
	    $this->MDRI_responder->Return_code(500);
	    exit(0);
	  }    
	  $this->ParseRequestData();
	  $database=urldecode($lastpartofURI);
	  if($this->MDRI_database->DoesDatabaseExists($database)){
	    if($this->privileges["UPDATE"]=1 && level_control($this->URI_type,"UPDATE") && $this->MDRI_database->CheckcreateDBprivileges()){
	      if(count($this->pairs)==1){
		$this->query='';
		if($this->AlterDatabase()){
		  if($this->MDRI_database->AlterDatabase($this->query,$database)){
		    $this->MDRI_responder->Return_code(200);
		  }
		  else {
		    $this->MDRI_responder->Return_code(500);
		  }
		}
		else{$this->MDRI_responder->Return_code(400);}
	      }else{$this->MDRI_responder->Return_code(400);}
	    }
	    else{
	      $this->MDRI_responder->Return_code(403);
	      exit(0);
	    }
	  }
	  else {
	    if($this->privileges["INSERT"]==1 && level_control($this->URI_type,"INSERT") && $this->MDRI_database->CheckcreateDBprivileges()){
	      if($this->CreateDatabase()){
		if($this->MDRI_database->CreateDatabase($this->query,$database)){
		  $this->MDRI_responder->Return_code(201,$this->URI."/".$lastpartofURI);
		}
		else {
		  $this->MDRI_responder->Return_code(500);
		}
	      }
	      else{
		$this->MDRI_responder->Return_code(400);
	      }
	    }
	    else{
	      $this->MDRI_responder->Return_code(403);
	      exit(0);
	    }
	  }
	  break;
	case 1:
	  if(!$this->MDRI_database->Connect($this->database)){
	    $this->MDRI_responder->Return_code(400);
	    exit(0);
	  }
	  $this->StripExtraActions();
	  $this->ParseRequestData();
	  $table=urldecode($lastpartofURI);
	  if($this->MDRI_database->Doestableexists($table)){
	    if($this->privileges["UPDATE"]==1  && level_control($this->URI_type,"UPDATE") && $this->MDRI_database->CheckcreateTABprivileges($this->database)){
	      foreach($this->pairs as $key => $value){
		if ($this->Searchcolumn($table,$key)){						//searches if the column already exists.
		  if($this->IsTableDelete($value,$key)){					//detects if the client want to delete the column (to delete a column the $value field must contain ""(empty string), "CASCADE" or "RESTRICT").
		  }else {
		    $this->query.="ALTER $key $value, ";
		  }
		}
		else{
		  $this->query.="ADD $key $value, ";
		}
	      }
	      if(isset($this->Extraactions)){
		foreach($this->Extraactions as $key =>$value){
		  $this->query.=$key." ".$value.", ";
		}
	      }
	      $this->query=substr($this->query, 0, -2);
	      if($this->MDRI_database->AlterTable($this->query,$table)){
		$this->MDRI_responder->Return_code(200);
	      }
	      else {
		$this->MDRI_responder->Return_code(500);
	      }
	    }
	    else{
	      $this->MDRI_responder->Return_code(403);
	      exit(0);}
	  }
	  else{
	    if($this->privileges["INSERT"]==1 && level_control($this->URI_type,"INSERT") && $this->MDRI_database->CheckcreateTABprivileges($this->database)){
	      foreach($this->pairs as $key => $value){
		$this->query.="$key $value , ";
	      }
	      $this->query=substr($this->query, 0, -2);
	      if($this->MDRI_database->CreateTable($this->query,$table,$this->Extraactions)){
		$this->MDRI_responder->Return_code(201,$this->URI."/".$lastpartofURI);
	      }
	      else {
		$this->MDRI_responder->Return_code(500);
	      }
	    }
	    else{
	      $this->MDRI_responder->Return_code(403);
	      exit(0);}
	  }
	  break;
	  case 2:
	    if(!$this->MDRI_database->Connect($this->database)){
	      $this->MDRI_responder->Return_code(400);
	      exit(0);
	    }
	    $this->ParseRequestData();
	    if(count($this->table[0])==1){
	      $primary=$this->MDRI_database->Get_primarykey($this->table[0][0]);
	      $PKs=$this->my_interpreter->Entriesparser($lastpartofURI,$primary);
	      if($PKs===FALSE){
		$this->MDRI_responder->Return_code(400);
		exit(0);
	      }
	      foreach($PKs as $key => $entry){
		$this->pairs[$key]=$entry;
	      }
	      if($this->IsInsert($primary)){
		if($this->privileges["INSERT"]==1 && level_control($this->URI_type,"INSERT") && $this->MDRI_database->Checktablepermissions($this->table,"INSERT")){
		  $query=$this->InsertCreator($this->table[0][0]);
		  if($this->MDRI_database->Insert($query)){
		    $this->MDRI_responder->Return_code(201,$this->URI."/".$lastpartofURI);
		  }else{
		    $this->MDRI_responder->Return_code(400);}  
		}
		else{
		  $this->MDRI_responder->Return_code(403);
		  exit(0);
		}
		
	      }
	      
	      else{
		if($this->privileges["UPDATE"]==1 && level_control($this->URI_type,"UPDATE") &&  $this->MDRI_database->Checktablepermissions($this->table,"UPDATE")){	
		  $query=$this->UpdateCreator($primary);
		  if($this->MDRI_database->Insert($query)){
		    $this->MDRI_responder->Return_code(200);
		  }else{
		    $this->MDRI_responder->Return_code(400);}
		}
		else{
		  $this->MDRI_responder->Return_code(403);
		  exit(0);}
	      }
	    }
	    else{
	      $this->MDRI_responder->Return_code(400);}	
	      break;
	  case 3:
	    $this->MDRI_responder->Return_code(400);
	    break;
	  case 4:
	    $this->MDRI_responder->Return_code(400);
	    break;
	  default:
	    $this->MDRI_responder->Return_code(400);
	    break;
      }}
      else {
	$this->MDRI_responder->Return_code(400);}
  }
  
  /**
  * This method will analyze the body of a "create database" request (URI level 0).
  * It will recognize all PostgreSQL fields (OWNER, TEMPLATE, ecc..); if a field can't be recognized it will return FALSE and the execution stops.
  */ 
  private function CreateDatabase(){
    $specialparameters=array("OWNER","TEMPLATE","ENCODING","LC_COLLATE","LC_CTYPE","TABLESPACE","CONNECTION LIMIT");
    foreach ($this->pairs as $parameter => $value) {
      if(strcasecmp($parameter, $specialparameters[0]) == 0){ $this->query.="OWNER $value "; continue;}
      elseif(strcasecmp($parameter, $specialparameters[1]) == 0){$this->query.="TEMPLATE $value ";continue;}
      elseif(strcasecmp($parameter, $specialparameters[2]) == 0){$this->query.="ENCODING $value ";continue;}
      elseif(strcasecmp($parameter, $specialparameters[3]) == 0){$this->query.="LC_COLLATE $value ";continue;}
      elseif(strcasecmp($parameter, $specialparameters[4]) == 0){$this->query.="LC_CTYPE $value ";continue;}
      elseif(strcasecmp($parameter, $specialparameters[5]) == 0){$this->query.="TABLESPACE $value ";continue;}
      elseif(strcasecmp($parameter, $specialparameters[6]) == 0){$this->query.="CONNECTION LIMIT $value ";continue;}
      return (FALSE);
    }
    return (TRUE);
  }

  /**
  * This method will analyze the body of a "alter database" request (URI level 0).
  * It will recognize all PostgreSQL fields (TABLESPACE, OWNER, ecc..); if a field can't be recognized is supposed to be a "configuration_parameter" (see PostgreSQL ALTER DATABASE documentation for more informations).
  */  
  private function AlterDatabase(){
    $specialparameters=array("TABLESPACE","OWNER","RENAME","CONNECTION LIMIT","RESET");
    foreach ($this->pairs as $parameter => $value) {
      if(strcasecmp($parameter, $specialparameters[0]) == 0){ $this->query.="SET TABLESPACE $value";}
      elseif(strcasecmp($parameter, $specialparameters[1]) == 0){$this->query.="SET OWNER TO $value";}
      elseif(strcasecmp($parameter, $specialparameters[2]) == 0){$this->query.="RENAME TO $value";}
      elseif(strcasecmp($parameter, $specialparameters[3]) == 0){$this->query.="CONNECTION LIMIT $value";}
      elseif(strcasecmp($parameter, $specialparameters[4]) == 0){
	if(strcasecmp($value, "ALL") == 0){$value="ALL";}
	$this->query.="RESET $value;";}
	else{
	  $this->query.="SET $parameter TO $value";}
    }
    return (TRUE);
  }
  
  
  
  private function Searchcolumn($table,$key){
    if($this->columnlist==NULL){
      $this->columnlist=$this->MDRI_database->Columnlist($table);
    }
    foreach($this->columnlist as $column){
      if (strcmp($key,$column["column_name"])==0){
	return(TRUE);
	break;
      }
    }
    return(FALSE);
  }
  
  private function IsTableDelete($value,$key){
    $deletetype=array("/^[C|c][A|a][S|s][C|c][A|a][D|d][E|e]$/","/^[R|r][E|e][S|s][T|t][R|r][I|i][C|c][T|t]$/");
    if(empty($value)){$this->query.="DROP $key, "; return(TRUE);}
    elseif(preg_match($deletetype[0],$value)){$this->query.="DROP $key CASCADE, ";return(TRUE);}
    elseif(preg_match($deletetype[1],$value)){$this->query.="DROP $key RESTRICT, ";return(TRUE);}
    return(FALSE);
  }
  
  private function StripExtraActions(){
    $split=preg_split("/(\n)(?:E|e)(?:X|x)(?:T|t)(?:R|r)(?:A|a)(?:A|a)(?:C|c)(?:T|t)(?:I|i)(?:O|o)(?:N|n)(?:S|s)(\n)/",$this->requestData);
    $this->requestData=$split[0];
    $Extra=$split[1];
    if(isset($Extra)){
      $this->ParseExtraActions($Extra);
    }
  }

  /**
  *This method will cut out all the fields after the string "EXTRAACTIONS". These fields will be analyzed by ParseExtraActions() method.
  */
  private function ParseExtraActions($Extra){
    $this->Extraactions = array();
    $pairs = $this->my_interpreter->OldParser($this->requestData,"\n","\\",FALSE);
    foreach ($pairs as $pair) {
      $parts = $this->my_interpreter->OldParser($pair,"=","\\",TRUE,1);
      if (isset($parts[0]) && isset($parts[1])) {
	$parts[0]=trim($parts[0]);$parts[1]=trim($parts[1]);
	$this->Extraactions[$parts[0]] = $parts[1];
      }
      if(isset($parts[0]) && !isset($parts[1])){
	$parts[0]=trim($parts[0]);
	if($parts[0]!=""){
	  $this->Extraactions[$parts[0]]=NULL;}
      }
    }
  }
  
  private function Doestableexists($table){
    $return=$this->MDRI_database->Doestableexists($table);
    if($return!=FALSE){
      $this->tableinquestion=$return;
      return(TRUE);
    }
    else{
      return(FALSE);
    }
  }
  
  /**
  * This method will create the SQL query containing the list of columns and respective values for modify a entry.
  * In the WHERE clause it will set the values of PKs (this is a modification of a tuple).
  */
  private function UpdateCreator($primaries){
    $where="";
    $pairs=$this->pairs;
    foreach($primaries as $primary){
      $where.="$primary = '".$this->pairs[$primary]."' AND ";
      unset($pairs[$primary]);
    }
    $where = substr($where, 0, -5);
    $values = '';
    foreach ($pairs as $column => $data) {
      $values.= $column."='".$data."', ";
    }
    $values = substr($values, 0, -2);
    $query = sprintf('UPDATE %s SET %s WHERE %s', $this->table[0][0], $values, $where);
    return($query);
  }

  /**
  * This method will create the SQL query containing the list of columns and respective values for create a entry.
  */
  private function InsertCreator($table){
    $values = '(';
    $columns='(';
    foreach ($this->pairs as $column => $data) {
      $columns .= "".$column.", ";
      $values.= "'".$data."', ";
    }
    $values = substr($values, 0, -2);
    $columns = substr($columns, 0, -2);
    $values.=")";
    $columns.=")";;
    $query = sprintf('INSERT INTO %s %s VALUES %s', $table, $columns, $values);
    return($query);
  }
  
  /**
  * This method will analyze the client's data in the body of the HTTP request
  * It will split the data using the method OldParser of Interpreter class and create an array of couple field/value
  */
  private function ParseRequestData() {
    $this->pairs = array();
    $pairs = $this->my_interpreter->OldParser($this->requestData,"\n","\\",FALSE);
    foreach ($pairs as $pair) {
      $parts = $this->my_interpreter->OldParser($pair,"=","\\",TRUE,1);
      if (isset($parts[0]) && isset($parts[1])) {
	$parts[0]=trim($parts[0]);$parts[1]=trim($parts[1]);;
	$this->pairs[$parts[0]] = $parts[1];
      }
      if(isset($parts[0]) && !isset($parts[1])){
	$parts[0]=trim($parts[0]);
	if($parts[0]!=""){
	  $this->pairs[$parts[0]]=NULL;}
      }
    }
  }

  /**
  * This method is used for detect if an entry already exists.
  * If there is already an entry with that PK's values It return TRUE otherwise FALSE.
  */
  private function IsInsert($primaries){
    $query="SELECT * FROM ".$this->table[0][0]." WHERE ";
    foreach($primaries as $primary){
      if(!isset($this->pairs[$primary])){
	return (FALSE);}
	$query.="".$primary."='{$this->pairs[$primary]}' AND ";
    }
    $query = substr($query, 0, -5);
    $query.=";";
    $returned=$this->MDRI_database->Execute($query);
    if($returned!=FALSE){
      return(FALSE);
    }
    return(!$returned);
  }
  
  public function flush(){
    $this->columnlist=NULL;
    $this->URI = NULL;
    $this->user=NULL;
    $this->database=NULL;
    $this->MDRI_database=NULL;
    $this->MDRI_responder=NULL;
    $this->requestData=NULL;
    $this->URI_type=NULL;
    $this->configurationdatabase=NULL;
    $this->IPsender=NULL;
    $this->table=NULL;
    $this->entry=NULL;
    $this->query='';
    $this->attrib=NULL;
    $this->pairs=NULL;
    $this->my_interpreter=NULL;
    $this->privileges=NULL;
    $this->config=NULL;
  }
}
?>