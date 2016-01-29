<?php
require 'vendor/autoload.php';
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

include_once '../sys.includes.php';

$app = new \Slim\App;

// user viewable page
$app->get('/', 'showEndpoints');

// api
$app->get('/users', 'Users::get_list');
$app->get('/user/list', 'Users::get_list');
$app->get('/user/{id}', 'Users::get');
$app->put('/user', 'Users::create');
$app->delete('/user/{id}', 'Users::del');

$app->run();

function showEndpoints(Request $request, Response $response)
{
    $response->write('<ul>
          <li><a href="/api">/</a> -> (home)</li>
          <li>GET /users -> Get user list</li>
          <li>PUT /user -> Create user</li>
          <li>GET /user/list -> Get user list</li>
          <li>GET /user/(id) -> Get user by id</li>
          <li>DELETE /user/(id) -> Delete user by id</li>
          <!-- TODO -->
        </ul>');
}

class Users {
    function get_list(Request $request, Response $response) {

        global $database;

        $database->MySQLDB();
        $cq = "SELECT * FROM tbl_users WHERE level != '0'";

        $ret = array();

        $cq .= " ORDER BY name ASC";

        $sql = $database->query($cq);
        $count = mysql_num_rows($sql);

        while($row = mysql_fetch_array($sql)) {
            $u = array();
            $u['id'] = $row['id'];
            $u['user'] = $row['user'];
            $ret[] = $u;
        }

        $database->Close();

        return $response->withHeader('Content-type', 'application/json')->write(json_encode($ret));
    }

    function get(Request $request, Response $response, $args) {

        global $database;

        $id = (int)$args['id'];

        $database->MySQLDB();
        $cq = "SELECT * FROM tbl_users WHERE id = " . ((int)$id);
        $sql = $database->query($cq);
        if ($row = mysql_fetch_array($sql)) {
            $u = array();
            $u["id"] = $row["id"];
            $u["user"] = $row["user"];
            $u["name"] = $row["name"];
            $u["email"] = $row["email"];
            $u["level"] = $row["level"];
            $u["timestamp"] = $row["timestamp"];
            $u["address"] = $row["address"];
            $u["phone"] = $row["phone"];
            $u["notify"] = $row["notify"];
            $u["contact"] = $row["contact"];
            $u["created_by"] = $row["created_by"];
            $u["active"] = $row["active"];

            return $response->withHeader('Content-type', 'application/json')->write(json_encode($u));
        }
        else
            return $response->withHeader('Content-type', 'application/json')->write(json_encode(null));
        
    }

    function create(Request $request, Response $response) {
        $vars = $request->getParsedBody();

        global $database;
        $database->MySQLDB();

        // cleanup
        $add_user_data_name = encode_html($vars['name']);
        $add_user_data_email = encode_html($vars['email']);
        $add_user_data_level = encode_html($vars['level']);
        $add_user_data_user = encode_html($vars['user']);
        $add_user_data_active = (isset($vars["active"])) ? 1 : 0;


        $new_user = new UserActions();
        $new_arguments = array(
            'id' => '',
            'username' => $add_user_data_user,
            'password' => $vars['pass'],
            'name' => $add_user_data_name,
            'email' => $add_user_data_email,
            'role' => $add_user_data_level,
            'active' => $add_user_data_active,
            'type' => 'new_user'
        );

        /** Validate the information from the posted data. */
        $new_validate = $new_user->validate_user($new_arguments);

        /** Create the user if validation is correct. */
        if ($new_validate == 1) {
            $new_response = $new_user->create_user($new_arguments);

            return $response->withHeader('Content-type', 'application/json')->write(json_encode($new_response));
        }
        else {
            global $valid_me;
            return $response->withStatus(400)->write($valid_me->error_msg);
        }
    }

    function del(Request $request, Response $response, $args) {

        $id = (int)$args['id'];

        if ($id == 0) {
            return $response->withStatus(400)->write('You cannot delete your own account.');
        }

        // force auth... TODO: provides auth
        $_SESSION['userlevel'] = 9;
        
        $this_user = new UserActions();
        $delete_user = $this_user->delete_user($id);

        return $response->withHeader('Content-type', 'application/json')->write(json_encode($delete_user));
    }
}
