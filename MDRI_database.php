<?php
class MDRI_database {
  protected $connection=NULL;
  protected $host=NULL;
  protected $database=NULL;
  protected $user=NULL;
  protected $password=NULL;
  protected $port=NULL;
  protected $MDRI_responder=NULL;
  private $LastQueryAffectedRows=NULL;
  private $printheaders=FALSE;
  
  
  /**
   * Constructor of the MDRI_database class.
   * It will initialize the variables for the object.
   */
  function MDRI_database($host,$port,$user,$password,$responder) {
    $this->host=$host;
    $this->user=$user;
    $this->password=$password;
    $this->port=$port;
    $this->MDRI_responder=$responder;
  }
  
  /**
   * Method used by a HEAD request. It will insert a header containing user's privileges foreach table in the request.
   */
  public function PrintHeaders($boolean){
    $this->printheaders=$boolean;
  }
  
  /**
   * Recursive method for virtual tables.
   * Controls if a table is a virtual table.
   * Each join is composed by two tables; while the first table isn't a first level table it will recursively call itself for analyze it.
   */
  public function Join_controller($table,$database,$DBtablelist){
    $elenco=array();
    $tmp=array();
    $join=array();
    if(list($table1,$table2,$type,$condition)=$this->Is_join($table,$database)){						//if it's a join
      if((array_search($table1,$DBtablelist,TRUE)===FALSE)){									//if the first table isn't a first level table
	list($join,$elenco)=$this->Join_controller($table1,$database,$DBtablelist);
	if($join===FALSE && $elenco===FALSE){
	  return array(FALSE,FALSE);}
	  array_push($elenco,$table2);
	  $tmp=array("type" => $type,"condition" => $condition);
	  array_push($join,$tmp);
      }
      else{															//else if it's first level
	array_push($elenco,$table1,$table2);
	array_push($join,FALSE);
	$tmp=array("type" => $type,"condition" => $condition);
	array_push($join,$tmp);
      }
      return array($join,$elenco);
    }
    else{
      return array(FALSE,FALSE);
    }
  }
  
  /**
   * Method used for determine if a table is a virtual table in that database
   * It will select the virtual table in the MDRI_joins table and return: 
   * - the two tables of the joins
   * - Join type
   * - Join condition
   */
  private function Is_join($table,$database){
    $query=sprintf("SELECT * FROM \"MDRI_Joins\" WHERE \"join_name\" = '%s' AND \"join_database\" = '%s';",$table,$database);
    if($return=$this->Execute($query)){
      return array($return[0]["join_table1"],$return[0]["join_table2"], $return[0]["join_type"],$return[0]["join_condition"]);
    }
    else {
      return FALSE;
    }
  }
  
  /**
   * Method used to obtain the list of the table in the database.
   * If the object is not already connected to that database it will connect to it.
   */
  public function Tablelist($database = FALSE){
    if($database!==FALSE){
      $this->Connect($database);}
      $query="SELECT table_name FROM information_schema.tables WHERE table_schema='public';";
      $return=$this->Execute($query);
      if($database!==FALSE){
      }
      $tablelist=array();
      foreach($return as $returned) {
	array_push($tablelist,$returned["table_name"]);
      }
      return($tablelist);
  }
  
  /**
   * Method connect.
   * Used to connect to a database if isn't already connected.
   */
  public function Connect($database){
    if(strcmp($this->database,$database)==0 && !empty($this->database)){								//already connected to that database
      return TRUE;}
      else{
	$this->Disconnect();}
	$this->database=$database;
	if($this->connection=@pg_connect("host=$this->host port=$this->port dbname=$this->database user=$this->user password=$this->password"))
	{
	  return(TRUE);
	}
	else {
	  return(FALSE);
	}
  }
  
  /**
   * Method disconnect.
   * Used to disconnect from a database.
   */
  public function Disconnect(){
    if($this->connection!=NULL){
      @pg_close($this->connection);
      $this->database=NULL;
    }
  }
  
