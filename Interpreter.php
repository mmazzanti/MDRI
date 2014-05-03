<?php
class Interpreter {
  private $errorlogger=NULL;
  private $defaultdabase=NULL;
  private $database=NULL;
  private $table=NULL;
  private $entry=NULL;
  private $attrib=NULL;
  private $join=NULL;
  private $request=NULL;
  private $DBtables=NULL;
  private $where=NULL;
  private $MDRI_controller=NULL;
  private $MDRI_responder=NULL;
  private $URI=NULL;
  private $get=NULL;
  private $configurationdatabase=NULL;
  private $querylimit=NULL;
  private $MDRI_database=NULL;
  
  /**
   * Construtor of the Interpreter class.
   * It will set the configuration parameters passed by the caller.
   */
  public function Interpreter($URI,$get,&$connection,$responder,$request,$configurationdatabase,$defaultdabase,$errorlogger,$limit=NULL){
    $this->request=$request;
    $this->URI= parse_url($URI, PHP_URL_PATH);
    $this->get=$get;
    $this->MDRI_database=$connection;
    $this->MDRI_responder=$responder;
    $this->configurationdatabase=$configurationdatabase;
    $this->defaultdabase=$defaultdabase;
    $this->errorlogger=$errorlogger;
    $this->querylimit=$limit;
  }
  
  /**
   * Method used to detect if a attribute in the ORDER BY clause must be ordered in ascending or descendin order.
   */
  private function ASC_DESC($string){
    $string=trim($string);
    if(strcasecmp(substr($string,-4)," ASC")==0){
      return array(substr($string,0,-4),"ASC");
    }
    elseif(strcasecmp(substr($string,-5)," DESC")==0) {
      return array(substr($string,0,-5),"DESC");
    }
    return array($string,FALSE);
  }
  
  /**
   * Method used for the query-string analysis.
   * It will detect all reserved fields (GROUP_BY, ORDER_BY, OFFSET) and parse the others.
   */
  public function Conditions_analyzer(){
    $GROUP=array();
    $ORDER=array();
    $offset=NULL;
    if(empty($this->get)){
      return NULL;
    }
    $get_normalized=array();
    $tmp=array();
    foreach($this->get as $field =>$get){
      if(strcasecmp($field,"GROUP_BY")==0){				//Reserved field GROUP_BY
	$GROUP=$this->Parser($get,",",TRUE);
	unset($this->get[$field]);
      }
      elseif(strcasecmp($field,"ORDER_BY")==0){				//Reserved field ORDER_BY
	foreach($this->Parser($get,",",TRUE) as $entry) {
	  array_push($ORDER,$this->ASC_DESC($entry));
	}
	unset($this->get[$field]);
      }
      elseif(strcasecmp($field,"OFFSET")==0){
	$offset=intval($this->Normalizer($get));			//Reserved field OFFSET
	unset($this->get[$field]);
      }
      elseif(is_array($get)){
	foreach($get as $sameget) {
	  array_push($tmp,$field,$sameget);
	  array_push($get_normalized,$tmp);
	  $tmp=array();
	}   
      }
      else{	
	array_push($tmp,$field,$get);
	array_push($get_normalized,$tmp);
	$tmp=array();
      }
    }
    $HAVING=array();
    $conditions=array();
    foreach($get_normalized as $key => $get) {
      if($this->IsAggregateFunction($get[0])){
	$this->ParseValue($get[1]);
	array_push($HAVING,$this->Normalizer($get[0])." ".$get[1]);}
	elseif(strcasecmp($get[0],"EXISTS")==0){							//special case EXISTS
	  if($this->ParseSubQuery($get[1],FALSE)){
	    array_push($conditions,"EXISTS ( ".$get[1].") ");
	  }
	  else{
	    $this->MDRI_responder->Return_code(400);
	    exit(0);
	  }
	}
	elseif(strcasecmp($get[0],"NOT EXISTS")==0){							//special case NOT EXISTS
	  if($this->ParseSubQuery($get[1],FALSE)){
	    array_push($conditions,"NOT EXISTS ( ".$get[1].") ");
	  }
	  else{
	    $this->MDRI_responder->Return_code(400);
	    exit(0);
	  }
	}
	elseif(strcasecmp("ROW(",substr($get[0],0,4))==0 && $get[0][strlen($get[0])-1]==')'){		//special case ROW(list)
	  if($operator=$this->IscomparisonOperator($get[1])){
	    if($this->ParseSubQuery($get[1],FALSE)){
	      array_push($conditions,$this->Normalizer($get[0])." ".$operator." (".$get[1].") ");
	    }
	    else{
	      $this->MDRI_responder->Return_code(400);
	      exit(0);
	    }
	  }
	  elseif ($this->IsLogicalOperator($get[1],FALSE,3)) {
	    array_push($conditions,$this->Normalizer($get[0])." ".$get[1]);
	  }
	  
	  else{
	    if($this->ParseSubQuery($get[1],FALSE)){
	      array_push($conditions,$this->Normalizer($get[0])." = (".$get[1].") ");
	    }else{
	      $this->MDRI_responder->Return_code(400);
	      exit(0);
	    }
	  }
	}
	else{
	  $this->ParseValue($get[1]);
	  array_push($conditions,"\"".$this->Normalizer($get[0])."\""." ".$get[1]);
	}
    }
    return array($conditions,$HAVING,$GROUP,$ORDER,$offset);
  }
  
