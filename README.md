MDRI
====

A Multiple Database RESTful Interface

This is a RESTful interface for a PostgreSQL database.
The interface follow all the REST principles; statelessness, addressability and is Resource-oriented.
The interface defines 5 tipes of resources:
	0. Server
	1. Database
	2. Table
	3. Entry
	4. Attribute
The interface will return all information in JSON.

##READ AND DELETE (GET AND DELETE HTTP METHODS)

###Level 0 (Server)
A Server resource is a particular type of resource, it contains the list of databases (that user can see)  on the server.
It's possible to return a JSON array with a list of all database with :
'''
HTTP GET www.example.com
'''
Where example.com would be the server upon which your PostgreSQL is running.

###Level 1 (Database)
Will list all tables in specified database:
'''
HTTP GET www.example.com/example-db
'''
It's possible to delete a database:
'''
HTTP DELETE www.example.com/example-db
'''
####WARNING!! Deleting a database could be very annoying. Using a detele method on a database should be turned off (and this is the default setting) in the interface configuration file.

###Level 2 (Table)
To get a list of all entries in a table:
'''
HTTP GET www.example.com/example-db/example-table
'''
Delete a table with:
'''
HTTP DELETE www.example.com/example-db/example-table
'''
It's possible to read from multuple tables (like doing a CROSS JOIN between them):
'''
HTTP GET www.example.com/example-db/example-table1,example-table2,example-table3
'''
Separating them with a ","

##The MDRI database
Inside the database should be created a new DB named MDRI (or whatever you want, you can change name inside configuration file) with this tables:
        - MDRI_joins (used for virtual table)
        - MDRI_POE (used for POE function)

##VIRTUAL TABLES
One of the most powerful and useful database use are the joins.
Joins between two tables wouldn't be a resource because don't really exist inside a DB but is created "on the fly".
To use this as a resource i've thinked about it as a "virtual table".
A virtual table could be a particular join (INNER, LEFT etc) between two or more tables of the database.
But how the interface could be recognize a virtual table ?
Virtual tables should have unique names, and how to create them should be stored somewhere; this can be done inside a the database in use.
####WARNING!! You cannot delete tables using a virtual table or more than one table

###The MDRI_joins table
The MDRI_joins should be like this_
'''
| join_name(PK) | join_table1 | join_table2 | join_condition | join_database | join_type
'''
where_
	- join_name : the name of the virtual table
	- join_table1 : the first table used for the join
	- join_table2 : the second table used for the join
	- join_condition : the SQL code of the Join condition
	- join_database : the name of the database where the new virtual table should exist
	- join_type : the type of the join (INNER, LEFT, RIGHT, FULL, OUTER)
Inside the fields "join_table1" and "join_table2" you can use also other virtual tables, creating joins using a infinite number of tables.
For example (a really bad example):
"In a database with three tables like student, grade, subject. If I would get the student with the higgest grade in math; I would do a Join between subject and grade, using the join_condition "subject.PK=grade.FK", and name this new table Virtual1. Now i can create another virtual table between student and the table Virtual1 using the join condition "student.PK=grade.FK""

