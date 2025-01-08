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
        http_response_code(401); // Unauthorized
        echo json_encode(["error" => "Invalid or missing token."]);
        exit;
    }
}


if ($_SERVER["REQUEST_METHOD"] == "GET") {
    checkAuthorization($conn); 

    try {
        $stmt = $conn->prepare("SELECT * FROM player_teams");
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $result = $stmt->fetchAll();

        header("Content-Type: application/json");
        echo json_encode($result, JSON_PRETTY_PRINT);
    } catch (PDOException $e) {
        echo json_encode(["error" => "Error fetching data: " . $e->getMessage()]);
        http_response_code(500); // Server Error
    }
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    checkAuthorization($conn); 

    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data["player_id"]) || !isset($data["team_id"])) {
        echo json_encode(["error" => "Incomplete information has been submitted."]);
        http_response_code(400); // Bad Request
        exit();
    }

    try {
        $stmt = $conn->prepare("INSERT INTO player_teams (player_id, team_id) VALUES (:player_id, :team_id)");
        $stmt->bindParam(":player_id", $data["player_id"]);
        $stmt->bindParam(":team_id", $data["team_id"]);
        $stmt->execute();

        echo json_encode(["message" => "The player_team was successfully registered."]);
        http_response_code(201); // Created
    } catch (PDOException $e) {
        echo json_encode(["error" => "Error in recording information: " . $e->getMessage()]);
        http_response_code(500); // Server Error
    }
}

// متد PUT
if ($_SERVER["REQUEST_METHOD"] == "PUT") {
    checkAuthorization($conn); 

    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['id'])) {
        echo json_encode(["error" => "Id is not sent"]);
        http_response_code(400); // Bad Request
        exit();
    }

    if (!isset($data['player_id']) || !isset($data['team_id'])) {
        echo json_encode(["error" => "Incomplete information has been submitted."]);
        http_response_code(400); // Bad Request
        exit();
    }

    try {
        $stmt = $conn->prepare("
            UPDATE player_teams 
            SET player_id = :player_id, team_id = :team_id 
            WHERE id = :id
        ");
        $stmt->bindParam(":id", $data["id"]);
        $stmt->bindParam(":player_id", $data["player_id"]);
        $stmt->bindParam(":team_id", $data["team_id"]);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            echo json_encode(["message" => "Player team successfully updated."]);
        } else {
            echo json_encode(["error" => "No player_team found with this ID or no changes were made."]);
            http_response_code(404); // Not Found
        }
    } catch (PDOException $e) {
        echo json_encode(["error" => "Error in updating data: " . $e->getMessage()]);
        http_response_code(500); // Server Error
    }
}


// متد PATCH
if ($_SERVER["REQUEST_METHOD"] == "PATCH") {
    checkAuthorization($conn); 

    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['id'])) {
        echo json_encode(["error" => "Id is not sent"]);
        http_response_code(400); // Bad Request
        exit();
    }

    $fields = [];
    $params = [':id' => $data['id']];

    foreach ($data as $key => $value) {
        if ($key !== 'id') {
            $fields[] = "$key = :$key";
            $params[":$key"] = $value;
        }
    }

    if (empty($fields)) {
        echo json_encode(["error" => "No fields submitted for update"]);
        http_response_code(400); // Bad Request
        exit();
    }

    try {
        $query = "UPDATE player_teams SET " . implode(", ", $fields) . " WHERE id = :id";
        $stmt = $conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            echo json_encode(["message" => "Player team successfully updated"]);
        } else {
            echo json_encode(["error" => "No player_team found with this ID"]);
            http_response_code(404); // Not Found
        }
    } catch (PDOException $e) {
        echo json_encode(["error" => "Error in updating data: " . $e->getMessage()]);
        http_response_code(500); // Server Error
    }
}

// متد DELETE
if ($_SERVER["REQUEST_METHOD"] == "DELETE") {
    checkAuthorization($conn); 

    if (!isset($_GET['id'])) {
        echo json_encode(["error" => "Id is not sent"]);
        http_response_code(400); // Bad Request
        exit();
    }

    $id = intval($_GET['id']);
    if ($id <= 0) {
        echo json_encode(["error" => "Id is not valid!"]);
        http_response_code(400); // Bad Request
        exit();
    }

    try {
        $stmt = $conn->prepare("DELETE FROM player_teams WHERE id = :id");
        $stmt->bindParam(":id", $id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            echo json_encode(["message" => "The player_team was successfully deleted!"]);
        } else {
            echo json_encode(["error" => "No player_team found with this ID"]);
            http_response_code(404); // Not Found
        }
    } catch (PDOException $e) {
        echo json_encode(["error" => "Error in deleting the player_team: " . $e->getMessage()]);
        http_response_code(500); // Server Error
    }
}

?>
