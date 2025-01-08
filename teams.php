<?php
require_once("db.php");





function validateToken($token, $conn) {
    $stmt = $conn->prepare("SELECT * FROM tokens WHERE token = :token");
    $stmt->bindParam(':token', $token);
    $stmt->execute();

    return $stmt->rowCount() > 0;
}


function checkAuthorization($conn) {
    $headers = getallheaders();
    $token = isset($headers['Authorization']) ? $headers['Authorization'] : '';

    if (!validateToken($token, $conn)) {
        http_response_code(401); 
        echo json_encode(["error" => "Invalid or missing token."]);
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "GET") {

    $limit = isset($_GET["limit"]) ? intval($_GET["limit"]) : 10; // Default to 10 if not specified
    $offset = isset($_GET["offset"]) ? intval($_GET["offset"]) : 0; // Default to 0 if not specified
 


    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM teams");
        $stmt->execute();
        $totalCount = $stmt->fetchColumn(); 
    } catch (PDOException $e) {
        echo json_encode(["error" => "Error fetching total count: " . $e->getMessage()]);
        http_response_code(500); 
        exit;
    }

    try {
        $stmt = $conn->prepare("SELECT * FROM teams LIMIT :limit OFFSET :offset");
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $result = $stmt->fetchAll();
    } catch (PDOException $e) {
        echo json_encode(["error" => "Error fetching data: " . $e->getMessage()]);
        http_response_code(500); 
        exit;
    }

 
   $previousUrl = null;
   $nextUrl = null;

   if ($offset > 0) {
       $previousOffset = $offset - $limit;
       if ($previousOffset < 0) $previousOffset = 0;
       $previousUrl = "http://localhost/Football/teams.php?limit=$limit&offset=$previousOffset";
   }

   if ($offset + $limit < $totalCount) {
       $nextOffset = $offset + $limit;
       $nextUrl = "http://localhost/Football/teams.php?limit=$limit&offset=$nextOffset";
   }


   $response = [
       "count" => $totalCount,
       "previous" => $previousUrl,
       "next" => $nextUrl,
       "result" => $result
   ];

   header("Content-Type: application/json");
   echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

   



if ($_SERVER["REQUEST_METHOD"] == "POST") {
    checkAuthorization($conn); 
 
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    if (!isset($data["name"]) || !isset($data["location"]) || !isset($data["ranking"]) || !isset($data["year_founded"])) {
        echo json_encode(["error" => "Incomplete information has been submitted"]);
        http_response_code(400); 
        exit();
    }
    $name = $data["name"];
    $location = $data["location"];
    $ranking = $data["ranking"];
    $year_founded = $data["year_founded"];
    try {
    
        $stmt = $conn->prepare("INSERT INTO teams (name,  location, ranking, year_founded) VALUES (:name, :location, :ranking, :year_founded)");
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":location", $location);
        $stmt->bindParam(":ranking", $ranking);
        $stmt->bindParam(":year_founded", $year_founded);
        $stmt->execute();

        echo json_encode(["message" => "The team was successfully registered"]);
        http_response_code(201); 
    } catch (PDOException $e) {
        echo json_encode(["error" => "Error in recording information" . $e->getMessage()]);
        http_response_code(500); 
    }
}






if ($_SERVER["REQUEST_METHOD"] == "PATCH") {
    checkAuthorization($conn);

    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($data['id'])) {
        $fields = [];
        $params = [':id' => $data['id']];

        if (isset($data['name'])) {
            $fields[] = "name = :name";
            $params[':name'] = $data['name'];
        }
        if (isset($data['location'])) {
            $fields[] = "location = :location";
            $params[':location'] = $data['location'];
        }

        if (isset($data['ranking'])) {
            $fields[] = "ranking = :ranking";
            $params[':ranking'] = $data['ranking'];
        }

        if(isset($data['year_founded'])){
            $fields[] = "year_founded = :year_founded";
            $params[':year_founded'] = $data['year_founded'];
        }
        
        if (!empty($fields)) {
            $sql = "UPDATE teams SET " . implode(', ', $fields) . " WHERE id = :id";
            $stmt = $conn->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            echo json_encode(["success" => true, "message" => "Team updated."]);
        } else {
            http_response_code(400);
            echo json_encode(["error" => "No fields to update."]);
        }
    } else {
        http_response_code(400); 
        echo json_encode(["error" => "Team's ID is required."]);
    }
}






if ($_SERVER["REQUEST_METHOD"] == "PUT") {
    checkAuthorization($conn); 
    $data = json_decode(file_get_contents("php://input"), true);

 
    if (isset($data["id"], $data["name"], $data["location"], $data["ranking"], $data["year_founded"])) {
        $stmt = $conn->prepare("
            UPDATE teams
            SET name = :name, 
                location = :location, 
                ranking = :ranking, 
                year_founded = :year_founded
            WHERE id = :id
        ");
        $stmt->bindParam(':id', $data['id']);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':location', $data['location']);
        $stmt->bindParam(':ranking', $data['ranking']);
        $stmt->bindParam(':year_founded', $data['year_founded']);
        $stmt->execute();

        echo json_encode(["success" => true, "message" => "Team updated."]);
    } else {
        http_response_code(400); 
        echo json_encode(["error" => "Missing required fields."]);
    }
}





if ($_SERVER["REQUEST_METHOD"] == "DELETE") {
    checkAuthorization($conn); 

    if (!isset($_GET['id'])) {
        echo json_encode(["error" => "Id is not sent"]);
        http_response_code(400); 
    }

    $id = intval($_GET['id']); 
    if ($id <= 0) {
        echo json_encode(["error" => "Id is not valid!"]);
        http_response_code(400); 
        exit();
    }

    try {
        $stmt = $conn->prepare("DELETE FROM teams WHERE id = :id");
        $stmt->bindParam(":id", $id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            echo json_encode(["message" => "The team was successfully deleted!"]);
            http_response_code(200);
        } else {
            echo json_encode(["error" => "No team found with this ID"]);
            http_response_code(404); 
        }
    } catch (PDOException $e) {
        echo json_encode(["error" => "Error in deleting the team" . $e->getMessage()]);
        http_response_code(500);
    }
}
?>