##Level 3 (Entry)
To get a specified entry in a table:
'''
HTTP GET www.example.com/example-db/example-table/PK
'''
Or fror delete :
'''
HTTP DELETE www.example.com/example-db/example-table/PK
'''
If table has composed PK:
'''
HTTP GET www.example.com/example-db/example-table/PK1;PK2;PK3
'''
For delete:
'''
HTTP DELETE www.example.com/example-db/example-table/PK1;PK2;PK3
'''
In this case the order of the Pk's should be the order defined in the DB.
If you don't care about a field you can use the reserved character '*' this means "each".
If you want it's possible to specify the fields of the PK's:
'''
HTTP GET www.example.com/example-db/example-table/PK-field1=PK1;PK-field2=PK2;PK-field3=PK3
'''
For delete:
'''
HTTP DELETE www.example.com/example-db/example-table/PK-field1=PK1;PK-field2=PK2;PK-field3=PK3
'''
If you are using a virtual table:
'''
HTTP GET www.example.com/example-db/virtual-table/PK-field1=PK1;PK-field2=PK2+PK-field3=PK3
'''
Also here,for delete:
'''
HTTP DELETE www.example.com/example-db/virtual-table/PK-field1=PK1;PK-field2=PK2+PK-field3=PK3
'''
You should specify the PK's from the two (or more tables) that compose the virtual table and separate each group of Pks with a "+".
In this case the table "virtual-table" is compose by two tables(like "table1" and "table2"), you are declaring PK1 for "PK-field1", and PK2 for "PK-field2" for "table1", and PK3 for "PK-field3" for "table2".
Or for a cross join:
'''
HTTP GET www.example.com/example-db/example-table1,example-table2,example-table3/PK1table1;PK2table1,PKtable2,PK1table3;PK2table3;PK3table3
'''
Delete with:
'''
HTTP DELETE www.example.com/example-db/example-table1,example-table2,example-table3/PK1table1;PK2table1,PKtable2,PK1table3;PK2table3;PK3table3
'''
Separate each group of Pks with a "," like for tables.
In this case table 1 has a PK composed by two field, table 2 by one, and table 3 by 3.

##Level 4 (Attribute)
###WARNING!!! YOU CANNOT USE DELETE METHOD INSIDE LEVEL 4 (IT DOESN'T MAKE SENSE...).
In the case you want to return only a specified value of a entry you can do that with:
'''
HTTP GET www.example.com/example-db/example-table/PK/attrib1;attrib2;attrib3
'''
###In this case you should specify the name of the field with "attrib1" not the value
This will return you the JSON representation of attrib1, attrib2 and, attrib3 from the entry with the primary key=PK.
If you are using multiple tables (CROSS JOIN):
'''
HTTP GET www.example.com/example-db/example-table1,example-table2,example-table3/PK1table1+PK2table1;PKtable2;PK1table3+PK2table
3+PK3table3/attrib1;attrib2,*,attrib1
'''
In this case you will get "attrib1" and "attrib2" from "example-table1", all attributes from "example-table2", and "attrib1" from "example-table3".
For a virtual table:
'''
HTTP GET www.example.com/example-db/virtual-table/PK-field1=PK1;PK-field2=PK2+PK-field3=PK3/attrib1+attrib1;attrib2
'''
Extracting "attrib1" from "table1", and "attrib1" and "attrib2" from "table2" (assuming that "virtual-table" is composed by the join from "table1" and "table").
If you want to estract some values from all entries of a table:
'''
HTTP GET www.example.com/example-db/example-table/*/attrib1;attrib2;attrib3;etc;etc
'''

#ADVANCED EXTRAPOLATION (USE AT YOU OWN RISK)
PostgreSQL has a lot of functions, I know that is "impossible" to make a RESTful interface that use all of them.
You can use the "query-string" from method "GET" to use some of them.
The format for query-string is the default:
		<field>=<value>&<field>=<value>
A <field> could be:
	- A particular attribute of the table
	- OFFSET : reserverd, can be used for specify the return offset (eg. offset 20 will return row from 21 to ...)
	- ORDER_BY : reserved, you can put in <value> a list of :
			field1[ASC|DESC],field2[ASC|DESC]
		In this case the fields (that can be for eg table columns) will be put in order (ascending or descending). 
	- GROUP_BY : Inside <value> you can put a list:
			field1,field2,ecc..
		Fields will be grouped.

####The "\" can be used as a escape character for all separators "," of the list

A <value>:
	- Can start with a special operator "<,>,<=,>=,<>" for comparison (= is default) eg: age=>=18 means age >= than 18 (I know that is ugly).
	- NULL or NOTNULL
	- Can also contain a logical operator like:
		- BETWEEN(<value1>,<value2>) : in this case the two values should be separated by ",".
		- LIKE(<pattern>) : <pattern> could be a regex
		- IN(<list>) : a <list> could be a list of values separed by "," or also a sub-query (Take a look at the sub-query)
		- NOT IN(<list>) 
		- ANY (<list>)
		- ALL(<list>)