  /**
   * Method used to return a list of field that compose the composite key or the primary key.
   */
  public function Get_primarykey($table){
    $table=addslashes($table);
    $i=0;
    do{$query = sprintf('SELECT pg_attribute.attname FROM pg_class, pg_attribute, pg_index WHERE pg_class.oid = pg_attribute.attrelid AND pg_class.oid = pg_index.indrelid AND pg_index.indkey[%d] = pg_attribute.attnum AND pg_index.indisprimary = \'t\' AND relname=\'%s\'', $i, $table);
      $row=$this->Execute($query);
      if ($row != FALSE) {
	$primary[] = $row[0]['attname'];
      } 
      $i++;
    } while ($row);
    return($primary);
  }
  
  /**
   * Method Execute.
   * This method is used to execute a query (usually a select SQL query) on the database.
   * It will parse the returned values using the function pg_affected_rows.
   */
  public function Execute($query){
    $this->MDRI_responder->echo_query($query);
    $result=@pg_query($this->connection,$query);
    if($result===FALSE){
      $this->LastQueryAffectedRows=0;
      $this->MDRI_responder->Return_code(500);
      exit(-1);
      return(FALSE);
    }
    else
    {
      $this->LastQueryAffectedRows=pg_affected_rows($result);
      $arr=pg_fetch_all($result);
      return($arr);q
    }
  }
  
  /**
   * Insert method.
   * Used to execute a insert or modify SQL query.
   */
  public function Insert($query){
    $this->MDRI_responder->echo_query($query);
    $this->LastQueryAffectedRows=0;
    if(($result=@pg_query($this->connection,$query))!==FALSE){
      $this->LastQueryAffectedRows=pg_affected_rows($result);
      return(TRUE);
    }
    else
    {
      return(FALSE);
    }
  }
  
  /**
   * Method delete.
   * Used to execute a delete SQL query.
   */
  public function Delete($query){
    $this->MDRI_responder->echo_query($query);
    $this->LastQueryAffectedRows=0;
    if(($result=@pg_query($this->connection,$query))!==FALSE){
      $this->LastQueryAffectedRows=pg_affected_rows($result);
      return(TRUE);
    }
    else
    {
      return(FALSE);
    }
  }
  
  /**
   * Method Escape_string. It will escape the reserved charaters of a string.
   */
  public function Escape_string($string) {
    return  pg_escape_string($this->connection,$string);
  }
  
  /**
   * This method is used to determine if a PK has associated with it a sequence.
   * If it has, the method will extract the next value of the sequence using the PostgreSQL nextval function.
   * This one is a atomic function: it will estract the next value of the sequence and increase the latter.
   */
  public function IsPKSequence($primary,$table){
    $relname=$table."_".$primary."_seq";
    $query=sprintf("SELECT relname FROM pg_class WHERE relkind = 'S' AND relname='%s';",$relname);
    $result=$this->Execute($query);
    if($result!=FALSE){
      $query="SELECT nextval('customers_customerid_seq');";
      $result=$this->Execute($query);
      return($result[0]["nextval"]);}
      else{return(FALSE);}
  }
  
  /**
   * This method will return an array of privileges for the user on the specified table
   */
  public function Privileges($table){
    if(strcmp($table,"information_schema.tables")==0){
      $query=sprintf("select usename, nspname || '.' || relname as relation, case relkind when 'r' then 'TABLE' end as relation_type, priv from pg_class join pg_namespace on pg_namespace.oid = pg_class.relnamespace, pg_user, (values('SELECT', 1),('INSERT', 2),('UPDATE', 3),('DELETE', 4)) privs(priv, privorder) where relkind in ('r') and has_table_privilege(pg_user.usesysid, pg_class.oid, priv) and not (nspname ~ '^pg_' or nspname = 'information_schema') and usename='%s' order by 2, 1, 3, privorder;",$user);
    }
    else{
      $query=sprintf("select usename, nspname || '.' || relname as relation, case relkind when 'r' then 'TABLE' end as relation_type, priv from pg_class join pg_namespace on pg_namespace.oid = pg_class.relnamespace, pg_user, (values('SELECT', 1),('INSERT', 2),('UPDATE', 3),('DELETE', 4)) privs(priv, privorder) where relkind in ('r') and has_table_privilege(pg_user.usesysid, pg_class.oid, priv) and usename='%s' and relname='%s' order by 2, 1, 3, privorder;",$this->user,$table);
    }
    $return=$this->Execute($query);
    return($return);
  }
  
