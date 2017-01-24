<?php
/*
 * Program : Makes running mysql commands faster and easier
 * Revision:v1
 * Author: Corey Shaw
 * Date : 2016-12-5
 */

//Set the envirnoment mode
//This is what allows us to have different variable based on what our server is
if (( $_SERVER["SERVER_ADDR"] == '127.0.0.1' ) || ($_SERVER["SERVER_ADDR"] == '::1'))
{
    $envir = 'development';
} else {
    $envir = 'production';
}

//Set envir variable
DEFINE('ENVIRONMENT', $envir);

//Set DB connections
DEFINE('CLASS_DB_HOST', (ENVIRONMENT == 'production' ? 'solrenviewSql1' : 'V2'));
DEFINE('CLASS_DB_DATABASE', 'solren');
DEFINE('CLASS_DB_USERNAME', 'SRVTool');
DEFINE('CLASS_DB_PASSWORD', 'solectriarenewables');

class db
{
    //Set variables
    var $table      = false;
    var $update     = false;
    var $select     = false;
    var $where      = array();
    var $delete     = false;
    var $limit      = false;
    var $order_by   = false;
    var $insert     = array();

    private $connection = false;
    var $built_query = false;

    /**
    * db__construct()
    * @desc Establishes a connection with the database
    * @return string
    */
    function __construct()
    {
        //Check to see if we already have an active connection
        if(!$this->connection)
        {
            //Set active connection
            $this->connection = mysqli_connect(CLASS_DB_HOST, CLASS_DB_USERNAME, 
                CLASS_DB_PASSWORD, CLASS_DB_DATABASE);

            //Check if connection is successfully
            if(!$this->connection)
            {
                $this->print_error(mysqli_error());
            }
        }
    }

    /**
    * select()
    * @desc Allows user to pass in an array of items to select off of
    * @param $key
    * @param $value
    */
    public function select($value)
    {
        //Add where clauses to array
        $this->select = $value;
    }

    /**
    * where()
    * @desc Allows user to pass in an associative array of where statements
    * @param $key
    * @param $value
    */
    public function where($key, $value)
    {
        //Add where clauses to array
        $this->where[][$key] = $value;
    }

    /**
    * from()
    * @desc Allows user to pass in an associative array of where statements
    * @param $key
    * @param $value
    */
    public function from($value)
    {
        //Add where clauses to array
        $this->from = $value;
    }

    /**
    * Limit()
    * @desc Allows user to pass in a limit aguement
    * @param $value
    */
    public function limit($value)
    {
        //Add where clauses to array
        $this->limit = $value;
    }

    /**
    * order_by()
    * @desc Allows user to pass in a order by aguement
    * @param $value
    */
    public function order_by($item,$direction)
    {
        //Add order by clause
        $this->order_by = $item.' '.$direction;
    }

    /**
    * insert()
    * @desc Allows the user to easily perform an insert statement
    * @param $table, what table were inserting into
    * @param $associate array of items to insert
    * @return insert_id
    */
    public function insert($table = false,$array = false)
    {
        //Add where clauses to array
        $this->insert = $array;

        $this->print_array($this->insert);

        //Build keys
        //Create temp array for keys
        $keys_temp = array();
        foreach ($this->insert as $key => $value) {
            $keys_temp[] = $key;
        }

        //Implode the keys array into a string
        $keys = implode(", ",$keys_temp);

        //Build values
        //Create temp array for values
        $value_temp = array();
        foreach ($this->insert as $key => $value) {
            $value_temp[] = "'".$value."'";
        }

        //Implode the keys array into a string
        $value = implode(", ",$value_temp);

        //Strip slashes
        $value = mysqli_real_escape_string($this->connection, $value);

        //Build insert statement
        $insert_statement = "INSERT INTO ".$table." (".$keys.")
        VALUES (".$value.");";

        //Insert the statement into query
        $results = $this->query(null,$insert_statement);

        return $results;
    }

    /**
    * query()
    * @desc Queries the database based on set params in the object
    * @return $array
    */
    public function query($query = false, $insert_statement = false)
    {
        //If a custom query is passed in then dont run this.
        if(!$query && !$insert_statement)
        {
            //Call the function to start building the query
            $this->build_query();
        } else {
            if($query)
            {
                //Else set the custom query to the built_query variable
                $this->built_query = $query;
            } else if($insert_statement)
            {
                //Set insert statement
                $this->built_query = $insert_statement;
            }
        }

        //Query the DB
        //$this->print_array($this->built_query);
        $get_query = mysqli_query($this->connection, $this->built_query) 
        
        //Check for errors
        or 
        trigger_error(mysqli_error($this->connection)." in ".$this->built_query);

        if(!$insert_statement)
        {
            //Set object
            $results = new StdClass();

            //Check if $get_query returned any results
            if (mysqli_num_rows($get_query) > 0)
            {
                //Loop throught the array result if it is a  select query
                while ($rowResult = mysqli_fetch_assoc($get_query))
                {
                    //Put information in object
                    foreach ($rowResult as $key => $value) {
                        $results->$key = $value;
                    }
                }

                //Flush out the mysql cache
                mysqli_free_result($get_query);

                //Close connection once we have finished the query
                mysqli_close($this->connection);

                //If successful return object
                return $results;
            } else {
                //No rows were found, return message
                return 'No records found for:<br /><br />'.$this->built_query;
            }
        }

        //If were performing an insert then return the insert ID
        if($insert_statement)
        {
            //If insert id is 0 then the primary key is not set to auto increment so just 
            //return true to let us know it was successful
            if(mysqli_insert_id($this->connection) == 0)
            {
                return true;
            } else {
                return mysqli_insert_id($this->connection);
            }
        }
    }

    /**
    * build_query()
    * @desc Queries the database based on set params in the object
    * @return $array
    */
    public function build_query()
    {
        //Build the query
        
        //Put in the select statements
        $this->built_query = "SELECT ".$this->select." ";

        //Put in the from statements
        $this->built_query .= "FROM ".$this->from." ";

        if($this->where)
        {
            $this->built_query .= 'WHERE '.$this->build_where_and().' ';
        }

        //Pull in order by
        if($this->order_by)
        {
            $this->built_query .= 'ORDER BY '.$this->order_by.' ';
        }

        //Pull in limit
        if($this->limit)
        {
            $this->built_query .= 'LIMIT '.$this->limit.' ';
        }

        //Put on ending semi
        $this->built_query .= ';';
    }

    /**
    * build_where_and()
    * @desc Queries the database based on set params in the object
    * @return $array
    */
    public function build_where_and()
    {
        //Loop through where to start building that
        $where_clause = array();
        foreach ($this->where as $key => $value) {
            foreach ($value as $key => $value) {
                $where_clause[] = $key." = ".$value;
            }
        }

        return implode(" AND ",$where_clause);
    }


    /**
    * print_error()
    * @desc Prints error messages that are passed in.
    * @param $error
    * @return string
    */
    public function test_where()
    {
        $this->print_array($this->select);
    }

    public function print_array($array)
    {
        echo "<pre>";
            print_r($array);
        echo "</pre>";
    }

    /**
    * print_error()
    * @desc Prints error messages that are passed in.
    * @param $error
    * @return string
    */
    private function print_error($error = false)
    {
        //Kill the process and display an error.
        //Might make this fancier later on
        die($error);
    }
}
?>