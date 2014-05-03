<?php
require_once 'MDRI_database.php';
require_once 'Interpreter.php';
require_once 'MDRI_responder.php';

class MDRI_post {
  private $columnlist=NULL;
  private $URI = NULL;
  private $user=NULL;
  private $database=NULL;
  private $MDRI_database=NULL;
  private $MDRI_responder=NULL;
  private $requestData=NULL;
  private $IPsender=NULL;
  private $URI_type=NULL;
  private $table=NULL;
  private $entry=NULL;
  private $attrib=NULL;
  private $pairs=NULL;
  private $hash=NULL;
  private $my_interpreter=NULL;
  private $config=NULL;
  private $query='';
  private $privileges=NULL;
  
    /**
   * Constructor of the MDRI_post class.
   * It uses variables taken from $config array (the array containing the configuration parameters) to initialize some of the variables.
   * This method will also create a new istance of MDRI_responder and MDRI_database classes.
   * It will save the URI path part inside the variable $URI and the query string inside the variable $conditions and save body content in the variable $requestData
   * If no content is set it will return error 204 (NO CONTENT)
   */
  function MDRI_post($config) {
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
    $this->privileges=UpdateorInsert("POST");			//load POST use: UPDATE, INSERT or both
    if($this->privileges===FALSE){				//if method UpdateorInsert() returns FALSE there were errors in the analysis of configuration
      $this->MDRI_responder->Return_code(500);
      exit(0);
    }
    $this->post();
  }

