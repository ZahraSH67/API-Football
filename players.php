<?php
require_once("db.php");


//Token check function
function validateToken($token, $conn) {
    $stmt = $conn->prepare("SELECT * FROM tokens WHERE token = :token");
    $stmt->bindParam(':token', $token);
    $stmt->execute();

    return $stmt->rowCount() > 0;
}

// Check token in all methods
function checkAuthorization($conn) {
    $headers = getallheaders();
    $token = isset($headers['Authorization']) ? $headers['Authorization'] : '';

    if (!validateToken($token, $conn)) {
        http_response_code(401); // Unauthorized
        echo json_encode(["error" => "Invalid or missing token."]);
        exit;
    }
}


if ($_SERVER["REQUEST_METHOD"] == "GET") {
    checkAuthorization($conn);

    if (isset($_GET['id'])) { 
        $id = intval($_GET['id']);

        if ($id <= 0) {
            echo json_encode(["error" => "Invalid ID provided."]);
            http_response_code(400); 
            exit();
        }

        try {
            $stmt = $conn->prepare("SELECT * FROM players WHERE id = :id");
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->execute();
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            $result = $stmt->fetch();

            if ($result) {
                echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                http_response_code(200); 
            } else {
                echo json_encode(["error" => "Player not found."]);
                http_response_code(404); 
            }
        } catch (PDOException $e) {
            echo json_encode(["error" => "Error fetching data: " . $e->getMessage()]);
            http_response_code(500); 
        }
    } else { 
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10; //  10
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0; // 0

        $table = "players"; 

        try {
          
            $stmt = $conn->prepare("SELECT COUNT(*) FROM $table");
            $stmt->execute();
            $totalCount = $stmt->fetchColumn(); 
        } catch (PDOException $e) {
            echo json_encode(["error" => "Error fetching total count: " . $e->getMessage()]);
            http_response_code(500); // Server Error
            exit;
        }

        try {

            $stmt = $conn->prepare("SELECT * FROM $table LIMIT :limit OFFSET :offset");
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
            $previousUrl = "http://localhost/Football/players.php?limit=$limit&offset=$previousOffset";
        }

        if ($offset + $limit < $totalCount) {
            $nextOffset = $offset + $limit;
            $nextUrl = "http://localhost/Football/players.php?limit=$limit&offset=$nextOffset";
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
}





//POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    checkAuthorization($conn); 

 
    $data = json_decode(file_get_contents("php://input"), true);


    if (!isset($data["name"], $data["nationality"], $data["birth_year"], $data["matches_played"], $data["goals_scored"], $data["ranking"], $data["position"])) {
        http_response_code(400);
        echo json_encode(["error" => "All fields are required."]);
        exit;
    }

    try {
     
        $stmt = $conn->prepare("INSERT INTO players (name, nationality, birth_year, matches_played, goals_scored, ranking, position) VALUES (:name, :nationality, :birth_year, :matches_played, :goals_scored, :ranking, :position)");

  
        $stmt->bindParam(":name", $data["name"]);
        $stmt->bindParam(":nationality", $data["nationality"]);
        $stmt->bindParam(":birth_year", $data["birth_year"]);
        $stmt->bindParam(":matches_played", $data["matches_played"]);
        $stmt->bindParam(":goals_scored", $data["goals_scored"]);
        $stmt->bindParam(":ranking", $data["ranking"]);
        $stmt->bindParam(":position", $data["position"]);
        $stmt->execute();

    
        http_response_code(201); // Created
        echo json_encode(["message" => "The player was successfully registered."]);
    } catch (PDOException $e) {
     
        http_response_code(500); // Internal Server Error
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
}



// متد PUT
if ($_SERVER["REQUEST_METHOD"] == "PUT") {
    checkAuthorization($conn); 

    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($data['id'], $data['name'], $data['nationality'], $data['birth_year'], $data['position'], $data['matches_played'], $data['goals_scored'], $data['ranking'])) {
        $stmt = $conn->prepare("
            UPDATE players
            SET 
                name = :name, 
                nationality = :nationality, 
                birth_year = :birth_year, 
                position = :position,
                matches_played = :matches_played,
                goals_scored = :goals_scored,
                ranking = :ranking
            WHERE id = :id
        ");
        $stmt->bindParam(':id', $data['id']);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':nationality', $data['nationality']);
        $stmt->bindParam(':birth_year', $data['birth_year']);
        $stmt->bindParam(':position', $data['position']);
        $stmt->bindParam(':matches_played', $data['matches_played']);
        $stmt->bindParam(':goals_scored', $data['goals_scored']);
        $stmt->bindParam(':ranking', $data['ranking']);
        $stmt->execute();
    
        echo json_encode(["success" => true, "message" => "Player updated."]);
    } else {
        http_response_code(400); // Bad Request
        echo json_encode(["error" => "Missing required fields."]);
    }
    
}

// PATCH
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
        if (isset($data['nationality'])) {
            $fields[] = "nationality = :nationality";
            $params[':nationality'] = $data['nationality'];
        }

        if (isset($data['birth_year'])) {
            $fields[] = "birth_year = :birth_year";
            $params[':birth_year'] = $data['birth_year'];
        }

        if(isset($data['matches_played'])){
            $fields[] = "matches_played = :matches_played";
            $params[':matches_played'] = $data['matches_played'];
        }
        if(isset($data['goals_scored'])){
            $fields[] = "goals_scored = :goals_scored";
            $params[':goals_scored'] = $data['goals_scored'];
        }

        if(isset($data['ranking'])){
            $fields[] = "ranking = :ranking";
            $params[':ranking'] = $data['ranking'];
        }
        

        if (isset($data['position'])) {
            $fields[] = "position = :position";
            $params[':position'] = $data['position'];
        }

        if (!empty($fields)) {
            $sql = "UPDATE players SET " . implode(', ', $fields) . " WHERE id = :id";
            $stmt = $conn->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            echo json_encode(["success" => true, "message" => "Player updated."]);
        } else {
            http_response_code(400); // Bad Request
            echo json_encode(["error" => "No fields to update."]);
        }
    } else {
        http_response_code(400); // Bad Request
        echo json_encode(["error" => "Player ID is required."]);
    }
}




// DELETE
if ($_SERVER["REQUEST_METHOD"] == "DELETE") {
    checkAuthorization($conn); 
 

    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($data['id'])) {
        $stmt = $conn->prepare("DELETE FROM players WHERE id = :id");
        $stmt->bindParam(':id', $data['id']);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            echo json_encode(["success" => true, "message" => "Player deleted."]);
        } else {
            http_response_code(404); // Not Found
            echo json_encode(["error" => "Player not found."]);
        }
    } else {
        http_response_code(400); // Bad Request
        echo json_encode(["error" => "Player ID is required."]);
    }
}

?>