  /**
   * Method used for parsing values.
   * It will detect Comparison operators and logical operators
   */
  private function ParseValue(&$value){
    if($operator=$this->IscomparisonOperator($value)){				//if $value is a comparison operator...
      if($this->ParseSubQuery($value,FALSE)){
	$value=$operator." (".$value.")";
	return;
      }
      elseif ($this->IsLogicalOperator($value,FALSE,5)) {			//if after a comparison operator, there is one logical this one can be an ANY () or ALL (), nothing else.
	$value=$operator." ".substr($value,2);
	return;
      }
      else{
	$value=$operator." '".$this->Normalizer($value)."'";}
	return;
    }
    elseif($this->IsLogicalOperator($value)){					//else if is a logical operator...
    }
    else{
      if($this->ParseSubQuery($value,FALSE)){
	$value="= (".$value.")";
      }
      else{
	$value="= '".$this->Normalizer($value)."'";				//if it is not a comparison operator or a logical operator it's a normal value
      }
    }
  }
  
  /**
   * This method will control if a string contains a sub-query.
   * If there's a sub query it will create a new Interpreter istance and make it analyze the URI.
   * If the sub-query can be analyzed without errors it will return the SQL code associated to the URI; otherwise the other Interpreter will return a error while parsing
   * If $array is setted true the method will control if $string is a list (if it's not a sub-query).
   */
  private function ParseSubQuery(&$string, $array=TRUE){
    if(strcasecmp("SUB-QUERY(",substr($string,0,10))==0 && $string[strlen($string)-1]==')'){				//if $string starts with "SUB-QUERY(" and ends with ")"... Maybe that's a sub-query!
      $string=substr($string,10,strlen($string)-11);
      $uri=parse_url($string);
      if($uri===FALSE || !isset($uri["path"])){
	$this->MDRI_responder->Return_code(400);
	exit(0);
      }
      if(isset($uri["query"])){												//if in the sub-query there is a query string...
	parse_str($uri["query"],$subconditions);
	$my_interpreter=new Interpreter($uri["path"],$subconditions, $this->MDRI_database,$this->MDRI_responder,"SELECT",$this->configurationdatabase,$this->defaultdabase,$this->errorlogger,$this->querylimit);
      }
      else{														//if not...
	$my_interpreter=new Interpreter($uri["path"],"", $this->MDRI_database,$this->MDRI_responder,"SELECT",$this->configurationdatabase,$this->defaultdabase,$this->errorlogger,$this->querylimit);
      }
      list($URI_type, $database,$table, $entry, $attrib,$where,$join)= $my_interpreter->URIanalyzer();
      if(strcmp($database,$this->database)!=0){										//a sub-query can be executed only if the database is the same of the query.
	$this->MDRI_responder->Return_code(400);
	exit(0);
      }
      list($conditions,$having,$group,$order,$offset)=$my_interpreter->Conditions_analyzer();
      $query=$this->MDRI_database->SelectQueryConstructor($URI_type,$attrib,$table,$join,$where,$conditions,$this->querylimit,$offset,$group,$order,$having);
      $string=substr($query,0,-1);
      return TRUE;
    }
    elseif($array){													//if it's not a sub-query maybe could be a list...
      $parsed=$this->Parser($string,",",TRUE);
      $string="";
      foreach($parsed AS $value){
	$string.="'".$value."' ,";
      }
      $string=substr($string, 0, -2);
      return TRUE;
    }
    else{
      return FALSE;}
  }
  