##SUB-QUERY
Inside a <list> can be inserted a list of values; those values can be generated by a sub-query.
You can declare a sub-query with:
		SUB-QUERY(URI)
The URI should be like a URI in the standard defined by the MDRI interface.
You can so define a list of values (or only a value) with:
	<field>=IN(SUB-QUERY(<URI>))
Inside a sub-query it's possible to use others sub-query.

#INSERT AND MODIFY (METHODS PUT AND POST)
The difference between PUT method and POST method is that the PUT URI should contain the information about "where" put the values (the PK), POST method should only specify the values and let the server decide where put them.
Like: If you want to insert a new student in the student DB I should not care about the unique ID (matriculation number) assigned at the student, the server should care about it (I should use POST). Also, if you want create a new subject in the table containing all subject of the scool you should use PUT method and specify in the URI the name (PK) of the new subject.
###WARNING!! FOR INSERT AND MODIFY IS NOT PERMITTED THE USE OF VIRTUAL TABLES OR CROSS JOINS
For POST you can use only level 0,1,and 2, for PUT only level 1,2, and 3.
A POST request should contain a <hash> for the POE functionality of the interface (see POE)
The POST will create a new resource children of the resource speicified by the URI, this explain why permitted levels are different for POST and PUT.
Inside a body request the standard should be:
		<field1>=<value1>
		<field2>=<value2>
		<field3>=<value3>
			...
The "\" character is a excape character for the divion characters "=" and "new line".

##Level 0/1 (0 for POST and 1 for PUT) DATABASE
This mean "create new DB".
For insert inside a request body it's possible the use of:
	- OWNER
	- TEMPLATE
	- ENCODING
	- LC_COLLATE
	- LC_CTYPE
	- TABLESPACE
	- CONNECTION LIMIT
In POST should be used also :
	- DATABASENAME
For the name of the new DB.
For modify: 
	- RENAME
	- OWNER
	- CONNECTION LIMIT
	- RESET

You can also modify a value with the default:
<parameter>=DEFAULT
(You can use also RESET=<parameter>)
 
####All of these fields are case insensitive
For example, create new DB with PUT:
'''
HTTP PUT  www.example.com/<DB-name>
--- PUT BODY ---
OWNER=Smith
ENCODING=UTF8
CONNECTION LIMIT=1011
'''

Or with POST:
'''
HTTP POST www.example.com/<hash>
--- POST BODY ---
DATABASENAME=<DB-name>
OWNER=Smith
ENCODING=UTF8
'''

##Level 1/2 TABLE
New values for columns should be listed:
	<column_name>=<column_value>
For a table can be used some "extra actions" as:
	- CONSTRAINT
	- INHERIT
	- ENABLE RULE
	- ENABLE TRIGGER
	- etc..