  /**
   * Method used to estract a list of aggregate functions existing on the server.
   */
  public function LoadAggregateFunctions(){
    $query="select proname from pg_proc WHERE proisagg GROUP BY proname;";
    $return=$this->Execute($query);
    $functions=array();
    if($return!==FALSE){
      foreach($return as $aggregatefunction) {
	array_push($functions,$aggregatefunction['proname']);
      }
      return ($functions);
    }
    return NULL;
  }
  
  /**
   * Method used to determine if a table exists.
   * If the provided table exists it will return TRUE, otherwise FALSE.
   */
  public function Doestableexists($table, $DBtablelist =FALSE){
    if($DBtablelist!==FALSE && $DBtablelist!==NULL){
      $tablelist=$DBtablelist;}
      else{
	$tablelist=$this->Tablelist();
      }
      foreach($tablelist as $tab){
	if (strcmp($table,$tab)==0){
	  return(TRUE);
	  break;
	}
      }
      return(FALSE);
  }
  
  public function CheckcreateTABprivileges($database){
    return TRUE;
  }
  
  /**
   * This method will check if a user has privileges to create a database.
   */
  public function CheckcreateDBprivileges(){
    $query=sprintf("SELECT * FROM pg_authid WHERE rolname = '%s';",$this->user);
    $return=$this->Execute($query);
    if($return!==FALSE){
      if(strcasecmp($return[0]["rolcreatedb"],"t")==0){
	return TRUE;
      }
      if(strcasecmp($return[0]["rolsuper"],"t")==0){
	return TRUE;
      }
    }
    return FALSE;
  }
  
  /**
   * This method will check if the user has the privileges $privileges (DELETE, INSERT, SELECT or UPDATE) on each table provided.
   * The list of the tables is contained in the $tables array.
   */
  public function Checktablepermissions($tables,$permission){
    $p=0;
    foreach($tables as $tablecombination){
      if($tablecombination!==FALSE){
	foreach($tablecombination as $table){
	  $permitted=$this->Privileges($table);
	  if($permitted!=FALSE){
	    $listpermissions="";
	    foreach($permitted as $tablepermission){
	      $listpermissions.=$tablepermission["priv"]." ";
	      if(strcmp($tablepermission["priv"],$permission)==0){
		$p=1;}
	    }
	    if($p!=1){
	      return(FALSE);
	    }
	    $p=0;
	    if(strcmp($table,"information_schema.tables")!=0 && strcmp($table,"pg_database")!=0){
	      if($this->printheaders){
		header($table.":".$listpermissions);}
	    }
	    unset($listpermissions);
	  }
	  else{
	    return(FALSE);}
	}
      }
    }
    return (TRUE);
  }
  
  /**
   * This method will return a list of columns of the provided table.
   */
  public function Columnlist($table){
    $query=sprintf("SELECT column_name FROM information_schema.columns WHERE table_name ='%s'",$table);
    $result=$this->Execute($query);
    if($result!=FALSE){
      return ($result);}
      else{
	return(FALSE);
      }
      
  }
  
  /**
   * This method will determine if a database Exists or not.
   */
  public function DoesDatabaseExists($database){
    $query=sprintf("SELECT datname FROM pg_database WHERE datname ='%s';",$database);
    $result=$this->Execute($query);
    if($result!=FALSE){
      return (TRUE);}
      else{
	return(FALSE);
      }
  }
  
  /**
   * This method is used by a POST or PUT request to execute a ALTER TABLE SQL query.
   * A part of the SQL query ($subquery) has already be written by the MDRI_put or MDRI_post object caller.
   */
  public function AlterTable($subquery,$table){
    $query="ALTER TABLE ".$table." ".$subquery.";";
    $return=$this->Insert($query);
    if($this->LastQueryAffectedRows!=0 || $return!=FALSE){
      return TRUE;}
      return FALSE;
  }
  
  /**
   * This method is used by a POST or PUT request to execute a ALTER DATABASE SQL query.
   * A part of the SQL query ($subquery) has already be written by the MDRI_put or MDRI_post object caller.
   */
  public function AlterDatabase($subquery,$database){
    $query="ALTER DATABASE \"".$database."\" ".$subquery.";";
    $return=$this->Insert($query);
    if($this->LastQueryAffectedRows!=0 || $return!=FALSE){
      return TRUE;}
      return FALSE;
  }
  