  /**
   * Method used to detect if value string starts with a comparison operator.
   * Allowed comparison operators are : "<", ">", ">=", "<=", "<>".
   */
  private function IscomparisonOperator(&$value){
    if($value[0]=='>'){
      if($value[1]=='='){
	$value=substr($value,2);
	return (">=");
      }
      $value=substr($value,1);
      return (">");
    }
    if($value[0]=='<'){
      if($value[1]=='>'){	
	$value=substr($value,2);
	return ("<>");
      }
      if($value[1]=='='){
	$value=substr($value,2);
	return ("<=");	
      }
      $value=substr($value,1);
      return ("<");
    }
    return FALSE;
  }
  
  /*
   * This method will detect if a string $value contains a logical operator.
   * For some logical opeartors is expected a sub-query or a list of values.
   * The two parameters $limit1 and $limit2 are used to exclude from the control some operators.
   * $limit1 exclude x operators from the beginning of the array $similfunctions.
   * $limit2 exclude x operators from the ending of the array $similfunctions.
   * $nullscontrol is used to set on/off the control of nulls values.
   */
  private function IsLogicalOperator(&$value,$nullscontrol=TRUE,$limit1=-1,$limit2=6){
    $similfunctions=array("BETWEEN(","NOT BETWEEN(","LIKE(","IN(", "NOT IN(","ANY(","ALL(",);
    $nulls=array("IS NULL", "ISNULL","IS NOT NULL","NOTNULL");
    foreach($similfunctions as $index =>$function) {
      if(strcasecmp(substr($value,0,strlen($function)),$function)==0 && $value[strlen($value)-1]==')'){
	if($index<$limit || $index>$limit2){
	  return FALSE;
	}
	$value=substr($value,strlen($function),strlen($value)-strlen($function)-1);
	switch ($index){
	  case 0:
	    $param=$this->Parser($value,",",TRUE);
	    if(count($param)!= 2){
	      $this->MDRI_responder->Return_code(400);
	      exit(0);
	    }
	    else{
	      $value="BETWEEN '".$param[0]."' AND '".$param[1]."'";
	    }
	    break;
	  case 1:
	    $param=$this->Parser($value,",",TRUE);
	    if(count($param)!= 2){
	      $this->MDRI_responder->Return_code(400);
	      exit(0);
	    }
	    else{
	      $value="NOT BETWEEN '".$param[0]."' AND '".$param[1]."'";
	    }
	    break;
	  case 2:
	    $value="LIKE '".$this->Normalizer($value)."'";
	    break;
	  case 3:
	    $this->ParseSubQuery($value);
	    $value="IN (".$value.")";
	    break;
	  case 4:
	    $this->ParseSubQuery($value);
	    $value="NOT IN (".$value.")";
	    break;
	  case 5:
	    $this->ParseSubQuery($value);
	    $value="= ANY (".$value.")";
	    break;
	  case 6:
	    $this->ParseSubQuery($value);
	    $value="= ALL (".$value.")";
	    break;
	}
	return TRUE;
      }
    }
    if($nullscontrol){
      foreach($nulls as $index => $null) {
	if(strcasecmp($value,$null)==0){
	  switch ($index){
	    case 0:
	    case 1:
	      $value="IS NULL";
	      break;
	    case 2:
	    case 3:
	      $value="IS NOT NULL";
	      break;
	  }
	  return TRUE;
	}
      }}
      return FALSE;
      
  }
  /**
   * Method used by POST and PUT requests to cut last part of uri (containing a hash or some of the new resource informations).
   */
  public function URIcutter($URI){									//Cuts the last part of the URI (used by PUT request)
    $string_tmp=explode("/",$URI,5);
    $last = end($string_tmp);
    return ($last);
  }
  