There is no limit for extra actions, you can use all of PostgreSQL features.
###WARNING!! THE INTERFACE HAS NO CONTROL FOR THE EXTRA ACTIONS, THERE ARE SO MANY FEATURES IN POSTGRESQL... IT'S IMPOSSIBLE CHECK THEM ALL WITHOUT LIMIT THE INTERFACE'S FEATURES.
Extra actions should be separed with a "extraactions" string, and listed like this:
'''
<column_name>=<column_value>
<column_name>=<column_value>
extraactions
CONSTRAINT=<value>
ENABLE RULE=<value>
'''
A tipical PUT request should have the table name in the URI. A POST should specify the name of the new table with:
'''
tablename=<name_of_new_table>

You can use PUT and POST also for modify, in this case you could need to delete a column.
A column can be delete writing some reserved values inside a <column_value> field:
	- "": (empty string) in this case the column will be delete with the default DBMS method
	- CASCADE : Delete column using cascade
	- RESTRICT : Delete column using restrict

##Level 2/3 ENTRY
Rules for PUT method URI are the same for GET and DELETE; Creating a new row with composed PK :
'''
HTTP PUT www.example.com/table/PK1;PK2;PK3
HTTP PUT www.example.com/table/pk_field1=PK1;pk_field2=PK2;pk_field3=PK3
'''
In a POST request instead all PK fields should be inserted in the request body:
'''
--- POST BODY ---
pk_field1=PK1
pk_field2=PK2
pk_field3=PK3
'''
In the request body you should also always list all NOT NULL fields.
For modify a row the rules are the same (but in this case NOT NULL fields can be ignored).

#HEAD
The URI rules for a HEAD method are the same for GET. Using HEAD the interface will return you the information about the lenght of a GET request without the informations.
A example of a  tipical HEAD request:
'''
HTTP HEAD www.example.com/table/PK
'''
And his response:
'''
HTTP/1.1 200 OK
Date:1 March 2013 9:34:45 GMT
Server: ...
Last-Modified: ...
Content-Lenght: 48531
'''
If you'll make a HTTP GET request with the same URI the interface will return you 48531 bytes of body.

A HEAD method can be also used for the POI feature of the interface.
In this case inside the HEAD request body you have use a header named POE with value "1".
'''
--- LIST OF REQUEST HEADERS ---
POE=1
--- END OF HEADERS ---
'''

#THE POE FEATURE
All method HEAD, GET, DELETE, PUT are idempotent but POST method isn't.
For make the POST method idempotent I've implemented the POE feature that works like this:
	1- You make a HEAD request with the POE header =1 at with the URI of the parent resource that you want create (for a entry the parent table, for a table the parent DB, and for the DB the root URI of the server.
	2- The server will generate a unique hash and insert the hash inside the MDRI_POE table (inside MDRI database)
	3- Inside the HEAD response you can find the POE-link header with a hash
	4- You can make a POST request with the parent resource URI (for example www.example.com/table) appending the generated hash (in this case www.example.com/table/poe-hash-ed672b936e3bb00cf32c6d93444085e2601345e4
	5- The new resource will be created and the hash can't be used anymore.

####You have to put "poe-hash-" before all hashes inside the POST URI

The MDRI_POE table should be created inside the MDRI database (the same for the MDRI_joins table, see "virtual tables") and be like this:
		| POE_hash(PK) | POE_user | POE_path | POE_time | used |

	- POE_hash : the unique hash generated
	- POE_user : the name of the user that generated the hash (just for control)
	- POE_path : the URI of the parent resource (this POE can be only used with the same parent)
	- POE_time : the time when re request has ben received
	- used	   : a boolean; 1 the hash has been used, 0 it hasn't

#RESPONSE CODES
The response codes of the inteface are:
##200 (OK)
	- GET : URI has been analyzed without errors, the resource exists and you can find his JSON representation in body
	- HEAD : URI has been analyzed without errors, you can find the header "content-lenght" in the response
	- DELETE : URI has been analyzed without errors and the resource destroyed
	- POST/PUT : the resource has been modified
##201 (CREATED)
	- POST/PUT : the resource has been created
##204 (NO CONTENT)
	- POST/PUT : the request body is empty
##400 (BAD REQUEST)
	- ALL METHODS : wrong URI syntax
##404 (NOT FOUND)
	- ALL METHODS : URI syntax is ok but the resource hasn't been found
##403 (FORBIDDEN)
	- ALL METHODS : the user hasn't that privilege (DELETE, SELECT, UPDATE, or  INSERT) on the resource
##405 (METHOD NOT ALLOWED)
	- If POE has been used two times or the request contains a wrong hash
##500 (INTERNAL SERVER ERROR)
	- Interface can't read configuration file
	- There was a problem executing the SQL

#USER AND PASSWORD
User and password should be read from the config file.
A user can insert in a request two headers:
	- user : contains the name of the user
	- password : contains the password of the user (if not set will use a empty string as password)