  /**
   * This method is used by a POST or PUT request to execute a CREATE DATABASE SQL query.
   * A part of the SQL query ($subquery) has already be written by the MDRI_put or MDRI_post object caller.
   */
  public function CreateDatabase($subquery,$database){
    $query="CREATE DATABASE \"".$database."\" ".$subquery.";";
    $return=$this->Insert($query);
    if($this->LastQueryAffectedRows!=0 || $return!=FALSE){
      return TRUE;}
      return FALSE;
  }
  
  /**
   * This method is used by a POST or PUT request to execute a CREATE TABLE SQL query.
   * A part of the SQL query ($subquery) has already be written by the MDRI_put or MDRI_post object caller.
   */
  public function CreateTable($subquery,$table,$extra=FALSE){
    $query="CREATE TABLE ".$table." ( ".$subquery." )";
    if(!empty($extra)){
      foreach($extra as $key =>$value){
	$query.=$key." ".$value." ";
      }
    }
    $query.=";";
    $return=$this->Insert($query);
    var_dump($this->LastQueryAffectedRows);
    if($this->LastQueryAffectedRows!=0 || $return!=FALSE){
      return TRUE;}
      return FALSE;
  }
  
  /**
   * Method used by a MDRI_head object in case of a request with a POE header.
   * It will insert the new created hash for the given URI, the user and the URI in the MDRI_POE table.
   */
  public function POEinsert($hash,$user,$time,$path){
    $query=sprintf("INSERT INTO \"MDRI_poe\" (\"POE_hash\", \"POE_user\", \"POE_path\",\"POE_time\",\"Used\") VALUES ('%s', '%s', '%s','%s','FALSE');",$hash,$user,$path,$time);
    return($this->Insert($query));
  }
  
  /**
   * Method used by a MDRI_post object to determine if a hash is valid and can be used.
   * It will check in the MDRI_POE table if exist a hash with that user and URI.
   */
  public function Hashcontrol($hash,$user,$URI){
    $this->Connect("MDRI");
    $query=sprintf("SELECT * FROM \"MDRI_poe\" WHERE \"POE_hash\"='%s' AND \"POE_user\"='%s' AND \"POE_path\"='%s' AND \"Used\"='FALSE'",$hash,$user,$URI);
    $return=$this->Execute($query);
    if($return!=FALSE){
      return(TRUE);
    }
    else{
      return(FALSE);
    }
  }
  
  /**
   * Method used after the execution of a POST request (if all goes well).
   * It will set the "used" field to TRUE, the tuple associated with that combination of user, hashing, and URI.
   */
  public function Sethashused($hash,$user,$URI){
    $this->Connect("MDRI");
    $query=sprintf("UPDATE \"MDRI_poe\" SET \"Used\"='TRUE' WHERE \"POE_hash\"='%s' AND \"POE_user\"='%s' AND \"POE_path\"='%s' AND \"Used\"='FALSE'",$hash,$user,$URI);
    $return=$this->Insert($query);
    if($return!=FALSE){
      return(TRUE);} else {
	return (FALSE);}
  }
  
  /**
   * Method used to create and execute a DELETE DATABASE SQL query.
   */
  public function DeleteDatabase($database){
    $query="DROP DATABASE \"".$database."\" ;";
    return($this->Delete($query));
  }
  
  /**
   * Method used to create and execute a DELETE TABLE SQL query.
   */
  public function DeleteTable($table){
    $query="DROP TABLE \"".$table."\";";
    return($this->Delete($query));
  }
  