  public function SetURI($URI){
    $this->URI=$URI;
  }
  
  /**
   * Method used to explode the URI.
   */
  private function My_explosion(){
    $string_tmp=explode("/",$this->URI,5);
    if(!empty($string_tmp[1])){
      if($this->MDRI_database->Connect($this->defaultdabase)){
	$this->errorlogger->log_errors("ERROR: CAN'T CONNECT TO DEFAULT DATABASE");
	$this->database=$this->Normalizer($string_tmp[1]);
      }
      else{
	
	$this->MDRI_responder->Return_code(500);
	exit(0);
      }
    }
    else{$this->database=FALSE;}
    (!empty($string_tmp[2]))? $this->table=$string_tmp[2] : $this->table=FALSE;
    (!empty($string_tmp[3]))? $this->entry=$string_tmp[3] : $this->entry=FALSE;
    (!empty($string_tmp[4]))? $this->attrib=$string_tmp[4] : $this->attrib=FALSE;
    return;
  }
  
  /**
   * This method will control all tables using the namesake method of MDRI_database class.
   * If a table is a virtual table in its place within the array will be included the various tables of the join.
   * In $join array there will be 2 values:
   * - type : the type of the join
   * - conditions : the SQL condition of join
   */
  private function Join_controller(&$tables){
    if($this->DBtables===NULL){
      $this->DBtables=$this->MDRI_database->Tablelist();}
      if($this->MDRI_database->Connect($this->configurationdatabase)){				//If the interpreter can not connect to the configuration database it will log the error but continue execution (maybe the administrator does not want to use virtual tables).
	for($i=0;$i<count($tables);$i++){
	  if((array_search($tables[$i],$this->DBtables,TRUE)===FALSE)){
	    if(list($this->join[$i],$tables[$i])=$this->MDRI_database->Join_controller($tables[$i],$this->database,$this->DBtables)){
	      if($tables[$i]===FALSE){
		$this->MDRI_responder->Return_code(404);
		exit(0);
	      }
	    }
	    else{
	      $this->MDRI_responder->Return_code(404);
	      exit(0);
	    }
	  }
	  else{
	    $this->join[$i]=FALSE;
	    $tables[$i]=array($tables[$i]);
	  }
	}
	$this->MDRI_database->Connect($this->database);
	return;
      }
      $this->errorlogger->log_errors("ERROR: CAN'T CONNECT TO CONFIGURATION DATABASE");
      return;
  }
  
  /**
   * This method will controll if each table exists.
   */
  private function Tablescontroller($tables){
    foreach($tables as $tablecombination){
      if($tablecombination!==FALSE){
	foreach($tablecombination as $table){
	  if($this->MDRI_database->Doestableexists($table,$this->DBtables)){}
	  else{
	    return (FALSE);
	  }
	}
      }
    }
    return (TRUE);
    
  }
  
  
  /**
   * Method OldParser. Used by POST and PUT to analyze the request body.
   * It will explode string if a $explode character is encountered; if the $explode character is preceded by a odd number of $escape characters this one will be ignored.
   * If $DBescape is setted TRUE the sub-strings will be escaped.
   * $max parameter indicates the maximum number of sub-strings which can be obtained (-1 = no limit).
   */
  public function OldParser($entries, $explode, $escape ="\\",$DBescape=TRUE,$max=-1){
    $parsed=array();
    if(empty($entries)){
      array_push($parsed,$entries);
    }
    $c=0;
    $tmp="";
    $parse=FALSE;
    $enabled=TRUE;
    $c2=0;
    $lenght=strlen($entries);
    while($c<$lenght){
      if(strcmp($entries[$c],$escape)==0 && !$parse){
	$c2=$c;
	while($entries[$c2]==$escape){
	  $c2++;
	}
	if(strcmp($entries[$c2],$explode)==0){
	  $parse=TRUE;										//this group of escape characters is followed by a explode character. So must be analyzed
	}
      }
      if(strcmp($entries[$c],$escape)==0 && $parse){
	if($enabled){$enabled=FALSE;
	}
	else{
	  $enabled=TRUE;
	  $tmp.=$escape;}
      }
      elseif($c==$lenght-1 || (strcmp($entries[$c],$explode)==0 && $enabled)){
	$parse=FALSE;
	$enabled=TRUE;
	if($c==$lenght-1 && strcmp($entries[$c],$explode)!=0){
	  $tmp.=$entries[$c];
	}
	($DBescape)? array_push($parsed,$this->MDRI_database->Escape_string($tmp)) : array_push($parsed,$tmp);
	if($c==$lenght-1 && strcmp($entries[$c],$explode)==0){
	  $tmp="";
	  ($DBescape)? array_push($parsed,$this->MDRI_database->Escape_string($tmp)) : array_push($parsed,$tmp);
	}
	$tmp="";
	$max--;
	if($max==0){
	  ($DBescape)? array_push($parsed,$this->MDRI_database->Escape_string(substr($entries,$c))) : array_push($parsed,substr($entries,$c));
	  return $parsed;
	}
      }
      elseif(strcmp($entries[$c],$explode)==0 && !$enabled){
	$tmp.=$explode;
	$enabled=TRUE;
	$parse=FALSE;
      }
      elseif((!$parse)){
	$tmp.=$entries[$c];
      }
      $c++;
    }
    return $parsed;
  }
  
