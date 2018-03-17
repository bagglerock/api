<?php 
//  Connect to database
mb_http_output( "UTF-8" );
$connection = mysqli_connect('localhost', 'root', 'user', 'password');

//  Get the HTTP request method
$requestMethod = $_SERVER['REQUEST_METHOD'];
switch ($requestMethod) {
	case 'GET':
		//  Search for matches
		$query = $_GET['input'];
		getmatches($query);
		break;
	
	case 'POST':
		//  Add item to database
		addItem();
		break;

	case 'PUT':
		//  Update item in database
		$input = $_GET['input'];
		updateItem($input);
		break;

	case 'DELETE':
		//  Delete item from database
		$input = $_GET['input'];
		deleteItem($input);
		break;

	default:
		//  Invalid request method
		header("HTTP/1.0 405 Method Not Allowed");
		break;
}

function getMatches($query){
	global $connection;
	//  Check to see if there is the "-2F" character and change it back to "/"
	if ( strpos($query, '-2f') || strpos($query, '-2F') ){
		$query = urldecode(str_replace('-', '%', $query));
	}
	//  Check to see if the query is an item number and make search among the secpic field
	if( is_numeric($query) && strlen($query ) == 8){
		$sqlQuery = "SELECT * FROM static  WHERE secPic LIKE '%{$query}%'";
	//  Check to see if the query is a sku number, remove any zeroes in front and make search among the sku field	
	} else if ( is_numeric($query) && strlen($query) > 8 ){
		$query = $query * 1;
		$sqlQuery = "SELECT * FROM static WHERE sku LIKE '%{$query}%'";
	//  Add "+" to every word in the query and seach
	} else {
		$query = preg_replace('/(\w+)/', '+$1', $query);
		$sqlQuery = "SELECT * FROM static WHERE MATCH (sku,brand,collection,type,color,secPic) AGAINST ('$query' IN BOOLEAN MODE)LIMIT 100";
	}
	//  Make a connection to the database
	if($connection->connect_errno == 0){
		//  Initialize an array to put the response into
		$response = array();
		//  Make the query and store the result in an array
		$result = mysqli_query($connection, $sqlQuery);
		//  While there are still rows, keep fetching the result and stick each array in the response array
		while($row=mysqli_fetch_assoc($result)){
			$response[]=$row;
		}
		//  Close the connection
		mysqli_close($connection);
	} else {
		die('Unable to connect to database. [' . $connection->connect_error . ']');
	}
	//  In case there are any non utf-8 results, encode in utf-8 because JSON needs that
	array_walk_recursive($response, function(&$item, $key){
        if(!mb_detect_encoding($item, 'utf-8', true)){
            $item = utf8_encode($item);
        }
    });
	//  Encode in JSON and return response
	header('Content-Type: application/json');
	echo json_encode($response);
	
}

function addItem(){
	global $connection;
	// Assign the $_POST as an array
	$post = $_POST;
	// Make an array for the variables
    $variables = [];
    // First item in this $_POST is the action, so just clip it off
    $firstElement = array_shift($post);
    // Setup the Field Names for the query
    $fieldNames = implode(", ", array_keys($post));
    // Addslashes to the values and push them into an array
    foreach ($post as $name => $value){
        ${"$name"} = addslashes($value);
        array_push($variables, ${"$name"});
    }
    // Setup the Variables for the query
    $variables = "'".implode("','", array_values($variables))."'";
    // Add to Database & return the id and a response
    if($connection->connect_errno == 0){
    	$sqlQuery = "INSERT INTO static ($fieldNames) VALUES ($variables)";
    	if ( mysqli_query($connection, $sqlQuery) ){
    		$id = mysqli_insert_id($connection); //gets the id number of the last inserted entry
    		$response = array(
    			'status' => 1,
    			'statusMessage' => 'ITEM added SUCCESSFULLY',
    			'id' => $id
    		);
    	} else {
    		$response = array(
    			'status' => 0,
    			'statusMessage' => 'ITEM addition FAILED'
			);
    	}
    	mysqli_close($connection);
    	header('Content-Type: application/json');
    	return json_encode($response);
    } else {
    	die('Unable to connect to database. [' . $connection->connect_error . ']');
    }
}

function updateItem($itemID){
	global $connection;
	// Assign the $_POST as an array
    $post = $_POST;
    // Make an array for the variables
    $itemDetails = [];
    // Addslashes to the values and push them into an array with some kind of syntax
    foreach ($post as $name => $value){
        ${"$name"} = addslashes($value);
        array_push($itemDetails, "$name = '${"$name"}'");
    }
    // Add some commas to the argument
    $itemDetails = implode(", ",$details);
    // Update the database and close link and send a response
    if($connection->connect_errno == 0){
        $sqlQuery = "UPDATE static SET $itemDetails WHERE id = '$itemID'";
		if ( mysqli_query($connection, $sqlQuery) ){
			$response = array(
				'status' => 1,
				'statusMessage' => 'ITEM updated SUCCESSFULLY'
			);
		} else {
			$response = array(
				'status' => 0,
				'statusMessage' => 'ITEM updated FAILED'
			);
		}
		mysqli_close($connection);
		header('Content-Type: application/json');
    	return json_encode($response);
	} else {
		die('Unable to connect to database. [' . $connection->connect_error . ']');
	}
}

function deleteItem($itemID){
	global $connection;
	//  Delete item from database at $itemID and return a response
	if($connection->connect_errno == 0){
		$sqlQuery = "DELETE FROM static WHERE id='$itemID'";
		if ( mysqli_query($connection, $sqlQuery) ){
			$response = array(
				'status' => 1,
				'statusMessage' => 'ITEM Deleted SUCCESSFULLY'
			);
		} else {
			$response = array(
				'status' => 0,
				'statusMessage' => 'ITEM Deletion FAILED'
			);
		}
		mysqli_close($connection);
		header('Content-Type: application/json');
    	return json_encode($response);
	} else {
		die('Unable to connect to database. [' . $connection->connect_error . ']');
	}
}
?>