  /**
   * Method used to create a SQL query for delete a tuple.
   * It will use all the tables in the $table array; first table goes in the FROM clause others goes in the USING clause.
   * $where, joins conditions ($joins["condition"]) and user conditions ($conditions) goes in the WHERE clause.
   */
  public function DeleteEntry($table,$where,$joins,$conditions){
    $primary_table=$table[0][0];
    $used=array();
    unset($table[0][0]);
    $query="DELETE FROM ".$primary_table;
    if(count($table)!=1 || !empty($table[0])){
      foreach($table as $tablegroup){
	foreach($tablegroup as $tabella){
	  if(array_search($tabella,$used)===FALSE){
	    if(empty($used)){
	      $query.=" USING ";
	    }
	    array_push($used,$tabella);
	    $query.=$tabella.", ";
	  }
	}
      }
      $query= substr($query, 0, -3);
    }
    $therearejoins=FALSE;
    foreach($joins as $join){
      if($join!==FALSE){
	$therearejoins=TRUE; break;
      }
    }
    if($therearejoins || !empty($where) || !empty($conditions)){
      $query.=" WHERE ";
      foreach($joins as $join){
	if($join!==FALSE){
	  foreach($join as $join_clause) {
	    if($join_clause!==FALSE){
	      $query.=$join_clause["condition"]." AND ";
	    }
	  }
	}
      }
      if(!empty($where)){
	$query.=$where;
      }
      if(!empty($conditions)){
	foreach($conditions as $condition) {
	  $query.=$condition." AND ";  
	}
      }
    }
    $query=substr($query,0,-5);
    $query.=";";
    $result=$this->Delete($query);
    if($this->LastQueryAffectedRows==0 && $result){
      return 404;
    }
    if($result){
      return 1;
    }else{
      return (-1);}
  }
  
  /**
   * Method used to create a SQL SELECT query.
   * It will use all the parameters analyzed by the interpreter class:
   * - $type = URI level
   * - $attrib = list of attributes
   * - $table = list of tables (first level tables)
   * - $join_array = list of all joins, with type and conditions
   * - $where = WHERE clause (on some PKs)
   * - $conditions = User conditions (extracted from query string)
   * - $limit = query limit (extracted by configuration file=
   * - $offset = OFFSET of the selection
   * - $group = GROUP BY clause
   * - $order = ORDER BY clause
   * - $having = HAVING clause
   */
  public function SelectQueryConstructor($type,$attrib,$table,$join_array,$where,$conditions,$limit,$offset,$group,$order,$having){
    //normalization for URI with level 0 or 1...
    if($type==0){
      $attrib[0]="datname";
      $table[0][0]="pg_database";
    }
    if($type==1){
      $attrib[0]="table_name";
      $table[0][0]="information_schema.tables";
      if(!empty($where)){$where.=" AND ".$where;}else{
	$where="table_schema = 'public' ";}
    }
    //end normalization...
    $query="SELECT ";
    for($c=0;$c<count($attrib);$c++){
      $query.=$attrib[$c];
      if($c!=count($attrib)-1){
	$query.=", ";}
    }
    $query.=" FROM ";
    for($c=0;$c<count($table);$c++){
      $query.=$table[$c][0];
      if($join_array[$c]!==FALSE && $type!=0){
	foreach($join_array[$c] as $c2=>$join){
	  if($join!==FALSE){
	    $query.=" ".$join["type"]." ".$table[$c][$c2]." ON ".$join["condition"];
	  }
	}
      }
      $query.=", ";
    }
    $query = substr($query, 0, -2);
    if($where!=""){
      $query.=" WHERE ".$where;
      if(!empty($conditions)){
	foreach($conditions as $condition) {
	  $query.=" AND ".$condition;  
	}
      }
    }
    else{
      if(!empty($conditions)){
	$query.=" WHERE ";
	foreach($conditions as $condition) {
	  $query.=$condition." AND ";  
	}
	$query=substr($query,0,-5);
      }
    }
    if(!empty($group)){
      $query.=" GROUP BY ";
      foreach($group as $entry) {
	$query.= $entry.", ";
      }
      $query=substr($query,0,-2);
    }
    if(!empty($order)){
      $query.=" ORDER BY ";
      foreach($order as $entry) {
	if($entry[1]!==FALSE){
	  $query.="\"".$entry[0]."\" ".$entry[1].", ";  
	}
	else{
	  $query.="\"".$entry[0]."\", ";
	}
      }
      $query=substr($query,0,-2);
    }
    if(!empty($having)){
      $query.=" HAVING ";
      foreach($having as $clause) {
	$query.= $clause.", ";
      }
      $query=substr($query,0,-2);
    }
    if(intval($limit)!=-1){
      $query.=" LIMIT ".intval($limit);
    }
    if(!empty($offset)){
      $query.=" OFFSET ".intval($offset);
    }
    $query.=";";
    return($query);
  }
}
?>