  /**
   * Method used to explode the sub parts of URI.
   * If $normalize is set TRUE the sub-parts will be normalized.
   */
  private function Parser($string, $explode, $normalize=FALSE){
    $parsed=array();
    $parsed=explode($explode,$string);
    if($normalize){
      foreach($parsed as $c=>$substr) {
	$parsed[$c]=$this->Normalizer($substr);
      }
    }
    
    return $parsed;
  }
  
  /**
   * Method used by a PUT request with URI level 3.
   * It will analyze the string containing the entries creating an array with the PKs and the relative values in the order specified by the client.
   */
  public function Entriesparser($string,$primaries){
    $return=array();
    $entries=$this->Parser($string,";");
    if(count($entries)!=count($primaries)){
      return FALSE;}
      foreach($entries as $c=>$entry){
	$userDEF=$this->Parser($entry,"=");
	if(count($userDEF)>2){
	  return FALSE;
	}
	if(count($userDEF)==2){
	  $search=array_search($this->Normalizer($userDEF[0]),$primaries);
	  if($search!==FALSE){
	    $return[$primaries[$search]]=$this->Normalizer($userDEF[1]);
	    array_unshift($primaries, array_pop($primaries[$search])); 
	  }
	  else{
	    return FALSE;
	  }
	}else{
	  $return[$primaries[$c]]=$this->Normalizer($userDEF[0]);
	}
      }
      return $return;
  }
  
  /**
   * This method will normalize a string using the PHP functions : urldecode and pg_escape_string
   */
  private function Normalizer($string){
    $string=urldecode($string);
    $string=$this->MDRI_database->Escape_string($string);
    return $string;
  }
  
  /**
   * This method will detect if a attribute is a aggregate function or not.
   */
  private function IsAggregateFunction($attrib){
    $functions=$this->MDRI_database->LoadAggregateFunctions();
    if($functions===NULL){
      return FALSE;}
      $attrib=trim($attrib);
      $c=0;
      $functions_number=count($functions);
      if($attrib[strlen($attrib)-1]==")"){
	while($c<$functions_number){
	  if(strcasecmp($functions[$c],substr($attrib,0,(strlen($functions[$c]))))==0){
	    return TRUE;}
	    $c++;
	}
      }
      return FALSE;
  }
  
  private function IsSelectAll(&$string){
    if(empty($string)){return TRUE;}
    if(strcmp($string,"*")==0){return TRUE;}
    return FALSE;
  }
  