  /**
  * Method delete(), main core of the MDRI_post class.
  * If the POE functionality is enabled the part of URI containing the hash will be cutted, and the hash will be checked.
  * This method will create a new istance of class Interpreter; it will it for analyze the URI and then according to the URI level :
  * 0 - create/modify a new database
  * 1 - create/modify a new table
  * 2 - create/modify a new entry 
  * If the new resource can be created without errors it will return a code 201 (CREATED) and the header "Location" containing the URI of the newly created resource.
  * If the resource can be modified without errors il will return a code 200 (OK).
  **/
  private function post(){
    $this->my_interpreter=new Interpreter($this->URI,"", $this->MDRI_database,$this->MDRI_responder,"POST",$this->config["CONFIGURATION_DATABASE"],$this->config["DEFAULT_DATABASE"],$this->config["ERROR_LOGGER"]);
    if(IsPOEenabled()){
      $this->hash=$this->my_interpreter->URIcutter($this->URI);}
      if(!IsPOEenabled() || $this->Hashcontrol()){							//lazy evaluation. If POE is disabled $this->Hashcontrol() will not cut URI
	$this->my_interpreter->SetURI($this->URI);
	list($this->URI_type, $this->database, $this->table, $this->entry, $this->attrib)= $this->my_interpreter->URIanalyzer();
	if($this->URI_type<3 && count($this->table)<=1 && count($this->table[0])<=1){			//the number of tables must be 1 (no multiple tables or virtual tables are admitted)
	  switch ($this->URI_type){
	    case 0:
	      if(!$this->MDRI_database->Connect($this->config["DEFAULT_DATABASE"])){
		$this->MDRI_responder->Return_code(500);
		exit(0);
	      }
	      $this->ParseRequestData();
	      if(isset($this->pairs[databasename])){
		$database=$this->pairs[databasename];
		unset($this->pairs[databasename]);
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
			$this->MDRI_responder->Return_code(201,$this->URI."/".$database);
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
	      }
	      else{$this->MDRI_responder->Return_code(400);}
	      break;
	      case 1:
		if(!$this->MDRI_database->Connect($this->database)){
		  $this->MDRI_responder->Return_code(400);
		  exit(0);
		}    
		$this->StripExtraActions();
		$this->ParseRequestData();  
		if(isset($this->pairs[tablename])){
		  $table=$this->pairs[tablename];
		  unset($this->pairs[tablename]);
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
			$this->MDRI_responder->Return_code(201,$this->URI."/".$table);
		      }
		      else {
			$this->MDRI_responder->Return_code(500);
		      }
		    }
		    else{ 
		      $this->MDRI_responder->Return_code(403);
		      exit(0);}
		  }
		}
		else
		{
		  $this->MDRI_responder->Return_code(400);
		}
		break;
		case 2:
		  if(!$this->MDRI_database->Connect($this->database)){
		    $this->MDRI_responder->Return_code(400);
		    exit(0);
		  }
		  $this->ParseRequestData();
		  if(count($this->table[0])==1){
		    $primaries=$this->MDRI_database->Get_primarykey($this->table[0][0]);
		    foreach($primaries as $primary){
		      if(isset($this->pairs[$primary]) || $this->IsPKSequence($primary,$this->table[0][0])){			//lazy evaluation. IsPKSequence() will increment the sequence value. So this will be done if the client did not specify that field
		      }
		      else {
			$this->MDRI_responder->Return_code(400);
			exit(0);
		      }
		    }
		    if($this->IsInsert($primaries)){
		      if($this->privileges["INSERT"]==1 && level_control($this->URI_type,"INSERT") && $this->MDRI_database->Checktablepermissions($this->table,"INSERT")){
			$query=$this->InsertCreator($this->table[0][0]);
			if($this->MDRI_database->Insert($query)){
			  $subURI='';
			  foreach($primaries as $primary){$subURI.=$this->pairs[$primary].";";}					//creating the URI of the newly resource
			  $subURI = substr($subURI, 0, -1);
			  $this->MDRI_responder->Return_code(201,$this->URI."/".$subURI);		
			  $this->MDRI_database->Sethashused($this->hash,$this->user,$this->URI);
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
			$query=$this->UpdateCreator($primaries);
			if($this->MDRI_database->Insert($query)){
			  $this->MDRI_responder->Return_code(200);
			  $this->MDRI_database->Sethashused($this->hash,$this->user,$this->URI);
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
      else{
	$this->MDRI_responder->Return_code(405);
      }
  }
  
  /**
  * This method will analyze the body of a "create database" request (URI level 0).
  * It will recognize all PostgreSQL fields (OWNER, TEMPLATE, ecc..); if a field can't be recognized it will return FALSE and the execution stops.
  */ 
  private function CreateDatabase(){
    $specialparameters=array("OWNER","TEMPLATE","ENCODING","LC_COLLATE","LC_CTYPE","TABLESPACE","CONNECTION LIMIT");
    foreach ($this->pairs as $parameter => $value) {
      if(strcasecmp($parameter, $specialparameters[0]) == 0){ $this->query.="OWNER \"$value\" "; continue;}
      elseif(strcasecmp($parameter, $specialparameters[1]) == 0){$this->query.="TEMPLATE \"$value\" ";continue;}
      elseif(strcasecmp($parameter, $specialparameters[2]) == 0){$this->query.="ENCODING \"$value\" ";continue;}
      elseif(strcasecmp($parameter, $specialparameters[3]) == 0){$this->query.="LC_COLLATE \"$value\" ";continue;}
      elseif(strcasecmp($parameter, $specialparameters[4]) == 0){$this->query.="LC_CTYPE \"$value\" ";continue;}
      elseif(strcasecmp($parameter, $specialparameters[5]) == 0){$this->query.="TABLESPACE \"$value\" ";continue;}
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

  /**
  *This method will cut out all the fields after the string "EXTRAACTIONS". These fields will be analyzed by ParseExtraActions() method.
  */
  private function StripExtraActions(){
    $split=preg_split("/(\n)(?:E|e)(?:X|x)(?:T|t)(?:R|r)(?:A|a)(?:A|a)(?:C|c)(?:T|t)(?:I|i)(?:O|o)(?:N|n)(?:S|s)(\n)/",$this->requestData);
    $this->requestData=$split[0];
    $Extra=$split[1];
    if(isset($Extra)){
      $this->ParseExtraActions($Extra);
    }
  }
  
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
  * This method will detect if a PK field has a sequence associated with it
  * To do this it will use the namesake method of MDRI_database class.
  */
  private function ISPKSequence($primary,$table){
    $sequence=$this->MDRI_database->IsPKSequence($primary,$table);
    if($sequence!=FALSE){
      $this->pairs[$primary]=$sequence;
      return(TRUE);
    }
    return(FALSE);
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
  * This method will cut the hash from the URI and check if it is valid and not yet used.
  */
  private function Hashcontrol(){
    $URI=str_replace("/".$this->hash,'',$this->URI);
    $this->URI=$URI;
    $this->hash=substr($this->hash,9,40);
    return($this->MDRI_database->Hashcontrol($this->hash,$this->user,$URI));
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
    $returned=$this->MDRI_database->Execute($query);
    if($returned!=FALSE){
      return(FALSE);
    }
    return(!$returned);
  }
  
  
  public function flush(){
	  $this->columnlist=NULL;
	  $this->URI = NULL; //URI
	  $this->user=NULL;
	  $this->database=NULL;
	  $this->MDRI_database=NULL;
	  $this->MDRI_responder=NULL;
	  $this->requestData=NULL;
	  $this->IPsender=NULL;
	  $this->URI_type=NULL;
	  $this->table=NULL;
	  $this->entry=NULL;
	  $this->attrib=NULL;
	  $this->pairs=NULL;
	  $this->hash=NULL;
	  $this->my_interpreter=NULL;
	  $this->config=NULL;
	  $this->query='';
	  $this->privileges=NULL;
  }
}
?>