  /**
   * The Core() method is also the core of the interface.
   * It will analyze: 
   *  - the tables (to detect virtual tables).
   *  - the entries (to create the WHERE clause).
   *  - the list of attributes.
   *
   */
  private function Core(){
    if(!$this->MDRI_database->Connect($this->defaultdabase)){
      $this->MDRI_responder->Return_code(400);
      exit(0);
    }
    if($this->MDRI_database->DoesDatabaseExists($this->database)){
      if($this->MDRI_database->Connect($this->database)){
	$table=$this->Parser($this->table,",", TRUE);
	$entries=$this->Parser($this->entry,",");
	$attrib=$this->Parser($this->attrib,",");
	$this->attrib=array();
	if(count($attrib)>count($table)){											//there can't be multiple groups of attributes than tables
	  $this->MDRI_responder->Return_code(400);
	  exit(0);}
	  if($this->request=="SELECT" || $this->request=="DELETE"){
	    $this->Join_controller($table);
	    if($this->Tablescontroller($table)){
	      $this->where='';
	      if($this->MDRI_database->Checktablepermissions($table,$this->request)){						//check if the user has permissions to the requested action on the resource
		if(count($entries)!=1 || !$this->ISSelectAll($entries[0])){
		  if((count($entries))==(count($table))){
		    foreach($table as $t=>$tablegroup){
		      if(count($tablegroup)>1){											//if it's a join
			$entries[$t]=$this->Parser($entries[$t],"+");
			if((count($entries[$t])!=1 || !$this->IsSelectAll($entries[$t][0]))){
			  if(count($tablegroup)==count($entries[$t])){								//there must be a entry foreach table....
			    foreach($entries[$t] as $k =>$entry){
			      $entries[$t][$k]=$this->Parser($entry,";");
			    }
			    foreach($tablegroup as $index => $tab){
			      if($primary=$this->MDRI_database->Get_primarykey($tab)){
				if((count($entries[$t][$index])==1 && $this->IsSelectAll($entries[$t][$index][0])) || (count($entries[$t][$index])==count($primary))){
				  foreach($entries[$t][$index] as $kk=>$subentry) {
				    $userDEF=$this->Parser($subentry,"=");							//checking to see if the user has specified a particular order for the PKs
				    if(count($userDEF)>2){
				      $this->MDRI_responder->Return_code(400);
				      exit(0);
				    }
				    if(count($userDEF)==2){
				      $search=array_search($this->Normalizer($userDEF[0]),$primary);
				      if($search!==FALSE){
					$PK=$primary[$search];
					$value=$this->Normalizer($userDEF[1]);
					array_unshift( $primary, array_pop($primary[$search])); 				//moving the PK used at the beginning of the array (so the others will right shift)
				      }
				      else{
					$this->MDRI_responder->Return_code(400);
					exit(0);
				      }
				    }else{
				      $PK=$primary[$kk];
				      $value=$this->Normalizer($userDEF[0]);}
				      
				      if(!$this->IsSelectAll($value)){
					$this->where.=sprintf("%s.%s=%s AND ",$tab,$PK,$this->Normalizer($value));
				      }
				  }
				}
				else{
				  $this->MDRI_responder->Return_code(400);
				  exit(0);
				}
				unset($primary);
			      }
			      else{
				$this->MDRI_responder->Return_code(500);
				exit(0);
			      }
			    }
			  }
			  else{
			    $this->MDRI_responder->Return_code(400);
			    exit(0);
			  }}
		      }
		      else{													//if it's not a join...
			$entries[$t]=$this->Parser($entries[$t],";");
			if($primary=$this->MDRI_database->Get_primarykey($tablegroup[0])){
			  if(count($entries[$t])==count($primary)){
			    foreach($entries[$t] as $kk=>$subentry) {
			      $userDEF=$this->Parser($subentry,"=");
			      if(count($userDEF)>2){										//checking to see if the user has specified a particular order for the PKs
				$this->MDRI_responder->Return_code(400);
				exit(0);
			      }
			      if(count($userDEF)==2){
				$search=array_search($this->Normalizer($userDEF[0]),$primary);
				if($search!==FALSE){
				  $PK=$primary[$search];
				  $value=$this->Normalizer($userDEF[1]);
				  array_unshift($primary, array_pop($primary[$search])); 					//moving the PK used at the beginning of the array (so the others will right shift)
				}
				else{
				  $this->MDRI_responder->Return_code(400);
				  exit(0);
				}
			      }else{
				$PK=$primary[$kk];
				$value=$this->Normalizer($userDEF[0]);}
				
				$entries[$t][$kk]=trim($subentry);
				if(!$this->IsSelectAll($value)){
				  $this->where.=sprintf("%s.%s=%s AND ",$tablegroup[0],$PK,$this->Normalizer($value));
				}
			    }
			    
			  }
			  else{
			    $this->MDRI_responder->Return_code(400);
			    exit(0);
			  }
			}
			else{
			  $this->MDRI_responder->Return_code(500);
			  exit(0);
			}
			
		      }
		    }
		  }
		  else{
		    $this->MDRI_responder->Return_code(400); 
		    exit(0);}
		}
		if(count($attrib)!=1 || !empty($attrib[0])){									//parsing of attributes
		  foreach($table as $t=>$tablegroup){
		    if(count($tablegroup)>1){											//if it's a join...
		      $attrib[$t]=$this->Parser($attrib[$t],"+");
		      if(count($attrib[$t])>count($tablegroup)){								//there can be more groups of attributes than tables
			$this->MDRI_responder->Return_code(400);
			exit(0);}
			foreach($tablegroup as $index => $tab){
			  $attrib[$t][$index]=$this->Parser($attrib[$t][$index],";");						//there may be infinite attributes for each table
			  if(!empty($attrib[$t][$index])){
			    foreach($attrib[$t][$index] as $j=>$subattrib){
			      if(!empty($subattrib)){
				$subattrib=$this->Normalizer($subattrib);
				if(!$this->IsAggregateFunction($subattrib)){
				  array_push($this->attrib,$tab.".".$subattrib);
				}
				else{
				  $subattrib=$this->Normalizer($subattrib);
				  array_push($this->attrib,$subattrib);
				}
			      }
			    }
			  }}
		    }
		    else{													//if it's not a join
		      $attrib[$t]=$this->Parser($attrib[$t],";");
		      if(!empty($attrib[$t])){
			foreach($attrib[$t] as $j=>$subattrib){
			  if(!empty($subattrib)){
			    if(!$this->IsAggregateFunction($subattrib)){
			      $subattrib=$this->Normalizer($subattrib);
			      array_push($this->attrib,$tablegroup[0].".".$subattrib);
			    }
			    else{
			      $subattrib=$this->Normalizer($subattrib);
			      array_push($this->attrib,$subattrib);
			    }
			  }
			}
		      }
		    }
		  }
		}
	      }
	      else{
		$this->MDRI_responder->Return_code(403);
		exit(0);}
		$this->where = substr($this->where, 0, -5);
	    }
	    else{
	      $this->MDRI_responder->Return_code(400);
	      exit(0);}
	  }
	  else{
	    $tmp=$table;													//normalization of the array that contains the tables
	    unset($table);
	    $table=array();
	    array_push($table,$tmp);
	    unset($tmp);
	    if(!$this->Tablescontroller($table)){		  
	      $this->MDRI_responder->Return_code(400);
	      exit(0);
	    }
	  }
	  
	  $this->table=$table;
	  $this->entry=$entries;
	  return;
	  
      }
      else{
	$this->MDRI_responder->Return_code(400);
	exit(0);
      }
    }
    else{
      $this->MDRI_responder->Return_code(404);
      exit(0);
    }
  }
  
  /**
   * This method will analyze the various parts of the URI and determine its level.
   */
  public function URIanalyzer(){
    $this->My_explosion();
    $case=-1;
    if(($this->database!="") && ($this->table!="") && ($this->entry!="")&& ($this->attrib!=""))				//level 4 URI
    {
      $this->Core();
      $case=4;
    }
    if(($this->database!="") && ($this->table!="") && ($this->entry!="") && ($this->attrib==""))			//level 3 URI
    {
      $case=3;
      $this->Core();
      $this->attrib="*";
    }
    if(($this->database!="") && ($this->table!="") && ($this->entry=="") && ($this->attrib==""))			//level 2 URI
    {
      $case=2;
      $this->Core();
      $this->attrib="*";
    }
    if(($this->database!="") && ($this->table=="") && ($this->entry=="") && ($this->attrib==""))			//level 1 URI
    {
      $this->Core();
      $case=1;
    }
    if(($this->database=="") && ($this->table=="") && ($this->entry=="") && ($this->attrib==""))			//level 0 URI
    {
      $this->database=$this->defaultdabase;
      $case=0;
    }
    return array ($case,$this->database,$this->table,$this->entry,$this->attrib,$this->where,$this->join);
  }
}
?>